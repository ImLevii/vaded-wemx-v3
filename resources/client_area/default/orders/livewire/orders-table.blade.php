<?php

use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component
{
    #[Url('filterOrderStatus')]
    public array $filterStatus = [];

    #[Url('orderSearch')]
    public string $search = '';
}

?>

@php
    $user = auth()->user();
    $orders = $user->orders->sortByDesc('created_at');

    // get all available statuses as an array and the count of each status
    $statuses = $orders->pluck('status')->countBy();

    // filter orders by status
    if($this->filterStatus) {
        // make sure the $filterStatus is an array with string values that are in $statuses
        $filterStatus = collect($this->filterStatus)->filter(function($status) use ($statuses) {
            return $statuses->has($status);
        })->toArray();

        // filter orders by status
        $orders = $orders->whereIn('status', $filterStatus);
    }

    // search orders based on package name or category name, and status
    if($this->search) {
        $orders = $orders->filter(function($order) {
            return str_contains(strtolower($order->package->name), strtolower($this->search)) || str_contains(strtolower($order->status), strtolower($this->search)) || str_contains(strtolower($order->package->category->name), strtolower($this->search));
        });
    }
@endphp


<section class="vh-orders min-w-0" aria-label="Orders">
    @if(auth()->user()->orders->isEmpty())
        <x-theme::empty-state
            title="No orders found"
            description="You have not placed any orders yet."
            icon='<svg class="w-8 h-8 text-gray-500 dark:text-gray-400 mb-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h1.5L8 16m0 0h8m-8 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm8 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm.75-3H7.5M11 7H6.312M17 4v6m-3-3h6"/>
            </svg>'
            action-text="Place an order"
            :action-href="route('categories.index')"
            :action-navigate="true"
        />
    @else
        <div class="vh-orders-card bg-white dark:bg-gray-800 relative rounded-xl border shadow-sm">
            <div class="vh-orders-toolbar flex flex-wrap items-center justify-between gap-4 p-4 border-b dark:border-gray-700">
                <div class="flex shrink-0 items-center gap-3">
                    <h5 class="dark:text-white font-semibold">Orders</h5>
                </div>
                <div class="vh-orders-controls flex min-w-0 flex-wrap items-center gap-3">
                    <a href="{{ route('categories.index') }}" wire:navigate class="flex shrink-0 items-center justify-center whitespace-nowrap text-white bg-primary-700 hover:bg-primary-800 focus:ring-4 focus:ring-primary-300 font-medium rounded-lg text-sm px-3 py-2 dark:bg-primary-600 dark:hover:bg-primary-700 focus:outline-none dark:focus:ring-primary-800">
                        <svg class="h-3.5 w-3.5 mr-2" fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path clip-rule="evenodd" fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
                        </svg>
                        New Order
                    </a>

                    <div class="relative" x-data="{ open: false }" @keydown.escape.prevent.stop="open = false; $refs.filter.focus()" @click.outside="open = false">
                    <button x-ref="filter" @click="open = !open" :aria-expanded="open" aria-controls="orders-status-filter" class="flex shrink-0 items-center justify-center gap-1 whitespace-nowrap py-2 px-4 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-primary-700 focus:z-10 focus:ring-4 focus:ring-gray-200 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700" type="button">
                        <svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="h-4 w-4 mr-2 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd"></path>
                        </svg>
                        Filter
                        <svg class="-mr-1 ml-1.5 w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path clip-rule="evenodd" fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"></path>
                        </svg>
                    </button>
                    <div id="orders-status-filter" x-cloak x-show="open" class="absolute right-0 top-full z-20 mt-2 w-48 rounded-lg border border-gray-200 bg-white p-3 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                        <h6 class="mb-3 text-sm font-medium text-gray-900 dark:text-white">Filter by status</h6>
                        <ul class="space-y-2 text-sm" aria-label="Filter orders by status">
                            @foreach($statuses as $status => $count)
                            <li class="flex items-center">
                                <input id="orders-status-{{ $status }}" wire:model.change="filterStatus" type="checkbox" value="{{ $status }}" class="w-4 h-4 bg-gray-100 border-gray-300 rounded text-primary-600 focus:ring-primary-500 dark:focus:ring-primary-600 dark:ring-offset-gray-700 focus:ring-2 dark:bg-gray-600 dark:border-gray-500">
                                <label for="orders-status-{{ $status }}" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ ucfirst($status) }} ({{ $count }})</label>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    </div>
                    <div class="vh-orders-search flex min-w-0 items-center">
                        <label for="client-orders-search" class="sr-only">Search orders</label>
                        <div class="relative w-full">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg aria-hidden="true" class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <input type="search" wire:model.live.debounce.300ms="search" id="client-orders-search" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" placeholder="Search orders">
                        </div>
                    </div>
                </div>
            </div>
            <div class="vh-orders-scroll overflow-x-auto rounded-b-xl" role="region" aria-label="Service orders" tabindex="0">
                <table class="vh-orders-table w-full text-sm text-left text-gray-500 dark:text-gray-400">
                    <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-4 py-3">
                            <span class="sr-only">Expand/Collapse Row</span>
                        </th>
                        <th scope="col" class="px-4 py-3">Product</th>
                        <th scope="col" class="px-4 py-3">
                            Price Cycle
                        </th>
                        <th scope="col" class="px-4 py-3">
                            Members
                        </th>
                        <th scope="col" class="px-4 py-3">
                            Status
                        </th>
                        <th scope="col" class="px-4 py-3">
                            Due Date
                        </th>
                        <th scope="col" class="px-4 py-3">
                            Manage
                        </th>
                    </tr>
                    </thead>
                    @forelse($orders as $order)
                    <tbody wire:key="client-order-{{ $order->id }}" x-data="{ expanded: false }">
                    <tr class="border-b dark:border-gray-700 hover:bg-gray-200 dark:hover:bg-gray-700 cursor-pointer transition" @click="expanded = !expanded">
                        <td class="p-3 w-4">
                            <button type="button" class="flex h-8 w-8 items-center justify-center rounded-lg" @click.stop="expanded = !expanded" :aria-expanded="expanded" aria-controls="table-column-body-{{ $order->id }}" aria-label="Details for {{ $order->package->name }}" id="table-column-header-{{ $order->id }}">
                                <svg :class="{ 'rotate-180': expanded }" class="w-5 h-5 shrink-0 transition-transform" fill="currentColor" viewbox="0 0 20 20" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </td>
                        <th scope="row" class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                            <div class="flex items-center gap-3">
                                <img class="h-10 w-10 shrink-0 rounded-lg object-cover" src="{{ $order->package->icon() }}" alt="">
                                <span class="flex min-w-0 flex-col gap-1 break-words"> {{ $order->package->name }}
                                    <small class="text-gray-500 dark:text-gray-400">
                                        {{ $order->data['name'] ?? $order->package->category->name }}
                                    </small>
                                </span>
                            </div>
                        </th>
                        <td class="px-4 py-3" data-label="Billing">
                            @if($order->requiresBillingReview())
                                Billing review pending
                            @else
                            <div class="flex items-center text-gray-500 dark:text-gray-400">
                        <span class="mr-1 font-bold text-gray-500 dark:text-white">
                            {{ price($order->price) }}
                        </span>
                                / {{ $order->cycle() }}
                            </div>
                            @endif
                        </td>
                        <td class="px-4 py-3" data-label="Team">
                            <a href="{{ route('orders.view.members', $order->id) }}" wire:navigate @click.stop class="vh-text-link">Manage team</a>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap" data-label="Service status">
                            @if($order->status == 'active')
                                <x-theme::badge.success text="Active" />
                            @elseif($order->status == 'suspended')
                                <x-theme::badge.warning text="Suspended" />
                            @elseif($order->status == 'cancelled')
                                <x-theme::badge.danger text="Cancelled" />
                            @elseif($order->status == 'terminated')
                                <x-theme::badge.danger text="Terminated" />
                            @elseif(in_array($order->status, ['pending', 'processing']))
                                <x-theme::badge.primary text="{{ ucfirst($order->status) }}" />
                            @else
                                <x-theme::badge.warning text="{{ ucfirst($order->status) }}" />
                            @endif
                        </td>
                        <td class="px-4 py-3" data-label="Renews">
                            @if($order->requiresBillingReview())
                                Awaiting billing review
                            @elseif($order->due_date)
                                {{ $order->due_date->format('d M Y') }}
                            @else
                                Never
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('orders.view', $order->id) }}" wire:navigate @click.stop class="vh-action vh-action-secondary">Manage <x-theme::icon name="arrow" /></a>
                            @if(! $order->isTerminated())
                                @if($order->package->serverConnection?->extension_identifier === 'server-pterodactyl' && $order->external_id)
                                    <a href="{{ route('orders.panel', $order) }}" target="_blank" rel="noopener noreferrer" @click.stop class="vh-action vh-action-secondary">Open panel</a>
                                @endif
                                <a href="{{ route('orders.termination', $order) }}" wire:navigate @click.stop class="font-medium text-red-600 dark:text-red-400 hover:underline">Terminate</a>
                            @endif
                            </div>
                            @if($order->termination_requested_at && ! $order->isTerminated())
                                <p class="mt-2 text-sm text-amber-700 dark:text-amber-300">{{ $order->terminate_at?->isFuture() ? 'Terminates '.$order->terminate_at->format('d M Y H:i') : 'Termination requested' }}</p>
                            @endif
                        </td>
                    </tr>
                    <tr x-cloak x-show="expanded" id="table-column-body-{{ $order->id }}" aria-labelledby="table-column-header-{{ $order->id }}">
                        <td class="p-4 border-b dark:border-gray-700" colspan="7">
                            <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                                <h6 class="mb-2 text-base font-medium leading-none text-gray-900 dark:text-white">
                                    Details
                                </h6>
                                <x-theme::datagrid.grid :cols="3" :gap="4">
                                    <x-theme::datagrid.item>
                                        <x-slot:label>Package</x-slot:label>
                                        {{ $order->package->name }}
                                    </x-theme::datagrid.item>

                                    <x-theme::datagrid.item>
                                        <x-slot:label>Billing cycle</x-slot:label>
                                        @if($order->requiresBillingReview())
                                            Billing review pending
                                        @else
                                            <span class="mr-1 font-bold text-gray-500 dark:text-white">{{ price($order->price) }}</span> / {{ $order->cycle() }}
                                        @endif
                                    </x-theme::datagrid.item>

                                    <x-theme::datagrid.item>
                                        <x-slot:label>Status</x-slot:label>
                                        @if($order->status == 'active')
                                            <x-theme::badge.success text="Active" />
                                        @elseif($order->status == 'suspended')
                                            <x-theme::badge.warning text="Suspended" />
                                        @elseif($order->status == 'cancelled')
                                            <x-theme::badge.danger text="Cancelled" />
                                        @elseif($order->status == 'terminated')
                                            <x-theme::badge.danger text="Terminated" />
                                        @elseif(in_array($order->status, ['pending', 'processing']))
                                            <x-theme::badge.primary text="{{ ucfirst($order->status) }}" />
                                        @else
                                            <x-theme::badge.warning text="{{ ucfirst($order->status) }}" />
                                        @endif
                                    </x-theme::datagrid.item>

                                    <x-theme::datagrid.item>
                                        <x-slot:label>Due date</x-slot:label>
                                        @if($order->requiresBillingReview())
                                            Awaiting billing review
                                        @elseif($order->due_date)
                                            {{ $order->due_date->format('d M Y') }}
                                        @else
                                            Never
                                        @endif
                                    </x-theme::datagrid.item>

                                    <x-theme::datagrid.item>
                                        <x-slot:label>Last renewal date</x-slot:label>
                                        {{ $order->requiresBillingReview() ? 'Unknown' : $order->last_renewed_at->format('d M Y') }}
                                    </x-theme::datagrid.item>

                                    <x-theme::datagrid.item>
                                        <x-slot:label>Next Invoice</x-slot:label>
                                        @if($order->requiresBillingReview())
                                            On hold
                                        @elseif($order->due_date)
                                            {{ $order->due_date->diffForHumans() }}
                                        @else
                                            Never
                                        @endif
                                    </x-theme::datagrid.item>
                                </x-theme::datagrid.grid>
                                <div class="mt-4 flex items-center space-x-3">
                                    <a href="{{ route('orders.view', $order->id) }}" wire:navigate class="bg-primary-700 hover:bg-primary-800 focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800 flex items-center rounded-lg px-3 py-2 text-center text-sm font-medium text-white focus:outline-none focus:ring-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="mr-1 h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z"></path>
                                            <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd"></path>
                                        </svg>
                                        Manage
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                    @empty
                    <tbody>
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center">
                                <p class="font-medium text-gray-900 dark:text-white">No matching orders</p>
                                <p class="mt-1">Try another search or adjust your status filters.</p>
                            </td>
                        </tr>
                    </tbody>
                    @endforelse
                </table>
            </div>
        </div>
    @endif
</section>
