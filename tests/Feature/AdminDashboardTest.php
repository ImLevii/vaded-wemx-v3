<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_when_admin_assets_are_served_outside_the_php_public_directory(): void
    {
        config(['app.installed' => true, 'app.license_key' => 'WMX-TESTING-KEY']);
        Cache::put('lcs_checked_at', now(), 21600);

        $admin = User::factory()->create(['id' => 1, 'status' => 'active', 'language' => 'en']);
        $originalPublicPath = public_path();
        $runtimePublicPath = sys_get_temp_dir().'/wemx-admin-assets-'.bin2hex(random_bytes(8));

        try {
            foreach (['css/vaded-theme.css', 'js/vaded-theme.js'] as $asset) {
                $destination = $runtimePublicPath.'/assets/common/'.$asset;
                File::ensureDirectoryExists(dirname($destination));
                File::copy(public_path('assets/common/'.$asset), $destination);
            }

            $this->app->usePublicPath($runtimePublicPath);
            $this->assertFileDoesNotExist(public_path('assets/adminarea/default/css/vaded.css'));

            $response = $this->actingAs($admin)
                ->withSession(['admin_reauthenticated_at' => now()->toDateTimeString()])
                ->get(route('admin.index'));

            $response->assertOk()
                ->assertViewIs('admin::dashboard.index')
                ->assertSee('Administration')
                ->assertSee(admin_asset('css/vaded.css'));
        } finally {
            $this->app->usePublicPath($originalPublicPath);
            File::deleteDirectory($runtimePublicPath);
        }
    }
}
