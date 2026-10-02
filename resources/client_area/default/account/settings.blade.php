@extends('theme::layouts.wrapper', [
    'activePage' => 'account-settings',
])

@section('title', 'Account Settings')

@section('content')
    <div class="mx-auto max-w-screen-xl px-4 2xl:px-0">
        <header class="vh-service-heading"><div><span class="vh-kicker">YOUR ACCOUNT</span><h1>Account &amp; security</h1><p>Manage your identity, protect access, and review your sessions.</p></div><a href="{{ route('dashboard') }}" wire:navigate class="vh-text-link">Back to services <x-theme::icon name="arrow" /></a></header>
        @livewire(client_view_path('account.livewire.account-settings'))
    </div>
@endsection
