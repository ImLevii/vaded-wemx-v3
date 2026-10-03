<?php

namespace Tests\Feature;

use App\Helpers\EnvironmentWriter;
use App\Models\Role;
use App\Models\User;
use App\Services\SmtpConfiguration;
use Dotenv\Dotenv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SmtpConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->admin = User::factory()->create(['status' => 'active']);
        $role = Role::query()->create(['name' => 'SMTP admin', 'super_admin' => true]);
        DB::table('role_user')->insert(['user_id' => $this->admin->id, 'role_id' => $role->id]);
        $this->actingAs($this->admin);
        config(['mail.default' => 'log', 'mail.mailers.smtp.password' => 'saved-secret']);
    }

    public function test_saved_smtp_password_is_not_exposed_in_component_state(): void
    {
        Volt::test(admin_view_path('emails.livewire.configure-smtp-form'))->assertSet('password', '')->assertDontSee('saved-secret');
    }

    public function test_smtp_test_explicitly_uses_smtp_and_preserves_application_configuration(): void
    {
        $mailer = \Mockery::mock(Mailer::class);
        $mailer->shouldReceive('raw')->once()->with('This is a test email', \Mockery::type('Closure'));
        Mail::shouldReceive('purge')->with('smtp');
        Mail::shouldReceive('mailer')->with('smtp')->once()->andReturn($mailer);
        Volt::test(admin_view_path('emails.livewire.configure-smtp-form'))
            ->set('host', 'smtp.example.test')->set('port', 587)->set('encryption', 'tls')
            ->call('testConnection')->assertHasNoErrors()->assertSet('connectionSuccessfull', true);
        $this->assertSame('log', config('mail.default'));
    }

    public function test_failed_smtp_test_cannot_save_even_if_success_flag_is_forged(): void
    {
        $mailer = \Mockery::mock(Mailer::class);
        $mailer->shouldReceive('raw')->once()->andThrow(new \RuntimeException('Credential secret should not be displayed'));
        Mail::shouldReceive('purge')->with('smtp');
        Mail::shouldReceive('mailer')->with('smtp')->once()->andReturn($mailer);
        $service = \Mockery::mock(SmtpConfiguration::class)->makePartial();
        $service->shouldNotReceive('save');
        $this->app->instance(SmtpConfiguration::class, $service);
        Volt::test(admin_view_path('emails.livewire.configure-smtp-form'))
            ->set('host', 'smtp.example.test')->set('port', 587)->set('encryption', 'tls')
            ->set('connectionSuccessfull', true)->call('updateSmtpConfig')
            ->assertSet('connectionSuccessfull', false)->assertSee('SMTP delivery failed')->assertDontSee('Credential secret');
        $this->assertSame('log', config('mail.default'));
    }

    public function test_smtp_settings_are_validated_before_testing(): void
    {
        Volt::test(admin_view_path('emails.livewire.configure-smtp-form'))
            ->set('host', '')->set('port', 70000)->set('mail_from_address', 'invalid')
            ->call('testConnection')->assertHasErrors(['host', 'port', 'mail_from_address']);
    }

    public function test_permission_is_checked_again_for_each_smtp_action(): void
    {
        $component = Volt::test(admin_view_path('emails.livewire.configure-smtp-form'));
        $this->actingAs(User::factory()->create(['status' => 'active']));
        $component->call('updateSmtpConfig')->assertForbidden();
    }

    public function test_saving_switches_the_mailer_and_preserves_password_characters(): void
    {
        $directory = storage_path('framework/testing/smtp-'.bin2hex(random_bytes(8)));
        File::ensureDirectoryExists($directory);
        File::put($directory.'/.env', "MAIL_MAILER=log\nMAIL_PASSWORD=old\n");
        $previousEnvironmentPath = $this->app->environmentPath();
        $previousEnvironmentFile = $this->app->environmentFile();
        $this->app->useEnvironmentPath($directory)->loadEnvironmentFrom('.env');
        try {
            app(SmtpConfiguration::class)->save([
                'host' => 'smtp.example.test', 'port' => 465, 'encryption' => 'ssl',
                'username' => 'sender', 'password' => 'literal$1\\password',
                'mail_from_address' => 'sender@example.test', 'mail_from_name' => 'Hosting',
            ]);
            $contents = File::get($directory.'/.env');
            $this->assertStringContainsString('MAIL_MAILER=smtp', $contents);
            $this->assertStringContainsString('literal\$1', $contents);
            $this->assertStringContainsString('MAIL_SCHEME=smtps', $contents);
            $parsed = Dotenv::parse($contents);
            $this->assertSame('literal$1\\password', $parsed['MAIL_PASSWORD']);
            $this->assertSame('smtp', config('mail.default'));
            $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
            $this->assertSame('literal$1\\password', config('mail.mailers.smtp.password'));
        } finally {
            $this->app->useEnvironmentPath($previousEnvironmentPath)->loadEnvironmentFrom($previousEnvironmentFile);
            File::delete($directory.'/.env');
            File::deleteDirectory($directory);
        }
    }

    #[DataProvider('smtpPasswords')]
    public function test_passwords_round_trip_through_environment_serialization(string $password): void
    {
        $contents = 'OTHER_VALUE=expanded'.PHP_EOL.'MAIL_PASSWORD='.EnvironmentWriter::escapeEnvironmentValue($password);
        $this->assertSame($password, Dotenv::parse($contents)['MAIL_PASSWORD']);
    }

    /** @return array<string, array{0: string}> */
    public static function smtpPasswords(): array
    {
        return [
            'dollar reference' => ['secret${OTHER_VALUE}'],
            'quoted password' => ['"secret"'],
            'apostrophe' => ["it's a password"],
            'backslash' => ['secret\password'],
            'replacement sequence' => ['secret$1'],
            'hash and whitespace' => [' secret# value '],
        ];
    }
}
