@extends('admin::layouts.wrapper', [
    'activePage' => 'orders',
])

@section('title', __('messages.orders'))

@section('actions')
    <div class="col-auto ms-auto d-print-none">
        <div class="btn-list">
            <x-admin::button href="{{ route('admin.orders.create') }}" wire:navigate>{{ __('messages.create') }}</x-admin::button>
        </div>
    </div>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <ul class="nav nav-pills">
                @foreach(['recent', 'active', 'suspended', 'terminated', 'pending', 'processing', 'failed'] as $status)
                    <li class="nav-item">
                        <a class="nav-link @if(request()->get('status', 'recent') == $status) active @endif" aria-current="page" href="{{ route('admin.orders.index', ['status' => $status]) }}" wire:navigate>{{ ucfirst($status) }} {{ ($status == 'recent') ? \App\Models\Order::query()->count() : \App\Models\Order::query()->whereStatus($status)->count() }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    @php

    $query = request()->get('status', 'recent') == 'recent' ?
        \App\Models\Order::query()->latest() :
        \App\Models\Order::query()->latest()->where('status', request()->get('status', 'recent'));

    @endphp

    {{--  Orders Table  --}}
    @livewire(admin_view_path('livewire.table'), [
        'title' => __('messages.orders'),
        'entries' => 15,
        'columns' => [
            __('messages.id'),
            __('messages.name'),
            __('messages.package'),
            __('messages.user'),
            __('messages.amount'),
            __('messages.status'),
            __('messages.created_at'),
            '',
        ],
        'sortableColumns' => [
            __('messages.id'),
            __('messages.name'),
            __('messages.amount'),
            __('messages.status'),
            __('messages.created_at'),
        ],
        'rows' => $query->get()->map(function ($order) {
            return [
                $order->id,
                '<a href="' . route('admin.orders.edit', $order->id) . '" wire:navigate>' . $order->package->name . '</a>',
                '<div class="d-flex py-1 align-items-center"><img src="' . $order->package->icon() . '" class="avatar me-2" alt="' . $order->package->name . '"><div class="flex-fill"><div class="font-weight-medium"><a href="' . route('admin.packages.edit', $order->package_id) . '" wire:navigate class="text-reset">' . $order->package->name . '</a></div><div class="text-secondary"><a href="' . route('admin.categories.edit', $order->package->category_id) . '" wire:navigate class="text-reset">' . $order->package->category->name . '</a></div></div></div>',
                $order->user ? '<div class="d-flex py-1 align-items-center"><span class="avatar avatar-2 me-2" style="background-image: url(' . $order->user->getAvatarUrl() . ')"></span><div class="flex-fill"><div class="font-weight-medium"><a href="' . route('admin.users.edit', $order->user_id) . '" wire:navigate class="text-reset">' . $order->user->full_name . ' (' . $order->user->username . ')</a></div><div class="text-secondary"><a href="'. route('admin.users.edit', $order->user_id) .'" wire:navigate class="text-reset">' . $order->user->email . '</a></div></div></div>' : '<span class="badge bg-secondary-lt">Guest</span>',
                $order->requiresBillingReview() ? 'Billing review pending' : price($order->price) . ' / ' . $order->cycle(),
                $order->status == 'active' ? '<span class="badge bg-green-lt">Active</span>' : ($order->status == 'suspended' ? '<span class="badge bg-warning-lt">Suspended</span>' : ($order->status == 'terminated' ? '<span class="badge bg-danger-lt">Terminated</span>' : '<span class="badge bg-warning-lt">' . ucfirst($order->status) . '</span>')),
                $order->created_at->translatedFormat('d M Y H:i'),
                '<a href="' . route('admin.orders.edit', $order->id) . '" wire:navigate>' . __('messages.edit') . '</a>'
            ];
        })->toArray(),
    ])
@endsection
