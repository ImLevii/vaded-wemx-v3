<?php

use App\Models\AppTaskLog;
use App\Models\IntegratedMarketplaceInstallation;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    public function mount(): void
    {
        if (AppTaskLog::isQueueWorkerRunning()) {
            session()->forget('admin_dismiss_queue_toast');
        }
    }

    public function closeQueueToast(): void
    {
        session()->put('admin_dismiss_queue_toast', true);
    }

    /**
     * @return Collection<int, IntegratedMarketplaceInstallation>
     */
    #[Computed]
    public function marketplaceUpdates(): Collection
    {
        if (! config('services.marketplace.enabled') || ! auth()->user()?->hasPermission('admin.marketplace.index')) {
            return new Collection;
        }

        return IntegratedMarketplaceInstallation::query()
            ->where('update_available', true)
            ->orderBy('resource_name')
            ->get()
            ->filter(fn (IntegratedMarketplaceInstallation $installation): bool => $installation->isPresent());
    }
}

?>


<div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 9999">

    @if($this->marketplaceUpdates->isNotEmpty())
        <div class="toast show" role="alert" aria-live="polite" aria-atomic="true" data-bs-autohide="false">
            <div class="toast-header">
                <strong class="me-auto">Marketplace updates available</strong>
            </div>
            <div class="toast-body">
                {{ trans_choice(':count resource you installed from the marketplace has an update ready.|:count resources you installed from the marketplace have an update ready.', $this->marketplaceUpdates->count()) }}
                <ul class="mt-2">
                    @foreach($this->marketplaceUpdates as $installation)
                        <li>{{ $installation->resource_name }}: version {{ $installation->version }} installed, version {{ $installation->latest_version }} available</li>
                    @endforeach
                </ul>
                <a class="btn btn-primary" href="{{ route('admin.marketplace.installed') }}" wire:navigate>View installed resources</a>
            </div>
        </div>
    @endif

    @if(false)
    <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="false" data-bs-toggle="toast">
        <div class="toast-header">
            <strong class="me-auto">Update Available</strong>
            <small>11 mins ago</small>
        </div>
        <div class="toast-body">
            There is a new version of Vaded Hosting available for download.
            <div class="mt-2 pt-2 border-top">
                <button type="button" class="btn btn-primary" style="padding: 6px 12px;">
                    <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-refresh"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4" /><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4" /></svg>
                    Update
                </button>
                <button type="button" class="btn btn-secondary"  style="padding: 6px 12px;">Learn More</button>
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
            <div class="mt-2 pt-2 border-top">
                <button type="button" class="btn btn-primary"  style="padding: 6px 12px;">
                    <svg  xmlns="http://www.w3.org/2000/svg"  width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-external-link"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 6h-6a2 2 0 0 0 -2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-6" /><path d="M11 13l9 -9" /><path d="M15 4h5v5" /></svg>
                    Learn More
                </button>
            </div>
        </div>
    </div>
    @endif

</div>
