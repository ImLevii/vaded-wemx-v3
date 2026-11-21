<?php

use App\Models\Setting;
use App\Support\Appearance;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $mode = 'auto';
    public string $accent = 'vaded';
    public string $seasonal = 'auto';
    public bool $motion = true;
    public bool $logoMotion = true;
    public bool $effects = false;
    public array $effectMap = [];
    public string $effectIntensity = 'normal';
    public string $effectSpeed = 'normal';
    public string $effectStrength = 'normal';
    public string $effectAreas = 'client';

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasPermission('admin.settings.index'), 403);
        $this->mode = settings('appearance_mode', 'auto');
        $this->accent = settings('appearance_accent', 'vaded');
        $this->seasonal = settings('appearance_seasonal', 'auto');
        $this->motion = (bool) settings('appearance_motion', true);
        $this->logoMotion = (bool) settings('appearance_logo_motion', true);
        $this->effects = (bool) settings('appearance_effects', false);
        $this->effectMap = Appearance::effectMap();
        $this->effectIntensity = Appearance::option('appearance_effect_intensity', 'effect_intensities', 'normal');
        $this->effectSpeed = Appearance::option('appearance_effect_speed', 'effect_speeds', 'normal');
        $this->effectStrength = Appearance::option('appearance_effect_strength', 'effect_strengths', 'normal');
        $this->effectAreas = Appearance::option('appearance_effect_areas', 'effect_areas', 'client');
    }

    public function saveChanges(): void
    {
        abort_unless(auth()->user()?->hasPermission('admin.settings.index'), 403);
        $validated = $this->validate([
            'mode' => ['required', Rule::in(array_keys(config('appearance.modes')))],
            'accent' => ['required', Rule::in(array_keys(config('appearance.accents')))],
            'seasonal' => ['required', Rule::in(['auto', 'disabled', ...array_keys(config('appearance.themes'))])],
            'motion' => ['boolean'],
            'logoMotion' => ['boolean'],
            'effects' => ['boolean'],
            'effectMap' => ['required', 'array:'.implode(',', array_keys(config('appearance.themes')))],
            'effectMap.*' => ['required', 'string', Rule::in([Appearance::EFFECT_NONE, ...array_keys(config('appearance.effects'))])],
            'effectIntensity' => ['required', Rule::in(array_keys(config('appearance.effect_intensities')))],
            'effectSpeed' => ['required', Rule::in(array_keys(config('appearance.effect_speeds')))],
            'effectStrength' => ['required', Rule::in(array_keys(config('appearance.effect_strengths')))],
            'effectAreas' => ['required', Rule::in(array_keys(config('appearance.effect_areas')))],
        ]);
        $this->effectMap = Appearance::effectMap($validated['effectMap']);

        Setting::store([
            'appearance_mode' => $validated['mode'],
            'appearance_accent' => $validated['accent'],
            'appearance_seasonal' => $validated['seasonal'],
            'appearance_motion' => $validated['motion'],
            'appearance_logo_motion' => $validated['logoMotion'],
            'appearance_effects' => $validated['effects'],
            'appearance_effect_map' => $this->effectMap,
            'appearance_effect_intensity' => $validated['effectIntensity'],
            'appearance_effect_speed' => $validated['effectSpeed'],
            'appearance_effect_strength' => $validated['effectStrength'],
            'appearance_effect_areas' => $validated['effectAreas'],
        ]);
        $this->dispatch('appearance-saved', settings: Appearance::clientConfig());
        $this->dispatch('alert', 'success', 'Site appearance saved successfully.');
    }
}; ?>

<div class="vh-appearance-panel">
    <x-admin::settings.page-form title="Theme & Appearance" saveLabel="Save site appearance">
        <p class="text-secondary mb-4">Your brand, through every season. Saved settings apply across the client area and administration.</p>
        <section class="vh-personal-appearance mb-4" aria-labelledby="personal-appearance-heading">
            <div><h3 id="personal-appearance-heading">Your display</h3><p class="text-secondary mb-0">A personal override for this browser. Use site default to follow the shared settings.</p></div>
            <label class="form-label" for="personal-theme-mode">Display mode</label>
            <select id="personal-theme-mode" class="form-select" data-personal-theme>
                <option value="site">Site default</option><option value="auto">Day / night</option><option value="system">Device preference</option><option value="light">Light</option><option value="dark">Dark</option>
            </select>
            <label class="form-label" for="personal-effects">Page effects</label>
            <select id="personal-effects" class="form-select" data-personal-effects>
                <option value="site">Site default</option><option value="off">Off in this browser</option>
            </select>
        </section>
        <div class="row g-4 mb-4">
            <div class="col-12 col-sm-6"><label for="appearance-mode" class="form-label">Site display mode</label><x-admin::form.select id="appearance-mode" wire:model="mode" :options="config('appearance.modes')" /><p class="form-hint">Day / night uses each visitor’s local time: light from 7 am to 7 pm.</p>@error('mode')<x-admin::form.error :message="$message" />@enderror</div>
            <div class="col-12 col-sm-6"><label for="appearance-accent" class="form-label">Accent palette</label><x-admin::form.select id="appearance-accent" wire:model="accent" :options="config('appearance.accents')" />@error('accent')<x-admin::form.error :message="$message" />@enderror</div>
        </div>
        <label class="form-check form-switch mb-4"><input type="checkbox" class="form-check-input" wire:model="motion" data-motion-control data-effect-control @checked($motion)><span class="form-check-label">Ambient animations</span><span class="form-check-description">Background drift, section reveals and decorative transitions.</span></label>
        <section aria-labelledby="seasonal-logo-heading">
            <h3 id="seasonal-logo-heading">Seasonal logo</h3>
            <div class="vh-seasonal-preview" wire:ignore x-data="{ theme: $wire.entangle('seasonal'), logoMotion: $wire.entangle('logoMotion'), motion: $wire.entangle('motion') }" x-effect="window.WemxTheme.preview($el, theme, logoMotion && motion)">
                <div class="vh-seasonal-stage">
                    <span class="vh-seasonal-stage-label">Vaded seasonal collection</span>
                    <x-theme::brand-logo data-logo-preview="auto" />
                    <span class="vh-seasonal-motion-status" data-logo-motion-status>Live preview</span>
                </div>
                <div class="vh-seasonal-preview-copy">
                    <span class="vh-appearance-eyebrow">Edition preview</span>
                    <h4 data-logo-theme-name aria-live="polite">Following the calendar</h4>
                    <p data-logo-theme-description>Choose an edition to explore its artwork and motion.</p>
                    <span class="vh-seasonal-effect-name" data-effect-name aria-live="polite"></span>
                    <div class="vh-seasonal-preview-footer">
                        <span class="vh-seasonal-palette" aria-hidden="true"><i></i><i></i><i></i></span>
                        <span>Made for your brand</span>
                    </div>
                    <button type="button" class="btn btn-outline-secondary" data-logo-replay><x-admin::icon icon="refresh" /> Replay animation</button>
                </div>
            </div>
            <div class="row g-4 mt-1 mb-4">
                <div class="col-12 col-sm-6"><label for="seasonal-selection" class="form-label">Theme selection</label><select id="seasonal-selection" class="form-select" wire:model="seasonal"><option value="auto">Automatic (local calendar)</option><option value="disabled">Off — original logo</option>@foreach(['season' => 'Seasons', 'holiday' => 'Holidays'] as $kind => $group)<optgroup label="{{ $group }}">@foreach(config('appearance.themes') as $key => $theme)@if($theme['kind'] === $kind)<option value="{{ $key }}">{{ $theme['name'] }}</option>@endif @endforeach</optgroup>@endforeach</select>@error('seasonal')<x-admin::form.error :message="$message" />@enderror</div>
                <div class="col-12 col-sm-6"><label class="form-check form-switch"><input type="checkbox" class="form-check-input" wire:model="logoMotion"><span class="form-check-label">Animate the logo</span><span class="form-check-description">Turn off motion while keeping the seasonal artwork.</span></label></div>
            </div>
            <p class="form-hint mb-4">Holidays take priority over the four Northern Hemisphere seasons. Reduced motion keeps artwork still. Your uploaded logo stays at the center of every edition.</p>
            @foreach(['season' => 'The four seasons', 'holiday' => 'Holiday editions'] as $kind => $group)
                <fieldset class="vh-seasonal-group mb-4"><legend>{{ $group }}</legend><div class="vh-seasonal-grid">
                    @foreach(config('appearance.themes') as $key => $theme)
                        @if($theme['kind'] === $kind)
                            <button type="button" class="vh-seasonal-choice" data-logo-theme="{{ $key }}" x-on:click="$wire.seasonal = @js($key)" x-bind:aria-pressed="$wire.seasonal === @js($key)" aria-label="Use {{ $theme['name'] }} theme">
                                <span class="vh-seasonal-swatch"><x-theme::brand-logo :data-logo-preview="$key" data-logo-static="true" /></span>
                                <span><strong>{{ $theme['name'] }}</strong><small>{{ $theme['window'] }}</small></span>
                                <span class="vh-seasonal-choice-check" aria-hidden="true"><x-admin::icon icon="check" /></span>
                            </button>
                        @endif
                    @endforeach
                </div></fieldset>
            @endforeach
        </section>
        <section class="vh-effects-panel mb-4" aria-labelledby="seasonal-effects-heading">
            <div class="vh-effects-head">
                <div>
                    <h3 id="seasonal-effects-heading">Seasonal page effects</h3>
                    <p class="text-secondary mb-0">Snowfall for winter and Christmas, confetti for New Year’s, petals, leaves and more, drawn across the page in the active edition’s colours. The effect follows the theme selection above.</p>
                </div>
                <label class="form-check form-switch m-0"><input type="checkbox" class="form-check-input" wire:model="effects" data-effects-enabled data-effect-control @checked($effects)><span class="form-check-label">Show page effects</span></label>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6 col-xl-3"><label for="effect-intensity" class="form-label">Intensity</label><select id="effect-intensity" class="form-select" wire:model="effectIntensity" data-effect-intensity data-effect-control>@foreach(config('appearance.effect_intensities') as $value => $label)<option value="{{ $value }}" @selected($effectIntensity === $value)>{{ $label }}</option>@endforeach</select>@error('effectIntensity')<x-admin::form.error :message="$message" />@enderror</div>
                <div class="col-12 col-sm-6 col-xl-3"><label for="effect-speed" class="form-label">Speed</label><select id="effect-speed" class="form-select" wire:model="effectSpeed" data-effect-speed data-effect-control>@foreach(config('appearance.effect_speeds') as $value => $label)<option value="{{ $value }}" @selected($effectSpeed === $value)>{{ $label }}</option>@endforeach</select>@error('effectSpeed')<x-admin::form.error :message="$message" />@enderror</div>
                <div class="col-12 col-sm-6 col-xl-3"><label for="effect-strength" class="form-label">Strength</label><select id="effect-strength" class="form-select" wire:model="effectStrength" data-effect-strength data-effect-control>@foreach(config('appearance.effect_strengths') as $value => $label)<option value="{{ $value }}" @selected($effectStrength === $value)>{{ $label }}</option>@endforeach</select>@error('effectStrength')<x-admin::form.error :message="$message" />@enderror</div>
                <div class="col-12 col-sm-6 col-xl-3"><label for="effect-areas" class="form-label">Show on</label><select id="effect-areas" class="form-select" wire:model="effectAreas" data-effect-control>@foreach(config('appearance.effect_areas') as $value => $label)<option value="{{ $value }}" @selected($effectAreas === $value)>{{ $label }}</option>@endforeach</select>@error('effectAreas')<x-admin::form.error :message="$message" />@enderror</div>
            </div>
            <fieldset class="vh-effects-group">
                <legend>Effect for each edition</legend>
                <div class="vh-effects-grid">
                    @foreach(config('appearance.themes') as $key => $theme)
                        <label class="vh-effects-row" data-effect-row="{{ $key }}" data-logo-theme="{{ $key }}">
                            <span class="vh-effects-row-name"><strong>{{ $theme['name'] }}</strong><small>{{ $theme['window'] }}</small></span>
                            <span class="vh-effects-row-live">Showing now</span>
                            <select class="form-select form-select-sm" wire:model="effectMap.{{ $key }}" data-effect-edition="{{ $key }}" data-effect-control aria-label="Page effect for {{ $theme['name'] }}">
                                <option value="none" @selected(($effectMap[$key] ?? 'none') === 'none')>No effect</option>
                                @foreach(config('appearance.effects') as $effectKey => $effect)<option value="{{ $effectKey }}" @selected(($effectMap[$key] ?? null) === $effectKey)>{{ $effect['name'] }}</option>@endforeach
                            </select>
                        </label>
                    @endforeach
                </div>
                @error('effectMap')<x-admin::form.error :message="$message" />@enderror
                @error('effectMap.*')<x-admin::form.error :message="$message" />@enderror
            </fieldset>
            <p class="form-hint mt-3 mb-0">Effects need <strong>Ambient animations</strong> on, pause in background tabs, and never play for visitors who prefer reduced motion. Visitors can switch them off with the “Effects” button in the site footer.</p>
        </section>
        <div class="vh-appearance-save-state" wire:dirty role="status">You have unsaved appearance changes.</div>
    </x-admin::settings.page-form>
</div>
