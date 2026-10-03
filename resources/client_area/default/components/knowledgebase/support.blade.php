@php
    $supportUrl = config('hosting.resources.Contact support');
    $discordUrl = config('hosting.resources.Discord');
@endphp
<div class="vh-kb-support flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex items-start gap-4">
        <span class="vh-kb-category-icon"><x-theme::icon name="users" /></span>
        <div>
            <h2>Still need a hand?</h2>
            <p>Have your service name and relevant console logs ready so we can help you get moving.</p>
        </div>
    </div>
    <div class="flex shrink-0 flex-wrap gap-3">
        @if($discordUrl)<a href="{{ $discordUrl }}" class="vh-kb-button vh-kb-button-secondary">Join our Discord</a>@endif
        <a href="{{ $supportUrl ?: route('dashboard') }}" class="vh-kb-button">{{ $supportUrl ? 'Contact support' : 'Open client area' }} <span aria-hidden="true">&rarr;</span></a>
    </div>
</div>
