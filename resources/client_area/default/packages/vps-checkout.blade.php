@php
    $groups = $this->vpsConfiguration;
    $cycles = $this->vpsBillingCycles;
    $selectedCycle = $cycles->first(fn (array $cycle): bool => (string) $cycle['price']->id === (string) $packagePriceId);
    $showDiscounts = $cycles->count() > 1 && $cycles->contains(fn (array $cycle): bool => $cycle['discount'] !== null);
    // Admin pre-fills new options with the literal description "Description"; treat that as empty.
    $describe = fn ($option, ?string $fallback): ?string => filled($option->description) && trim($option->description) !== 'Description' ? $option->description : $fallback;
    $isRequired = fn ($option): bool => in_array('required', explode('|', $option->rules ?? ''), true);
    $sections = [
        'intendedUse' => ['fallback' => 'Please explain the intended use of the server.', 'placeholder' => 'e.g. Game servers, a website, a development environment'],
        'hostname' => ['fallback' => 'Choose a name for your server. It is shown in your control panel and set on the operating system you select.', 'placeholder' => 'vps.example.com'],
        'location' => ['fallback' => 'Choose the data center your server is deployed in.'],
        'operatingSystems' => ['fallback' => 'Select the operating system for your server.'],
        'network' => ['fallback' => 'Choose how many IPv4 addresses your server needs.'],
        'service' => ['fallback' => null],
    ];
@endphp
<section class="vh-package-page vh-vps-checkout mx-auto max-w-screen-xl px-4 2xl:px-0">
    <header class="vh-vps-checkout-heading"><h1>Checkout</h1><p>Complete your order for {{ $package->name }}</p></header>
    <div class="vh-vps-checkout-layout grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="vh-vps-checkout-main flex min-w-0 flex-col">
            @error('package_error')<div role="alert"><x-theme::alert.danger :text="$message" /></div>@enderror

            <section class="vh-vps-checkout-panel vh-vps-checkout-plan" aria-labelledby="vps-plan-heading">
                <div class="vh-vps-plan-head">
                    <h2 id="vps-plan-heading">{{ $package->name }}</h2>
                    @if($this->packagePrice)
                        <p class="vh-vps-plan-rate"><strong>{{ price($this->packagePrice->price) }}</strong> {{ Str::lower($selectedCycle['label'] ?? $this->packagePrice->cycle()) }}</p>
                    @endif
                </div>
                @if($package->features->isNotEmpty())
                    <h3>Server Specifications</h3>
                    <ul class="vh-vps-spec-list">@foreach($package->features as $feature)<li>{{ $feature->description }}</li>@endforeach</ul>
                @elseif($package->short_description)
                    <p>{{ $package->short_description }}</p>
                @endif
            </section>
            @if($reviewUrl = config('hosting.resources.Trustpilot'))
                <div class="vh-vps-review"><a href="{{ $reviewUrl }}" target="_blank" rel="noopener noreferrer">Review us on <span aria-hidden="true">&#9733;</span><strong>Trustpilot</strong></a></div>
            @endif

            <section class="vh-vps-checkout-billing" aria-labelledby="vps-billing-heading">
                <header class="vh-vps-step-heading"><h2 id="vps-billing-heading">Choose a Billing Cycle</h2><p>How often do you want to pay for your service?</p></header>
                <fieldset><legend class="sr-only">Billing cycle</legend>
                    <div class="vh-vps-cycle-grid" data-count="{{ min($cycles->count(), 4) }}">
                        @forelse($cycles as $cycle)
                            @php($price = $cycle['price'])
                            <div wire:key="vps-billing-{{ $price->id }}">
                                <input class="sr-only" type="radio" name="package-billing-cycle" id="vps-price-{{ $price->id }}" value="{{ $price->id }}" wire:model.live="packagePriceId" @checked((string) $packagePriceId === (string) $price->id)>
                                <label for="vps-price-{{ $price->id }}">
                                    <span class="vh-vps-cycle-name">{{ $cycle['label'] }}</span>
                                    <strong>{{ price($price->price) }}</strong>
                                    @if($showDiscounts && $cycle['discount'] !== null)
                                        <span class="vh-vps-cycle-discount" @if($cycle['discount'] > 0) data-saving @endif>{{ $cycle['discount'] }}% discount</span>
                                    @endif
                                    @if($price->setup_fee > 0)<small>+ {{ price($price->setup_fee) }} setup fee</small>@endif
                                </label>
                            </div>
                        @empty
                            <p class="vh-package-unavailable">No billing options are currently available for this plan.</p>
                        @endforelse
                    </div>
                </fieldset>
            </section>

            @if($this->packagePrice && $package->configOptions->isNotEmpty())
                <h2 class="vh-vps-options-divider"><span>Server Options</span></h2>
                @foreach($sections as $group => $meta)
                    @foreach($groups[$group] as $option)
                        <section @class(['vh-vps-option-section', 'vh-vps-os-section' => $group === 'operatingSystems']) wire:key="vps-option-{{ $option->id }}" aria-labelledby="vps-option-{{ $option->id }}-heading">
                            <header class="vh-vps-step-heading">
                                <h3 id="vps-option-{{ $option->id }}-heading">{{ $option->label }}@if($isRequired($option))<span class="vh-vps-required" aria-hidden="true"> *</span>@endif</h3>
                                @if($text = $describe($option, $meta['fallback']))<p>{{ $text }}</p>@endif
                            </header>
                            @if($group === 'operatingSystems')
                                <x-theme::form.os-picker :name="$option->key" :groups="\App\Support\OperatingSystemOptions::group($option->data['options'] ?? [])" :model="'config_options.'.$option->key" :selected="data_get($this->config_options, $option->key)" :required="$isRequired($option)" :period-in-days="$this->packagePrice->period_in_days" :cycle="$this->packagePrice->cycle()" />
                                @error('config_options.'.$option->key)<div role="alert"><x-theme::form.error :text="$message" /></div>@enderror
                            @else
                                <x-theme::form.package-option :option="$option" :show-label="false" :show-description="false" :placeholder="$meta['placeholder'] ?? null" :selected="data_get($this->config_options, $option->key)" />
                            @endif
                        </section>
                    @endforeach
                @endforeach
            @endif

            @if($this->packagePrice)
                <div class="vh-vps-next-row">
                    <button type="button" wire:click="addToCart" wire:loading.attr="disabled" class="vh-action vh-vps-next">
                        <span wire:loading.remove wire:target="addToCart">{{ $cartItemId ? 'Save configuration' : 'Next' }} <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
                        <span wire:loading wire:target="addToCart" role="status">Saving configuration&hellip;</span>
                    </button>
                </div>
            @endif
        </div>
        @include('theme::packages.vps-order-summary')
    </div>
</section>
