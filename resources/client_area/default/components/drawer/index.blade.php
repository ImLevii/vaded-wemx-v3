@props([

])

<div {{ $attributes->merge(['class' => 'vh-drawer fixed top-0 right-0 z-50 h-dvh max-w-full p-4 overflow-y-auto overscroll-contain transition-transform translate-x-full bg-white w-80 dark:bg-gray-800', 'tabindex' => '-1']) }}>{{ $slot }}</div>
