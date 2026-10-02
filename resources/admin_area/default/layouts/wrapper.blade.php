<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-vaded-area="admin">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <meta name="csrf-token" content="{{ csrf_token() }}"/>
    <script src="{{ asset('assets/common/js/vaded-theme.js') }}?v={{ filemtime(public_path('assets/common/js/vaded-theme.js')) }}" data-navigate-once></script>

    <title>@yield('title') | {{ settings('app_name', config('app.name')) }} Admin</title>
    <link rel="icon" href="{{ asset(settings('favicon', '/assets/common/img/vaded-logo.png')) }}">
    <!-- CSS files -->
    <link href="{{ admin_asset('css/tabler.min.css?1692870487') }}" rel="stylesheet"/>
    <link href="{{ admin_asset('css/tabler-flags.min.css?1692870487') }}" rel="stylesheet">
    <link href="{{ admin_asset('css/tabler-payments.min.css?1692870487') }}" rel="stylesheet"/>
    <link href="{{ admin_asset('css/tabler-vendors.min.css?1692870487') }}" rel="stylesheet"/>
    <link href="{{ admin_asset('css/demo.min.css?1692870487') }}" rel="stylesheet"/>
    <link href="{{ asset('assets/common/css/vaded-theme.css') }}?v={{ filemtime(public_path('assets/common/css/vaded-theme.css')) }}" rel="stylesheet"/>
    <link href="{{ admin_asset('css/vaded.css') }}&updated={{ filemtime(public_path('assets/adminarea/'.config('app.theme', 'default').'/css/vaded.css')) }}" rel="stylesheet"/>

    <!-- Tabler Core -->
    <script src="{{ admin_asset('js/tabler.min.js?1692870487') }}" defer></script>
    <script src="{{ admin_asset('js/demo.min.js?1692870487') }}" defer></script>
    <link rel="stylesheet" href="{{ admin_asset('libs/tabler-icons/dist/tabler-icons.min.css') }}">

    @livewireStyles
    @yield('styles')
</head>
<body class="layout-fluid vaded-theme vaded-admin">

<div class="page">

    <x-admin::navigation.sidebar :activePage="$activePage ?? ''"/>

    <div class="page-wrapper">
        <header class="vh-topbar d-print-none">
            <div class="container-xl">
                <span class="vh-workspace-label">Administration</span>
                <x-admin::navigation.navbar />
            </div>
        </header>
        <!-- Page header -->
        <div class="page-header d-print-none">
            <div class="container-xl">
                <div class="row g-2 align-items-center">
                    <div class="col">
                        <!-- Page pre-title -->
                        <div class="page-pretitle">
                            {{ __('messages.overview') }}
                        </div>
                        <h2 class="page-title">
                            @yield('title')
                        </h2>
                    </div>
                    <!-- Page title actions -->
                    @yield('actions')
                </div>
            </div>
        </div>
        <!-- Page body -->
        <div class="page-body">
            <div class="container-xl">
                @include('admin::layouts.alerts')
                @livewire(admin_view_path('livewire.alerts'))
                @yield('content')
            </div>
        </div>
        @livewire(admin_view_path('livewire.toasts'))
        @include('admin::layouts.footer')
    </div>

</div>

@livewire(admin_view_path('layouts.livewire.search-modal'))

<!-- Darkmode -->
<script>
    function isLoading(button) {
        //get button original name
        buttonName = button.innerText;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> ' + buttonName;
        button.disabled = true;
    }
</script>

<!-- Libs JS -->
<script src="{{ admin_asset('libs/apexcharts/dist/apexcharts.min.js') }}" defer></script>
<script src="{{ admin_asset('libs/jsvectormap/dist/js/jsvectormap.min.js') }}" defer></script>
<script src="{{ admin_asset('libs/jsvectormap/dist/maps/world.js') }}" defer></script>
<script src="{{ admin_asset('libs/jsvectormap/dist/maps/world-merc.js') }}" defer></script>
<script src="{{ admin_asset('libs/tom-select/dist/js/tom-select.base.js') }}" defer></script>

<!-- Tabler Core -->
@yield('scripts')

<!-- Livewire -->
@livewireScripts
@stack('scripts')
</body>
</html>
