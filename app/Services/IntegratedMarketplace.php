<?php

namespace App\Services;

use App\Models\IntegratedMarketplaceInstallation;
use App\Support\LocalLicense;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class IntegratedMarketplace
{
    public const INSTALLABLE_CATEGORY_SLUGS = [
        'server',
        'module',
        'payment-gateway',
        'email-theme',
        'invoice-theme',
    ];

    public const PER_PAGE = 18;

    public const CATALOG_CACHE_TTL_SECONDS = 600;

    public const RESOURCE_CACHE_TTL_SECONDS = 1800;

    /**
     * @param  array{search?: string, category?: string|null, sort_by?: string, page?: int, per_page?: int}  $filters
     * @return array{
     *     resources: list<array<string, mixed>>,
     *     featured: list<array<string, mixed>>,
     *     categories: list<array{slug: string, name: string}>,
     *     page: int,
     *     last_page: int,
     *     total: int,
     *     error: string|null
     * }
     */
    public function catalog(array $filters = []): array
    {
        if (LocalMarketplace::isEnabled()) {
            return app(LocalMarketplace::class)->catalog($filters);
        }

        if ($this->licenseKey() === '' && ! LocalLicense::isBypassed()) {
            return $this->emptyCatalog('Add a license key before using the marketplace.');
        }

        $query = [
            'search' => trim((string) ($filters['search'] ?? '')),
            'category' => $this->normalizeCategory($filters['category'] ?? null) ?? '',
            'sort_by' => (string) ($filters['sort_by'] ?? 'popular'),
            'page' => max(1, (int) ($filters['page'] ?? 1)),
            'per_page' => self::PER_PAGE,
        ];

        if ($query['category'] === '') {
            unset($query['category']);
        }

        if ($query['search'] === '') {
            unset($query['search']);
        }

        $key = $this->cacheKey('catalog', (string) json_encode($query));

        return $this->remember($key, fn (): array => $this->fetchCatalog($query), self::CATALOG_CACHE_TTL_SECONDS);
    }

    /**
     * @return array{resource: array<string, mixed>|null, error: string|null}
     */
    public function resource(string $slug, bool $fresh = false): array
    {
        $slug = trim($slug);

        if (LocalMarketplace::isEnabled()) {
            return ['resource' => app(LocalMarketplace::class)->resource($slug), 'error' => null];
        }

        if ($this->licenseKey() === '' && ! LocalLicense::isBypassed()) {
            return [
                'resource' => null,
                'error' => 'Add a license key before using the marketplace.',
            ];
        }

        if ($fresh) {
            $this->forgetResource($slug);
        }

        return $this->remember(
            $this->cacheKey('resource', $slug),
            fn (): array => $this->fetchResource($slug),
            self::RESOURCE_CACHE_TTL_SECONDS,
        );
    }

    public function canInstall(mixed $resource): bool
    {
        if (! is_array($resource)) {
            return false;
        }

        $category = is_array($resource['category'] ?? null) ? $resource['category'] : [];

        return in_array($category['slug'] ?? null, self::INSTALLABLE_CATEGORY_SLUGS, true);
    }

    public function normalizeCategory(mixed $category): ?string
    {
        $category = trim((string) $category);

        if ($category === '' || preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $category) !== 1) {
            return null;
        }

        return $category;
    }

    public function isNewerVersion(string $latest, string $installed): bool
    {
        $latest = $this->normalizeVersion($latest);
        $installed = $this->normalizeVersion($installed);

        if ($latest === '' || $installed === '') {
            return false;
        }

        return version_compare($latest, $installed, '>');
    }

    public function refreshInstalledUpdates(): int
    {
        $updates = 0;

        foreach (IntegratedMarketplaceInstallation::query()->orderBy('id')->get() as $installation) {
            if (! $installation->isPresent()) {
                if ($installation->update_available) {
                    $installation->update([
                        'update_available' => false,
                        'update_checked_at' => now(),
                    ]);
                }

                continue;
            }

            $payload = $this->resource($installation->resource_slug, fresh: true);

            if (($payload['error'] ?? null) !== null) {
                continue;
            }

            $latest = $this->latestInstallableVersion(is_array($payload['resource'] ?? null) ? $payload['resource'] : null);
            $available = is_string($latest) && $this->isNewerVersion($latest, (string) ($installation->version ?: ''));

            $installation->update([
                'latest_version' => $latest,
                'update_available' => $available,
                'update_checked_at' => now(),
            ]);

            if ($available) {
                $updates++;
            }
        }

        return $updates;
    }

    /**
     * @param  array<string, mixed>|null  $resource
     */
    private function latestInstallableVersion(?array $resource): ?string
    {
        $versions = is_array($resource['versions'] ?? null) ? $resource['versions'] : [];
        $latest = null;

        foreach ($versions as $version) {
            if (! is_array($version) || empty($version['integrated_marketplace'])) {
                continue;
            }

            $number = trim((string) ($version['version'] ?? ''));

            if ($number === '') {
                continue;
            }

            if ($latest === null || version_compare($this->normalizeVersion($number), $this->normalizeVersion($latest), '>')) {
                $latest = $number;
            }
        }

        return $latest;
    }

    private function normalizeVersion(string $version): string
    {
        return ltrim(strtolower(trim($version)), 'v');
    }

    public function forgetResource(string $slug): void
    {
        Cache::forget($this->cacheKey('resource', trim($slug)));
    }

    /**
     * @return array{username: ?string, email: ?string, error: ?string, mock?: bool}
     */
    public function account(): array
    {
        $empty = [
            'username' => null,
            'email' => null,
            'error' => null,
        ];

        if (LocalMarketplace::isEnabled()) {
            return [...$empty, 'mock' => true];
        }

        if ($this->licenseKey() === '' && ! LocalLicense::isBypassed()) {
            $empty['error'] = 'Add a license key before using the marketplace.';

            return $empty;
        }

        $key = $this->cacheKey('account', hash('sha256', $this->licenseKey()));
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = $this->request()->get('/api/v1/marketplace/account');
        } catch (ConnectionException) {
            $empty['error'] = 'The marketplace could not be reached. Try again in a moment.';

            return $empty;
        }

        if ($response->notFound()) {
            return $empty;
        }

        if (! $response->successful()) {
            $empty['error'] = $this->failureMessage($response);

            return $empty;
        }

        $account = $response->json('account');
        $payload = [
            'username' => is_array($account) && is_string($account['username'] ?? null) ? $account['username'] : null,
            'email' => is_array($account) && is_string($account['email'] ?? null) ? $account['email'] : null,
            'error' => null,
        ];

        if ($payload['username'] !== null || $payload['email'] !== null) {
            Cache::put($key, $payload, self::CATALOG_CACHE_TTL_SECONDS);
        }

        return $payload;
    }

    public function recordView(string $slug): void
    {
        if (LocalMarketplace::isEnabled()) {
            return;
        }

        $slug = trim($slug);

        if ($slug === '' || ($this->licenseKey() === '' && ! LocalLicense::isBypassed())) {
            return;
        }

        try {
            $response = $this->request()->post('/api/v1/marketplace/resources/'.rawurlencode($slug).'/view', [
                'visitor' => hash('sha256', 'integrated:'.rtrim((string) config('app.url'), '/').':'.(auth()->id() ?? 'guest')),
            ]);
        } catch (ConnectionException) {
            return;
        }

        if ($response->successful()) {
            $this->forgetResource($slug);
        }
    }

    /**
     * @param  callable(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function remember(string $key, callable $callback, int $ttl): array
    {
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $payload = $callback();

        if (($payload['error'] ?? null) !== null) {
            return $payload;
        }

        if (array_key_exists('resource', $payload) && $payload['resource'] === null) {
            return $payload;
        }

        Cache::put($key, $payload, $ttl);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{
     *     resources: list<array<string, mixed>>,
     *     featured: list<array<string, mixed>>,
     *     categories: list<array{slug: string, name: string}>,
     *     page: int,
     *     last_page: int,
     *     total: int,
     *     error: string|null
     * }
     */
    private function fetchCatalog(array $query): array
    {
        $empty = [
            'resources' => [],
            'featured' => [],
            'categories' => [],
            'page' => 1,
            'last_page' => 1,
            'total' => 0,
            'error' => null,
        ];

        try {
            $response = $this->request()->get('/api/v1/marketplace/resources', $query);
        } catch (ConnectionException) {
            $empty['error'] = 'The marketplace could not be reached. Try again in a moment.';

            return $empty;
        }

        if (! $response->successful()) {
            $empty['error'] = $this->failureMessage($response);

            return $empty;
        }

        $json = $response->json();

        $resources = is_array($json['data'] ?? null) ? $json['data'] : [];
        $featured = is_array($json['featured'] ?? null) ? $json['featured'] : [];
        $categories = is_array($json['categories'] ?? null) ? $json['categories'] : [];

        return [
            'resources' => array_values(array_filter(array_map($this->summary(...), $resources))),
            'featured' => array_values(array_filter(array_map($this->summary(...), $featured))),
            'categories' => array_values(array_filter($categories, function (mixed $category): bool {
                return is_array($category)
                    && is_string($category['slug'] ?? null)
                    && $category['slug'] !== ''
                    && is_string($category['name'] ?? null);
            })),
            'page' => (int) ($json['current_page'] ?? 1),
            'last_page' => max(1, (int) ($json['last_page'] ?? 1)),
            'total' => (int) ($json['total'] ?? 0),
            'error' => null,
        ];
    }

    /**
     * @return array{resource: array<string, mixed>|null, error: string|null}
     */
    private function fetchResource(string $slug): array
    {
        try {
            $response = $this->request()->get('/api/v1/marketplace/resources/'.rawurlencode($slug));
        } catch (ConnectionException) {
            return [
                'resource' => null,
                'error' => 'The marketplace could not be reached. Try again in a moment.',
            ];
        }

        if ($response->notFound()) {
            return [
                'resource' => null,
                'error' => null,
            ];
        }

        if (! $response->successful()) {
            return [
                'resource' => null,
                'error' => $this->failureMessage($response),
            ];
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            return [
                'resource' => null,
                'error' => 'This resource could not be loaded.',
            ];
        }

        return [
            'resource' => $data,
            'error' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(mixed $resource): array
    {
        if (! is_array($resource)) {
            return [];
        }

        $category = is_array($resource['category'] ?? null) ? $resource['category'] : [];
        $user = is_array($resource['user'] ?? null) ? $resource['user'] : [];

        return [
            'id' => $resource['id'] ?? null,
            'name' => $resource['name'] ?? '',
            'slug' => $resource['slug'] ?? '',
            'short_description' => $resource['short_description'] ?? '',
            'icon' => $resource['icon'] ?? null,
            'initials' => $resource['initials'] ?? '',
            'price' => $resource['price'] ?? '',
            'featured' => (bool) ($resource['featured'] ?? false),
            'official' => (bool) ($resource['official'] ?? false),
            'views' => (int) ($resource['views'] ?? 0),
            'downloads' => (int) ($resource['downloads'] ?? 0),
            'purchases' => (int) ($resource['purchases'] ?? 0),
            'reviews_count' => (int) ($resource['reviews_count'] ?? 0),
            'reviews_avg' => (float) ($resource['reviews_avg'] ?? 0),
            'latest_version' => $resource['latest_version'] ?? ($resource['versions'][0]['version'] ?? null),
            'view_url' => $resource['view_url'] ?? null,
            'category' => [
                'slug' => $category['slug'] ?? null,
                'name' => $category['name'] ?? null,
            ],
            'user' => [
                'username' => $user['username'] ?? null,
                'avatar' => $user['avatar'] ?? null,
            ],
            'has_access' => array_key_exists('has_access', $resource) ? (bool) $resource['has_access'] : true,
        ];
    }

    private function cacheKey(string $type, string $discriminator): string
    {
        $scope = rtrim((string) config('services.marketplace.url'), '/');

        return 'integrated-marketplace.'.$type.'.'.md5($scope."\n".$discriminator);
    }

    private function licenseKey(): string
    {
        return trim((string) config('app.license_key'));
    }

    private function failureMessage(Response $response): string
    {
        $message = $response->json('message');

        if (is_string($message) && $message !== '') {
            return $message;
        }

        return match ($response->status()) {
            401 => 'A license key is required.',
            403 => 'License is not active.',
            default => 'The marketplace returned an unexpected response.',
        };
    }

    /**
     * @return array{
     *     resources: list<array<string, mixed>>,
     *     featured: list<array<string, mixed>>,
     *     categories: list<array{slug: string, name: string}>,
     *     page: int,
     *     last_page: int,
     *     total: int,
     *     error: string|null
     * }
     */
    private function emptyCatalog(string $error): array
    {
        return [
            'resources' => [],
            'featured' => [],
            'categories' => [],
            'page' => 1,
            'last_page' => 1,
            'total' => 0,
            'error' => $error,
        ];
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.marketplace.url'), '/'))
            ->when($this->licenseKey() !== '', fn (PendingRequest $request): PendingRequest => $request->withToken($this->licenseKey()))
            ->acceptJson()
            ->withOptions([
                'allow_redirects' => [
                    'strict' => true,
                    'referer' => true,
                    'protocols' => ['http', 'https'],
                    'max' => 5,
                ],
            ])
            ->connectTimeout(3)
            ->timeout(8)
            ->retry(2, 200, function ($exception): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError());
            }, throw: false);
    }
}
