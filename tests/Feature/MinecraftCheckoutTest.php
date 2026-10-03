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

class MinecraftCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Package $package;

    private PackagePrice $monthly;

    private PackagePrice $quarterly;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.installed' => true, 'app.license_key' => 'WMX-TESTING-KEY']);
        Cache::put('lcs_checked_at', now(), 21600);
        Currency::query()->updateOrCreate(['currency' => 'USD'], ['display_name' => 'US Dollar', 'format' => '$1,0.00', 'market_rate' => 1, 'is_active' => true]);
        session()->put('currency', 'USD');
        $category = Category::query()->create(['name' => 'Minecraft Hosting', 'slug' => 'minecraft-hosting', 'status' => 'active', 'icon' => '']);
        $connection = ServerConnection::query()->create(['alias' => 'minecraft-checkout-test', 'extension_identifier' => 'server-universal']);
        $this->package = Package::query()->create(['category_id' => $category->id, 'connection_id' => $connection->id, 'name' => 'Minecraft 2GB', 'slug' => 'minecraft-2gb', 'status' => 'active']);
        $this->monthly = $this->package->prices()->create(['period_in_days' => 30, 'price' => 6, 'setup_fee' => 0, 'is_active' => true]);
        $this->quarterly = $this->package->prices()->create(['period_in_days' => 90, 'price' => 17.46, 'setup_fee' => 0, 'is_active' => true]);
        $this->package->configOptions()->create([
            'key' => 'location_id', 'label' => 'Server location', 'type' => 'select', 'rules' => 'required|integer', 'default_value' => '1',
            'data' => ['options' => [
                ['value' => '1', 'name' => 'Europe', 'flag' => 'EU', 'ping_url' => 'https://eu.example.com/ping'],
                ['value' => '2', 'name' => 'North America', 'flag' => 'CA', 'daily_price' => 0.1, 'ping_url' => 'https://na.example.com/ping'],
                ['value' => '3', 'name' => 'Unavailable region', 'available' => false],
            ]],
        ]);
        $this->package->configOptions()->create([
            'key' => 'server_software', 'label' => 'Server software', 'type' => 'select', 'rules' => 'required|string', 'default_value' => 'vanilla',
            'data' => ['options' => [
                ['value' => 'paper', 'name' => 'Paper'],
                ['value' => 'vanilla', 'name' => 'Vanilla'],
                ['value' => 'fabric', 'name' => 'Fabric', 'description' => '<script>alert(1)</script>'],
                ['value' => 'ftb', 'name' => 'FTB Modpack', 'group' => 'modpacks', 'daily_price' => 4 / 30],
            ]],
        ]);
        $this->package->configOptions()->create(['key' => 'server_name', 'label' => 'Server name', 'type' => 'text', 'rules' => 'required|string', 'default_value' => 'My world']);
    }

    public function test_minecraft_checkout_renders_real_cycles_locations_and_grouped_software(): void
    {
        $this->get(route('packages.view', $this->package->slug))->assertOk()
            ->assertSee('Choose a Billing Cycle')->assertSee('3% discount')->assertSee('0% discount')
            ->assertSee('Choose a Location')->assertSee('Europe')->assertSee('North America')
            ->assertSee('Test my ping')->assertSee('Ping not tested')->assertDontSee('149ms')
            ->assertSee('Server Version')->assertSee('Jars')->assertSee('Modpacks')->assertSee('Paper')->assertSee('FTB Modpack')
            ->assertSee('+ $4.00 / Monthly')->assertSee('Service Configuration')->assertSee('Server name')
            ->assertSee('wire:model.change="config_options.location_id"', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('Unavailable region')->assertDontSee('Singapore');
    }

    public function test_recommended_software_preserves_other_selections_and_prefers_configured_recommendations(): void
    {
        Volt::test(client_view_path('packages.livewire.view-package'), ['packageSlug' => $this->package->slug])
            ->set('config_options.location_id', '2')->set('config_options.server_name', 'Our world')
            ->call('useRecommendedMinecraftConfiguration')->assertSet('config_options.server_software', 'paper')
            ->assertSet('config_options.location_id', '2')->assertSet('config_options.server_name', 'Our world');

        $option = $this->package->configOptions()->where('key', 'server_software')->firstOrFail();
        $option->update(['data' => ['options' => [['value' => 'fabric', 'name' => 'Fabric', 'recommended' => true]]]]);
        Volt::test(client_view_path('packages.livewire.view-package'), ['packageSlug' => $this->package->slug])
            ->call('useRecommendedMinecraftConfiguration')->assertSet('config_options.server_software', 'fabric');
    }

    public function test_configuration_costs_follow_cycle_and_the_selected_modpack_remains_visible(): void
    {
        $component = Volt::test(client_view_path('packages.livewire.view-package'), ['packageSlug' => $this->package->slug])
            ->set('config_options.location_id', '2')->set('config_options.server_software', 'ftb')->assertSee('$13.00')
            ->set('packagePriceId', $this->quarterly->id)->assertSee('$38.46')->assertSee('+ $12.00 / Quarterly');
        $this->assertStringContainsString("group: 'Modpacks'", $component->html());
        $this->assertMatchesRegularExpression('/value="ftb"[^>]*checked/', $component->html());
    }

    public function test_one_time_surcharges_use_the_options_day_equivalent_and_hide_discount(): void
    {
        $this->package->configOptions()->where('key', 'server_software')->update(['onetime_day_equivalent' => 30]);
        $onetime = $this->package->prices()->create(['period_in_days' => 0, 'price' => 10, 'setup_fee' => 0, 'is_active' => true]);
        Volt::test(client_view_path('packages.livewire.view-package'), ['packageSlug' => $this->package->slug])
            ->set('config_options.server_software', 'ftb')->set('packagePriceId', $onetime->id)
            ->assertSee('$14.00')->assertSee('+ $4.00 / One Time');
    }

    public function test_choices_are_saved_to_cart_with_their_actual_prices(): void
    {
        $cart = $this->currentCart();
        Livewire::withQueryParams(['cart' => $cart])
            ->test(client_view_path('packages.livewire.view-package'), ['packageSlug' => $this->package->slug])
            ->set('config_options.location_id', '2')->set('config_options.server_software', 'ftb')
            ->call('addToCart')->assertHasNoErrors()->assertRedirect(route('cart'));
        $item = $cart->items()->firstOrFail();
        $this->assertSame('2', $item->options()->where('key', 'location_id')->firstOrFail()->value);
        $this->assertEquals(3, $item->options()->where('key', 'location_id')->firstOrFail()->price);
        $this->assertSame('ftb', $item->options()->where('key', 'server_software')->firstOrFail()->value);
        $this->assertEquals(4, $item->options()->where('key', 'server_software')->firstOrFail()->price);
    }

    public function test_invalid_and_unavailable_selections_cannot_be_added_to_cart(): void
    {
        $cart = $this->currentCart();
        foreach ([['location_id', '99'], ['location_id', '3'], ['server_software', 'unknown']] as [$key, $value]) {
            Livewire::withQueryParams(['cart' => $cart])
                ->test(client_view_path('packages.livewire.view-package'), ['packageSlug' => $this->package->slug])
                ->set('config_options.'.$key, $value)->call('addToCart')->assertHasErrors('config_options.'.$key);
        }
        $this->assertSame(0, $cart->items()->count());
    }

    public function test_inactive_billing_cycles_are_hidden_and_rejected(): void
    {
        $this->monthly->update(['is_active' => false]);
        Volt::test(client_view_path('packages.livewire.view-package'), ['packageSlug' => $this->package->slug])
            ->assertSet('packagePriceId', $this->quarterly->id)->assertDontSee('0% discount')
            ->set('packagePriceId', $this->monthly->id)->call('addToCart')->assertHasErrors('package_error');
    }

    public function test_plans_without_configured_choices_or_prices_handle_unavailability(): void
    {
        $this->package->configOptions()->delete();
        $this->get(route('packages.view', $this->package->slug))->assertOk()
            ->assertSee('Location selection is not available')->assertSee('Software selection is not available')
            ->assertDontSee('Test my ping')->assertDontSee('Use our recommended server software');
        $this->package->prices()->delete();
        $this->get(route('packages.view', $this->package->slug))->assertOk()
            ->assertSee('This plan is currently unavailable')->assertDontSee('Add to cart');
    }

    public function test_minecraft_configuration_can_be_reopened_and_edited_in_cart(): void
    {
        $cart = $this->currentCart();
        $item = $cart->items()->create(['cartable_type' => PackagePrice::class, 'cartable_id' => $this->monthly->id, 'name' => $this->package->name, 'price' => 6, 'quantity' => 2]);
        $item->options()->create(['name' => 'Server software', 'key' => 'server_software', 'value' => 'ftb', 'price' => 4]);
        Livewire::withQueryParams(['cart' => $cart, 'cartItemId' => $item->id])
            ->test(client_view_path('packages.livewire.view-package'), ['packageSlug' => $this->package->slug])
            ->assertSet('config_options.server_software', 'ftb')->assertSee('Save configuration')
            ->set('config_options.server_software', 'paper')->call('addToCart')->assertHasNoErrors();
        $this->assertSame(1, $cart->items()->count());
        $this->assertSame(2, $item->fresh()->quantity);
        $this->assertSame('paper', $item->options()->where('key', 'server_software')->firstOrFail()->value);
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
