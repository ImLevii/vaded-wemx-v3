<?php

use Livewire\Volt\Component;
use Illuminate\View\View;
use App\Models\Category;
use App\Models\Package;

new class extends Component
{
    public $category;

    public $packages;

    public function mount($category)
    {
        $category = Category::whereSlug($category)->firstOrFail();
        $isAdmin = auth()->check() && auth()->user()->isAdmin();
        $canViewCategory = match ($category->status) {
            'active', 'unlisted' => true,
            'restricted' => $isAdmin,
            default => false,
        };

        abort_unless($canViewCategory, 404);

        $this->category = $category;
        $this->packages = Package::query()
            ->where('category_id', $category->id)
            ->visibleToUser(auth()->user(), includeUnlisted: false)
            ->with(['prices', 'features'])
            ->get();
    }
}

?>

<section class="vh-portal-plans antialiased">
    <div class="mx-auto max-w-screen-xl">
        <!-- Heading & Filters -->
        <div class="vh-portal-heading">
            <div>
                <span class="vh-portal-eyebrow">Find your perfect fit</span>
                <h2>{{ $category->name }} plans</h2>
                <p>{{ $category->description }}</p>
            </div>
        </div>

        <div @class(['vh-plan-grid grid grid-cols-1 gap-5', 'max-w-xl' => $packages->count() === 1, 'sm:grid-cols-2' => $packages->count() > 1, 'xl:grid-cols-3' => $packages->count() > 2])>
            @forelse($packages as $package)
            <!-- Pricing Card -->
            <div wire:key="package-{{ $package->id }}" class="vh-portal-plan vh-plan-card flex flex-col rounded-xl border p-7 text-left">
                <img class="mb-5 aspect-video w-full rounded-lg object-cover" src="{{ $package->icon() }}" alt="" loading="lazy" decoding="async" width="640" height="360">
                <div class="vh-plan-heading min-w-0 !pr-0">
                <span class="vh-portal-eyebrow">{{ $category->name }}</span>
                <h3 class="mb-4 text-2xl font-semibold">{{ $package->name }}</h3>
                <p class="text-gray-500 text-light sm:text-lg dark:text-gray-400">{{ Str::limit($package->short_description, 70) }}</p>
                </div>
                <div class="vh-portal-price my-8 flex flex-wrap items-baseline gap-2">
                    <span class="mr-2 text-5xl font-extrabold">{{ price($package->prices->first()->price) }}</span>
                    <span class="text-gray-500">/{{ $package->prices->first()->cycle() }}</span>
                </div>
                <!-- List -->
                <ul role="list" class="mb-8 space-y-4 text-left">
                    @foreach($package->features as $feature)
                    <li class="flex items-center space-x-3">
                        <!-- Icon -->
                        <svg class="flex-shrink-0 w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                        <span>{{ $feature->description }}</span>
                    </li>
                    @endforeach
                </ul>
                <a href="{{ route('packages.view', $package->slug) }}" wire:navigate class="vh-action mt-auto" aria-label="Order {{ $package->name }}">Order now <span aria-hidden="true">&rarr;</span></a>
            </div>
            @empty
                <div class="vh-portal-empty col-span-full rounded-xl border p-8 text-center">
                    <h3>More plans are on the way.</h3>
                    <p>No plans are currently available for this service. Explore another service or check back soon.</p>
                    <a href="{{ route('categories.index') }}#services" class="vh-action vh-action-secondary mt-6">Explore other services</a>
                </div>
            @endforelse
        </div>

    </div>
</section>
