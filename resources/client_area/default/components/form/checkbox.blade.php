@props([
    'label' => null,
    'id' => rand(1000, 99999999),
])

<div class="flex min-w-0 items-center gap-3">
    <input id="{{ $id }}" type="checkbox" {{ $attributes->class('w-4 h-4 shrink-0 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600') }}>
    <label for="{{ $id }}" class="inline-flex min-h-11 min-w-0 cursor-pointer items-center text-sm font-medium text-gray-900 dark:text-gray-300">{{ $label ?? $slot }}</label>
</div>
