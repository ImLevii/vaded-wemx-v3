@extends('theme::layouts.wrapper', [
    'activePage' => 'categories',
])

@section('title', 'Game Hosting & Services')

@section('content')
    @php
        $isAdmin = auth()->check() && auth()->user()->isAdmin();
        $categories = \App\Models\Category::query()
            ->when($isAdmin, fn ($query) => $query->whereNotIn('status', ['disabled', 'unlisted']))
            ->when(! $isAdmin, fn ($query) => $query->where('status', 'active'))
            ->get();

        $requestedCategorySlug = request()->get('category');
        $requestedCategory = $requestedCategorySlug
            ? \App\Models\Category::where('slug', $requestedCategorySlug)->first()
            : null;
        $canViewRequestedCategory = ! $requestedCategory || match ($requestedCategory->status) {
            'active', 'unlisted' => true,
            'restricted' => $isAdmin,
            default => false,
        };
    @endphp

    <div class="vh-portal mx-auto max-w-screen-xl px-4 2xl:px-0">
        <x-theme::hosting-hero
            class="vh-portal-hero"
            :ambient="true"
            eyebrow="Premium Performance Hosting"
            title="VADED"
            accent="HOSTING"
            description="Hosting for your games, your community, and your next big idea. Explore available services and find the right plan for your project."
            image="assets/common/img/vaded-branded-server-rack.png"
            primary-href="#services"
            primary-label="View all services"
            :primary-navigate="false"
            secondary-href="#pricing"
            secondary-label="Pricing plans"
        />

        <div class="vh-portal-highlights grid grid-cols-1 gap-4 border-y py-5 sm:grid-cols-3" aria-label="Platform highlights">
            <div><span aria-hidden="true">01</span><p><strong>Your services, connected</strong><small>One dashboard. Everything in reach.</small></p></div>
            <div><span aria-hidden="true">02</span><p><strong>Billing made simple</strong><small>Payments and renewals in one place.</small></p></div>
            <div><span aria-hidden="true">03</span><p><strong>Built for your community</strong><small>Invite your team and manage access.</small></p></div>
        </div>

        <section id="services" class="vh-portal-section vh-services-layout" aria-labelledby="services-heading">
            <div class="vh-portal-heading">
                <span class="vh-portal-eyebrow">Built for your next project</span>
                <h2 id="services-heading">All Services</h2>
                <p>Your next project starts here. Choose a service to compare plans and find your fit.</p>
                <a href="#features" class="vh-inline-link mt-5 inline-flex items-center gap-2">Discover the Vaded experience <span aria-hidden="true">&rarr;</span></a>
            </div>

            <div @class(['vh-service-grid grid grid-cols-1 gap-5', 'sm:grid-cols-2' => $categories->count() > 1, 'vh-service-grid-single' => $categories->count() === 1])>
                @forelse($categories as $category)
                    <a href="{{ route('categories.index', ['category' => $category->slug]) }}#pricing"
                        @class(['vh-portal-category group flex flex-col overflow-hidden rounded-xl border', 'is-selected' => request()->get('category') == $category->slug])
                        @if(request()->get('category') == $category->slug) aria-current="true" @endif>
                        <div class="vh-portal-category-art flex items-center justify-center overflow-hidden">
                            <img class="h-32 w-full object-contain transition-transform duration-300 motion-safe:group-hover:scale-105" src="{{ $category->icon() }}" alt="" loading="lazy" width="320" height="128">
                        </div>
                        <div class="vh-category-copy flex flex-1 flex-col gap-3 p-6">
                            <span class="vh-portal-eyebrow">Explore the possibilities</span>
                            <h3>{{ $category->name }}</h3>
                            <p>{{ $category->description ?: 'Find the right plan for your next project. View available options and manage your service from one dashboard.' }}</p>
                            <span class="vh-portal-category-link mt-auto flex items-center justify-between gap-3 pt-3">
                                {{ request()->get('category') == $category->slug ? 'View selected plans' : 'Explore plans' }}
                                <span aria-hidden="true">&rarr;</span>
                            </span>
                        </div>
                    </a>
                @empty
                    <div class="vh-portal-empty col-span-full rounded-xl border p-8 text-center">
                        <h3>New adventures are on the way.</h3>
                        <p>There are no services available just yet. Check back soon for new plans.</p>
                    </div>
                @endforelse
            </div>
        </section>

        <section id="pricing" class="vh-portal-section" aria-label="Hosting plans">
            @if(request()->has('category') && $canViewRequestedCategory)
                @livewire(client_view_path('categories.livewire.product-list'), ['category' => request()->get('category')])
            @elseif(request()->has('category') && ! $canViewRequestedCategory)
                <x-theme::alert.warning text="This category is not available." />
            @else
                <div class="vh-pricing-prompt flex flex-wrap items-center justify-between gap-5 rounded-xl border p-6">
                    <div>
                        <h2>Find your plan.</h2>
                        <p>Select a service above to see its available plans and pricing.</p>
                    </div>
                    @if($categories->isNotEmpty())
                        <a href="#services" class="vh-action vh-action-secondary">Choose a service <span aria-hidden="true">&uarr;</span></a>
                    @endif
                </div>
            @endif
        </section>

        <section id="features" class="vh-portal-section vh-portal-features rounded-2xl border" aria-labelledby="features-heading">
            <div class="vh-portal-heading text-center">
                <span class="vh-portal-eyebrow">The Vaded experience</span>
                <h2 id="features-heading">Why Choose Vaded?</h2>
                <p>From your first order to your next upgrade, keep your hosting organized in one simple dashboard.</p>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach([
                    ['title' => 'Your services, together', 'description' => 'Keep track of your active services and open their details from a single dashboard.', 'icon' => 'M4 4h16v6H4z M4 14h16v6H4z M8 7h.01 M8 17h.01 M12 7h5 M12 17h5'],
                    ['title' => 'Straightforward billing', 'description' => 'Review your payments, download invoices, and see exactly what you are paying for.', 'icon' => 'M6 3h12v18l-3-2-3 2-3-2-3 2V3z M9 7h6 M9 11h6 M9 15h3'],
                    ['title' => 'Flexible renewals', 'description' => 'Manage subscriptions and renewal options as your plans and projects evolve.', 'icon' => 'M20 7v5h-5 M4 17v-5h5 M6 7a7 7 0 0 1 12-1l2 3 M4 15l2 3a7 7 0 0 0 12-1'],
                    ['title' => 'Bring your team', 'description' => 'Invite members to your services and manage access as your community grows.', 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M22 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75'],
                    ['title' => 'Stay in the loop', 'description' => 'Find service emails and account updates together in your email inbox.', 'icon' => 'M3 5h18v14H3z M3 5l9 7 9-7'],
                    ['title' => 'Make it your account', 'description' => 'Update your account details, manage sessions, and set up two-factor authentication.', 'icon' => 'M12 3l8 4v5c0 5-8 9-8 9s-8-4-8-9V7l8-4z M9 12l2 2 4-4'],
                ] as $feature)
                    <article class="vh-portal-feature flex flex-col items-start gap-3 rounded-xl border p-6 sm:p-7">
                        <span class="vh-portal-feature-icon flex h-11 w-11 items-center justify-center rounded-xl">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $feature['icon'] }}" /></svg>
                        </span>
                        <h3>{{ $feature['title'] }}</h3>
                        <p>{{ $feature['description'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="vh-portal-section vh-infrastructure-layout" aria-labelledby="infrastructure-heading">
            <div class="vh-portal-heading">
                <span class="vh-portal-eyebrow">Built for your community</span>
                <h2 id="infrastructure-heading">VADED <span class="vh-gradient-text">INFRASTRUCTURE</span></h2>
                <p>A home for your next project. Explore our hosting services and manage everything from your client dashboard.</p>
                <a href="{{ route('dashboard') }}" wire:navigate class="vh-inline-link mt-6 inline-flex items-center gap-2">Explore your client area <span aria-hidden="true">&rarr;</span></a>
            </div>
            <div class="vh-infrastructure-map mx-auto overflow-hidden rounded-2xl border">
                <img src="{{ asset('assets/common/img/vaded-archive-map.png') }}" alt="" width="1024" height="1024" loading="lazy">
            </div>
        </section>

        <section class="vh-portal-cta flex flex-col items-start gap-6 rounded-2xl border p-8 sm:p-12 lg:flex-row lg:items-center lg:justify-between" aria-labelledby="get-started-heading">
            <div>
            <span class="vh-portal-eyebrow">Let’s build something great</span>
            <h2 id="get-started-heading">Your community starts with you.</h2>
            <p>Make room for your next big idea. We’ll keep everything in one place.</p>
            </div>
            @auth
                <a href="{{ route('dashboard') }}" wire:navigate class="vh-action">Go to your dashboard <span aria-hidden="true">&rarr;</span></a>
            @else
                <a href="{{ route('register') }}" wire:navigate class="vh-action">Create your account <span aria-hidden="true">&rarr;</span></a>
            @endauth
        </section>
    </div>
@endsection
