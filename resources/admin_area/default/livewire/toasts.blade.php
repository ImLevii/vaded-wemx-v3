<?php

use App\Models\AppTaskLog;
use App\Models\IntegratedMarketplaceInstallation;
use App\Services\WemxGitHubReleases;
use Livewire\Volt\Component;

new class extends Component
{
    public function mount(): void
    {
        if (AppTaskLog::isSchedularRunning()) {
            session()->forget('admin_dismiss_cron_toast');
        }

        if (AppTaskLog::isQueueWorkerRunning()) {
            session()->forget('admin_dismiss_queue_toast');
        }

        if (! admin_is_prerelease_version()) {
            session()->forget('admin_dismiss_prerelease_toast');
        }

        if (! $this->hasGitHubUpdate()) {
            session()->forget('admin_dismiss_update_toast');
        }

        if ($this->marketplaceUpdates()->isEmpty()) {
            session()->forget('admin_dismiss_marketplace_update_toast');
        }
    }

    public function hasGitHubUpdate(): bool
    {
        return app(WemxGitHubReleases::class)->hasUpdateAvailable();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function githubUpdateStatus(): ?array
    {
        return app(WemxGitHubReleases::class)->getCachedUpdateStatus();
    }

    public function isPrereleaseVersion(): bool
    {
        return admin_is_prerelease_version();
    }

    public function closePrereleaseToast(): void
    {
        session()->put('admin_dismiss_prerelease_toast', true);
    }

    public function closeCronToast(): void
    {
        session()->put('admin_dismiss_cron_toast', true);
    }

    public function closeQueueToast(): void
    {
        session()->put('admin_dismiss_queue_toast', true);
    }

    public function closeUpdateToast(): void
    {
        session()->put('admin_dismiss_update_toast', true);
    }

    /**
     * @return \Illuminate\Support\Collection<int, IntegratedMarketplaceInstallation>
     */
    public function marketplaceUpdates()
    {
        if (! auth()->user()?->hasPermission('admin.integrated-marketplace')) {
            return collect();
        }

        return IntegratedMarketplaceInstallation::query()
            ->where('update_available', true)
            ->orderBy('resource_name')
            ->get()
            ->filter(fn (IntegratedMarketplaceInstallation $installation): bool => $installation->isPresent())
            ->values();
    }

    public function marketplaceUpdateSignature(): string
    {
        return $this->marketplaceUpdates()
            ->map(fn (IntegratedMarketplaceInstallation $installation): string => $installation->resource_slug.':'.$installation->latest_version)
            ->implode('|');
    }

    public function closeMarketplaceUpdateToast(): void
    {
        session()->put('admin_dismiss_marketplace_update_toast', $this->marketplaceUpdateSignature());
    }
}

?>


<div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 9999">

    @if($this->isPrereleaseVersion() && ! session('admin_dismiss_prerelease_toast'))
    <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false" data-bs-toggle="toast">
        <div class="toast-header">
            <strong class="me-auto">{{ __('Pre-release version') }}</strong>
            <small>{{ config('app.version') }}</small>
            <button type="button" wire:click="closePrereleaseToast" class="ms-2 btn-close" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            {{ __('You are running an alpha or beta release. Do not use this build for production workloads; data loss and breaking changes are possible.') }}
        </div>
    </div>
    @endif

    @if(! AppTaskLog::isSchedularRunning() && ! session('admin_dismiss_cron_toast'))
    <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false" data-bs-toggle="toast">
        <div class="toast-header">
            <strong class="me-auto">Cron jobs are not running</strong>
            <small>{{ AppTaskLog::lastSchedularRun() }}</small>
            <button type="button" wire:click="closeCronToast" class="ms-2 btn-close" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            Last run {{ AppTaskLog::lastSchedularRun() }}. If this message persists, please check your server's cron job configuration.
        </div>
    </div>
    @endif

    @if($this->hasGitHubUpdate() && ! session('admin_dismiss_update_toast'))
    @php($updateStatus = $this->githubUpdateStatus())
    <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false" data-bs-toggle="toast">
        <div class="toast-header">
            <strong class="me-auto">{{ __('Update available') }}</strong>
            <small>{{ $updateStatus['latest_tag'] ?? '' }}</small>
            <button type="button" wire:click="closeUpdateToast" class="ms-2 btn-close" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            {!! __('A new version of WemX is available. You are running <code>:current</code> and <code>:latest</code> is available.', [
                'current' => $updateStatus['installed_version'] ?? config('app.version'),
                'latest' => $updateStatus['latest_tag'] ?? __('unknown'),
            ]) !!}
            <div class="mt-2 pt-2 border-top">
                <a href="{{ route('admin.updates.index') }}" wire:navigate class="btn btn-primary" style="padding: 6px 12px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-refresh"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4" /><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4" /></svg>
                    {{ __('View updates') }}
                </a>
            </div>
        </div>
    </div>
    @endif

    @php($marketplaceUpdates = $this->marketplaceUpdates())
    @if($marketplaceUpdates->isNotEmpty() && session('admin_dismiss_marketplace_update_toast') !== $this->marketplaceUpdateSignature())
    <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false" data-bs-toggle="toast">
        <div class="toast-header">
            <strong class="me-auto">Marketplace updates</strong>
            <button type="button" wire:click="closeMarketplaceUpdateToast" class="ms-2 btn-close" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            {{ $marketplaceUpdates->count() === 1
                ? 'A resource you installed from the marketplace has an update ready.'
                : $marketplaceUpdates->count().' resources you installed from the marketplace have an update ready.' }}
            <ul class="mb-0 mt-2 ps-3">
                @foreach($marketplaceUpdates as $update)
                    <li>
                        {{ $update->resource_name }}
                        <span class="text-secondary">
                            @if(filled($update->version))
                                version {{ $update->version }} installed
                            @else
                                installed
                            @endif
                            @if($update->latest_version)
                                , version {{ $update->latest_version }} available
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
            <div class="mt-2 pt-2 border-top">
                <a href="{{ route('admin.marketplace.installed') }}" wire:navigate class="btn btn-primary" style="padding: 6px 12px;">
                    View installed resources
                </a>
            </div>
        </div>
    </div>
    @endif

    @if(! AppTaskLog::isQueueWorkerRunning() && ! session('admin_dismiss_queue_toast'))
    <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false" data-bs-toggle="toast">
        <div class="toast-header">
            <strong class="me-auto">Queue worker is not running</strong>
            <small>{{ now()->diffForHumans() }}</small>
            <button type="button" wire:click="closeQueueToast" class="ms-2 btn-close" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            The queue worker is not running. Please ensure that the queue worker is started to process background jobs.
        </div>
    </div>
    @endif

</div>
