<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Locked;
use App\Models\Category;
use App\Models\Package;
use App\Models\PackagePrice;
use Illuminate\Support\Collection;
use App\Support\MinecraftOrderOptions;
use Livewire\Attributes\Computed;
use Illuminate\Validation\Rule;

new class extends Component
{
    #[Locked]
    public Category|string $category;

    #[Locked]
    public $packages;

    public int $minecraftPlanIndex = 0;

    public int $minecraftThreadIndex = 0;

    public string $minecraftPeriod = '30';

    #[Locked]
    public bool $vpsLayout = false;

    public function mount(string $category, bool $vpsLayout = false): void
    {
        $category = Category::whereSlug($category)->firstOrFail();
        $canViewCategory = match ($category->status) {
            'active', 'unlisted' => true,
            'restricted' => auth()->user()?->isAdmin() ?? false,
            default => false,
        };

        abort_unless($canViewCategory, 404);

        $this->category = $category;
        $this->vpsLayout = $vpsLayout;
        $this->packages = Package::query()
            ->where('category_id', $category->id)
            ->visibleToUser(auth()->user(), includeUnlisted: false)
            ->with(['prices', 'features', 'configOptions'])
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        $prices = $this->minecraftPlans->first()['prices'] ?? collect();
        $this->minecraftPeriod = (string) ($prices->firstWhere('period_in_days', 30)?->period_in_days ?? $prices->first()?->period_in_days ?? 30);
    }

    #[Computed]
    public function minecraftPlans(): Collection
    {
        if ($this->vpsLayout || ! str_contains(strtolower($this->category->slug), 'minecraft')) {
            return collect();
        }

        $this->packages->loadMissing(['prices', 'features', 'configOptions']);

        return $this->packages->map(function (Package $package): array {
            $threads = MinecraftOrderOptions::threads($package);
            $memory = MinecraftOrderOptions::memory($package);
            $prices = $package->prices->where('is_active', true)->sortBy('price')->unique('period_in_days')->sortBy('period_in_days')->values();
            $oneTimeDays = $package->configOptions->firstWhere('key', 'cpu_limit')?->onetime_day_equivalent ?? 365;

            return [
                'package' => $package,
                'memory' => $memory,
                'prices' => $prices,
                'threads' => $threads,
                'preview' => [
                    'name' => $package->name,
                    'memory' => $memory.'GB',
                    'includedThreads' => (int) (($threads[0]['value'] - $threads[0]['additional'] * 100) / 100),
                    'threads' => $threads,
                    'rates' => $prices->map(fn (PackagePrice $price): array => [
                        'period' => (string) $price->period_in_days,
                        'cycle' => $price->cycle(),
                        'totals' => array_map(fn (array $thread): string => price((float) $price->price + $thread['dailyPrice'] * ($price->period_in_days ?: $oneTimeDays)), $threads),
                        'setup' => $price->setup_fee > 0 ? price((float) $price->setup_fee).' one-time setup' : 'No setup fee',
                    ])->all(),
                ],
            ];
        })->filter(fn (array $plan): bool => $plan['memory'] !== null && $plan['prices']->isNotEmpty())
            ->sortBy('memory')->values();
    }

    public function updatedMinecraftPlanIndex(): void
    {
        $this->minecraftThreadIndex = 0;
        $prices = $this->minecraftPlans->get($this->minecraftPlanIndex)['prices'] ?? collect();

        if (! $prices->contains('period_in_days', $this->minecraftPeriod)) {
            $this->minecraftPeriod = (string) ($prices->firstWhere('period_in_days', 30)?->period_in_days ?? $prices->first()?->period_in_days ?? 30);
        }
    }

    public function configureMinecraftServer(): void
    {
        $plans = $this->minecraftPlans;
        $this->validate(['minecraftPlanIndex' => ['required', 'integer', 'min:0', 'max:'.($plans->count() - 1)]]);
        $plan = $plans[$this->minecraftPlanIndex];
        $this->validate([
            'minecraftThreadIndex' => ['required', 'integer', Rule::in(array_keys($plan['threads']))],
            'minecraftPeriod' => ['required', Rule::in($plan['prices']->pluck('period_in_days')->all())],
        ]);

        $package = Package::query()->visibleToUser(auth()->user(), includeUnlisted: false)
            ->where('category_id', $this->category->id)->findOrFail($plan['package']->id);
        $price = $package->prices()->where('is_active', true)->findOrFail($plan['prices']->firstWhere('period_in_days', $this->minecraftPeriod)->id);
        $parameters = ['package' => $package->slug, 'packagePriceId' => $price->id];

        if ($package->configOptions()->where('key', 'cpu_limit')->exists()) {
            $parameters['config_options'] = ['cpu_limit' => $plan['threads'][$this->minecraftThreadIndex]['value']];
        }

        $this->redirect(route('packages.view', $parameters), navigate: true);
    }

    /**
     * @return array{vpsPlans?: Collection, vpsBillingCycles?: Collection, defaultVpsPeriod?: string}
     */
    public function with(): array
    {
        if (! $this->vpsLayout) {
            return [];
        }

        $billingCycles = $this->packages->flatMap->prices->sortBy('period_in_days')->unique('period_in_days')->values();
        $plans = $this->packages->map(function (Package $package): array {
            $resources = [];
            $features = [];

            foreach ($package->features as $feature) {
                $resource = $this->resourceType($feature->description);

                if ($resource !== null) {
                    $resources[$resource][] = $feature->description;
                } else {
                    $features[] = $feature->description;
                }
            }

            return [
                'package' => $package,
                'prices' => $package->prices->sortBy('price')->unique('period_in_days')->values(),
                'resources' => $resources,
                'features' => $features,
            ];
        });

        return [
            'vpsPlans' => $plans,
            'vpsBillingCycles' => $billingCycles,
            'defaultVpsPeriod' => (string) ($billingCycles->firstWhere('period_in_days', 30)?->period_in_days ?? $billingCycles->first()?->period_in_days ?? 'all'),
        ];
    }

    private function resourceType(string $description): ?string
    {
        foreach ([
            'cpu' => '/\b(v?cores?|vcpus?|cpu|processors?)\b/i',
            'memory' => '/\b(ram|ddr[345]|memory)\b/i',
            'storage' => '/\b(storage|ssd|nvme|disk)\b/i',
            'network' => '/\b(bandwidth|traffic|gbps|mbps|transfer)\b/i',
        ] as $resource => $pattern) {
            if (preg_match($pattern, $description)) {
                return $resource;
            }
        }

        return null;
    }
};
?>
<div>
@if($vpsLayout)
    @include('theme::categories.vps-plans')
@else
    @if($this->minecraftPlans->isNotEmpty())
        @include('theme::categories.minecraft-order-slider')
    @endif
<section class="vh-plans" x-data="{ period: 'all', comparison: false, plan: '{{ $packages->count() > 4 ? $packages->first()->id : 'all' }}' }" aria-label="{{ $category->name }} plans">
    @php($billingCycles = $packages->flatMap->prices->sortBy('period_in_days')->unique('period_in_days'))
    <div class="vh-plans-toolbar">
        <h3>{{ $category->name }} plans</h3>
        @if($billingCycles->count() > 1)
            <fieldset class="vh-cycle-picker" @change="plan = 'all'"><legend class="sr-only">Billing cycle</legend>
                <label><input type="radio" value="all" x-model="period" name="catalog-cycle"><span>All cycles</span></label>
                @foreach($billingCycles as $billingCycle)
                    <label><input type="radio" value="{{ $billingCycle->period_in_days }}" x-model="period" name="catalog-cycle"><span>{{ $billingCycle->cycle() }}</span></label>
                @endforeach
            </fieldset>
        @endif
        @if($packages->count() > 1)
            <button class="vh-text-link" type="button" @click="comparison = !comparison" :aria-expanded="comparison" aria-controls="plan-comparison"><x-theme::icon name="sliders" /> Compare features</button>
        @endif
    </div>
    @if($packages->count() > 4)
        <div class="vh-plan-picker" aria-label="Select a plan">
            @foreach($packages as $package)<button type="button" @click="plan = '{{ $package->id }}'; period = 'all'" :aria-pressed="plan === '{{ $package->id }}'">{{ $package->name }}</button>@endforeach
            <button type="button" @click="plan = 'all'" :aria-pressed="plan === 'all'">Show all plans</button>
        </div>
    @endif
    <div class="vh-pricing-grid" :class="{ 'is-focused': plan !== 'all' }">
        @forelse($packages as $package)
            @php($prices = $package->prices->sortBy('price')->unique('period_in_days')->values())
            <article class="vh-pricing-card" wire:key="package-{{ $package->id }}" x-show="(plan === 'all' || plan === '{{ $package->id }}') && (period === 'all' || @js($prices->pluck('period_in_days')->map(fn ($days) => (string) $days)->values()).includes(period))">
                <div class="vh-plan-name"><x-theme::icon name="server" /><span class="vh-kicker">{{ $category->name }}</span></div>
                <h4>{{ $package->name }}</h4>
                <p class="vh-plan-description">{{ $package->short_description ?: 'Configure this plan for your hosting workload.' }}</p>
                @forelse($prices as $planPrice)
                    <div class="vh-plan-rate" x-show="period === '{{ $planPrice->period_in_days }}' || (period === 'all' && {{ $loop->first ? 'true' : 'false' }})" @unless($loop->first) x-cloak @endunless>
                        <strong>{{ price($planPrice->price) }}</strong><span>/ {{ $planPrice->cycle() }}</span>
                        <small>{{ $planPrice->setup_fee > 0 ? price($planPrice->setup_fee).' one-time setup' : 'No setup fee' }}</small>
                    </div>
                @empty
                    <div class="vh-plan-rate"><span>Currently unavailable</span></div>
                @endforelse
                <ul class="vh-plan-features">
                    @forelse($package->features as $feature)
                        <li><x-theme::icon name="check" /><span>{{ $feature->description }}</span></li>
                    @empty
                        <li><x-theme::icon name="sliders" /><span>Review full plan details and available configuration.</span></li>
                    @endforelse
                </ul>
                @foreach($prices as $planPrice)
                    <a href="{{ route('packages.view', ['package' => $package->slug, 'packagePriceId' => $planPrice->id]) }}" wire:navigate class="vh-action" aria-label="Configure {{ $package->name }}, {{ $planPrice->cycle() }}" x-show="period === '{{ $planPrice->period_in_days }}' || (period === 'all' && {{ $loop->first ? 'true' : 'false' }})" @unless($loop->first) x-cloak @endunless>Configure Server <x-theme::icon name="arrow" /></a>
                @endforeach
            </article>
        @empty
            <x-theme::empty-state title="More plans are on the way." description="No plans are currently available for this service. Explore another service or check back soon." action-text="Explore other services" :action-href="route('categories.index').'#services'" />
        @endforelse
    </div>
    @if($packages->count() > 1)
        <div id="plan-comparison" class="vh-comparison" x-show="comparison" x-cloak>
            <h4>Compare what’s included</h4>
            <p>Prices are per billing cycle. Setup fees and configurable extras are shown before checkout.</p>
            <div class="vh-comparison-grid">
                @foreach($packages as $package)
                    @php($prices = $package->prices->sortBy('price')->unique('period_in_days')->values())
                    <article x-show="period === 'all' || @js($prices->pluck('period_in_days')->map(fn ($days) => (string) $days)->values()).includes(period)">
                        <h5>{{ $package->name }}</h5>
                        @foreach($prices as $planPrice)
                            <p x-show="period === '{{ $planPrice->period_in_days }}' || (period === 'all' && {{ $loop->first ? 'true' : 'false' }})">{{ price($planPrice->price) }} / {{ $planPrice->cycle() }}</p>
                        @endforeach
                        <ul>@foreach($package->features as $feature)<li>{{ $feature->description }}</li>@endforeach</ul>
                        @if($prices->isNotEmpty())<a class="vh-text-link" href="{{ route('packages.view', $package->slug) }}" wire:navigate>Full configuration <x-theme::icon name="arrow" /></a>@endif
                    </article>
                @endforeach
            </div>
        </div>
    @endif
</section>
@endif
</div>
