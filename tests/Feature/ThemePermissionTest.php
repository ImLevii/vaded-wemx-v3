<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ThemePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.installed' => true, 'app.license_key' => 'WMX-TESTING-KEY']);
        Cache::put('lcs_checked_at', now(), 21600);
    }

    public function test_guests_use_automatic_themes_without_manual_controls(): void
    {
        foreach (['/', '/auth/login'] as $path) {
            $this->get($path.'?theme=dark')
                ->assertOk()
                ->assertSee('<meta name="wemx-theme-control" content="automatic">', false)
                ->assertDontSee('onclick="toggleDarkmode()"', false);
        }
    }

    public function test_customers_have_no_manual_controls_on_client_and_payment_pages(): void
    {
        $customer = User::factory()->create(['id' => 2, 'status' => 'active', 'language' => 'en']);
        $this->actingAs($customer);

        foreach (['categories.index', 'dashboard', 'payments.completed'] as $routeName) {
            $this->get(route($routeName))
                ->assertOk()
                ->assertSee('<meta name="wemx-theme-control" content="automatic">', false)
                ->assertDontSee('onclick="toggleDarkmode()"', false);
        }
    }

    public function test_primary_admins_can_choose_the_theme_on_client_and_payment_pages(): void
    {
        $admin = User::factory()->create(['id' => 1, 'status' => 'active', 'language' => 'en']);
        $this->actingAs($admin);

        foreach (['categories.index', 'dashboard', 'payments.completed'] as $routeName) {
            $this->get(route($routeName))
                ->assertOk()
                ->assertSee('<meta name="wemx-theme-control" content="manual">', false)
                ->assertSee('onclick="toggleDarkmode()"', false);
        }
    }

    public function test_staff_without_dashboard_permission_use_automatic_themes(): void
    {
        $staff = User::factory()->create(['id' => 2, 'status' => 'active', 'language' => 'en']);
        $role = Role::query()->create(['name' => 'Support', 'super_admin' => false]);
        $staff->roles()->create(['role_id' => $role->id]);

        $this->actingAs($staff)->get('/')
            ->assertOk()
            ->assertSee('<meta name="wemx-theme-control" content="automatic">', false)
            ->assertDontSee('onclick="toggleDarkmode()"', false);
    }

    public function test_staff_with_dashboard_permission_can_choose_the_theme(): void
    {
        $staff = User::factory()->create(['id' => 2, 'status' => 'active', 'language' => 'en']);
        $role = Role::query()->create(['name' => 'Dashboard administrators', 'super_admin' => false]);
        $role->permissions()->create(['permission' => 'admin.dashboard']);
        $staff->roles()->create(['role_id' => $role->id]);

        $this->actingAs($staff)->get('/')
            ->assertOk()
            ->assertSee('<meta name="wemx-theme-control" content="manual">', false)
            ->assertSee('aria-label="Toggle color theme"', false);
    }

    public function test_role_based_super_admins_can_choose_the_theme(): void
    {
        $admin = User::factory()->create(['id' => 2, 'status' => 'active', 'language' => 'en']);
        $role = Role::query()->create(['name' => 'Super administrators', 'super_admin' => true]);
        $admin->roles()->create(['role_id' => $role->id]);

        $this->actingAs($admin)->get('/')
            ->assertOk()
            ->assertSee('<meta name="wemx-theme-control" content="manual">', false)
            ->assertSee('aria-label="Toggle color theme"', false);
    }

    public function test_admin_area_and_reauthentication_share_the_manual_theme_policy(): void
    {
        $admin = User::factory()->create(['id' => 1, 'status' => 'active', 'language' => 'en']);
        $this->actingAs($admin)->withSession(['admin_reauthenticated_at' => now()->toDateTimeString()]);

        $this->get(route('admin.index'))
            ->assertOk()
            ->assertSee('<meta name="wemx-theme-control" content="manual">', false)
            ->assertSee('?theme=dark', false)
            ->assertSee('?theme=light', false);

        $this->get(route('admin.reauthenticate'))
            ->assertOk()
            ->assertSee('<meta name="wemx-theme-control" content="manual">', false)
            ->assertSee('assets/common/js/vaded-theme.js');
    }
}
