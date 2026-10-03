<?php

namespace Tests\Feature;

use App\Jobs\DeliverCustomerMail;
use App\Mail\CustomerMail;
use App\Models\Email;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Symfony\Component\Mailer\Transport\NullTransport;
use Tests\TestCase;

class CustomerMailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function email(): Email
    {
        return Email::query()->create(['to' => 'customer@example.test', 'subject' => 'Service notice', 'lines' => ['Your server is ready.']]);
    }

    public function test_mail_is_queued_after_database_commit(): void
    {
        $email = $this->email();
        Queue::assertPushed(DeliverCustomerMail::class, fn ($job) => $job->email->id === $email->id && $job->afterCommit === true);
    }

    public function test_log_mailer_does_not_claim_inbox_delivery(): void
    {
        config(['mail.default' => 'log']);
        $email = $this->email();
        (new DeliverCustomerMail($email))->handle();
        $this->assertSame('logged', $email->fresh()->status);
    }

    public function test_successful_transport_delivery_updates_the_email_status(): void
    {
        Mail::extend('test_delivery', fn () => new NullTransport);
        config(['mail.default' => 'test_delivery', 'mail.mailers.test_delivery.transport' => 'test_delivery']);
        $email = $this->email();
        (new DeliverCustomerMail($email))->handle();
        $this->assertSame('delivered', $email->fresh()->status);
    }

    public function test_already_delivered_and_read_emails_are_not_sent_again(): void
    {
        Mail::fake();
        foreach (['delivered', 'read'] as $status) {
            $email = $this->email();
            $email->update(['status' => $status]);
            (new DeliverCustomerMail($email))->handle();
            $this->assertSame($status, $email->fresh()->status);
        }
        Mail::assertNothingSent();
    }

    public function test_transport_failure_is_retried_and_final_failure_is_visible(): void
    {
        $email = $this->email();
        $job = new DeliverCustomerMail($email);
        Mail::shouldReceive('to')->with($email->to)->once()->andReturnSelf();
        Mail::shouldReceive('send')->with(\Mockery::type(CustomerMail::class))->once()->andThrow(new RuntimeException('SMTP unavailable'));
        try {
            $job->handle();
            $this->fail('SMTP failure must be propagated for retry.');
        } catch (RuntimeException $exception) {
            $this->assertSame('pending', $email->fresh()->status);
            $this->assertSame(3, $job->tries);
            $job->failed($exception);
            $this->assertSame('failed', $email->fresh()->status);
        }
    }
}
