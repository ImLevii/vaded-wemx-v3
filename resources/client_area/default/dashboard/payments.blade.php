@extends('theme::dashboard.dashboard-layout')

@section('container')
    <header class="vh-service-heading"><div><span class="vh-kicker">BILLING CENTER</span><h1>Payments &amp; invoices</h1><p>Review payment history, open an invoice, and track your hosting costs.</p></div><a class="vh-text-link" href="{{ route('subscriptions.index') }}" wire:navigate>Subscriptions <x-theme::icon name="arrow" /></a></header>
    <div class="mb-4">
        @livewire(client_view_path('livewire.table'), [
            'title' => 'Payments',
            'description' => 'View your recent successful payments.',
            'columns' => [
                'Description',
                'Amount',
                'Currency',
                'Status',
                'Gateway',
                'Date',
                'Actions',
            ],
            'rows' =>
                auth()->user()->payments->where('status', 'paid')->map(function($payment) {
                    return [
                        $payment->description,
                        priceIn($payment->total(), $payment->currency),
                        $payment->currency,
                        ucfirst($payment->status),
                        $payment->gatewayConfig ? $payment->gatewayConfig->display_name : 'None',
                        $payment->created_at->format(settings('date_format', 'd M Y H:i')),
                        '<a href="'. route('payments.view', $payment->token) .'" wire:navigate class="font-medium text-blue-600 dark:text-blue-500 hover:underline">View</a>',
                    ];
                })->toArray(),
        ])
    </div>
@endsection
