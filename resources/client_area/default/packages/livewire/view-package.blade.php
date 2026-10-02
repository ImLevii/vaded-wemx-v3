<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\PackagePrice;
use Illuminate\Support\Facades\DB;
use App\Models\Payment;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;
use Illuminate\View\View;

use App\Models\Package;
use App\Models\GatewayConfig;
use Livewire\Attributes\Url;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Illuminate\Support\Arr;

new class extends Component {
    #[Locked]
    public $package;

    #[Url]
    public $packagePriceId;

    #[Url]
    public $config_options = [];

    #[Url]
    #[Locked]
    public ?int $cartItemId = null;

    public function mount($packageSlug)
    {
        $this->package = Package::where('slug', $packageSlug)->firstOrFail();
        abort_unless($this->package->isVisibleToUser(auth()->user()), 404);

        $firstPrice = $this->package->prices->first();

        if ($firstPrice AND !$this->packagePriceId) {
            $this->packagePriceId = $firstPrice->id;
        }

        if ($this->cartItemId) {
            $item = $this->editableCartItem();
            $this->packagePriceId = $item->cartable_id;
            foreach ($item->options as $option) {
                Arr::set($this->config_options, $option->key, $option->value);
            }
        }
    }

    private function editableCartItem(): CartItem
    {
        $item = cart()->items()->with(['cartable', 'options'])->findOrFail($this->cartItemId);
        abort_unless($item->cartable instanceof PackagePrice && $item->cartable->package_id === $this->package->id, 404);

        return $item;
    }

    public function addToCart(): void
    {
        $editingItem = $this->cartItemId ? $this->editableCartItem() : null;

        if (! $this->packagePrice) {
            $this->addError('package_error', 'Choose an available billing cycle.');

            return;
        }
        // if server connection has prevent_purchasing enabled, and the server connection is not healthy, prevent adding to cart
        if ($this->package->serverConnection->prevent_purchasing AND !$this->package->serverConnection->isHealthy()) {
            $this->addError('package_error', 'Could not establish connection to third party server. Please contact support or try again later.');
            return;
        }

        try {
            // if method exists, call the event to validate the package before adding to cart
            if (method_exists($this->package->serverConnection->server->functions(), 'eventAddToCart')) {
                $this->package->serverConnection->server->functions()->eventAddToCart(
                    $this->package,
                    $this->config_options,
                );
            }
        } catch (\Exception $e) {
            // If validation fails, we can handle it here or let the Livewire component handle it
            $this->addError('package_error', $e->getMessage());
            return;
        }

        if ($editingItem) {
            $breakdown = $this->package->configurableOptionCalculator($this->config_options, $this->packagePrice->period_in_days);
            DB::transaction(function () use ($editingItem, $breakdown): void {
                $editingItem->update([
                    'cartable_id' => $this->packagePrice->id,
                    'price' => $this->packagePrice->price + $this->packagePrice->setup_fee,
                ]);
                $editingItem->options()->delete();
                $editingItem->options()->createMany(collect($breakdown['breakdown'])->map(fn (array $option): array => [
                    'name' => $option['label'],
                    'price' => $option['total'],
                    'key' => $option['key'],
                    'value' => $option['value'],
                ])->all());
            });
        } else {
            Cart::actions()->addPackageToCart([
                'cart_id' => cart()->id,
                'package_price_id' => $this->packagePriceId,
                'config_options' => $this->config_options,
            ]);
        }

        $this->redirect(route('cart'), true);
    }

    #[Computed]
    public function packagePrice()
    {
        return $this->package->prices()->find($this->packagePriceId);
    }

    #[Computed]
    public function calculateConfigOptionCost()
    {
        try {
            return $this->package->configurableOptionCalculator($this->config_options, $this->packagePrice->period_in_days);
        } catch (ValidationException $e) {
            // If validation fails, return an empty array
            return [
                'total' => 0,
                'breakdown' => [],
            ];
        }
    }

    public function rendering($view)
    {
        if ($this->config_options == []) {
            $this->config_options = [];

            foreach ($this->package->configOptions as $option) {
                // Sets nested arrays using dot notation: "a.b.c" => ['a' => ['b' => ['c' => value]]]
                Arr::set($this->config_options, $option->key, $option->default_value ?? '');
            }
        }
    }
};
?>

<section class="vh-package-page mx-auto max-w-screen-xl px-4 2xl:px-0">
    <div class="vh-package-topline">
        <a href="{{ route('categories.index') }}" wire:navigate class="vh-package-back"><span aria-hidden="true">&larr;</span> All services</a>
        <x-theme::purchase-steps :current="2" />
    </div>

    <header class="vh-package-heading">
        <div class="vh-package-identity">
            <div class="vh-package-art"><img src="{{ $package->icon() }}" alt="" width="96" height="96"></div>
            <div>
                <span class="vh-package-eyebrow">Make it yours</span>
                <h1>{{ $package->name }}</h1>
                @if (filled($package->short_description))
                    <p>{{ $package->short_description }}</p>
                @else
                    <p>Choose your billing cycle and configure your service.</p>
                @endif
            </div>
        </div>
        @if ($this->packagePrice)
            <div class="vh-package-price-preview"><span>Selected plan</span><strong>{{ price($this->packagePrice->price) }}</strong><small>{{ $this->packagePrice->cycle() }}</small></div>
        @endif
    </header>

    <div class="vh-package-layout">
        <div class="vh-package-main">
            @error('package_error')
                <div role="alert"><x-theme::alert.danger :text="$message" /></div>
            @enderror

            @if (filled(trim($package->description ?? '')) || $package->features->isNotEmpty())
                <section class="vh-purchase-panel vh-package-overview" aria-labelledby="package-overview-heading">
                    <div class="vh-purchase-section-heading"><span class="vh-purchase-section-icon" aria-hidden="true">&#10003;</span><div><h2 id="package-overview-heading">Your plan at a glance</h2><p>Everything included with this service.</p></div></div>
                    @if (filled(trim($package->description ?? '')))
                        <div class="format format-sm dark:format-invert vh-package-description">{!! Str::markdown($package->description) !!}</div>
                    @endif
                    @if ($package->features->isNotEmpty())
                        <ul class="vh-package-features">
                            @foreach ($package->features as $feature)
                                <li><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m4 10 4 4 8-8"/></svg>{{ $feature->description }}</li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif

            <section class="vh-purchase-panel" aria-labelledby="billing-heading">
                <div class="vh-purchase-section-heading"><span class="vh-purchase-section-icon" aria-hidden="true">01</span><div><h2 id="billing-heading">Billing cycle</h2><p>Choose how often you would like to be billed.</p></div></div>
                <fieldset>
                    <legend class="sr-only">Billing cycle</legend>
                    <ul class="vh-billing-options">
                        @forelse ($package->prices as $price)
                            <li wire:key="billing-{{ $price->id }}">
                                <input type="radio" name="package-billing-cycle" wire:model.live="packagePriceId" id="package_price_id{{ $price->id }}" value="{{ $price->id }}" class="sr-only peer" required>
                                <label for="package_price_id{{ $price->id }}" class="vh-billing-option">
                                    <span class="vh-billing-option-top"><span>{{ $price->cycle() }}</span><span class="vh-billing-check" aria-hidden="true"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 8 3 3 7-7"/></svg></span></span>
                                    <strong>{{ price($price->price) }}</strong>
                                    @if (filled($price->short_description))<span class="vh-billing-description">{{ $price->short_description }}</span>@endif
                                    <span class="vh-billing-setup">{{ $price->setup_fee > 0 ? price($price->setup_fee).' setup fee' : 'No setup fee' }}</span>
                                </label>
                            </li>
                        @empty
                            <li class="vh-package-unavailable">No billing options are currently available for this plan.</li>
                        @endforelse
                    </ul>
                </fieldset>
            </section>

            @if ($this->packagePrice && $this->package->configOptions->isNotEmpty())
                <section class="vh-purchase-panel vh-package-options" aria-labelledby="options-heading">
                    <div class="vh-purchase-section-heading"><span class="vh-purchase-section-icon" aria-hidden="true">02</span><div><h2 id="options-heading">Configure your service</h2><p>Adjust the options to suit your project.</p></div></div>
                        @foreach($this->package->configOptions as $option)
                            <div class="mb-4">
                                @if($option->type !== 'radio')
                                    <x-theme::form.label :text="$option->label" for="{{ $option->key }}-input"/>
                                @endif
                                @if($option->type == 'select')
                                    <x-theme::form.select
                                        :options="collect($option->data['options'] ?? [])->pluck('name', 'value')->toArray()"
                                        id="{{ $option->key }}-input"
                                        wire:model.change="config_options.{{ $option->key }}"
                                    />
                                @elseif($option->type == 'radio')
                                    <x-theme::form.radio-cards
                                        :name="$option->key"
                                        :title="$option->label"
                                        :options="$option->data['options'] ?? []"
                                        :selected="data_get($config_options, $option->key)"
                                        :model="'config_options.'.$option->key"
                                    />
                                @elseif((in_array($option->type, ['text', 'email', 'password'])))
                                    <x-theme::form.input
                                        :type="$option->type"
                                        :name="$option->name"
                                        :placeholder="$option->placeholder"
                                        id="{{ $option->key }}-input"
                                        wire:model.change="config_options.{{ $option->key }}"
                                    />
                                @elseif(in_array($option->type, ['number', 'range']))
                                    <x-theme::form.input
                                        id="{{ $option->key }}-input"
                                        :type="$option->type"
                                        :name="$option->name"
                                        :placeholder="$option->placeholder"
                                        :min="$option->data['min_value'] ?? ''"
                                        :max="$option->data['max_value'] ?? ''"
                                        :step="$option->data['step_value'] ?? 1"
                                        wire:model.change="config_options.{{ $option->key }}"
                                    />
                                @elseif($option->type == 'textarea')
                                    <x-theme::form.textarea
                                        :name="$option->name"
                                        :placeholder="$option->placeholder"
                                        id="{{ $option->key }}-input"
                                        wire:model.change="config_options.{{ $option->key }}"
                                    />
                                @endif
                                @error("config_options.{$option->key}")
                                <x-theme::form.error :text="$message"/>
                                @else
                                    <x-theme::form.description :text="$option->description"/>
                                    @enderror
                            </div>
                        @endforeach

                </section>
            @endif
        </div>

        <aside class="vh-package-summary" aria-labelledby="package-summary-heading" x-data="{ expanded: false }">
            <div class="vh-purchase-panel">
                <button type="button" class="vh-summary-toggle" @click="expanded = !expanded" :aria-expanded="expanded" aria-controls="package-price-breakdown">Order summary <span x-text="expanded ? 'Hide details −' : 'Show details +'">Show details +</span></button>
                <span class="vh-package-eyebrow">Your configuration</span>
                <h2 id="package-summary-heading">Order summary</h2>
                <div class="vh-summary-product"><img src="{{ $package->icon() }}" alt="" width="44" height="44"><div><strong>{{ $package->name }}</strong><span>{{ $this->packagePrice?->cycle() ?? 'Unavailable' }}</span></div></div>
                @if ($this->packagePrice)
                    <div id="package-price-breakdown" class="vh-summary-breakdown" :class="{ 'vh-mobile-collapsed': !expanded }" aria-live="polite" aria-atomic="true">
                        <dl><dt>Billing cycle</dt><dd>{{ price($this->packagePrice->price) }} <small>/ {{ $this->packagePrice->cycle() }}</small></dd></dl>
                        <dl><dt>Setup fee</dt><dd>{{ price($this->packagePrice->setup_fee) }}</dd></dl>
                        @if ($this->calculateConfigOptionCost()['total'] > 0)
                            @foreach ($this->calculateConfigOptionCost()['breakdown'] as $option)
                                @continue((float) ($option['total'] ?? 0) <= 0)
                                <dl><dt>{{ $option['label'] }}</dt><dd>{{ price($option['total']) }}</dd></dl>
                            @endforeach
                        @endif
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
    </div>
</section>
