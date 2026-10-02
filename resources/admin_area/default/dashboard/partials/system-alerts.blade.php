@php
    $showPrereleaseAlert = admin_is_prerelease_version();
    $showCronAlert = ! \App\Models\AppTaskLog::isSchedularRunning();
    $showQueueAlert = ! \App\Models\AppTaskLog::isQueueWorkerRunning();
@endphp

@if($showPrereleaseAlert)
    <div class="mb-3">
        <x-admin::alerts.warning
            :title="__('Pre-release version') . ' (' . config('app.version') . ')'"
            :message="__('You are running an alpha or beta release. Do not use this build for production workloads; data loss and breaking changes are possible.')"
        />
    </div>
@endif

@if($showCronAlert)
    <div class="mb-3">
        <x-admin::alerts.danger
            :title="__('Cron jobs are not running')"
            :message="'Last run ' . \App\Models\AppTaskLog::lastSchedularRun() . '. ' . __('If this message persists, please check your server\'s cron job configuration.')"
        />
    </div>
@endif

@if($showQueueAlert)
    <div class="mb-3">
        <x-admin::alerts.danger
            :title="__('Queue worker is not running')"
            :message="__('The queue worker is not running. Please ensure that the queue worker is started to process background jobs.')"
        />
    </div>
@endif
