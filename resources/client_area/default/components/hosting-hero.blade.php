@props([
    'eyebrow' => 'Your hosting command center',
    'title' => 'YOUR HOSTING.',
    'accent' => 'UNDER CONTROL.',
    'description' => 'Manage your servers, services, and billing from one place. Your next project starts here.',
    'primaryHref' => null,
    'primaryLabel' => 'VIEW ALL SERVICES',
    'primaryDescription' => null,
    'primaryNavigate' => true,
    'secondaryHref' => null,
    'secondaryLabel' => 'Learn more',
    'secondaryDescription' => null,
    'image' => 'assets/common/img/vaded-server-rack.png',
    'inlineTitle' => false,
    'ambient' => false,
])

<section {{ $attributes->class(['vh-hosting-hero']) }} aria-label="Hosting overview">
    @if($ambient)
        <div class="vh-hero-ambience pointer-events-none absolute inset-0" aria-hidden="true">
            <span class="vh-hero-aura"></span>
            <span class="vh-hero-grid"></span>
            <span class="vh-hero-embers"></span>
        </div>
    @endif
    <div class="vh-hero-copy">
        <span class="vh-hero-badge">{{ $eyebrow }}</span>
        <h1>{{ $title }}@if($inlineTitle) {{ ' ' }}@else<br>@endif<span>{{ $accent }}</span></h1>
        <p>{{ $description }}</p>
        <div class="vh-hero-actions">
            <a href="{{ $primaryHref ?? route('categories.index') }}" @if($primaryNavigate) wire:navigate @endif class="vh-action vh-action-tile">
                <x-theme::action-content :label="$primaryLabel" :description="$primaryDescription" icon="M4 3h16v7H4zM4 14h16v7H4zM7 6.5h.01M7 17.5h.01M11 6.5h6M11 17.5h6" />
            </a>
            @if($secondaryHref)
                <a href="{{ $secondaryHref }}" class="vh-action vh-action-secondary vh-action-tile">
                    <x-theme::action-content :label="$secondaryLabel" :description="$secondaryDescription" />
                </a>
            @else
                @auth
                    <a href="{{ route('account.settings') }}" wire:navigate class="vh-action vh-action-secondary">Manage account</a>
                @else
                    <a href="{{ route('login') }}" wire:navigate class="vh-action vh-action-secondary">Sign in</a>
                @endauth
            @endif
        </div>
        {{ $slot }}
    </div>
    <div class="vh-hero-art" aria-hidden="true">
        <img src="{{ asset($image) }}" alt="" width="1024" height="1536" fetchpriority="high">
        {{ $artOverlay ?? '' }}
    </div>
</section>
