<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-vaded-area="client">

<head>
    <title>{{ settings('app_name', config('app.name')) }}@hasSection('title') | @yield('title')@endif</title>
    <link rel="icon" href="@settings('favicon', '/assets/common/img/vaded-logo.png')">

    {{-- meta tags --}}
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Meta Description Tag: Affects click-through rates from search results -->
    <meta name="description" content="{{ trim($__env->yieldContent('description', 'Game server and cloud hosting. Compare plans, configure your server, and manage your community with Vaded Hosting.')) }}">
    <meta name="theme-color" content="#030405">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">

    <!-- Meta Robots Tag: Controls search engine crawling and indexing -->
    <meta name="robots" content="@settings('seo::robots', 'index, follow')">

    <!-- Open Graph Tags: Enhances visibility and engagement on social media platforms -->
    <meta property="og:title" content="{{ trim($__env->yieldContent('title')) }} - @settings('seo::title', 'Vaded Hosting')">
    <meta property="og:description" content="{{ trim($__env->yieldContent('description', 'Game server and cloud hosting. Compare plans, configure your server, and manage your community with Vaded Hosting.')) }}">
    <meta property="og:image" content="@settings('seo::image', '/assets/common/img/vaded-social.png')">

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
<a href="#main-content" class="vh-skip-link">Skip to content</a>
@include('theme::layouts.header', ['activePage' => $activePage ?? ''])
    <main id="main-content" tabindex="-1" class="vh-client-main flex-1 space-y-4">
        @yield('content')
    </main>
@include('theme::layouts.footer')
@livewireScripts
</body>
</html>
