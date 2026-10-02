<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WemxGitHubReleases
{
    public const REPOSITORY = 'wemxnet/wemx';

    public const API_URL = 'https://api.github.com/repos/'.self::REPOSITORY.'/releases';

    public const RELEASES_PAGE_URL = 'https://github.com/'.self::REPOSITORY.'/releases';

    public const CACHE_KEY = 'wemx.github.releases.v2';

    public const CACHE_TTL_SECONDS = 3600;

    /**
     * @return array{
     *     releases: list<array<string, mixed>>,
     *     error: string|null,
     *     fetched_at: string|null
     * }
     */
    public function getPayload(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            Cache::forget(self::CACHE_KEY);
            Cache::forget('wemx.github.releases');
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            $response = Http::timeout(12)
                ->withHeaders([
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => (string) config('app.name', 'WemX'),
                ])
                ->get(self::API_URL, [
                    'per_page' => 30,
                ]);

            if (! $response->successful()) {
                return [
                    'releases' => [],
                    'error' => $this->resolveFetchErrorMessage($response->status(), $response->header('X-RateLimit-Remaining'), $response->header('X-RateLimit-Reset')),
                    'fetched_at' => null,
                ];
            }

            $releases = collect($response->json())
                ->filter(fn (mixed $release): bool => is_array($release) && empty($release['draft']))
                ->map(fn (array $release): array => $this->mapRelease($release))
                ->values()
                ->all();

            return [
                'releases' => $this->enrichReleases($releases),
                'error' => null,
                'fetched_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * @param  list<array<string, mixed>>  $releases
     * @return list<array<string, mixed>>
     */
    public function enrichReleases(array $releases): array
    {
        return array_map(function (array $release): array {
            $appBuildAsset = $this->primaryAppBuildAsset($release);

            $release['app_build_asset'] = $appBuildAsset;
            $release['has_app_build'] = $appBuildAsset !== null;

            return $release;
        }, $releases);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getReleases(bool $forceRefresh = false): array
    {
        return $this->getPayload($forceRefresh)['releases'];
    }

    public function installedVersion(): string
    {
        return (string) config('app.version', '');
    }

    /**
     * @param  list<array<string, mixed>>  $releases
     */
    public function findInstalledRelease(array $releases): ?array
    {
        $installed = $this->normalizeVersion($this->installedVersion());

        if ($installed === '') {
            return null;
        }

        foreach ($releases as $release) {
            if ($this->normalizeVersion((string) ($release['tag_name'] ?? '')) === $installed) {
                return $release;
            }
        }

        foreach ($releases as $release) {
            $tag = $this->normalizeVersion((string) ($release['tag_name'] ?? ''));

            if ($tag !== '' && (str_contains($tag, $installed) || str_contains($installed, $tag))) {
                return $release;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $releases
     * @return array{
     *     installed_version: string,
     *     latest_tag: string|null,
     *     matched_release: array<string, mixed>|null,
     *     update_available: bool,
     *     is_prerelease_channel: bool
     * }
     */
    public function buildStatus(array $releases): array
    {
        $installedVersion = $this->installedVersion();
        $latest = $releases[0] ?? null;
        $matchedRelease = $this->findInstalledRelease($releases);
        $latestTag = $latest['tag_name'] ?? null;

        $updateAvailable = false;

        if ($latestTag !== null) {
            $updateAvailable = $matchedRelease === null
                ? $this->normalizeVersion($installedVersion) !== $this->normalizeVersion((string) $latestTag)
                : (int) ($matchedRelease['id'] ?? 0) !== (int) ($latest['id'] ?? 0);
        }

        return [
            'installed_version' => $installedVersion,
            'latest_tag' => $latestTag,
            'matched_release' => $matchedRelease,
            'update_available' => $updateAvailable,
            'is_prerelease_channel' => admin_is_prerelease_version(),
        ];
    }

    public function normalizeVersion(string $version): string
    {
        return strtolower(ltrim(trim($version), 'vV'));
    }

    /**
     * @param  list<array<string, mixed>>  $releases
     */
    public function findReleaseById(array $releases, int $releaseId): ?array
    {
        foreach ($releases as $release) {
            if ((int) ($release['id'] ?? 0) === $releaseId) {
                return $release;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $release
     * @return array<string, mixed>|null
     */
    public function primaryAppBuildAsset(array $release): ?array
    {
        $assets = $release['assets'] ?? [];

        if (! is_array($assets)) {
            return null;
        }

        $tagName = (string) ($release['tag_name'] ?? '');

        foreach ($assets as $asset) {
            if (! is_array($asset)) {
                continue;
            }

            $name = (string) ($asset['name'] ?? '');

            if ($name !== '' && preg_match('/^WemX-.+\.zip$/i', $name) === 1) {
                return $asset;
            }
        }

        foreach ($assets as $asset) {
            if (! is_array($asset)) {
                continue;
            }

            $name = (string) ($asset['name'] ?? '');

            if ($tagName !== '' && $name === "WemX-{$tagName}.zip") {
                return $asset;
            }
        }

        foreach ($assets as $asset) {
            if (! is_array($asset)) {
                continue;
            }

            $name = (string) ($asset['name'] ?? '');

            if (str_ends_with(strtolower($name), '.zip')) {
                return $asset;
            }
        }

        return null;
    }

    protected function resolveFetchErrorMessage(int $status, ?string $rateLimitRemaining, ?string $rateLimitReset): string
    {
        if ($status === 403 && $rateLimitRemaining === '0') {
            $retryAt = is_numeric($rateLimitReset)
                ? Carbon::createFromTimestamp((int) $rateLimitReset)->diffForHumans()
                : 'later';

            return "GitHub API rate limit exceeded. Unauthenticated requests are limited to 60 per hour. Try again {$retryAt}, or click Refresh after the limit resets.";
        }

        if ($status === 403) {
            return 'GitHub denied access to release data (HTTP 403). Check repository visibility and API rate limits.';
        }

        if ($status === 404) {
            return 'GitHub repository or releases endpoint was not found (HTTP 404).';
        }

        return 'Unable to load releases from GitHub (HTTP '.$status.'). Please try again later.';
    }

    /**
     * @param  array<string, mixed>  $release
     * @return array<string, mixed>|null
     */
    protected function mapRelease(array $release): array
    {
        $publishedAt = isset($release['published_at'])
            ? Carbon::parse((string) $release['published_at'])
            : null;

        $assets = collect($release['assets'] ?? [])
            ->filter(fn (mixed $asset): bool => is_array($asset))
            ->map(fn (array $asset): array => [
                'id' => (int) ($asset['id'] ?? 0),
                'name' => (string) ($asset['name'] ?? ''),
                'size' => (int) ($asset['size'] ?? 0),
                'download_count' => (int) ($asset['download_count'] ?? 0),
                'content_type' => (string) ($asset['content_type'] ?? ''),
                'browser_download_url' => (string) ($asset['browser_download_url'] ?? ''),
            ])
            ->values()
            ->all();

        $appBuildAsset = $this->primaryAppBuildAsset([
            'tag_name' => (string) ($release['tag_name'] ?? ''),
            'assets' => $assets,
        ]);

        return [
            'id' => (int) ($release['id'] ?? 0),
            'tag_name' => (string) ($release['tag_name'] ?? ''),
            'name' => (string) ($release['name'] ?? $release['tag_name'] ?? 'Release'),
            'body' => (string) ($release['body'] ?? ''),
            'html_url' => (string) ($release['html_url'] ?? ''),
            'prerelease' => (bool) ($release['prerelease'] ?? false),
            'published_at' => $publishedAt?->toIso8601String(),
            'published_at_human' => $publishedAt?->diffForHumans(),
            'published_at_formatted' => $publishedAt?->format(settings('date_format', 'd M Y H:i')),
            'author_login' => (string) data_get($release, 'author.login', ''),
            'author_avatar_url' => (string) data_get($release, 'author.avatar_url', ''),
            'assets' => $assets,
            'app_build_asset' => $appBuildAsset,
            'has_app_build' => $appBuildAsset !== null,
            'zipball_url' => (string) ($release['zipball_url'] ?? ''),
            'tarball_url' => (string) ($release['tarball_url'] ?? ''),
        ];
    }
}
