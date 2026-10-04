<?php

use App\Models\Setting;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $mode = 'auto';
    public string $accent = 'vaded';
    public string $seasonal = 'auto';
    public bool $motion = true;
    public bool $logoMotion = true;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasPermission('admin.settings.index'), 403);
        $this->mode = settings('appearance_mode', 'auto');
        $this->accent = settings('appearance_accent', 'vaded');
        $this->seasonal = settings('appearance_seasonal', 'auto');
        $this->motion = (bool) settings('appearance_motion', true);
        $this->logoMotion = (bool) settings('appearance_logo_motion', true);
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
        ]);

        Setting::store([
            'appearance_mode' => $validated['mode'],
            'appearance_accent' => $validated['accent'],
            'appearance_seasonal' => $validated['seasonal'],
            'appearance_motion' => $validated['motion'],
            'appearance_logo_motion' => $validated['logoMotion'],
        ]);
        $this->dispatch('appearance-saved', settings: $validated);
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
        </section>
        <div class="row g-4 mb-4">
            <div class="col-12 col-sm-6"><label for="appearance-mode" class="form-label">Site display mode</label><x-admin::form.select id="appearance-mode" wire:model="mode" :options="config('appearance.modes')" /><p class="form-hint">Day / night uses each visitor’s local time: light from 7 am to 7 pm.</p>@error('mode')<x-admin::form.error :message="$message" />@enderror</div>
            <div class="col-12 col-sm-6"><label for="appearance-accent" class="form-label">Accent palette</label><x-admin::form.select id="appearance-accent" wire:model="accent" :options="config('appearance.accents')" />@error('accent')<x-admin::form.error :message="$message" />@enderror</div>
        </div>
        <label class="form-check form-switch mb-4"><input type="checkbox" class="form-check-input" wire:model="motion"><span class="form-check-label">Ambient animations</span><span class="form-check-description">Background drift, section reveals and decorative transitions.</span></label>
        <section aria-labelledby="seasonal-logo-heading">
            <h3 id="seasonal-logo-heading">Seasonal logo</h3>
            <div class="vh-seasonal-preview" wire:ignore x-data="{ theme: $wire.entangle('seasonal'), logoMotion: $wire.entangle('logoMotion'), motion: $wire.entangle('motion') }" x-effect="window.WemxTheme.preview($el, theme, logoMotion && motion)">
                <x-theme::brand-logo data-logo-preview="auto" />
                <div><span class="vh-appearance-eyebrow">Live preview</span><h4 data-logo-theme-name aria-live="polite">Following the calendar</h4><p class="text-secondary mb-0">Preview your selection before saving.</p></div>
                <button type="button" class="btn btn-icon btn-outline-secondary" data-logo-replay aria-label="Replay logo animation"><x-admin::icon icon="refresh" /></button>
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
                            <button type="button" class="vh-seasonal-choice" x-on:click="$wire.seasonal = @js($key)" x-bind:aria-pressed="$wire.seasonal === @js($key)" aria-label="Use {{ $theme['name'] }} theme">
                                <x-theme::brand-logo :data-logo-preview="$key" data-logo-static="true" />
                                <span><strong>{{ $theme['name'] }}</strong><small>{{ $theme['window'] }}</small></span>
                            </button>
                        @endif
                    @endforeach
                </div></fieldset>
            @endforeach
        </section>
        <div class="vh-appearance-save-state" wire:dirty role="status">You have unsaved appearance changes.</div>
    </x-admin::settings.page-form>
</div>
