<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <meta name="csrf-token" content="{{ csrf_token() }}"/>

    <title>Vaded Hosting | Installer</title>
    <link rel="icon" href="{{ asset(settings('favicon', '/assets/common/img/vaded-logo.png')) }}">
    <!-- CSS files -->
    <link href="{{ admin_asset('css/tabler.min.css?1692870487') }}" rel="stylesheet"/>
    <link href="{{ admin_asset('css/tabler-flags.min.css?1692870487') }}" rel="stylesheet">
    <link href="{{ admin_asset('css/tabler-payments.min.css?1692870487') }}" rel="stylesheet"/>
    <link href="{{ admin_asset('css/tabler-vendors.min.css?1692870487') }}" rel="stylesheet"/>
    <link href="{{ admin_asset('css/demo.min.css?1692870487') }}" rel="stylesheet"/>
    <link href="{{ admin_asset('css/admin.css') }}" rel="stylesheet"/>

    <!-- Tabler Core -->
    <script src="{{ admin_asset('js/tabler.min.js?1692870487') }}" defer></script>
    <script src="{{ admin_asset('js/demo.min.js?1692870487') }}" defer></script>
    <link rel="stylesheet" href="{{ admin_asset('libs/tabler-icons/dist/tabler-icons.min.css') }}">

    @livewireStyles
    @yield('styles')
</head>
<body>

<div class="page page-center">
    @livewire('install-wizard')
</div>

<!-- Darkmode -->
<script>
    function isLoading(button) {
        //get button original name
        buttonName = button.innerText;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> ' + buttonName;
        button.disabled = true;
    }
</script>

<script>
    !function(e){"function"==typeof define&&define.amd?define(e):e()}((function(){"use strict";var e,t="tablerTheme",a=new Proxy(new URLSearchParams(window.location.search),{get:function(e,t){return e.get(t)}});if(a.theme)localStorage.setItem(t,a.theme),e=a.theme;else{var n=localStorage.getItem(t);e=n||"light"}"dark"===e?document.body.setAttribute("data-bs-theme",e):document.body.removeAttribute("data-bs-theme")}));
</script>

<!-- Libs JS -->
<script src="{{ admin_asset('libs/tom-select/dist/js/tom-select.base.js') }}" defer></script>

<!-- Tabler Core -->
@yield('scripts')

<!-- Livewire -->
@livewireScripts
@stack('scripts')
</body>
</html>
