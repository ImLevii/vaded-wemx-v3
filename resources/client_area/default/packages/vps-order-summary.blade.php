        <aside class="vh-package-summary vh-vps-summary" aria-labelledby="vps-summary-heading">
            <div class="vh-purchase-panel">
                <h2 id="vps-summary-heading">Order Summary</h2>
                @if ($this->packagePrice)
                    @php($pricing = $this->vpsPricing)
                    <div aria-live="polite" aria-atomic="true">
                        <ul class="vh-vps-summary-lines">
                            <li class="vh-vps-summary-product"><span>{{ $package->name }}</span><span>{{ price($pricing['base']) }}</span></li>
                            @foreach ($pricing['lines'] as $line)
                                <li><span><span aria-hidden="true">&raquo;</span> {{ $line['label'] }}: {{ $line['value'] }}</span><span>{{ price($line['total']) }}</span></li>
                            @endforeach
                        </ul>
                        <dl class="vh-vps-summary-totals">
                            <div><dt>Setup Fees</dt><dd>{{ price($pricing['setup']) }}</dd></div>
                            <div><dt>{{ $this->packagePrice->cycle() }}</dt><dd>{{ price($pricing['recurring']) }}</dd></div>
                        </dl>
                        <p class="vh-vps-summary-due"><strong>{{ price($pricing['dueToday']) }}</strong> <span>Total Due Today</span></p>
                    </div>
                    @if ($errors->any() && ! $errors->has('package_error'))
                        <p class="vh-vps-summary-error" role="alert">Please complete the highlighted fields.</p>
                    @endif
                    <button type="button" wire:click="addToCart" wire:loading.attr="disabled" class="vh-action vh-vps-next">
                        <span wire:loading.remove wire:target="addToCart">{{ $cartItemId ? 'Save configuration' : 'Next' }} <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
                        <span wire:loading wire:target="addToCart" role="status">Saving configuration&hellip;</span>
                    </button>
                    <p class="vh-summary-footnote">You can review your cart before payment.</p>
                @else
                    <p class="vh-package-unavailable">This plan is currently unavailable. Explore our other services to find your next server.</p>
                @endif
                <a href="{{ route('categories.index') }}" wire:navigate class="vh-package-continue">Continue shopping <span aria-hidden="true">&rarr;</span></a>
            </div>
        </aside>
