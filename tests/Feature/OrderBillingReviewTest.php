<?php

namespace Tests\Feature;

use App\Actions\OrderActions;
use App\Events\Orders\OrderCreated;
use App\Models\Category;
use App\Models\GatewayConfig;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\Payment;
use App\Models\ServerConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class OrderBillingReviewTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.installed' => true, 'app.license_key' => 'WMX-TESTING-KEY']);
        Cache::put('lcs_checked_at', now(), 21600);
        Queue::fake();
        Http::preventStrayRequests();
        $customer = User::factory()->create(['status' => 'active', 'balance' => 100]);
        $this->actingAs($customer);
        $category = Category::query()->create(['name' => 'Games', 'slug' => 'games', 'status' => 'active', 'icon' => 'server']);
        $connection = ServerConnection::query()->create(['alias' => 'test', 'extension_identifier' => 'server-universal']);
        $package = Package::query()->create(['category_id' => $category->id, 'connection_id' => $connection->id, 'name' => 'Minecraft', 'slug' => 'minecraft', 'status' => 'active']);
        $price = PackagePrice::query()->create(['package_id' => $package->id, 'price' => 30, 'period_in_days' => 30, 'is_active' => true]);
        Event::fake([OrderCreated::class]);
        $this->order = Order::query()->create([
            'user_id' => $customer->id, 'package_id' => $package->id, 'package_price_id' => $price->id,
            'status' => 'active', 'cycle_price' => 0, 'period_in_days' => 30,
            'due_date' => null, 'last_renewed_at' => now(), 'auto_balance_renew' => false,
            'data' => ['name' => 'Restored SMP', '_import' => ['billing_review_required' => true]],
        ]);
    }

    public function test_billing_actions_cannot_charge_or_renew_a_service_under_review(): void
    {
        $gateway = GatewayConfig::query()->create(['extension_identifier' => 'test-gateway', 'namespace' => self::class, 'display_name' => 'Test', 'type' => 'subscription']);
        $alternatePrice = PackagePrice::query()->create(['package_id' => $this->order->package_id, 'price' => 60, 'period_in_days' => 30, 'is_active' => true]);
        $attempts = [
            fn () => Payment::actions()->renewalPaymentForClient(['order_id' => $this->order->id, 'renewal_days' => 30]),
            fn () => Order::actions()->renewOrderAsClient(['order_id' => $this->order->id, 'renewal_days' => 30]),
            fn () => $this->order->attemptBalanceRenewal(),
            fn () => OrderActions::createSubscriptionAsClient(['order_id' => $this->order->id, 'user_id' => $this->order->user_id, 'gateway_config_id' => $gateway->id]),
            fn () => Order::actions()->upgradeOrderAsAdmin(['order_id' => $this->order->id, 'package_price_id' => $alternatePrice->id]),
        ];
        foreach ($attempts as $attempt) {
            try {
                $attempt();
                $this->fail('Billing must stay on hold.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('Billing review pending', $exception->errors()['order_id'][0]);
            }
        }
        $this->assertEquals(100, $this->order->user->fresh()->balance);
        $this->assertNull($this->order->fresh()->due_date);
        foreach (['payments', 'subscriptions', 'balance_transactions'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_review_orders_are_excluded_from_expiry_even_if_a_date_is_added(): void
    {
        $this->order->updateQuietly(['due_date' => now()->subDays(10), 'auto_balance_renew' => true]);
        $this->assertFalse(Order::getOrdersPastDueDate()->whereKey($this->order->id)->exists());
        $this->order->updateQuietly(['due_date' => now()->addDay()]);
        $this->assertFalse(Order::getOrdersAboutToExpire()->whereKey($this->order->id)->exists());
    }

    public function test_customer_sees_review_status_and_cannot_enable_balance_renewal(): void
    {
        Volt::test(client_view_path('orders.livewire.orders-table'))
            ->assertSee('Restored SMP')->assertSee('Billing review pending')->assertSee('Awaiting billing review');
        Volt::test(client_view_path('orders.livewire.renew-order-drawer'), ['order' => $this->order])
            ->assertSee('Billing review pending')->assertDontSee('Pay Now')->call('createPayment')->assertHasErrors('order_id');
        Volt::test(client_view_path('orders.livewire.balance-renewal-switch'), ['order_id' => $this->order->id])
            ->call('enableBalanceRenewal')->assertHasErrors('order_id');
        $this->assertFalse($this->order->fresh()->auto_balance_renew);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_service_page_keeps_access_visible_without_unverified_billing_controls(): void
    {
        $this->get(route('orders.view', $this->order->id))->assertOk()
            ->assertSee('Restored SMP')->assertSee('Billing review pending')->assertSee('Unknown')
            ->assertDontSee('text="Renew"', false)->assertDontSee('Enable auto balance renewal');
        $this->get(route('orders.view.subscription', $this->order->id))->assertOk()
            ->assertSee('Billing review pending')->assertDontSee('Setup Subscription');
    }

    public function test_verified_normal_billing_still_creates_a_renewal_invoice(): void
    {
        $this->order->updateQuietly(['cycle_price' => 1, 'due_date' => now()->addDays(30), 'data' => ['_import' => ['billing_review_required' => false]]]);
        $payment = Payment::actions()->renewalPaymentForClient(['order_id' => $this->order->id, 'renewal_days' => 30]);
        $this->assertEquals(30, $payment->subtotal);
        $this->assertFalse($this->order->fresh()->requiresBillingReview());
        $this->assertTrue(Order::getOrdersAboutToExpire(31)->whereKey($this->order->id)->exists());
    }
}
