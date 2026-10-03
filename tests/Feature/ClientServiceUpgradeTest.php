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
        $this->get(route('orders.upgrade', $this->order))->assertOk()->assertSee('Larger')->assertSee('$60.00')->assertSee('$13.00')->assertSee('Existing add-ons are retained')->assertDontSee('$50.00');
        $this->get(route('orders.view', $this->order))->assertOk()->assertSee('Upgrade service');
        Volt::test(client_view_path('orders.livewire.orders-table'))->assertSee('Upgrade')->assertSee(route('orders.upgrade', $this->order), false);
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

    public function test_different_connections_categories_and_unsupported_providers_are_rejected(): void
    {
        $input = $this->upgradeInput();
        $connection = ServerConnection::query()->create(['alias' => 'different', 'extension_identifier' => 'server-pterodactyl']);
        $this->larger->update(['connection_id' => $connection->id]);
        $this->post(route('orders.upgrade.purchase', $this->order), $input)->assertSessionHasErrors('package_price_id');
        $category = Category::query()->create(['name' => 'Other', 'slug' => 'other', 'status' => 'active', 'icon' => 'server']);
        $this->larger->update(['connection_id' => $this->order->package->connection_id, 'category_id' => $category->id]);
        $this->post(route('orders.upgrade.purchase', $this->order), $input)->assertSessionHasErrors('package_price_id');
        $this->order->package->serverConnection->update(['extension_identifier' => 'server-universal']);
        $this->post(route('orders.upgrade.purchase', $this->order), $input)->assertSessionHasErrors('package_price_id');
        $this->assertSame(0, Payment::query()->count());
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

    /** @return array{package_price_id: int, quote_token: string, confirm_upgrade: string} */
    private function upgradeInput(): array
    {
        $order = $this->order->fresh(['prices', 'package']);
        $quote = app(OrderUpgradeService::class)->quote($order, $this->target->fresh('package'));

        return ['package_price_id' => $this->target->id, 'quote_token' => $quote['token'], 'confirm_upgrade' => '1'];
    }

    private function fakePanel(): void
    {
        Http::fake(['https://panel.example.test/api/application/servers/101' => Http::response(['attributes' => ['allocation' => 77]], 200),
            'https://panel.example.test/api/application/servers/101/build' => Http::response([], 200)]);
    }
}
