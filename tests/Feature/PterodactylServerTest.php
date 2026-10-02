<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Email;
use App\Models\Extension;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\Role;
use App\Models\ServerAccount;
use App\Models\ServerConnection;
use App\Models\User;
use Extensions\Servers\Pterodactyl\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PterodactylServerTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected ServerConnection $connection;

    protected Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::preventStrayRequests();
        $this->customer = User::factory()->create(['status' => 'active']);

        Extension::query()->updateOrCreate(['identifier' => 'server-pterodactyl'], [
            'namespace' => Server::class,
            'type' => 'server',
            'name' => 'Pterodactyl Server',
            'status' => 'enabled',
            'version' => '1.0.0',
        ]);

        $this->connection = ServerConnection::query()->create([
            'alias' => 'pterodactyl-test',
            'extension_identifier' => 'server-pterodactyl',
            'status' => 'healthy',
            'is_active' => true,
            'config' => $this->credentials(),
        ]);

        $category = Category::query()->create([
            'name' => 'Game servers', 'slug' => 'games', 'status' => 'active', 'icon' => 'server',
        ]);

        $this->package = Package::query()->create([
            'category_id' => $category->id,
            'connection_id' => $this->connection->id,
            'name' => 'Minecraft Starter',
            'slug' => 'minecraft-starter',
            'status' => 'active',
            'data' => [
                'location_id' => 7, 'nest_id' => 1, 'egg_id' => 4,
                'memory_limit' => 1.5, 'disk_limit' => 10, 'swap_limit' => -1,
                'cpu_limit' => 200, 'cpu_pinning' => '0-1', 'block_io_weight' => 500,
                'database_limit' => 1, 'allocation_limit' => 2, 'backup_limit' => 3,
                'docker_image' => 'ghcr.io/pterodactyl/yolks:java_21',
                'startup' => 'java -jar server.jar',
                'environment' => ['SERVER_JARFILE' => 'server.jar', 'MINECRAFT_VERSION' => 'latest'],
            ],
        ]);
    }

    public function test_extension_is_discovered_and_can_be_enabled(): void
    {
        Extension::where('identifier', 'server-pterodactyl')->delete();
        Extension::discover();
        $extension = Extension::findOrFail('server-pterodactyl');
        $extension->enable();

        $this->assertTrue($extension->isEnabled());
        $this->assertInstanceOf(Server::class, $extension->extension());
        $this->assertContains('v3-alpha', $extension->extension()->getWemXVersion());
        $this->assertTrue($extension->extension()->canChangePassword());
    }

    public function test_connection_uses_application_auth_and_normalizes_trailing_slashes(): void
    {
        Http::fake(['https://panel.example.test/api/application/users*' => Http::response(['data' => []])]);
        $credentials = $this->credentials();
        $credentials['hostname'] .= '/';

        $this->assertSame('Connected to the Pterodactyl Application API.', Server::testConnection($credentials));
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer ptla_test_key')
            && $request->hasHeader('Accept', 'Application/vnd.Pterodactyl.v1+json')
            && $request['per_page'] === 1);
    }

    public function test_admin_can_open_the_pterodactyl_connection_form(): void
    {
        $this->app->instance('env', 'local');
        config(['app.installed' => true, 'app.license_bypass' => true]);
        $role = Role::query()->create(['name' => 'Pterodactyl tester', 'super_admin' => true]);
        DB::table('role_user')->insert([
            'user_id' => $this->customer->id, 'role_id' => $role->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->customer)
            ->withSession(['admin_reauthenticated_at' => now()->toDateTimeString()])
            ->get(route('admin.servers.connections.create', ['serverId' => 'server-pterodactyl']))
            ->assertOk()
            ->assertSee('Pterodactyl Server')
            ->assertSee('Application API Key');
        Http::assertNothingSent();
    }

    public function test_client_api_keys_are_rejected_before_sending_a_request(): void
    {
        $credentials = $this->credentials();
        $credentials['api_key'] = 'ptlc_client_key';

        try {
            Server::testConnection($credentials);
            $this->fail('A Client API key must not pass connection validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('api_key', $exception->errors());
            Http::assertNothingSent();
        }
    }

    public function test_rejected_credentials_throw_instead_of_reporting_success(): void
    {
        Http::fake(['*' => Http::response(['errors' => [['detail' => 'Forbidden']]], 403)]);
        $this->expectException(RequestException::class);

        Server::testConnection($this->credentials());
    }

    public function test_html_responses_do_not_pass_the_connection_test(): void
    {
        Http::fake(['*' => Http::response('<html>Login</html>', 200)]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Check the panel URL');

        Server::testConnection($this->credentials());
    }

    public function test_connection_errors_are_propagated(): void
    {
        Http::fake(['*' => Http::failedConnection()]);
        $this->expectException(ConnectionException::class);

        Server::testConnection($this->credentials());
    }

    public function test_package_fields_include_egg_environment_and_cache_the_egg(): void
    {
        Http::fake(['https://panel.example.test/api/application/nests/1/eggs/4*' => Http::response([
            'attributes' => [
                'startup' => 'java -jar {{SERVER_JARFILE}}',
                'docker_image' => 'java:21',
                'relationships' => ['variables' => ['data' => [['attributes' => [
                    'name' => 'Jar file', 'description' => 'Server jar', 'env_variable' => 'SERVER_JARFILE',
                    'default_value' => 'server.jar', 'rules' => 'required|string',
                ]]]]],
            ],
        ])]);

        $server = new Server;
        $fields = collect($server->setPackageConfig($this->package, $this->connection))->keyBy('key');
        $server->setPackageConfig($this->package, $this->connection);

        $this->assertSame(['required', 'string'], $fields['environment.SERVER_JARFILE']['rules']);
        $this->assertSame('java:21', $fields['docker_image']['default_value']);
        $this->assertTrue($fields->has(['memory_limit', 'backup_limit', 'cpu_pinning']));
        Http::assertSentCount(1);
    }

    public function test_checkout_checks_megabytes_and_continues_past_full_nodes(): void
    {
        $this->fakePanel();
        Server::eventAddToCart($this->package, ['memory_limit' => '2.5']);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/nodes/deployable')
            && $request['memory'] === 2560 && $request['disk'] === 10240
            && $request['location_ids'] === [7]);
        Http::assertSentCount(1);
    }

    public function test_checkout_searches_subsequent_node_pages(): void
    {
        Http::fake(['https://panel.example.test/api/application/nodes/deployable*' => Http::sequence()
            ->push(['data' => [$this->node(20, 7, true)], 'meta' => ['pagination' => ['total_pages' => 2]]])
            ->push(['data' => [$this->node(21, 7, false)], 'meta' => ['pagination' => ['total_pages' => 2]]])]);

        Server::eventAddToCart($this->package);

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request['page'] === 2);
    }

    public function test_create_provisions_on_the_correct_location_with_limits_and_environment(): void
    {
        $this->fakePanel();
        $order = $this->createOrder();
        $order->prices()->create(['key' => 'environment.MINECRAFT_VERSION', 'value' => '1.21', 'cycle_price' => 0, 'description' => 'Minecraft version']);

        (new Server)->create($order, $this->connection);

        $this->assertSame('101', (string) $order->fresh()->external_id);
        $this->assertSame('abc123', $order->fresh()->data['identifier']);
        $this->assertDatabaseHas('server_accounts', ['order_id' => $order->id, 'external_id' => 42]);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/servers')
            && $request['external_id'] === 'wemx_'.$order->id
            && $request['user'] === 42 && $request['egg'] === 4
            && $request['allocation']['default'] === 210
            && $request['limits'] === ['memory' => 1536, 'swap' => -1, 'disk' => 10240, 'io' => 500, 'cpu' => 200, 'threads' => '0-1']
            && $request['feature_limits'] === ['databases' => 1, 'allocations' => 2, 'backups' => 3]
            && $request['environment'] === ['SERVER_JARFILE' => 'server.jar', 'MINECRAFT_VERSION' => '1.21']);
    }

    public function test_new_accounts_store_encrypted_passwords_and_email_the_real_panel_link(): void
    {
        $this->customer->update(['first_name' => null, 'last_name' => null]);
        $this->fakePanel(['https://panel.example.test/api/application/users?*' => Http::response(['data' => []])]);
        $order = $this->createOrder();

        (new Server)->create($order, $this->connection);

        $account = ServerAccount::where('order_id', $order->id)->firstOrFail();
        $this->assertNotSame($account->password, $account->getRawOriginal('password'));
        $this->assertArrayNotHasKey('password', $account->data);
        $this->assertSame($this->connection->id, $account->data['connection_id']);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/users')
            && $request['password'] === $account->password
            && $request['first_name'] === $this->customer->username && $request['last_name'] === 'Customer');
        $email = Email::where('identifier', 'server.pterodactyl.account_created')->firstOrFail();
        $this->assertSame('https://panel.example.test', $email->button_url);
    }

    public function test_unavailable_capacity_does_not_create_remote_users_or_servers(): void
    {
        $this->fakePanel(['https://panel.example.test/api/application/nodes/deployable*' => Http::response([
            'data' => [$this->node(7, 99, false), $this->node(20, 7, true)],
        ])]);
        $order = $this->createOrder();

        try {
            (new Server)->create($order, $this->connection);
            $this->fail('Provisioning should fail without capacity.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('free allocation', $exception->getMessage());
            Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
            $this->assertNull($order->fresh()->external_id);
        }
    }

    public function test_user_lookup_failure_propagates_without_attempting_account_creation(): void
    {
        $this->fakePanel(['https://panel.example.test/api/application/users?*' => Http::response([], 500)]);

        try {
            (new Server)->create($this->createOrder(), $this->connection);
            $this->fail('The panel failure should propagate.');
        } catch (RequestException $exception) {
            $this->assertSame(500, $exception->response->status());
            Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
        }
    }

    public function test_failed_server_creation_does_not_mark_the_order_provisioned(): void
    {
        $this->fakePanel(['https://panel.example.test/api/application/servers' => Http::response([], 422)]);
        $order = $this->createOrder();

        try {
            (new Server)->create($order, $this->connection);
            $this->fail('The panel failure should propagate.');
        } catch (RequestException $exception) {
            $this->assertSame(422, $exception->response->status());
            $this->assertNull($order->fresh()->external_id);
        }
    }

    public function test_retry_recovers_the_existing_remote_server_without_another_post(): void
    {
        $this->fakePanel(['https://panel.example.test/api/application/servers/external/*' => Http::response([
            'attributes' => $this->panelServer(),
        ])]);
        $order = $this->createOrder();

        (new Server)->create($order, $this->connection);
        (new Server)->create($order->fresh(), $this->connection);

        $this->assertSame('101', (string) $order->fresh()->external_id);
        Http::assertSentCount(2);
        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
    }

    public function test_recovery_does_not_adopt_another_customers_server(): void
    {
        $this->fakePanel([
            'https://panel.example.test/api/application/servers/external/*' => Http::response(['attributes' => $this->panelServer()]),
            'https://panel.example.test/api/application/users/42' => Http::response([
                'attributes' => array_merge($this->panelUser(), ['email' => 'someone-else@example.test']),
            ]),
        ]);
        $order = $this->createOrder();

        try {
            (new Server)->create($order, $this->connection);
            $this->fail('Another customer server must not be adopted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('does not belong', $exception->getMessage());
            $this->assertNull($order->fresh()->external_id);
            $this->assertDatabaseMissing('server_accounts', ['order_id' => $order->id]);
            Http::assertNotSent(fn (Request $request) => $request->method() !== 'GET');
        }
    }

    public function test_remote_lookup_authentication_errors_do_not_trigger_creation(): void
    {
        $this->fakePanel(['https://panel.example.test/api/application/servers/external/*' => Http::response([], 403)]);
        $this->expectException(RequestException::class);

        (new Server)->create($this->createOrder(), $this->connection);
    }

    public function test_lifecycle_actions_call_the_application_api(): void
    {
        $this->fakePanel();
        $order = $this->provisionedOrder();
        $server = new Server;

        $server->suspend($order, $this->connection);
        $server->unsuspend($order, $this->connection);
        $server->terminate($order, $this->connection);

        foreach (['suspend', 'unsuspend'] as $action) {
            Http::assertSent(fn (Request $request) => $request->method() === 'POST' && str_ends_with($request->url(), '/101/'.$action));
        }
        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/servers/101'));
        Http::assertSentCount(3);
    }

    public function test_lifecycle_actions_reject_unprovisioned_orders(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not have a provisioned');

        (new Server)->suspend($this->createOrder(), $this->connection);
    }

    #[DataProvider('invalidServerIds')]
    public function test_lifecycle_actions_reject_invalid_server_ids_without_calling_the_panel(?string $serverId): void
    {
        $order = $this->createOrder();
        $order->update(['external_id' => $serverId]);
        $server = new Server;

        foreach (['suspend', 'unsuspend', 'terminate'] as $action) {
            try {
                $server->{$action}($order, $this->connection);
                $this->fail('An invalid server ID must not reach the panel.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString("Order #{$order->id}", $exception->getMessage());
                $this->assertStringContainsString('link its numeric panel server ID', $exception->getMessage());
            }
        }

        Http::assertNothingSent();
        $this->assertSame('pending', $order->fresh()->status);
    }

    /** @return array<string, array{0: ?string}> */
    public static function invalidServerIds(): array
    {
        return [
            'missing' => [null],
            'empty' => [''],
            'zero' => ['0'],
            'negative' => ['-1'],
            'fraction' => ['1.5'],
            'identifier instead of numeric ID' => ['abc123'],
            'extra path' => ['101/suspend'],
            'whitespace' => [' 101 '],
        ];
    }

    public function test_lifecycle_actions_normalize_a_trailing_slash_in_the_panel_url(): void
    {
        $this->fakePanel();
        $this->connection->update(['config' => array_merge($this->credentials(), ['hostname' => 'https://panel.example.test/'])]);
        $order = $this->provisionedOrder();
        $server = new Server;

        $server->suspend($order, $this->connection);
        $server->unsuspend($order, $this->connection);
        $server->terminate($order, $this->connection);

        Http::assertSentCount(3);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '.test//'));
    }

    public function test_lifecycle_api_errors_are_propagated_without_changing_order_status(): void
    {
        Http::fake(['*' => Http::response(['errors' => [['detail' => 'Method not allowed']]], 405)]);
        $order = $this->provisionedOrder();
        $server = new Server;

        foreach (['suspend', 'unsuspend', 'terminate'] as $action) {
            try {
                $server->{$action}($order, $this->connection);
                $this->fail('A failed panel action must not report success.');
            } catch (\Exception $exception) {
                $this->assertStringContainsString('status code: 405', $exception->getMessage());
            }
        }

        Http::assertSentCount(3);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_order_alerts_display_the_actual_failed_action(): void
    {
        $order = $this->createOrder();

        foreach (['create', 'suspend', 'unsuspend', 'terminate'] as $action) {
            $order->exceptions()->create(['action' => $action, 'message' => 'Panel action failed']);
        }

        $resolved = $order->exceptions()->create(['action' => 'terminate', 'message' => 'Resolved incident']);
        $resolved->resolve();

        Volt::test('admin_area.default.orders.livewire.order-alerts', ['order' => $order])
            ->assertSee('Failed to perform action "Create"', false)
            ->assertSee('Failed to perform action "Suspend"', false)
            ->assertSee('Failed to perform action "Unsuspend"', false)
            ->assertSee('Failed to perform action "Terminate"', false)
            ->assertDontSee('Resolved incident');
    }

    public function test_password_changes_resolve_the_user_from_the_correct_panel(): void
    {
        $this->fakePanel();
        $otherOrder = $this->createOrder();
        $otherOrder->createExternalUser([
            'external_id' => 999, 'username' => 'other-panel-user', 'password' => 'unchanged',
            'data' => ['id' => 999, 'connection_id' => 999],
        ]);
        $order = $this->provisionedOrder();

        (new Server)->changePassword($order, 'new-secure-password');

        Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
            && str_ends_with($request->url(), '/users/42') && $request['password'] === 'new-secure-password');
        $this->assertSame('new-secure-password', $order->getExternalUser()->password);
        $this->assertSame('unchanged', $otherOrder->getExternalUser()->password);
    }

    public function test_password_changes_reject_another_customers_panel_account(): void
    {
        $this->fakePanel(['https://panel.example.test/api/application/users/42' => Http::response([
            'attributes' => array_merge($this->panelUser(), ['email' => 'someone-else@example.test']),
        ])]);
        $order = $this->provisionedOrder();

        try {
            (new Server)->changePassword($order, 'new-secure-password');
            $this->fail('Changing another customer password must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('does not belong', $exception->getMessage());
            Http::assertNotSent(fn (Request $request) => $request->method() === 'PATCH');
        }
    }

    public function test_upgrades_apply_new_package_defaults_and_keep_purchased_options(): void
    {
        $this->fakePanel();
        $order = $this->provisionedOrder();
        $order->prices()->create(['key' => 'backup_limit', 'value' => '8', 'cycle_price' => 0, 'description' => 'Extra backups']);
        $newPackage = $this->package->replicate();
        $newPackage->name = 'Minecraft Plus';
        $newPackage->slug = 'minecraft-plus';
        $newPackage->data = array_merge($this->package->data, ['memory_limit' => 4, 'disk_limit' => 20]);
        $newPackage->save();
        $oldPrice = $this->price($this->package);
        $newPrice = $this->price($newPackage);

        (new Server)->upgradeOrDowngrade($order, $oldPrice, $newPrice, $this->connection);

        Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
            && str_ends_with($request->url(), '/servers/101/build')
            && $request['allocation'] === 210 && $request['limits']['memory'] === 4096
            && $request['limits']['disk'] === 20480 && $request['feature_limits']['backups'] === 8);
    }

    /** @return array{hostname: string, api_key: string} */
    protected function credentials(): array
    {
        return ['hostname' => 'https://panel.example.test', 'api_key' => 'ptla_test_key'];
    }

    protected function createOrder(): Order
    {
        return Order::withoutEvents(fn () => Order::query()->create([
            'user_id' => $this->customer->id, 'package_id' => $this->package->id,
            'status' => 'pending', 'cycle_price' => 1, 'period_in_days' => 30,
        ]));
    }

    protected function provisionedOrder(): Order
    {
        $order = $this->createOrder();
        $order->update(['external_id' => 101, 'data' => $this->panelServer()]);

        return $order;
    }

    protected function price(Package $package): PackagePrice
    {
        return PackagePrice::create(['package_id' => $package->id, 'period_in_days' => 30, 'price' => 10]);
    }

    /** @return array{id: int, email: string, username: string, first_name: string, last_name: string} */
    protected function panelUser(): array
    {
        return ['id' => 42, 'email' => $this->customer->email, 'username' => 'player42', 'first_name' => 'Test', 'last_name' => 'Player'];
    }

    /** @return array{id: int, identifier: string, user: int, allocation: int} */
    protected function panelServer(): array
    {
        return ['id' => 101, 'identifier' => 'abc123', 'user' => 42, 'allocation' => 210];
    }

    /** @return array{attributes: array<string, mixed>} */
    protected function node(int $id, int $location, bool $assigned): array
    {
        return ['attributes' => [
            'id' => $id, 'location_id' => $location,
            'relationships' => ['allocations' => ['data' => [['attributes' => [
                'id' => $id * 10, 'assigned' => $assigned,
            ]]]]],
        ]];
    }

    /** @param array<string, mixed> $overrides */
    protected function fakePanel(array $overrides = []): void
    {
        Http::fake(array_replace([
            'https://panel.example.test/api/application/servers/external/*' => Http::response([], 404),
            'https://panel.example.test/api/application/nodes/deployable*' => Http::response(['data' => [
                $this->node(7, 99, false), $this->node(20, 7, true), $this->node(21, 7, false),
            ]]),
            'https://panel.example.test/api/application/users?*' => Http::response(['data' => [['attributes' => $this->panelUser()]]]),
            'https://panel.example.test/api/application/users' => Http::response(['attributes' => $this->panelUser()], 201),
            'https://panel.example.test/api/application/users/42' => Http::response(['attributes' => $this->panelUser()]),
            'https://panel.example.test/api/application/servers' => Http::response(['attributes' => $this->panelServer()], 201),
            'https://panel.example.test/api/application/servers/101' => fn (Request $request) => $request->method() === 'DELETE'
                ? Http::response('', 204) : Http::response(['attributes' => $this->panelServer()]),
            'https://panel.example.test/api/application/servers/101/suspend' => Http::response('', 204),
            'https://panel.example.test/api/application/servers/101/unsuspend' => Http::response('', 204),
            'https://panel.example.test/api/application/servers/101/build' => Http::response(['attributes' => $this->panelServer()]),
        ], $overrides));
    }
}
