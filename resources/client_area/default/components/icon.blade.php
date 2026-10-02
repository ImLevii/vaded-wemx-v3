@props(['name' => 'server'])
@php
    $paths = [
        'server' => 'M4 3h16v7H4z M4 14h16v7H4z M7 6.5h.01 M7 17.5h.01 M11 6.5h6 M11 17.5h6',
        'cpu' => 'M6 6h12v12H6z M9 9h6v6H9z M9 2v4 M15 2v4 M9 18v4 M15 18v4 M2 9h4 M2 15h4 M18 9h4 M18 15h4',
        'memory' => 'M3 7h18v10H3z M7 10v4 M11 10v4 M15 10v4 M19 10v4 M6 17v3 M10 17v3 M14 17v3 M18 17v3',
        'storage' => 'M5 4h14l3 12H2L5 4z M2 16v4h20v-4 M6 18h.01 M9 18h.01',
        'network' => 'M12 3v7 M5 14v-4h14v4 M3 14h4v6H3z M10 14h4v6h-4z M17 14h4v6h-4z M10 2h4v4h-4z',
        'shield' => 'M12 2 3 6v6c0 5 9 10 9 10s9-5 9-10V6l-9-4z M8 12l3 3 5-6',
        'pin' => 'M19 10c0 5-7 12-7 12S5 15 5 10a7 7 0 1 1 14 0z M12 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6',
        'users' => 'M9 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M2 21v-3a6 6 0 0 1 12 0v3 M17 4a4 4 0 0 1 0 8 M18 15a5 5 0 0 1 4 5v1',
        'receipt' => 'M5 2h14v20l-4-2-3 2-3-2-4 2V2z M8 7h8 M8 11h8 M8 15h4',
        'sliders' => 'M4 3v8 M4 15v6 M12 3v3 M12 10v11 M20 3v11 M20 18v3 M1 11h6v4H1z M9 6h6v4H9z M17 14h6v4h-6z',
        'console' => 'M3 4h18v16H3z M7 9l3 3-3 3 M13 15h4',
        'cube' => 'm12 2 10 5v10l-10 5-10-5V7l10-5z M2 7l10 5 10-5 M12 12v10 M7 4.5l10 5v4',
        'cloud' => 'M7 18a5 5 0 1 1 0-10 7 7 0 0 1 13 2 4 4 0 0 1 0 8H7z',
        'bot' => 'M5 7h14v13H5z M12 3v4 M9 11h.01 M15 11h.01 M8 16h8 M2 10v7 M22 10v7',
        'arrow' => 'M4 12h16 M14 6l6 6-6 6',
        'check' => 'M5 12l4 4L19 6',
        'search' => 'M10 3a7 7 0 1 0 0 14 7 7 0 0 0 0-14 M15 15l6 6',
    ];
@endphp
<svg {{ $attributes->class('vh-icon') }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['server'] }}" /></svg>
