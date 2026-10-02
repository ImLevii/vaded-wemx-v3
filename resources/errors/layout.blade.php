<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
    <title>@yield('title') | {{ config('app.name', 'Vaded Hosting') }}</title>
    <link rel="icon" href="/assets/common/img/vaded-favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/common/css/vaded-theme.css">
    <style>body{margin:0}.vh-error-shell{min-height:100svh;display:grid;place-items:center;padding:32px;box-sizing:border-box}.vh-error-card{max-width:580px;text-align:center}.vh-error-card img{width:48px;height:48px;border-radius:10px;margin-bottom:30px}.vh-error-code{font:12px var(--vh-mono);color:var(--vh-accent);letter-spacing:.15em}.vh-error-card h1{font-size:clamp(32px,5vw,50px);line-height:1.15;letter-spacing:-.04em;margin:22px 0}.vh-error-card p{font-size:15px;line-height:1.8;color:var(--vh-muted);margin:0 0 28px}.vh-error-card .vh-action{padding:12px 24px}</style>
</head>
<body class="vaded-theme"><main class="vh-error-shell"><div class="vh-error-card"><a href="/" aria-label="Vaded Hosting home"><img src="/assets/common/img/vaded-logo.png" alt="Vaded Hosting" width="48" height="48"></a><div class="vh-error-code">VADED / @yield('code')</div><h1>@yield('title')</h1><p>@yield('message')</p><a href="/" class="vh-action">Return to hosting &rarr;</a></div></main></body>
</html>
