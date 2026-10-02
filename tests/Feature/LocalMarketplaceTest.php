<?php

namespace Tests\Feature;

use App\Mail\EmailTheme;
use App\Models\IntegratedMarketplaceInstallation;
use App\Models\Role;
use App\Models\User;
use App\Services\IntegratedMarketplace;
use App\Services\IntegratedMarketplaceInstaller;
use App\Services\LocalMarketplace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class LocalMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance('env', 'local');
        config([
            'app.installed' => true,
            'app.license_key' => '',
            'app.license_bypass' => true,
            'services.marketplace.mock' => true,
            'services.marketplace.url' => 'https://marketplace.test',
            'cache.default' => 'array',
        ]);
        Http::preventStrayRequests();
    }

    public function test_catalog_and_account_work_without_a_license_or_network(): void
    {
        config(['app.license_bypass' => false]);
        $marketplace = app(IntegratedMarketplace::class);

        $catalog = $marketplace->catalog();
        $this->assertNull($catalog['error']);
        $this->assertSame(1, $catalog['total']);
        $this->assertSame(LocalMarketplace::SLUG, $catalog['resources'][0]['slug']);
        $this->assertFalse($catalog['resources'][0]['official']);
        $this->assertTrue($marketplace->account()['mock']);
        $this->assertNull($marketplace->account()['username']);
        $this->assertNull($marketplace->resource(LocalMarketplace::SLUG)['resource']['view_url']);
        $marketplace->recordView(LocalMarketplace::SLUG);

        Http::assertNothingSent();
    }

    public function test_catalog_filters_and_pagination_are_supported(): void
    {
        $marketplace = app(IntegratedMarketplace::class);

        $this->assertCount(1, $marketplace->catalog(['search' => 'DEMO', 'category' => 'email-theme'])['resources']);
        $this->assertSame(0, $marketplace->catalog(['search' => 'pterodactyl'])['total']);
        $this->assertSame(0, $marketplace->catalog(['category' => 'server'])['total']);
        $this->assertSame([], $marketplace->catalog(['page' => 2])['resources']);
        $this->assertSame(1, $marketplace->catalog(['page' => 0])['page']);
        Http::assertNothingSent();
    }

    public function test_unknown_resources_do_not_fall_back_to_the_hosted_marketplace(): void
    {
        $this->assertNull(app(IntegratedMarketplace::class)->resource('pterodactyl')['resource']);

        try {
            app(IntegratedMarketplaceInstaller::class)->install('pterodactyl', 1);
            $this->fail('Unknown resources must not install.');
        } catch (RuntimeException $exception) {
            $this->assertSame('This resource could not be loaded from the marketplace.', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    #[DataProvider('disabledModes')]
    public function test_mock_mode_requires_local_environment_and_explicit_opt_in(string $environment, bool $enabled): void
    {
        $this->app->instance('env', $environment);
        config(['services.marketplace.mock' => $enabled, 'app.license_bypass' => false]);

        $this->assertFalse(LocalMarketplace::isEnabled());
        $this->assertNull(app(LocalMarketplace::class)->resource(LocalMarketplace::SLUG));
        $this->assertSame('Add a license key before using the marketplace.', app(IntegratedMarketplace::class)->catalog()['error']);
        Http::assertNothingSent();
    }

    public function test_switching_off_mock_mode_uses_the_real_marketplace_without_demo_cache(): void
    {
        $marketplace = app(IntegratedMarketplace::class);
        $this->assertSame(1, $marketplace->catalog()['total']);

        config(['services.marketplace.mock' => false, 'app.license_key' => 'WMX-TEST']);
        Http::fake(['https://marketplace.test/*' => Http::response(['data' => [], 'total' => 0])]);

        $this->assertSame([], $marketplace->catalog()['resources']);
        Http::assertSentCount(1);
    }

    public function test_admin_sees_demo_labels_and_local_install_instructions(): void
    {
        $this->actingAsMarketplaceAdmin()
            ->get(route('admin.marketplace.index'))
            ->assertOk()
            ->assertSee('Local mock marketplace')
            ->assertSee('Local demo email theme')
            ->assertDontSee('Connected as');

        Volt::test('admin_area.default.integrated-marketplace.livewire.browse')
            ->set('q', 'missing')
            ->assertDontSee('Local demo email theme')
            ->set('q', 'demo')
            ->assertSee('Local demo email theme');

        Volt::test('admin_area.default.integrated-marketplace.livewire.resource', ['slug' => LocalMarketplace::SLUG])
            ->assertSee('Local mock marketplace')
            ->assertDontSee('View on marketplace')
            ->call('openInstall', LocalMarketplace::VERSION_ID)
            ->assertSee('The demo zip is generated locally')
            ->assertDontSee('The zip is downloaded from the marketplace');

        Http::assertNothingSent();
    }

    public function test_demo_installation_extracts_a_discoverable_email_theme_and_records_it(): void
    {
        $this->actingAsMarketplaceAdmin();
        config(['app.license_bypass' => false]);
        $originalBase = base_path();
        $originalStorage = storage_path();
        $sandbox = storage_path('framework/testing/local-marketplace-'.Str::uuid());
        File::ensureDirectoryExists($sandbox.'/resources/email_templates/default');
        File::copy(resource_path('email_templates/default/email.blade.php'), $sandbox.'/resources/email_templates/default/email.blade.php');

        try {
            $this->app->setBasePath($sandbox);
            $this->app->useStoragePath($sandbox.'/storage');

            $message = app(IntegratedMarketplaceInstaller::class)->install(LocalMarketplace::SLUG, LocalMarketplace::VERSION_ID);

            $this->assertStringContainsString('was extracted', $message);
            $this->assertFileExists(resource_path('email_templates/'.LocalMarketplace::SLUG.'/email.blade.php'));
            $this->assertSame('Local demo email theme', EmailTheme::find(LocalMarketplace::SLUG)?->name);
            $this->assertSame('markdown', EmailTheme::find(LocalMarketplace::SLUG)?->format);
            $installation = IntegratedMarketplaceInstallation::query()->where('resource_slug', LocalMarketplace::SLUG)->firstOrFail();
            $this->assertSame('1.0.0', $installation->version);
            $this->assertSame('resources/email_templates/'.LocalMarketplace::SLUG, $installation->path);
            $this->assertSame([], File::glob(storage_path('app/marketplace-installs/*.zip')));
            Http::assertNothingSent();
        } finally {
            $this->app->setBasePath($originalBase);
            $this->app->useStoragePath($originalStorage);
            $resolvedSandbox = realpath($sandbox);
            $testingDirectory = realpath($originalStorage.'/framework/testing');

            if ($resolvedSandbox !== false && $testingDirectory !== false
                && str_starts_with($resolvedSandbox, $testingDirectory.DIRECTORY_SEPARATOR)) {
                File::deleteDirectory($resolvedSandbox);
            }
        }
    }

    public function test_unknown_demo_versions_are_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This version is not available on the integrated marketplace.');

        app(IntegratedMarketplaceInstaller::class)->install(LocalMarketplace::SLUG, 999);
    }

    public function test_archive_generation_is_unavailable_outside_local_mock_mode(): void
    {
        $this->app->instance('env', 'production');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This local demo version is unavailable.');

        app(LocalMarketplace::class)->archive(LocalMarketplace::SLUG, LocalMarketplace::VERSION_ID);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function disabledModes(): array
    {
        return [
            'local without opt-in' => ['local', false],
            'production with opt-in' => ['production', true],
            'staging with opt-in' => ['staging', true],
            'testing with opt-in' => ['testing', true],
        ];
    }

    private function actingAsMarketplaceAdmin(): self
    {
        $user = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $role = Role::query()->create(['name' => 'Local marketplace tester', 'super_admin' => true]);
        DB::table('role_user')->insert([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->actingAs($user)->withSession(['admin_reauthenticated_at' => now()->toDateTimeString()]);
    }
}
