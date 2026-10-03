<?php

namespace Tests\Feature;

use App\Actions\PaymentActions;
use App\Jobs\Orders\OrderCreateServer;
use App\Jobs\Orders\OrderTerminateServer;
use App\Models\Category;
use App\Models\Extension;
use App\Models\GatewayConfig;
use App\Models\Order;
use App\Models\Package;
use App\Models\ServerConnection;
use App\Models\Subscription;
use App\Models\User;
use Extensions\Gateways\SandboxSubscription\Gateway;
use Extensions\Servers\Pterodactyl\Server;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ClientServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::preventStrayRequests();
        config(['app.installed' => true, 'app.license_key' => 'WMX-test']);
        Cache::put('lcs_checked_at', now(), 3600);
        $this->customer = User::factory()->create(['status' => 'active']);
        Extension::query()->updateOrCreate(['identifier' => 'server-pterodactyl'], [
            'namespace' => Server::class, 'type' => 'server', 'name' => 'Pterodactyl', 'status' => 'enabled', 'version' => '1.0.0',
        ]);
        $connection = ServerConnection::query()->create([
            'alias' => 'management-test', 'extension_identifier' => 'server-pterodactyl',
            'config' => ['hostname' => 'https://panel.example.test/', 'api_key' => 'ptla_test'],
        ]);
        $category = Category::query()->create(['name' => 'Hosting', 'slug' => 'hosting', 'status' => 'active', 'icon' => 'server']);
        $package = Package::query()->create(['category_id' => $category->id, 'connection_id' => $connection->id, 'name' => 'Game server', 'slug' => 'game-server', 'status' => 'active']);
        $this->order = Order::withoutEvents(fn () => Order::query()->create([
            'user_id' => $this->customer->id, 'package_id' => $package->id, 'status' => 'active',
            'external_id' => '101', 'data' => ['identifier' => 'abc123'], 'period_in_days' => 30,
            'due_date' => now()->addDays(10), 'last_renewed_at' => now(), 'auto_balance_renew' => true,
        ]));
        $this->actingAs($this->customer);
    }

    public function test_owner_can_schedule_termination_at_the_due_date(): void
    {
        $dueDate = $this->order->due_date->copy();
        $this->post(route('orders.terminate', $this->order), ['termination_mode' => 'due_date', 'confirm_termination' => '1'])->assertRedirect(route('dashboard'));
        $order = $this->order->fresh();
        $this->assertTrue($order->terminate_at->equalTo($dueDate));
        $this->assertNotNull($order->termination_requested_at);
        $this->assertFalse($order->auto_balance_renew);
        $this->assertSame('active', $order->status);
        Queue::assertNotPushed(OrderTerminateServer::class);
        $this->assertDatabaseHas('order_logs', ['order_id' => $order->id, 'action' => 'termination_requested', 'user_id' => $this->customer->id]);
        $this->assertDatabaseHas('emails', ['user_id' => $this->customer->id, 'subject' => 'Termination requested for service #'.$order->id]);
    }

    public function test_owner_can_request_immediate_termination_and_escalate_a_scheduled_request(): void
    {
        $this->post(route('orders.terminate', $this->order), ['termination_mode' => 'due_date', 'confirm_termination' => '1'])->assertSessionHasNoErrors();
        $this->post(route('orders.terminate', $this->order), ['termination_mode' => 'now', 'confirm_termination' => '1'])->assertRedirect(route('dashboard'));
        $this->assertTrue($this->order->fresh()->terminate_at->lessThanOrEqualTo(now()));
        Queue::assertPushed(OrderTerminateServer::class, 1);
        $this->post(route('orders.terminate', $this->order), ['termination_mode' => 'now', 'confirm_termination' => '1'])->assertSessionHasErrors('termination_mode');
        Queue::assertPushed(OrderTerminateServer::class, 1);
    }

    public function test_other_customers_cannot_access_termination_or_panel_actions(): void
    {
        $this->actingAs(User::factory()->create(['status' => 'active']));
        $this->get(route('orders.termination', $this->order))->assertForbidden();
        $this->get(route('orders.panel', $this->order))->assertForbidden();
        $this->post(route('orders.terminate', $this->order), ['termination_mode' => 'now', 'confirm_termination' => '1'])->assertForbidden();
        $this->assertNull($this->order->fresh()->termination_requested_at);
    }

    public function test_guest_cannot_terminate_services(): void
    {
        auth()->logout();
        $this->post(route('orders.terminate', $this->order), ['termination_mode' => 'now', 'confirm_termination' => '1'])->assertRedirect();
        $this->assertNull($this->order->fresh()->termination_requested_at);
    }

    public function test_confirmation_and_valid_timing_are_required(): void
    {
        $this->post(route('orders.terminate', $this->order), ['termination_mode' => 'now'])->assertSessionHasErrors('confirm_termination');
        $this->post(route('orders.terminate', $this->order), ['termination_mode' => 'invalid', 'confirm_termination' => '1'])->assertSessionHasErrors('termination_mode');
        foreach ([null, now()->subDay()] as $dueDate) {
            $this->order->update(['due_date' => $dueDate]);
            $this->post(route('orders.terminate', $this->order), ['termination_mode' => 'due_date', 'confirm_termination' => '1'])->assertSessionHasErrors('termination_mode');
        }
        $this->assertNull($this->order->fresh()->termination_requested_at);
        Queue::assertNotPushed(OrderTerminateServer::class);
    }

    public function test_terminated_services_cannot_be_cancelled_again_or_open_the_panel(): void
    {
        $this->order->update(['status' => 'terminated']);
        $this->post(route('orders.terminate', $this->order), ['termination_mode' => 'now', 'confirm_termination' => '1'])->assertSessionHasErrors('termination_mode');
        $this->get(route('orders.panel', $this->order))->assertNotFound();
    }

    public function test_scheduler_only_queues_requests_that_have_reached_their_date(): void
    {
        $this->order->update(['termination_requested_at' => now(), 'terminate_at' => now()->addHour()]);
        $this->artisan('cronjobs:orders:terminate-requested')->assertSuccessful();
        Queue::assertNotPushed(OrderTerminateServer::class);
        $this->travel(1)->hours();
        $this->artisan('cronjobs:orders:terminate-requested')->assertSuccessful();
        Queue::assertPushed(OrderTerminateServer::class, fn ($job) => $job->order->id === $this->order->id);
    }

    public function test_termination_deletes_the_remote_service_and_retries_are_idempotent(): void
    {
        Http::fake(['https://panel.example.test/api/application/servers/101' => Http::response('', 204)]);
        $this->order->update(['termination_requested_at' => now(), 'terminate_at' => now()]);
        $job = new OrderTerminateServer($this->order);
        $job->handle();
        $job->handle();
        $this->assertSame('terminated', $this->order->fresh()->status);
        $this->assertFalse($this->order->fresh()->auto_balance_renew);
        Http::assertSentCount(1);
    }

    public function test_panel_errors_do_not_report_successful_termination(): void
    {
        Http::fake(['*' => Http::response([], 500)]);
        try {
            (new OrderTerminateServer($this->order))->handle();
            $this->fail('Panel failure must be propagated.');
        } catch (RequestException $exception) {
            $this->assertSame('active', $this->order->fresh()->status);
            $this->assertSame(500, $exception->response->status());
        }
    }

    public function test_cancelled_pending_services_are_never_provisioned(): void
    {
        $this->order->update(['status' => 'pending', 'external_id' => null, 'termination_requested_at' => now(), 'terminate_at' => now()]);
        (new OrderCreateServer($this->order))->handle();
        (new OrderTerminateServer($this->order))->handle();
        $this->assertSame('terminated', $this->order->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_cancellation_blocks_new_renewal_payments(): void
    {
        $this->order->update(['termination_requested_at' => now(), 'terminate_at' => now()->addDay()]);
        $this->expectException(ValidationException::class);
        (new PaymentActions)->renewalPaymentForClient(['order_id' => $this->order->id, 'renewal_days' => 30]);
    }

    public function test_dashboard_and_confirmation_page_include_service_controls(): void
    {
        Volt::test(client_view_path('orders.livewire.orders-table'))
            ->assertSee('Open panel')->assertSee('Terminate')->assertSee(route('orders.panel', $this->order), false);
        $this->get(route('orders.termination', $this->order))->assertOk()->assertSee('At the next due date')->assertSee('Right away')->assertSee('permanently deleted');
    }

    public function test_panel_button_redirects_to_the_correct_server_without_credentials(): void
    {
        $this->get(route('orders.panel', $this->order))->assertRedirect('https://panel.example.test/server/abc123')->assertHeader('Cache-Control', 'no-store, private');
        Http::assertNothingSent();
    }

    public function test_pending_payment_subscriptions_are_cancelled_with_the_service(): void
    {
        $subscription = Subscription::query()->create([
            'user_id' => $this->customer->id, 'subscribable_type' => Order::class, 'subscribable_id' => $this->order->id,
            'status' => 'pending', 'description' => 'Service subscription', 'currency' => 'USD', 'amount' => 10, 'frequency' => 30,
        ]);
        $this->post(route('orders.terminate', $this->order), ['termination_mode' => 'due_date', 'confirm_termination' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $subscription->fresh()->status);
    }

    public function test_active_recurring_billing_is_cancelled_before_service_termination(): void
    {
        Extension::query()->updateOrCreate(['identifier' => 'gateway-sandbox-subscription'], [
            'namespace' => Gateway::class, 'type' => 'gateway', 'name' => 'Sandbox', 'status' => 'enabled', 'version' => '1.0.0',
        ]);
        $gateway = GatewayConfig::query()->create(['extension_identifier' => 'gateway-sandbox-subscription', 'namespace' => Gateway::class, 'type' => 'subscription', 'display_name' => 'Sandbox', 'config' => []]);
        $subscription = Subscription::query()->create([
            'user_id' => $this->customer->id, 'gateway_config_id' => $gateway->id,
            'subscribable_type' => Order::class, 'subscribable_id' => $this->order->id,
            'status' => 'active', 'description' => 'Service subscription', 'currency' => 'USD', 'amount' => 10, 'frequency' => 30,
        ]);
        $this->post(route('orders.terminate', $this->order), ['termination_mode' => 'now', 'confirm_termination' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $subscription->fresh()->status);
        Queue::assertPushed(OrderTerminateServer::class);
        $subscription->activated('late-provider-activation');
        $this->assertSame('cancelled', $subscription->fresh()->status);
        $this->assertTrue($this->order->fresh()->terminate_at->lessThanOrEqualTo(now()));
    }

    public function test_billing_cancellation_failure_leaves_the_service_unchanged(): void
    {
        $subscription = Subscription::query()->create([
            'user_id' => $this->customer->id, 'subscribable_type' => Order::class, 'subscribable_id' => $this->order->id,
            'status' => 'active', 'description' => 'Unavailable provider', 'currency' => 'USD', 'amount' => 10, 'frequency' => 30,
        ]);
        $this->post(route('orders.terminate', $this->order), ['termination_mode' => 'now', 'confirm_termination' => '1'])->assertSessionHasErrors('termination_mode');
        $this->assertNull($this->order->fresh()->termination_requested_at);
        $this->assertSame('active', $subscription->fresh()->status);
        Queue::assertNotPushed(OrderTerminateServer::class);
    }

    public function test_scheduled_requests_for_failed_provisioning_are_processed_unless_termination_already_failed(): void
    {
        $this->order->update(['status' => 'failed', 'termination_requested_at' => now(), 'terminate_at' => now()]);
        $this->artisan('cronjobs:orders:terminate-requested')->assertSuccessful();
        Queue::assertPushed(OrderTerminateServer::class, 1);
        Queue::fake();
        $this->order->exceptions()->create(['action' => 'terminate', 'message' => 'Panel unavailable']);
        $this->artisan('cronjobs:orders:terminate-requested')->assertSuccessful();
        Queue::assertNotPushed(OrderTerminateServer::class);
    }
}
