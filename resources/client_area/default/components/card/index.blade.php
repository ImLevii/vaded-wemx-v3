@props([

])

<div {{ $attributes->class(["min-w-0 p-4 sm:p-6 bg-white border border-gray-200 rounded-lg shadow dark:bg-gray-800 dark:border-gray-700"])->merge([]) }}>
    {{ $slot }}
</div>
