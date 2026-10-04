<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Setting;
use DOMDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SocialMetadataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.installed' => true, 'app.license_key' => 'WMX-TESTING-KEY']);
        Cache::put('lcs_checked_at', now(), 21600);
        Setting::forget('seo::image');
        Setting::forget('seo::title');
    }

    protected function tearDown(): void
    {
        Setting::forget('seo::image');
        Setting::forget('seo::title');
        parent::tearDown();
    }

    #[DataProvider('publicPages')]
    public function test_public_and_authentication_pages_use_the_requested_server_image(string $path): void
    {
        $metadata = $this->metadata($path);
        $this->assertSame(url('/assets/common/img/vaded-branded-server-rack.png'), $metadata['og:image']);
        $this->assertSame($metadata['og:image'], $metadata['twitter:image']);
        $this->assertSame('summary_large_image', $metadata['twitter:card']);
        $this->assertSame('image/png', $metadata['og:image:type']);
        $dimensions = getimagesize(public_path('assets/common/img/vaded-branded-server-rack.png'));
        $this->assertSame((string) $dimensions[0], $metadata['og:image:width']);
        $this->assertSame((string) $dimensions[1], $metadata['og:image:height']);
        $this->assertNotEmpty($metadata['og:image:alt']);
        $this->assertSame($metadata['og:image:alt'], $metadata['twitter:image:alt']);
        $this->assertSame($metadata['og:title'], $metadata['twitter:title']);
        $this->assertSame($metadata['description'], $metadata['og:description']);
        $this->assertSame($metadata['description'], $metadata['twitter:description']);
        $this->assertSame('Vaded Hosting', $metadata['og:site_name']);
        $this->assertNotEmpty($metadata['description']);
    }

    /** @return array<string, array{string}> */
    public static function publicPages(): array
    {
        return ['homepage' => ['/'], 'sign in' => ['/auth/login']];
    }

    #[DataProvider('legacyImages')]
    public function test_empty_and_legacy_default_settings_use_the_new_server_image(string $image): void
    {
        $image = str_replace(
            ['{site}', '{schemeless_site}'],
            [url('/'), preg_replace('/^https?:/', '', url('/'))],
            $image,
        );
        Setting::put('seo::image', $image);
        $metadata = $this->metadata('/');
        $this->assertSame(url('/assets/common/img/vaded-branded-server-rack.png'), $metadata['og:image']);
        $this->assertSame($metadata['og:image'], $metadata['twitter:image']);
        $this->assertSame('1126', $metadata['og:image:width']);
    }

    /** @return array<string, array{string}> */
    public static function legacyImages(): array
    {
        return [
            'empty' => [''], 'blank' => ['   '],
            'relative PNG' => ['/assets/common/img/vaded-social.png'],
            'relative SVG' => ['assets/common/img/vaded-social.svg'],
            'same-site absolute' => ['{site}/assets/common/img/vaded-social.png?version=old'],
            'same-site protocol-relative' => ['{schemeless_site}/assets/common/img/vaded-social.png'],
        ];
    }

    #[DataProvider('customImages')]
    public function test_custom_preview_images_are_preserved_with_absolute_urls(string $configuredImage, string $expectedImage): void
    {
        Setting::put('seo::image', $configuredImage);
        $metadata = $this->metadata('/');
        $expectedUrl = str_starts_with($expectedImage, '/') ? url($expectedImage) : $expectedImage;
        $this->assertSame($expectedUrl, $metadata['og:image']);
        $this->assertSame($expectedUrl, $metadata['twitter:image']);
        $this->assertArrayNotHasKey('og:image:width', $metadata);
        $this->assertArrayNotHasKey('og:image:height', $metadata);
    }

    /** @return array<string, array{string, string}> */
    public static function customImages(): array
    {
        return [
            'custom local image' => ['/assets/custom-preview.jpg', '/assets/custom-preview.jpg'],
            'external image' => ['https://cdn.example.test/social.webp', 'https://cdn.example.test/social.webp'],
            'protocol-relative CDN' => ['//cdn.example.test/social.webp', 'http://cdn.example.test/social.webp'],
            'external legacy-looking filename' => ['https://cdn.example.test/assets/common/img/vaded-social.png', 'https://cdn.example.test/assets/common/img/vaded-social.png'],
        ];
    }

    public function test_category_previews_preserve_page_copy_and_escape_titles_and_descriptions(): void
    {
        $category = Category::query()->create(['name' => 'Games & "Cloud"', 'slug' => 'games-cloud', 'status' => 'active', 'icon' => '',
            'description' => 'Hosting for "friends" & <communities>.']);
        Setting::put('seo::title', 'Vaded & "Hosting"');
        $metadata = $this->metadata('/?category='.$category->slug);
        $this->assertSame('Games & "Cloud" Plans - Vaded & "Hosting"', $metadata['og:title']);
        $this->assertSame('Hosting for "friends" & <communities>.', $metadata['og:description']);
        $this->assertSame($metadata['og:title'], $metadata['twitter:title']);
        $this->assertSame($metadata['og:description'], $metadata['twitter:description']);
        $this->assertSame(url('/').'/?category='.$category->slug, $metadata['og:url']);
    }

    public function test_empty_page_titles_fall_back_to_the_site_name(): void
    {
        $html = view('theme::components.social-meta')->render();
        $this->assertStringContainsString('<meta property="og:title" content="Vaded Hosting">', $html);
        $this->assertStringNotContainsString('content=" - Vaded Hosting"', $html);
    }

    /** @return array<string, string> */
    private function metadata(string $path): array
    {
        $response = $this->get($path)->assertOk();
        $document = new DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $metadata = [];
        foreach ($document->getElementsByTagName('meta') as $tag) {
            $key = $tag->getAttribute('property') ?: $tag->getAttribute('name');
            if ($key !== '') {
                $this->assertArrayNotHasKey($key, $metadata, 'Metadata must not be duplicated.');
                $metadata[$key] = $tag->getAttribute('content');
            }
        }

        return $metadata;
    }
}
