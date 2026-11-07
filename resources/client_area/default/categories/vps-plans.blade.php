<section @class(['vh-vps-layout', 'vh-vps-layout-single' => $vpsPlans->count() === 1]) x-data="{ period: @js($defaultVpsPeriod) }" aria-label="{{ $category->name }} plans">
    <aside class="vh-vps-settings" aria-label="VPS plan options">
        <div class="vh-vps-setting">
            <h2 class="vh-vps-control-label">Compute platform</h2>
            <div class="vh-vps-platform"><span class="vh-vps-icon-chip"><x-theme::icon name="cpu" /></span><div class="vh-vps-option-copy"><strong>Cloud VPS</strong><span>Virtual private server</span></div><span class="vh-vps-selected" title="Selected"><x-theme::icon name="check" /></span></div>
            <p class="vh-vps-setting-note">Resources for your own corner of the cloud.</p>
        </div>
        <div class="vh-vps-setting">
            <h2 class="vh-vps-control-label">Deployment</h2>
            <div class="vh-vps-region"><span class="vh-vps-icon-chip"><x-theme::icon name="pin" /></span><div class="vh-vps-option-copy"><strong>Make it your own</strong><span>Review options with your plan</span></div></div>
            <p class="vh-vps-setting-note">Install Rocky, Alma, Debian, Ubuntu, Fedora, or Windows Server with one click. <a href="#operating-systems" class="vh-vps-inline-link">See all operating systems</a></p>
        </div>
        @if($vpsBillingCycles->isNotEmpty())
            <fieldset class="vh-vps-setting vh-vps-billing"><legend class="vh-vps-control-label">Billing cycle</legend>
                <div @class(['vh-vps-cycles', 'vh-vps-cycles-single' => $vpsBillingCycles->count() === 1])>
                    @foreach($vpsBillingCycles as $billingCycle)
                        <label><input type="radio" name="vps-billing-cycle" value="{{ $billingCycle->period_in_days }}" x-model="period" @checked((string) $billingCycle->period_in_days === $defaultVpsPeriod)><span>{{ $billingCycle->cycle() }}</span></label>
                    @endforeach
                    @if($vpsBillingCycles->count() > 1)<label><input type="radio" name="vps-billing-cycle" value="all" x-model="period"><span>All cycles</span></label>@endif
                </div>
                <p class="vh-vps-setting-note">Prices are per billing cycle. Review your total before checkout.</p>
            </fieldset>
        @endif
        <div class="vh-vps-sidebar-foot">
            <a href="#vps-details" class="vh-vps-link"><x-theme::icon name="sliders" /> Explore the possibilities</a>
            <a href="{{ route('categories.index') }}#services" wire:navigate class="vh-vps-link vh-vps-link-muted"><x-theme::icon name="arrow" /> Explore all hosting</a>
        </div>
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
                    @if($plan['resources'])
                        <dl class="vh-vps-resources">
                            @foreach(['cpu' => ['cpu', 'CPU cores'], 'memory' => ['memory', 'Memory'], 'storage' => ['storage', 'Storage'], 'network' => ['network', 'Bandwidth'], 'os' => ['console', 'Operating system']] as $resource => [$icon, $label])
                                @isset($plan['resources'][$resource])
                                    <div @class(['vh-vps-spec', 'vh-vps-spec-wide' => $resource === 'os'])><dt><x-theme::icon :name="$icon" />{{ $label }}</dt><dd>{{ implode(' · ', $plan['resources'][$resource]) }}</dd></div>
                                @endisset
                            @endforeach
                        </dl>
                    @endif
                    @if($plan['features'] || ! $plan['resources'])
                        <ul class="vh-vps-features">
                            @forelse($plan['features'] as $feature)
                                <li><x-theme::icon name="check" /><span>{{ $feature }}</span></li>
                            @empty
                                <li><x-theme::icon name="sliders" /><span>View all details and available configuration.</span></li>
                            @endforelse
                        </ul>
                    @endif
                    <div class="vh-vps-plan-footer">
                        <span class="vh-vps-config-note"><x-theme::icon name="sliders" /> Your configuration. Your server.</span>
                        @foreach($prices as $planPrice)
                            <a class="vh-action vh-vps-deploy" href="{{ route('packages.view', ['package' => $package->slug, 'packagePriceId' => $planPrice->id]) }}" wire:navigate x-show="period === '{{ $planPrice->period_in_days }}' || (period === 'all' && {{ $loop->first ? 'true' : 'false' }})" @if((string) $planPrice->period_in_days !== $defaultVpsPeriod) x-cloak @endif aria-label="Configure {{ $package->name }}, {{ $planPrice->cycle() }}">Configure VPS <x-theme::icon name="arrow" /></a>
                        @endforeach
                    </div>
                </article>
            @empty
                <div class="vh-vps-empty"><x-theme::empty-state title="More VPS plans are on the way." description="No plans are currently available. Explore our other services or check back soon." action-text="Explore hosting" :action-href="route('categories.index').'#services'" /></div>
            @endforelse
            @if($vpsPlans->count() === 1)
                <div class="vh-vps-build-panel" aria-label="A home for your next project">
                    <div><span class="vh-vps-label">Your next chapter</span><h3>A little space.<br><em>A lot of potential.</em></h3><p>Your app. Your development environment. Your side project that could become something bigger.</p></div>
                    <div class="vh-vps-server-art" aria-hidden="true">
                        <div><x-theme::icon name="server" /><span></span><span></span></div>
                        <div><x-theme::icon name="server" /><span></span><span></span></div>
                        <div><x-theme::icon name="server" /><span></span><span></span></div>
                        <span class="vh-vps-art-label">VADED / Cloud infrastructure</span>
                    </div>
                    <span class="vh-vps-build-note"><x-theme::icon name="cloud" /> Built around what you want to run.</span>
                </div>
            @endif
        </div>
        <p class="vh-vps-catalog-note">Included resources are listed per plan. Configuration, billing, and any extras are reviewed before you order.</p>
    </div>
</section>
