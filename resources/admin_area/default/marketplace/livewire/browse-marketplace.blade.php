<?php

use App\Helpers\Marketplace;
use Livewire\Volt\Component;

use Illuminate\Support\Facades\Http;

new class extends Component
{
    public int $activeResourceKey = 0;

    public string $searchQuery = '';

    public ?string $categoryFilter = null;

    public string $versionDownloadStatus = '';

    /**
     * Cached marketplace API payload — persisted across Livewire requests so switching resources
     * does not re-hit the remote HTTP API on every click.
     *
     * @var array<string, mixed>|null
     */
    public ?array $marketplacePayload = null;

    public function placeholder()
    {
        return view('admin::marketplace.browse-marketplace-placeholder');
    }

    public function mount(): void
    {
        $this->ensureMarketplaceLoaded();
    }

    public function ensureMarketplaceLoaded(): void
    {
        if ($this->marketplacePayload !== null) {
            return;
        }

        $payload = (new Marketplace)->getResources();

        if (! is_array($payload) || ! isset($payload['data']) || ! is_array($payload['data'])) {
            $this->marketplacePayload = ['data' => [], 'meta' => [], 'links' => []];
        } else {
            $this->marketplacePayload = $payload;
        }
    }

    /**
     * Pull fresh marketplace data (optional call after installs / manual reload later).
     */
    public function reloadMarketplaceFromApi(): void
    {
        $this->marketplacePayload = null;
        $this->ensureMarketplaceLoaded();
    }

    public function installExtension($version_id, $extractPath, $renameExtractTo): void
    {
        $this->versionDownloadStatus = '';
        $this->resetErrorBag();

        $marketplaceApiUrl = Marketplace::API_DOMAIN;

        $zipUrl = $marketplaceApiUrl.'/api/v1/marketplace/resources/download/'.$version_id.'?license_key='.config('app.license_key');
        $tmpZipPath = storage_path("app/marketplace/tmp_zips/tmp_version{$version_id}.zip");
        $tempExtractPath = storage_path("app/marketplace/tmp_extract/version{$version_id}");

        $response = Http::get($zipUrl);

        if (! $response->ok()) {
            $this->addError('download', 'Failed to download the extension. The host may be unreachable or the version does not exist.');

            return;
        }

        if (! is_dir(dirname($tmpZipPath))) {
            mkdir(dirname($tmpZipPath), 0777, true);
        }
        if (! is_dir($tempExtractPath)) {
            mkdir($tempExtractPath, 0777, true);
        }

        file_put_contents($tmpZipPath, $response->body());

        $zip = new ZipArchive;
        if ($zip->open($tmpZipPath) === true) {
            $zip->extractTo($tempExtractPath);
            $zip->close();
        } else {
            $this->addError('extract', 'Failed to extract the extension. The ZIP file may be corrupted.');
            @\File::delete($tmpZipPath);

            return;
        }

        $items = array_values(array_diff(scandir($tempExtractPath), ['.', '..']));
        if (empty($items)) {
            $this->addError('extract', 'The extracted extension is empty. Please check the ZIP file.');
            @\File::delete($tmpZipPath);
            @\File::deleteDirectory($tempExtractPath);

            return;
        }

        $first = $tempExtractPath.DIRECTORY_SEPARATOR.$items[0];
        $sourcePath = is_dir($first) && count($items) === 1 ? $first : $tempExtractPath;

        $destinationBase = rtrim(base_path(trim($extractPath, "/\\")), "/\\");

        if ($renameExtractTo) {
            $destinationPath = $destinationBase.DIRECTORY_SEPARATOR.$renameExtractTo;
        } else {
            $destinationPath = $destinationBase.DIRECTORY_SEPARATOR.basename($sourcePath);
        }

        if (! is_dir(dirname($destinationPath))) {
            mkdir(dirname($destinationPath), 0777, true);
        }

        if (is_dir($destinationPath)) {
            // \File::deleteDirectory($destinationPath);
        }

        if (! \File::copyDirectory($sourcePath, $destinationPath)) {
            $this->addError('install', 'Failed to move the extension to the destination. Please check permissions.');
            @\File::delete($tmpZipPath);
            @\File::deleteDirectory($tempExtractPath);

            return;
        }

        $this->versionDownloadStatus = "Successfully installed extension to {$destinationPath}";

        @\File::delete($tmpZipPath);
        @\File::deleteDirectory($tempExtractPath);

        \App\Models\Extension::discover();
        $this->reloadMarketplaceFromApi();
    }

    #[\Livewire\Attributes\Computed]
    public function categoryFilters(): array
    {
        $this->ensureMarketplaceLoaded();

        $categories = [];
        foreach ($this->marketplacePayload['data'] ?? [] as $resource) {
            $slug = data_get($resource, 'category.slug');
            $name = data_get($resource, 'category.name');
            if (is_string($slug) && $slug !== '' && is_string($name) && $name !== '') {
                $categories[$slug] = $name;
            }
        }
        ksort($categories);

        return $categories;
    }

    #[\Livewire\Attributes\Computed]
    public function filteredResources(): array
    {
        $this->ensureMarketplaceLoaded();

        $needle = mb_strtolower(trim($this->searchQuery));
        $filtered = [];

        foreach ($this->marketplacePayload['data'] ?? [] as $key => $resource) {
            if ($this->categoryFilter !== null && data_get($resource, 'category.slug') !== $this->categoryFilter) {
                continue;
            }

            if ($needle !== '') {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    data_get($resource, 'name'),
                    data_get($resource, 'short_description'),
                    data_get($resource, 'description'),
                    data_get($resource, 'category.name'),
                    data_get($resource, 'user.username'),
                ])));

                if (! str_contains($haystack, $needle)) {
                    continue;
                }
            }

            $filtered[$key] = $resource;
        }

        return $filtered;
    }

    #[\Livewire\Attributes\Computed]
    public function activeResource(): ?array
    {
        $this->ensureMarketplaceLoaded();

        $data = $this->marketplacePayload['data'] ?? [];

        if ($data === []) {
            return null;
        }

        return $data[$this->activeResourceKey] ?? ($data[array_key_first($data)] ?? null);
    }

    public function setCategoryFilter(?string $slug): void
    {
        $this->categoryFilter = $slug;
    }

    /**
     * Updates which resource is shown in the drawer. The drawer is opened immediately in the browser
     * (see the Details button onclick) so users do not wait for this round-trip before it slides out.
     */
    public function selectResource(int $key): void
    {
        $this->activeResourceKey = $key;
    }

    /**
     * @param  array<string, mixed>|null  $resource
     * @return array<string, mixed>|null
     */
    public function firstInstallableVersion(?array $resource): ?array
    {
        if ($resource === null) {
            return null;
        }

        foreach ($resource['versions'] ?? [] as $version) {
            if (! empty($version['integrated_marketplace'])) {
                return $version;
            }
        }

        return null;
    }

    public function installFromDrawerFooter(): void
    {
        $resource = $this->activeResource;
        $version = $this->firstInstallableVersion($resource);

        if ($version === null) {
            return;
        }

        $this->installExtension(
            (string) $version['id'],
            (string) ($version['extract_path'] ?? ''),
            $version['rename_extract_to'] ?? null,
        );
    }
}

?>

<div
    x-data="{ marketplaceDrawerBusy: false }"
    @marketplace-drawer-busy.window="marketplaceDrawerBusy = $event.detail.busy"
>
    <div
        hidden
        x-bind:hidden="! marketplaceDrawerBusy"
        class="position-fixed end-0 bottom-0 p-3 mb-0 shadow rounded d-flex align-items-center gap-2 alert alert-primary py-2 px-3"
        style="z-index: 1090; max-width: min(100vw - 2rem, 320px);"
    >
        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
        <span>Updating…</span>
    </div>

    <div class="mb-4">
        <div class="card">
            <div class="card-body">
                <div class="row align-items-center g-3">
                    <div class="col-lg">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <button
                                type="button"
                                wire:click="setCategoryFilter(null)"
                                class="btn btn-sm {{ $categoryFilter === null ? 'btn-primary' : 'btn-outline-secondary' }}"
                            >
                                All
                            </button>
                            @foreach($this->categoryFilters as $slug => $label)
                                <button
                                    type="button"
                                    wire:click="setCategoryFilter(@js($slug))"
                                    class="btn btn-sm {{ $categoryFilter === $slug ? 'btn-primary' : 'btn-outline-secondary' }}"
                                >
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-lg-auto" style="min-width: min(100%, 280px);">
                        <div class="input-icon">
                            <span class="input-icon-addon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"/><path d="M21 21l-6 -6"/></svg>
                            </span>
                            <input type="search" wire:model.live.debounce.300ms="searchQuery" class="form-control" placeholder="Search name, description, author…" autocomplete="off">
                        </div>
                    </div>
                </div>
                @php $loadedCount = count(($this->marketplacePayload ?? [])['data'] ?? []); @endphp
                @if($loadedCount > 0)
                    <div class="text-secondary small mt-3 mb-0">
                        Showing {{ count($this->filteredResources) }} of {{ $loadedCount }} resources (page {{ (int) data_get($this->marketplacePayload, 'meta.current_page', 1) }}).
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="row">
        @forelse($this->filteredResources as $key => $resource)
            @php
                $versions = $resource['versions'] ?? [];
                $latestVersion = collect($versions)->sortByDesc('created_at')->first();
                $latestLabel = $latestVersion['version'] ?? null;
            @endphp
            <div class="col-12 col-sm-6 col-xl-4 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body d-flex flex-column px-4 py-4">
                        <div class="d-flex align-items-start gap-3 mb-3">
                            <span class="avatar avatar-xl rounded border bg-secondary-lt flex-shrink-0" style="background-image: url('{{ $resource['icon'] }}')" role="img" aria-label="{{ $resource['name'] }}"></span>
                            <div class="flex-fill min-w-0">
                                <div class="d-flex flex-wrap gap-2 mb-1">
                                    @if(! empty(data_get($resource, 'category.name')))
                                        <span class="badge bg-azure-lt">{{ $resource['category']['name'] }}</span>
                                    @endif
                                    <span class="badge {{ ($resource['price'] ?? '') === 'Free' ? 'bg-green-lt' : 'bg-yellow-lt' }}">{{ $resource['price'] ?? '—' }}</span>
                                    @if($latestLabel)
                                        <span class="badge bg-secondary-lt">v{{ $latestLabel }}</span>
                                    @endif
                                </div>
                                <h3 class="card-title mb-1 text-truncate" title="{{ $resource['name'] }}">{{ Str::limit($resource['name'], 48) }}</h3>
                                <p class="text-secondary mb-0 small">{{ Str::limit($resource['short_description'] ?? '', 140) }}</p>
                            </div>
                        </div>
                        <div class="row g-2 text-center small mt-auto pt-3 border-top mx-0 px-2">
                            <div class="col-4">
                                <div class="text-secondary">Views</div>
                                <div class="fw-semibold">{{ $resource['views'] ?? 0 }}</div>
                            </div>
                            <div class="col-4">
                                <div class="text-secondary">Downloads</div>
                                <div class="fw-semibold">{{ $resource['downloads'] ?? 0 }}</div>
                            </div>
                            <div class="col-4">
                                <div class="text-secondary">Purchases</div>
                                <div class="fw-semibold">{{ $resource['purchases'] ?? 0 }}</div>
                            </div>
                        </div>
                        @if($latestVersion && isset($latestVersion['created_at']))
                            <div class="text-secondary small mt-2">
                                Latest release {{ \Carbon\Carbon::parse($latestVersion['created_at'])->diffForHumans() }}
                            </div>
                        @endif
                    </div>
                    <div class="card-footer bg-transparent border-top-0 pt-3 pb-3">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <div class="d-flex align-items-center min-w-0">
                                <span class="avatar avatar-sm me-2 flex-shrink-0" style="background-image: url('{{ data_get($resource, 'user.avatar') }}')"></span>
                                <div class="text-truncate">
                                    <div class="fw-medium text-truncate">{{ data_get($resource, 'user.username', 'Unknown') }}</div>
                                    <div class="text-secondary text-truncate small">Author</div>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="btn btn-primary flex-shrink-0"
                                @click="
                                    $dispatch('marketplace-drawer-busy', { busy: true });
                                    (function () {
                                        var el = document.getElementById('view-resource-drawer');
                                        if (el && window.bootstrap && bootstrap.Offcanvas) {
                                            bootstrap.Offcanvas.getOrCreateInstance(el).show();
                                        }
                                    })();
                                    Promise.resolve($wire.selectResource({{ (int) $key }})).finally(() => {
                                        $dispatch('marketplace-drawer-busy', { busy: false });
                                    });
                                "
                            >
                                Details
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <h3 class="card-title">No resources found</h3>
                        <p class="text-secondary mb-0">Try another search or category, or verify the marketplace API is reachable.</p>
                    </div>
                </div>
            </div>
        @endforelse
    </div>

    @if($this->activeResource)
        @php
            $r = $this->activeResource;
            $drawerVersions = $r['versions'] ?? [];
            $drawerLatest = collect($drawerVersions)->sortByDesc('created_at')->first();
            $footerVersion = $this->firstInstallableVersion($r);
        @endphp
        <div
            class="offcanvas offcanvas-end border-start shadow-lg"
            tabindex="-1"
            id="view-resource-drawer"
            aria-labelledby="view-resource-drawer-label"
            wire:ignore.self
            style="width: min(720px, 100vw);"
        >
            <div class="offcanvas-header border-bottom align-items-start">
                <div class="me-auto pe-2 min-w-0" id="view-resource-drawer-label">
                    <div class="d-flex align-items-start gap-3">
                        <span class="avatar avatar-xl rounded border bg-secondary-lt flex-shrink-0" style="background-image: url('{{ $r['icon'] }}')" role="img" aria-label="{{ $r['name'] }}"></span>
                        <div class="flex-fill min-w-0">
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                @if(! empty(data_get($r, 'category.name')))
                                    <span class="badge bg-azure-lt">{{ $r['category']['name'] }}</span>
                                @endif
                                <span class="badge {{ ($r['price'] ?? '') === 'Free' ? 'bg-green-lt' : 'bg-yellow-lt' }}">{{ $r['price'] ?? '—' }}</span>
                                @if($drawerLatest && isset($drawerLatest['version']))
                                    <span class="badge bg-secondary-lt">Latest v{{ $drawerLatest['version'] }}</span>
                                @endif
                            </div>
                            <h2 class="offcanvas-title mb-1 text-break">{{ $r['name'] }}</h2>
                            <p class="text-secondary mb-0 small">{{ $r['short_description'] ?? '' }}</p>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close mt-1 flex-shrink-0" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body d-flex flex-column p-0 overflow-hidden">
                <div class="flex-grow-1 overflow-auto p-3">
                    @if(! empty($r['source']))
                        <div class="mb-3">
                            <a href="{{ $r['source'] }}" target="_blank" rel="noopener noreferrer" class="text-break">{{ $r['source'] }}</a>
                        </div>
                    @endif
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <div class="card mb-0">
                                <div class="card-body py-3 px-3">
                                    <div class="text-secondary small">Author</div>
                                    <div class="d-flex align-items-center mt-2">
                                        <span class="avatar avatar-sm me-2" style="background-image: url('{{ data_get($r, 'user.avatar') }}')"></span>
                                        <span class="fw-medium">{{ data_get($r, 'user.username', '—') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="card mb-0">
                                <div class="card-body py-3 px-3">
                                    <div class="text-secondary small">Engagement</div>
                                    <div class="d-flex flex-wrap gap-3 mt-2 small">
                                        <span><span class="text-secondary">Views</span> <strong>{{ $r['views'] ?? 0 }}</strong></span>
                                        <span><span class="text-secondary">Downloads</span> <strong>{{ $r['downloads'] ?? 0 }}</strong></span>
                                        <span><span class="text-secondary">Purchases</span> <strong>{{ $r['purchases'] ?? 0 }}</strong></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="card mb-0">
                                <div class="card-body py-3 px-3">
                                    <div class="text-secondary small">Timeline</div>
                                    <div class="small mt-2">
                                        @if(isset($r['created_at']))
                                            <div><span class="text-secondary">Listed</span> <strong>{{ \Carbon\Carbon::parse($r['created_at'])->diffForHumans() }}</strong></div>
                                        @endif
                                        @if($drawerLatest && isset($drawerLatest['created_at']))
                                            <div class="mt-1"><span class="text-secondary">Latest release</span> <strong>{{ \Carbon\Carbon::parse($drawerLatest['created_at'])->diffForHumans() }}</strong></div>
                                        @else
                                            <div class="mt-1 text-secondary">No published versions yet.</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @error('download')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror
                    @error('extract')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror
                    @error('install')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror
                    @if($this->versionDownloadStatus)
                        <div class="alert alert-success">{{ $this->versionDownloadStatus }}</div>
                    @endif

                    <div class="card mb-0">
                        <div class="card-header py-2">
                            <ul class="nav nav-tabs card-header-tabs" data-bs-toggle="tabs" wire:ignore.self>
                                <li class="nav-item">
                                    <a href="#marketplace-tabs-overview" class="nav-link active" data-bs-toggle="tab">Overview</a>
                                </li>
                                <li class="nav-item">
                                    <a href="#marketplace-tabs-versions" class="nav-link" data-bs-toggle="tab">Versions</a>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body pt-3">
                            <div class="tab-content" wire:ignore.self>
                                <div class="tab-pane active show px-1" id="marketplace-tabs-overview">
                                    <div class="markdown" style="max-height: min(50vh, 420px); overflow-y: auto;">
                                        {!! Str::markdown($r['description'] ?? '') !!}
                                    </div>
                                </div>
                                <div class="tab-pane px-1" id="marketplace-tabs-versions">
                                    @forelse($drawerVersions as $version)
                                        <div class="@if(! $loop->last) mb-3 @endif">
                                            <div class="card mb-0 border">
                                                <div class="card-body py-3 px-3">
                                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                                        <div class="min-w-0">
                                                            <h4 class="mb-0 text-break">{{ $version['name'] ?? 'Release' }} <span class="badge bg-secondary-lt ms-1">v{{ $version['version'] ?? '?' }}</span></h4>
                                                            @if(isset($version['created_at']))
                                                                <div class="text-secondary small mt-1">{{ \Carbon\Carbon::parse($version['created_at'])->diffForHumans() }}</div>
                                                            @endif
                                                        </div>
                                                        @if(! empty($version['integrated_marketplace']))
                                                            <span class="badge bg-green-lt flex-shrink-0">In-app install</span>
                                                        @else
                                                            <span class="badge bg-orange-lt flex-shrink-0">External</span>
                                                        @endif
                                                    </div>
                                                    <div class="markdown mb-3 small" style="max-height: 140px; overflow-y: auto;">
                                                        {!! Str::markdown(! empty($version['changelog']) ? $version['changelog'] : '*No changelog provided.*') !!}
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                        <div class="text-secondary small">
                                                            @if(! empty($version['extract_path']))
                                                                Extract: <code class="text-break">{{ $version['extract_path'] }}</code>
                                                            @endif
                                                        </div>
                                                        <div>
                                                            @if(! empty($version['integrated_marketplace']))
                                                                <button
                                                                    type="button"
                                                                    class="btn btn-primary btn-sm"
                                                                    wire:loading.attr="disabled"
                                                                    wire:click="installExtension({{ (int) $version['id'] }}, @js($version['extract_path'] ?? ''), @js($version['rename_extract_to'] ?? null))"
                                                                    wire:confirm="You are installing a third party extension, only install extensions from developers you trust."
                                                                >
                                                                    Install {{ $version['version'] }}
                                                                </button>
                                                            @else
                                                                <a href="{{ $r['view_url'] ?? '#' }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm">View on marketplace</a>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-secondary mb-0">No versions available for this resource.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-top bg-body p-3 mt-auto">
                    <div class="d-flex flex-wrap align-items-center gap-2 justify-content-between">
                        <button type="button" class="btn btn-link link-secondary px-0" data-bs-dismiss="offcanvas">Close</button>
                        <div class="d-flex flex-wrap gap-2 justify-content-end">
                            @if(! empty($r['view_url']))
                                <a href="{{ $r['view_url'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary">Open listing</a>
                            @endif
                            @if($footerVersion)
                                <button type="button" class="btn btn-primary" wire:loading.attr="disabled" wire:click="installFromDrawerFooter" wire:confirm="You are installing a third party extension, only install extensions from developers you trust.">
                                    Quick install v{{ $footerVersion['version'] ?? '' }}
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
