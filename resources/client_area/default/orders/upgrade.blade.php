@extends('theme::orders.layout', ['activeTab' => 'upgrade'])

@section('title', 'Upgrade service #'.$order->id)

@section('container')
    <div class="flex flex-col gap-5">
        <x-theme::card>
            <h1 class="mb-3 text-2xl font-bold text-gray-900 dark:text-white">Upgrade your service</h1>
            <p class="text-gray-600 dark:text-gray-300">Choose a larger plan for {{ $order->package->name }}. Your existing server, files, and renewal date stay the same. The upgrade takes effect after payment.</p>
            @if(! $order->requiresBillingReview())
                <p class="mt-3 text-gray-600 dark:text-gray-300">Current price: <strong>{{ price($order->price) }} / {{ $order->cycle() }}</strong>. Renewal date: {{ $order->due_date?->format('d M Y') ?? 'None' }}.</p>
            @endif
            @error('package_price_id') <x-theme::form.error :text="$message" /> @enderror
            @error('quote_token') <x-theme::form.error :text="$message" /> @enderror
            @error('quoted_at') <x-theme::form.error :text="$message" /> @enderror
            @error('confirm_upgrade') <x-theme::form.error :text="$message" /> @enderror
        </x-theme::card>

        @if($completedUpgrade)
            <x-theme::alert.success text="{{ 'Your latest service upgrade is complete. Invoice: '.$completedUpgrade->invoice_id.'.' }}" />
        @endif

        @if($pendingUpgrade)
            <x-theme::card>
                @if($pendingUpgrade->isPaid())
                    <h2 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">Your paid upgrade needs attention</h2>
                    <p class="mb-4 text-gray-600 dark:text-gray-300">Payment is recorded, but your upgrade has not completed. Retry without another charge. If it still fails, contact support with invoice {{ $pendingUpgrade->invoice_id }}.</p>
                    <form method="POST" action="{{ route('orders.upgrade.retry', ['order' => $order, 'payment' => $pendingUpgrade]) }}">
                        @csrf
                        <x-theme::button.primary type="submit" text="Retry paid upgrade" />
                    </form>
                @else
                    <p class="mb-4 text-gray-600 dark:text-gray-300">An upgrade invoice is waiting for payment. Your service will stay on its current plan until it is paid.</p>
                    <x-theme::button.primary href="{{ route('payments.view', $pendingUpgrade->token) }}" text="Continue upgrade payment" />
                @endif
            </x-theme::card>
        @endif

        @if($unavailableReason)
            <x-theme::alert.warning :text="$unavailableReason" />
        @elseif($upgradeOptions->isEmpty())
            <x-theme::card><p class="text-gray-600 dark:text-gray-300">No compatible larger plans are currently available for this service. Contact support if you need more resources.</p></x-theme::card>
        @elseif(! $pendingUpgrade?->isPaid())
            <p class="text-sm text-gray-600 dark:text-gray-300">Pay only the price difference for the time remaining until {{ $order->due_date->format('d M Y') }}, plus any upgrade fee. Existing add-ons are retained. These quotes are valid for 15 minutes. Applicable taxes are shown at checkout.</p>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                @foreach($upgradeOptions as $option)
                    <x-theme::card>
                        <h2 class="mb-3 text-xl font-semibold text-gray-900 dark:text-white">{{ $option['price']->package->name }}</h2>
                        <dl class="mb-4 grid grid-cols-2 gap-3 text-sm text-gray-600 dark:text-gray-300">
                            @foreach($option['resources'] as $label => $value)
                                <dt>{{ $label }}</dt><dd class="text-right">{{ $value }}</dd>
                            @endforeach
                            <dt>New recurring price</dt><dd class="text-right font-semibold">{{ price($option['recurring']) }} / {{ $order->cycle() }}</dd>
                            <dt>Remaining period</dt><dd class="text-right">{{ price($option['prorated']) }}</dd>
                            <dt>Upgrade fee</dt><dd class="text-right">{{ price($option['fee']) }}</dd>
                            <dt class="font-semibold">Due today, before tax</dt><dd class="text-right font-semibold">{{ price($option['total']) }}</dd>
                        </dl>
                        <form method="POST" action="{{ route('orders.upgrade.purchase', $order) }}" class="flex flex-col gap-4">
                            @csrf
                            <input type="hidden" name="package_price_id" value="{{ $option['price']->id }}">
                            <input type="hidden" name="quote_token" value="{{ $option['token'] }}">
                            <input type="hidden" name="quoted_at" value="{{ $option['quoted_at'] }}">
                            <label class="flex items-start gap-3 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" name="confirm_upgrade" value="1" required>
                                <span>I agree to the new recurring price of {{ price($option['recurring']) }} / {{ $order->cycle() }} and today's charge of {{ price($option['total']) }} before tax.</span>
                            </label>
                            <x-theme::button.primary type="submit" text="Continue to payment" />
                        </form>
                    </x-theme::card>
                @endforeach
            </div>
        @endif
        <a href="{{ route('orders.view', $order) }}" wire:navigate class="font-medium text-primary-600 hover:underline dark:text-primary-400">Back to service</a>
    </div>
@endsection
