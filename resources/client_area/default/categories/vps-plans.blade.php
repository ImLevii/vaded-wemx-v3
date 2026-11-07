@php
    $vpsSystems = collect(config('hosting.vps_operating_systems', []))->map(fn (array $system): array => [
        'name' => $system['name'],
        'short' => $system['short'] ?? $system['name'],
        'mark' => $system['mark'],
        'logo' => $system['logo'] ?? null,
        'versions' => $system['versions'],
        'badge' => $system['badge'] ?? null,
        'note' => $system['note'] ?? null,
    ]);
    [$defaultOsFamily, $defaultOsVersion] = config('hosting.vps_default_os', ['ubuntu', '22.04']);
    if (! $vpsSystems->has($defaultOsFamily)) {
        $defaultOsFamily = $vpsSystems->keys()->first();
        $defaultOsVersion = collect($vpsSystems->get($defaultOsFamily)['versions'] ?? [])->last();
    }
    $defaultOsLabel = $defaultOsFamily ? (count($vpsSystems[$defaultOsFamily]['versions']) > 1 ? $vpsSystems[$defaultOsFamily]['name'].' '.$defaultOsVersion : $vpsSystems[$defaultOsFamily]['name']) : null;
@endphp
<section @class(['vh-vps-layout', 'vh-vps-layout-single' => $vpsPlans->count() === 1]) aria-label="{{ $category->name }} plans"
    x-data="{
        period: @js($defaultVpsPeriod),
        systems: @js($vpsSystems),
        osFamily: @js($defaultOsFamily),
        osVersion: @js($defaultOsVersion),
        pickFamily(family) { this.osFamily = family; const versions = this.systems[family].versions; this.osVersion = versions[versions.length - 1]; },
        get osLabel() { const system = this.systems[this.osFamily]; return system ? (system.versions.length > 1 ? system.name + ' ' + this.osVersion : system.name) : ''; },
        configureHref(base, os) {
            const value = os && os.values[this.osFamily + '|' + this.osVersion];
            return value ? base + (base.includes('?') ? '&' : '?') + encodeURIComponent('config_options[' + os.key + ']') + '=' + encodeURIComponent(value) : base;
        },
    }">
    <aside class="vh-vps-options" aria-labelledby="vps-options-title">
        <header class="vh-vps-options-head">
            <span class="vh-vps-icon-chip"><x-theme::icon name="sliders" /></span>
            <div><h2 id="vps-options-title">Configurable Options</h2><p>Choose your operating system and billing cycle.</p></div>
        </header>

        @if($vpsSystems->isNotEmpty())
            <fieldset class="vh-vps-cfg-group">
                <legend class="vh-vps-control-label">Operating system</legend>
                <div class="vh-vps-cfg-os">
                    @foreach($vpsSystems as $key => $system)
                        <label class="vh-vps-cfg-os-choice" data-os="{{ $key }}">
                            <input type="radio" name="vps-os-family" value="{{ $key }}" x-model="osFamily" @change="pickFamily('{{ $key }}')" @checked($key === $defaultOsFamily)>
                            <span class="vh-vps-cfg-os-tile">
                                <span class="vh-vps-distro-mark" aria-hidden="true">@if($system['logo'] ?? null)<img src="{{ asset($system['logo']) }}" alt="" width="26" height="26" loading="lazy" decoding="async">@else{{ $system['mark'] }}@endif</span>
                                <span class="vh-vps-cfg-os-name">{{ $system['short'] }}</span>
                                @if($system['badge'])<span class="vh-vps-distro-badge" title="Bring Your Own License">{{ $system['badge'] }}</span>@endif
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <fieldset class="vh-vps-cfg-group">
                <legend class="vh-vps-control-label">Version</legend>
                @foreach($vpsSystems as $key => $system)
                    <div class="vh-vps-cfg-versions" x-show="osFamily === '{{ $key }}'" @if($key !== $defaultOsFamily) x-cloak @endif>
                        @foreach($system['versions'] as $version)
                            <label><input type="radio" name="vps-os-version-{{ $key }}" value="{{ $version }}" x-model="osVersion" @checked($key === $defaultOsFamily && $version === $defaultOsVersion)><span>{{ $version }}</span></label>
                        @endforeach
                    </div>
                @endforeach
                <p class="vh-vps-cfg-hint" x-show="osFamily === 'windows'" x-cloak><span class="vh-vps-distro-badge">BYOL</span> Bring Your Own License: Windows Server is installed without a license key.</p>
                @foreach($vpsSystems->filter(fn ($system) => $system['note']) as $key => $system)
                    <p class="vh-vps-cfg-hint" x-show="osFamily === '{{ $key }}'" x-cloak><x-theme::icon name="console" /> {{ $system['note'] }}</p>
                @endforeach
                <p class="vh-vps-cfg-hint" x-show="osFamily !== 'windows' && ! systems[osFamily]?.note"><x-theme::icon name="check" /> Installs with one click from your control panel.</p>
            </fieldset>
        @endif

        @if($vpsBillingCycles->isNotEmpty())
            <fieldset class="vh-vps-cfg-group">
                <legend class="vh-vps-control-label">Billing cycle</legend>
                <div @class(['vh-vps-cycles', 'vh-vps-cycles-single' => $vpsBillingCycles->count() === 1])>
                    @foreach($vpsBillingCycles as $billingCycle)
                        <label><input type="radio" name="vps-billing-cycle" value="{{ $billingCycle->period_in_days }}" x-model="period" @checked((string) $billingCycle->period_in_days === $defaultVpsPeriod)><span>{{ $billingCycle->cycle() }}</span></label>
                    @endforeach
                    @if($vpsBillingCycles->count() > 1)<label><input type="radio" name="vps-billing-cycle" value="all" x-model="period"><span>All cycles</span></label>@endif
                </div>
                <p class="vh-vps-setting-note">Prices are per billing cycle. Review your total before checkout.</p>
            </fieldset>
        @endif
    </aside>

    <div class="vh-vps-main">
        <div class="vh-vps-grid" @if($vpsPlans->count() === 1) data-single-plan @endif>
            @forelse($vpsPlans as $plan)
                @php($package = $plan['package'])
                @php($prices = $plan['prices'])
                <article class="vh-vps-plan-card" wire:key="vps-package-{{ $package->id }}">
                    <header class="vh-vps-plan-heading">
                        <h3>{{ $package->name }}</h3>
                        <span class="vh-vps-icon-chip"><x-theme::icon name="server" /></span>
                    </header>
                    @if($package->short_description)<p class="vh-vps-plan-description">{{ $package->short_description }}</p>@endif
                    @forelse($prices as $planPrice)
                        <div class="vh-vps-plan-price" x-show="period === '{{ $planPrice->period_in_days }}' || (period === 'all' && {{ $loop->first ? 'true' : 'false' }})" @if((string) $planPrice->period_in_days !== $defaultVpsPeriod) x-cloak @endif>
                            <div class="vh-vps-price-line"><strong>{{ price($planPrice->price) }}</strong><span>/ {{ $planPrice->cycle() }}</span></div>
                            <small><x-theme::icon :name="$planPrice->setup_fee > 0 ? 'receipt' : 'check'" /> {{ $planPrice->setup_fee > 0 ? price($planPrice->setup_fee).' one-time setup' : 'No setup fee' }}</small>
                        </div>
                    @empty
                        <div class="vh-vps-plan-price"><strong class="vh-vps-unavailable">Currently unavailable</strong></div>
                    @endforelse
                    @if($prices->isNotEmpty())
                        <div class="vh-vps-cycle-unavailable" x-show="period !== 'all' && ! @js($prices->pluck('period_in_days')->map(fn ($days) => (string) $days)->values()).includes(period)" x-cloak>This plan is not offered on the selected billing cycle.</div>
                    @endif
                    <dl class="vh-vps-resources">
                        @foreach(['cpu' => ['cpu', 'CPU cores'], 'memory' => ['memory', 'Memory'], 'storage' => ['storage', 'Storage'], 'network' => ['network', 'Bandwidth']] as $resource => [$icon, $label])
                            @isset($plan['resources'][$resource])
                                <div class="vh-vps-spec"><dt><x-theme::icon :name="$icon" />{{ $label }}</dt><dd>{{ implode(' · ', $plan['resources'][$resource]) }}</dd></div>
                            @endisset
                        @endforeach
                        @if($defaultOsLabel)
                            <div class="vh-vps-spec vh-vps-spec-wide"><dt><x-theme::icon name="console" />Operating system</dt><dd x-text="osLabel">{{ $defaultOsLabel }}</dd></div>
                        @elseif(isset($plan['resources']['os']))
                            <div class="vh-vps-spec vh-vps-spec-wide"><dt><x-theme::icon name="console" />Operating system</dt><dd>{{ implode(' · ', $plan['resources']['os']) }}</dd></div>
                        @endif
                    </dl>
                    @if($plan['features'])
                        <ul class="vh-vps-features">
                            @foreach($plan['features'] as $feature)
                                <li><x-theme::icon name="check" /><span>{{ $feature }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="vh-vps-plan-footer">
                        <span class="vh-vps-config-note"><x-theme::icon name="sliders" /> Your configuration. Your server.</span>
                        @foreach($prices as $planPrice)
                            @php($configureUrl = route('packages.view', ['package' => $package->slug, 'packagePriceId' => $planPrice->id]))
                            <a class="vh-action vh-vps-deploy" href="{{ $configureUrl }}" :href="configureHref(@js($configureUrl), @js($plan['os']))" wire:navigate x-show="period === '{{ $planPrice->period_in_days }}' || (period === 'all' && {{ $loop->first ? 'true' : 'false' }})" @if((string) $planPrice->period_in_days !== $defaultVpsPeriod) x-cloak @endif aria-label="Configure {{ $package->name }}, {{ $planPrice->cycle() }}">Configure VPS <x-theme::icon name="arrow" /></a>
                        @endforeach
                    </div>
                </article>
            @empty
                <div class="vh-vps-empty"><x-theme::empty-state title="More VPS plans are on the way." description="No plans are currently available. Explore our other services or check back soon." action-text="Explore hosting" :action-href="route('categories.index').'#services'" /></div>
            @endforelse
        </div>
        <p class="vh-vps-catalog-note">Included resources are listed per plan. Configuration, billing, and any extras are reviewed before you order.</p>
    </div>
</section>
