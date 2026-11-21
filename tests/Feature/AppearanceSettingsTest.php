<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\Appearance;
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
        foreach (['mode', 'accent', 'seasonal', 'motion', 'logo_motion', 'effects', 'effect_map', 'effect_intensity', 'effect_speed', 'effect_strength', 'effect_areas'] as $key) {
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
            $response->assertSee('data-logo-preview="'.$key.'"', false)->assertSee('data-effect-edition="'.$key.'"', false);
        }
        $response->assertSee('Seasonal page effects')->assertSee('Show page effects')->assertSee('data-personal-effects', false);
        $this->get(route('admin.index'))->assertOk()->assertSee('VADED HOSTING')
            ->assertSee('Manage appearance, site colors, motion and seasonal logo editions.')
            ->assertDontSee('Make it yours');
    }

    public function test_page_effects_are_off_by_default_and_every_edition_has_a_default_effect(): void
    {
        $config = Appearance::clientConfig();
        $this->assertFalse($config['effects']);
        $this->assertSame(array_keys(config('appearance.themes')), array_keys($config['effectMap']));
        $this->assertSame('snow', $config['effectMap']['winter']);
        $this->assertSame('festive', $config['effectMap']['christmas']);
        $this->assertSame('confetti', $config['effectMap']['newYear']);
        foreach ($config['effectMap'] as $effect) {
            $this->assertArrayHasKey($effect, config('appearance.effects'));
        }
        $this->assertSame(['normal', 'normal', 'normal', 'client'], [$config['effectIntensity'], $config['effectSpeed'], $config['effectStrength'], $config['effectAreas']]);
        $this->assertStringContainsString('/assets/common/js/vaded-effects.js', $config['effectsScript']);
    }

    public function test_admin_can_save_and_reload_page_effects(): void
    {
        $this->actingAs(User::factory()->create(['id' => 1, 'status' => 'active', 'language' => 'en']));
        Volt::test(admin_view_path('settings.livewire.appearance'))
            ->assertSet('effects', false)->assertSet('effectMap.christmas', 'festive')
            ->set('effects', true)->set('effectMap.christmas', 'confetti')->set('effectMap.winter', 'none')
            ->set('effectIntensity', 'heavy')->set('effectSpeed', 'calm')->set('effectStrength', 'subtle')->set('effectAreas', 'both')
            ->call('saveChanges')->assertHasNoErrors()
            ->assertDispatched('appearance-saved', fn ($name, $params) => $params['settings']['effects'] === true
                && $params['settings']['effectMap']['christmas'] === 'confetti' && $params['settings']['effectStrength'] === 'subtle');
        Volt::test(admin_view_path('settings.livewire.appearance'))
            ->assertSet('effects', true)->assertSet('effectMap.christmas', 'confetti')->assertSet('effectMap.winter', 'none')
            ->assertSet('effectMap.autumn', 'leaves')->assertSet('effectIntensity', 'heavy')->assertSet('effectSpeed', 'calm')
            ->assertSet('effectStrength', 'subtle')->assertSet('effectAreas', 'both');
        $this->assertSame('confetti', json_decode(Setting::get('appearance_effect_map'), true)['christmas']);
    }

    public function test_invalid_page_effect_options_are_rejected_without_saving(): void
    {
        $this->actingAs(User::factory()->create(['id' => 1, 'status' => 'active', 'language' => 'en']));
        Volt::test(admin_view_path('settings.livewire.appearance'))
            ->set('effects', true)->set('effectMap.christmas', '<script>')->set('effectMap.lasers', 'snow')
            ->set('effectIntensity', 'max')->set('effectSpeed', 'warp')->set('effectStrength', 'blinding')->set('effectAreas', 'nowhere')
            ->call('saveChanges')
            ->assertHasErrors(['effectMap', 'effectMap.christmas', 'effectIntensity', 'effectSpeed', 'effectStrength', 'effectAreas']);
        $this->assertDatabaseMissing('settings', ['key' => 'appearance_effects']);
        $this->assertDatabaseMissing('settings', ['key' => 'appearance_effect_map']);
    }

    public function test_stale_or_tampered_effect_settings_fall_back_to_defaults(): void
    {
        Setting::store([
            'appearance_effect_map' => json_encode(['christmas' => 'lasers', 'winter' => 'none', 'unknown' => 'snow']),
            'appearance_effect_intensity' => 'extreme',
            'appearance_effect_areas' => 'both',
        ]);
        $config = Appearance::clientConfig();
        $this->assertSame('festive', $config['effectMap']['christmas']);
        $this->assertSame('none', $config['effectMap']['winter']);
        $this->assertArrayNotHasKey('unknown', $config['effectMap']);
        $this->assertSame('normal', $config['effectIntensity']);
        $this->assertSame('both', $config['effectAreas']);
    }

    public function test_guests_receive_the_effect_settings_and_a_hidden_footer_toggle(): void
    {
        Setting::store(['appearance_effects' => true, 'appearance_seasonal' => 'christmas', 'appearance_effect_strength' => 'subtle']);
        $response = $this->get('/')->assertOk()->assertSee('data-effects-toggle', false);
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $settings = json_decode($xpath->query('//meta[@name="wemx-appearance"]')->item(0)->getAttribute('content'), true);
        $this->assertTrue($settings['effects']);
        $this->assertSame('festive', $settings['effectMap']['christmas']);
        $this->assertSame('subtle', $settings['effectStrength']);
        $this->assertTrue($xpath->query('//button[@data-effects-toggle]')->item(0)->hasAttribute('hidden'));
    }
}
