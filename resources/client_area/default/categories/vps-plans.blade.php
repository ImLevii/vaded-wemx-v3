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

    $vpsPlans = $vpsPlans->values();
    $planCount = $vpsPlans->count();
    // A cycle must be picked for a price to show; "all cycles" falls back to the first one.
    $defaultVpsPeriod = $defaultVpsPeriod === 'all' ? (string) ($vpsBillingCycles->first()?->period_in_days ?? '30') : $defaultVpsPeriod;
    $specCards = [
        'cpu' => ['icon' => 'cpu', 'title' => 'VPS Cores', 'bullet' => 'Cores'],
        'memory' => ['icon' => 'memory', 'title' => 'Server Memory', 'bullet' => 'Memory'],
        'storage' => ['icon' => 'storage', 'title' => 'VPS Storage', 'bullet' => 'Disk'],
        'network' => ['icon' => 'network', 'title' => 'Server Bandwidth', 'bullet' => 'Bandwidth'],
    ];
    $sliderMax = max($planCount - 1, 1);
@endphp
<section class="vh-vps-builder" aria-labelledby="vps-builder-title"
    x-data="{
        plan: 0,
        planNames: @js($vpsPlans->map(fn (array $plan): string => $plan['package']->name)),
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
    <header class="vh-vps-builder-head">
        <h2 id="vps-builder-title">Configure Your VPS Server</h2>
        <p>Choose a plan, operating system and billing cycle. Your configuration updates as you go.</p>
    </header>

    @if($planCount === 0)
        <div class="vh-vps-empty"><x-theme::empty-state title="More VPS plans are on the way." description="No plans are currently available. Explore our other services or check back soon." action-text="Explore hosting" :action-href="route('categories.index').'#services'" /></div>
    @else
        <div class="vh-vps-panel vh-vps-plan-picker">
            @foreach($vpsPlans as $index => $plan)
                <h3 class="vh-vps-plan-name" x-show="plan === {{ $index }}" @if($index > 0) x-cloak @endif>{{ $plan['package']->name }}</h3>
            @endforeach
            <div class="vh-vps-slider" style="--vps-plan-pos: 0" :style="`--vps-plan-pos: ${plan / {{ $sliderMax }}}`" @if($planCount === 1) data-single @endif>
                <span class="vh-vps-slider-bubble" aria-hidden="true" x-text="planNames[plan]">{{ $vpsPlans[0]['package']->name }}</span>
                <input type="range" min="0" max="{{ $planCount - 1 }}" step="1" value="0" x-model.number="plan" aria-label="VPS plan" :aria-valuetext="planNames[plan]" aria-valuetext="{{ $vpsPlans[0]['package']->name }}" @if($planCount === 1) tabindex="-1" aria-disabled="true" @endif>
                <ol class="vh-vps-slider-ticks">
                    @foreach($vpsPlans as $index => $plan)
                        <li style="--vps-tick-pos: {{ $planCount === 1 ? 0 : $index / $sliderMax }}"><button type="button" @click="plan = {{ $index }}" :aria-pressed="(plan === {{ $index }}).toString()" aria-pressed="{{ $index === 0 ? 'true' : 'false' }}" aria-label="{{ $plan['package']->name }}" tabindex="-1">{{ $plan['tick'] }}</button></li>
                    @endforeach
                </ol>
            </div>
            @if($planCount === 1)<p class="vh-vps-slider-note">More plans are on the way.</p>@endif
        </div>

        <div class="vh-vps-builder-body">
            <div class="vh-vps-builder-main">
                @foreach($vpsPlans as $index => $plan)
                    <div class="vh-vps-spec-cards" x-show="plan === {{ $index }}" @if($index > 0) x-cloak @endif wire:key="vps-specs-{{ $plan['package']->id }}">
                        @foreach($specCards as $resource => $card)
                            @isset($plan['specs'][$resource])
                                <article class="vh-vps-panel vh-vps-spec-card">
                                    <h4>{{ $card['title'] }}</h4>
                                    <strong>{{ $plan['specs'][$resource]['value'] }}</strong>
                                    <p><span>{{ $plan['specs'][$resource]['detail'] }}</span><x-theme::icon :name="$card['icon']" /></p>
                                </article>
                            @endisset
                        @endforeach
                        @if($plan['features'])
                            <ul class="vh-vps-features">
                                @foreach($plan['features'] as $feature)
                                    <li><x-theme::icon name="check" /><span>{{ $feature }}</span></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach

                <div class="vh-vps-panel vh-vps-options" aria-labelledby="vps-options-title">
                    <header class="vh-vps-options-head">
                        <span class="vh-vps-icon-chip"><x-theme::icon name="sliders" /></span>
                        <div><h3 id="vps-options-title">Configurable Options</h3><p>Choose your operating system and billing cycle.</p></div>
                    </header>

                    @if($vpsSystems->isNotEmpty())
                        <fieldset class="vh-vps-cfg-group">
                            <legend class="vh-vps-control-label">Operating system</legend>
                            <div class="vh-vps-cfg-os">
                                @foreach($vpsSystems as $key => $system)
                                    <label class="vh-vps-cfg-os-choice" data-os="{{ $key }}">
                                        <input type="radio" name="vps-os-family" value="{{ $key }}" x-model="osFamily" @change="pickFamily('{{ $key }}')" @checked($key === $defaultOsFamily)>
                                        <span class="vh-vps-cfg-os-tile">
                                            <span class="vh-vps-distro-mark" aria-hidden="true">@if($system['logo'] ?? null)<img src="{{ asset($system['logo']) }}" alt="" width="26" height="26">@else{{ $system['mark'] }}@endif</span>
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
                            <p class="vh-vps-cfg-hint" x-show="osFamily === 'windows'" x-cloak><span class="vh-vps-distro-badge">BYOL</span> Bring Your Own License: Windows is installed without a license key.</p>
                            @foreach($vpsSystems->filter(fn ($system) => $system['note']) as $key => $system)
                                <p class="vh-vps-cfg-hint" x-show="osFamily === '{{ $key }}'" x-cloak><x-theme::icon name="console" /> {{ $system['note'] }}</p>
                            @endforeach
                            <p class="vh-vps-cfg-hint" x-show="osFamily !== 'windows' && ! systems[osFamily]?.note"><x-theme::icon name="check" /> Installed automatically when your server is created.</p>
                        </fieldset>
                    @endif

                    @if($vpsBillingCycles->isNotEmpty())
                        <fieldset class="vh-vps-cfg-group">
                            <legend class="vh-vps-control-label">Billing cycle</legend>
                            <div @class(['vh-vps-cycles', 'vh-vps-cycles-single' => $vpsBillingCycles->count() === 1])>
                                @foreach($vpsBillingCycles as $billingCycle)
                                    <label><input type="radio" name="vps-billing-cycle" value="{{ $billingCycle->period_in_days }}" x-model="period" @checked((string) $billingCycle->period_in_days === $defaultVpsPeriod)><span>{{ $billingCycle->cycle() }}</span></label>
                                @endforeach
                            </div>
                            <p class="vh-vps-setting-note">Prices are per billing cycle. Review your total before checkout.</p>
                        </fieldset>
                    @endif
                </div>
            </div>

            <aside class="vh-vps-panel vh-vps-current" aria-labelledby="vps-current-title" aria-live="polite">
                <h3 id="vps-current-title">Current Configuration</h3>
                <p class="vh-vps-current-intro">The configuration selected for your VPS server. Change it with the plan slider and options on this page.</p>
                @foreach($vpsPlans as $index => $plan)
                    @php($package = $plan['package'])
                    @php($prices = $plan['prices'])
                    <div class="vh-vps-current-plan" x-show="plan === {{ $index }}" @if($index > 0) x-cloak @endif wire:key="vps-current-{{ $package->id }}">
                        <ul class="vh-vps-current-list">
                            <li><span>Plan:</span> {{ $package->name }}</li>
                            @foreach($specCards as $resource => $card)
                                @isset($plan['specs'][$resource])<li><span>{{ $card['bullet'] }}:</span> {{ $plan['specs'][$resource]['value'] }}</li>@endisset
                            @endforeach
                            @if($defaultOsLabel)
                                <li><span>Operating system:</span> <span x-text="osLabel">{{ $defaultOsLabel }}</span></li>
                            @elseif(isset($plan['resources']['os']))
                                <li><span>Operating system:</span> {{ implode(' · ', $plan['resources']['os']) }}</li>
                            @endif
                        </ul>
                        <div class="vh-vps-current-total">
                            @forelse($prices as $planPrice)
                                <div class="vh-vps-plan-price" x-show="period === '{{ $planPrice->period_in_days }}'" @if((string) $planPrice->period_in_days !== $defaultVpsPeriod) x-cloak @endif>
                                    <div class="vh-vps-price-line"><strong>{{ price($planPrice->price) }}</strong><span>/ {{ $planPrice->cycle() }}</span></div>
                                    <small><x-theme::icon :name="$planPrice->setup_fee > 0 ? 'receipt' : 'check'" /> {{ $planPrice->setup_fee > 0 ? price($planPrice->setup_fee).' one-time setup' : 'No setup fee' }}</small>
                                </div>
                            @empty
                                <div class="vh-vps-plan-price"><strong class="vh-vps-unavailable">Currently unavailable</strong></div>
                            @endforelse
                            @if($prices->isNotEmpty())
                                <p class="vh-vps-cycle-unavailable" x-show="! @js($prices->pluck('period_in_days')->map(fn ($days) => (string) $days)->values()).includes(period)" x-cloak>This plan is not offered on the selected billing cycle.</p>
                            @endif
                        </div>
                        @foreach($prices as $planPrice)
                            @php($configureUrl = route('packages.view', ['package' => $package->slug, 'packagePriceId' => $planPrice->id]))
                            <a class="vh-action vh-vps-deploy" href="{{ $configureUrl }}" :href="configureHref(@js($configureUrl), @js($plan['os']))" wire:navigate x-show="period === '{{ $planPrice->period_in_days }}'" @if((string) $planPrice->period_in_days !== $defaultVpsPeriod) x-cloak @endif aria-label="Order {{ $package->name }}, {{ $planPrice->cycle() }}">Order Now <x-theme::icon name="arrow" /></a>
                        @endforeach
                    </div>
                @endforeach
                <p class="vh-vps-catalog-note">Configuration, billing and any extras are reviewed before you order.</p>
            </aside>
        </div>
    @endif
</section>
