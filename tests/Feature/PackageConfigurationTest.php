<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\ServerConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PackageConfigurationTest extends TestCase
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
        $category = Category::query()->create(['name' => 'Hosting', 'slug' => 'hosting', 'status' => 'active', 'icon' => '']);
        $connection = ServerConnection::query()->create(['alias' => 'package-test', 'extension_identifier' => 'server-universal']);
        $this->package = Package::query()->create(['category_id' => $category->id, 'connection_id' => $connection->id, 'name' => 'Starter configuration', 'slug' => 'starter-config', 'status' => 'active', 'description' => '']);
        $this->monthly = $this->package->prices()->create(['period_in_days' => 30, 'price' => 2.99, 'setup_fee' => 0, 'is_active' => true]);
        $this->yearly = $this->package->prices()->create(['period_in_days' => 365, 'price' => 29.99, 'setup_fee' => 5, 'is_active' => true]);
    }

    public function test_package_page_omits_empty_overview_and_displays_billing_choices(): void
    {
        $this->get(route('packages.view', $this->package->slug))->assertOk()
            ->assertSee('Starter configuration')->assertSee('Order summary')
            ->assertSee('No setup fee')->assertDontSee('package-overview-heading');
    }

    public function test_changing_billing_cycle_updates_selected_price_and_setup_fee(): void
    {
        $component = Volt::test(client_view_path('packages.livewire.view-package'), ['packageSlug' => $this->package->slug])
            ->assertSet('packagePriceId', $this->monthly->id)
            ->set('packagePriceId', $this->yearly->id)
            ->assertSee('Plus $5.00 one-time setup fee.');

        $this->assertStringContainsString('$29.99', str($component->html())->after('class="vh-summary-total"')->before('</div></div>')->toString());
    }

    public function test_package_features_and_configuration_fields_are_available(): void
    {
        $this->package->features()->create(['description' => '1 GB memory', 'sort_order' => 0]);
        $this->package->configOptions()->create(['label' => 'Server name', 'key' => 'server_name', 'type' => 'text', 'default_value' => 'My server', 'rules' => 'nullable|string']);

        Volt::test(client_view_path('packages.livewire.view-package'), ['packageSlug' => $this->package->slug])
            ->assertSee('Your plan at a glance')->assertSee('1 GB memory')
            ->assertSee('Configure your service')->assertSee('Server name')
            ->assertSet('config_options.server_name', 'My server');
    }

    public function test_package_without_prices_shows_an_unavailable_state(): void
    {
        $this->package->prices()->delete();
        $this->get(route('packages.view', $this->package->slug))->assertOk()
            ->assertSee('No billing options are currently available')
            ->assertSee('This plan is currently unavailable')
            ->assertDontSee('Review before checkout');
    }
}
