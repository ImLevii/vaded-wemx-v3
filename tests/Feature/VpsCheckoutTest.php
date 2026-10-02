<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\ServerConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class VpsCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Package $package;

    private PackagePrice $monthly;

    private PackagePrice $yearly;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.installed' => true, 'app.license_key' => 'WMX-TESTING-KEY']);
        Cache::put('lcs_checked_at', now(), 21600);
        Currency::query()->updateOrCreate(['currency' => 'USD'], ['display_name' => 'US Dollar', 'format' => '$1,0.00', 'market_rate' => 1, 'is_active' => true]);
        session()->put('currency', 'USD');
        $category = Category::query()->create(['name' => 'VPS Hosting', 'slug' => 'vps-hosting', 'status' => 'active', 'icon' => '']);
        $connection = ServerConnection::query()->create(['alias' => 'vps-checkout-test', 'extension_identifier' => 'server-universal']);
        $this->package = Package::query()->create(['category_id' => $category->id, 'connection_id' => $connection->id, 'name' => 'B-VPS-2', 'slug' => 'b-vps-2', 'status' => 'active']);
        $this->monthly = $this->package->prices()->create(['period_in_days' => 30, 'price' => 10, 'setup_fee' => 0, 'is_active' => true]);
        $this->yearly = $this->package->prices()->create(['period_in_days' => 365, 'price' => 100, 'setup_fee' => 5, 'is_active' => true]);
        foreach (['1x vCPU Core', '2GB DDR4 Ram', '50GB SSD Storage', 'Unmetered Bandwidth'] as $index => $description) {
            $this->package->features()->create(['description' => $description, 'sort_order' => $index]);
        }
        foreach (['server_name' => 'Server Name', 'hostname' => 'Hostname'] as $key => $label) {
            $this->package->configOptions()->create(['key' => $key, 'label' => $label, 'type' => 'text', 'rules' => 'required|string', 'default_value' => $key === 'hostname' ? 'server.example.com' : 'My VPS']);
        }
        $this->package->configOptions()->create([
            'key' => 'operating_system', 'label' => 'Operating System', 'type' => 'select', 'rules' => 'required|string', 'default_value' => 'debian-11',
            'data' => ['options' => [
                ['value' => 'debian-11', 'name' => 'Debian 11 (Bullseye)', 'description' => 'Minimal installation with limited packages.'],
                ['value' => 'debian-12', 'name' => 'Debian 12 (Bookworm)', 'description' => 'Minimal installation with limited packages.'],
                ['value' => 'debian-13', 'name' => 'Debian 13 (Trixie)', 'description' => 'Minimal installation on an ext4 filesystem.'],
                ['value' => 'centos-9', 'name' => 'CentOS Stream 9'],
                ['value' => 'rocky-9', 'name' => 'Rocky Linux 9'],
                ['value' => 'alma-9', 'name' => 'AlmaLinux 9'],
                ['value' => 'ubuntu-2404', 'name' => 'Ubuntu 24.04', 'description' => 'Ubuntu server installation.'],
                ['value' => 'fedora-43', 'name' => 'Fedora 43'],
                ['value' => 'windows-2022', 'name' => 'Windows Server 2022', 'description' => '<script>alert(1)</script>', 'daily_price' => 0.1],
            ]],
        ]);
        $this->package->configOptions()->create([
            'key' => 'ip_addresses', 'label' => 'IPv4 Addresses', 'type' => 'select', 'rules' => 'required|string', 'default_value' => '1',
            'data' => ['options' => [
                ['value' => '1', 'name' => '1 IPv4 Address'],
                ['value' => '2', 'name' => '2 IPv4 Addresses', 'daily_price' => 0.2],
            ]],
        ]);
    }

    public function test_vps_checkout_groups_real_options_and_escapes_descriptions(): void
    {
        $this->get(route('packages.view', $this->package->slug))->assertOk()
            ->assertSee('Complete your order for B-VPS-2')->assertSee('50GB SSD Storage')
            ->assertSee('Service Configuration')->assertSee('Server Name')->assertSee('Hostname')
            ->assertSee('Choose Operating System')->assertSee('Debian 12 (Bookworm)')
            ->assertSee('Ubuntu 24.04')->assertSee('Other')->assertSee('Windows Server 2022')
            ->assertSee('IP Addresses')->assertSee('2 IPv4 Addresses')->assertSee('Order summary')
            ->assertSee('wire:model.change="config_options.operating_system"', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('CentOS')->assertSee('Rocky Linux')->assertSee('AlmaLinux')->assertSee('Fedora')
            ->assertDontSee('Review us on');

    }

    public function test_initial_defaults_show_the_selected_family_and_keep_configuration_inside_livewire_root(): void
    {
        $this->package->configOptions()->where('key', 'operating_system')->update(['default_value' => 'ubuntu-2404']);

        $component = Volt::test(client_view_path('packages.livewire.view-package'), ['packageSlug' => $this->package->slug])
            ->assertSet('config_options.operating_system', 'ubuntu-2404');

        $html = $component->html();
        $this->assertStringContainsString('group: \'ubuntu\'', $html);
        $this->assertMatchesRegularExpression('/value="ubuntu-2404"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/^\s*<div[^>]*wire:id=/', $html);
    }

    public function test_selected_family_and_option_cost_follow_configuration_and_billing_cycle(): void
    {
        $component = Volt::test(client_view_path('packages.livewire.view-package'), ['packageSlug' => $this->package->slug])
            ->assertSet('config_options.operating_system', 'debian-11')
            ->set('config_options.operating_system', 'windows-2022')
            ->set('config_options.ip_addresses', '2')->assertSee('$19.00')
            ->set('packagePriceId', $this->yearly->id)->assertSee('$209.50')
            ->assertSee('Plus $5.00 one-time setup fee.');

        $this->assertStringContainsString('group: \'other\'', $component->html());
        $this->assertMatchesRegularExpression('/value="windows-2022"[^>]*checked/', $component->html());
    }

    public function test_selected_os_and_ip_values_are_saved_to_cart_with_their_actual_prices(): void
    {
        $cart = $this->currentCart();

        Livewire::withQueryParams(['cart' => $cart])
            ->test(client_view_path('packages.livewire.view-package'), ['packageSlug' => $this->package->slug])
            ->set('config_options.operating_system', 'windows-2022')
            ->set('config_options.ip_addresses', '2')
            ->call('addToCart')->assertHasNoErrors()->assertRedirect(route('cart'));

        $item = $cart->items()->firstOrFail();
        $this->assertSame('windows-2022', $item->options()->where('key', 'operating_system')->firstOrFail()->value);
        $this->assertEquals(3, $item->options()->where('key', 'operating_system')->firstOrFail()->price);
        $this->assertEquals(6, $item->options()->where('key', 'ip_addresses')->firstOrFail()->price);
    }

    public function test_unavailable_os_is_rejected_and_no_cart_item_is_created(): void
    {
        $cart = $this->currentCart();

        Livewire::withQueryParams(['cart' => $cart])
            ->test(client_view_path('packages.livewire.view-package'), ['packageSlug' => $this->package->slug])
            ->set('config_options.operating_system', 'unsupported-image')
            ->call('addToCart')->assertHasErrors('config_options.operating_system');

        $this->assertSame(0, $cart->items()->count());
    }

    public function test_plan_without_configuration_or_prices_has_no_invented_options(): void
    {
        $this->package->configOptions()->delete();
        $this->package->prices()->delete();

        $this->get(route('packages.view', $this->package->slug))->assertOk()
            ->assertSee('Complete your order for B-VPS-2')->assertSee('This plan is currently unavailable')
            ->assertDontSee('Choose Operating System')->assertDontSee('Service Configuration')
            ->assertDontSee('IP Addresses')->assertDontSee('Add to cart');
    }

    private function currentCart(): Cart
    {
        $this->actingAs(User::factory()->create(['status' => 'active', 'language' => 'en']));
        $this->get(route('cart'))->assertOk();
        $cart = Cart::query()->where('user_id', auth()->id())->firstOrFail();
        Livewire::listen('hydrate', function () use ($cart): void {
            request()->merge(['cart' => $cart->fresh()]);
        });

        return $cart;
    }
}
