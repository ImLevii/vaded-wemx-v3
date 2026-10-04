<?php

use App\Models\Order;
use Livewire\Volt\Component;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Computed;

new class extends Component {
    #[Locked]
    public int $order_id;

    public bool $auto_balance_renew = false;

    public function mount($order_id)
    {
        $this->order_id = $order_id;
        $this->auto_balance_renew = $this->order->auto_balance_renew;
    }

    #[Computed]
    public function order()
    {
        return Order::find($this->order_id);
    }

    public function enableBalanceRenewal()
    {
        abort_unless((int) $this->order->user_id === auth()->id(), 403);
        $this->order->assertBillingReady();
        $this->auto_balance_renew = !$this->auto_balance_renew;

        // if user already has a subscription, show an error toast and return
        if ($this->order->hasActiveSubscription()) {
            $this->auto_balance_renew = false;
            $this->dispatch('toast', type: 'error', message: 'You already have an active subscription for this order. Please cancel the subscription first before enabling auto balance renewal.', title: 'Error');
            return;
        }

        // check if user has enough balance to renew the order
        if ($this->auto_balance_renew && $this->order->price > $this->order->user->balance) {
            $this->auto_balance_renew = false;
            $this->dispatch('toast', type: 'error', message: 'You do not have enough balance to enable auto balance renewal.', title: 'Error');
            return;
        }

        // if order status is not active, show a warning toast
        if ($this->order->status != 'active' AND $this->auto_balance_renew) {
            $this->auto_balance_renew = false;
            $this->dispatch('toast', type: 'warning', message: 'You can only enable auto balance renewal for active orders.', title: 'Warning');
            return;
        }

        // if auto_balance_renew has been disabled, show a warning toast
        if (!$this->auto_balance_renew) {
            $this->order->update(['auto_balance_renew' => false]);
            $this->dispatch('toast', type: 'warning', message: 'You have disabled auto balance renewal. You will need to manually renew this order when it is due.', title: 'Warning');
        } else {
            $this->order->update(['auto_balance_renew' => true]);

            $this->dispatch('toast', type: 'success', message: 'Successfully updated auto balance renewal setting!', title: 'Success');
        }
    }
}

?>

<x-theme::card class="vh-service-renewal">
    <div class="vh-service-renewal-heading">
        <div class="vh-service-renewal-copy">
            <span class="vh-service-panel-icon"><x-theme::icon name="receipt" /></span>
            <div><h2>Enable auto balance renewal</h2><p>Automatically renew using your account balance.</p></div>
        </div>
        <button type="button" role="switch" aria-label="Enable auto balance renewal" aria-checked="{{ $auto_balance_renew ? 'true' : 'false' }}" class="vh-service-renewal-toggle" wire:click="enableBalanceRenewal" wire:loading.attr="disabled" wire:target="enableBalanceRenewal"><span aria-hidden="true"></span></button>
    </div>
    @if($this->order->auto_balance_renew)
    <p class="vh-service-renewal-note">
        Make sure your account always has enough balance to cover the renewal cost of <strong>{{ price($this->order->price) }}</strong> for this order. The next balance renewal date is <strong>{{ $this->order->due_date?->copy()->subDays(3)->format('d M Y') ?? 'Never' }}</strong>.
    </p>
    @endif
</x-theme::card>
