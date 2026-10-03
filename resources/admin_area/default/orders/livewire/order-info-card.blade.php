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
    public function refreshOrder()
    {

    }
}
?>

<div class="card">
    <div class="card-header">
        <div>
            <div class="row align-items-center">
                <div class="col-auto">
                    <span class="avatar" style="background-image: url(https://www.gravatar.com/avatar/b7501746962b32771ff173ae8557c6f6);"></span>
                </div>
                <div class="col">
                    <div class="card-title">{{ $order->package->name }}</div>
                    <div class="card-subtitle">{{ $order->requiresBillingReview() ? 'Billing review pending' : price($order->price).' / '.$order->cycle() }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="datagrid">
            <div class="datagrid-item">
                <div class="datagrid-title">Package</div>
                <div class="datagrid-content"><a href="{{ route('admin.packages.edit', $order->package->id) }}" wire:navigate>{{ $order->package->name }}</a></div>
            </div>
            <div class="datagrid-item">
                <div class="datagrid-title">Price</div>
                <div class="datagrid-content">{{ $order->requiresBillingReview() ? 'Billing review pending' : price($order->price).' / '.$order->cycle() }}</div>
            </div>
            <div class="datagrid-item">
                <div class="datagrid-title">Uphrade Price</div>
                <div class="datagrid-content">{{ $order->requiresBillingReview() ? 'Unknown' : price($order->upgrade_fee) }}</div>
            </div>
            <div class="datagrid-item">
                <div class="datagrid-title">User</div>
                <div class="datagrid-content"><a href="{{ route('admin.users.edit', $order->user->id) }}" wire:navigate>{{ $order->user->username }} ({{ $order->user->email }})</a></div>
            </div>
            <div class="datagrid-item">
                <div class="datagrid-title">Category</div>
                <div class="datagrid-content"><a href="{{ route('admin.categories.edit', $order->package->category->id) }}" wire:navigate>{{ $order->package->name }}</a></div>
            </div>
            <div class="datagrid-item">
                <div class="datagrid-title">Server</div>
                <div class="datagrid-content"><a href="{{ route('admin.servers.connections.edit', $order->package->serverConnection->id) }}" wire:navigate>{{ $order->package->serverConnection->alias }} [{{ $order->package->serverConnection->extension_identifier }}]</a></div>
            </div>
            <div class="datagrid-item">
                <div class="datagrid-title">Status</div>
                <div class="datagrid-content">
                    @if($order->status == "active") <span class="badge bg-green-lt">Active</span> @elseif($order->status == "suspended") <span class="badge bg-warning-lt">Suspended</span> @elseif($order->status == "terminated") <span class="badge bg-danger-lt">Terminated</span> @else <span class="badge bg-warning-lt">{{ ucfirst($order->status)  }}</span> @endif
                </div>
            </div>
            <div class="datagrid-item">
                <div class="datagrid-title">External ID</div>
                <div class="datagrid-content">{{ $order->external_id ?? '-' }}</div>
            </div>
            <div class="datagrid-item">
                <div class="datagrid-title">Due Date</div>
                <div class="datagrid-content">
                    {{ $order->requiresBillingReview() ? 'Awaiting billing review' : ($order->due_date?->format('d M Y') ?? 'Never') }}
                </div>
            </div>

            <div class="datagrid-item">
                <div class="datagrid-title">Last Renewed At</div>
                <div class="datagrid-content">
                    {{ $order->requiresBillingReview() ? 'Unknown' : $order->last_renewed_at->format('d M Y') }}
                </div>
            </div>

            <div class="datagrid-item">
                <div class="datagrid-title">Created At</div>
                <div class="datagrid-content">
                    {{ $order->created_at->format('d M Y') }}
                </div>
            </div>
        </div>
    </div>
</div>
