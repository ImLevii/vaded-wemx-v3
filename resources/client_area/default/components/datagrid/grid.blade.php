@props([
    'cols' => 3,   // number of columns
    'gap'  => 4,   // Tailwind gap size
])

@php
    $colsClass = [
        1 => 'lg:grid-cols-1',
        2 => 'lg:grid-cols-2',
        3 => 'lg:grid-cols-3',
        4 => 'lg:grid-cols-4',
        5 => 'lg:grid-cols-5',
        6 => 'lg:grid-cols-6',
        7 => 'lg:grid-cols-7',
        8 => 'lg:grid-cols-8',
        9 => 'lg:grid-cols-9',
        10 => 'lg:grid-cols-10',
        11 => 'lg:grid-cols-11',
        12 => 'lg:grid-cols-12',
    ][$cols] ?? 'lg:grid-cols-3';
    $tabletColsClass = (int) $cols === 1 ? 'md:grid-cols-1' : 'md:grid-cols-2';
    $gapClass  = 'gap-' . $gap;
@endphp

<div {{ $attributes->merge([
    'class' => "mt-4 mb-4 min-w-0 grid grid-cols-1 {$gapClass} {$tabletColsClass} {$colsClass}"
]) }}>
    {{ $slot }}
</div>
