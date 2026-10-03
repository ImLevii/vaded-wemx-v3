<?php

namespace App\Actions;

use App\Events\Payments\PaymentRefunded;
use App\Handlers\BalanceTopupHandler;
use App\Handlers\OrderRenewalHandler;
use App\Models\GatewayConfig;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\PaymentTaxDetail;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentActions extends Action
{
    /**
     * Create a new payment.
     *
     * @throws ValidationException
     */
    public static function createPaymentAsAdmin(array $input)
    {
        $validatedData = Validator::make($input, [
            'user_id' => ['nullable', 'exists:users,id'],
            'description' => ['required', 'string'],
            'currency' => ['required', 'string', 'size:3'],
            'subtotal' => ['required', 'numeric', 'min:0'],
        ])->validate();

        $validatedData['total'] = $validatedData['subtotal'];

        $payment = Payment::create(self::omitNullValues($validatedData));

        $payment->logActivity([
            'user_id' => auth()->check() ? auth()->id() : null,
            'event' => 'payment.created',
            'description' => 'Payment created manually by '.(auth()->check() ? auth()->user()->username : 'system'),
            'model_type' => Payment::class,
            'model_id' => $payment->id,
        ]);

        return $payment;
    }

    /**
     * Update payment details as an admin.
     *
     * @throws ValidationException
     */
    public static function updatePaymentAsAdmin(array $input)
    {
        $validatedData = Validator::make($input, [
            'payment_id' => ['required', 'integer', 'exists:payments,id'],
            'user_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'description' => ['sometimes', 'required', 'string'],
            'currency' => ['sometimes', 'required', 'string', 'size:3'],
            'subtotal' => ['sometimes', 'required', 'numeric', 'min:0'],
            'status' => ['sometimes', 'required', 'string', 'in:unpaid,paid,refunded'],
            'invoice_id' => ['sometimes', 'required', 'string', 'max:255'],
            'transaction_id' => ['sometimes', 'nullable', 'string', 'max:255'],
        ])->validate();

        $payment = Payment::find($validatedData['payment_id']);

        if (! $payment) {
            throw ValidationException::withMessages([
                'payment_id' => 'Payment not found',
            ]);
        }

        // set total if subtotal is provided
        if (isset($validatedData['subtotal'])) {
            $validatedData['total'] = $validatedData['subtotal'];
        }

        unset($validatedData['payment_id']);

        return $payment->update(self::omitNullValues($validatedData));
    }

    /**
     * Refund a payment as an admin.
     *
     * @throws ValidationException
     */
    public static function refundPaymentAsAdmin(array $input)
    {
        $validatedData = Validator::make($input, [
            'payment_id' => ['required', 'integer', 'exists:payments,id'],
            'admin_user_id' => ['nullable', 'integer', 'exists:users,id'], // the admin user id who is performing the refund
            'gateway_config_id' => ['required', 'integer', 'exists:gateway_configs,id'],
            'amount' => ['nullable', 'numeric'],
            'reason' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $payment = Payment::find($validatedData['payment_id']);
        $gateway = GatewayConfig::find($validatedData['gateway_config_id']);

        if (! $payment) {
            throw ValidationException::withMessages([
                'payment_id' => 'Payment not found',
            ]);
        }

        // Check if the payment is paid and not already refunded
        if ($payment->status !== 'paid') {
            throw ValidationException::withMessages([
                'payment_id' => 'Payment is not in a refundable state, status must be "paid"',
            ]);
        }

        if (! $gateway) {
            throw ValidationException::withMessages([
                'gateway_config_id' => 'Gateway configuration not found',
            ]);
        }

        // Check if the gateway is gateway-balance or if it was used to pay for the payment
        if ($gateway->id !== $payment->gateway_config_id) {
            // check if the gateway is not a balance gateway
            if ($gateway->extension_identifier !== 'gateway-balance') {
                throw ValidationException::withMessages([
                    'gateway_config_id' => 'The specified gateway must be the one used for the payment or a balance gateway',
                ]);
            }
        }

        // check if the gateway supports refunds
        if (! $gateway->gateway->supportsRefunds()) {
            throw ValidationException::withMessages([
                'gateway_config_id' => 'The specified gateway does not support refunds',
            ]);
        }

        // if the gateway supports partial refunds, and the amount is not provided, set it to the payment amount
        if ($gateway->gateway->supportsPartialRefunds() && is_null($validatedData['amount'])) {
            $validatedData['amount'] = $payment->total();
        }

        // check if the amount is not greater than the payment amount
        if ($gateway->gateway->supportsPartialRefunds() && $validatedData['amount'] > $payment->total()) {
            throw ValidationException::withMessages([
                'amount' => 'Refund amount cannot be greater than the payment amount',
            ]);
        }

        // Here you would implement the logic to process the refund
        // For example, interacting with a payment gateway API
        try {
            if ($gateway->gateway->supportsPartialRefunds()) {
                // Process partial refund
                $gateway->gateway->extension()->refund(
                    $payment,
                    amount: $validatedData['amount']
                );
            } else {
                // Process full refund
                $gateway->gateway->extension()->refund($payment);
            }
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'gateway_config_id' => 'Failed to process refund: '.$e->getMessage(),
            ]);
        }

        // Mark the payment as refunded
        $payment->update([
            'status' => 'refunded',
            'earnings' => (isset($validatedData['amount']) ? $payment->total() - $validatedData['amount'] : 0),
        ]);

        // Create a payment refund record
        PaymentRefund::create([
            'payment_id' => $payment->id,
            'user_id' => $validatedData['admin_user_id'],
            'gateway_config_id' => $gateway->id,
            'amount' => $validatedData['amount'] ?? $payment->total(),
            'currency' => $payment->currency,
            'reason' => $validatedData['reason'] ?? 'No reason provided',
            'transaction_id' => $payment->transaction_id,
        ]);

        // dispatch event
        PaymentRefunded::dispatch($payment, $validatedData['amount'] ?? $payment->total());

        // Log the refund activity
        $payment->logActivity([
            'user_id' => auth()->check() ? auth()->id() : null,
            'event' => 'payment.refunded',
            'description' => 'Payment refunded manually by '.(auth()->check() ? auth()->user()->username : 'system'),
            'model_type' => Payment::class,
            'model_id' => $payment->id,
        ]);

        return $payment;
    }

    /**
     * Complete a payment as a client
     *
     * @throws ValidationException
     */
    public static function calculateSalesTaxAsClient(array $input)
    {
        $input['country'] = strtoupper((string) ($input['country'] ?? ''));

        $validatedData = Validator::make($input, [
            'gateway_config_id' => ['required', 'integer', 'exists:gateway_configs,id'],
            'payment_id' => ['required', 'integer', 'exists:payments,id'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['required_with:company_name', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2'], // ISO 3166-1 alpha-2 country code
            'region' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => in_array(strtoupper((string) ($input['country'] ?? '')), ['US', 'CA'], true)),
            ],
            'zip_code' => ['required', 'string', 'max:20'], // Postal code
        ])->validate();

        $payment = Payment::find($validatedData['payment_id']);

        if (! $payment) {
            throw ValidationException::withMessages([
                'payment_id' => 'Payment not found',
            ]);
        }

        $taxBreakdown = $payment->calculateSalesTax(
            $validatedData['country'],
            $validatedData['region'] ?? null,
            $validatedData['tax_id'] ?? null,
            $validatedData['gateway_config_id']
        );

        PaymentTaxDetail::create([
            'payment_id' => $payment->id,
            'company_name' => $validatedData['company_name'] ?? null,
            'tax_id' => $validatedData['tax_id'] ?? null,
            'country' => $validatedData['country'],
            'region' => $validatedData['region'] ?? null,
            'zip_code' => $validatedData['zip_code'] ?? null,
            'tax_name' => $taxBreakdown['tax_name'] ?? 'Sales Tax',
            'tax_rate' => $taxBreakdown['tax_rate'] ?? 0,
        ]);

        return $payment;
    }

    public function renewalPaymentForClient(array $input)
    {
        $validatedData = Validator::make($input, [
            'order_id' => ['required', 'exists:orders,id'],
            'renewal_days' => ['required', 'integer', 'min:1'],
        ])->validate();

        $order = Order::find($validatedData['order_id']);

        if (! $order) {
            throw ValidationException::withMessages([
                'order_id' => 'Order not found',
            ]);
        }

        if ($order->status == 'terminated') {
            throw ValidationException::withMessages([
                'order_id' => 'Order is terminated and cannot be renewed',
            ]);
        }

        $order->assertBillingReady();
        $renewalPrice = $validatedData['renewal_days'] * $order->daily_price;

        $payment = Payment::create([
            'user_id' => $order->user_id,
            'description' => "Renewal for Order #{$order->id} for {$validatedData['renewal_days']} days",
            'payable_type' => Order::class,
            'payable_id' => $order->id,
            'subtotal' => $renewalPrice,
            'currency' => baseCurrency(),
            'data' => [
                'renewal_days' => $validatedData['renewal_days'],
            ],
            'handler' => OrderRenewalHandler::class,
        ]);

        return $payment;
    }

    public static function createBalancePaymentForClient(array $input)
    {
        $validatedData = Validator::make($input, [
            'user_id' => ['required', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:'.settings('min_balance_topup_amount', 5)],
        ])->validate();

        $user = User::find($validatedData['user_id']);

        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => 'User not found',
            ]);
        }

        return Payment::create([
            'user_id' => $user->id,
            'description' => 'Balance top-up of '.price($validatedData['amount'], in: baseCurrency(), to: baseCurrency()),
            'subtotal' => $validatedData['amount'],
            'currency' => baseCurrency(),
            'handler' => BalanceTopupHandler::class,
            'data' => [
                'amount' => $validatedData['amount'],
            ],
        ]);
    }
}
