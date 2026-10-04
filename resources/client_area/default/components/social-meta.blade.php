@props(['title' => '', 'description' => ''])

@php
    $siteTitle = settings('seo::title', 'Vaded Hosting');
    $socialTitle = trim($title) !== '' ? $title.' - '.$siteTitle : $siteTitle;
    $description = trim($description) !== '' ? $description : 'Game server and cloud hosting. Compare plans, configure your server, and manage your community with Vaded Hosting.';
    $defaultImage = 'assets/common/img/vaded-branded-server-rack.png';
    $configuredImage = trim((string) settings('seo::image', $defaultImage));
    $imagePath = ltrim((string) parse_url($configuredImage, PHP_URL_PATH), '/');
    $imageHost = parse_url($configuredImage, PHP_URL_HOST);
    $isLocalImage = $imageHost === null || in_array(strtolower((string) $imageHost), [strtolower(request()->getHost()), strtolower((string) parse_url(config('app.url'), PHP_URL_HOST))], true);
    if ($configuredImage === '' || ($isLocalImage && in_array($imagePath, ['assets/common/img/vaded-social.png', 'assets/common/img/vaded-social.svg'], true))) {
        $configuredImage = $defaultImage;
    }
    $isDefaultImage = $configuredImage === $defaultImage || ($isLocalImage && $imagePath === $defaultImage);
    $imageUrl = str_starts_with($configuredImage, '//') ? request()->getScheme().':'.$configuredImage : asset($configuredImage);
    $imageAlt = $isDefaultImage ? 'Vaded Hosting server rack with red lighting and the Vaded Hosting logo' : $siteTitle.' preview image';
@endphp

<meta name="description" content="{{ $description }}">
<meta name="robots" content="{{ settings('seo::robots', 'index, follow') }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $siteTitle }}">
<meta property="og:title" content="{{ $socialTitle }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ request()->fullUrl() }}">
<meta property="og:image" content="{{ $imageUrl }}">
@if($isDefaultImage)
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1126">
    <meta property="og:image:height" content="1397">
@endif
<meta property="og:image:alt" content="{{ $imageAlt }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $socialTitle }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $imageUrl }}">
<meta name="twitter:image:alt" content="{{ $imageAlt }}">
