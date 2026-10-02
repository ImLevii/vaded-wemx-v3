<section class="vh-package-page vh-vps-checkout mx-auto max-w-screen-xl px-4 2xl:px-0">
    <header class="vh-vps-checkout-heading"><h1>Checkout</h1><p>Complete your order for {{ $package->name }}</p></header>
    <div class="vh-vps-checkout-layout grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
        <div class="vh-vps-checkout-main flex min-w-0 flex-col gap-6">
            @error('package_error')<div role="alert"><x-theme::alert.danger :text="$message" /></div>@enderror
            <section class="vh-vps-checkout-panel vh-vps-checkout-plan" aria-label="Selected VPS plan">
                <h2>{{ $package->name }}</h2>
                @if($package->features->isNotEmpty())
                    <ul>@foreach($package->features as $feature)<li>{{ $feature->description }}</li>@endforeach</ul>
                @elseif($package->short_description)
                    <p>{{ $package->short_description }}</p>
                @endif
            </section>
            @if($reviewUrl = config('hosting.resources.Trustpilot'))
                <div class="vh-vps-review"><a href="{{ $reviewUrl }}" target="_blank" rel="noopener noreferrer">Review us on <span aria-hidden="true">&#9733;</span><strong>Trustpilot</strong></a></div>
            @endif

            @if($this->packagePrice)
                @if($this->vpsConfiguration['service']->isNotEmpty())
                    <section class="vh-vps-checkout-panel" aria-labelledby="vps-service-heading">
                        <h2 id="vps-service-heading">Service Configuration</h2>
                        <div class="vh-vps-service-fields grid grid-cols-1 gap-4 md:grid-cols-2">
                            @foreach($this->vpsConfiguration['service'] as $option)
                                <x-theme::form.package-option :option="$option" :selected="data_get($this->config_options, $option->key)" wire:key="vps-config-{{ $option->id }}" />
                            @endforeach
                        </div>
                    </section>
                @endif
                @foreach($this->vpsConfiguration['operatingSystems'] as $option)
                    <section class="vh-vps-os-section" wire:key="vps-os-option-{{ $option->id }}" aria-labelledby="vps-os-heading-{{ $option->id }}">
                        <h2 id="vps-os-heading-{{ $option->id }}">Choose Operating System</h2><p>{{ $option->description ?: 'Select the operating system for your server' }}</p>
                        <x-theme::form.os-picker :name="$option->key" :groups="\App\Support\OperatingSystemOptions::group($option->data['options'] ?? [])" :model="'config_options.'.$option->key" :selected="data_get($this->config_options, $option->key)" :required="in_array('required', explode('|', $option->rules ?? ''), true)" :period-in-days="$this->packagePrice->period_in_days" :cycle="$this->packagePrice->cycle()" />
                        @error('config_options.'.$option->key)<div role="alert"><x-theme::form.error :text="$message" /></div>@enderror
                    </section>
                @endforeach
                @if($this->vpsConfiguration['network']->isNotEmpty())
                    <section class="vh-vps-ip-section" aria-labelledby="vps-ip-heading"><h2 id="vps-ip-heading">IP Addresses</h2><div class="flex flex-col gap-4">
                        @foreach($this->vpsConfiguration['network'] as $option)
                            <x-theme::form.package-option :option="$option" :show-label="$this->vpsConfiguration['network']->count() > 1" :selected="data_get($this->config_options, $option->key)" wire:key="vps-network-{{ $option->id }}" />
                        @endforeach
                    </div></section>
                @endif
            @endif

            <section class="vh-vps-checkout-panel vh-vps-checkout-billing" aria-labelledby="vps-billing-heading"><h2 id="vps-billing-heading">Billing Cycle</h2>
                <fieldset><legend class="sr-only">Billing cycle</legend><div class="vh-vps-checkout-cycles grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @forelse($package->prices as $price)
                        <div wire:key="vps-billing-{{ $price->id }}"><input class="sr-only" type="radio" name="package-billing-cycle" id="vps-price-{{ $price->id }}" value="{{ $price->id }}" wire:model.live="packagePriceId" @checked((string) $packagePriceId === (string) $price->id)><label for="vps-price-{{ $price->id }}"><span>{{ $price->cycle() }}<small>{{ $price->setup_fee > 0 ? price($price->setup_fee).' setup fee' : 'No setup fee' }}</small></span><strong>{{ price($price->price) }}</strong></label></div>
                    @empty
                        <p class="vh-package-unavailable">No billing options are currently available for this plan.</p>
                    @endforelse
                </div></fieldset>
            </section>
        </div>
        @include('theme::packages.order-summary')
    </div>
</section>
