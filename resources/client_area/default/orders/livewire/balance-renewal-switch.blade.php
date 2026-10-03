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

<x-theme::card class="mb-4">
    <label class="flex justify-between w-full items-center cursor-pointer" wire:click="enableBalanceRenewal()">
        <x-theme::text.p text="Enable auto balance renewal" class="font-medium"/>
        <div>
            <input type="checkbox" disabled value="" class="sr-only peer" wire:model="auto_balance_renew"/>
            <div
                class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:peer-focus:ring-primary-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary-600"
            ></div>
        </div>
    </label>
    @if($this->order->auto_balance_renew)
    <hr class="h-px my-8 bg-gray-200 border-0 dark:bg-gray-700">
    <p class="text-gray-500 dark:text-gray-400">
        Make sure your account always has enough balance to cover the renewal cost of <strong>{{ price($this->order->price) }}</strong> for this order. The next balance renewal date is <strong>{{ $this->order->due_date?->copy()->subDays(3)->format('d M Y') ?? 'Never' }}</strong>.
    </p>
    @endif
</x-theme::card>
