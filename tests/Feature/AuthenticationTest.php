<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CustomerServerCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Volt\Volt;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.installed' => true, 'app.license_key' => 'WMX-TESTING-KEY', 'queue.default' => 'sync']);
        Cache::put('lcs_checked_at', now(), 21600);
        Mail::fake();
    }

    public function test_login_succeeds_by_email_and_username_and_persists_the_session(): void
    {
        $user = User::factory()->create(['status' => 'active', 'language' => 'en']);

        foreach ([$user->email, $user->username] as $identifier) {
            auth()->logout();
            Volt::test(client_view_path('auth.livewire.login-form'))
                ->set('username', $identifier)->set('password', 'password')
                ->call('handleLogin')->assertHasNoErrors()->assertRedirect(route('dashboard'));
            $this->assertAuthenticatedAs($user);
            $this->get(route('dashboard'))->assertOk();
        }
    }

    public function test_invalid_password_does_not_authenticate(): void
    {
        $user = User::factory()->create();
        Volt::test(client_view_path('auth.livewire.login-form'))
            ->set('username', $user->email)->set('password', 'wrong-password')
            ->call('handleLogin')->assertHasErrors('username')->assertNoRedirect();
        $this->assertGuest();
    }

    public function test_login_notification_failure_does_not_block_valid_credentials(): void
    {
        $user = User::factory()->create();
        $this->simulateMailFailure();
        Volt::test(client_view_path('auth.livewire.login-form'))
            ->set('username', $user->email)->set('password', 'password')
            ->call('handleLogin')->assertHasNoErrors()->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_verification_delivery_failure_does_not_leave_registration_reported_as_failed(): void
    {
        User::factory()->create();
        $this->simulateMailFailure();
        $this->registration()->call('handleRegistration')->assertHasNoErrors()->assertRedirect(route('dashboard'));
        $user = User::query()->where('email', 'newcustomer@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->verification_token);
        $this->assertNull($user->email_verified_at);
        $this->get(route('dashboard'))->assertRedirect(route('verify-email'));
    }

    public function test_failed_account_setup_rolls_back_the_user_and_address(): void
    {
        $credentials = Mockery::mock(CustomerServerCredentials::class);
        $credentials->shouldReceive('capture')->once()->andThrow(new RuntimeException('Credential storage unavailable'));
        $this->app->instance(CustomerServerCredentials::class, $credentials);
        $this->registration()->call('handleRegistration')->assertHasErrors('email')->assertNoRedirect();
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'newcustomer@example.test']);
        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_existing_migration_repairs_the_production_missing_column_failure(): void
    {
        $user = User::factory()->create();
        $migration = require database_path('migrations/2026_10_03_155014_add_server_password_to_users_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'server_password'));
        $migration->up();
        $this->assertTrue(Schema::hasColumn('users', 'server_password'));
        Volt::test(client_view_path('auth.livewire.login-form'))
            ->set('username', $user->email)->set('password', 'password')
            ->call('handleLogin')->assertHasNoErrors()->assertRedirect(route('dashboard'));
        auth()->logout();
        $this->registration()->call('handleRegistration')->assertHasNoErrors()->assertRedirect(route('dashboard'));
        $registered = User::query()->where('email', 'newcustomer@example.test')->sole();
        $this->assertTrue(Hash::check('securepassword', $registered->password));
        $this->assertSame('securepassword', app(CustomerServerCredentials::class)->password($registered));
    }

    private function simulateMailFailure(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Mail transport unavailable'));
    }

    private function registration(): Testable
    {
        return Volt::test(client_view_path('auth.livewire.register-form'))
            ->set('first_name', 'Jamie')->set('last_name', 'Taylor')
            ->set('username', 'newcustomer')->set('email', 'newcustomer@example.test')
            ->set('password', 'securepassword')->set('password_confirmation', 'securepassword');
    }
}
