<?php

namespace Tests\Feature;

use App\Handlers\OrderRenewalHandler;
use App\Handlers\OrderUpgradeHandler;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Extension;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\Payment;
use App\Models\ServerConnection;
use App\Models\Subscription;
use App\Models\User;
use App\Services\OrderUpgradeService;
use Extensions\Servers\Pterodactyl\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClientServiceUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Order $order;

    private Package $larger;

    private PackagePrice $target;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        Queue::fake();
        Http::preventStrayRequests();
        config(['app.installed' => true, 'app.license_key' => 'WMX-test']);
        Cache::put('lcs_checked_at', now(), 3600);
        Currency::query()->updateOrCreate(['currency' => 'USD'], ['display_name' => 'US Dollar', 'format' => '$1,0.00', 'market_rate' => 1, 'is_active' => true]);
        session()->put('currency', 'USD');
        $this->customer = User::factory()->create(['status' => 'active']);
        Extension::query()->updateOrCreate(['identifier' => 'server-pterodactyl'], [
            'namespace' => Server::class, 'type' => 'server', 'name' => 'Pterodactyl', 'status' => 'enabled', 'version' => '1.0.0',
        ]);
        $connection = ServerConnection::query()->create(['alias' => 'upgrade-test', 'extension_identifier' => 'server-pterodactyl',
            'config' => ['hostname' => 'https://panel.example.test', 'api_key' => 'ptla_test']]);
        $category = Category::query()->create(['name' => 'Minecraft', 'slug' => 'minecraft', 'status' => 'active', 'icon' => 'server']);
        $package = Package::query()->create(['category_id' => $category->id, 'connection_id' => $connection->id,
            'name' => 'Starter', 'slug' => 'starter', 'status' => 'active',
            'data' => ['nest_id' => 1, 'egg_id' => 2, 'memory_limit' => 2, 'disk_limit' => 20, 'cpu_limit' => 100,
                'swap_limit' => 0, 'backup_limit' => 2, 'database_limit' => 1, 'allocation_limit' => 1]]);
        $originalPrice = $package->prices()->create(['period_in_days' => 30, 'price' => 30, 'setup_fee' => 0, 'is_active' => true]);
        $this->larger = $package->replicate();
        $this->larger->fill(['name' => 'Larger', 'slug' => 'larger', 'data' => array_merge($package->data, ['memory_limit' => 4, 'cpu_limit' => 200])])->save();
        $this->target = $this->larger->prices()->create(['period_in_days' => 30, 'price' => 60, 'setup_fee' => 50, 'upgrade_fee' => 3, 'is_active' => true]);
        $this->order = Order::withoutEvents(fn () => Order::query()->create(['user_id' => $this->customer->id,
            'package_id' => $package->id, 'package_price_id' => $originalPrice->id, 'status' => 'active', 'external_id' => '101',
            'cycle_price' => 1, 'setup_fee' => 0, 'period_in_days' => 30, 'due_date' => now()->addDays(10),
            'last_renewed_at' => now()->subDays(20), 'auto_balance_renew' => true, 'data' => ['identifier' => 'abc123', 'name' => 'My server']]));
        $this->actingAs($this->customer);
    }

    public function test_customer_sees_eligible_upgrade_controls_and_clear_prorated_prices(): void
    {
        $this->get(route('orders.upgrade', $this->order))->assertOk()->assertSee('Larger')->assertSee('$60.00')->assertSee('$13.00')->assertSee('Existing add-ons are retained')->assertSee('name="quoted_at"', false)->assertDontSee('$50.00');
        $this->get(route('orders.view', $this->order))->assertOk()->assertSee('Upgrade service');
        Volt::test(client_view_path('orders.livewire.orders-table'))->assertSee('Upgrade')->assertSee(route('orders.upgrade', $this->order), false);
    }

    public function test_minecraft_plans_with_larger_java_heaps_are_offered_and_applied_after_payment(): void
    {
        $this->configureMinecraftPlans();
        $this->get(route('orders.upgrade', $this->order))->assertOk()
            ->assertSee('Minecraft | 4GB')->assertSee('4 GB')->assertSee('$11.99')->assertSee('$1.00')
            ->assertSee('Continue to payment');
        Volt::test(client_view_path('orders.livewire.orders-table'))
            ->assertSee(route('orders.upgrade', $this->order), false);

        $payment = $this->checkout();
        Http::assertNothingSent();
        $this->fakePanel($this->minecraftPanel());
        $payment->completed('TX-minecraft');

        $this->assertSame($this->larger->id, $this->order->fresh()->package_id);
        $this->assertTrue($this->order->fresh()->due_date->equalTo($this->order->due_date));
        $this->assertNotNull($payment->fresh()->data('upgrade_applied_at'));
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/101/build')
            && $request['allocation'] === 77 && $request['limits']['memory'] === 4096
            && $request['limits']['threads'] === null);
        Http::assertSent(fn ($request): bool => $request->method() === 'PATCH' && str_ends_with($request->url(), '/101/startup')
            && $request['startup'] === 'java -Xms4096M -Xmx4096M -XX:+UseG1GC -jar {{SERVER_JARFILE}} nogui'
            && $request['environment'] === $this->minecraftPanel()['container']['environment']
            && $request['egg'] === 2 && $request['image'] === 'ghcr.io/pterodactyl/yolks:java_21'
            && $request['skip_scripts'] === false);
        Http::assertSentCount(3);
    }

    public function test_minecraft_startup_failure_can_be_retried_after_the_remote_limits_have_already_changed(): void
    {
        $this->configureMinecraftPlans();
        $payment = $this->checkout();
        Http::fakeSequence()->push(['attributes' => $this->minecraftPanel()])
            ->push([], 204)->push([], 500)
            ->push(['attributes' => array_merge($this->minecraftPanel(), ['limits' => ['memory' => 4096]])])
            ->push([], 204)->push([], 200);

        $payment->completed('TX-startup-failed');
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertTrue($payment->fresh()->data('upgrade_failed'));
        $this->assertSame($this->order->package_id, $this->order->fresh()->package_id);

        $this->post(route('orders.upgrade.retry', ['order' => $this->order, 'payment' => $payment]))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame($this->larger->id, $this->order->fresh()->package_id);
        $this->assertFalse($payment->fresh()->data('upgrade_failed'));
        $this->assertSame('TX-startup-failed', $payment->fresh()->transaction_id);
        $this->assertSame(1, Payment::query()->count());
        Http::assertSentCount(6);
    }

    #[DataProvider('cpuAffinityChanges')]
    public function test_cpu_affinity_can_expand_without_excluding_larger_minecraft_plans(?string $oldPinning, ?string $newPinning, bool $allowed): void
    {
        $this->configureMinecraftPlans();
        $this->order->package->update(['data' => array_merge($this->order->package->data, ['cpu_pinning' => $oldPinning])]);
        $this->larger->update(['data' => array_merge($this->larger->data, ['cpu_pinning' => $newPinning])]);

        $response = $this->get(route('orders.upgrade', $this->order))->assertOk();
        if ($allowed) {
            $response->assertSee('Continue to payment');
            $this->post(route('orders.upgrade.purchase', $this->order), $this->upgradeInput())
                ->assertRedirect()->assertSessionHasNoErrors();
        } else {
            $response->assertDontSee('Continue to payment');
            $this->post(route('orders.upgrade.purchase', $this->order), $this->upgradeInput())
                ->assertSessionHasErrors('package_price_id');
        }
        Http::assertNothingSent();
    }

    /** @return array<string, array{?string, ?string, bool}> */
    public static function cpuAffinityChanges(): array
    {
        return [
            'remove pinning' => ['0-1', null, true],
            'more cores' => ['0-1', '0,1,3,4', true],
            'equivalent notation' => ['0-1', '0,1', true],
            'cover disjoint cores' => ['0,2', '0-3', true],
            'narrow cores retain existing access' => ['0-1', '0', true],
            'retain previously unpinned access' => [null, '0-1', true],
            'retain core zero' => ['0', '1', true],
            'malformed range' => ['0-1', '3-1', false],
        ];
    }

    #[DataProvider('liveJavaStartups')]
    public function test_java_heap_updates_retain_live_customizations_and_support_retries(string $startup, string $expectedStartup): void
    {
        $this->configureMinecraftPlans();
        $payment = $this->checkout();
        $panel = $this->minecraftPanel();
        $panel['container']['startup_command'] = $startup;
        $this->fakePanel($panel);

        $payment->completed('TX-custom-startup');

        $this->assertSame($this->larger->id, $this->order->fresh()->package_id);
        if ($startup === $expectedStartup) {
            Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/startup'));
            Http::assertSentCount(2);
        } else {
            Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/startup')
                && $request['startup'] === $expectedStartup);
            Http::assertSentCount(3);
        }
    }

    /** @return array<string, array{string, string}> */
    public static function liveJavaStartups(): array
    {
        return [
            'equivalent gigabyte values' => ['java -Xms3G -Xmx3G -jar server.jar', 'java -Xms4096M -Xmx4096M -jar server.jar'],
            'custom minimum heap' => ['java -Xms128M -Xmx3072M -jar server.jar', 'java -Xms128M -Xmx4096M -jar server.jar'],
            'custom maximum heap' => ['java -Xms128M -Xmx2048M -jar server.jar', 'java -Xms128M -Xmx2048M -jar server.jar'],
            'dynamic heap' => ['java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar server.jar', 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar server.jar'],
            'already upgraded' => ['java -Xms4096M -Xmx4096M -jar server.jar', 'java -Xms4096M -Xmx4096M -jar server.jar'],
            'custom flags' => ['java -Xms3072M -Xmx3072M -Dcustom=true -jar customer.jar', 'java -Xms4096M -Xmx4096M -Dcustom=true -jar customer.jar'],
            'quoted custom arguments' => ['java -Dmessage=" -Xmx3072M " -Xms3072M -Xmx3072M -jar server.jar', 'java -Dmessage=" -Xmx3072M " -Xms4096M -Xmx4096M -jar server.jar'],
        ];
    }

    public function test_heap_like_values_in_quoted_arguments_cannot_hide_a_startup_command_change(): void
    {
        $this->configureMinecraftPlans();
        $this->order->package->update(['data' => array_merge($this->order->package->data, [
            'startup' => 'java -Dmessage=" -Xmx3072M " -Xms3072M -Xmx3072M -jar server.jar',
        ])]);
        $this->larger->update(['data' => array_merge($this->larger->data, [
            'startup' => 'java -Dmessage=" -Xmx4096M " -Xms4096M -Xmx4096M -jar server.jar',
        ])]);

        $this->get(route('orders.upgrade', $this->order))->assertOk()->assertDontSee('Continue to payment');
        $this->post(route('orders.upgrade.purchase', $this->order), $this->upgradeInput())
            ->assertSessionHasErrors('package_price_id');

        $this->assertSame(0, Payment::query()->count());
        Http::assertNothingSent();
    }

    public function test_incomplete_live_startup_settings_fail_before_changing_remote_limits(): void
    {
        $this->configureMinecraftPlans();
        $payment = $this->checkout();
        $panel = $this->minecraftPanel();
        unset($panel['container']['environment']);
        $this->fakePanel($panel);

        $payment->completed('TX-invalid-panel');

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertTrue($payment->fresh()->data('upgrade_failed'));
        $this->assertSame($this->order->package_id, $this->order->fresh()->package_id);
        Http::assertNotSent(fn ($request): bool => $request->method() === 'PATCH');
        Http::assertSentCount(1);
    }

    public function test_memory_addons_remain_included_in_the_upgraded_java_heap(): void
    {
        $this->configureMinecraftPlans();
        $this->order->prices()->create(['description' => 'Extra RAM', 'type' => 'config_option',
            'key' => 'memory_limit', 'value' => '6', 'cycle_price' => 0.1]);
        $this->larger->update(['data' => array_merge($this->larger->data, ['backup_limit' => 3])]);
        $payment = $this->checkout();
        $this->fakePanel($this->minecraftPanel());

        $payment->completed('TX-memory-addon');

        $this->assertSame($this->larger->id, $this->order->fresh()->package_id);
        $this->assertSame(1, $this->order->prices()->count());
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/build') && $request['limits']['memory'] === 6144);
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/startup')
            && $request['startup'] === 'java -Xms6144M -Xmx6144M -XX:+UseG1GC -jar {{SERVER_JARFILE}} nogui');
    }

    public function test_healthy_connection_with_purchase_protection_allows_the_complete_upgrade_flow(): void
    {
        $this->order->package->serverConnection->update(['status' => 'healthy', 'prevent_purchasing' => true]);

        $this->get(route('orders.upgrade', $this->order))->assertOk()
            ->assertSee('Larger')->assertSee('Continue to payment')
            ->assertDontSee('Self-service upgrades are unavailable');
        $this->get(route('orders.view', $this->order))->assertOk()->assertSee('Upgrade service');
        Volt::test(client_view_path('orders.livewire.orders-table'))
            ->assertSee(route('orders.upgrade', $this->order), false);

        $payment = $this->checkout();
        app(OrderUpgradeService::class)->assertPayable($payment->fresh());
        $this->assertSame($this->order->package_id, $this->order->fresh()->package_id);
        Http::assertNothingSent();

        $this->fakePanel();
        $payment->completed('TX-protected-connection');

        $this->assertSame($this->larger->id, $this->order->fresh()->package_id);
        $this->assertNotNull($payment->fresh()->data('upgrade_applied_at'));
        Http::assertSentCount(2);
    }

    #[DataProvider('connectionHealthStates')]
    public function test_connection_purchase_protection_depends_on_health(string $status, bool $preventPurchasing, bool $canUpgrade): void
    {
        $input = $this->upgradeInput();
        $this->order->package->serverConnection->update(['status' => $status, 'prevent_purchasing' => $preventPurchasing]);

        $response = $this->get(route('orders.upgrade', $this->order))->assertOk();
        if ($canUpgrade) {
            $response->assertSee('Continue to payment');
            $this->post(route('orders.upgrade.purchase', $this->order), $input)
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame(1, Payment::query()->count());
        } else {
            $response->assertDontSee('Continue to payment');
            $this->post(route('orders.upgrade.purchase', $this->order), $input)
                ->assertSessionHasErrors('package_price_id');
            $this->assertSame(0, Payment::query()->count());
        }
        Http::assertNothingSent();
    }

    /** @return array<string, array{string, bool, bool}> */
    public static function connectionHealthStates(): array
    {
        return [
            'healthy protected' => ['healthy', true, true],
            'unavailable protected' => ['unavailable', true, false],
            'unknown protected' => ['unknown', true, false],
            'healthy unprotected' => ['healthy', false, true],
            'unavailable unprotected' => ['unavailable', false, true],
            'unknown unprotected' => ['unknown', false, true],
        ];
    }

    public function test_paid_upgrade_waits_for_protected_connection_to_recover_and_retries_without_another_charge(): void
    {
        $this->order->package->serverConnection->update(['status' => 'healthy', 'prevent_purchasing' => true]);
        $payment = $this->checkout();
        $this->order->package->serverConnection->update(['status' => 'unavailable']);

        $payment->completed('TX-connection-offline');

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertTrue($payment->fresh()->data('upgrade_failed'));
        $this->assertSame($this->order->package_id, $this->order->fresh()->package_id);
        Http::assertNothingSent();

        $this->order->package->serverConnection->update(['status' => 'healthy']);
        $this->fakePanel();
        $this->post(route('orders.upgrade.retry', ['order' => $this->order, 'payment' => $payment]))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame($this->larger->id, $this->order->fresh()->package_id);
        $this->assertFalse($payment->fresh()->data('upgrade_failed'));
        $this->assertSame('TX-connection-offline', $payment->fresh()->transaction_id);
        $this->assertSame(1, Payment::query()->count());
        Http::assertSentCount(2);
    }

    public function test_checkout_preserves_the_service_until_payment_and_reuses_duplicate_requests(): void
    {
        $payment = $this->checkout();
        $this->assertSame('unpaid', $payment->status);
        $this->assertSame(13.0, (float) $payment->subtotal);
        $this->assertSame(OrderUpgradeHandler::class, $payment->handler);
        $this->assertSame($this->order->package_id, $this->order->fresh()->package_id);
        $this->assertSame($payment->id, $this->checkout()->id);
        $this->assertSame(1, Payment::query()->count());
        Http::assertNothingSent();
    }

    public function test_paid_upgrade_updates_existing_server_and_billing_once_preserving_addons(): void
    {
        $this->order->prices()->create(['description' => 'Extra CPU', 'type' => 'config_option', 'key' => 'cpu_limit', 'value' => '300', 'cycle_price' => 0.1, 'is_active' => true]);
        $payment = $this->checkout();
        $this->get(route('orders.upgrade', $this->order))->assertOk()->assertSee('$63.00');
        $this->fakePanel();
        $payment->completed('TX-upgrade');
        $payment->fresh()->completed('duplicate');
        (new OrderUpgradeHandler)->onPaymentCompleted($payment->fresh());
        $order = $this->order->fresh();
        $this->assertSame($this->larger->id, $order->package_id);
        $this->assertSame($this->target->id, $order->package_price_id);
        $this->assertSame(2.0, (float) $order->cycle_price);
        $this->assertSame('101', $order->external_id);
        $this->assertTrue($order->due_date->equalTo($this->order->due_date));
        $this->assertTrue($order->last_renewed_at->equalTo($this->order->last_renewed_at));
        $this->assertTrue($order->auto_balance_renew);
        $this->assertSame($this->order->data, $order->data);
        $this->assertSame(63.0, (float) $order->price);
        $this->assertSame(0.0, (float) $order->setup_fee);
        $this->assertSame(1, $order->prices()->count());
        $this->assertNotNull($payment->fresh()->data('upgrade_applied_at'));
        $this->assertSame(1, $order->logs()->where('action', 'order_upgraded')->count());
        Http::assertSent(fn ($request): bool => $request->method() === 'PATCH' && $request->url() === 'https://panel.example.test/api/application/servers/101/build'
            && $request['allocation'] === 77 && $request['limits']['memory'] === 4096 && $request['limits']['cpu'] === '300');
        Http::assertSentCount(2);
        $this->get(route('payments.view', $payment->token))->assertOk()->assertSee('Your service has been upgraded.');
        $this->get(route('orders.upgrade', $this->order))->assertOk()->assertSee('Your latest service upgrade is complete.');
    }

    public function test_remote_failure_preserves_billing_and_the_paid_upgrade_can_be_retried_without_charging(): void
    {
        $payment = $this->checkout();
        Http::fakeSequence()->push([], 500)->push(['attributes' => ['allocation' => 77]], 200)->push([], 200);
        $payment->completed('TX-paid');
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertTrue($payment->fresh()->data('upgrade_failed'));
        $this->assertSame($this->order->package_id, $this->order->fresh()->package_id);
        $this->assertSame(1.0, (float) $this->order->fresh()->cycle_price);
        $this->get(route('orders.upgrade', $this->order))->assertOk()->assertSee('Retry paid upgrade');
        $this->post(route('orders.upgrade.retry', ['order' => $this->order, 'payment' => $payment]))->assertRedirect()->assertSessionHas('success');
        $this->assertSame($this->larger->id, $this->order->fresh()->package_id);
        $this->assertFalse($payment->fresh()->data('upgrade_failed'));
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame('TX-paid', $payment->fresh()->transaction_id);
    }

    public function test_other_customers_guests_and_team_members_cannot_upgrade(): void
    {
        $input = $this->upgradeInput();
        $payment = $this->checkout();
        $payment->update(['status' => 'paid']);
        $other = User::factory()->create(['status' => 'active']);
        $this->order->members()->create(['user_id' => $other->id, 'email' => $other->email, 'status' => 'active']);
        $this->actingAs($other);
        $this->get(route('orders.upgrade', $this->order))->assertForbidden();
        $this->post(route('orders.upgrade.purchase', $this->order), $input)->assertForbidden();
        $this->post(route('orders.upgrade.retry', ['order' => $this->order, 'payment' => $payment]))->assertForbidden();
        auth()->logout();
        $this->post(route('orders.upgrade.purchase', $this->order), $input)->assertRedirect();
        Http::assertNothingSent();
    }

    public function test_confirmation_and_valid_server_generated_quote_are_required(): void
    {
        $input = $this->upgradeInput();
        unset($input['confirm_upgrade']);
        $this->post(route('orders.upgrade.purchase', $this->order), $input)->assertSessionHasErrors('confirm_upgrade');
        $input = $this->upgradeInput();
        $input['quote_token'] = str_repeat('0', 64);
        $this->post(route('orders.upgrade.purchase', $this->order), $input)->assertSessionHasErrors('package_price_id');
        $this->assertSame(0, Payment::query()->count());
    }

    #[DataProvider('unavailableServices')]
    public function test_ineligible_services_cannot_purchase_upgrades(array $changes): void
    {
        $input = $this->upgradeInput();
        $this->order->update($changes);
        $this->post(route('orders.upgrade.purchase', $this->order), $input)->assertSessionHasErrors('package_price_id');
        $this->assertSame(0, Payment::query()->count());
        Http::assertNothingSent();
    }

    public static function unavailableServices(): array
    {
        return [
            'suspended' => [['status' => 'suspended']], 'pending' => [['status' => 'pending']],
            'terminated' => [['status' => 'terminated']], 'one-time' => [['period_in_days' => 0]],
            'unprovisioned' => [['external_id' => null]], 'invalid server' => [['external_id' => 'abc']],
            'missing billing plan' => [['package_price_id' => null]], 'missing due date' => [['due_date' => null]],
            'billing review' => [['data' => ['_import' => ['billing_review_required' => true]]]],
        ];
    }

    #[DataProvider('incompatiblePlans')]
    public function test_incompatible_plans_are_hidden_and_rejected(array $packageChanges, array $priceChanges): void
    {
        $input = $this->upgradeInput();
        if (isset($packageChanges['data'])) {
            $packageChanges['data'] = array_merge($this->larger->data, $packageChanges['data']);
        }
        $this->larger->update($packageChanges);
        $this->target->update($priceChanges);
        $this->get(route('orders.upgrade', $this->order))->assertOk()->assertDontSee('Continue to payment');
        $this->post(route('orders.upgrade.purchase', $this->order), $input)->assertSessionHasErrors('package_price_id');
        $this->assertSame(0, Payment::query()->count());
    }

    public static function incompatiblePlans(): array
    {
        return [
            'disabled plan' => [['status' => 'disabled'], []], 'unlisted plan' => [['status' => 'unlisted'], []],
            'restricted plan' => [['status' => 'restricted'], []], 'inactive price' => [[], ['is_active' => false]],
            'different cycle' => [[], ['period_in_days' => 365]], 'cheaper plan' => [[], ['price' => 15]],
            'same price' => [[], ['price' => 30]], 'different egg' => [['data' => ['egg_id' => 99]], []],
            'different image' => [['data' => ['docker_image' => 'java:17']], []],
            'different startup command' => [['data' => ['startup' => 'java -jar other.jar']], []],
            'less disk' => [['data' => ['disk_limit' => 10]], []], 'less backup' => [['data' => ['backup_limit' => 1]], []],
            'no larger resources' => [['data' => ['memory_limit' => 2, 'cpu_limit' => 100]], []],
            'sold out' => [['global_quantity' => 0], []], 'no client capacity' => [['client_quantity' => 0], []],
        ];
    }

    public function test_termination_overdue_and_disabled_connections_block_upgrades(): void
    {
        $input = $this->upgradeInput();
        $this->order->update(['termination_requested_at' => now()]);
        $this->post(route('orders.upgrade.purchase', $this->order), $input)->assertSessionHasErrors('package_price_id');
        $this->order->update(['termination_requested_at' => null, 'due_date' => now()->subDay()]);
        $this->post(route('orders.upgrade.purchase', $this->order), $input)->assertSessionHasErrors('package_price_id');
        $this->order->update(['due_date' => now()->addDays(10)]);
        $this->order->package->serverConnection->update(['is_active' => false]);
        $this->post(route('orders.upgrade.purchase', $this->order), $input)->assertSessionHasErrors('package_price_id');
    }

    public function test_pending_subscriptions_and_renewal_invoices_block_upgrades(): void
    {
        $input = $this->upgradeInput();
        $subscription = Subscription::query()->create(['user_id' => $this->customer->id, 'subscribable_type' => Order::class,
            'subscribable_id' => $this->order->id, 'status' => 'pending', 'description' => 'Pending subscription', 'amount' => 30, 'frequency' => 30]);
        $this->get(route('orders.upgrade', $this->order))->assertOk()->assertSee('Cancel the recurring subscription');
        $this->post(route('orders.upgrade.purchase', $this->order), $input)->assertSessionHasErrors('package_price_id');
        $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $this->order->payments()->create(['user_id' => $this->customer->id, 'handler' => OrderRenewalHandler::class, 'description' => 'Renewal', 'subtotal' => 30]);
        $this->post(route('orders.upgrade.purchase', $this->order), $input)->assertSessionHasErrors('package_price_id');
        Http::assertNothingSent();
    }

    public function test_changed_quotes_are_rejected_before_charging(): void
    {
        $input = $this->upgradeInput();
        $this->target->update(['price' => 90]);
        $this->post(route('orders.upgrade.purchase', $this->order), $input)->assertSessionHasErrors('package_price_id');
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_quote_remains_payable_at_the_confirmed_price_while_the_customer_reads_the_page(): void
    {
        $quote = $this->get(route('orders.upgrade', $this->order))->assertOk()->viewData('upgradeOptions')->first();
        $input = ['package_price_id' => $quote['price']->id, 'quote_token' => $quote['token'],
            'quoted_at' => $quote['quoted_at'], 'confirm_upgrade' => '1'];
        $this->travel(10)->minutes();

        $this->post(route('orders.upgrade.purchase', $this->order), $input)
            ->assertRedirect()->assertSessionHasNoErrors();

        $payment = Payment::query()->where('handler', OrderUpgradeHandler::class)->sole();
        $this->assertSame(13.0, (float) $payment->subtotal);
        $this->assertSame($this->order->package_id, $this->order->fresh()->package_id);
        Http::assertNothingSent();
    }

    public function test_quote_timestamp_is_required_and_cannot_be_in_the_future(): void
    {
        $input = $this->upgradeInput();
        unset($input['quoted_at']);
        $this->post(route('orders.upgrade.purchase', $this->order), $input)
            ->assertSessionHasErrors('quoted_at');

        $input = $this->upgradeInput();
        $input['quoted_at'] = now()->addMinute()->getTimestamp();
        $this->post(route('orders.upgrade.purchase', $this->order), $input)
            ->assertSessionHasErrors('package_price_id');

        $this->assertSame(0, Payment::query()->count());
        Http::assertNothingSent();
    }

    public function test_expired_quotes_are_rejected_before_creating_an_invoice(): void
    {
        $this->target->update(['price' => 30.0001]);
        $input = $this->upgradeInput();
        $this->travel(16)->minutes();

        $this->post(route('orders.upgrade.purchase', $this->order), $input)
            ->assertSessionHasErrors('package_price_id');

        $this->assertSame(0, Payment::query()->count());
        Http::assertNothingSent();
    }

    public function test_quote_timestamp_cannot_be_changed_to_extend_its_validity(): void
    {
        $input = $this->upgradeInput();
        $this->travel(2)->seconds();
        $input['quoted_at'] = now()->getTimestamp();

        $this->post(route('orders.upgrade.purchase', $this->order), $input)
            ->assertSessionHasErrors('package_price_id');

        $this->assertSame(0, Payment::query()->count());
        Http::assertNothingSent();
    }

    public function test_invalid_quotes_cannot_reuse_an_existing_upgrade_invoice(): void
    {
        $payment = $this->checkout();
        $input = $this->upgradeInput();
        $input['quote_token'] = str_repeat('0', 64);

        $this->post(route('orders.upgrade.purchase', $this->order), $input)
            ->assertSessionHasErrors('package_price_id');

        $this->assertSame(1, Payment::query()->count());
        $this->assertNull($payment->fresh()->data('upgrade_superseded_at'));
        Http::assertNothingSent();
    }

    public function test_service_changes_after_payment_creation_do_not_apply_a_stale_upgrade(): void
    {
        $payment = $this->checkout();
        $this->order->update(['due_date' => now()->addDays(40)]);
        $payment->completed('TX-stale');
        $this->assertTrue($payment->fresh()->data('upgrade_failed'));
        $this->assertSame($this->order->package_id, $this->order->fresh()->package_id);
        Http::assertNothingSent();
    }

    public function test_stock_is_transferred_only_after_successful_upgrade(): void
    {
        $this->larger->update(['global_quantity' => 1]);
        $this->order->package->update(['global_quantity' => 0]);
        $payment = $this->checkout();
        $this->fakePanel();
        $payment->completed('TX-stock');
        $this->assertSame(0, $this->larger->fresh()->global_quantity);
        $this->assertSame(1, $this->order->package->fresh()->global_quantity);
    }

    public function test_superseded_invoices_cannot_be_paid_and_paid_failed_upgrades_block_new_purchases(): void
    {
        $first = $this->checkout();
        $this->target->update(['price' => 90]);
        $second = $this->checkout();
        $this->assertNotSame($first->id, $second->id);
        $this->assertNotNull($first->fresh()->data('upgrade_superseded_at'));
        try {
            $first->fresh()->payWith(1);
            $this->fail('Superseded invoices must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment_id', $exception->errors());
        }
        $second->update(['status' => 'paid', 'data' => array_merge($second->data, ['upgrade_failed' => true])]);
        $this->target->update(['price' => 120]);
        $this->post(route('orders.upgrade.purchase', $this->order), $this->upgradeInput())->assertSessionHasErrors('package_price_id');
        Http::assertNothingSent();
    }

    public function test_different_connections_service_types_and_unsupported_providers_are_rejected(): void
    {
        $input = $this->upgradeInput();
        $connection = ServerConnection::query()->create(['alias' => 'different', 'extension_identifier' => 'server-pterodactyl']);
        $this->larger->update(['connection_id' => $connection->id]);
        $this->post(route('orders.upgrade.purchase', $this->order), $input)->assertSessionHasErrors('package_price_id');
        $category = Category::query()->create(['name' => 'Other', 'slug' => 'other', 'status' => 'active', 'icon' => 'server']);
        $this->larger->update(['connection_id' => $this->order->package->connection_id, 'category_id' => $category->id,
            'data' => array_merge($this->larger->data, ['egg_id' => 99])]);
        $this->post(route('orders.upgrade.purchase', $this->order), $input)->assertSessionHasErrors('package_price_id');
        $this->order->package->serverConnection->update(['extension_identifier' => 'server-universal']);
        $this->post(route('orders.upgrade.purchase', $this->order), $input)->assertSessionHasErrors('package_price_id');
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_compatible_plans_in_other_catalog_categories_are_offered_and_applied(): void
    {
        $category = Category::query()->create(['name' => 'Premium', 'slug' => 'premium', 'status' => 'active', 'icon' => 'server']);
        $this->larger->update(['category_id' => $category->id]);
        $this->get(route('orders.upgrade', $this->order))->assertOk()->assertSee('Larger')->assertSee('Continue to payment');
        $this->fakePanel();
        $payment = $this->checkout();
        $payment->completed('TX-cross-category');
        $this->assertSame($this->larger->id, $this->order->fresh()->package_id);
        $this->assertNotNull($payment->fresh()->data('upgrade_applied_at'));
    }

    #[DataProvider('serviceTypes')]
    public function test_upgrades_work_for_each_hosted_service_type(string $name, int $egg, string $startup): void
    {
        $data = array_merge($this->order->package->data, ['egg_id' => $egg, 'startup' => $startup]);
        $this->order->package->update(['name' => $name.' Starter', 'data' => $data]);
        $this->larger->update(['name' => $name.' Larger', 'data' => array_merge($data, ['memory_limit' => 4, 'cpu_limit' => 200])]);
        $dueDate = $this->order->due_date;
        Volt::test(client_view_path('orders.livewire.orders-table'))->assertSee(route('orders.upgrade', $this->order), false);
        $this->get(route('orders.upgrade', $this->order))->assertOk()->assertSee($name.' Larger')->assertSee('Continue to payment');
        $this->fakePanel();
        $payment = $this->checkout();
        $payment->completed('TX-'.$egg);
        $upgraded = $this->order->fresh();
        $this->assertSame($this->larger->id, $upgraded->package_id);
        $this->assertSame('101', $upgraded->external_id);
        $this->assertTrue($dueDate->equalTo($upgraded->due_date));
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/servers/101/build')
            && $request['limits']['memory'] === 4096 && $request['limits']['cpu'] === 200);
        Http::assertSentCount(2);
        $this->assertNotNull($payment->fresh()->data('upgrade_applied_at'));
    }

    /** @return array<string, array{string, int, string}> */
    public static function serviceTypes(): array
    {
        return [
            'VPS' => ['VPS', 44, '/home/container/.vaded-runtime/start.mjs'],
            'Discord bots' => ['Bot Hosting', 20, 'node {{BOT_JS_FILE}}'],
            'Lavalink' => ['Lavalink', 41, 'java -jar Lavalink.jar'],
            'Rust' => ['Rust', 26, './RustDedicated -batchmode +server.port {{SERVER_PORT}}'],
            'Unturned' => ['Unturned', 22, './Unturned_Headless.x86_64 -port {{SERVER_PORT}}'],
            'DayZ' => ['DayZ', 42, './DayZServer -port={{SERVER_PORT}}'],
        ];
    }

    #[DataProvider('minecraftTiers')]
    public function test_every_minecraft_tier_with_a_larger_plan_can_upgrade_without_losing_cpu_access(int $sourceMemory, int $targetMemory, ?string $sourcePinning, ?string $targetPinning, ?string $expectedPinning): void
    {
        $this->configureMinecraftPlans();
        $sourceStartup = 'java -Xms'.($sourceMemory * 1024).'M -Xmx'.($sourceMemory * 1024).'M -jar {{SERVER_JARFILE}} nogui';
        $this->larger->update(['name' => 'Larger Minecraft tier']);
        $this->order->package->update(['name' => 'Minecraft | '.$sourceMemory.'GB', 'data' => array_merge($this->order->package->data, [
            'memory_limit' => $sourceMemory, 'startup' => $sourceStartup, 'cpu_pinning' => $sourcePinning,
        ])]);
        $this->larger->update(['name' => 'Minecraft | '.$targetMemory.'GB', 'data' => array_merge($this->larger->data, [
            'memory_limit' => $targetMemory, 'cpu_pinning' => $targetPinning,
            'startup' => 'java -Xms'.($targetMemory * 1024).'M -Xmx'.($targetMemory * 1024).'M -jar {{SERVER_JARFILE}} nogui',
        ])]);
        $this->get(route('orders.upgrade', $this->order))->assertOk()->assertSee('Continue to payment');
        $panel = $this->minecraftPanel();
        $panel['container']['startup_command'] = $sourceStartup;
        $this->fakePanel($panel);
        $payment = $this->checkout();
        $payment->completed('TX-tier-'.$sourceMemory);
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/build')
            && $request['limits']['memory'] === $targetMemory * 1024 && $request['limits']['threads'] === $expectedPinning);
        $upgraded = $this->order->fresh();
        $this->assertSame($this->larger->id, $upgraded->package_id);
        $pinning = $upgraded->option('cpu_pinning');
        $this->assertSame($expectedPinning, trim($pinning ?? '') === '' ? null : $pinning);
        $this->assertSame(0.0, (float) $upgraded->prices->sum('cycle_price'));
        $this->assertNotNull($payment->fresh()->data('upgrade_applied_at'));
    }

    /** @return array<string, array{int, int, ?string, ?string, ?string}> */
    public static function minecraftTiers(): array
    {
        return [
            '1GB to 2GB' => [1, 2, '0-1', '0-1', '0-1'],
            '2GB to 3GB' => [2, 3, '0-1', '0-1', '0-1'],
            '3GB to 4GB' => [3, 4, '0-1', null, null],
            '4GB to 6GB' => [4, 6, null, '0-1', null],
            '6GB to 8GB' => [6, 8, '0-1', '0-1', '0-1'],
            '8GB to 9GB' => [8, 9, '0-1', '0-1', '0-1'],
            '9GB to 10GB' => [9, 10, '0-1', '0,1,3,4', '0,1,3,4'],
            '10GB to 12GB' => [10, 12, '0,1,3,4', '0,1,3,4', '0,1,3,4'],
            '12GB to 16GB' => [12, 16, '0,1,3,4', '0,1,3,4', '0,1,3,4'],
            'retain existing cores' => [4, 6, '0-3', '0-1', '0-3'],
            'retain core zero' => [4, 6, '0', '1', '0'],
        ];
    }

    #[DataProvider('upgradePageStates')]
    public function test_upgrade_entry_remains_available_when_no_upgrade_can_be_purchased(string $status, string $provider, string $message): void
    {
        $this->order->update(['status' => $status]);
        $this->order->package->serverConnection->update(['extension_identifier' => $provider]);
        $this->target->update(['is_active' => false]);
        Volt::test(client_view_path('orders.livewire.orders-table'))->assertSee(route('orders.upgrade', $this->order), false);
        if ($status !== 'pending') {
            $this->get(route('orders.view', $this->order))->assertOk()->assertSee('Upgrade service');
        }
        $this->get(route('orders.upgrade', $this->order))->assertOk()->assertSee('Upgrade your service')
            ->assertSee($message)->assertDontSee('Continue to payment');
        $this->assertSame(0, Payment::query()->count());
        Http::assertNothingSent();
    }

    /** @return array<string, array{string, string, string}> */
    public static function upgradePageStates(): array
    {
        return [
            'highest tier' => ['active', 'server-pterodactyl', 'No compatible larger plans'],
            'suspended' => ['suspended', 'server-pterodactyl', 'Only active, provisioned services'],
            'pending' => ['pending', 'server-pterodactyl', 'Only active, provisioned services'],
            'manual services' => ['active', 'server-universal', 'Self-service upgrades are unavailable'],
        ];
    }

    public function test_unlimited_resources_cannot_be_downgraded_to_finite_limits(): void
    {
        $this->order->package->update(['data' => array_merge($this->order->package->data, ['cpu_limit' => 0])]);
        $this->get(route('orders.upgrade', $this->order))->assertOk()->assertDontSee('Continue to payment');
        $this->post(route('orders.upgrade.purchase', $this->order), $this->upgradeInput())->assertSessionHasErrors('package_price_id');
        $this->larger->update(['data' => array_merge($this->larger->data, ['cpu_limit' => 0])]);
        $this->get(route('orders.upgrade', $this->order))->assertOk()->assertSee('Continue to payment')->assertSee('Unlimited');
    }

    public function test_addon_overrides_cannot_result_in_purchasing_identical_resources(): void
    {
        foreach (['memory_limit' => 8, 'cpu_limit' => 300] as $key => $value) {
            $this->order->prices()->create(['description' => $key, 'type' => 'config_option', 'key' => $key, 'value' => (string) $value, 'cycle_price' => 0.1]);
        }
        $this->get(route('orders.upgrade', $this->order))->assertOk()->assertSee('No compatible larger plans');
        $this->post(route('orders.upgrade.purchase', $this->order), $this->upgradeInput())->assertSessionHasErrors('package_price_id');
    }

    public function test_upgrade_cannot_be_applied_with_an_unpaid_invoice(): void
    {
        $payment = $this->checkout();
        try {
            app(OrderUpgradeService::class)->apply($payment);
            $this->fail('Unpaid upgrades cannot be applied.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('package_price_id', $exception->errors());
        }
        $this->post(route('orders.upgrade.retry', ['order' => $this->order, 'payment' => $payment]))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_changed_plan_is_rechecked_before_gateway_payment_and_after_payment(): void
    {
        $payment = $this->checkout();
        $this->target->update(['price' => 90]);
        try {
            $payment->payWith(1);
            $this->fail('Changed invoices must be rejected before charging.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment_id', $exception->errors());
        }
        $payment->completed('TX-late');
        $this->assertTrue($payment->fresh()->data('upgrade_failed'));
        $this->assertSame($this->order->package_id, $this->order->fresh()->package_id);
        Http::assertNothingSent();
    }

    public function test_destination_stock_is_rechecked_after_checkout(): void
    {
        $payment = $this->checkout();
        $this->larger->update(['global_quantity' => 0]);
        $payment->completed('TX-sold-out');
        $this->assertTrue($payment->fresh()->data('upgrade_failed'));
        $this->assertSame($this->order->package_id, $this->order->fresh()->package_id);
        Http::assertNothingSent();
    }

    public function test_proration_uses_fractional_days_and_does_not_cap_prepaid_time(): void
    {
        $this->order->update(['due_date' => now()->addHours(12)]);
        $quote = app(OrderUpgradeService::class)->options($this->order->fresh())->first();
        $this->assertSame(3.5, $quote['total']);
        $this->order->update(['due_date' => now()->addDays(45)]);
        $quote = app(OrderUpgradeService::class)->options($this->order->fresh())->first();
        $this->assertSame(48.0, $quote['total']);
    }

    private function checkout(): Payment
    {
        $this->post(route('orders.upgrade.purchase', $this->order), $this->upgradeInput())->assertRedirect()->assertSessionHasNoErrors();

        return Payment::query()->where('handler', OrderUpgradeHandler::class)->latest('id')->firstOrFail();
    }

    /** @return array{package_price_id: int, quote_token: string, quoted_at: int, confirm_upgrade: string} */
    private function upgradeInput(): array
    {
        $order = $this->order->fresh(['prices', 'package']);
        $quote = app(OrderUpgradeService::class)->quote($order, $this->target->fresh('package'));

        return ['package_price_id' => $this->target->id, 'quote_token' => $quote['token'], 'quoted_at' => $quote['quoted_at'], 'confirm_upgrade' => '1'];
    }

    private function configureMinecraftPlans(): void
    {
        $this->order->package->serverConnection->update(['status' => 'healthy', 'prevent_purchasing' => true]);
        $data = array_merge($this->order->package->data, [
            'memory_limit' => 3, 'disk_limit' => 0, 'cpu_limit' => 0, 'cpu_pinning' => '0-1', 'block_io_weight' => 500,
            'docker_image' => 'ghcr.io/pterodactyl/yolks:java_21',
            'startup' => 'java -Xms3072M -Xmx3072M -XX:+UseG1GC -jar {{SERVER_JARFILE}} nogui',
        ]);
        $this->order->package->update(['name' => 'Minecraft | 3GB', 'data' => $data]);
        PackagePrice::query()->findOrFail($this->order->package_price_id)->update(['price' => 8.99]);
        $this->order->update(['cycle_price' => 8.99 / 30]);
        $this->larger->update(['name' => 'Minecraft | 4GB', 'data' => array_merge($data, [
            'memory_limit' => 4, 'cpu_pinning' => null,
            'startup' => 'java -Xms4096M -Xmx4096M -XX:+UseG1GC -jar {{SERVER_JARFILE}} nogui',
        ])]);
        $this->target->update(['price' => 11.99, 'upgrade_fee' => 0]);
    }

    /** @return array<string, mixed> */
    private function minecraftPanel(): array
    {
        return ['allocation' => 77, 'egg' => 2, 'container' => [
            'startup_command' => 'java -Xms3072M -Xmx3072M -XX:+UseG1GC -jar {{SERVER_JARFILE}} nogui',
            'image' => 'ghcr.io/pterodactyl/yolks:java_21', 'skip_scripts' => false,
            'environment' => ['SERVER_JARFILE' => 'custom.jar', 'MINECRAFT_VERSION' => '1.20.4', 'CUSTOM_VARIABLE' => 'keep-me'],
        ]];
    }

    /** @param array<string, mixed> $attributes */
    private function fakePanel(array $attributes = []): void
    {
        Http::fake(['https://panel.example.test/api/application/servers/101' => Http::response(['attributes' => array_merge(['allocation' => 77], $attributes)], 200),
            'https://panel.example.test/api/application/servers/101/build' => Http::response([], 200),
            'https://panel.example.test/api/application/servers/101/startup' => Http::response([], 200)]);
    }
}
