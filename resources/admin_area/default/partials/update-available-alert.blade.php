@php
    $githubReleases = app(\App\Services\WemxGitHubReleases::class);
    $updateStatus = $githubReleases->getCachedUpdateStatus();
    $showUpdateAlert = $githubReleases->hasUpdateAvailable();
@endphp

@if($showUpdateAlert && $updateStatus)
    <div class="mb-3">
        <x-admin::alerts.action
            variant="info"
            :title="__('Update available')"
            :message="__(
                'A new version of Vaded Hosting is available. You are running <code>:current</code> and <code>:latest</code> is available.',
                [
                    'current' => $updateStatus['installed_version'],
                    'latest' => $updateStatus['latest_tag'] ?? __('unknown'),
                ],
            )"
            :link-text="__('View updates')"
            :link-href="route('admin.updates.index')"
        />
    </div>
@endif
