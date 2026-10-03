<?php

namespace Tests\Feature;

use App\Models\PasswordResetToken;
use App\Models\User;
use App\Services\CustomerServerCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerServerCredentialsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_verified_passwords_are_encrypted_and_hidden(): void
    {
        $user = User::factory()->create();
        $credentials = app(CustomerServerCredentials::class);

        $credentials->capture($user, 'password');
        $user->refresh();

        $this->assertSame('password', $credentials->password($user));
        $this->assertNotSame('password', $user->getRawOriginal('server_password'));
        $this->assertArrayNotHasKey('server_password', $user->toArray());
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_incorrect_passwords_are_never_captured(): void
    {
        $user = User::factory()->create();

        try {
            app(CustomerServerCredentials::class)->capture($user, 'wrong-password');
            $this->fail('An unverified password must not be stored.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('password', $exception->errors());
            $this->assertNull($user->fresh()->server_password);
        }
    }

    public function test_stale_credentials_cannot_be_used_after_a_password_change(): void
    {
        $user = User::factory()->create();
        $credentials = app(CustomerServerCredentials::class);
        $credentials->capture($user, 'password');
        $user->update(['password' => Hash::make('replacement-password')]);

        $this->assertNull($credentials->password($user->fresh()));
    }

    public function test_successful_login_captures_existing_customer_credentials(): void
    {
        $user = User::factory()->create();

        User::authActions()->loginAsClient(['username' => $user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
        $this->assertSame('password', app(CustomerServerCredentials::class)->password($user->fresh()));
    }

    public function test_failed_login_does_not_capture_credentials(): void
    {
        $user = User::factory()->create();

        try {
            User::authActions()->loginAsClient(['username' => $user->email, 'password' => 'wrong-password']);
            $this->fail('An incorrect password must not log in.');
        } catch (ValidationException $exception) {
            $this->assertGuest();
            $this->assertNull($user->fresh()->server_password);
        }
    }

    public function test_registration_captures_the_customer_password(): void
    {
        $user = User::authActions()->registerAsClient([
            'first_name' => 'Alice', 'last_name' => 'Customer',
            'username' => 'alicecustomer', 'email' => 'alice@example.test',
            'password' => 'registered-password', 'password_confirmation' => 'registered-password',
        ]);

        $this->assertSame('registered-password', app(CustomerServerCredentials::class)->password($user->fresh()));
        $this->assertTrue(Hash::check('registered-password', $user->password));
    }

    public function test_password_reset_updates_captured_credentials(): void
    {
        $user = User::factory()->create();
        $user->emailPasswordResetLink();
        $token = PasswordResetToken::where('email', $user->email)->firstOrFail();

        User::authActions()->resetPasswordAsClient([
            'token' => $token->token,
            'password' => 'reset-password', 'password_confirmation' => 'reset-password',
        ]);

        $this->assertSame('reset-password', app(CustomerServerCredentials::class)->password($user->fresh()));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_customer_password_changes_update_captured_credentials(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        User::actions()->updatePasswordAsClient([
            'user_id' => $user->id, 'current_password' => 'password',
            'new_password' => 'changed-password', 'new_password_confirmation' => 'changed-password',
        ]);

        $this->assertSame('changed-password', app(CustomerServerCredentials::class)->password($user->fresh()));
    }

    public function test_admin_created_customers_have_matching_server_credentials(): void
    {
        $user = User::actions()->createUserAsAdmin([
            'first_name' => 'Alice', 'last_name' => 'Customer',
            'username' => 'alicecustomer', 'email' => 'alice@example.test',
            'password' => 'created-password',
        ]);

        $this->assertSame('created-password', app(CustomerServerCredentials::class)->password($user->fresh()));
    }

    public function test_admin_password_changes_update_captured_credentials(): void
    {
        $user = User::factory()->create();

        User::actions()->updateUserAsAdmin([
            'user_id' => $user->id, 'first_name' => 'Alice', 'last_name' => 'Customer',
            'username' => $user->username, 'email' => $user->email,
            'password' => 'admin-changed-password',
        ]);

        $this->assertSame('admin-changed-password', app(CustomerServerCredentials::class)->password($user->fresh()));
    }
}
