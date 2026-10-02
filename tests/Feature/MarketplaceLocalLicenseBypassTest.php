<?php

namespace Tests\Feature;

use App\Services\IntegratedMarketplace;
use App\Services\IntegratedMarketplaceInstaller;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class MarketplaceLocalLicenseBypassTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance('env', 'local');
        config([
            'app.license_key' => '',
            'app.license_bypass' => true,
            'services.marketplace.url' => 'https://marketplace.test',
            'services.marketplace.mock' => false,
            'cache.default' => 'array',
        ]);
        Http::preventStrayRequests();
    }

    public function test_local_bypass_allows_catalog_resource_and_account_requests_without_a_key(): void
    {
        Http::fake([
            'https://marketplace.test/api/v1/marketplace/resources/demo' => Http::response(['data' => ['slug' => 'demo']]),
            'https://marketplace.test/api/v1/marketplace/resources*' => Http::response(['data' => [['slug' => 'demo']]]),
            'https://marketplace.test/api/v1/marketplace/account' => Http::response(['account' => ['username' => 'developer']]),
        ]);

        $marketplace = app(IntegratedMarketplace::class);

        $this->assertSame('demo', $marketplace->catalog()['resources'][0]['slug']);
        $this->assertSame('demo', $marketplace->resource('demo')['resource']['slug']);
        $this->assertSame('developer', $marketplace->account()['username']);
        Http::assertSentCount(3);
        Http::assertNotSent(fn (Request $request): bool => $request->hasHeader('Authorization'));
    }

    public function test_local_bypass_allows_view_requests_but_ignores_empty_slugs(): void
    {
        Http::fake(['https://marketplace.test/*' => Http::response([])]);

        app(IntegratedMarketplace::class)->recordView('');
        Http::assertNothingSent();

        app(IntegratedMarketplace::class)->recordView('demo');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/resources/demo/view')
            && ! $request->hasHeader('Authorization'));
    }

    #[DataProvider('enforcedEnvironments')]
    public function test_empty_key_is_blocked_unless_local_bypass_is_enabled(string $environment, bool $enabled): void
    {
        $this->app->instance('env', $environment);
        config(['app.license_bypass' => $enabled]);
        Http::fake();

        $marketplace = app(IntegratedMarketplace::class);
        $message = 'Add a license key before using the marketplace.';

        $this->assertSame($message, $marketplace->catalog()['error']);
        $this->assertSame($message, $marketplace->resource('demo')['error']);
        $this->assertSame($message, $marketplace->account()['error']);
        $marketplace->recordView('demo');
        Http::assertNothingSent();
    }

    public function test_configured_license_key_is_still_sent(): void
    {
        config(['app.license_key' => 'WMX-TESTING-KEY']);
        Http::fake(['https://marketplace.test/*' => Http::response(['data' => []])]);

        app(IntegratedMarketplace::class)->catalog();

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer WMX-TESTING-KEY'));
    }

    public function test_remote_license_rejection_is_preserved_and_not_cached(): void
    {
        Http::fake(['https://marketplace.test/*' => Http::sequence()
            ->push(['message' => 'A license key is required.'], 401)
            ->push(['data' => []])]);

        $marketplace = app(IntegratedMarketplace::class);
        $this->assertSame('A license key is required.', $marketplace->catalog()['error']);
        $this->assertNull($marketplace->catalog()['error']);
        Http::assertSentCount(2);
    }

    public function test_local_installer_reaches_download_and_preserves_remote_denial(): void
    {
        Http::fake([
            'https://marketplace.test/api/v1/marketplace/resources/demo' => Http::response([
                'data' => [
                    'slug' => 'demo',
                    'category' => ['slug' => 'module'],
                    'versions' => [['id' => 9, 'integrated_marketplace' => true]],
                ],
            ]),
            'https://marketplace.test/api/v1/marketplace/resources/download/9' => Http::response(['message' => 'Download requires an active license.'], 403),
        ]);

        try {
            app(IntegratedMarketplaceInstaller::class)->install('demo', 9);
            $this->fail('The remote download rejection must be preserved.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Download requires an active license.', $exception->getMessage());
        }

        Http::assertSentCount(2);
        Http::assertNotSent(fn (Request $request): bool => $request->hasHeader('Authorization'));
    }

    #[DataProvider('enforcedEnvironments')]
    public function test_download_keeps_its_license_check_without_local_bypass(string $environment, bool $enabled): void
    {
        $this->app->instance('env', $environment);
        config(['app.license_bypass' => $enabled]);
        Http::fake([
            'https://marketplace.test/api/v1/marketplace/resources/demo' => Http::response([
                'data' => [
                    'category' => ['slug' => 'module'],
                    'versions' => [['id' => 9, 'integrated_marketplace' => true]],
                ],
            ]),
        ]);

        try {
            app(IntegratedMarketplaceInstaller::class)->install('demo', 9);
            $this->fail('A license must be required without local bypass.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Add a license key before using the marketplace.', $exception->getMessage());
        }

        Http::assertSentCount(1);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function enforcedEnvironments(): array
    {
        return [
            'local without opt-in' => ['local', false],
            'production with opt-in' => ['production', true],
            'staging with opt-in' => ['staging', true],
            'testing with opt-in' => ['testing', true],
        ];
    }
}
