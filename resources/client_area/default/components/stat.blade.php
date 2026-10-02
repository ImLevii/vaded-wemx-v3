@props([
    'icon' => '',
    'title' => '',
    'description' => '',
])

<div class="vh-client-stat rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-6">
    <div class="mb-2 flex h-12 w-12 items-center justify-center rounded-lg bg-primary-600 text-primary-700 dark:bg-primary-900 dark:text-primary-400">
        {!! $icon !!}
    </div>
    <div>
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $title }}</h2>
        <p class="text-gray-500 dark:text-gray-400">{{ $description }}</p>
    </div>
</div>
