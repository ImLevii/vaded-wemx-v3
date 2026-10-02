@extends('theme::layouts.wrapper', ['activePage' => 'categories'])
@section('title', $selectedCategory && request()->has('category') ? $selectedCategory->name.' Plans' : 'Game Server & Cloud Hosting')
@section('description', $selectedCategory?->description ?: 'Game server and cloud hosting for communities, server owners, and developers. Compare plans, configure your resources, and manage everything with Vaded Hosting.')

@section('content')
@if(request()->has('category') && $selectedCategory && Str::contains(Str::lower($selectedCategory->slug), 'vps'))
    @include('theme::categories.vps')
@else
<div class="vh-store">
    <section class="vh-store-hero" aria-labelledby="hosting-title">
        <div class="vh-hero-backdrop" aria-hidden="true">
            <div class="vh-hero-backdrop-glow"></div>
            <img src="{{ asset('assets/common/img/vaded-branded-server-rack.png') }}" alt="" width="1126" height="1397" fetchpriority="high" decoding="async">
            <div class="vh-hero-backdrop-grid"></div>
            <span class="vh-hero-scan"></span>
        </div>
        <div class="vh-store-hero-copy">
            <span class="vh-kicker"><span></span> VADED GAME INFRASTRUCTURE</span>
            <h1 id="hosting-title">YOUR SERVER.<br><em>AT FULL POWER.</em></h1>
            <p class="vh-hero-lead">Game server &amp; cloud hosting.<br>Built for the communities behind the screen.</p>
            <p class="vh-hero-detail">Choose your resources. Configure your server. Keep your hosting, billing, and team in one connected workspace.</p>
            <div class="vh-cta-row"><a class="vh-action" href="#services">Deploy Your Server <x-theme::icon name="arrow" /></a><a class="vh-action vh-action-secondary" href="#pricing">View Hosting Plans</a></div>
            <div class="vh-hero-footnote"><x-theme::icon name="sliders" /> Your plan. Your configuration. Clear pricing.</div>
        </div>
        <div class="vh-hero-signature" aria-hidden="true"><span>VADED / INFRASTRUCTURE</span><strong>BUILT FOR YOUR WORLD.</strong><span class="vh-hero-signature-line"></span></div>
    </section>
    <x-theme::technology-carousel />

    <div class="vh-proof-strip" aria-label="Platform capabilities">
        @foreach(config('hosting.capabilities', []) as $capability)
            <div><x-theme::icon :name="$capability['icon']" /><span><strong>{{ $capability['title'] }}</strong><small>{{ $capability['description'] }}</small></span></div>
        @endforeach
    </div>

    <section id="services" class="vh-section vh-services-section" aria-labelledby="services-heading">
        <div class="vh-section-heading">
            <div>
                <span class="vh-kicker"><span></span> 01 / CHOOSE YOUR SERVER</span>
                <h2 id="services-heading">Built for your <em>kind of hosting.</em></h2>
                <p>Find your platform. Choose your resources. Build your community.</p>
            </div>
            <a class="vh-services-compare" href="#pricing">Compare hosting plans <x-theme::icon name="arrow" /></a>
        </div>
        <div class="vh-service-cards">
            @forelse($hostingCategories as $category)
                @php($startingPrice = $category->packages->flatMap->prices->sortBy('price')->first())
                <a href="{{ route('categories.index', ['category' => $category->slug]) }}#pricing" wire:navigate class="vh-service-card" @if($selectedCategory?->is($category)) aria-current="true" @endif>
                    <div class="vh-service-art">
                        @if($category->icon && ! Str::contains($category->icon, ['placeholder', 'vaded-branded-server-rack', 'vaded-archive-server', 'default.png']))
                            <img src="{{ $category->icon() }}" alt="" width="480" height="240" loading="lazy" decoding="async">
                        @else
                            <x-theme::icon :name="str_contains($category->slug, 'minecraft') ? 'cube' : (str_contains($category->slug, 'bot') ? 'bot' : (str_contains($category->slug, 'vps') ? 'cloud' : 'server'))" />
                        @endif
                        <span class="vh-service-index">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        @if($selectedCategory?->is($category))
                            <span class="vh-service-selected">Selected</span>
                        @endif
                    </div>
                    <div class="vh-service-body"><h3>{{ $category->name }}</h3><p>{{ $category->description ?: 'Choose the resources for your workload and manage your hosting from one place.' }}</p>
                        <div class="vh-service-price">@if($startingPrice)<small>Starting from</small><strong>{{ price($startingPrice->price) }}</strong><span>/ {{ $startingPrice->cycle() }}</span>@else<span>Explore available plans</span>@endif</div>
                        <span class="vh-service-link"><span>View {{ $category->name }} plans</span><span class="vh-service-arrow"><x-theme::icon name="arrow" /></span></span>
                    </div>
                </a>
            @empty
                <x-theme::empty-state title="New services are on the way." description="Hosting plans will appear here as soon as they are available." />
            @endforelse
        </div>
    </section>

    <section id="pricing" class="vh-section vh-plan-section" aria-label="Compare hosting plans">
        <div class="vh-section-heading"><div><span class="vh-kicker">02 / FIND YOUR FIT</span><h2>Resources for your next level.</h2><p>Compare the plans. See what’s included. Configure before you commit.</p></div></div>
        <nav class="vh-catalog-tabs" aria-label="Select hosting service">
            @foreach($hostingCategories as $category)
                <a href="{{ route('categories.index', ['category' => $category->slug]) }}#pricing" wire:navigate @if($selectedCategory?->is($category)) aria-current="page" @endif>{{ $category->name }}</a>
            @endforeach
        </nav>
        @if($selectedCategory)
            @livewire(client_view_path('categories.livewire.product-list'), ['category' => $selectedCategory->slug], key('plans-'.$selectedCategory->id))
        @elseif(request()->has('category'))
            <x-theme::alert.warning text="This category is not available." />
        @else
            <x-theme::empty-state title="Your server starts here." description="Check back for available plans and configuration options." />
        @endif
    </section>

    <section id="infrastructure" class="vh-section" aria-labelledby="hardware-heading">
        <div class="vh-section-heading"><div><span class="vh-kicker">03 / UNDERSTAND THE RESOURCES</span><h2 id="hardware-heading">Power behind the panel.</h2><p>Match your workload to the resources that matter. Exact allocations are listed with each plan.</p></div><a href="#pricing" class="vh-text-link">Compare plans <x-theme::icon name="arrow" /></a></div>
        <div class="vh-spec-grid">
            @forelse(config('hosting.hardware', []) as $spec)
                <article class="vh-spec-card"><x-theme::icon :name="$spec['icon'] ?? 'cpu'" /><span class="vh-kicker">{{ $spec['label'] }}</span><h3>{{ $spec['title'] }}</h3><p>{{ $spec['description'] }}</p></article>
            @empty
                @foreach([
                    ['cpu', 'COMPUTE', 'Keep the world moving.', 'Game loops, mods, and applications put different demands on CPU resources. Choose a plan that suits your workload.'],
                    ['memory', 'MEMORY', 'Room for your community.', 'Allow space for your game, plugins, and concurrent activity. Compare the memory allocations listed in each plan.'],
                    ['storage', 'STORAGE', 'Make space to grow.', 'Worlds, saves, and application data need room. Review included storage and any available extras before checkout.'],
                    ['network', 'NETWORK', 'Bring players together.', 'Location affects connection latency. Check the deployment options available when configuring your service.'],
                ] as [$icon, $label, $title, $description])
                    <article class="vh-spec-card"><x-theme::icon :name="$icon" /><span class="vh-kicker">{{ $label }}</span><h3>{{ $title }}</h3><p>{{ $description }}</p></article>
                @endforeach
            @endforelse
        </div>
    </section>

    <section id="features" class="vh-section vh-control-section" aria-labelledby="control-heading">
        <div><span class="vh-kicker">04 / YOUR COMMAND CENTER</span><h2 id="control-heading">Control everything.<br><em>Keep it simple.</em></h2><p>Manage your services, payments, team, and account from one connected workspace.</p><a href="{{ route('dashboard') }}" wire:navigate class="vh-action vh-action-secondary">Explore the Client Area <x-theme::icon name="arrow" /></a></div>
        <div class="vh-workspace-preview" x-data="{ tab: 'services' }">
            <div class="vh-workspace-bar"><x-theme::brand-logo /><strong>VADED WORKSPACE</strong><span>Feature tour</span></div>
            <div class="vh-workspace-tabs" aria-label="Workspace features">
                @foreach(['services' => 'Services', 'billing' => 'Billing', 'team' => 'Team', 'account' => 'Account'] as $key => $label)
                    <button type="button" @click="tab = '{{ $key }}'" :aria-pressed="tab === '{{ $key }}'">{{ $label }}</button>
                @endforeach
            </div>
            @foreach([
                'services' => ['server', 'Your services, in reach.', 'Search your services, check renewal dates, and open the controls available for your hosting.', 'Open services', route('dashboard')],
                'billing' => ['receipt', 'Every payment, accounted for.', 'Review invoices, track subscriptions, and manage your available account balance.', 'Open billing', route('dashboard.payments')],
                'team' => ['users', 'Build with your community.', 'Invite members to your services and manage their access from your service page.', 'Manage services', route('dashboard')],
                'account' => ['shield', 'Keep your account protected.', 'Update your details, manage your password, and enable two-factor authentication.', 'Account settings', route('account.settings')],
            ] as $key => [$icon, $title, $description, $label, $href])
                <div class="vh-workspace-content" x-show="tab === '{{ $key }}'" @if($key !== 'services') x-cloak @endif>
                    <x-theme::icon :name="$icon" /><h3>{{ $title }}</h3><p>{{ $description }}</p><a href="{{ $href }}" wire:navigate class="vh-text-link">{{ $label }} <x-theme::icon name="arrow" /></a>
                </div>
            @endforeach
        </div>
    </section>

    <section id="locations" class="vh-section vh-network-section" aria-labelledby="locations-heading">
        <div><span class="vh-kicker">05 / CONNECT YOUR COMMUNITY</span><h2 id="locations-heading">Closer to your players.</h2><p>Choose the deployment options available for your plan. Your configuration is shown before checkout.</p><a href="#pricing" class="vh-text-link">See deployment options <x-theme::icon name="arrow" /></a></div>
        <div class="vh-location-list">
            @forelse(config('hosting.locations', []) as $location)
                <article class="vh-location-card"><x-theme::icon name="pin" /><div><h3>{{ $location['city'] }}</h3><p>{{ $location['region'] }}</p>@isset($location['capacity'])<small>{{ $location['capacity'] }}</small>@endisset</div><span>{{ $location['status'] ?? 'See availability' }}</span>@isset($location['test_url'])<a href="{{ $location['test_url'] }}" class="vh-text-link">Test latency</a>@endisset</article>
            @empty
                <div class="vh-network-art" aria-hidden="true"><span>YOUR COMMUNITY</span><i></i><x-theme::icon name="network" /><i></i><span>YOUR SERVER</span></div><p class="vh-location-note">Location availability is specific to each service. Review the options on your chosen plan.</p>
            @endforelse
        </div>
    </section>

    @if(config('hosting.reviews'))
        <section class="vh-section" aria-labelledby="reviews-heading"><span class="vh-kicker">FROM OUR COMMUNITY</span><h2 id="reviews-heading">Built together.</h2><div class="vh-spec-grid">
            @foreach(config('hosting.reviews') as $review)
                <figure class="vh-spec-card"><blockquote>{{ $review['quote'] }}</blockquote><figcaption><strong>{{ $review['name'] }}</strong><span>{{ $review['service'] }}</span>@if($review['verified'] ?? false)<small>Verified customer</small>@endif</figcaption></figure>
            @endforeach
        </div></section>
    @endif

    <section id="faq" class="vh-section vh-faq-section" aria-labelledby="faq-heading" x-data="{ group: 'Hosting' }">
        <div><span class="vh-kicker">BEFORE YOU DEPLOY</span><h2 id="faq-heading">Good questions.<br>Clear answers.</h2><p>Hosting, billing, and account essentials.</p><div class="vh-faq-tabs" aria-label="FAQ categories">@foreach(['Hosting', 'Billing', 'Technical', 'Account'] as $group)<button type="button" @click="group = '{{ $group }}'" :aria-pressed="group === '{{ $group }}'">{{ $group }}</button>@endforeach</div></div>
        <div class="vh-faq-answers">
            @foreach([
                ['Hosting', 'How do I choose a server?', 'Start with your game or workload. Compare the included resources and features, then open a plan to see its billing cycles and configuration options.'],
                ['Hosting', 'What happens after I order?', 'Complete checkout using an available payment method. Follow your service’s status and access its available controls from your dashboard. Provisioning depends on the service.'],
                ['Billing', 'What will I pay?', 'Your plan price and billing cycle appear before checkout. Configuration options, setup fees, discounts, and applicable taxes are shown in the order review.'],
                ['Billing', 'Where are my invoices and renewals?', 'Open Payments & invoices in your client area for payment history. Your service page shows its renewal date and the available renewal or subscription options.'],
                ['Technical', 'Can I choose my resources and location?', 'Available choices depend on the plan. Open Configure Server to review the exact resources, locations, and additional options offered for that service.'],
                ['Technical', 'Where do I manage my game server?', 'Open your service in the client area. The controls and linked panel depend on the hosting service and its integration.'],
                ['Account', 'Can my team help manage services?', 'Yes. Service owners can invite members and manage service access from their service page.'],
                ['Account', 'How can I protect my account?', 'Use a unique password and enable two-factor authentication in your account security settings. Review your active sessions regularly.'],
            ] as [$group, $question, $answer])
                <details class="vh-faq-answer" x-show="group === '{{ $group }}'" @if($group !== 'Hosting') x-cloak @endif><summary>{{ $question }}<span aria-hidden="true">+</span></summary><p>{{ $answer }}</p></details>
            @endforeach
        </div>
    </section>
    <section class="vh-launch-cta"><div><span class="vh-kicker">YOUR COMMUNITY STARTS HERE</span><h2>Ready to launch?</h2><p>Find the server that fits. Make it yours.</p></div><div class="vh-cta-row"><a href="#services" class="vh-action">Choose Your Server <x-theme::icon name="arrow" /></a><a href="#pricing" class="vh-action vh-action-secondary">View Plans</a></div></section>
</div>
@endif
@endsection
