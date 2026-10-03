<?php

namespace App\Handlers\Subscriptions;

use App\Models\Order;
use App\Models\OrderSubscription;
use App\Models\Subscription;

class OrderSubscriptionHandler
{
    public static function onSubscriptionActivated(Subscription $subscription)
    {
        $order = Order::find($subscription->subscribable_id);

        if (! $order || $order->termination_requested_at || $order->isTerminated()) {
            return;
        }

        // calculate remaining days, the difference between the order expiry date and today, only if order expiry date is in the future
        $remaining_days = 0;

        if ($order->due_date && $order->due_date->isFuture()) {
            $remaining_days = now()->diffInDays($order->due_date);

            // ensure its a positive number and round down
            if ($remaining_days < 0) {
                $remaining_days = 0;
            }

            $remaining_days = (int) floor($remaining_days);
        }

        OrderSubscription::create([
            'order_id' => $order->id,
            'subscription_id' => $subscription->id,
            'status' => 'active',
            'remaining_days' => $remaining_days,
        ]);

        // set order due date to subscription next billing date
        $order->due_date = $subscription->next_billing_at;
        $order->save();
    }

    public static function onSubscriptionDeactivated(Subscription $subscription)
    {
        $orderSubscription = OrderSubscription::where('subscription_id', $subscription->id)->first();

        if (! $orderSubscription) {
            return;
        }

        // if remaining days have not been added, add them to the order due date if greater than 0
        if (! $orderSubscription->remaining_days_added && $orderSubscription->remaining_days > 0) {
            $order = $orderSubscription->order;

            if ($order) {
                if ($order->due_date && $order->due_date->isFuture()) {
                    $order->due_date = $order->due_date->addDays($orderSubscription->remaining_days);
                } else {
                    $order->due_date = now()->addDays($orderSubscription->remaining_days);
                }

                $order->save();

                // mark remaining days as added
                $orderSubscription->remaining_days_added = true;
            }
        }

        $orderSubscription->status = 'inactive';
        $orderSubscription->save();
    }

    public static function onSubscriptionBillingDateUpdated(Subscription $subscription)
    {
        $orderSubscription = OrderSubscription::where('subscription_id', $subscription->id)->first();

        if (! $orderSubscription) {
            return;
        }

        if ($orderSubscription->order->termination_requested_at || $orderSubscription->order->isTerminated()) {
            return;
        }

        $orderSubscription->order->due_date = $subscription->next_billing_at;
        $orderSubscription->order->save();
    }
}
