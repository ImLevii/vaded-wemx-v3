<?php

namespace App\Jobs\Orders;

use App\Events\Orders\Errors\OrderTerminationFailed;
use App\Events\Orders\OrderTerminated;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class OrderTerminateServer implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 600;

    public function uniqueId(): string
    {
        return (string) $this->order->id;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Order $order,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Cache::lock('order-server-operation:'.$this->order->id, 180)->block(5, function (): void {
            $this->terminate();
        });
    }

    private function terminate(): void
    {
        $this->order->refresh();
        if ($this->order->isTerminated()) {
            return;
        }
        if ($this->order->status === 'processing') {
            throw new RuntimeException('Service provisioning is still in progress. Termination will be retried.');
        }
        // Here we will attempt to terminate the server for the order
        if ($this->order->external_id !== null || ! in_array($this->order->status, ['pending', 'failed', 'cancelled'], true)) {
            $connection = $this->order->package->serverConnection;
            $this->order->serverMethods()->terminate($this->order, $connection);
        }

        // Mark the order as terminated
        $this->order->update([
            'status' => 'terminated',
            'auto_balance_renew' => false,
        ]);

        // notify the user about the termination
        $this->order->emailOrderTermination();

        // dispatch an event that the order has been terminated
        OrderTerminated::dispatch($this->order);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $this->order->exceptions()->create([
            'action' => 'terminate',
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'code' => $exception->getCode(),
        ]);

        // set the order status to failed
        $this->order->update([
            'status' => 'failed',
        ]);

        // Dispatch an event that the order termination has failed
        OrderTerminationFailed::dispatch($this->order, $exception);
    }
}
