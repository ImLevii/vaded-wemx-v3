@extends('theme::dashboard.dashboard-layout')

@section('container')
    @if(($inviteCount = \App\Models\OrderMember::where('email', auth()->user()->email)->where('status', 'pending')->count()) > 0)
    <div>
        <x-theme::alert.primary class="vh-dashboard-alert flex items-center justify-between">
            <span>You have {{ $inviteCount }} pending invite(s) to orders.</span>
            <x-theme::button.primary href="{{ route('dashboard.order-invites') }}" wire:navigate text="View Invites" />
        </x-theme::alert.primary>
    </div>
    @endif

    @foreach(auth()->user()->orders()->where('status', 'active')->whereNotNull('due_date')->where('due_date', '<', now()->addDays(5))->get() as $order)
        <div>
            <x-theme::alert.warning class="vh-dashboard-alert flex items-center justify-between">
            <span>Order {{ $order->package->name }} (#{{ $order->id }}) is due in {{ $order->due_date->diffForHumans() }}, please renew it in time to avoid suspension.</span>
                <x-theme::button.primary href="{{ route('orders.view', $order->id) }}" wire:navigate text="View Order"/>
            </x-theme::alert.warning>
        </div>
    @endforeach

    @foreach(auth()->user()->orders()->where('status', 'suspended')->get() as $order)
        <div>
            <x-theme::alert.danger class="vh-dashboard-alert flex items-center justify-between">
            <span>Order {{ $order->package->name }} (#{{ $order->id }}) is suspended, please renew it in time to avoid termination.</span>
                <x-theme::button.primary href="{{ route('orders.view', $order->id) }}" wire:navigate text="View Order"/>
            </x-theme::alert.danger>
        </div>
    @endforeach

    <div id="my-services" class="vh-dashboard-services mb-6">
        <div class="vh-panel-heading">
            <div>
                <span class="vh-portal-eyebrow">Your infrastructure</span>
                <h2>My services</h2>
            </div>
            <a href="{{ route('categories.index') }}" wire:navigate class="vh-inline-link">Browse services <span aria-hidden="true">&rarr;</span></a>
        </div>
        @livewire(client_view_path('orders.livewire.orders-table'))
    </div>

    <div class="mb-4">
        @livewire(client_view_path('livewire.table'), [
            'title' => 'Recent payments',
            'description' => 'View your recent successful payments.',
            'perPage' => 5,
            'columns' => [
                'Description',
                'Gateway',
                'Transaction ID',
                'Amount',
                'Currency',
                'Status',
                'Paid At',
                'Actions',
            ],
            'rows' =>
                auth()->user()->payments()->latest()->whereIn('status', ['paid', 'refunded'])->get()->map(function($payment) {
                    return [
                        Str::limit($payment->description, 50),
                        $payment->gatewayConfig ? $payment->gatewayConfig->display_name : 'None',
                        $payment->transaction_id ? Str::limit($payment->transaction_id, 32) : '-',
                        priceIn($payment->total(), $payment->currency),
                        $payment->currency,
                        ucfirst($payment->status),
                        $payment->paid_at ? $payment->paid_at->format(settings('date_format', 'd M Y H:i')) : '-',
                        '<a href="'. route('payments.view', $payment->token) .'" wire:navigate class="font-medium text-blue-600 dark:text-blue-500 hover:underline">View</a>',
                    ];
                })->toArray(),
        ])
    </div>
@endsection
