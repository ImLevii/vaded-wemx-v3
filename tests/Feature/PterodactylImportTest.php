<?php

namespace Tests\Feature;

use App\Events\Orders\OrderCreated;
use App\Events\Users\UserCreated;
use App\Models\Category;
use App\Models\Extension;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\ServerAccount;
use App\Models\ServerConnection;
use App\Models\User;
use App\Services\UserImportBackup;
use Extensions\Servers\Pterodactyl\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PterodactylImportTest extends TestCase
{
    use RefreshDatabase;

    protected ServerConnection $connection;

    protected PackagePrice $price;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::preventStrayRequests();
        $this->mock(UserImportBackup::class)->shouldReceive('create')->andReturn('/private/pre-import-snapshot.json');
        Extension::query()->updateOrCreate(['identifier' => 'server-pterodactyl'], [
            'namespace' => Server::class, 'type' => 'server', 'name' => 'Pterodactyl', 'status' => 'enabled', 'version' => '1.0.0',
        ]);
        $this->connection = ServerConnection::query()->create([
            'alias' => 'Import panel', 'extension_identifier' => 'server-pterodactyl',
            'config' => ['hostname' => 'https://panel.example.test', 'api_key' => 'ptla_test_key'],
            'status' => 'healthy', 'is_active' => true,
        ]);
        $category = Category::query()->create(['name' => 'Games', 'slug' => 'games', 'status' => 'active', 'icon' => 'server']);
        $package = Package::query()->create([
            'category_id' => $category->id, 'connection_id' => $this->connection->id,
            'name' => 'Minecraft', 'slug' => 'minecraft', 'status' => 'active', 'data' => ['egg_id' => 5],
        ]);
        $this->price = PackagePrice::query()->create([
            'package_id' => $package->id, 'price' => '30.00', 'period_in_days' => 30,
            'setup_fee' => '5.00', 'upgrade_fee' => '2.00', 'is_active' => true,
        ]);
    }

    public function test_dry_run_does_not_persist_or_dispatch_side_effects(): void
    {
        Event::fake([UserCreated::class, OrderCreated::class]);
        $this->fakeServers([$this->server()]);
        $this->artisan('app:import-pterodactyl', $this->importOptions(['--commit' => false]))
            ->expectsOutputToContain('Dry run passed')->expectsOutputToContain('Users created: 1')->assertSuccessful();
        foreach (['users', 'orders', 'server_accounts', 'addresses'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertNoSideEffects();
    }

    public function test_import_links_owners_servers_and_billing_without_reprovisioning(): void
    {
        Event::fake([UserCreated::class, OrderCreated::class]);
        $this->fakeServers([$this->server(), $this->server(43, suspended: true)]);
        $this->artisan('app:import-pterodactyl', $this->importOptions())->assertSuccessful();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('addresses', 1);
        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseCount('server_accounts', 2);
        $user = User::firstOrFail();
        $this->assertSame('owner@example.test', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertSame(0, $user->roles()->count());
        $order = Order::where('external_id', '42')->firstOrFail();
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame('1.00000000', $order->cycle_price);
        $this->assertSame(30, $order->period_in_days);
        $this->assertSame(now()->addDays(30)->toDateString(), $order->due_date->toDateString());
        $this->assertSame('0.00000000', $order->setup_fee);
        $this->assertFalse($order->auto_balance_renew);
        $this->assertSame('Imported server 42', $order->data['name']);
        $this->assertSame('active', $order->status);
        $this->assertSame('suspended', Order::where('external_id', '43')->firstOrFail()->status);
        $account = $order->getExternalUser();
        $this->assertSame('7', $account->external_id);
        $this->assertSame('panel-owner', $account->username);
        $this->assertSame('unknown', $account->password);
        $this->assertArrayNotHasKey('root_admin', $account->data);
        $this->assertNoSideEffects();
    }

    public function test_existing_user_is_matched_without_overwriting_credentials(): void
    {
        $user = User::factory()->create(['email' => 'OWNER@example.test', 'password' => Hash::make('keep-password'), 'status' => 'suspended']);
        $this->fakeServers([$this->server()]);
        $this->artisan('app:import-pterodactyl', $this->importOptions())
            ->expectsOutputToContain('existing users matched: 1')->assertSuccessful();
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($user->id, Order::firstOrFail()->user_id);
        $this->assertTrue(Hash::check('keep-password', $user->fresh()->password));
        $this->assertSame('suspended', $user->fresh()->status);
    }

    public function test_username_collision_keeps_accounts_distinct(): void
    {
        User::factory()->create(['username' => 'panel-owner', 'email' => 'different@example.test']);
        $this->fakeServers([$this->server()]);
        $this->artisan('app:import-pterodactyl', $this->importOptions())->assertSuccessful();
        $this->assertSame('panel-owner-ptero-7-1', User::where('email', 'owner@example.test')->firstOrFail()->username);
    }

    public function test_rerun_preserves_billing_and_repairs_a_missing_account(): void
    {
        $this->fakeServers([$this->server()]);
        $this->artisan('app:import-pterodactyl', $this->importOptions())->assertSuccessful();
        $order = Order::firstOrFail();
        $order->updateQuietly(['cycle_price' => '4.00']);
        ServerAccount::query()->delete();
        $this->artisan('app:import-pterodactyl', ['--connection' => $this->connection->id, '--commit' => true])
            ->expectsOutputToContain('existing orders skipped: 1')->assertSuccessful();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('server_accounts', 1);
        $this->assertSame('4.00000000', $order->fresh()->cycle_price);
    }

    public function test_all_pages_and_distinct_owners_are_imported(): void
    {
        $second = $this->server(43, 8);
        $second['relationships']['user']['attributes']['email'] = 'second@example.test';
        Http::fake(fn (Request $request) => Http::response($this->response([
            $request['page'] === 1 ? $this->server() : $second,
        ], 2)));
        $this->artisan('app:import-pterodactyl', $this->importOptions())->assertSuccessful();
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('orders', 2);
        Http::assertSentCount(2);
    }

    public function test_empty_panel_succeeds_without_creating_anything(): void
    {
        $this->fakeServers([]);
        $this->artisan('app:import-pterodactyl', ['--commit' => true])->assertSuccessful();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_per_server_price_override_supports_a_mixed_panel(): void
    {
        $package = Package::query()->create([
            'category_id' => $this->price->package->category_id, 'connection_id' => $this->connection->id,
            'name' => 'Rust', 'slug' => 'rust', 'status' => 'active', 'data' => ['egg_id' => 6],
        ]);
        $price = PackagePrice::query()->create([
            'package_id' => $package->id, 'price' => '60.00', 'period_in_days' => 30, 'is_active' => true,
        ]);
        $server = $this->server(43);
        $server['egg'] = 6;
        $this->fakeServers([$this->server(), $server]);
        $this->artisan('app:import-pterodactyl', $this->importOptions(['--server-price' => ['43:'.$price->id]]))->assertSuccessful();
        $this->assertSame($price->id, Order::where('external_id', '43')->firstOrFail()->package_price_id);
    }

    public function test_mismatched_server_rolls_back_the_entire_import(): void
    {
        $server = $this->server(43);
        $server['egg'] = 6;
        $this->fakeServers([$this->server(), $server]);
        $this->artisan('app:import-pterodactyl', $this->importOptions())
            ->expectsOutputToContain('package egg does not match server 43')->assertFailed();
        foreach (['users', 'orders', 'server_accounts', 'addresses'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_package_matching_uses_exact_resources_and_a_unique_active_price(): void
    {
        $this->price->package->update(['data' => ['egg_id' => 5, 'memory_limit' => 1, 'disk_limit' => 10, 'cpu_limit' => 200]]);
        $this->fakeServers([$this->server()]);
        $options = $this->importOptions(['--match-packages' => true]);
        unset($options['--package-price']);
        $this->artisan('app:import-pterodactyl', $options)->assertSuccessful();
        $this->assertSame($this->price->id, Order::firstOrFail()->package_price_id);
    }

    public function test_missing_or_ambiguous_package_matches_abort_without_importing(): void
    {
        $this->fakeServers([$this->server()]);
        $options = $this->importOptions(['--match-packages' => true]);
        unset($options['--package-price']);
        $this->artisan('app:import-pterodactyl', $options)->expectsOutputToContain('0 matching prices')->assertFailed();
        $this->price->package->update(['data' => ['egg_id' => 5, 'memory_limit' => 1, 'disk_limit' => 10, 'cpu_limit' => 200]]);
        PackagePrice::query()->create([
            'package_id' => $this->price->package_id, 'price' => '300.00', 'period_in_days' => 365, 'is_active' => true,
        ]);
        $this->artisan('app:import-pterodactyl', $options)->expectsOutputToContain('2 matching prices')->assertFailed();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('orders', 0);
        $options['--server-price'] = ['42:'.$this->price->id];
        $this->artisan('app:import-pterodactyl', $options)->assertSuccessful();
    }

    public function test_price_from_another_connection_is_rejected(): void
    {
        $other = ServerConnection::query()->create([
            'alias' => 'Other panel', 'extension_identifier' => 'server-pterodactyl', 'config' => [],
        ]);
        $this->price->package->update(['connection_id' => $other->id]);
        $this->fakeServers([$this->server()]);
        $this->artisan('app:import-pterodactyl', $this->importOptions())
            ->expectsOutputToContain('active price from this connection')->assertFailed();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_recurring_prices_require_an_explicit_future_due_date(): void
    {
        $this->fakeServers([$this->server()]);
        $options = $this->importOptions();
        unset($options['--due-date']);
        $this->artisan('app:import-pterodactyl', $options)
            ->expectsOutputToContain('Recurring orders require --due-date')->assertFailed();
        $this->artisan('app:import-pterodactyl', $this->importOptions(['--due-date' => now()->subDay()->toDateString()]))->assertFailed();
        $this->artisan('app:import-pterodactyl', $this->importOptions(['--due-date' => 'invalid']))->assertFailed();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_one_time_prices_need_no_renewal_date(): void
    {
        $this->price->update(['period_in_days' => 0]);
        $this->fakeServers([$this->server()]);
        $options = $this->importOptions();
        unset($options['--due-date']);
        $this->artisan('app:import-pterodactyl', $options)->assertSuccessful();
        $order = Order::firstOrFail();
        $this->assertNull($order->due_date);
        $this->assertSame('30.00000000', $order->cycle_price);
    }

    public function test_api_failure_on_a_later_page_leaves_no_partial_import(): void
    {
        Http::fake(fn (Request $request) => $request['page'] === 1
            ? Http::response($this->response([$this->server()], 2))
            : Http::response(['error' => 'forbidden'], 403));
        $this->artisan('app:import-pterodactyl', $this->importOptions())
            ->expectsOutputToContain('Unable to read Pterodactyl servers on page 2')->assertFailed();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_missing_owner_permissions_and_duplicate_servers_are_rejected(): void
    {
        $server = $this->server();
        unset($server['relationships']['user']['attributes']);
        $this->fakeServers([$server]);
        $this->artisan('app:import-pterodactyl', $this->importOptions())->assertFailed();
        $this->fakeServers([$this->server(), $this->server()]);
        $this->artisan('app:import-pterodactyl', $this->importOptions())->assertFailed();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_conflicting_existing_order_ownership_is_not_overwritten(): void
    {
        $this->fakeServers([$this->server()]);
        $this->artisan('app:import-pterodactyl', $this->importOptions())->assertSuccessful();
        $other = User::factory()->create();
        $order = Order::firstOrFail();
        $order->updateQuietly(['user_id' => $other->id]);
        $this->artisan('app:import-pterodactyl', $this->importOptions())
            ->expectsOutputToContain('conflicting ownership')->assertFailed();
        $this->assertSame($other->id, $order->fresh()->user_id);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_conflicting_external_account_is_not_overwritten(): void
    {
        $this->fakeServers([$this->server()]);
        $this->artisan('app:import-pterodactyl', $this->importOptions())->assertSuccessful();
        ServerAccount::firstOrFail()->updateQuietly(['external_id' => '999']);
        $this->artisan('app:import-pterodactyl', $this->importOptions())
            ->expectsOutputToContain('conflicting external account')->assertFailed();
        $this->assertSame('999', ServerAccount::firstOrFail()->external_id);
    }

    public function test_connection_credentials_and_concurrent_import_are_checked(): void
    {
        $this->artisan('app:import-pterodactyl', ['--connection' => 999])->assertFailed();
        $lock = Cache::lock('pterodactyl-import:'.$this->connection->id, 3600);
        $this->assertTrue($lock->get());
        try {
            $this->artisan('app:import-pterodactyl', $this->importOptions())
                ->expectsOutputToContain('already running')->assertFailed();
        } finally {
            $lock->release();
        }
        $this->connection->update(['config' => ['hostname' => 'https://panel.example.test', 'api_key' => 'ptlc_wrong_key']]);
        $this->artisan('app:import-pterodactyl', $this->importOptions())
            ->expectsOutputToContain('Application API key')->assertFailed();
        Http::assertNothingSent();
    }

    public function test_list_displays_local_ids_without_contacting_the_panel(): void
    {
        $this->artisan('app:import-pterodactyl', ['--list' => true])
            ->expectsOutputToContain('Import panel')->expectsOutputToContain('Minecraft')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_invalid_duplicate_and_unknown_price_mappings_are_rejected(): void
    {
        $this->artisan('app:import-pterodactyl', $this->importOptions(['--server-price' => ['bad']]))->assertFailed();
        $this->artisan('app:import-pterodactyl', $this->importOptions(['--server-price' => ['42:1', '42:2']]))->assertFailed();
        $this->artisan('app:import-pterodactyl', $this->importOptions(['--package-price' => 'invalid']))->assertFailed();
        $this->fakeServers([$this->server()]);
        $this->artisan('app:import-pterodactyl', $this->importOptions(['--server-price' => ['99:1']]))->assertFailed();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_selected_servers_restore_existing_customer_access_with_billing_held(): void
    {
        $customer = User::factory()->create(['email' => 'owner@example.test', 'data' => ['_legacy_pterodactyl' => ['id' => 7]]]);
        Event::fake([UserCreated::class, OrderCreated::class]);
        $servers = [];
        foreach ([42, 43, 44, 45] as $id) {
            $server = $this->server($id);
            $server['external_id'] = 'wmx-'.($id + 80);
            $servers['https://panel.example.test/api/application/servers/'.$id.'*'] = Http::response(['object' => 'server', 'attributes' => $server]);
        }
        Http::fake($servers);
        $options = $this->importOptions(['--server' => ['42', '43', '44', '45'], '--user' => $customer->id, '--billing-review' => true]);
        unset($options['--due-date']);
        $this->artisan('app:import-pterodactyl', $options)->assertSuccessful();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('orders', 4);
        $this->assertDatabaseCount('server_accounts', 4);
        foreach (Order::all() as $order) {
            $this->assertSame($customer->id, $order->user_id);
            $this->assertTrue($order->requiresBillingReview());
            $this->assertNull($order->due_date);
            $this->assertFalse($order->auto_balance_renew);
            $this->assertSame('0.00000000', $order->cycle_price);
            $this->assertSame($this->price->id, $order->package_price_id);
            $this->assertSame((int) $order->external_id + 80, $order->data['_import']['legacy_wemx_order_id']);
            $this->assertSame('7', $order->getExternalUser()->external_id);
        }
        $this->artisan('app:import-pterodactyl', $options)->expectsOutputToContain('existing orders skipped: 4')->assertSuccessful();
        $this->assertDatabaseCount('orders', 4);
        $this->assertDatabaseCount('server_accounts', 4);
        Http::assertNotSent(fn (Request $request): bool => parse_url($request->url(), PHP_URL_PATH) === '/api/application/servers');
        $this->assertNoSideEffects();
    }

    public function test_selected_server_ownership_and_missing_servers_abort_before_any_changes(): void
    {
        $customer = User::factory()->create(['email' => 'different@example.test']);
        Http::fake(['https://panel.example.test/api/application/servers/42*' => Http::response(['attributes' => $this->server()])]);
        $options = $this->importOptions(['--server' => ['42'], '--user' => $customer->id]);
        $this->artisan('app:import-pterodactyl', $options)->expectsOutputToContain('does not belong')->assertFailed();
        $customer->updateQuietly(['email' => 'owner@example.test', 'data' => ['_legacy_pterodactyl' => ['id' => 999]]]);
        $this->artisan('app:import-pterodactyl', $options)->expectsOutputToContain('does not belong')->assertFailed();
        Http::fake(['https://panel.example.test/api/application/servers/42*' => Http::response([], 404)]);
        $this->artisan('app:import-pterodactyl', $options)->assertFailed();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('server_accounts', 0);
    }

    public function test_billing_review_requires_explicit_scope_and_no_invented_due_date(): void
    {
        $customer = User::factory()->create(['email' => 'owner@example.test']);
        $this->artisan('app:import-pterodactyl', $this->importOptions(['--billing-review' => true]))->assertFailed();
        $this->artisan('app:import-pterodactyl', $this->importOptions(['--billing-review' => true, '--server' => ['42'], '--user' => $customer->id]))->assertFailed();
        $this->artisan('app:import-pterodactyl', $this->importOptions(['--server' => ['bad']]))->assertFailed();
        $this->artisan('app:import-pterodactyl', $this->importOptions(['--server' => ['42', '42']]))->assertFailed();
        Http::assertNothingSent();
    }

    public function test_selected_customer_id_is_used_even_when_email_has_surrounding_whitespace(): void
    {
        $customer = User::factory()->create(['email' => ' owner@example.test ']);
        Http::fake(['https://panel.example.test/api/application/servers/42*' => Http::response(['attributes' => $this->server()])]);
        $this->artisan('app:import-pterodactyl', $this->importOptions(['--server' => ['42'], '--user' => $customer->id]))->assertSuccessful();
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($customer->id, Order::firstOrFail()->user_id);
    }

    public function test_existing_order_must_belong_to_the_selected_customer_id(): void
    {
        Http::fake(fn (Request $request) => Http::response(str_contains($request->url(), '/servers/42')
            ? ['attributes' => $this->server()]
            : $this->response([$this->server()])));
        $this->artisan('app:import-pterodactyl', $this->importOptions())->assertSuccessful();
        $customer = User::factory()->create(['email' => 'OWNER@example.test']);
        $this->artisan('app:import-pterodactyl', $this->importOptions(['--server' => ['42'], '--user' => $customer->id]))
            ->expectsOutputToContain('conflicting ownership')->assertFailed();
        $this->assertDatabaseCount('orders', 1);
        $this->assertNotSame($customer->id, Order::firstOrFail()->user_id);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function importOptions(array $overrides = []): array
    {
        return array_replace([
            '--connection' => $this->connection->id, '--package-price' => $this->price->id,
            '--due-date' => now()->addDays(30)->toDateString(), '--commit' => true,
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function server(int $id = 42, int $ownerId = 7, bool $suspended = false): array
    {
        return [
            'id' => $id, 'uuid' => '123e4567-e89b-42d3-a456-426614174000',
            'identifier' => '123e4567', 'name' => 'Imported server '.$id, 'user' => $ownerId, 'egg' => 5,
            'status' => $suspended ? 'suspended' : null, 'suspended' => $suspended,
            'limits' => ['memory' => 1024, 'disk' => 10240, 'cpu' => 200],
            'relationships' => ['user' => ['object' => 'user', 'attributes' => [
                'id' => $ownerId, 'username' => 'panel-owner', 'email' => 'owner@example.test',
                'first_name' => 'Panel', 'last_name' => 'Owner', 'root_admin' => true,
            ]]],
        ];
    }

    /** @param list<array<string, mixed>> $servers */
    private function fakeServers(array $servers): void
    {
        Http::fake(['https://panel.example.test/api/application/servers*' => Http::response($this->response($servers))]);
    }

    /**
     * @param  list<array<string, mixed>>  $servers
     * @return array{data: list<array{object: string, attributes: array<string, mixed>}>, meta: array{pagination: array{total_pages: int}}}
     */
    private function response(array $servers, int $pages = 1): array
    {
        return ['data' => array_map(fn (array $server): array => ['object' => 'server', 'attributes' => $server], $servers),
            'meta' => ['pagination' => ['total_pages' => $pages]]];
    }

    private function assertNoSideEffects(): void
    {
        Queue::assertNothingPushed();
        Event::assertNotDispatched(UserCreated::class);
        Event::assertNotDispatched(OrderCreated::class);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('emails', 0);
        Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET');
    }
}
