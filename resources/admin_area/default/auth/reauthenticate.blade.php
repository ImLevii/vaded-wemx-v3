<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <meta name="csrf-token" content="{{ csrf_token() }}"/>
    <title>Re-authenticate | {{ settings('app_name', config('app.name')) }} Admin</title>
    <link rel="icon" href="{{ asset(settings('favicon', '/assets/common/img/vaded-logo.png')) }}">
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

    <script>
        !function(e){"function"==typeof define&&define.amd?define(e):e()}((function(){"use strict";var e,t="tablerTheme",a=new Proxy(new URLSearchParams(window.location.search),{get:function(e,t){return e.get(t)}});if(a.theme)localStorage.setItem(t,a.theme),e=a.theme;else{var n=localStorage.getItem(t);e=n||"light"}"dark"===e?document.body.setAttribute("data-bs-theme",e):document.body.removeAttribute("data-bs-theme")}));
    </script>

    @livewireScripts
</body>
</html>
