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
use App\Support\OperatingSystemOptions;
use App\Support\MinecraftCheckoutOptions;
use App\Models\PackageConfigOption;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;

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

    public function mount(string $packageSlug): void
    {
        $this->package = Package::with(['category', 'prices', 'features', 'configOptions'])->where('slug', $packageSlug)->firstOrFail();
        abort_unless($this->package->isVisibleToUser(auth()->user()), 404);

        $firstPrice = $this->isMinecraftCheckout ? $this->minecraftBillingCycles->firstWhere('period_in_days', 30) ?? $this->minecraftBillingCycles->first() : $this->package->prices->first();

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
        abort_unless($this->package->isVisibleToUser(auth()->user()), 404);
        $editingItem = $this->cartItemId ? $this->editableCartItem() : null;

        if (! $this->packagePrice) {
            $this->addError('package_error', 'Choose an available billing cycle.');

            return;
        }
        if ($this->isVpsCheckout) {
            $this->validateVpsOptions();
        }
        if ($this->isMinecraftCheckout) {
            foreach ($this->package->configOptions as $option) {
                if (MinecraftCheckoutOptions::kind($option) !== 'service') {
                    $this->validate(['config_options.'.$option->key => [\Illuminate\Validation\Rule::in(array_column(MinecraftCheckoutOptions::choices($option), 'value'))]]);
                }
            }
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
    public function packagePrice(): ?PackagePrice
    {
        return $this->package->prices()->when($this->isMinecraftCheckout, fn (Builder $query): Builder => $query->where('is_active', true))->find($this->packagePriceId);
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
        foreach ($this->package->configOptions as $option) {
            if (! Arr::has($this->config_options, $option->key)) {
                Arr::set($this->config_options, $option->key, $option->default_value ?? '');
            }
        }
    }

    #[Computed]
    public function isVpsCheckout(): bool
    {
        return Str::contains(Str::lower($this->package->category->slug), 'vps');
    }

    #[Computed]
    public function isMinecraftCheckout(): bool
    {
        return Str::contains(Str::lower($this->package->category->slug), 'minecraft');
    }

    #[Computed]
    public function minecraftBillingCycles(): \Illuminate\Support\Collection
    {
        return $this->package->prices->where('is_active', true)->sortBy('period_in_days')->values();
    }

    #[Computed]
    public function hasMinecraftRecommendation(): bool
    {
        return $this->minecraftConfiguration['software']->contains(fn (PackageConfigOption $option): bool => MinecraftCheckoutOptions::recommendation($option) !== null);
    }

    /** @return array{locations: \Illuminate\Support\Collection, software: \Illuminate\Support\Collection, service: \Illuminate\Support\Collection} */
    #[Computed]
    public function minecraftConfiguration(): array
    {
        $groups = $this->package->configOptions->groupBy(fn (PackageConfigOption $option): string => MinecraftCheckoutOptions::kind($option));

        return [
            'locations' => $groups->get('locations', collect()),
            'software' => $groups->get('software', collect()),
            'service' => $groups->get('service', collect()),
        ];
    }

    public function useRecommendedMinecraftConfiguration(): void
    {
        abort_unless($this->isMinecraftCheckout && $this->package->isVisibleToUser(auth()->user()), 404);

        foreach ($this->minecraftConfiguration['software'] as $option) {
            $recommended = MinecraftCheckoutOptions::recommendation($option);

            if ($recommended) {
                Arr::set($this->config_options, $option->key, $recommended['value']);
            }
        }

        $this->resetValidation();
    }

    /**
     * VPS "Server Options", grouped in the order the checkout shows them.
     * Each package option lands in exactly one group, matched by its key/label.
     *
     * @return array{intendedUse: \Illuminate\Support\Collection, hostname: \Illuminate\Support\Collection, location: \Illuminate\Support\Collection, operatingSystems: \Illuminate\Support\Collection, network: \Illuminate\Support\Collection, service: \Illuminate\Support\Collection}
     */
    #[Computed]
    public function vpsConfiguration(): array
    {
        $remaining = $this->package->configOptions->keyBy('id');
        $take = function (callable $matches) use (&$remaining): \Illuminate\Support\Collection {
            $found = $remaining->filter($matches);
            $remaining = $remaining->diffKeys($found);

            return $found->values();
        };
        $named = fn (array $types, string $pattern): callable => fn (PackageConfigOption $option): bool => in_array($option->type, $types, true) && preg_match($pattern, $option->key.' '.$option->label) === 1;

        $operatingSystems = $take(fn (PackageConfigOption $option): bool => OperatingSystemOptions::recognizes($option));
        $network = $take($named(['select', 'radio', 'number', 'range'], '/(?:^|[\s_.-])(?:ipv?[46]?|ips|ip[\s_.-]*addresses?)(?:$|[\s_.-])/i'));
        $hostname = $take($named(['text'], '/host[\s_.-]*name|fqdn/i'));
        $intendedUse = $take($named(['text', 'textarea'], '/intended|purpose|use[\s_.-]*case|usage/i'));
        $location = $take($named(['select', 'radio'], '/location|region|data[\s_.-]*cent(?:er|re)|country/i'));

        return [
            'intendedUse' => $intendedUse,
            'hostname' => $hostname,
            'location' => $location,
            'operatingSystems' => $operatingSystems,
            'network' => $network,
            'service' => $remaining->values(),
        ];
    }

    /**
     * Billing cycles shortest first, with the saving against paying monthly.
     *
     * @return \Illuminate\Support\Collection<int, array{price: PackagePrice, label: string, discount: int|null}>
     */
    #[Computed]
    public function vpsBillingCycles(): \Illuminate\Support\Collection
    {
        $prices = $this->package->prices->sortBy('period_in_days')->values();
        $base = $prices->firstWhere('period_in_days', 30) ?? $prices->first(fn (PackagePrice $price): bool => $price->period_in_days > 0);

        return $prices->map(function (PackagePrice $price) use ($base): array {
            $days = (int) $price->period_in_days;
            $months = $days >= 28 ? (int) round($days / 30) : 0;
            $wholeMonths = $months >= 1 && abs($days - $months * 30) <= 6;

            $label = match (true) {
                $wholeMonths && $months === 1 => 'Every Month',
                $wholeMonths => "Every {$months} Months",
                default => $price->cycle(),
            };

            $discount = null;
            if ($base && $days > 0 && (float) $base->price > 0) {
                // Compare with paying the monthly price for the same span (12 x monthly for a year).
                $standard = $wholeMonths && (int) $base->period_in_days === 30
                    ? (float) $base->price * $months
                    : (float) $base->price / $base->period_in_days * $days;
                $discount = max(0, (int) round((1 - (float) $price->price / $standard) * 100));
            }

            return ['price' => $price, 'label' => $label, 'discount' => $discount];
        });
    }

    /**
     * Itemised VPS order summary. Priced options only, so an empty required
     * text field (hostname, intended use) can't blank the breakdown.
     *
     * @return array{lines: list<array{label: string, value: string, total: float}>, base: float, setup: float, recurring: float, dueToday: float}
     */
    #[Computed]
    public function vpsPricing(): array
    {
        $price = $this->packagePrice;
        if (! $price) {
            return ['lines' => [], 'base' => 0.0, 'setup' => 0.0, 'recurring' => 0.0, 'dueToday' => 0.0];
        }
        $priced = [];
        foreach ($this->package->configOptions as $option) {
            if (in_array($option->type, ['select', 'radio', 'number', 'range'], true) && Arr::has($this->config_options, $option->key)) {
                Arr::set($priced, $option->key, Arr::get($this->config_options, $option->key));
            }
        }

        try {
            $breakdown = collect($this->package->configurableOptionCalculator($priced, $price->period_in_days)['breakdown'])->keyBy('key');
        } catch (ValidationException $e) {
            $breakdown = collect();
        }

        $lines = [];
        $groups = $this->vpsConfiguration;
        foreach ([...$groups['location'], ...$groups['operatingSystems'], ...$groups['network'], ...$groups['service']] as $option) {
            $item = $breakdown->get($option->key);
            if (! $item) {
                continue;
            }
            $value = in_array($option->type, ['select', 'radio'], true)
                ? (collect($option->data['options'] ?? [])->firstWhere('value', $item['value'])['name'] ?? $item['value'])
                : $item['value'];
            $lines[] = ['label' => $option->label, 'value' => (string) $value, 'total' => (float) $item['total']];
        }

        $base = (float) $price->price;
        $setup = (float) $price->setup_fee;
        $recurring = $base + array_sum(array_column($lines, 'total'));

        return ['lines' => $lines, 'base' => $base, 'setup' => $setup, 'recurring' => $recurring, 'dueToday' => $recurring + $setup];
    }

    /**
     * Run each option's admin rules with its label as the field name (so errors read
     * "The Hostname field is required." rather than "config options.hostname"), and
     * require hostnames to be valid DNS names whatever rules they have in admin.
     */
    private function validateVpsOptions(): void
    {
        $rules = [];
        $attributes = [];
        foreach ($this->package->configOptions as $option) {
            $path = 'config_options.'.$option->key;
            $rules[$path] = array_values(array_filter(explode('|', (string) $option->rules)));
            $attributes[$path] = $option->label;
        }

        foreach ($this->vpsConfiguration['hostname'] as $option) {
            $path = 'config_options.'.$option->key;
            $value = Arr::get($this->config_options, $option->key);
            if (is_string($value)) {
                $value = Str::lower(trim($value));
                Arr::set($this->config_options, $option->key, $value);
            }
            if (filled($value)) {
                $rules[$path] = [...$rules[$path], 'string', 'max:253', 'regex:/^(?!-)[a-z0-9-]{1,63}(?<!-)(?:\.(?!-)[a-z0-9-]{1,63}(?<!-))*$/'];
            }
        }

        $rules = array_filter($rules);
        if ($rules) {
            $this->validate($rules, ['regex' => 'Use letters, numbers, hyphens and dots only, for example vps.example.com.'], $attributes);
        }
    }
};
?>

@section('title', $package->name.' Hosting')
@section('description', $package->short_description ?: 'Configure '.$package->name.' with Vaded Hosting. Review included features, billing cycles, and available server options before checkout.')

<div>
@if($this->isVpsCheckout)
    @include('theme::packages.vps-checkout')
@elseif($this->isMinecraftCheckout)
    @include('theme::packages.minecraft-checkout')
@else
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

        @include('theme::packages.order-summary')
    </div>
</section>
@endif
</div>
