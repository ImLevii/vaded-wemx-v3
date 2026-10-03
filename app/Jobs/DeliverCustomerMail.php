<?php

namespace App\Jobs;

use App\Mail\CustomerMail;
use App\Models\Email;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Mail\Transport\LogTransport;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DeliverCustomerMail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Email $email,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->email->refresh();
        if (in_array($this->email->status, ['delivered', 'read'], true)) {
            return;
        }

        Mail::to($this->email->to)->send(new CustomerMail($this->email));
        $transport = Mail::mailer()->getSymfonyTransport();
        if ($transport instanceof LogTransport || $transport instanceof ArrayTransport) {
            $this->email->update(['status' => 'logged']);
        } else {
            $this->email->markAsDelivered();
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        // mark email as failed in the system
        $this->email->markAsFailed();
    }
}
