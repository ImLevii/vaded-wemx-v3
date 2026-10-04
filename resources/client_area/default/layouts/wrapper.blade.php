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
