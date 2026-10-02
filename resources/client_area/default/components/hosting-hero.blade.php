@props([
    'eyebrow' => 'Your hosting command center',
    'title' => 'YOUR HOSTING.',
    'accent' => 'UNDER CONTROL.',
    'description' => 'Manage your servers, services, and billing from one place. Your next project starts here.',
])

<section class="vh-hosting-hero" aria-label="Hosting overview">
    <div class="vh-hero-copy">
        <span class="vh-hero-badge">{{ $eyebrow }}</span>
        <h1>{{ $title }}<br><span>{{ $accent }}</span></h1>
        <p>{{ $description }}</p>
        <div class="vh-hero-actions">
            <a href="{{ route('categories.index') }}" wire:navigate class="vh-action">VIEW ALL SERVICES <span aria-hidden="true">&rarr;</span></a>
            @auth
                <a href="{{ route('account.settings') }}" wire:navigate class="vh-action vh-action-secondary">Manage account</a>
            @else
                <a href="{{ route('login') }}" wire:navigate class="vh-action vh-action-secondary">Sign in</a>
            @endauth
        </div>
    </div>
    <div class="vh-hero-art" aria-hidden="true">
        <img src="{{ asset('assets/common/img/vaded-server-rack.png') }}" alt="" width="1024" height="1536" fetchpriority="high">
    </div>
</section>
