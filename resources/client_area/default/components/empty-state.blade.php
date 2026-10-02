@props([
    'title',
    'description' => null,
    'icon' => null,
    'actionText' => null,
    'actionHref' => null,
    'actionNavigate' => false,
])

<div {{ $attributes->class('p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700 flex flex-col items-center text-center') }}>
    @if($icon)
        <div class="mb-3">
            {!! $icon !!}
        </div>
    @endif

    <p class="mb-2 text-base font-bold tracking-tight text-gray-900 dark:text-white">
        {{ $title }}
    </p>

    @if($description)
        <p class="mb-3 font-normal text-gray-700 dark:text-gray-400">
            {{ $description }}
        </p>
    @endif

    @if($actionText && $actionHref)
        <a
            href="{{ $actionHref }}"
            @if($actionNavigate) wire:navigate @endif
            class="inline-flex items-center px-3 py-2 text-sm font-medium text-center text-white bg-primary-700 rounded-lg hover:bg-primary-800 focus:ring-4 focus:outline-none focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800"
        >
            {{ $actionText }}
        </a>
    @endif
</div>
