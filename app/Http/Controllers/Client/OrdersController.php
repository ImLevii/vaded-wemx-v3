<?php

namespace App\Http\Controllers\Client;

use App\Actions\OrderActions;
use App\Handlers\OrderUpgradeHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\TerminateOrderRequest;
use App\Http\Requests\Client\UpgradeOrderRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderUpgradeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class OrdersController extends Controller
{
    public function upgrade(Order $order, OrderUpgradeService $upgrades): View
    {
        abort_unless((int) $order->user_id === auth()->id(), 403);

        return view('theme::orders.upgrade', [
            'order' => $order, 'upgradeOptions' => $upgrades->options($order),
            'unavailableReason' => $upgrades->unavailableReason($order),
            'pendingUpgrade' => $upgrades->pendingPayment($order),
            'completedUpgrade' => $order->payments()->where('handler', OrderUpgradeHandler::class)->where('status', 'paid')
                ->whereNotNull('data->upgrade_applied_at')->latest()->first(),
        ]);
    }

    public function purchaseUpgrade(UpgradeOrderRequest $request, Order $order, OrderUpgradeService $upgrades): RedirectResponse
    {
        $payment = $upgrades->checkout($order, (int) $request->validated('package_price_id'), $request->validated('quote_token'), (int) $request->validated('quoted_at'));

        return redirect()->route($payment->isPaid() ? 'orders.upgrade' : 'payments.view', $payment->isPaid() ? $order : $payment->token);
    }

    public function retryUpgrade(Order $order, Payment $payment): RedirectResponse
    {
        abort_unless((int) $order->user_id === auth()->id() && (int) $payment->user_id === auth()->id(), 403);
        abort_unless($payment->payable_type === $order->getMorphClass() && (int) $payment->payable_id === (int) $order->id
            && $payment->handler === OrderUpgradeHandler::class && $payment->isPaid(), 404);
        (new OrderUpgradeHandler)->onPaymentCompleted($payment);
        $wasApplied = (bool) $payment->fresh()->data('upgrade_applied_at');

        return redirect()->route('orders.upgrade', $order)->with($wasApplied ? 'success' : 'error',
            $wasApplied ? 'Your service has been upgraded.' : 'Your payment is recorded, but the upgrade could not be completed. Please contact support or retry later.');
    }

    public function termination(Order $order): View
    {
        abort_unless((int) $order->user_id === auth()->id(), 403);

        return view('theme::orders.terminate', compact('order'));
    }

    public function terminate(TerminateOrderRequest $request, Order $order): RedirectResponse
    {
        Order::actions()->terminateOrderAsClient([
            'order_id' => $order->id,
            'termination_mode' => $request->validated('termination_mode'),
        ]);

        return redirect()->route('dashboard')->with('success', 'Service termination requested. Automatic renewal has been disabled.');
    }

    public function panel(Order $order): RedirectResponse
    {
        abort_unless((int) $order->user_id === auth()->id(), 403);
        abort_unless($order->package->serverConnection?->extension_identifier === 'server-pterodactyl' && ! $order->isTerminated() && $order->external_id, 404);
        $hostname = rtrim($order->package->serverConnection->config['hostname'] ?? '', '/');
        abort_unless(filter_var($hostname, FILTER_VALIDATE_URL) && in_array(parse_url($hostname, PHP_URL_SCHEME), ['https', 'http'], true), 404);
        $identifier = $order->data['identifier'] ?? null;
        $path = is_string($identifier) && preg_match('/^[a-zA-Z0-9]+$/', $identifier) ? '/server/'.$identifier : '/';

        return redirect()->away($hostname.$path)->withHeaders(['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function view(Order $order): View
    {
        $canUpgrade = (int) $order->user_id === auth()->id() && ! $order->isTerminated();

        return view('theme::orders.view', compact('order', 'canUpgrade'));
    }

    public function payments(Order $order)
    {
        return view('theme::orders.payments', compact('order'));
    }

    public function subscription(Order $order)
    {
        return view('theme::orders.subscription', compact('order'));
    }

    public function subscribe(Order $order, $gateway_id)
    {
        // if order already has an active subscription, redirect to order view page
        if ($order->hasActiveSubscription(true)) {
            return redirect()->back();
        }

        // if order is not active, redirect to order view page
        if (! $order->isActive()) {
            return redirect()->back()->with('error', 'Order is not active.');
        }

        // create a new subscription for this order
        $subscription = OrderActions::createSubscriptionAsClient([
            'order_id' => $order->id,
            'gateway_config_id' => $gateway_id,
            'user_id' => auth()->id(),
        ]);

        if (! $subscription) {
            return redirect()->back()->with('error', 'Failed to create subscription for this order.');
        }

        return redirect(route('payments.subscribe', ['gateway' => $gateway_id, 'subscription' => $subscription->token]));
    }

    public function emails(Order $order)
    {
        return view('theme::orders.emails', compact('order'));
    }

    public function members(Order $order)
    {
        return view('theme::orders.members', compact('order'));
    }

    public function acceptInvite()
    {
        Order::actions()->acceptInviteAsClient([
            'member_id' => request('member_id'),
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('dashboard.order-invites')->with('success', 'Invite accepted successfully.');
    }

    public function rejectInvite()
    {
        Order::actions()->declineInviteAsClient([
            'member_id' => request('member_id'),
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('dashboard.order-invites')->with('success', 'Invite declined successfully.');
    }

    public function removeMember()
    {
        Order::actions()->removeMemberAsClient([
            'member_id' => request('member_id'),
            'user_id' => auth()->id(),
        ]);

        return redirect()->back();
    }
}
