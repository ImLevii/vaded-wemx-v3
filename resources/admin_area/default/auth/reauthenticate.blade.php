<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-vaded-area="admin">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <meta name="csrf-token" content="{{ csrf_token() }}"/>
    <meta name="wemx-theme-control" content="{{ auth()->user()?->hasPermission('admin.dashboard') ? 'manual' : 'automatic' }}">
    <x-theme::appearance-config />
    <script src="{{ asset('assets/common/js/vaded-theme.js') }}?v={{ filemtime(public_path('assets/common/js/vaded-theme.js')) }}" data-navigate-once></script>
    <title>Re-authenticate | {{ settings('app_name', config('app.name')) }} Admin</title>
    <link rel="icon" href="{{ asset(settings('favicon', '/assets/common/img/vaded-app-logo.png')) }}">
    <link href="{{ admin_asset('css/tabler.min.css?1692870487') }}" rel="stylesheet"/>
    <link href="{{ admin_asset('css/admin.css') }}" rel="stylesheet"/>
    @livewireStyles
</head>
<body class="d-flex flex-column">
    <div class="page page-center">
        <div class="container container-tight py-4">
            @livewire(admin_view_path('auth.livewire.reauthenticate'))
        </div>
    </div>

    @livewireScripts
</body>
</html>
