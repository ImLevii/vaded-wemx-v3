@props(['containerClass' => ''])

<div class="vh-table-scroll relative min-w-0 max-w-full overflow-x-auto overscroll-x-contain shadow-md rounded-t-lg {{ $containerClass }}" tabindex="0" role="region" aria-label="Scrollable table">
    <table {{ $attributes->merge(['class' => 'w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400']) }}>
        {{ $slot }}
    </table>
</div>
