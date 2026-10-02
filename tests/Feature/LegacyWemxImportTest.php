<?php

namespace Tests\Feature;

use App\Events\Users\UserCreated;
use App\Models\Category;
use App\Models\Extension;
use App\Models\Package;
use App\Models\ServerConnection;
use App\Models\User;
use App\Services\LegacyWemxDumpReader;
use App\Services\LegacyWemxImporter;
use Extensions\Servers\Pterodactyl\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class LegacyWemxImportTest extends TestCase
{
    use RefreshDatabase;

    protected ServerConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Extension::query()->updateOrCreate(['identifier' => 'server-pterodactyl'], [
            'namespace' => Server::class, 'type' => 'server', 'name' => 'Pterodactyl', 'status' => 'enabled', 'version' => '1.0.0',
        ]);
        $this->connection = ServerConnection::create([
            'alias' => 'Import test', 'extension_identifier' => 'server-pterodactyl',
            'status' => 'healthy', 'is_active' => true, 'config' => [],
        ]);
    }

    public function test_dry_run_reports_counts_without_persisting_records(): void
    {
        $report = (new LegacyWemxImporter)->import($this->tables(), $this->connection, [5 => 1]);
        $this->assertSame(1, $report['created']['users']);
        $this->assertSame(1, $report['created']['prices']);
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('packages', 0);
        $this->assertDatabaseCount('users', 0);
        Queue::assertNothingPushed();
    }

    public function test_import_remaps_ids_preserves_hashes_and_converts_package_units(): void
    {
        $existing = User::factory()->create(['username' => 'legacy-user', 'email' => 'existing@example.test']);
        Category::create(['name' => 'Existing', 'slug' => 'existing', 'status' => 'active', 'icon' => 'server']);
        Event::fake([UserCreated::class]);
        $tables = $this->tables();
        $report = (new LegacyWemxImporter)->import($tables, $this->connection, [5 => 1], true);
        $user = User::findOrFail($report['ids']['users'][1]);
        $this->assertNotSame($existing->id, $user->id);
        $this->assertSame('legacy-user-legacy-1', $user->username);
        $this->assertSame($tables['users'][0]['password'], $user->password);
        $this->assertTrue(Hash::check('legacy-password', $user->password));
        $this->assertSame('12.34000000', $user->balance);
        $this->assertNull($user->remember_token);
        $this->assertSame('123 Example Street', $user->address->address);
        $this->assertSame('Suite 2', $user->address->address2);
        $this->assertCount(2, $user->data['_legacy_wemx']['addresses']);
        $package = Package::findOrFail($report['ids']['packages'][1]);
        $this->assertSame($report['ids']['categories'][1], $package->category_id);
        $this->assertSame(2, $package->data('memory_limit'));
        $this->assertSame(10, $package->data('disk_limit'));
        $this->assertSame(1, $package->data('nest_id'));
        $this->assertSame(1, $package->data('location_id'));
        $this->assertSame(30, $package->prices->first()->period_in_days);
        $this->assertSame('4.50000000', $package->prices->first()->price);
        $this->assertSame('2 GB memory', $package->features->first()->description);
        Event::assertNotDispatched(UserCreated::class);
        Queue::assertNothingPushed();
    }

    public function test_rerunning_preserves_existing_records_and_avoids_duplicates(): void
    {
        $importer = new LegacyWemxImporter;
        $tables = $this->tables();
        $first = $importer->import($tables, $this->connection, [5 => 1], true);
        $user = User::findOrFail($first['ids']['users'][1]);
        $user->forceFill(['balance' => 99, 'password' => Hash::make('current-password')])->saveQuietly();
        $package = Package::findOrFail($first['ids']['packages'][1]);
        $package->update(['data' => ['location_id' => 88]]);
        $second = $importer->import($tables, $this->connection, [5 => 1], true);
        $this->assertSame(0, array_sum($second['created']));
        $this->assertSame(['categories' => 1, 'packages' => 1, 'users' => 1], $second['matched']);
        $this->assertSame('99.00000000', $user->fresh()->balance);
        $this->assertTrue(Hash::check('current-password', $user->fresh()->password));
        $this->assertSame(88, $package->fresh()->data('location_id'));
        $this->assertDatabaseCount('package_prices', 1);
        $this->assertDatabaseCount('addresses', 1);
    }

    public function test_invalid_relationship_rolls_back_the_entire_import(): void
    {
        $tables = $this->tables();
        $tables['packages'][0]['category_id'] = 999;
        try {
            (new LegacyWemxImporter)->import($tables, $this->connection, [5 => 1], true);
            $this->fail('Missing category must abort the import.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Missing category', $exception->getMessage());
            $this->assertDatabaseCount('categories', 0);
            $this->assertDatabaseCount('packages', 0);
        }
    }

    public function test_incompatible_renewal_prices_are_not_silently_changed(): void
    {
        $tables = $this->tables();
        $tables['package_prices'][0]['renewal_price'] = '9.00';
        try {
            (new LegacyWemxImporter)->import($tables, $this->connection, [5 => 1], true);
            $this->fail('Different renewal pricing must be reviewed.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('renewal pricing', $exception->getMessage());
            $this->assertDatabaseCount('packages', 0);
            $this->assertDatabaseCount('categories', 0);
        }
    }

    public function test_reader_handles_mysql_escaping_without_executing_sql(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wemx-dump-');
        file_put_contents($path, <<<'SQL'
DROP TABLE users;
CREATE TABLE `categories` (
  `id` bigint NOT NULL,
  `name` text,
  `description` text
);
INSERT INTO `categories` VALUES
(1,'O\'Brien, hosting','line\npath\\file; DROP TABLE users;'),
(2,'It''s fine',NULL);
SQL);
        try {
            $rows = (new LegacyWemxDumpReader)->read($path)['categories'];
            $this->assertSame("O'Brien, hosting", $rows[0]['name']);
            $this->assertSame("line\npath\\file; DROP TABLE users;", $rows[0]['description']);
            $this->assertSame("It's fine", $rows[1]['name']);
            $this->assertNull($rows[1]['description']);
            $this->assertDatabaseCount('users', 0);
        } finally {
            unlink($path);
        }
    }

    public function test_reader_rejects_expressions_in_place_of_literals(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wemx-dump-');
        file_put_contents($path, "CREATE TABLE `users` (\n `id` bigint\n);\nINSERT INTO `users` VALUES (SLEEP(1));");
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('only literals');
            (new LegacyWemxDumpReader)->read($path);
        } finally {
            unlink($path);
        }
    }

    /** @return array<string, list<array<string, mixed>>> */
    protected function tables(): array
    {
        return [
            'categories' => [['id' => 1, 'name' => 'Legacy Games', 'link' => 'legacy-games', 'status' => 'active', 'icon' => 'server', 'order' => 2]],
            'packages' => [[
                'id' => 1, 'category_id' => 1, 'name' => 'Legacy Starter', 'service' => 'pterodactyl', 'status' => 'active', 'order' => 1,
                'data' => json_encode(['locations' => ['1'], 'egg' => '5', 'memory_limit' => '2048', 'disk_limit' => '10240', 'swap_limit' => '0']),
            ]],
            'package_prices' => [['id' => 1, 'package_id' => 1, 'type' => 'recurring', 'period' => 30, 'price' => '4.50', 'renewal_price' => '4.50']],
            'package_features' => [['id' => 1, 'package_id' => 1, 'description' => '2 GB memory', 'order' => 1]],
            'users' => [[
                'id' => 1, 'username' => 'legacy-user', 'email' => 'legacy@example.test', 'status' => 'active',
                'password' => Hash::make('legacy-password'), 'balance' => '12.34',
            ]],
            'addresses' => [
                ['id' => 1, 'user_id' => 1, 'address' => '123 Example Street', 'address_2' => 'Suite 2', 'country' => 'US'],
                ['id' => 2, 'user_id' => 1, 'address' => null, 'country' => null],
            ],
        ];
    }
}
