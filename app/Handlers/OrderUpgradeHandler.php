<?php

namespace App\Handlers;

use App\Models\Payment;
use App\Services\OrderUpgradeService;
use Throwable;

class OrderUpgradeHandler
{
    public function onPaymentCompleted(Payment $payment): void
    {
        try {
            app(OrderUpgradeService::class)->apply($payment);
        } catch (Throwable $exception) {
            report($exception);
            $payment->refresh();
            if ($payment->data('upgrade_applied_at')) {
                return;
            }
            $payment->update(['data' => array_merge($payment->data ?? [], ['upgrade_failed' => true])]);
            $payment->payable?->exceptions()->create(['action' => 'upgrade', 'message' => 'Paid upgrade could not be applied. Payment #'.$payment->id]);
        }
    }
}
