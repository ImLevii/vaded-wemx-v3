<?php

namespace Tests\Feature;

use App\Events\Users\UserCreated;
use App\Models\User;
use App\Services\BackupUserImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class BackupUserImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_customer_import_requires_an_existing_primary_administrator(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('primary administrator');
        (new BackupUserImporter)->import(['users' => [$this->wemxUser()]], [], true);
    }

    public function test_dry_run_rolls_back_users_addresses_and_identity_links(): void
    {
        $admin = User::factory()->create();
        $report = (new BackupUserImporter)->import(['users' => [$this->wemxUser()]], []);
        $this->assertSame(1, $report['created']['wemx']);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('addresses', 1);
        $this->assertNull($admin->fresh()->data);
        Queue::assertNothingPushed();
    }

    public function test_combined_import_preserves_hashes_merges_emails_and_retains_panel_identity(): void
    {
        User::factory()->create();
        Event::fake([UserCreated::class]);
        $wemx = $this->wemxUser();
        $panel = $this->panelUser();
        $panelOnly = $this->panelUser(21, 'panel-only@example.test');
        $report = (new BackupUserImporter)->import(['users' => [$wemx]], ['users' => [$panel, $panelOnly]], true);
        $this->assertSame(['wemx' => 1, 'pterodactyl' => 1], $report['created']);
        $this->assertSame($report['ids']['wemx'][10], $report['ids']['pterodactyl'][20]);
        $user = User::findOrFail($report['ids']['wemx'][10]);
        $this->assertSame($wemx['password'], $user->password);
        $this->assertTrue(Hash::check('wemx-password', $user->password));
        $this->assertSame('12.34000000', $user->balance);
        $this->assertSame('suspended', $user->status);
        $this->assertSame('customer@example.test', $user->email);
        $this->assertSame(20, $user->data['_legacy_pterodactyl']['id']);
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->roles()->exists());
        $this->assertNull($user->remember_token);
        $panelUser = User::findOrFail($report['ids']['pterodactyl'][21]);
        $this->assertSame($panelOnly['password'], $panelUser->password);
        $this->assertSame('Panel', $panelUser->first_name);
        $this->assertNull($panelUser->email_verified_at);
        $this->assertFalse($panelUser->isAdmin());
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('addresses', 3);
        Event::assertNotDispatched(UserCreated::class);
        Queue::assertNothingPushed();
    }

    public function test_existing_account_credentials_security_state_and_profile_are_retained(): void
    {
        User::factory()->create();
        $existing = User::factory()->create(['email' => 'customer@example.test', 'username' => 'current-name',
            'password' => Hash::make('current-password'), 'balance' => 99, 'status' => 'pending',
            'tfa_enabled' => true, 'tfa_secret' => 'existing-secret', 'updated_at' => '2025-01-01 00:00:00']);
        $before = $existing->fresh()->getAttributes();
        $importer = new BackupUserImporter;
        $tables = ['users' => [$this->wemxUser()]];
        $report = $importer->import($tables, [], true);
        $after = $existing->fresh()->getAttributes();
        unset($before['data'], $after['data']);
        $this->assertSame($before, $after);
        $this->assertSame(1, $report['existing_passwords_retained']);
        $rerun = $importer->import($tables, [], true);
        $this->assertSame(0, array_sum($rerun['created']));
        $this->assertSame(0, array_sum($rerun['linked']));
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('addresses', 2);
    }

    public function test_username_conflicts_use_a_unique_suffix(): void
    {
        User::factory()->create(['username' => 'legacy']);
        User::factory()->create(['username' => 'legacy-wemx-10-1']);
        $row = $this->wemxUser();
        $row['username'] = 'LEGACY';
        $report = (new BackupUserImporter)->import(['users' => [$row]], [], true);
        $this->assertSame('LEGACY-wemx-10-2', User::findOrFail($report['ids']['wemx'][10])->username);
        $this->assertSame(1, $report['usernames_renamed']);
    }

    #[DataProvider('unsafeSourceCases')]
    public function test_unsafe_source_records_are_rejected_without_changing_users(string $case): void
    {
        User::factory()->create();
        $row = $this->wemxUser();
        $wemx = ['users' => [$row]];
        $panel = [];
        if ($case === 'duplicate') {
            $wemx['users'][] = array_replace($row, ['id' => 11, 'email' => 'CUSTOMER@example.test']);
        } elseif ($case === 'hash') {
            $wemx['users'][0]['password'] = 'plaintext';
        } elseif ($case === 'wemx_two_factor') {
            $wemx['user_2fa'] = [['user_id' => 10, 'key' => 'legacy-secret']];
        } else {
            $panel['users'] = [array_replace($this->panelUser(), ['use_totp' => 1])];
        }
        try {
            (new BackupUserImporter)->import($wemx, $panel, true);
            $this->fail('Unsafe records must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertNotEmpty($exception->getMessage());
            $this->assertDatabaseCount('users', 1);
            $this->assertDatabaseCount('addresses', 1);
        }
    }

    public static function unsafeSourceCases(): array
    {
        return [['duplicate'], ['hash'], ['wemx_two_factor'], ['panel_two_factor']];
    }

    public function test_conflicting_previous_identity_rolls_back_all_new_users(): void
    {
        User::factory()->create();
        User::factory()->create(['email' => 'old-email@example.test', 'data' => ['_legacy_wemx' => ['id' => 11]]]);
        $rows = [$this->wemxUser(), array_replace($this->wemxUser(), ['id' => 11, 'email' => 'changed-email@example.test'])];
        try {
            (new BackupUserImporter)->import(['users' => $rows], [], true);
            $this->fail('An identity conflict must roll back the entire import.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('previously imported', $exception->getMessage());
            $this->assertDatabaseCount('users', 2);
            $this->assertDatabaseCount('addresses', 2);
        }
    }

    public function test_duplicate_normalized_destination_emails_are_rejected(): void
    {
        User::factory()->create(['email' => 'customer@example.test']);
        User::factory()->create(['email' => 'CUSTOMER@example.test']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('duplicate normalized emails');
        (new BackupUserImporter)->import(['users' => [$this->wemxUser()]], [], true);
    }

    public function test_command_validates_checksums_and_runs_without_a_panel_connection(): void
    {
        User::factory()->create();
        $root = sys_get_temp_dir().'/wemx-user-import-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($root.'/wemx/wemx');
        File::ensureDirectoryExists($root.'/pterodactyl');
        $hash = Hash::make('fixture-password');
        $files = ['wemx/wemx/users.sql' => "CREATE TABLE `users` (\n `id` bigint,\n `username` text,\n `email` text,\n `password` text\n);\nINSERT INTO `users` VALUES (10,'fixture','fixture@example.test','{$hash}');",
            'pterodactyl/users.sql' => "CREATE TABLE `users` (\n `id` bigint\n);",
            'wemx/wemx/user_2fa.sql' => "CREATE TABLE `user_2fa` (\n `id` bigint\n);"];
        try {
            $manifest = '';
            foreach ($files as $path => $contents) {
                File::put($root.'/'.$path, $contents);
                $manifest .= hash('sha256', $contents).'  ./'.$path."\n";
            }
            File::put($root.'/SHA256SUMS', $manifest);
            $this->artisan('app:import-user-backup', ['directory' => $root])->expectsOutput('Dry run passed; all changes rolled back.')->assertSuccessful();
            $this->assertDatabaseCount('users', 1);
            File::append($root.'/wemx/wemx/users.sql', '\n');
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('checksum verification failed');
            $this->artisan('app:import-user-backup', ['directory' => $root])->run();
        } finally {
            File::deleteDirectory($root);
        }
    }

    /** @return array<string, mixed> */
    protected function wemxUser(): array
    {
        return ['id' => 10, 'username' => 'legacy-customer', 'email' => ' CUSTOMER@example.test ',
            'first_name' => 'Customer', 'status' => 'suspended', 'balance' => '12.34', 'data' => '{}',
            'password' => Hash::make('wemx-password'), 'remember_token' => 'old-session'];
    }

    /** @return array<string, mixed> */
    protected function panelUser(int $id = 20, string $email = 'customer@example.test'): array
    {
        return ['id' => $id, 'uuid' => fake()->uuid(), 'username' => 'panel-'.$id, 'email' => $email,
            'name_first' => 'Panel', 'name_last' => 'Customer', 'root_admin' => 1, 'use_totp' => 0,
            'password' => Hash::make('panel-password')];
    }
}
