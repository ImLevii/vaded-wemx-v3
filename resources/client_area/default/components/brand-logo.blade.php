@php
    $logo = settings('app_logo', '/assets/common/img/vaded-logo.png');
    $localPath = parse_url($logo, PHP_URL_PATH);
    if (str_starts_with($logo, '/') && ! str_starts_with($logo, '//') && ! is_file(public_path($localPath ?? ''))) {
        $logo = '/assets/common/img/vaded-logo.png';
    }
@endphp
<img src="{{ $logo }}" alt="" {{ $attributes->merge(['width' => 32, 'height' => 32]) }} onerror="this.onerror = null; this.src = '/assets/common/img/vaded-logo.png'">
