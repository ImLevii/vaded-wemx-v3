<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AppearanceSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.installed' => true, 'app.license_key' => 'WMX-TESTING-KEY']);
        Cache::put('lcs_checked_at', now(), 21600);
        foreach (['mode', 'accent', 'seasonal', 'motion', 'logo_motion'] as $key) {
            Setting::forget('appearance_'.$key);
        }
    }

    public function test_admin_can_save_and_reload_site_appearance(): void
    {
        $this->actingAs(User::factory()->create(['id' => 1, 'status' => 'active', 'language' => 'en']));
        Volt::test(admin_view_path('settings.livewire.appearance'))
            ->set('mode', 'system')->set('accent', 'emerald')->set('seasonal', 'christmas')
            ->set('motion', false)->set('logoMotion', true)->call('saveChanges')
            ->assertHasNoErrors()->assertDispatched('appearance-saved');
        Volt::test(admin_view_path('settings.livewire.appearance'))
            ->assertSet('mode', 'system')->assertSet('accent', 'emerald')->assertSet('seasonal', 'christmas')
            ->assertSet('motion', false)->assertSet('logoMotion', true);
        $this->assertDatabaseHas('settings', ['key' => 'appearance_seasonal', 'value' => 'christmas']);
    }

    public function test_invalid_options_are_rejected_without_saving(): void
    {
        $this->actingAs(User::factory()->create(['id' => 1, 'status' => 'active', 'language' => 'en']));
        Volt::test(admin_view_path('settings.livewire.appearance'))
            ->set('mode', 'invalid')->set('accent', '<script>')->set('seasonal', 'unknown')
            ->call('saveChanges')->assertHasErrors(['mode', 'accent', 'seasonal']);
        $this->assertDatabaseMissing('settings', ['key' => 'appearance_seasonal']);
    }

    public function test_dashboard_staff_without_settings_permission_cannot_open_the_component(): void
    {
        $staff = User::factory()->create(['id' => 2, 'status' => 'active', 'language' => 'en']);
        $role = Role::query()->create(['name' => 'Dashboard only', 'super_admin' => false]);
        $role->permissions()->create(['permission' => 'admin.dashboard']);
        $staff->roles()->create(['role_id' => $role->id]);
        $this->actingAs($staff);
        Volt::test(admin_view_path('settings.livewire.appearance'))->assertForbidden();
    }

    public function test_permission_is_checked_again_when_saving(): void
    {
        $admin = User::factory()->create(['id' => 1, 'status' => 'active', 'language' => 'en']);
        $this->actingAs($admin);
        $component = Volt::test(admin_view_path('settings.livewire.appearance'));
        $this->actingAs(User::factory()->create(['id' => 2, 'status' => 'active', 'language' => 'en']));
        $component->set('seasonal', 'halloween')->call('saveChanges')->assertForbidden();
        $this->assertDatabaseMissing('settings', ['key' => 'appearance_seasonal']);
    }

    public function test_saved_settings_are_shared_with_guests_without_exposing_admin_controls(): void
    {
        Setting::store(['appearance_mode' => 'dark', 'appearance_seasonal' => 'halloween', 'appearance_motion' => false]);
        $response = $this->get('/')->assertOk()->assertSee('name="wemx-appearance"', false)
            ->assertSee('data-brand-logo', false)->assertDontSee('data-personal-theme', false);
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $settings = json_decode($xpath->query('//meta[@name="wemx-appearance"]')->item(0)->getAttribute('content'), true);
        $this->assertSame('dark', $settings['mode']);
        $this->assertSame('halloween', $settings['seasonal']);
        $this->assertFalse($settings['motion']);
    }

    public function test_settings_page_lists_all_twelve_logo_editions_and_dashboard_link(): void
    {
        $this->actingAs(User::factory()->create(['id' => 1, 'status' => 'active', 'language' => 'en']))
            ->withSession(['admin_reauthenticated_at' => now()->toDateTimeString()]);
        $response = $this->get(route('admin.settings.index', ['page' => 'appearance']))->assertOk()
            ->assertSee('Theme &amp; Appearance', false)->assertSee('Save site appearance');
        foreach (config('appearance.themes') as $key => $theme) {
            $response->assertSee('data-logo-preview="'.$key.'"', false);
        }
        $this->get(route('admin.index'))->assertOk()->assertSee('Make it yours');
    }
}
