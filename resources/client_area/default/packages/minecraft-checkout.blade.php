<section class="vh-package-page vh-minecraft-checkout mx-auto max-w-screen-xl px-4 2xl:px-0">
    <div class="vh-package-topline">
        <a href="{{ route('categories.index', ['category' => $package->category->slug]) }}" wire:navigate class="vh-package-back"><span aria-hidden="true">&larr;</span> Minecraft plans</a>
        <x-theme::purchase-steps :current="2" />
    </div>
    <header class="vh-package-heading"><div class="vh-package-identity"><div class="vh-package-art"><img src="{{ $package->icon() }}" alt="" width="96" height="96"></div><div><span class="vh-package-eyebrow">Build your world</span><h1>{{ $package->name }}</h1><p>{{ $package->short_description ?: 'Choose your billing cycle, location, and server software.' }}</p></div></div></header>
    <div class="vh-package-layout">
        <div class="vh-package-main">
            @error('package_error')<div role="alert"><x-theme::alert.danger :text="$message" /></div>@enderror
            @if($this->packagePrice && $this->hasMinecraftRecommendation)
                <button type="button" class="vh-minecraft-recommend" wire:click="useRecommendedMinecraftConfiguration" wire:loading.attr="disabled">
                    <x-theme::icon name="cube" /><span><strong>First time running a Minecraft server?</strong> Use our recommended server software.</span><x-theme::icon name="arrow" />
                </button>
            @endif
            <section class="vh-purchase-panel" aria-labelledby="minecraft-billing-heading">
                <div class="vh-purchase-section-heading"><span class="vh-purchase-section-icon" aria-hidden="true">01</span><div><h2 id="minecraft-billing-heading">Choose a Billing Cycle</h2><p>How often do you want to pay for your service?</p></div></div>
                <fieldset><legend class="sr-only">Billing cycle</legend><div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @forelse($this->minecraftBillingCycles as $price)
                        @php($discount = \App\Support\MinecraftCheckoutOptions::discount($price, $this->minecraftBillingCycles))
                        <div wire:key="minecraft-billing-{{ $price->id }}">
                            <input type="radio" name="package-billing-cycle" id="minecraft-price-{{ $price->id }}" value="{{ $price->id }}" wire:model.live="packagePriceId" class="sr-only" @checked((string) $packagePriceId === (string) $price->id)>
                            <label class="vh-minecraft-choice vh-minecraft-cycle" for="minecraft-price-{{ $price->id }}"><strong>{{ $price->cycle() }}</strong><span class="vh-minecraft-cycle-price">{{ price($price->price) }}</span>@if($discount !== null)<small>{{ $discount }}% discount</small>@endif<small>{{ $price->setup_fee > 0 ? price($price->setup_fee).' setup fee' : 'No setup fee' }}</small></label>
                        </div>
                    @empty
                        <p class="vh-package-unavailable">No billing options are currently available for this plan.</p>
                    @endforelse
                </div></fieldset>
            </section>
            @if($this->packagePrice)
                <div class="vh-minecraft-divider"><span>Server Options</span></div>
                <section class="vh-purchase-panel" aria-labelledby="minecraft-location-heading">
                    <div class="vh-purchase-section-heading"><span class="vh-purchase-section-icon" aria-hidden="true">02</span><div><h2 id="minecraft-location-heading">Choose a Location</h2><p>Choose a region close to you and your players.</p></div></div>
                    @forelse($this->minecraftConfiguration['locations'] as $option)
                        <x-theme::form.minecraft-picker :option="$option" :groups="['Locations' => \App\Support\MinecraftCheckoutOptions::choices($option)]" :selected="data_get($config_options, $option->key)" :period-in-days="$this->packagePrice->period_in_days" :cycle="$this->packagePrice->cycle()" wire:key="minecraft-location-{{ $option->id }}" />
                    @empty
                        <p class="vh-minecraft-help">This plan uses its configured server location. Location selection is not available.</p>
                    @endforelse
                </section>
                <section class="vh-purchase-panel" aria-labelledby="minecraft-software-heading">
                    <div class="vh-purchase-section-heading"><span class="vh-purchase-section-icon" aria-hidden="true">03</span><div><h2 id="minecraft-software-heading">Server Version</h2><p>Choose the software for your Minecraft server. Review any extra charges before continuing.</p></div></div>
                    @forelse($this->minecraftConfiguration['software'] as $option)
                        <x-theme::form.minecraft-picker :option="$option" :groups="\App\Support\MinecraftCheckoutOptions::softwareGroups($option)" :selected="data_get($config_options, $option->key)" :period-in-days="$this->packagePrice->period_in_days" :cycle="$this->packagePrice->cycle()" wire:key="minecraft-software-{{ $option->id }}" />
                    @empty
                        <p class="vh-minecraft-help">Server software is included with this plan. Software selection is not available.</p>
                    @endforelse
                </section>
                @if($this->minecraftConfiguration['service']->isNotEmpty())
                    <section class="vh-purchase-panel" aria-labelledby="minecraft-service-heading"><div class="vh-purchase-section-heading"><span class="vh-purchase-section-icon" aria-hidden="true">04</span><div><h2 id="minecraft-service-heading">Service Configuration</h2><p>Adjust your server details and resources.</p></div></div><div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        @foreach($this->minecraftConfiguration['service'] as $option)
                            <x-theme::form.package-option :option="$option" :selected="data_get($config_options, $option->key)" wire:key="minecraft-config-{{ $option->id }}" />
                        @endforeach
                    </div></section>
                @endif
            @endif
            @if($package->features->isNotEmpty() || filled($package->description))
                <section class="vh-purchase-panel vh-package-overview" aria-labelledby="minecraft-plan-heading"><h2 id="minecraft-plan-heading">Included with your plan</h2>@if(filled($package->description))<div class="format format-sm dark:format-invert vh-package-description">{!! Str::markdown($package->description) !!}</div>@endif<ul class="vh-package-features">@foreach($package->features as $feature)<li><x-theme::icon name="check" />{{ $feature->description }}</li>@endforeach</ul></section>
            @endif
        </div>
        @include('theme::packages.order-summary')
    </div>
</section>
