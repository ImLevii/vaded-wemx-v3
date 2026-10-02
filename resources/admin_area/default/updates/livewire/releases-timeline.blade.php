<?php

use App\Services\WemxGitHubReleases;
use App\Services\WemxReleaseInstaller;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    public ?string $loadError = null;

    public ?string $fetchedAt = null;

    public ?string $installStatus = null;

    public ?int $installingReleaseId = null;

    /** @var list<array<string, mixed>> */
    public array $releases = [];

    /** @var array<string, mixed> */
    public array $status = [];

    public bool $understandsInstallRisks = false;

    public ?int $selectedReleaseId = null;

    public function placeholder()
    {
        return view('admin::updates.releases-timeline-placeholder');
    }

    public function mount(WemxGitHubReleases $githubReleases): void
    {
        $this->loadReleases($githubReleases);
    }

    public function refreshReleases(WemxGitHubReleases $githubReleases): void
    {
        $this->loadReleases($githubReleases, forceRefresh: true);
        $this->dispatch('alert', 'success', 'Release data refreshed from GitHub.');
    }

    public function openInstallModal(int $releaseId): void
    {
        $release = app(WemxGitHubReleases::class)->findReleaseById($this->releases, $releaseId);

        if ($release === null) {
            return;
        }

        $this->selectedReleaseId = $releaseId;
        $this->understandsInstallRisks = false;
        $this->resetErrorBag();

        $this->dispatch(
            'show-install-release-modal',
            tag: (string) ($release['tag_name'] ?? ''),
            build: (string) ($release['app_build_asset']['name'] ?? 'WemX build'),
            prerelease: (bool) ($release['prerelease'] ?? false),
        );
    }

    public function closeInstallModal(): void
    {
        if ($this->installingReleaseId !== null) {
            return;
        }

        $this->resetInstallModalState();
        $this->dispatch('close-install-release-modal');
    }

    #[On('install-release-modal-closed')]
    public function resetInstallModalState(): void
    {
        $this->selectedReleaseId = null;
        $this->understandsInstallRisks = false;
    }

    public function confirmInstallRelease(
        bool $understandsRisks,
        WemxGitHubReleases $githubReleases,
        WemxReleaseInstaller $installer,
    ): void {
        $this->understandsInstallRisks = $understandsRisks;

        if (! $understandsRisks) {
            $this->dispatch('install-release-failed', message: 'Please confirm you understand the risks before installing.');

            return;
        }

        if ($this->selectedReleaseId === null) {
            $this->addError('install', 'No release selected. Close the dialog and try again.');

            return;
        }

        $this->installRelease($this->selectedReleaseId, $githubReleases, $installer);

        if ($this->installingReleaseId === null && $this->installStatus !== null) {
            $this->closeInstallModal();
        }
    }

    public function installRelease(
        int $releaseId,
        WemxGitHubReleases $githubReleases,
        WemxReleaseInstaller $installer,
    ): void {
        $this->installStatus = null;
        $this->installingReleaseId = $releaseId;

        $release = $githubReleases->findReleaseById($this->releases, $releaseId);

        if ($release === null) {
            $this->dispatch('install-release-failed', message: 'Release not found. Refresh the page and try again.');
            $this->installingReleaseId = null;

            return;
        }

        $result = $installer->install($release);

        $this->installingReleaseId = null;

        if (! $result['success']) {
            $this->dispatch('install-release-failed', message: $result['message']);

            return;
        }

        $this->installStatus = $result['message'];
        $this->loadReleases($githubReleases, forceRefresh: true);
        $this->dispatch('alert', 'success', $result['message']);
    }

    protected function loadReleases(WemxGitHubReleases $githubReleases, bool $forceRefresh = false): void
    {
        $payload = $githubReleases->getPayload($forceRefresh);

        $this->loadError = $payload['error'];
        $this->fetchedAt = $payload['fetched_at'];
        $this->releases = $githubReleases->enrichReleases($payload['releases']);
        $this->status = $githubReleases->buildStatus($this->releases);
    }

    public function canInstallRelease(array $release): bool
    {
        return app(WemxGitHubReleases::class)->primaryAppBuildAsset($release) !== null;
    }

    public function isInstalledRelease(array $release): bool
    {
        $matched = $this->status['matched_release'] ?? null;

        if (! is_array($matched)) {
            return $this->normalizeVersion((string) ($release['tag_name'] ?? ''))
                === $this->normalizeVersion((string) ($this->status['installed_version'] ?? ''));
        }

        return (int) ($release['id'] ?? 0) === (int) ($matched['id'] ?? 0);
    }

    public function isLatestRelease(array $release): bool
    {
        $latestTag = $this->status['latest_tag'] ?? null;

        if ($latestTag === null) {
            return false;
        }

        return $this->normalizeVersion((string) ($release['tag_name'] ?? ''))
            === $this->normalizeVersion((string) $latestTag);
    }

    public function releaseIconClass(array $release): string
    {
        if ($this->isInstalledRelease($release)) {
            return 'bg-green-lt';
        }

        if ($this->isLatestRelease($release)) {
            return 'bg-azure-lt';
        }

        if (! empty($release['prerelease'])) {
            return 'bg-orange-lt';
        }

        return 'bg-secondary-lt';
    }

    public function formatAssetSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / 1048576, 1).' MB';
    }

    protected function normalizeVersion(string $version): string
    {
        return strtolower(ltrim(trim($version), 'vV'));
    }
}

?>

<div>
    @if ($loadError)
        <x-admin::alerts.danger
            title="Could not load releases"
            :message="$loadError"
        />
    @endif

    @if (! $loadError && ($status['update_available'] ?? false))
        <div class="mb-3">
            <x-admin::alerts.info
                title="A newer release is available"
                :message="'You are on ' . ($status['installed_version'] ?? 'unknown') . '. The latest published release is ' . ($status['latest_tag'] ?? 'unknown') . '. Review the changelog below before upgrading.'"
            />
        </div>
    @endif

    @if ($status['is_prerelease_channel'] ?? false)
        <div class="mb-3">
            <x-admin::alerts.warning
                :title="__('Pre-release version') . ' (' . ($status['installed_version'] ?? '') . ')'"
                :message="__('You are running an alpha or beta channel build. Production deployments should use a stable tagged release.')"
            />
        </div>
    @endif

    @if ($installStatus)
        <div class="mb-3">
            <x-admin::alerts.info title="Installation complete" :message="$installStatus"/>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-secondary mb-1">Installed version</div>
                    <div class="h2 mb-1">{{ $status['installed_version'] ?? 'Unknown' }}</div>
                    @if (! empty($status['matched_release']['tag_name']))
                        <div class="text-secondary small">
                            Matched release:
                            <a href="{{ $status['matched_release']['html_url'] }}" target="_blank" rel="noopener noreferrer" class="text-reset">
                                {{ $status['matched_release']['tag_name'] }}
                            </a>
                        </div>
                    @else
                        <div class="text-secondary small">No matching GitHub release tag found for this build.</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-secondary mb-1">Latest release</div>
                    <div class="h2 mb-1">{{ $status['latest_tag'] ?? '—' }}</div>
                    @if (! empty($releases[0]['published_at_human']))
                        <div class="text-secondary small">Published {{ $releases[0]['published_at_human'] }}</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-secondary mb-1">Update status</div>
                    <div class="h2 mb-1">
                        @if ($status['update_available'] ?? false)
                            <span class="badge bg-orange-lt">Update available</span>
                        @elseif (! $loadError)
                            <span class="badge bg-green-lt">Up to date</span>
                        @else
                            <span class="badge bg-secondary-lt">Unknown</span>
                        @endif
                    </div>
                    <div class="text-secondary small">
                        Repository: <a href="{{ WemxGitHubReleases::RELEASES_PAGE_URL }}" target="_blank" rel="noopener noreferrer">{{ WemxGitHubReleases::REPOSITORY }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Release timeline</h3>
            <div class="card-actions btn-list">
                @if ($fetchedAt)
                    <span class="text-secondary small align-self-center">
                        Cached {{ \Carbon\Carbon::parse($fetchedAt)->diffForHumans() }}
                    </span>
                @endif
                <button
                    type="button"
                    class="btn btn-outline-secondary btn-sm"
                    wire:click="refreshReleases"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="refreshReleases">Refresh</span>
                    <span wire:loading wire:target="refreshReleases">Refreshing…</span>
                </button>
            </div>
        </div>
        <div class="card-body">
            <ul class="timeline timeline-simple">
                @forelse ($releases as $release)
                    <li class="timeline-event" wire:key="release-{{ $release['id'] }}">
                        <div class="timeline-event-icon {{ $this->releaseIconClass($release) }}">
                            <x-admin::icon icon="package" outline class="icon icon-1"/>
                        </div>
                        <div class="card timeline-event-card @if($this->isInstalledRelease($release)) border border-success @endif">
                            <div class="card-body">
                                <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                                    <div>
                                        <h4 class="mb-1">
                                            {{ $release['name'] }}
                                            <code class="ms-1">{{ $release['tag_name'] }}</code>
                                        </h4>
                                        <div class="text-secondary small">
                                            @if (! empty($release['published_at_formatted']))
                                                {{ $release['published_at_formatted'] }}
                                                @if (! empty($release['published_at_human']))
                                                    ({{ $release['published_at_human'] }})
                                                @endif
                                            @endif
                                            @if (! empty($release['author_login']))
                                                · by {{ $release['author_login'] }}
                                            @endif
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-1 align-items-start">
                                        @if ($this->isInstalledRelease($release))
                                            <span class="badge bg-green-lt">Your version</span>
                                        @endif
                                        @if ($this->isLatestRelease($release))
                                            <span class="badge bg-azure-lt">Latest</span>
                                        @endif
                                        @if (! empty($release['prerelease']))
                                            <span class="badge bg-orange-lt">Pre-release</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="mb-3">
                                    @if (trim((string) ($release['body'] ?? '')) !== '')
                                        <div class="markdown small" style="max-height: min(40vh, 320px); overflow-y: auto;">
                                            {!! Str::markdown($release['body']) !!}
                                        </div>
                                    @else
                                        <p class="text-secondary mb-0 small">No changelog provided for this release.</p>
                                    @endif
                                </div>

                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    @if ($this->canInstallRelease($release))
                                        @if ($this->isInstalledRelease($release))
                                            <button type="button" class="btn btn-sm btn-success" disabled>
                                                Installed
                                            </button>
                                        @else
                                            <button
                                                type="button"
                                                class="btn btn btn-primary"
                                                wire:click="openInstallModal({{ (int) $release['id'] }})"
                                            >
                                                Install version {{ $release['tag_name'] }}
                                            </button>
                                        @endif
                                    @else
                                        <span class="text-secondary small">No in-app installable build for this release.</span>
                                    @endif
                                    @if (! empty($release['html_url']))
                                        <a href="{{ $release['html_url'] }}" target="_blank" rel="noopener noreferrer" class="btn btn btn-outline-secondary">
                                            View on GitHub
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </li>
                @empty
                    <li class="text-secondary">
                        @if ($loadError)
                            No releases to display.
                        @else
                            No published releases found for this repository yet.
                        @endif
                    </li>
                @endforelse
            </ul>
        </div>
    </div>

    <div
        class="modal modal-blur fade"
        id="installReleaseModal"
        tabindex="-1"
        aria-hidden="true"
        data-bs-backdrop="static"
        data-bs-keyboard="false"
        wire:ignore.self
        x-data="{
            tag: '',
            build: '',
            prerelease: false,
            understands: false,
            error: '',
            installing: false,
        }"
    >
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0" x-show="!installing">
                    <div>
                        <h5 class="modal-title mb-1">Install release</h5>
                        <div class="text-secondary" x-text="tag"></div>
                    </div>
                    <button type="button" class="btn-close" @click="$wire.closeInstallModal()" aria-label="Close"></button>
                </div>

                <div class="modal-body py-5" x-show="installing" x-cloak>
                    <div class="text-center">
                        <div class="text-secondary mb-3">Installing Update</div>
                        <div class="progress progress-sm">
                            <div class="progress-bar progress-bar-indeterminate"></div>
                        </div>
                    </div>
                    <p class="text-secondary small text-center mt-4 mb-0">
                        Do not refresh or close this page until the update is finished. Installation can take up to a minute.
                    </p>
                </div>

                <div class="modal-body pt-2" id="installReleaseModalBody" x-show="!installing">
                    <p class="text-secondary mb-3">
                        Downloads <code x-text="build"></code> and applies it to your project root.
                        <span x-show="prerelease" class="badge bg-orange-lt ms-1">Pre-release</span>
                    </p>
                    <div class="row g-2 text-secondary small">
                        <div class="col-sm-6">
                            <div class="border rounded-3 p-3 h-100 bg-secondary-lt">
                                <div class="fw-semibold text-body mb-2">Will update</div>
                                <ul class="mb-0 ps-3">
                                    <li>Application code &amp; assets</li>
                                    <li>Vendor &amp; config from the build</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="border rounded-3 p-3 h-100">
                                <div class="fw-semibold text-body mb-2">Will not touch</div>
                                <ul class="mb-0 ps-3">
                                    <li><code>.env</code></li>
                                    <li><code>storage/</code></li>
                                    <li><code>database/database.sqlite</code></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <p class="text-secondary small mt-3 mb-0">
                        Back up your site first. Keep this tab open during install. Run migrations afterward if the release requires them.
                    </p>
                    <p class="text-danger small mt-2 mb-0" x-show="error" x-text="error"></p>
                </div>
                <div class="modal-footer border-0 pt-0 flex-column align-items-stretch" x-show="!installing">
                    <label class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" x-model="understands">
                        <span class="form-check-label text-secondary">
                            I have a backup and understand this will overwrite application files in the project root.
                        </span>
                    </label>
                    <div class="d-flex w-100 gap-2">
                        <button type="button" class="btn me-auto" @click="$wire.closeInstallModal()">Cancel</button>
                        <button
                            type="button"
                            class="btn btn-primary"
                            :disabled="!understands"
                            @click="
                                error = '';
                                installing = true;
                                $wire.confirmInstallRelease(understands);
                            "
                        >
                            Install now
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @once
        <script>
            function wemxBindInstallReleaseModal() {
                const modalElement = document.getElementById('installReleaseModal');

                if (!modalElement || modalElement.dataset.wemxBound === '1') {
                    return;
                }

                modalElement.dataset.wemxBound = '1';

                let modalInstance = null;

                const modal = () => {
                    if (!modalInstance) {
                        modalInstance = new bootstrap.Modal(modalElement, {
                            backdrop: 'static',
                            keyboard: false,
                        });
                    }

                    return modalInstance;
                };

                const alpine = () => Alpine.$data(modalElement);

                Livewire.on('show-install-release-modal', (payload) => {
                    const data = Array.isArray(payload) ? (payload[0] ?? {}) : (payload ?? {});
                    const state = alpine();

                    if (state) {
                        state.tag = data.tag ?? '';
                        state.build = data.build ?? '';
                        state.prerelease = Boolean(data.prerelease);
                        state.understands = false;
                        state.error = '';
                        state.installing = false;
                    }

                    modal().show();
                });

                Livewire.on('close-install-release-modal', () => {
                    modal().hide();
                });

                Livewire.on('install-release-failed', (payload) => {
                    const data = Array.isArray(payload) ? (payload[0] ?? {}) : (payload ?? {});
                    const state = alpine();

                    if (state) {
                        state.error = data.message ?? 'Installation failed.';
                        state.installing = false;
                    }
                });

                modalElement.addEventListener('hidden.bs.modal', () => {
                    const state = alpine();

                    if (state) {
                        state.installing = false;
                    }

                    Livewire.dispatch('install-release-modal-closed');
                });
            }

            document.addEventListener('livewire:init', wemxBindInstallReleaseModal);
            document.addEventListener('livewire:navigated', wemxBindInstallReleaseModal);
        </script>
    @endonce
</div>
