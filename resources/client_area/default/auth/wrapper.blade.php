<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-vaded-area="client">

<head>
    <title>@yield('title')</title>
    <link rel="icon" href="@settings('favicon', '/assets/core/img/logo.png')">

    {{-- meta tags --}}
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Meta Description Tag: Affects click-through rates from search results -->
    <meta name="description" content="Manage your orders with an easy-to-use Dashboard">
    <meta name="theme-color" content="#030405">
    <meta name="keywords" content="">

    <!-- Meta Robots Tag: Controls search engine crawling and indexing -->
    <meta name="robots" content="@settings('seo::robots', 'index, follow')">

    <!-- Open Graph Tags: Enhances visibility and engagement on social media platforms -->
    <meta property="og:title" content="{{ trim($__env->yieldContent('title')) }} - @settings('seo::title', 'WemX')">
    <meta property="og:description" content="Manage your orders with an easy-to-use Dashboard">
    <meta property="og:image" content="@settings('seo::image', '/static/wemx.png')">

    <script src="{{ asset('assets/common/js/vaded-theme.js') }}?v={{ filemtime(public_path('assets/common/js/vaded-theme.js')) }}" data-navigate-once></script>
    <link href="{{ asset('assets/common/css/vaded-theme.css') }}?v={{ filemtime(public_path('assets/common/css/vaded-theme.css')) }}" rel="stylesheet">

    <!-- Custom CSS -->
    @vite(['resources/client_area/default/assets/css/app.css','resources/client_area/default/assets/js/app.js'])

    @yield('header')


</head>

<body class="vaded-theme vaded-client">
    <main class="vh-auth-shell">
        <div class="vh-auth-layout">
            <div class="vh-auth-intro">
                <a href="/" class="vh-brand">
                    <img src="{{ settings('app_logo', '/assets/common/img/app-logo.png') }}" alt="" width="30" height="30">
                    {{ settings('app_name', 'My Application') }}
                </a>
                <span class="vh-eyebrow">Welcome to your hosting platform</span>
                <h1>Your services.<br><span>Your rules.</span></h1>
                <p>Manage your services, payments, and account from one place. Everything you need, within reach.</p>
                <div class="vh-auth-line" aria-hidden="true"></div>
            </div>
            <div class="min-w-0">
                <div class="vh-auth-card">
                    <div class="vh-window-bar">Account access</div>
                    <div class="p-6 space-y-4 md:space-y-6 sm:p-8">
                        @yield('content')
                    </div>
                </div>
                <div class="vh-auth-footer">
                    <span>{{ settings('app_name', 'WemX') }} &copy; {{ now()->year }}</span>
                    <button type="button" class="vh-auth-theme" onclick="toggleDarkmode()" aria-label="Toggle light and dark theme">Light / Dark</button>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
