@extends('admin::layouts.wrapper', [
    'activePage' => 'users'
])

@section('title',  __('messages.viewing') . ' '. $user->username)

@section('content')
<div class="row g-3 align-items-center mb-3">
    <div class="col-auto">
        @if($user->isOnline())
            <span class="status-indicator status-green status-indicator-animated">
              <span class="status-indicator-circle"></span>
              <span class="status-indicator-circle"></span>
              <span class="status-indicator-circle"></span>
            </span>
        @else
            <span class="status-indicator status-red status-indicator-animated">
              <span class="status-indicator-circle"></span>
              <span class="status-indicator-circle"></span>
            </span>
        @endif
    </div>
    <div class="col">
        <h2 class="page-title">{{ $user->full_name }}</h2>
        <div class="text-secondary">
            <ul class="list-inline list-inline-dots mb-0">
                <li class="list-inline-item">
                    @if($user->isOnline())
                        <span class="text-green">Online</span>
                    @else
                        <span class="text-red">Offline</span>
                    @endif
                </li>
                <li class="list-inline-item">
                    Last seen: {{ $user->last_seen_at ? $user->last_seen_at->diffForHumans() . ' ('. $user->last_seen_at->format(settings('date_format', 'd M Y H:i')) .')' : 'Never' }}
                </li>
            </ul>
        </div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12">
        @livewire(admin_view_path('users.livewire.user-info-card'), ['user' => $user])
    </div>
</div>

<div class="row">
  <div class="col-4">
    <div>
        @livewire(admin_view_path('users.livewire.user-sessions'), ['user_id' => $user->id])
    </div>

      <div class="mb-3">
          @livewire(admin_view_path('livewire.log'), ['model' => \App\Models\User::class, 'model_id' => $user->id])
      </div>

      <div class="mb-3">
          @livewire(admin_view_path('users.livewire.user-balance-card'), ['user' => $user])
      </div>
  </div>
    <div class="col-8">
        @php
            $userIps = \App\Models\Session::query()
                ->where('user_id', $user->id)
                ->whereNotNull('ip_address')
                ->pluck('ip_address')
                ->filter()
                ->unique();

            $altUsersCount = 0;
            if ($userIps->isNotEmpty()) {
                $altUserIds = \App\Models\Session::query()
                    ->whereNotNull('user_id')
                    ->where('user_id', '!=', $user->id)
                    ->whereIn('ip_address', $userIps)
                    ->pluck('user_id')
                    ->filter()
                    ->unique();
                $altUsersCount = $altUserIds->count();
            }
        @endphp

        @if($altUsersCount > 0)
            <x-admin::alerts.action
                class="mb-3"
                variant="danger"
                title="Potential alt accounts detected"
                :message="'Potential alt accounts detected: ' . $altUsersCount . ' possible alt account' . ($altUsersCount === 1 ? '' : 's') . ' based on shared session IPs. '"
                link-text="Review alt accounts"
                :link-href="route('admin.users.edit', ['user' => $user->id, 'userEditPage' => 'alt-accounts'])"
            />
        @endif

        @if($user->hasActiveBan())
            <x-admin::alerts.action
                class="mb-3"
                variant="danger"
                :message="'This user currently has an active ban. '"
                link-text="Open moderation tab"
                :link-href="route('admin.users.edit', ['user' => $user->id, 'userEditPage' => 'moderation'])"
            />
        @endif

        @if($user->hasBanHistory())
            <x-admin::alerts.action
                class="mb-3"
                variant="warning"
                :message="'This user has ban history on record. '"
                link-text="Review history"
                :link-href="route('admin.users.edit', ['user' => $user->id, 'userEditPage' => 'moderation'])"
            />
        @endif

        <div>
            @livewire(admin_view_path(('users.livewire.user-alerts')), ['user' => $user])
        </div>

        <div class="mb-3">
            @livewire(admin_view_path('users.livewire.edit-user-form'), ['user' => $user])
        </div>

        <div class="mb-3">
            @livewire(admin_view_path('livewire.table'), [
                'title' => 'Orders',
                'columns' => [
                    'ID',
                    'Package',
                    'Server',
                    'Amount',
                    'Status',
                    'Expires at',
                    'Created at',
                    '',
                ],
                'sortableColumns' => [
                    'ID',
                    'Package',
                    'Amount',
                    'Status',
                    'Expires at',
                    'Created at',
                ],
                'rows' => $user->orders->map(function ($order) {
                    return [
                        $order->id,
                        '<div class="d-flex py-1 align-items-center">
                           <img src="'. $order->package->icon() .'" class="avatar me-2" alt="'. $order->package->name .'">
                            <span class=""></span>
                            <div class="flex-fill">
                                <div class="font-weight-medium"><a href="https://wemxv2.test/admin/packages/edit/2" wire:navigate="" class="text-reset">'. $order->package->name .'</a></div>
                                <div class="text-secondary"><a href="https://wemxv2.test/admin/categories/edit/1" wire:navigate="" class="text-reset">'. $order->package->category->name .'</a></div>
                            </div>
                        </div>',
                        '<a href="' . route('admin.servers.connections.edit', $order->package->serverConnection->id) . '" wire:navigate>'. $order->package->serverConnection->alias .'</a>',
                        price($order->price) .' / '. $order->cycle(),
                        $order->status === 'active' ? '<span class="status status-green">'. __('messages.active') .'</span>' : '<span class="status status-red">'. __('messages.inactive') .'</span>',
                        $order->due_date ? $order->due_date->format(settings('date_format', 'd M Y H:i')) : 'N/A',
                        $order->created_at->format(settings('date_format', 'd M Y H:i')),
                        '<a href="' . route('admin.orders.edit', $order->id) . '" wire:navigate>View</a>',
                    ];
                })->toArray(),
            ])
        </div>
        <div class="mb-3">
            @livewire(admin_view_path('livewire.table'), [
                'title' => 'Payments',
                'columns' => [
                    'ID',
                    'Transaction ID',
                    'Description',
                    'Amount',
                    'Currency',
                    'Status',
                    'Gateway',
                    '',
                ],
                'sortableColumns' => [
                    'ID',
                    'Transaction ID',
                    'Description',
                    'Amount',
                    'Currency',
                    'Status',
                    'Gateway',
                ],
                'rows' => $user->payments()->whereStatus('paid')->get()->map(function ($payment) {
                    return [
                        $payment->id,
                        $payment->transaction_id,
                        Str::limit($payment->description, 50),
                        price($payment->total(), in: $payment->currency, to: $payment->currency),
                        strtoupper($payment->currency),
                        $payment->status === 'paid' ? '<span class="status status-green">'. __('messages.paid') .'</span>' : '<span class="status status-red">'. __('messages.unpaid') .'</span>',
                        $payment->gatewayConfig ? $payment->gatewayConfig->display_name : 'N/A',
                        '<a href="' . route('admin.payments.edit', $payment->id) . '" wire:navigate>View</a>',
                    ];
                })->toArray(),
            ])
        </div>
        @foreach(extensionElements('admin-customer-bottom-view') as $element)
            @includeIf($element['view'], ['user' => $user])
        @endforeach
    </div>
</div>
@endsection
