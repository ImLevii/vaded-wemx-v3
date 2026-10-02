@props([
    'label',
    'description' => null,
    'icon' => 'M4 3h16v18H4zM4 7h16M7 5h.01M10 5h.01M13 5h.01',
])

<svg class="vh-action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}" /></svg>
<span class="vh-action-copy">
    <span class="vh-action-label">{{ $label }}</span>
    @if ($description)
        <span class="vh-action-description">{{ $description }}</span>
    @endif
</span>
<svg class="vh-action-chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m6 4 4 4-4 4" /></svg>
