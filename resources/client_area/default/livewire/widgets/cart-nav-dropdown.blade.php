<?php

use App\Models\Cart;
use Livewire\Volt\Component;
use Illuminate\View\View;
use Livewire\Attributes\On;

new class extends Component {
    #[On('cart-updated')]
    public function onCartUpdated(): void
    {

    }

    public function removeCartItem($itemId)
    {
        Cart::actions()->removeItemFromCart([
            'cart_id' => cart()->id,
            'item_id' => $itemId,
        ]);
    }
}
?>

<div>
    <button
        id="cartDropdownButton1"
        data-dropdown-toggle="cartDropdown1"
        type="button"
        class="inline-flex items-center justify-center p-2 hover:bg-gray-100 rounded-lg text-sm font-medium leading-none text-gray-900 dark:text-white dark:hover:bg-gray-700"
    >
    <span class="sr-only">
        Cart
    </span>
        <div class="relative sm:me-2.5">
            <svg class="w-5 h-5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                 fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M5 4h1.5L9 16m0 0h8m-8 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm8 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm-8.5-3h9.25L19 7H7.312"></path>
            </svg>
            @if(cart()->getTotalQuantity())
                <div class="absolute inline-flex items-center justify-center w-4 h-4 text-xs font-medium text-white bg-red-700 rounded-full -top-1.5 -end-1.5 dark:bg-red-600">{{ cart()->getTotalQuantity() }}</div>
            @endif
        </div>
        <span class="hidden sm:flex">{{ price(cart()->total()) }}</span>
        <svg class="hidden sm:flex w-4 h-4 text-gray-900 dark:text-white ms-1" aria-hidden="true"
             xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="m19 9-7 7-7-7"></path>
        </svg>
    </button>

    <div
        id="cartDropdown1"
        class="z-50 mx-auto w-80 max-w-[calc(100vw-2rem)] space-y-4 overflow-y-auto overscroll-contain rounded-lg bg-white p-4 antialiased shadow-lg dark:bg-gray-700 hidden"
        data-popper-placement="bottom"
    >
        <dl class="flex items-center justify-between gap-4 border-b border-gray-200 pb-4 dark:border-gray-600">
            <dt class="font-semibold leading-none text-gray-900 dark:text-white">Your shopping cart</dt>
            <dd class="leading-none text-gray-500 dark:text-gray-400">{{ cart()->getTotalQuantity() }} items</dd>
        </dl>

        @if(cart()->items->isEmpty())
            <div class="min-w-0">
                <div class="flex min-w-0 items-center gap-2">
                    <div class="flex-1">
                        <p class="mt-0.5 truncate text-sm font-normal text-gray-500 dark:text-gray-400">
                            Your cart is empty
                        </p>
                    </div>
                </div>
            </div>
        @endif

        @foreach(cart()->items as $item)
            <div class="grid min-w-0 grid-cols-[minmax(0,1fr)_auto] items-center gap-2">
                <div class="col-span-2 flex min-w-0 items-center gap-2">
                    <a href="#" class="flex aspect-square h-9 w-9 shrink-0 items-center">
                        <img class="h-auto max-h-full w-full"
                             src="{{ $item->getIcon() }}"
                             alt="image"/>
                    </a>
                    <div class="min-w-0 flex-1">
                        <a href="#"
                           class="block truncate text-sm font-semibold leading-none text-gray-900 hover:underline dark:text-white">{{ $item->getName() }}</a>
                        <p class="mt-0.5 truncate text-sm font-normal text-gray-500 dark:text-gray-400">
                            @if($item->package)
                                {{ $item->package->short_description }}
                            @endif
                        </p>
                    </div>
                </div>

                <div class="text-sm font-normal leading-none text-gray-500 dark:text-gray-400">
                    x{{ $item->quantity }}</div>

                <div class="flex items-center justify-end gap-2">
                    <p class="text-sm font-semibold leading-none text-gray-900 dark:text-white">
                        {{ price($item->totalWithOptions()) }}</p>
                    <button type="button" wire:click="removeCartItem({{ $item->id }})"
                            class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-500 dark:hover:bg-gray-800 dark:hover:text-red-600">
                        <span class="sr-only"> Remove </span>
                        <svg class="h-4 w-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24"
                             height="24" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M5 7h14m-9 3v8m4-8v8M10 3h4a1 1 0 0 1 1 1v3H9V4a1 1 0 0 1 1-1ZM6 7h12v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7Z"></path>
                        </svg>
                    </button>
                </div>
            </div>
        @endforeach

        <dl class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-4 dark:border-gray-600">
            <dt class="font-semibold leading-none text-gray-900 dark:text-white">Total</dt>

            <dd class="font-semibold leading-none text-gray-900 dark:text-white">{{ price(cart()->total()) }}</dd>
        </dl>

        <a
            href="{{ route('cart') }}"
            wire:navigate
            title=""
            class="mb-2 me-2 inline-flex w-full items-center justify-center rounded-lg bg-primary-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-800 focus:outline-none focus:ring-4 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800"
            role="button"
        >
            See your cart
        </a>
    </div>

</div>
