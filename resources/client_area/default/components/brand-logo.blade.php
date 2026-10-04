@php
    $logo = settings('app_logo', '/assets/common/img/vaded-app-logo.png');
    $localPath = parse_url($logo, PHP_URL_PATH);
    if (str_starts_with($logo, '/') && ! str_starts_with($logo, '//') && ! is_file(public_path($localPath ?? ''))) {
        $logo = '/assets/common/img/vaded-app-logo.png';
    }
@endphp
<span class="vh-seasonal-logo" data-brand-logo {{ $attributes->only(['data-logo-preview', 'data-logo-static']) }}>
    <img src="{{ $logo }}" alt="" {{ $attributes->except(['data-logo-preview', 'data-logo-static'])->merge(['width' => 32, 'height' => 32]) }} onerror="this.onerror = null; this.src = '/assets/common/img/vaded-app-logo.png'">
    <x-theme::seasonal-artwork :edition="$attributes->get('data-logo-static') === 'true' ? $attributes->get('data-logo-preview') : null" />
</span>
