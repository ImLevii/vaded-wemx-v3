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

    <!-- Livewire Styles -->
    @livewireStyles

    <!-- Custom JS -->
    @yield('header')


</head>

<body class="vaded-theme vaded-client min-h-screen antialiased flex flex-col">
@include('theme::layouts.header', ['activePage' => $activePage ?? ''])
    <main class="vh-client-main flex-1 space-y-4">
        @yield('content')
    </main>
@include('theme::layouts.footer')
<script>
    document.addEventListener('livewire:navigated', function () {
        initFlowbite();

        const nav = document.getElementById('client-main-navigation');
        const toggle = document.getElementById('client-nav-toggle');
        if (
            nav &&
            toggle &&
            window.matchMedia('(max-width: 1023px)').matches &&
            !nav.classList.contains('hidden')
        ) {
            toggle.click();
        }
    });
</script>
@livewireScripts
</body>
</html>
