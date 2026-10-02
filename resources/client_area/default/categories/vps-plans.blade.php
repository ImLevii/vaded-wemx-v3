<section class="vh-vps-layout grid grid-cols-1 items-start gap-6 lg:grid-cols-[280px_minmax(0,1fr)]" x-data="{ period: @js($defaultVpsPeriod) }" aria-label="{{ $category->name }} plans">
    <aside class="vh-vps-settings flex flex-col gap-7" aria-label="VPS plan options">
        <div>
            <h2 class="vh-vps-control-label">COMPUTE PLATFORM</h2>
            <div class="vh-vps-platform flex items-center gap-3"><span class="vh-vps-chip"><x-theme::icon name="cpu" /></span><div class="flex-1"><strong>Cloud VPS</strong><span class="vh-vps-platform-tag">VIRTUAL PRIVATE SERVER</span></div><span class="vh-vps-selected"><x-theme::icon name="check" /></span></div>
            <p class="vh-vps-setting-note">Resources for your own corner of the cloud.</p>
        </div>
        <div>
            <h2 class="vh-vps-control-label">DEPLOYMENT</h2>
            <div class="vh-vps-region flex items-center gap-3"><x-theme::icon name="pin" /><div><strong>Make it your own</strong><span>Review options with your plan</span></div><x-theme::icon name="arrow" /></div>
            <p class="vh-vps-setting-note">Check your plan for available regions and operating systems.</p>
        </div>
        @if($vpsBillingCycles->isNotEmpty())
            <fieldset class="vh-vps-billing"><legend class="vh-vps-control-label">BILLING CYCLE</legend>
                <div class="vh-vps-cycles flex flex-wrap gap-1">
                    @foreach($vpsBillingCycles as $billingCycle)
                        <label><input type="radio" name="vps-billing-cycle" value="{{ $billingCycle->period_in_days }}" x-model="period" @checked((string) $billingCycle->period_in_days === $defaultVpsPeriod)><span>{{ $billingCycle->cycle() }}</span></label>
                    @endforeach
                    @if($vpsBillingCycles->count() > 1)<label><input type="radio" name="vps-billing-cycle" value="all" x-model="period"><span>All cycles</span></label>@endif
                </div>
                <p class="vh-vps-setting-note">Prices are per billing cycle. Review your total before checkout.</p>
            </fieldset>
        @endif
        <div class="vh-vps-sidebar-foot"><x-theme::icon name="sliders" /><p>Need a closer look?<br><a href="#vps-details" class="vh-text-link">Explore the possibilities <x-theme::icon name="arrow" /></a></p></div>
        <a href="{{ route('categories.index') }}#services" wire:navigate class="vh-vps-back-link">Explore all hosting <x-theme::icon name="arrow" /></a>
    </aside>

    <div class="min-w-0">
        <div class="vh-vps-grid grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3" @if($vpsPlans->count() === 1) data-single-plan @endif>
            @forelse($vpsPlans as $plan)
                @php($package = $plan['package'])
                @php($prices = $plan['prices'])
                <article class="vh-vps-plan-card flex min-w-0 flex-col" wire:key="vps-package-{{ $package->id }}">
                    <div class="vh-vps-plan-heading flex items-start justify-between gap-3"><h3>{{ $package->name }}</h3><x-theme::icon name="server" /></div>
                    @if($package->short_description)<p class="vh-vps-plan-description">{{ $package->short_description }}</p>@endif
                    @forelse($prices as $planPrice)
                        <div class="vh-vps-plan-price" x-show="period === '{{ $planPrice->period_in_days }}' || (period === 'all' && {{ $loop->first ? 'true' : 'false' }})" @if((string) $planPrice->period_in_days !== $defaultVpsPeriod) x-cloak @endif>
                            <div><strong>{{ price($planPrice->price) }}</strong><span>/ {{ $planPrice->cycle() }}</span></div>
                            <small>{{ $planPrice->setup_fee > 0 ? price($planPrice->setup_fee).' one-time setup' : 'No setup fee' }}</small>
                        </div>
                    @empty
                        <div class="vh-vps-plan-price"><strong class="vh-vps-unavailable">Currently unavailable</strong></div>
                    @endforelse
                    @if($prices->isNotEmpty())
                        <div class="vh-vps-cycle-unavailable" x-show="period !== 'all' && ! @js($prices->pluck('period_in_days')->map(fn ($days) => (string) $days)->values()).includes(period)" x-cloak>This plan is not offered on the selected billing cycle.</div>
                    @endif
                    @if($plan['resources'])
                        <dl class="vh-vps-resources flex flex-col gap-4">
                            @foreach(['cpu' => 'Compute', 'memory' => 'Memory', 'storage' => 'Storage', 'network' => 'Bandwidth'] as $resource => $label)
                                @isset($plan['resources'][$resource])
                                    <div class="flex items-start justify-between gap-3"><dt class="flex items-center gap-2"><x-theme::icon :name="$resource" />{{ $label }}</dt><dd>{{ implode(' · ', $plan['resources'][$resource]) }}</dd></div>
                                @endisset
                            @endforeach
                        </dl>
                    @endif
                    <ul class="vh-vps-features flex flex-col gap-3">
                        @forelse($plan['features'] as $feature)
                            <li class="flex items-start gap-2"><x-theme::icon name="check" /><span>{{ $feature }}</span></li>
                        @empty
                            <li class="flex items-start gap-2"><x-theme::icon name="sliders" /><span>View all details and available configuration.</span></li>
                        @endforelse
                    </ul>
                    <div class="vh-vps-plan-footer">
                        <span class="vh-vps-config-note flex items-center gap-2"><x-theme::icon name="sliders" /> Your configuration. Your server.</span>
                        @foreach($prices as $planPrice)
                            <a class="vh-action vh-vps-deploy" href="{{ route('packages.view', ['package' => $package->slug, 'packagePriceId' => $planPrice->id]) }}" wire:navigate x-show="period === '{{ $planPrice->period_in_days }}' || (period === 'all' && {{ $loop->first ? 'true' : 'false' }})" @if((string) $planPrice->period_in_days !== $defaultVpsPeriod) x-cloak @endif aria-label="Configure {{ $package->name }}, {{ $planPrice->cycle() }}">Configure VPS <x-theme::icon name="arrow" /></a>
                        @endforeach
                    </div>
                </article>
            @empty
                <div class="sm:col-span-2 xl:col-span-3"><x-theme::empty-state title="More VPS plans are on the way." description="No plans are currently available. Explore our other services or check back soon." action-text="Explore hosting" :action-href="route('categories.index').'#services'" /></div>
            @endforelse
            @if($vpsPlans->count() === 1)
                <div class="vh-vps-build-panel flex flex-col justify-between" aria-label="A home for your next project">
                    <div><span class="vh-kicker">YOUR NEXT CHAPTER</span><h3>A little space.<br><em>A lot of potential.</em></h3><p>Your app. Your development environment. Your side project that could become something bigger.</p></div>
                    <div class="vh-vps-server-art" aria-hidden="true"><div><x-theme::icon name="server" /><span></span><span></span></div><div><x-theme::icon name="server" /><span></span><span></span></div><div><x-theme::icon name="server" /><span></span><span></span></div><span class="vh-vps-art-label">VADED / CLOUD INFRASTRUCTURE</span></div>
                    <span class="vh-vps-build-note">Built around what you want to run.</span>
                </div>
            @endif
        </div>
        <p class="vh-vps-catalog-note">Included resources are listed per plan. Configuration, billing, and any extras are reviewed before you order.</p>
    </div>
</section>
