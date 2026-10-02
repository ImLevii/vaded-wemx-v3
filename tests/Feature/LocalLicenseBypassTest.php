<?php

namespace Tests\Feature;

use App\Http\Middleware\SyncRuntimeMiddleware;
use App\Install\Livewire\InstallWizard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LocalLicenseBypassTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.installed' => false,
            'app.license_key' => '',
            'app.license_bypass' => true,
            'app.demo_mode' => false,
            'cache.default' => 'array',
            'session.driver' => 'array',
        ]);
        $this->app->instance('env', 'local');
        $this->app['view']->addNamespace('install', base_path('app/Install/views'));

        Http::preventStrayRequests();
        Http::fake([
            'api-v3.wemx.org/*' => Http::response(['success' => false], 200),
        ]);
    }

    public function test_local_installer_can_continue_without_a_key_or_persisted_activation(): void
    {
        $environment = file_get_contents(base_path('.env'));

        Livewire::test(InstallWizard::class, ['step' => 'activation'])
            ->assertSee('Local Development')
            ->assertDontSee('Check License')
            ->assertSet('isLicenseActive', true)
            ->call('checkLicense')
            ->assertHasNoErrors()
            ->call('checkLicenseAndContinue')
            ->assertHasNoErrors()
            ->assertSet('step', 'database');

        Http::assertNothingSent();
        $this->assertFalse(session()->has('installer.license_active'));
        $this->assertFalse(Cache::has('lcs_checked_at'));
        $this->assertSame($environment, file_get_contents(base_path('.env')));
    }

    public function test_local_installer_ignores_an_invalid_existing_key(): void
    {
        config(['app.license_key' => 'invalid']);

        Livewire::test(InstallWizard::class, ['step' => 'activation'])
            ->call('checkLicense')
            ->assertHasNoErrors()
            ->call('checkLicenseAndContinue')
            ->assertSet('step', 'database');

        Http::assertNothingSent();
    }

    #[DataProvider('enforcedEnvironments')]
    public function test_installer_enforces_license_when_bypass_is_unavailable(string $environment, bool $enabled, bool $demoMode = false): void
    {
        $this->app->instance('env', $environment);
        config([
            'app.license_bypass' => $enabled,
            'app.demo_mode' => $demoMode,
            'app.license_key' => 'WMX-INVALID',
        ]);

        Livewire::test(InstallWizard::class, ['step' => 'activation'])
            ->assertSee('Activate License Key')
            ->assertSet('isLicenseActive', false)
            ->call('checkLicenseAndContinue')
            ->assertHasErrors('license_key')
            ->assertSet('step', 'activation');

        Http::assertSentCount(1);
    }

    public function test_disabling_bypass_does_not_reuse_local_activation(): void
    {
        Livewire::test(InstallWizard::class)->assertSet('isLicenseActive', true);

        config(['app.license_bypass' => false]);

        Livewire::test(InstallWizard::class, ['step' => 'activation'])
            ->assertSet('isLicenseActive', false)
            ->call('checkLicenseAndContinue')
            ->assertHasErrors(['license_key' => 'required'])
            ->assertSet('step', 'activation');

        Http::assertNothingSent();
    }

    public function test_local_runtime_allows_an_authenticated_user_without_a_license(): void
    {
        $this->actingAs(User::factory()->make());

        $response = (new SyncRuntimeMiddleware)->handle(
            Request::create('/'),
            fn (Request $request) => response('Local app')
        );

        $this->assertSame('Local app', $response->getContent());
        Http::assertNothingSent();
        $this->assertFalse(Cache::has('lcs_checked_at'));
    }

    public function test_local_admin_can_open_license_page_without_a_key(): void
    {
        config(['app.installed' => true]);
        $admin = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);

        $this->actingAs($admin)
            ->withSession(['admin_reauthenticated_at' => now()->toDateTimeString()])
            ->get(route('admin.license.index'))
            ->assertOk();

        Http::assertNothingSent();
    }

    #[DataProvider('enforcedEnvironments')]
    public function test_runtime_enforces_license_when_bypass_is_unavailable(string $environment, bool $enabled, bool $demoMode = false): void
    {
        $this->app->instance('env', $environment);
        config([
            'app.license_bypass' => $enabled,
            'app.demo_mode' => $demoMode,
            'app.license_key' => 'WMX-INVALID',
        ]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermission')->with('admin.settings.index')->andReturn(false);
        $this->actingAs($user);

        try {
            (new SyncRuntimeMiddleware)->handle(Request::create('/'), fn (Request $request) => response('Blocked'));
            $this->fail('An invalid license must block access.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        Http::assertSentCount(1);
    }

    public function test_local_license_reporting_is_skipped(): void
    {
        config(['app.license_key' => 'WMX-INVALID']);

        $this->artisan('cronjobs:report-active-check')
            ->expectsOutput('Skipping active license check in local development mode.')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertFalse(Cache::has('last_license_check_reported_at'));
    }

    public function test_production_demo_installer_continues_without_persisting_license_activation(): void
    {
        $this->app->instance('env', 'production');
        config(['app.demo_mode' => true]);
        $environment = file_get_contents(base_path('.env'));

        Livewire::test(InstallWizard::class, ['step' => 'activation'])
            ->assertSee('Development Demo')
            ->assertDontSee('Check License')
            ->call('checkLicenseAndContinue')
            ->assertHasNoErrors()
            ->assertSet('step', 'database');

        Http::assertNothingSent();
        $this->assertFalse(session()->has('installer.license_active'));
        $this->assertFalse(Cache::has('lcs_checked_at'));
        $this->assertSame($environment, file_get_contents(base_path('.env')));
    }

    public function test_production_demo_runtime_bypasses_checks_and_reenforces_them_when_disabled(): void
    {
        $this->app->instance('env', 'production');
        config(['app.demo_mode' => true]);
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermission')->with('admin.settings.index')->andReturn(false);
        $this->actingAs($user);

        $response = (new SyncRuntimeMiddleware)->handle(
            Request::create('/'),
            fn (Request $request) => response('Demo app')
        );

        $this->assertSame('Demo app', $response->getContent());
        Http::assertNothingSent();
        $this->assertFalse(Cache::has('lcs_checked_at'));

        config(['app.demo_mode' => false]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('License Expired');
        (new SyncRuntimeMiddleware)->handle(Request::create('/'), fn (Request $request) => response('Blocked'));
    }

    public function test_production_demo_skips_license_reporting(): void
    {
        $this->app->instance('env', 'production');
        config(['app.demo_mode' => true]);

        $this->artisan('cronjobs:report-active-check')
            ->expectsOutput('Skipping active license check in development demo mode.')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertFalse(Cache::has('last_license_check_reported_at'));
    }

    public function test_demo_admin_can_use_dashboard_and_license_actions_without_a_key(): void
    {
        $this->app->instance('env', 'production');
        config(['app.demo_mode' => true, 'app.installed' => true]);
        $admin = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $environment = file_get_contents(base_path('.env'));

        $this->actingAs($admin)
            ->withSession(['admin_reauthenticated_at' => now()->toDateTimeString()])
            ->get(route('admin.index'))
            ->assertOk();

        $this->get(route('admin.license.index'))
            ->assertOk()
            ->assertSee('Demo mode active')
            ->assertSee('License bypass is enabled')
            ->assertDontSee('Save License')
            ->assertDontSee('Verify License');

        foreach (['admin.license.verify', 'admin.license.update'] as $route) {
            $this->from(route('admin.license.index'))
                ->withSession(['_token' => 'demo-license-test'])
                ->post(route($route), ['_token' => 'demo-license-test'])
                ->assertRedirect(route('admin.license.index'))
                ->assertSessionHasNoErrors()
                ->assertSessionHas('status', 'License checks are bypassed for this development environment.');
        }

        Http::assertNothingSent();
        $this->assertSame($environment, file_get_contents(base_path('.env')));
        $this->assertFalse(Cache::has('license.validation.data'));
    }

    public function test_production_license_page_requires_activation_without_demo_mode(): void
    {
        $this->app->instance('env', 'production');
        config(['app.installed' => true]);
        $admin = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);

        $this->actingAs($admin)
            ->withSession(['admin_reauthenticated_at' => now()->toDateTimeString()])
            ->get(route('admin.license.index'))
            ->assertOk()
            ->assertDontSee('Demo mode active')
            ->assertSee('Save License');

        $this->withSession(['_token' => 'demo-license-test'])
            ->post(route('admin.license.verify'), ['_token' => 'demo-license-test'])
            ->assertSessionHasErrors('license_key');
    }

    /**
     * @return array<string, array{string, bool, bool?: bool}>
     */
    public static function enforcedEnvironments(): array
    {
        return [
            'local without opt-in' => ['local', false],
            'production with opt-in' => ['production', true],
            'staging with opt-in' => ['staging', true],
            'testing with opt-in' => ['testing', true],
            'demo without bypass opt-in' => ['production', false, true],
        ];
    }
}
