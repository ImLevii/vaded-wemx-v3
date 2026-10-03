<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Package;
use App\Models\ServerConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class VpsStorefrontTest extends TestCase
{
    use RefreshDatabase;

    private Category $vpsCategory;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.installed' => true, 'app.license_key' => 'WMX-TESTING-KEY']);
        Cache::put('lcs_checked_at', now(), 21600);
        Currency::query()->updateOrCreate(['currency' => 'USD'], ['display_name' => 'US Dollar', 'format' => '$1,0.00', 'market_rate' => 1, 'is_active' => true]);
        session()->put('currency', 'USD');

        Category::query()->create(['name' => 'Game hosting', 'slug' => 'games', 'status' => 'active', 'icon' => '']);
        $this->vpsCategory = Category::query()->create(['name' => 'VPS Hosting', 'slug' => 'vps-hosting', 'description' => 'A cloud for your next project.', 'status' => 'active', 'icon' => '']);
        $connection = ServerConnection::query()->create(['alias' => 'vps-storefront-test', 'extension_identifier' => 'server-universal']);
        $this->package = Package::query()->create(['category_id' => $this->vpsCategory->id, 'connection_id' => $connection->id, 'name' => 'Basic VPS', 'slug' => 'basic-vps', 'status' => 'active']);
        $this->package->prices()->create(['period_in_days' => 30, 'price' => 10, 'setup_fee' => 0, 'is_active' => true]);

        foreach (['2 vCores', '4 GB DDR4 RAM', '25 GB SSD Storage', 'Unlimited Bandwidth', 'Ubuntu 22.04 OS'] as $index => $description) {
            $this->package->features()->create(['description' => $description, 'sort_order' => $index]);
        }
    }

    public function test_vps_category_displays_its_actual_resources_and_checkout_price(): void
    {
        $monthly = $this->package->prices()->first();

        $this->get('/?category=vps-hosting')->assertOk()
            ->assertSee('Your cloud.')
            ->assertSee('At full power.')
            ->assertSee('A cloud for your next project.')
            ->assertSee('Basic VPS')
            ->assertSee('$10.00')
            ->assertSee('2 vCores')->assertSee('4 GB DDR4 RAM')
            ->assertSee('25 GB SSD Storage')->assertSee('Unlimited Bandwidth')
            ->assertSee('Ubuntu 22.04 OS')
            ->assertSee('No setup fee')
            ->assertSee(route('packages.view', ['package' => $this->package->slug, 'packagePriceId' => $monthly->id]), false)
            ->assertDontSee('vh-hero-wordmark', false)
            ->assertDontSee('Most popular')->assertDontSee('Buffalo')->assertDontSee('9950X');
    }

    public function test_vps_catalog_preserves_billing_cycles_fees_and_escapes_features(): void
    {
        $yearly = $this->package->prices()->create(['period_in_days' => 365, 'price' => 100, 'setup_fee' => 5, 'is_active' => true]);
        $this->package->features()->create(['description' => '<script>alert(1)</script>', 'sort_order' => 10]);

        $this->get('/?category=vps-hosting')->assertOk()
            ->assertSee('Monthly')->assertSee('Annually')
            ->assertSee('$100.00')->assertSee('$5.00 one-time setup')
            ->assertSee(route('packages.view', ['package' => $this->package->slug, 'packagePriceId' => $yearly->id]), false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_vps_grid_lists_all_public_plans_and_handles_unpriced_plans(): void
    {
        for ($index = 2; $index <= 6; $index++) {
            $package = $this->package->replicate();
            $package->fill(['name' => 'VPS '.$index, 'slug' => 'vps-'.$index])->save();
            $package->prices()->create(['period_in_days' => 30, 'price' => 10 * $index, 'setup_fee' => 0, 'is_active' => true]);
        }

        $privatePackage = $this->package->replicate();
        $privatePackage->fill(['name' => 'Private VPS', 'slug' => 'private-vps', 'status' => 'unlisted'])->save();
        $this->package->prices()->delete();

        $response = $this->get('/?category=vps-hosting')->assertOk()
            ->assertSee('Currently unavailable')->assertDontSee('Private VPS')
            ->assertDontSee('Show all plans');

        for ($index = 2; $index <= 6; $index++) {
            $response->assertSee('VPS '.$index);
        }
    }

    public function test_vps_layout_is_scoped_to_an_available_vps_category(): void
    {
        $this->get('/')->assertOk()->assertSeeText('VADED HOSTING')->assertDontSee('vh-vps-hero', false);
        $this->get('/?category=games')->assertOk()->assertSeeText('Game hosting plans')
            ->assertDontSee('vh-store-hero', false)->assertDontSee('vh-vps-hero', false);

        $this->package->update(['status' => 'disabled']);
        $this->get('/?category=vps-hosting')->assertOk()->assertSee('More VPS plans are on the way.')->assertDontSee('Basic VPS');

        $this->vpsCategory->update(['status' => 'disabled']);
        $this->get('/?category=vps-hosting')->assertOk()->assertSee('This category is not available.')->assertDontSee('vh-vps-hero', false);

        $this->get('/?category[]=vps-hosting')->assertOk()->assertSee('This category is not available.');
    }
}
