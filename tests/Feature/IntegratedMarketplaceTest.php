<?php

namespace Tests\Feature;

use App\Models\Extension;
use App\Models\IntegratedMarketplaceInstallation;
use App\Models\Role;
use App\Models\User;
use App\Services\IntegratedMarketplace;
use App\Services\IntegratedMarketplaceInstaller;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class IntegratedMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.installed' => true,
            'app.license_key' => 'WMX-TESTING-KEY',
            'services.marketplace.url' => 'http://wemx.test',
        ]);

        Cache::put('lcs_checked_at', now(), 21600);
        Cache::flush();
        Cache::put('lcs_checked_at', now(), 21600);

        Http::preventStrayRequests();
        Http::fake([
            'http://wemx.test/api/v1/marketplace/account' => Http::response([
                'account' => [
                    'username' => 'buyer',
                    'email' => 'buyer@example.com',
                ],
            ]),
        ]);
    }

    public function test_admin_can_browse_cached_marketplace_resources(): void
    {
        Http::fake([
            'http://wemx.test/api/v1/marketplace/resources*' => Http::response($this->catalogPayload()),
        ]);

        $this->actingAsMarketplaceAdmin()
            ->get(route('admin.marketplace.index'))
            ->assertOk()
            ->assertSee('Demo module')
            ->assertSee('Connected as')
            ->assertSee('buyer@example.com')
            ->assertSee('Featured')
            ->assertSee('Modules')
            ->assertSee('1 purchases')
            ->assertDontSee('<h2 class="mb-1">Marketplace</h2>', false);

        $this->actingAsMarketplaceAdmin()
            ->get(route('admin.marketplace.index'))
            ->assertOk();

        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer WMX-TESTING-KEY'));

        $cached = Cache::get('integrated-marketplace.catalog.'.md5("http://wemx.test\n".json_encode([
            'sort_by' => 'popular',
            'page' => 1,
            'per_page' => 18,
        ])));

        $this->assertIsArray($cached);
        $this->assertArrayNotHasKey('description', $cached['resources'][0]);
        $this->assertArrayNotHasKey('versions', $cached['resources'][0]);
        $this->assertSame('1.0.0', $cached['resources'][0]['latest_version']);
    }

    public function test_catalog_includes_every_category_and_only_installs_supported_ones(): void
    {
        $payload = $this->catalogPayload();
        $theme = $this->resourcePayload();
        $theme['name'] = 'Client theme';
        $theme['slug'] = 'client-theme';
        $theme['category'] = ['slug' => 'client-theme', 'name' => 'Client themes'];
        $payload['data'][] = $theme;
        $payload['categories'][] = ['slug' => 'client-theme', 'name' => 'Client themes'];
        $payload['featured'][] = $theme;

        Http::fake([
            'http://wemx.test/api/v1/marketplace/resources/client-theme/view' => Http::response(['views' => 1]),
            'http://wemx.test/api/v1/marketplace/resources/client-theme' => Http::response([
                'data' => $theme,
            ]),
            'http://wemx.test/api/v1/marketplace/resources*' => Http::response($payload),
        ]);

        $catalog = app(IntegratedMarketplace::class)->catalog(['category' => 'client-theme']);

        $this->assertSame(['demo-module', 'client-theme'], collect($catalog['resources'])->pluck('slug')->all());
        $this->assertSame(['demo-module', 'client-theme'], collect($catalog['featured'])->pluck('slug')->all());
        $this->assertSame(['module', 'client-theme'], collect($catalog['categories'])->pluck('slug')->all());
        $this->assertTrue(app(IntegratedMarketplace::class)->canInstall($this->resourcePayload()));
        $this->assertFalse(app(IntegratedMarketplace::class)->canInstall($theme));

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'category=client-theme'));

        $this->assertSame('Client theme', app(IntegratedMarketplace::class)->resource('client-theme')['resource']['name']);

        $this->actingAsMarketplaceAdmin();

        Volt::test('admin_area.default.integrated-marketplace.livewire.resource', ['slug' => 'client-theme'])
            ->assertSee('Client theme')
            ->assertDontSee('Install 1.0.0')
            ->call('openInstall', 9)
            ->assertSet('installVersionId', null);
    }

    public function test_admin_resource_page_has_sections_and_marketplace_link(): void
    {
        Http::fake([
            'http://wemx.test/api/v1/marketplace/resources/demo-module/view' => Http::response(['views' => 1]),
            'http://wemx.test/api/v1/marketplace/resources/demo-module' => Http::response([
                'data' => $this->resourcePayload(),
            ]),
        ]);

        $this->actingAsMarketplaceAdmin()
            ->get(route('admin.marketplace.show', 'demo-module'))
            ->assertOk()
            ->assertSee('Resource')
            ->assertSee('Versions')
            ->assertSee('Reviews')
            ->assertSee('View on marketplace')
            ->assertSee('http://wemx.test/marketplace/module/demo-module', false);

        Http::assertSent(fn ($request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/demo-module/view'));

        Volt::test('admin_area.default.integrated-marketplace.livewire.resource', ['slug' => 'demo-module'])
            ->assertSee('A demo resource.')
            ->call('setTab', 'versions')
            ->assertSee('Initial release')
            ->assertSee('v1.0.0')
            ->assertSee('Install 1.0.0')
            ->call('setTab', 'reviews')
            ->assertSee('Great free tool')
            ->assertSee('Works well.');
    }

    public function test_resource_page_prompts_when_the_account_lacks_access(): void
    {
        $payload = $this->resourcePayload();
        $payload['price'] = '$12.00';
        $payload['has_access'] = false;

        Http::fake([
            'http://wemx.test/api/v1/marketplace/resources/demo-module/view' => Http::response(['views' => 1]),
            'http://wemx.test/api/v1/marketplace/resources/demo-module' => Http::response([
                'data' => $payload,
            ]),
        ]);

        $this->actingAsMarketplaceAdmin();

        Volt::test('admin_area.default.integrated-marketplace.livewire.resource', ['slug' => 'demo-module'])
            ->assertSee('Your account does not have access to this resource.')
            ->assertSee('buyer@example.com')
            ->assertDontSee('Install 1.0.0')
            ->call('openInstall', 9)
            ->assertSet('installVersionId', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Your account does not have access to this resource.');

        app(IntegratedMarketplaceInstaller::class)->install('demo-module', 9);
    }

    public function test_resource_page_offers_install_for_the_latest_version(): void
    {
        Http::fake([
            'http://wemx.test/api/v1/marketplace/resources/demo-module/view' => Http::response(['views' => 1]),
            'http://wemx.test/api/v1/marketplace/resources/demo-module' => Http::response([
                'data' => $this->resourcePayload(),
            ]),
        ]);

        $this->actingAsMarketplaceAdmin()
            ->get(route('admin.marketplace.show', 'demo-module'))
            ->assertOk()
            ->assertSee('Install 1.0.0');
    }

    public function test_one_click_install_extracts_a_compatible_version_and_enables_it(): void
    {
        $zip = $this->moduleZip();

        Http::fake([
            'http://wemx.test/api/v1/marketplace/resources/one-click-demo' => Http::response([
                'data' => $this->installableResource(hash_file('sha256', $zip)),
            ]),
            'http://wemx.test/api/v1/marketplace/resources/download/9' => Http::response(file_get_contents($zip), 200, [
                'Content-Type' => 'application/zip',
            ]),
        ]);

        IntegratedMarketplaceInstallation::query()->create([
            'resource_slug' => 'one-click-demo',
            'resource_name' => 'One Click Demo',
            'version' => '0.1.0',
            'latest_version' => '2.0.0',
            'update_available' => true,
            'path' => 'extensions/Modules/OneClickDemo',
            'installed_at' => now()->subHour(),
        ]);

        IntegratedMarketplaceInstallation::query()->create([
            'resource_slug' => 'older-demo',
            'resource_name' => 'Older Demo',
            'version' => '0.1.0',
            'namespace' => 'Extensions\\Modules\\OlderDemo\\Module',
            'path' => 'extensions/Modules/OlderDemo',
            'installed_at' => now()->subDay(),
        ]);

        $this->actingAsMarketplaceAdmin();

        $message = app(IntegratedMarketplaceInstaller::class)->install('one-click-demo', 9);

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer WMX-TESTING-KEY')
            && str_contains($request->url(), '/resources/download/9'));

        $this->assertSame('One Click Demo 1.0.0 was installed.', $message);
        $this->assertTrue(
            IntegratedMarketplaceInstallation::query()->where('resource_slug', 'one-click-demo')->value('update_available')
        );
        $this->assertSame('2.0.0', IntegratedMarketplaceInstallation::query()->where('resource_slug', 'one-click-demo')->value('latest_version'));
        $this->assertFileExists(base_path('extensions/Modules/OneClickDemo/Module.php'));
        $this->assertSame('enabled', Extension::query()->where('identifier', 'one-click-demo')->value('status'));
        $this->assertTrue(IntegratedMarketplaceInstallation::query()->where('resource_slug', 'one-click-demo')->first()?->isPresent());

        $this->get(route('admin.marketplace.installed'))
            ->assertOk()
            ->assertSeeInOrder(['One Click Demo', 'Older Demo'])
            ->assertSee('Successfully installed')
            ->assertSee('Extensions\\Modules\\OneClickDemo\\Module', false);

        Extension::query()->where('identifier', 'one-click-demo')->delete();
        File::deleteDirectory(base_path('extensions/Modules/OneClickDemo'));

        $this->get(route('admin.marketplace.installed'))
            ->assertOk()
            ->assertDontSee('Successfully installed')
            ->assertSee('Was installed, but the namespace/extension could not be found');

        @unlink($zip);
    }

    public function test_one_click_install_rejects_an_incompatible_wemx_version(): void
    {
        $payload = $this->installableResource(null);
        $payload['versions'][0]['wemx_version'] = '9.9.9';

        Http::fake([
            'http://wemx.test/api/v1/marketplace/resources/one-click-demo' => Http::response(['data' => $payload]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This version requires WemX 9.9.9.');

        app(IntegratedMarketplaceInstaller::class)->install('one-click-demo', 9);
    }

    public function test_one_click_install_rejects_categories_without_an_install_button(): void
    {
        $payload = $this->installableResource(null);
        $payload['category'] = ['slug' => 'client-theme', 'name' => 'Client themes'];

        Http::fake([
            'http://wemx.test/api/v1/marketplace/resources/one-click-demo' => Http::response(['data' => $payload]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This resource cannot be installed from the marketplace.');

        app(IntegratedMarketplaceInstaller::class)->install('one-click-demo', 9);
    }

    public function test_resource_page_offers_install_for_email_and_invoice_themes(): void
    {
        $email = $this->resourcePayload();
        $email['slug'] = 'welcome-emails';
        $email['name'] = 'Welcome emails';
        $email['category'] = ['slug' => 'email-theme', 'name' => 'Email Theme'];
        $email['versions'][0]['extract_path'] = 'resources/email_templates';
        $email['versions'][0]['rename_extract_to'] = 'welcome';

        $invoice = $this->resourcePayload();
        $invoice['slug'] = 'accent-invoice';
        $invoice['name'] = 'Accent invoice';
        $invoice['category'] = ['slug' => 'invoice-theme', 'name' => 'Invoice Theme'];
        $invoice['versions'][0]['extract_path'] = 'resources/invoices';
        $invoice['versions'][0]['rename_extract_to'] = '';

        Http::fake([
            'http://wemx.test/api/v1/marketplace/resources/welcome-emails/view' => Http::response(['views' => 1]),
            'http://wemx.test/api/v1/marketplace/resources/welcome-emails' => Http::response(['data' => $email]),
            'http://wemx.test/api/v1/marketplace/resources/accent-invoice/view' => Http::response(['views' => 1]),
            'http://wemx.test/api/v1/marketplace/resources/accent-invoice' => Http::response(['data' => $invoice]),
        ]);

        $this->actingAsMarketplaceAdmin();

        Volt::test('admin_area.default.integrated-marketplace.livewire.resource', ['slug' => 'welcome-emails'])
            ->assertSee('Install 1.0.0')
            ->call('openInstall', 9)
            ->assertSee('resources/email_templates/welcome')
            ->assertSee('WemX then adds it to the list of email themes.');

        Volt::test('admin_area.default.integrated-marketplace.livewire.resource', ['slug' => 'accent-invoice'])
            ->assertSee('Install 1.0.0')
            ->call('openInstall', 9)
            ->assertSee('resources/invoices')
            ->assertSee('The folder name comes from the archive.')
            ->assertSee('WemX then adds it to the list of invoice themes.');
    }

    public function test_one_click_install_rejects_a_version_that_is_not_on_the_integrated_marketplace(): void
    {
        $payload = $this->installableResource(null);
        $payload['versions'][0]['integrated_marketplace'] = false;

        Http::fake([
            'http://wemx.test/api/v1/marketplace/resources/one-click-demo' => Http::response(['data' => $payload]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not available on the integrated marketplace');

        app(IntegratedMarketplaceInstaller::class)->install('one-click-demo', 9);
    }

    public function test_catalog_errors_are_not_cached(): void
    {
        Http::fake([
            'http://wemx.test/api/v1/marketplace/resources*' => Http::response(['message' => 'down'], 500),
        ]);

        $catalog = app(IntegratedMarketplace::class)->catalog();

        $this->assertNotNull($catalog['error']);
        $this->assertNull(Cache::get('integrated-marketplace.catalog.'.md5("http://wemx.test\n".json_encode([
            'sort_by' => 'popular',
            'page' => 1,
            'per_page' => 18,
        ]))));
    }

    public function test_installed_resources_are_marked_on_the_card_and_install_warns_about_the_current_version(): void
    {
        Http::fake([
            'http://wemx.test/api/v1/marketplace/resources/demo-module/view' => Http::response(['views' => 1]),
            'http://wemx.test/api/v1/marketplace/resources/demo-module' => Http::response([
                'data' => $this->resourcePayload(),
            ]),
            'http://wemx.test/api/v1/marketplace/resources*' => Http::response($this->catalogPayload()),
        ]);

        IntegratedMarketplaceInstallation::query()->create([
            'resource_slug' => 'demo-module',
            'resource_name' => 'Demo module',
            'version' => '0.9.0',
            'path' => 'app',
            'installed_at' => now(),
        ]);

        $this->actingAsMarketplaceAdmin();

        Volt::test('admin_area.default.integrated-marketplace.livewire.browse')
            ->assertSee('Installed')
            ->assertSee('Demo module');

        Volt::test('admin_area.default.integrated-marketplace.livewire.resource', ['slug' => 'demo-module'])
            ->assertSee('Installed')
            ->assertSee('Installed version 0.9.0')
            ->call('openInstall', 9)
            ->assertSee('This resource is already installed. Version 0.9.0 is currently on this site.')
            ->assertSee('How this will be installed')
            ->assertSee('extensions/Modules/DemoModule')
            ->assertSee('WemX then enables the module and runs its migrations.');
    }

    public function test_removed_installations_are_not_shown_as_installed(): void
    {
        Http::fake([
            'http://wemx.test/api/v1/marketplace/resources/demo-module/view' => Http::response(['views' => 1]),
            'http://wemx.test/api/v1/marketplace/resources/demo-module' => Http::response([
                'data' => $this->resourcePayload(),
            ]),
        ]);

        IntegratedMarketplaceInstallation::query()->create([
            'resource_slug' => 'demo-module',
            'resource_name' => 'Demo module',
            'version' => '0.9.0',
            'path' => 'extensions/Modules/MissingDemo',
            'installed_at' => now(),
        ]);

        $this->actingAsMarketplaceAdmin();

        Volt::test('admin_area.default.integrated-marketplace.livewire.resource', ['slug' => 'demo-module'])
            ->assertDontSee('Installed version')
            ->call('openInstall', 9)
            ->assertDontSee('already installed');
    }

    public function test_daily_check_records_marketplace_updates_for_installed_resources(): void
    {
        Http::fake([
            'http://wemx.test/api/v1/marketplace/resources/demo-module' => Http::response([
                'data' => [
                    'slug' => 'demo-module',
                    'versions' => [
                        ['version' => '1.0.0', 'integrated_marketplace' => true],
                        ['version' => '1.2.0', 'integrated_marketplace' => true],
                        ['version' => '9.0.0', 'integrated_marketplace' => false],
                    ],
                ],
            ]),
            'http://wemx.test/api/v1/marketplace/resources/current-module' => Http::response([
                'data' => [
                    'slug' => 'current-module',
                    'versions' => [
                        ['version' => '2.0.0', 'integrated_marketplace' => true],
                    ],
                ],
            ]),
        ]);

        $outdated = IntegratedMarketplaceInstallation::query()->create([
            'resource_slug' => 'demo-module',
            'resource_name' => 'Demo module',
            'version' => '1.0.0',
            'path' => 'app',
            'installed_at' => now(),
        ]);
        $current = IntegratedMarketplaceInstallation::query()->create([
            'resource_slug' => 'current-module',
            'resource_name' => 'Current module',
            'version' => 'v2.0.0',
            'path' => 'app',
            'installed_at' => now(),
        ]);
        $removed = IntegratedMarketplaceInstallation::query()->create([
            'resource_slug' => 'removed-module',
            'resource_name' => 'Removed module',
            'version' => '1.0.0',
            'path' => 'extensions/Modules/MissingDemo',
            'update_available' => true,
            'installed_at' => now(),
        ]);

        $this->artisan('cronjobs:check-marketplace-updates')
            ->expectsOutput('1 installed marketplace resource has an update ready.')
            ->assertSuccessful();

        $outdated->refresh();
        $current->refresh();
        $removed->refresh();

        $this->assertTrue($outdated->update_available);
        $this->assertSame('1.2.0', $outdated->latest_version);
        $this->assertNotNull($outdated->update_checked_at);
        $this->assertFalse($current->update_available);
        $this->assertSame('2.0.0', $current->latest_version);
        $this->assertFalse($removed->update_available);

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'cronjobs:check-marketplace-updates'));

        $this->assertNotNull($event);
        $this->assertSame('0 0 * * *', $event->expression);
    }

    public function test_marketplace_update_toast_lists_installed_resources_with_updates(): void
    {
        IntegratedMarketplaceInstallation::query()->create([
            'resource_slug' => 'demo-module',
            'resource_name' => 'Demo module',
            'version' => '1.0.0',
            'latest_version' => '1.2.0',
            'update_available' => true,
            'path' => 'app',
            'installed_at' => now(),
        ]);
        IntegratedMarketplaceInstallation::query()->create([
            'resource_slug' => 'invoice-accent',
            'resource_name' => 'Accent invoice',
            'version' => '1.0.0',
            'latest_version' => '1.1.0',
            'update_available' => true,
            'path' => 'app',
            'installed_at' => now(),
        ]);

        $this->actingAsMarketplaceAdmin();

        Volt::test('admin_area.default.livewire.toasts')
            ->assertSee('2 resources you installed from the marketplace have an update ready.')
            ->assertSee('Demo module')
            ->assertSee('version 1.0.0 installed')
            ->assertSee('version 1.2.0 available')
            ->assertSee('Accent invoice')
            ->assertSee('View installed resources')
            ->assertSee(route('admin.marketplace.installed'), false);

        $this->actingAsMarketplaceAdmin()
            ->get(route('admin.marketplace.installed'))
            ->assertOk()
            ->assertSee('Update available')
            ->assertSee('Version 1.2.0 is ready to install')
            ->assertSee('Version 1.1.0 is ready to install');
    }

    /**
     * @return array<string, mixed>
     */
    protected function catalogPayload(): array
    {
        return [
            'current_page' => 1,
            'last_page' => 1,
            'total' => 1,
            'data' => [$this->resourcePayload()],
            'categories' => [
                ['slug' => 'module', 'name' => 'Modules'],
            ],
            'featured' => [$this->resourcePayload()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function resourcePayload(): array
    {
        return [
            'id' => 1,
            'name' => 'Demo module',
            'slug' => 'demo-module',
            'short_description' => 'Adds extra client tools.',
            'description' => 'A demo resource.',
            'icon' => null,
            'initials' => 'DM',
            'price' => 'Free',
            'featured' => true,
            'official' => true,
            'views' => 12,
            'downloads' => 4,
            'purchases' => 1,
            'reviews_count' => 1,
            'reviews_avg' => 5,
            'source' => null,
            'website' => 'https://example.com',
            'docs' => null,
            'support' => null,
            'view_url' => 'http://wemx.test/marketplace/module/demo-module',
            'category' => ['id' => 1, 'slug' => 'module', 'name' => 'Modules'],
            'user' => ['username' => 'creator', 'avatar' => null, 'url' => null],
            'versions' => [[
                'id' => 9,
                'name' => 'Initial release',
                'version' => '1.0.0',
                'wemx_version' => '*',
                'changelog' => 'First public build.',
                'created_at' => now()->toIso8601String(),
                'integrated_marketplace' => true,
                'extract_path' => 'extensions/Modules',
                'rename_extract_to' => 'DemoModule',
                'size_label' => '40.0 KB',
            ]],
            'reviews' => [[
                'id' => 3,
                'rating' => 5,
                'title' => 'Great free tool',
                'body' => 'Works well.',
                'created_at' => now()->toIso8601String(),
                'user' => ['username' => 'buyer', 'avatar' => null],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function installableResource(?string $checksum): array
    {
        return [
            'name' => 'One Click Demo',
            'slug' => 'one-click-demo',
            'price' => 'Free',
            'category' => ['slug' => 'module', 'name' => 'Modules'],
            'versions' => [[
                'id' => 9,
                'version' => '1.0.0',
                'wemx_version' => '*',
                'integrated_marketplace' => true,
                'extract_path' => 'extensions/Modules',
                'rename_extract_to' => 'OneClickDemo',
                'checksum' => $checksum,
            ]],
        ];
    }

    protected function moduleZip(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mkt');
        $source = tempnam(sys_get_temp_dir(), 'mod');
        file_put_contents($source, <<<'PHP'
<?php

namespace Extensions\Modules\OneClickDemo;

use App\Extensions\Foundation\ModuleExtension;

class Module extends ModuleExtension
{
    protected string $id = 'one-click-demo';

    protected string $name = 'One Click Demo';

    protected string $description = 'Installed from the marketplace.';

    protected string $version = '1.0.0';

    protected string $marketplace_id = '9';

    protected array $wemxVersions = ['*'];

    public function elements(): array
    {
        return [];
    }

    public function onInstall(): void
    {
    }

    public function onUninstall(): void
    {
    }

    public function onEnable(): void
    {
    }

    public function onDisable(): void
    {
    }
}
PHP);

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFile($source, 'OneClickDemo/Module.php');
        $zip->close();
        @unlink($source);

        return $path;
    }

    protected function actingAsMarketplaceAdmin(): self
    {
        $admin = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $role = Role::query()->create([
            'name' => 'Marketplace '.$admin->id,
            'super_admin' => true,
        ]);

        DB::table('role_user')->insert([
            'role_id' => $role->id,
            'user_id' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->actingAs($admin->fresh())->withSession([
            'admin_reauthenticated_at' => now()->toDateTimeString(),
        ]);
    }
}
