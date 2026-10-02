@php
    /** @var array{username?: ?string, email?: ?string, error?: ?string} $account */
@endphp

@if(! empty($account['mock']))
    <div class="alert alert-info" role="status">
        <strong>Local mock marketplace</strong> — Sample resources only. No connection to the WemX marketplace.
    </div>
@elseif(! empty($account['error']))
    <div class="alert alert-warning" role="alert">{{ $account['error'] }}</div>
@elseif(! empty($account['username']) || ! empty($account['email']))
    <div class="alert alert-info" role="status">
        Connected as <strong>{{ $account['username'] ?: 'Unknown' }}</strong>
        @if(! empty($account['email']))
            <span class="text-secondary">({{ $account['email'] }})</span>
        @endif
    </div>
@endif
