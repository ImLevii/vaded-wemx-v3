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
use Tests\TestCase;

class HostingStorefrontTest extends TestCase
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

        $category = Category::query()->create(['name' => 'Community hosting', 'slug' => 'community', 'status' => 'active', 'icon' => '']);
        $connection = ServerConnection::query()->create(['alias' => 'storefront-test', 'extension_identifier' => 'server-universal']);
        $this->package = Package::query()->create(['category_id' => $category->id, 'connection_id' => $connection->id, 'name' => 'Community 4GB', 'slug' => 'community-4gb', 'status' => 'active']);
        $this->monthly = $this->package->prices()->create(['period_in_days' => 30, 'price' => 12, 'setup_fee' => 0, 'is_active' => true]);
        $this->yearly = $this->package->prices()->create(['period_in_days' => 365, 'price' => 120, 'setup_fee' => 5, 'is_active' => true]);
        $this->package->features()->create(['description' => '4 GB memory', 'sort_order' => 0]);
    }

    public function test_homepage_selects_a_real_catalog_and_preserves_price_cycle_links(): void
    {
        $this->get('/')->assertOk()->assertSee('AT FULL POWER.')
            ->assertSee('Game Server &amp; Cloud Hosting', false)
            ->assertDontSee('Game Server &amp;amp; Cloud Hosting', false)
            ->assertSee('Community hosting plans')->assertSee('4 GB memory')
            ->assertSee('$12.00')->assertSee('$120.00')->assertSee('$5.00 one-time setup')
            ->assertSee(route('packages.view', ['package' => $this->package->slug, 'packagePriceId' => $this->yearly->id]), false)
            ->assertDontSee('MOST POPULAR')->assertDontSee('All Systems Operational')
            ->assertDontSee('Verified customer');
    }

    public function test_starting_prices_exclude_hidden_packages_and_handle_plans_without_prices(): void
    {
        $hidden = $this->package->replicate();
        $hidden->fill(['name' => 'Private promotion', 'slug' => 'private-promotion', 'status' => 'unlisted'])->save();
        $hidden->prices()->create(['period_in_days' => 30, 'price' => 0.01, 'setup_fee' => 0]);
        $this->package->prices()->delete();

        $this->get('/')->assertOk()->assertSee('Currently unavailable')
            ->assertDontSee('Private promotion')->assertDontSee('$0.01');
    }

    public function test_unavailable_or_malformed_category_does_not_render_private_plans(): void
    {
        $this->package->category->update(['status' => 'disabled']);
        $this->get('/?category=community')->assertOk()->assertSee('This category is not available.')
            ->assertDontSee('Community 4GB');
        $this->get('/?category[]=community')->assertOk()->assertSee('This category is not available.');
    }

    public function test_verified_infrastructure_and_reviews_are_optional_and_escaped(): void
    {
        config([
            'hosting.locations' => [['city' => 'Test location', 'region' => 'Test region', 'status' => 'Limited capacity']],
            'hosting.reviews' => [['name' => 'Community owner', 'quote' => '<script>alert(1)</script>', 'service' => 'Hosting', 'verified' => true]],
        ]);

        $this->get('/')->assertOk()->assertSee('Test location')->assertSee('Limited capacity')
            ->assertSee('Verified customer')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_cart_configuration_edit_preserves_item_and_quantity(): void
    {
        $cart = $this->currentCart();
        $item = $cart->items()->create(['cartable_type' => PackagePrice::class, 'cartable_id' => $this->monthly->id, 'name' => $this->package->name, 'price' => 12, 'quantity' => 2]);
        $this->package->configOptions()->create(['label' => 'Server name', 'key' => 'server_name', 'type' => 'text', 'rules' => 'required|string', 'default_value' => 'My server']);
        $item->options()->create(['name' => 'Server name', 'key' => 'server_name', 'value' => 'Original', 'price' => 0]);

        $this->get(route('cart'))->assertOk()->assertSee('Edit configuration')
            ->assertSee(route('packages.view', ['package' => $this->package->slug, 'cartItemId' => $item->id]), false);

        Livewire::withQueryParams(['cartItemId' => $item->id, 'cart' => $cart])
            ->test(client_view_path('packages.livewire.view-package'), ['packageSlug' => $this->package->slug])
            ->assertSet('cartItemId', $item->id)
            ->assertSet('config_options.server_name', 'Original')
            ->assertSee('Save configuration')
            ->set('packagePriceId', $this->yearly->id)
            ->set('config_options.server_name', 'Updated community')
            ->call('addToCart')->assertHasNoErrors()->assertRedirect(route('cart'));

        $this->assertSame(1, $cart->items()->count());
        $this->assertSame(2, $item->fresh()->quantity);
        $this->assertSame($this->yearly->id, $item->fresh()->cartable_id);
        $this->assertEquals(125, $item->fresh()->price);
        $this->assertSame('Updated community', $item->options()->first()->value);
    }

    public function test_cart_edit_rejects_another_sessions_item(): void
    {
        $this->currentCart();
        $otherCart = Cart::query()->create(['session_id' => 'other-session']);
        $item = $otherCart->items()->create(['cartable_type' => PackagePrice::class, 'cartable_id' => $this->monthly->id, 'name' => 'Private item', 'price' => 12]);

        $this->get(route('packages.view', ['package' => $this->package->slug, 'cartItemId' => $item->id]))->assertNotFound();
        $this->assertEquals(12, $item->fresh()->price);
    }

    public function test_invalid_configuration_keeps_the_original_cart_item(): void
    {
        $cart = $this->currentCart();
        $item = $cart->items()->create(['cartable_type' => PackagePrice::class, 'cartable_id' => $this->monthly->id, 'name' => $this->package->name, 'price' => 12, 'quantity' => 1]);
        $this->package->configOptions()->create(['label' => 'Server name', 'key' => 'server_name', 'type' => 'text', 'rules' => 'required|string', 'default_value' => '']);

        Livewire::withQueryParams(['cartItemId' => $item->id, 'cart' => $cart])
            ->test(client_view_path('packages.livewire.view-package'), ['packageSlug' => $this->package->slug])
            ->set('config_options.server_name', '')->call('addToCart')->assertHasErrors();

        $this->assertSame($this->monthly->id, $item->fresh()->cartable_id);
        $this->assertEquals(12, $item->fresh()->price);
    }

    public function test_error_pages_keep_the_status_code_and_brand(): void
    {
        $this->get('/missing-vaded-page')->assertNotFound()->assertSee('Off the map.')->assertSee('Vaded');
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
