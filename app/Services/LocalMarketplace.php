<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

final class LocalMarketplace
{
    public const SLUG = 'local-demo-email';

    public const VERSION_ID = 1;

    public static function isEnabled(): bool
    {
        return app()->environment('local') && config('services.marketplace.mock', false) === true;
    }

    /**
     * @param  array{search?: string, category?: string|null, sort_by?: string, page?: int, per_page?: int}  $filters
     * @return array{resources: list<array<string, mixed>>, featured: array{}, categories: list<array{slug: string, name: string}>, page: int, last_page: int, total: int, error: null}
     */
    public function catalog(array $filters = []): array
    {
        $resource = $this->resource(self::SLUG);
        $search = trim((string) ($filters['search'] ?? ''));
        $category = trim((string) ($filters['category'] ?? ''));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $matches = $resource !== null
            && ($search === '' || Str::contains($resource['name'].' '.$resource['short_description'], $search, ignoreCase: true))
            && ($category === '' || $category === 'email-theme');

        return [
            'resources' => $matches && $page === 1 ? [$resource] : [],
            'featured' => [],
            'categories' => [['slug' => 'email-theme', 'name' => 'Email themes']],
            'page' => $page,
            'last_page' => 1,
            'total' => $matches ? 1 : 0,
            'error' => null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resource(string $slug): ?array
    {
        if (! self::isEnabled() || trim($slug) !== self::SLUG) {
            return null;
        }

        return [
            'id' => 1,
            'slug' => self::SLUG,
            'name' => 'Local demo email theme',
            'short_description' => 'A sample email theme for testing local marketplace installation.',
            'description' => 'This local demo reuses the built-in email layout. Install it to test the marketplace flow. It contains no WemX marketplace packages or Pterodactyl integration.',
            'icon' => null,
            'initials' => 'LD',
            'price' => 'Free',
            'featured' => false,
            'official' => false,
            'views' => 0,
            'downloads' => 0,
            'purchases' => 0,
            'reviews_count' => 0,
            'reviews_avg' => 0,
            'latest_version' => '1.0.0',
            'source' => null,
            'website' => null,
            'docs' => null,
            'support' => null,
            'view_url' => null,
            'category' => ['slug' => 'email-theme', 'name' => 'Email themes'],
            'user' => ['username' => 'Local demo', 'avatar' => null, 'url' => null],
            'has_access' => true,
            'reviews' => [],
            'versions' => [[
                'id' => self::VERSION_ID,
                'name' => 'Local demo',
                'version' => '1.0.0',
                'wemx_version' => '*',
                'changelog' => 'Sample package generated on this computer for development.',
                'created_at' => null,
                'integrated_marketplace' => true,
                'extract_path' => 'resources/email_templates',
                'rename_extract_to' => self::SLUG,
                'size_label' => 'Local sample',
            ]],
        ];
    }

    public function archive(string $slug, int $versionId): string
    {
        if ($this->resource($slug) === null || $versionId !== self::VERSION_ID) {
            throw new RuntimeException('This local demo version is unavailable.');
        }

        $directory = storage_path('app/marketplace-installs');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/'.Str::uuid().'.zip';
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The local demo archive could not be created.');
        }

        try {
            try {
                $template = File::get(resource_path('email_templates/default/email.blade.php'));
                $metadata = json_encode([
                    'name' => 'Local demo email theme',
                    'description' => 'Sample theme installed from the local mock marketplace.',
                    'format' => 'markdown',
                ], JSON_THROW_ON_ERROR);

                if (! $zip->addFromString(self::SLUG.'/email.blade.php', $template)
                    || ! $zip->addFromString(self::SLUG.'/theme.json', $metadata)) {
                    throw new RuntimeException('The local demo files could not be archived.');
                }
            } finally {
                $zip->close();
            }

            return File::get($path);
        } finally {
            File::delete($path);
        }
    }
}
