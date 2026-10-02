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
            eyebrow="Vaded Hosting / Made for your next chapter"
            title="YOUR WORLD."
            accent="POWERED UP."
            description="A home for your games. A launchpad for your ideas. Find your next server and bring your community together with Vaded."
            image="assets/common/img/vaded-branded-server-rack.png"
            primary-href="#services"
            primary-label="View all services"
            primary-description="Find your next server"
            :primary-navigate="false"
            secondary-href="#features"
            secondary-label="Explore the experience"
            secondary-description="See what makes us different"
        >
            <div class="vh-hero-note flex flex-wrap items-center gap-x-5 gap-y-2">
                <span>One account. All your services.</span>
                <a href="#how-it-works">See how it works <span aria-hidden="true">&darr;</span></a>
            </div>
            <x-slot:artOverlay>
                <span class="vh-hero-art-caption">THE NEXT CHAPTER IS YOURS <span>&#10022;</span></span>
            </x-slot:artOverlay>
        </x-theme::hosting-hero>

        <div class="vh-portal-highlights grid grid-cols-1 gap-4 border-y py-5 sm:grid-cols-3" aria-label="Platform highlights">
            <div><span aria-hidden="true">01</span><p><strong>Your services, connected</strong><small>One dashboard. Everything in reach.</small></p></div>
            <div><span aria-hidden="true">02</span><p><strong>Billing made simple</strong><small>Payments and renewals in one place.</small></p></div>
            <div><span aria-hidden="true">03</span><p><strong>Built for your community</strong><small>Invite your team and manage access.</small></p></div>
        </div>

        <section id="services" class="vh-portal-section vh-services-layout" aria-labelledby="services-heading">
            <div class="vh-portal-heading">
                <span class="vh-portal-eyebrow">Built for your next project</span>
                <h2 id="services-heading">All Services<span class="vh-heading-dot">.</span></h2>
                <p>Your next project starts here. Choose a service to compare plans and find your fit.</p>
                <a href="#features" class="vh-inline-link mt-5 inline-flex items-center gap-2">Discover the Vaded experience <span aria-hidden="true">&rarr;</span></a>
            </div>

            <div @class(['vh-service-grid grid grid-cols-1 gap-5', 'sm:grid-cols-2' => $categories->count() > 1, 'vh-service-grid-single' => $categories->count() === 1])>
                @forelse($categories as $category)
                    <a href="{{ route('categories.index', ['category' => $category->slug]) }}#pricing"
                        @class(['vh-portal-category group flex flex-col overflow-hidden rounded-xl border', 'is-selected' => request()->get('category') == $category->slug])
                        @if(request()->get('category') == $category->slug) aria-current="true" @endif>
                        <div class="vh-portal-category-art flex items-center justify-center overflow-hidden">
                            <img class="h-full w-full object-cover transition-transform duration-300 motion-safe:group-hover:scale-105" src="{{ $category->icon() }}" alt="" loading="lazy" width="320" height="128">
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
            <div class="vh-portal-heading">
                <span class="vh-portal-eyebrow">The Vaded experience</span>
                <h2 id="features-heading">Your hosting. <span class="vh-gradient-text">All connected.</span></h2>
                <p>Less time managing accounts. More time building something worth playing.</p>
            </div>
            <div class="vh-feature-grid grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <article class="vh-portal-feature vh-feature-showcase flex flex-col overflow-hidden rounded-xl border p-6 sm:col-span-2 sm:p-8 lg:row-span-2">
                    <span class="vh-portal-eyebrow">Your command center</span>
                    <h3>Big ideas.<br>One simple dashboard.</h3>
                    <p>Keep your services, subscriptions, and payments together. Go from your next order to your everyday essentials in one place.</p>
                    <a href="{{ route('dashboard') }}" class="vh-inline-link mt-5 inline-flex items-center gap-2">Explore your client area <span aria-hidden="true">&rarr;</span></a>
                    <div class="vh-panel-preview" aria-hidden="true">
                        <div class="vh-preview-bar"><span class="vh-preview-mark">V</span><strong>YOUR WORKSPACE</strong><span class="vh-preview-dots">&bull; &bull; &bull;</span></div>
                        <div class="vh-preview-body">
                            <div class="vh-preview-nav"><span class="is-active">Overview</span><span>Services</span><span>Billing</span><span>Account</span></div>
                            <div class="vh-preview-content">
                                <span class="vh-preview-label">EVERYTHING IN REACH</span>
                                <strong>Ready for your next idea.</strong>
                                <div class="vh-preview-tiles"><span>Services<i></i><i></i></span><span>Payments<i></i><i></i></span></div>
                                <div class="vh-preview-service"><span class="vh-preview-status"></span>Your hosting workspace<span>&rarr;</span></div>
                            </div>
                        </div>
                    </div>
                </article>
                @foreach([
                    ['title' => 'Straightforward billing', 'description' => 'Review your payments, download invoices, and see exactly what you are paying for.', 'icon' => 'M6 3h12v18l-3-2-3 2-3-2-3 2V3z M9 7h6 M9 11h6 M9 15h3'],
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

        <section id="how-it-works" class="vh-portal-section vh-infrastructure-layout" aria-labelledby="infrastructure-heading">
            <div class="vh-portal-heading">
                <span class="vh-portal-eyebrow">Built for your community</span>
                <h2 id="infrastructure-heading">Your next chapter.<br><span class="vh-gradient-text">Starts right here.</span></h2>
                <p>From finding your first plan to managing your growing community, make yourself at home.</p>
                <ol class="vh-start-steps mt-7 flex flex-col gap-5">
                    <li><span>01</span><div><strong>Find your fit</strong><p>Explore services and compare the available plans.</p></div></li>
                    <li><span>02</span><div><strong>Make it yours</strong><p>Choose your plan options and complete checkout.</p></div></li>
                    <li><span>03</span><div><strong>Take control</strong><p>Manage services, billing, and your team in your client area.</p></div></li>
                </ol>
            </div>
            <div class="vh-infrastructure-map mx-auto overflow-hidden rounded-2xl border">
                <img src="{{ asset('assets/common/img/vaded-archive-map.png') }}" alt="" width="1024" height="1024" loading="lazy">
            </div>
        </section>

        <section id="faq" class="vh-portal-section vh-faq-layout" aria-labelledby="faq-heading">
            <div class="vh-portal-heading">
                <span class="vh-portal-eyebrow">Good to know</span>
                <h2 id="faq-heading">A little clarity.<br><span class="vh-gradient-text">Before you begin.</span></h2>
                <p>The essentials for getting started with Vaded.</p>
            </div>
            <div class="vh-faq-list flex flex-col gap-3">
                <details class="vh-faq-item" open>
                    <summary><span>01</span>How do I choose a service?</summary>
                    <p>Choose a service from All Services to see its available plans. Open a plan to review its pricing and configuration options before adding it to your cart.</p>
                </details>
                <details class="vh-faq-item">
                    <summary><span>02</span>Where do I manage my hosting?</summary>
                    <p>Sign in to your client area to view your services and open an order for its details. Your dashboard also brings together payments, subscriptions, and account updates.</p>
                </details>
                <details class="vh-faq-item">
                    <summary><span>03</span>Can I give my team access?</summary>
                    <p>Use the member options on your service to invite teammates and manage their access. Invitations appear in the recipient's client area.</p>
                </details>
                <details class="vh-faq-item">
                    <summary><span>04</span>How do I keep track of renewals?</summary>
                    <p>Your client area includes subscription details, payment history, and balance management. Check your service and subscription pages for the renewal options available to your plan.</p>
                </details>
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
