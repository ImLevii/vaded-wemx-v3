<?php

namespace Tests\Feature;

use App\Events\Orders\OrderCreated;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderMember;
use App\Models\Package;
use App\Models\Payment;
use App\Models\ServerConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ClientDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.installed' => true, 'app.license_key' => 'WMX-TESTING-KEY']);
        Cache::put('lcs_checked_at', now(), 21600);
    }

    public function test_guests_cannot_access_the_client_dashboard(): void
    {
        $this->assertSame('/dashboard', route('dashboard', absolute: false));
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_homepage_displays_the_public_store_for_guests_and_customers(): void
    {
        $this->assertSame('/', route('categories.index', absolute: false));
        $this->get('/')
            ->assertOk()
            ->assertViewIs('theme::categories.index')
            ->assertSee('id="services"', false)
            ->assertSee('id="pricing"', false);

        $customer = User::factory()->create(['status' => 'active', 'language' => 'en']);
        $this->actingAs($customer)->get('/')
            ->assertOk()
            ->assertViewIs('theme::categories.index')
            ->assertSee(route('dashboard'));
    }

    public function test_old_category_urls_redirect_home_and_preserve_the_selected_category(): void
    {
        $category = Category::query()->create(['name' => 'VPS', 'slug' => 'vps', 'status' => 'active', 'icon' => '/assets/common/img/category-placeholder.png']);

        $this->get('/categories')->assertStatus(301)->assertRedirect(route('categories.index'));
        $this->get('/categories?category=vps&theme=light')
            ->assertStatus(301)
            ->assertRedirect(route('categories.index', ['category' => $category->slug, 'theme' => 'light']));
        $this->get('/?category=vps')->assertOk()->assertSee('VPS plans');
    }

    public function test_login_redirects_to_the_dashboard(): void
    {
        $customer = User::factory()->create(['status' => 'active', 'language' => 'en']);
        Mail::fake();

        Volt::test(client_view_path('auth.livewire.login-form'))
            ->set('username', $customer->email)
            ->set('password', 'password')
            ->call('handleLogin')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));
    }

    public function test_registration_redirects_to_the_dashboard(): void
    {
        Mail::fake();

        Volt::test(client_view_path('auth.livewire.register-form'))
            ->set('first_name', 'Jamie')
            ->set('last_name', 'Taylor')
            ->set('username', 'jamietaylor')
            ->set('email', 'jamie@example.test')
            ->set('password', 'securepassword')
            ->set('password_confirmation', 'securepassword')
            ->call('handleRegistration')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));
    }

    public function test_dashboard_shows_the_customers_own_payments_and_security_action(): void
    {
        $customer = User::factory()->create(['first_name' => 'Alex', 'status' => 'active', 'language' => 'en', 'balance' => 25]);
        $otherCustomer = User::factory()->create(['status' => 'active']);

        foreach ([[$customer, 'My hosting payment'], [$otherCustomer, 'Another customer private payment']] as [$user, $description]) {
            Payment::query()->create([
                'user_id' => $user->id,
                'status' => 'paid',
                'description' => $description,
                'currency' => 'USD',
                'subtotal' => 10,
                'tax' => 0,
                'discount' => 0,
                'total' => 10,
            ]);
        }

        $response = $this->actingAs($customer)->get(route('dashboard'));
        $response->assertOk()
            ->assertSee('Welcome back, Alex.')
            ->assertSee('My hosting payment')
            ->assertDontSee('Another customer private payment')
            ->assertDontSee('3 invoices in total')
            ->assertSee('Enable two-factor')
            ->assertSee(route('enable-2fa'))
            ->assertSee('No orders found');
    }

    public function test_dashboard_recognizes_enabled_two_factor_authentication(): void
    {
        $customer = User::factory()->create(['status' => 'active', 'language' => 'en', 'tfa_enabled' => true]);

        $this->actingAs($customer)
            ->withSession(['tfa_passed_at' => now()])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Protected')
            ->assertSee('Security settings')
            ->assertDontSee('Enable two-factor');
    }

    public function test_dashboard_counts_only_the_customers_pending_invitations(): void
    {
        $customer = User::factory()->create(['status' => 'active', 'language' => 'en']);
        $owner = User::factory()->create(['status' => 'active']);
        $category = Category::query()->create(['name' => 'Hosting', 'slug' => 'hosting', 'status' => 'active', 'icon' => '/assets/common/img/category-placeholder.png']);
        $connection = ServerConnection::query()->create(['alias' => 'dashboard-test', 'extension_identifier' => 'server-universal']);
        $package = Package::query()->create(['category_id' => $category->id, 'connection_id' => $connection->id, 'name' => 'Starter', 'slug' => 'starter', 'status' => 'active']);
        Event::fake([OrderCreated::class]);

        foreach (['pending', 'pending', 'accepted'] as $status) {
            $order = Order::query()->create(['user_id' => $owner->id, 'package_id' => $package->id]);
            OrderMember::query()->create(['order_id' => $order->id, 'user_id' => $customer->id, 'email' => $customer->email, 'status' => $status]);
            OrderMember::query()->create(['order_id' => $order->id, 'user_id' => $owner->id, 'email' => $owner->email, 'status' => 'pending']);
        }

        $this->actingAs($customer)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('You have 2 pending invite(s) to orders.');
    }

    public function test_orders_table_can_search_and_filter_without_losing_its_empty_state(): void
    {
        $customer = User::factory()->create(['status' => 'active', 'language' => 'en']);
        $category = Category::query()->create(['name' => 'Hosting', 'slug' => 'hosting', 'status' => 'active', 'icon' => '/assets/common/img/category-placeholder.png']);
        $connection = ServerConnection::query()->create(['alias' => 'orders-test', 'extension_identifier' => 'server-universal']);
        Event::fake([OrderCreated::class]);

        foreach (['active' => 'Bot Hosting', 'suspended' => 'Starter VPS'] as $status => $name) {
            $package = Package::query()->create(['category_id' => $category->id, 'connection_id' => $connection->id, 'name' => $name, 'slug' => $status, 'status' => 'active']);
            Order::query()->create([
                'user_id' => $customer->id,
                'package_id' => $package->id,
                'status' => $status,
                'due_date' => $status === 'active' ? now()->addMonth() : null,
                'last_renewed_at' => now(),
            ]);
        }

        $this->actingAs($customer)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Starter VPS')
            ->assertSee('is suspended');

        Volt::actingAs($customer)->test(client_view_path('orders.livewire.orders-table'))
            ->assertSee('Bot Hosting')
            ->assertSee('Starter VPS')
            ->assertSee('Never')
            ->set('filterStatus', ['suspended'])
            ->assertSee('Starter VPS')
            ->assertDontSee('Bot Hosting')
            ->set('search', 'bot')
            ->assertSee('No matching orders')
            ->assertDontSee('Starter VPS')
            ->set('filterStatus', [])
            ->assertSee('Bot Hosting')
            ->assertDontSee('No matching orders')
            ->set('search', '')
            ->assertSee('Bot Hosting')
            ->assertSee('Starter VPS');
    }
}
