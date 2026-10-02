<?php

namespace App\Http\Controllers\Client;

use App\Actions\OrderActions;
use App\Http\Controllers\Controller;
use App\Models\Order;

class OrdersController extends Controller
{
    public function view(Order $order)
    {
        return view('theme::orders.view', compact('order'));
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
