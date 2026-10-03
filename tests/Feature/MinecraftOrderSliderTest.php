<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\ServerConnection;
use App\Support\MinecraftOrderOptions;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MinecraftOrderSliderTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private Package $starter;

    private Package $larger;

    private PackagePrice $monthly;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.installed' => true, 'app.license_key' => 'WMX-TESTING-KEY']);
        Cache::put('lcs_checked_at', now(), 21600);
        Currency::query()->updateOrCreate(['currency' => 'USD'], ['display_name' => 'US Dollar', 'format' => '$1,0.00', 'market_rate' => 1, 'is_active' => true]);
        session()->put('currency', 'USD');
        $this->category = Category::query()->create(['name' => 'Minecraft', 'slug' => 'minecraft-server-hosting', 'status' => 'active', 'icon' => '']);
        $connection = ServerConnection::query()->create(['alias' => 'slider-test', 'extension_identifier' => 'server-universal']);
        $this->starter = Package::query()->create([
            'category_id' => $this->category->id, 'connection_id' => $connection->id,
            'name' => '1GB Premium Minecraft Server', 'slug' => 'minecraft-1gb', 'status' => 'active',
            'data' => ['memory_limit' => 1, 'cpu_limit' => 100], 'sort_order' => 10,
        ]);
        $this->monthly = $this->starter->prices()->create(['period_in_days' => 30, 'price' => 2.25, 'setup_fee' => 0, 'is_active' => true]);
        $this->starter->configOptions()->create([
            'label' => 'CPU limit', 'key' => 'cpu_limit', 'type' => 'range', 'default_value' => 100,
            'rules' => 'required|integer|min:100|max:600',
            'data' => ['min_value' => 100, 'max_value' => 600, 'step_value' => 100, 'free_value' => 100, 'daily_price' => 0.0005],
        ]);
        $this->starter->configOptions()->create(['label' => 'Server name', 'key' => 'server_name', 'type' => 'text', 'default_value' => 'My Minecraft server', 'rules' => 'required|string']);
        $this->larger = $this->starter->replicate();
        $this->larger->fill(['name' => '12GB Premium Minecraft Server', 'slug' => 'minecraft-12gb', 'data' => ['memory_limit' => 12, 'cpu_limit' => 200], 'sort_order' => 0])->save();
        $this->larger->prices()->create(['period_in_days' => 30, 'price' => 27, 'setup_fee' => 3, 'is_active' => true]);
    }

    public function test_minecraft_catalog_renders_real_discrete_sizes_and_reference_controls(): void
    {
        $this->get('/?category='.$this->category->slug)->assertOk()
            ->assertSee('Order your Minecraft server')->assertSee('Minecraft server memory')
            ->assertSee('Additional CPU threads')->assertSee('Configure Server')
            ->assertSee('$2.25')->assertSee('0 Additional Threads')->assertSee('12GB')
            ->assertSee('max="5"', false)->assertDontSee('36GB');

        Volt::test(client_view_path('categories.livewire.product-list'), ['category' => $this->category->slug])
            ->assertSet('minecraftPeriod', '30')
            ->assertSet('minecraftPlans', fn (Collection $plans): bool => $plans->pluck('memory')->all() === [1.0, 12.0]);
    }

    public function test_thread_selection_updates_price_and_hands_actual_cpu_value_to_checkout(): void
    {
        Volt::test(client_view_path('categories.livewire.product-list'), ['category' => $this->category->slug])
            ->set('minecraftThreadIndex', 2)->assertSee('2 Additional Threads')->assertSee('$5.25')
            ->call('configureMinecraftServer')->assertHasNoErrors()
            ->assertRedirect(route('packages.view', ['package' => $this->starter->slug, 'packagePriceId' => $this->monthly->id, 'config_options' => ['cpu_limit' => 300]]));

        Livewire::withQueryParams(['packagePriceId' => $this->monthly->id, 'config_options' => ['cpu_limit' => 300]])
            ->test(client_view_path('packages.livewire.view-package'), ['packageSlug' => $this->starter->slug])
            ->assertSet('config_options.cpu_limit', 300)
            ->assertSet('config_options.server_name', 'My Minecraft server');
    }

    public function test_changing_plan_resets_extra_threads_and_preserves_actual_price(): void
    {
        $price = $this->larger->prices()->first();
        Volt::test(client_view_path('categories.livewire.product-list'), ['category' => $this->category->slug])
            ->set('minecraftThreadIndex', 5)->set('minecraftPlanIndex', 1)
            ->assertSet('minecraftThreadIndex', 0)->assertSee('$27.00')->assertSee('$3.00 one-time setup')
            ->assertSee('Additional threads are unavailable.')
            ->call('configureMinecraftServer')->assertHasNoErrors()
            ->assertRedirect(route('packages.view', ['package' => $this->larger->slug, 'packagePriceId' => $price->id]));
    }

    public function test_cycle_selection_recalculates_thread_cost_without_changing_cpu_value(): void
    {
        $yearly = $this->starter->prices()->create(['period_in_days' => 365, 'price' => 22.5, 'setup_fee' => 5, 'is_active' => true]);
        Volt::test(client_view_path('categories.livewire.product-list'), ['category' => $this->category->slug])
            ->set('minecraftThreadIndex', 2)->set('minecraftPeriod', '365')->assertSee('$59.00')
            ->call('configureMinecraftServer')->assertHasNoErrors()
            ->assertRedirect(route('packages.view', ['package' => $this->starter->slug, 'packagePriceId' => $yearly->id, 'config_options' => ['cpu_limit' => 300]]));
    }

    public function test_invalid_slider_choices_do_not_redirect(): void
    {
        foreach ([['minecraftPlanIndex', 99], ['minecraftPlanIndex', -1], ['minecraftThreadIndex', 6], ['minecraftThreadIndex', -1], ['minecraftPeriod', '999']] as [$field, $value]) {
            Volt::test(client_view_path('categories.livewire.product-list'), ['category' => $this->category->slug])
                ->set($field, $value)->call('configureMinecraftServer')->assertHasErrors($field)->assertNoRedirect();
        }
    }

    public function test_hidden_packages_and_inactive_prices_are_excluded_from_slider(): void
    {
        $this->larger->update(['status' => 'unlisted']);
        $this->starter->prices()->create(['period_in_days' => 365, 'price' => 1, 'setup_fee' => 0, 'is_active' => false]);
        Volt::test(client_view_path('categories.livewire.product-list'), ['category' => $this->category->slug])
            ->assertSet('minecraftPlans', fn (Collection $plans): bool => $plans->count() === 1 && $plans[0]['prices']->count() === 1);

        $this->monthly->update(['is_active' => false]);
        Volt::test(client_view_path('categories.livewire.product-list'), ['category' => $this->category->slug])
            ->assertDontSee('Order your Minecraft server');
    }

    public function test_unavailable_products_are_rechecked_before_checkout(): void
    {
        $component = Volt::test(client_view_path('categories.livewire.product-list'), ['category' => $this->category->slug]);
        $this->starter->update(['status' => 'disabled']);
        $this->expectException(ModelNotFoundException::class);
        $component->call('configureMinecraftServer');
    }

    public function test_other_categories_keep_their_existing_catalog(): void
    {
        $this->category->update(['slug' => 'community']);
        $this->get('/?category=community')->assertOk()->assertDontSee('Order your Minecraft server');
    }

    public function test_thread_stops_respect_configured_steps_and_select_option_prices(): void
    {
        $option = $this->starter->configOptions()->where('key', 'cpu_limit')->first();
        $option->update(['data' => ['min_value' => 100, 'max_value' => 500, 'step_value' => 200, 'free_value' => 100, 'daily_price' => 0.0005]]);
        $this->assertSame([0, 2, 4], array_column(MinecraftOrderOptions::threads($this->starter->fresh()), 'additional'));

        $option->update(['type' => 'select', 'data' => ['options' => [
            ['name' => 'One thread', 'value' => 100, 'daily_price' => 0],
            ['name' => 'Three threads', 'value' => 300, 'daily_price' => 0.1],
        ]]]);
        $choices = MinecraftOrderOptions::threads($this->starter->fresh());
        $this->assertSame([100, 300], array_column($choices, 'value'));
        $this->assertSame(0.1, $choices[1]['dailyPrice']);
    }

    public function test_memory_detection_does_not_mistake_disk_features_for_ram(): void
    {
        $this->starter->fill(['name' => 'Starter', 'data' => []]);
        $this->starter->features()->create(['description' => '25 GB disk space']);
        $this->starter->features()->create(['description' => '4 GB RAM']);

        $this->assertSame(4.0, MinecraftOrderOptions::memory($this->starter));
    }

    public function test_duplicate_cycles_use_the_same_price_for_preview_and_checkout(): void
    {
        $cheaper = $this->starter->prices()->create(['period_in_days' => 30, 'price' => 2, 'setup_fee' => 0, 'is_active' => true]);
        Volt::test(client_view_path('categories.livewire.product-list'), ['category' => $this->category->slug])
            ->assertSet('minecraftPlans', fn (Collection $plans): bool => $plans[0]['prices']->count() === 1 && $plans[0]['prices'][0]->id === $cheaper->id)
            ->call('configureMinecraftServer')->assertHasNoErrors()
            ->assertRedirect(route('packages.view', ['package' => $this->starter->slug, 'packagePriceId' => $cheaper->id, 'config_options' => ['cpu_limit' => 100]]));
    }

    public function test_one_time_thread_cost_uses_the_configured_day_equivalent(): void
    {
        $this->starter->configOptions()->where('key', 'cpu_limit')->update(['onetime_day_equivalent' => 100]);
        $oneTime = $this->starter->prices()->create(['period_in_days' => 0, 'price' => 10, 'setup_fee' => 0, 'is_active' => true]);
        Volt::test(client_view_path('categories.livewire.product-list'), ['category' => $this->category->slug])
            ->set('minecraftPeriod', '0')->set('minecraftThreadIndex', 1)->assertSee('$15.00')
            ->call('configureMinecraftServer')->assertHasNoErrors()
            ->assertRedirect(route('packages.view', ['package' => $this->starter->slug, 'packagePriceId' => $oneTime->id, 'config_options' => ['cpu_limit' => 200]]));
    }
}
