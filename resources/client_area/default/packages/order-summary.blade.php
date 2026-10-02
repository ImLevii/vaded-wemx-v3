        <aside class="vh-package-summary" aria-labelledby="package-summary-heading" x-data="{ expanded: false }">
            <div class="vh-purchase-panel">
                <button type="button" class="vh-summary-toggle" @click="expanded = !expanded" :aria-expanded="expanded" aria-controls="package-price-breakdown">Price breakdown <span x-text="expanded ? 'Hide details −' : 'Show details +'">Show details +</span></button>
                <span class="vh-package-eyebrow">Your configuration</span>
                <h2 id="package-summary-heading">Order summary</h2>
                <div class="vh-summary-product"><img src="{{ $package->icon() }}" alt="" width="44" height="44"><div><strong>{{ $package->name }}</strong><span>{{ $this->packagePrice?->cycle() ?? 'Unavailable' }}</span></div></div>
                @if ($this->packagePrice)
                    <div class="vh-summary-breakdown" aria-live="polite" aria-atomic="true">
                        <div id="package-price-breakdown" :class="{ 'vh-mobile-collapsed': !expanded }">
                        <dl><dt>Billing cycle</dt><dd>{{ price($this->packagePrice->price) }} <small>/ {{ $this->packagePrice->cycle() }}</small></dd></dl>
                        <dl><dt>Setup fee</dt><dd>{{ price($this->packagePrice->setup_fee) }}</dd></dl>
                        @if ($this->calculateConfigOptionCost()['total'] > 0)
                            @foreach ($this->calculateConfigOptionCost()['breakdown'] as $option)
                                @continue((float) ($option['total'] ?? 0) <= 0)
                                <dl><dt>{{ $option['label'] }}</dt><dd>{{ price($option['total']) }}</dd></dl>
                            @endforeach
                        @endif
                        </div>
                        <div class="vh-summary-total"><span>Plan total</span><div><strong>{{ price($this->calculateConfigOptionCost()['total'] + $this->packagePrice->price) }}</strong><small>{{ $this->packagePrice->cycle() }}</small></div></div>
                        @if ($this->packagePrice->setup_fee > 0)<p class="vh-summary-note">Plus {{ price($this->packagePrice->setup_fee) }} one-time setup fee.</p>@endif
                    </div>
                    <button type="button" wire:click="addToCart" wire:loading.attr="disabled" class="vh-action vh-action-tile vh-package-submit">
                        <span wire:loading.remove wire:target="addToCart"><x-theme::action-content :label="$cartItemId ? 'Save configuration' : 'Add to cart'" description="Review before checkout" icon="M3 3h2l3 12h11l3-9H6M9 20h.01M18 20h.01" /></span>
                        <span wire:loading wire:target="addToCart" role="status">Saving configuration&hellip;</span>
                    </button>
                    <p class="vh-summary-footnote">You can review your items in the cart before checkout.</p>
                @else
                    <p class="vh-package-unavailable">This plan is currently unavailable. Explore our other services to find your next server.</p>
                @endif
                <a href="{{ route('categories.index') }}" wire:navigate class="vh-package-continue">Continue shopping <span aria-hidden="true">&rarr;</span></a>
            </div>
        </aside>