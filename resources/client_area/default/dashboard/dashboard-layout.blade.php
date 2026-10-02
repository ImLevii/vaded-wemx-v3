@extends('theme::layouts.wrapper', [
    'activePage' => 'dashboard',
])

@section('title', 'Dashboard')

@section('content')
@php
    $dashboardUser = auth()->user();
    $activeSubscriptionsCount = $dashboardUser->subscriptions()->where(function ($query) {
        $query->where('status', 'active')
            ->orWhere(function ($q) {
                $q->where('status', 'cancelled')
                    ->whereNotNull('next_billing_at')
                    ->where('next_billing_at', '>', now());
            });
    })->count();
    $totalSubscriptionsCount = $dashboardUser->subscriptions()->count();
@endphp
<div class="vh-dashboard mx-auto w-full min-w-0 max-w-screen-2xl">
    <div class="vh-dashboard-welcome flex flex-wrap items-center justify-between gap-4">
        <div class="min-w-0">
            <span class="vh-kicker">Client area / Overview</span>
            <h1>Welcome back, {{ $dashboardUser->first_name }}.</h1>
            <p>Your services, billing, and account. All under control.</p>
        </div>
        <a href="{{ route('categories.index') }}" wire:navigate class="vh-action">
            <span aria-hidden="true">+</span> Deploy a service
        </a>
    </div>

    <div class="vh-dashboard-stats grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-theme::stat label="Active services" :title="$dashboardUser->orders()->whereStatus('active')->count()" description="{{ $dashboardUser->orders()->whereStatus('suspended')->count() }} suspended · {{ $dashboardUser->orders()->whereStatus('terminated')->count() }} terminated" icon='<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 4h16v6H4z M4 14h16v6H4z M7 7h2 M7 17h2 M12 7h5 M12 17h5" /></svg>' />
        <x-theme::stat label="Account balance" :title="price($dashboardUser->balance)" description="Available for your next payment" icon='<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 5h16v15H4z M4 5V3h13v2 M15 10h5v5h-5z M17 12.5h1" /></svg>' />
        <x-theme::stat label="Active subscriptions" :title="$activeSubscriptionsCount" description="{{ $totalSubscriptionsCount }} subscriptions in total" icon='<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M20 7v5h-5 M4 17v-5h5 M6 7a7 7 0 0 1 12-1l2 3 M4 15l2 3a7 7 0 0 0 12-1" /></svg>' />
        <x-theme::stat label="Next renewal" :title="$nextRenewal ? price($nextRenewal->price) : 'No renewal due'" :description="$nextRenewal ? $nextRenewal->due_date->format('d M Y').' · '.$nextRenewal->package->name : 'Upcoming service renewals appear here'" icon='<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 5h16v16H4z M8 2v6 M16 2v6 M4 11h16 M8 15h3" /></svg>' />
    </div>

    <div class="vh-dashboard-grid grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
        <aside class="vh-dashboard-sidebar flex min-w-0 flex-col gap-5">
            <div class="vh-dashboard-panel overflow-hidden rounded-xl border">
                <div class="vh-account-identity flex items-center gap-3 p-5">
                    <img class="h-11 w-11 shrink-0 rounded-lg" src="{{ $dashboardUser->getAvatarUrl() }}" alt="" width="44" height="44">
                    <div class="min-w-0">
                        <h2 class="truncate">{{ $dashboardUser->full_name }}</h2>
                        <p class="truncate" title="{{ $dashboardUser->email }}">{{ $dashboardUser->email }}</p>
                    </div>
                </div>
                <nav class="vh-dashboard-nav flex flex-col gap-1 p-3" aria-label="Account navigation">
                    @foreach([
                        ['route' => 'dashboard', 'label' => 'Overview', 'icon' => 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z'],
                        ['route' => 'dashboard.payments', 'label' => 'Payments & invoices', 'icon' => 'M6 3h12v18l-3-2-3 2-3-2-3 2V3z M9 7h6 M9 11h6'],
                        ['route' => 'subscriptions.index', 'label' => 'Subscriptions', 'icon' => 'M20 7v5h-5 M4 17v-5h5 M6 7a7 7 0 0 1 12-1l2 3 M4 15l2 3a7 7 0 0 0 12-1'],
                        ['route' => 'dashboard.balance', 'label' => 'Balance history', 'icon' => 'M4 5h16v15H4z M4 5V3h13v2 M15 10h5v5h-5z'],
                        ['route' => 'dashboard.order-invites', 'label' => 'Service invitations', 'icon' => 'M3 5h18v14H3z M3 5l9 7 9-7'],
                        ['route' => 'account.settings', 'label' => 'Account settings', 'icon' => 'M12 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M4 21v-3a5 5 0 0 1 5-5h6a5 5 0 0 1 5 5v3'],
                    ] as $item)
                        <a href="{{ route($item['route']) }}" wire:navigate @class(['flex items-center gap-3 rounded-lg px-3 py-3', 'is-active' => request()->routeIs($item['route'])]) @if(request()->routeIs($item['route'])) aria-current="page" @endif>
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $item['icon'] }}" /></svg>
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>
            </div>

            <div class="vh-dashboard-panel vh-wallet rounded-xl border p-5">
                <span class="vh-stat-label">Your wallet</span>
                <div class="vh-wallet-amount">{{ price($dashboardUser->balance) }}</div>
                <p>Keep your next renewal covered.</p>
                <button type="button" class="vh-action mt-4 w-full" data-drawer-target="add-balance-drawer" data-drawer-show="add-balance-drawer" data-drawer-placement="right" aria-controls="add-balance-drawer">Add funds <span aria-hidden="true">+</span></button>
            </div>

            <div class="vh-dashboard-panel rounded-xl border p-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="vh-sidebar-heading">Account security</h2>
                    @if($dashboardUser->tfa_enabled)
                        <x-theme::badge.success text="Protected" />
                    @else
                        <x-theme::badge.warning text="Action needed" />
                    @endif
                </div>
                <p class="vh-sidebar-copy mt-3">{{ $dashboardUser->tfa_enabled ? 'Two-factor authentication adds an extra layer of protection to your account.' : 'Enable two-factor authentication to add an extra layer of protection.' }}</p>
                <a href="{{ route($dashboardUser->tfa_enabled ? 'account.settings' : 'enable-2fa') }}" wire:navigate class="vh-inline-link mt-4 inline-flex items-center gap-2">{{ $dashboardUser->tfa_enabled ? 'Security settings' : 'Enable two-factor' }} <span aria-hidden="true">&rarr;</span></a>
            </div>

            @foreach(extensionElements(['client-dashboard-sidebar-view']) as $element)
                @includeIf($element['view'], ['user' => $dashboardUser])
            @endforeach
        </aside>

        <div class="vh-dashboard-content min-w-0 space-y-6">

            <div class="vh-dashboard-shortcuts grid grid-cols-1 gap-3 sm:grid-cols-3">
                <a href="{{ route('dashboard.payments') }}" wire:navigate class="vh-dashboard-panel flex items-center justify-between gap-3 rounded-lg border p-4"><span><strong>Manage billing</strong><small>Payments & invoices</small></span><span aria-hidden="true">&rarr;</span></a>
                <a href="{{ route('subscriptions.index') }}" wire:navigate class="vh-dashboard-panel flex items-center justify-between gap-3 rounded-lg border p-4"><span><strong>Subscriptions</strong><small>Review your renewals</small></span><span aria-hidden="true">&rarr;</span></a>
                <a href="{{ route('dashboard.email-inbox') }}" wire:navigate class="vh-dashboard-panel flex items-center justify-between gap-3 rounded-lg border p-4"><span><strong>Email inbox</strong><small>Your account updates</small></span><span aria-hidden="true">&rarr;</span></a>
            </div>

            @foreach(extensionElements(['client-dashboard-top-view']) as $element)
                @includeIf($element['view'], ['user' => $dashboardUser])
            @endforeach

            @yield('container')

            @foreach(extensionElements(['client-dashboard-bottom-view']) as $element)
                @includeIf($element['view'], ['user' => $dashboardUser])
            @endforeach
        </div>
    </div>
    @livewire(client_view_path('dashboard.livewire.add-balance-drawer'))
</div>
@endsection
