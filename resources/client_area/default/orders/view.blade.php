@extends('theme::orders.layout', [
    'activeTab' => 'general',
])

@section('container')
<div>
    @if($order->termination_requested_at && ! $order->isTerminated())
        <x-theme::alert.warning text="{{ $order->terminate_at?->isFuture() ? 'This service will be terminated on '.$order->terminate_at->format('d M Y H:i').'. Automatic renewal is disabled.' : 'Termination is being processed. Automatic renewal is disabled.' }}" />
    @endif
    @if((int) $order->user_id === auth()->id() && ! $order->isTerminated())
        <div class="mb-4 flex flex-wrap gap-3">
            @if($order->package->serverConnection?->extension_identifier === 'server-pterodactyl' && $order->external_id)
                <x-theme::button.primary href="{{ route('orders.panel', $order) }}" target="_blank" rel="noopener noreferrer" text="Open panel" />
            @endif
            <x-theme::button.danger href="{{ route('orders.termination', $order) }}" text="Terminate service" />
        </div>
    @endif
    @if($order->requiresBillingReview())
        <x-theme::alert.warning text="Billing review pending. Your existing service is connected; renewal charges are on hold while we restore the original billing details." />
    @endif
    @if(!$order->termination_requested_at && $order->status == 'active' && $order->due_date && $order->due_date->isToday())
        <x-theme::alert.warning class="flex items-center justify-between">
            <span>Order {{ $order->package->name }} (#{{ $order->id }}) is due in {{ $order->due_date->diffForHumans() }}, please renew it in time to avoid suspension.</span>
        </x-theme::alert.warning>
    @endif

    @if(!$order->termination_requested_at && $order->status == 'suspended')
        <x-theme::alert.danger>
            <span>Order {{ $order->package->name }} (#{{ $order->id }}) is suspended, please renew it in time to avoid termination.</span>
        </x-theme::alert.danger>
    @endif

    @if(!$order->termination_requested_at && !$order->isTerminated() && !$order->requiresBillingReview() && \App\Models\GatewayConfig::balanceGateway())
        @livewire(client_view_path('orders.livewire.balance-renewal-switch'), ['order_id' => $order->id])
    @endif

    @foreach(extensionElements(['client-order-top-view']) as $element)
        @includeIf($element['view'], ['order' => $order])
    @endforeach

    @if(!$order->termination_requested_at && !$order->requiresBillingReview() && $order->isActive() && !$order->hasActiveSubscription() && $order->isRecurring() && !$order->auto_balance_renew && \App\Models\GatewayConfig::where('type', 'subscription')->where('is_active', true)->count() > 0)
        <div>
            <x-theme::alert.primary class="flex items-center justify-between">
            <span>
                Setup a subscription to automatically renew this order and never miss a payment.
            </span>
                <x-theme::button.primary href="{{ route('orders.view.subscription', $order->id) }}" wire:navigate text="Setup Subscription" />
            </x-theme::alert.primary>
        </div>
    @endif

    @if($activeSubscription = $order->hasActiveSubscription())
        <div>
            <x-theme::alert.primary class="flex items-center justify-between">
        <span>
                You have an active subscription for this order. The next billing date is {{ $activeSubscription->subscription ? $activeSubscription->subscription->next_billing_at->format('d M Y') : 'N/A' }}.
        </span>
                <x-theme::button.primary href="{{ route('orders.view.subscription', $order->id) }}" wire:navigate text="View Subscription" />
            </x-theme::alert.primary>
        </div>
    @endif

    <x-theme::card class="mb-4">
        <h5 class="mb-2 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
            {{ $order->data['name'] ?? $order->package->name }}
        </h5>
        <x-theme::datagrid.grid :cols="3" :gap="4">
            <x-theme::datagrid.item>
                <x-slot:label>Package</x-slot:label>
                {{ $order->package->name }}
            </x-theme::datagrid.item>

            <x-theme::datagrid.item>
                <x-slot:label>Billing cycle</x-slot:label>
                @if($order->requiresBillingReview())
                    Billing review pending
                @else
                    <span class="mr-1 font-bold text-gray-500 dark:text-white">{{ price($order->price) }}</span> / {{ $order->cycle() }}
                @endif
            </x-theme::datagrid.item>

            <x-theme::datagrid.item>
                <x-slot:label>Status</x-slot:label>
                @if($order->status == 'active')
                    <x-theme::badge.success text="Active" />
                @elseif($order->status == 'suspended')
                    <x-theme::badge.warning text="Suspended" />
                @elseif($order->status == 'cancelled')
                    <x-theme::badge.danger text="Cancelled" />
                @elseif($order->status == 'terminated')
                    <x-theme::badge.danger text="Terminated" />
                @elseif(in_array($order->status, ['pending', 'processing']))
                    <x-theme::badge.primary text="{{ ucfirst($order->status) }}" />
                @else
                    <x-theme::badge.warning text="{{ ucfirst($order->status) }}" />
                @endif
            </x-theme::datagrid.item>

            <x-theme::datagrid.item>
                <x-slot:label>Due date</x-slot:label>
                @if($order->requiresBillingReview())
                    Awaiting billing review
                @elseif($order->due_date)
                    {{ $order->due_date->format('d M Y') }}
                @else
                    Never
                @endif
            </x-theme::datagrid.item>

            <x-theme::datagrid.item>
                <x-slot:label>Last renewal date</x-slot:label>
                {{ $order->requiresBillingReview() ? 'Unknown' : $order->last_renewed_at->format('d M Y') }}
            </x-theme::datagrid.item>

            <x-theme::datagrid.item>
                <x-slot:label>Next Invoice</x-slot:label>
                @if($order->termination_requested_at || $order->isTerminated())
                    No further renewals
                @elseif($order->requiresBillingReview())
                    On hold
                @elseif($order->due_date)
                    {{ $order->due_date->diffForHumans() }}
                @else
                    Never
                @endif
            </x-theme::datagrid.item>
        </x-theme::datagrid.grid>
        @if(!$order->termination_requested_at && !$order->isTerminated() && !$order->requiresBillingReview())
            <x-theme::button.success type="button" data-drawer-target="renew-order-drawer" data-drawer-show="renew-order-drawer" data-drawer-placement="right" aria-controls="renew-order-drawer" text="Renew" />
        @endif
    </x-theme::card>

    @livewire(client_view_path('orders.livewire.server-account-block'), ['order_id' => $order->id])

    @foreach(extensionElements(['client-order-bottom-view']) as $element)
        @includeIf($element['view'], ['order' => $order])
    @endforeach
</div>

@livewire(client_view_path('orders.livewire.renew-order-drawer'), ['order' => $order])
@endsection
