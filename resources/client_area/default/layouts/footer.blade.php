@php
    $footerLinks = collect(extensionElements(['footer-item']));
    $legalLinks = $footerLinks->filter(fn ($element) => Str::contains(Str::lower($element['attributes']['name'] ?? ''), ['privacy', 'terms', 'legal']));
@endphp
<footer class="vh-footer">
    <div class="vh-footer-grid">
        <div>
            <a href="{{ route('categories.index') }}" wire:navigate class="vh-footer-brand"><x-theme::brand-logo />{{ settings('app_name', config('app.name')) }}</a>
            <p>Game servers. Cloud workloads.<br>A home for your community.</p>
        </div>
        <div>
            <h2>HOSTING</h2>
            <ul>
                @foreach($hostingCategories as $category)
                    <li><a href="{{ route('categories.index', ['category' => $category->slug]) }}#pricing" wire:navigate>{{ $category->name }}</a></li>
                @endforeach
                <li><a href="{{ route('categories.index') }}#services">All hosting services</a></li>
            </ul>
        </div>
        <div>
            <h2>EXPLORE</h2>
            <ul>
                <li><a href="{{ route('categories.index') }}#infrastructure">Infrastructure</a></li>
                <li><a href="{{ route('categories.index') }}#locations">Deployment locations</a></li>
                <li><a href="{{ route('categories.index') }}#features">Client area tour</a></li>
                <li><a href="{{ route('categories.index') }}#faq">Frequently asked questions</a></li>
                @foreach(config('hosting.resources', []) as $label => $href)
                    <li><a href="{{ $href }}">{{ $label }}</a></li>
                @endforeach
                @foreach($footerLinks->diffKeys($legalLinks) as $element)
                    <li><a href="{{ $element['attributes']['href'] ?? '#' }}">{{ $element['attributes']['name'] ?? 'Resource' }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <h2>YOUR ACCOUNT</h2>
            <ul>
                <li><a href="{{ route('dashboard') }}" wire:navigate>Client dashboard</a></li>
                <li><a href="{{ route('dashboard.payments') }}" wire:navigate>Payments &amp; invoices</a></li>
                <li><a href="{{ route('account.settings') }}" wire:navigate>Account &amp; security</a></li>
                <li><a href="{{ route('cart') }}" wire:navigate>Review your cart</a></li>
            </ul>
        </div>
    </div>
    <div class="vh-footer-bottom">
        <span>&copy; {{ now()->year }} {{ settings('app_name', config('app.name')) }}. All rights reserved.</span>
        <div>
            @foreach(config('hosting.legal', []) as $label => $href)<a href="{{ $href }}">{{ $label }}</a>@endforeach
            @foreach($legalLinks as $element)<a href="{{ $element['attributes']['href'] ?? '#' }}">{{ $element['attributes']['name'] }}</a>@endforeach
            <button type="button" onclick="toggleDarkmode()" aria-label="Toggle light and dark theme">Light / Dark</button>
        </div>
    </div>
</footer>
