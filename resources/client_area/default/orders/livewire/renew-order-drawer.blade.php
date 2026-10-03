<?php

use App\Models\Payment;
use Carbon\Carbon;
use Livewire\Volt\Component;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Locked;

new class extends Component {
    #[Locked]
    public $order;

    public int $period = 30;

    public array $renewalPeriods = [
        '30' => '1 Month',
        '60' => '2 Months',
        '90' => '3 Months',
        '180' => '6 Months',
        '365' => '1 Year',
        '730' => '2 Years',
    ];

    public $custom_period;

    public bool $selectCustomPeriod = false;

    public function mount($order)
    {
        $this->order = $order;

        $this->period = $order->period_in_days;

        if ($order->due_date) {
            $this->custom_period = $order->due_date->copy()->addDays($order->period_in_days)->toDateString();
        } else {
            $this->custom_period = now()->addDays(max($order->period_in_days, 1))->toDateString();
        }
    }

    public function toggleCustomPeriod()
    {
        $this->selectCustomPeriod = !$this->selectCustomPeriod;
    }

    public function rendering($view)
    {
        if ($this->selectCustomPeriod && $this->order->due_date) {
            // calculate difference in days from due date to custom period
            $dueDate = $this->order->due_date;
            $customDate = Carbon::parse($this->custom_period);
            $this->period = $dueDate->diffInDays($customDate) + 1; // +1 to include the due date itself
        }
    }

    public function nextDueDateText(): string
    {
        if (!$this->order->due_date) {
            return 'N/A';
        }

        return $this->order->due_date->copy()->addDays($this->period)->format('d M Y');
    }

    public function customPeriodMinDate(): string
    {
        if (!$this->order->due_date) {
            return now()->toDateString();
        }

        return $this->order->due_date->copy()->addDays(14)->toDateString();
    }

    public function createPayment()
    {
        $payment = Payment::actions()->renewalPaymentForClient([
            'order_id' => $this->order->id,
            'renewal_days' => $this->period,
        ]);

        $this->redirect(route('payments.view', [
            'payment' => $payment->token,
        ]), true);
    }
}

?>

<!-- renew drawer component -->
<x-theme::drawer id="renew-order-drawer" wire:ignore.self>
    <x-theme::drawer.title text="Renew Order Drawer"/>
    <x-theme::drawer.close-button drawer_id="renew-order-drawer"/>
    <x-theme::text.p class="text-sm mb-6" text="Select for how long you want to renew your order for."/>

    @if($order->requiresBillingReview())
        <x-theme::alert.warning text="Billing review pending. Renewal charges are on hold." />
    @elseif($order->status == 'terminated')
        <x-theme::alert.danger text="This order has been terminated and cannot be renewed." style="background: #00000014;"/>
    @else
        <div class="mb-4">
            @if(!$selectCustomPeriod)
                <div class="mb-3">
                    <x-theme::form.label for="renewal_period" text="Renewal Period"/>
                    <x-theme::form.select wire:model.change="period" id="renewal_period" :options="$renewalPeriods"/>
                    @error('period')
                        <x-theme::form.error :message="$message"/>
                    @else
                        <x-theme::form.description text="Select the period for which you want to renew your order."/>
                    @enderror
                </div>
                <div>
                    <a href="#" wire:click="toggleCustomPeriod"
                       class="font-medium text-blue-600 dark:text-blue-500 hover:underline">Select Custom Period</a>
                </div>
            @else
                <div class="mb-3">
                    <x-theme::form.label for="custom_period" text="Custom Period"/>
                    <x-theme::form.input type="date" wire:model.change="custom_period" id="custom_period"
                                         placeholder="Select a custom date"
                                         min="{{ $this->customPeriodMinDate() }}"/>
                    @error('custom_period')
                        <x-theme::form.error :message="$message"/>
                    @else
                        <x-theme::form.description text="Select the date until you want to renew your order"/>
                    @enderror
                </div>
                <div>
                    <a href="#" wire:click="toggleCustomPeriod"
                       class="font-medium text-blue-600 dark:text-blue-500 hover:underline">Select Predefined Period</a>
                </div>
            @endif
        </div>
        <div class="list mb-4">
            <p class="mb-4 flex justify-between text-sm font-normal text-gray-700 dark:text-gray-400">
                <span>Next Due Date</span>
                <span>{{ $this->nextDueDateText() }}</span>
            </p>
            <p class="mb-4 flex justify-between text-sm font-normal text-gray-700 dark:text-gray-400">
                <span>Renewal Price</span>
                <span>{{ price($this->period * $order->daily_price) }}</span>
            </p>
            <hr class="my-4 h-px border-0 bg-gray-200 dark:bg-gray-700">
        </div>
        <x-theme::button.primary wire:click="createPayment" class="w-full" text="Pay Now"/>
    @endif
</x-theme::drawer>
