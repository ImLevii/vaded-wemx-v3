<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-vaded-area="client">

<head>
    <title>{{ settings('app_name', config('app.name')) }}@hasSection('title') | {{ html_entity_decode(trim($__env->yieldContent('title')), ENT_QUOTES, 'UTF-8') }}@endif</title>
    <link rel="icon" href="@settings('favicon', '/assets/common/img/vaded-favicon.svg')">

    {{-- meta tags --}}
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <meta name="theme-color" content="#030405">
    <x-theme::social-meta
        :title="html_entity_decode(trim($__env->yieldContent('title')), ENT_QUOTES, 'UTF-8')"
        :description="html_entity_decode(trim($__env->yieldContent('description')), ENT_QUOTES, 'UTF-8')"
    />

    <meta name="wemx-theme-control" content="{{ auth()->user()?->hasPermission('admin.dashboard') ? 'manual' : 'automatic' }}">
    <x-theme::appearance-config />
    <script src="{{ asset('assets/common/js/vaded-theme.js') }}?v={{ filemtime(public_path('assets/common/js/vaded-theme.js')) }}" data-navigate-once></script>
    <link href="{{ asset('assets/common/css/vaded-theme.css') }}?v={{ filemtime(public_path('assets/common/css/vaded-theme.css')) }}" rel="stylesheet">

    <!-- Custom CSS -->
    @vite(['resources/client_area/default/assets/css/app.css','resources/client_area/default/assets/js/app.js'])

    @yield('header')


</head>

<body class="vaded-theme vaded-client">
    @hasSection('standalone-content')
        @yield('standalone-content')
    @else
    <main class="vh-auth-shell">
        <div class="vh-auth-layout">
            <div class="vh-auth-intro">
                <a href="/" class="vh-brand">
                    <x-theme::brand-logo />
                    {{ settings('app_name', config('app.name')) }}
                </a>
                <span class="vh-eyebrow">Vaded game infrastructure</span>
                <h2 class="vh-auth-headline">Your community.<br><span>Your command center.</span></h2>
                <p>From the first configuration to your next renewal. Keep your hosting under control.</p>
                <x-theme::server-node />
            </div>
            <div class="min-w-0">
                <div class="vh-auth-card">
                    <div class="vh-window-bar">Account access</div>
                    <div class="p-6 space-y-4 md:space-y-6 sm:p-8">
                        @yield('content')
                    </div>
                </div>
                <div class="vh-auth-footer">
                    <span>{{ settings('app_name', config('app.name')) }} &copy; {{ now()->year }}</span>
                    @if(auth()->user()?->hasPermission('admin.dashboard'))
                        <button type="button" class="vh-auth-theme" onclick="toggleDarkmode()" aria-label="Toggle light and dark theme">Light / Dark</button>
                    @endif
                </div>
            </div>
        </div>
    </main>
    @endif
</body>
</html>
