<?php

use App\Models\Order;
use Livewire\Volt\Component;
use Livewire\Attributes\Locked;

new class extends Component
{
    #[Locked]
    public Order $order;

    public function refreshStatus(): void
    {
        $this->order->refresh();
    }
};
?>
<section class="vh-service-shell" @if(in_array($order->status, ['pending', 'processing'])) wire:poll.10s="refreshStatus" @endif>
    <header class="vh-service-heading"><div><span class="vh-kicker">SERVICE #{{ $order->id }}</span><h1>{{ $order->package->name }}</h1><p>Deployment status</p></div><a href="{{ route('dashboard') }}" wire:navigate class="vh-text-link">Your services <x-theme::icon name="arrow" /></a></header>
    <div class="vh-control-section vh-section">
        <div aria-live="polite">
            <span class="vh-kicker">{{ strtoupper($order->status) }}</span>
            @if($order->status === 'pending')
                <h2 class="text-3xl font-bold mt-4">Your server is in the queue.</h2><p>Your order is pending. This page checks for updates while your service is prepared.</p>
            @elseif($order->status === 'processing')
                <h2 class="text-3xl font-bold mt-4">Preparing your server.</h2><p>Your service is being provisioned. This page updates when its status changes.</p>
            @elseif($order->status === 'active')
                <h2 class="text-3xl font-bold mt-4">Your service is ready.</h2><p>Open your service to access its available controls and configuration.</p><a href="{{ route('orders.view', $order->id) }}" wire:navigate class="vh-action">Open Server <x-theme::icon name="arrow" /></a>
            @elseif($order->status === 'failed')
                <h2 class="text-3xl font-bold mt-4">Deployment needs attention.</h2><p>We could not complete this deployment. Contact support with service #{{ $order->id }} so we can investigate.</p>@if(config('hosting.resources.Contact support'))<a href="{{ config('hosting.resources.Contact support') }}" class="vh-action">Contact support</a>@endif
            @else
                <h2 class="text-3xl font-bold mt-4">Service status updated.</h2><p>Open your service for the latest details.</p><a href="{{ route('orders.view', $order->id) }}" wire:navigate class="vh-action">View service</a>
            @endif
        </div>
        <x-theme::server-node />
    </div>
</section>
