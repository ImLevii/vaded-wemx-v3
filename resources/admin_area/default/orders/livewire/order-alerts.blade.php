<?php

use Livewire\Volt\Component;

new class extends Component
{
    public $order;

    public function mount($order)
    {
        $this->order = $order;
    }

    #[\Livewire\Attributes\On('order-updated')]
    public function orderUpdated()
    {

    }
}
?>

<div>
    @foreach($order->exceptions()->where('resolved_at', null)->latest()->get() as $exception)
    <div class="alert alert-danger" role="alert">
        <div>
            <h4 class="alert-heading">Failed to perform action "{{ Str::headline($exception->action) }}" #{{ $exception->id }}</h4>
            <div class="alert-description">
                <ul class="alert-list">
                    <li>
                        {{ Str::limit($exception->message, 100, '...') }}
                    </li>
                    <li>{{ $exception->created_at->diffForHumans() }} ({{ $exception->created_at->format('d M Y H:i:s') }})</li>
                </ul>
            </div>
        </div>
        <div class="mt-3">
            <a href="{{ route('admin.orders.edit', ['order' => $order->id, 'orderEditPage' => 'incident-logs']) }}" wire:navigate class="btn btn-danger">View Details</a>
        </div>
    </div>
    @endforeach

    {{-- Alert if order due_date is in less than 7 days --}}
    @if($order->isRecurring() && $order->status == 'active' && $order->due_date && $order->due_date->lte(now()->addDays(7)))
        <div class="alert alert-warning" role="alert">
            <div class="text-warning">
                This order is due in {{ $order->due_date?->diffForHumans() ?? 'Never' }} ({{ $order->due_date?->format('d M Y') ?? 'Never' }})
            </div>
        </div>
    @endif

    {{-- Alert if order is suspended --}}
    @if($order->status == 'suspended')
        <div class="alert alert-warning" role="alert">
            <div class="text-warning">
                This order is suspended
            </div>
        </div>
    @endif

    {{-- Alert if order is terminated --}}
    @if($order->status == 'terminated')
        <div class="alert alert-danger" role="alert">
            <div class="text-danger">
                This order is terminated
            </div>
        </div>
    @endif
</div>
