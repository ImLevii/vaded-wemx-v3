@php
    $showQueueAlert = ! \App\Models\AppTaskLog::isQueueWorkerRunning();
@endphp

@if($showQueueAlert)
    <div class="mb-3">
        <x-admin::alerts.danger
            :title="__('Queue worker is not running')"
            :message="__('The queue worker is not running. Please ensure that the queue worker is started to process background jobs.')"
        />
    </div>
@endif
