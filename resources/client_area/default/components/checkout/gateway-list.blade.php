@props([
    'excludeBalanceGateway' => false,
    'handler' => null,
])

@php
    $gateways = \App\Models\GatewayConfig::where('type', 'payment')
        ->where('is_active', true)
        ->get();
@endphp

@foreach($gateways as $gateway)
    @if($excludeBalanceGateway && $handler === 'App\Handlers\BalanceTopupHandler' && $gateway->extension_identifier === 'gateway-balance')
        @continue
    @endif

    @if($gateway->is_staff_only && (!auth()->user() || !auth()->user()->hasPerm('use-staff-gateways')))
        @continue
    @endif

    <div class="flex min-w-0 flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-600 dark:bg-gray-800">
        <div class="min-w-0 flex-1 basis-48 [overflow-wrap:anywhere]">
            <div class="flex items-start">
                <div class="flex h-5 shrink-0 items-center">
                    <input
                        id="gatewayId{{ $gateway->id }}"
                        aria-describedby="{{ $gateway->id }}-gateway-text"
                        type="radio"
                        name="gatewayId"
                        wire:model.change="gatewayId"
                        value="{{ $gateway->id }}"
                        class="h-4 w-4 border-gray-300 bg-white text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-gray-500 dark:bg-gray-600 dark:ring-offset-gray-800 dark:focus:ring-primary-600"
                    />
                </div>

                <div class="ms-4 text-sm">
                    <label for="gatewayId{{ $gateway->id }}" class="font-medium text-gray-900 dark:text-white">
                        Pay with {{ $gateway->display_name }}
                        @if($gateway->is_staff_only)
                            <span class="bg-yellow-100 text-yellow-800 text-xs font-medium me-2 px-2.5 py-0.5 rounded-sm dark:bg-yellow-900 dark:text-yellow-300 ms-1">Staff Only</span>
                        @endif
                    </label>
                    <p id="{{ $gateway->id }}-gateway-text" class="mt-2 text-sm text-gray-900 dark:text-white">
                        {{ $gateway->display_description }}
                    </p>
                    @if(!$gateway->gateway->supportsCurrency(activeCurrency()))
                        <x-theme::form.error text="This payment method does not support '{{ activeCurrency() }}', the amount will be converted to '{{ $gateway->gateway->baseCurrency() }}'." for="gatewayId{{ $gateway->id }}" />
                    @endif
                </div>
            </div>
        </div>

        @if($gateway->icon)
            <div class="shrink-0">
                <img class="h-8 w-auto max-w-24 object-contain" src="{{ $gateway->icon }}" alt="" />
            </div>
        @endif
    </div>
@endforeach
