<?php

use App\Models\User;
use Livewire\Volt\Component;

new class extends Component {
    public string $password = '';

    public string $tfa_code = '';

    public function submit(): void
    {
        User::authActions()->reauthenticateAdmin([
            'user_id' => auth()->id(),
            'password' => $this->password,
            'tfa_code' => $this->tfa_code ?: null,
        ]);

        session(['admin_reauthenticated_at' => now()->timestamp]);

        $redirectTo = session('admin_reauth_redirect_to', route('admin.index'));
        session()->forget('admin_reauth_redirect_to');

        $this->redirect($redirectTo, navigate: true);
    }
};

?>

<div class="container container-tight py-4">
    <div class="text-center mb-4">
        <a href="{{ route('dashboard') }}" aria-label="Vaded Hosting" class="navbar-brand navbar-brand-autodark">
            <img src="{{ settings('app_logo', '/assets/common/img/vaded-logo.png') }}" height="32" alt="{{ settings('app_name', config('app.name')) }}">
        </a>
    </div>

    <form class="card card-md" wire:submit="submit" autocomplete="off" novalidate>
        <div class="card-body text-center">
            <div class="mb-4">
                <h2 class="card-title">Admin Re-authentication</h2>
                <p class="text-secondary">
                    Please re-authenticate before continuing to the admin area.
                </p>
            </div>

            <div class="mb-4">
                <span class="avatar avatar-xl mb-3" style="background-image: url('{{ auth()->user()?->getAvatarUrl() }}')"></span>
                <h3 class="mb-0">{{ auth()->user()?->full_name ?? auth()->user()?->username }}</h3>
            </div>

            <div class="mb-3 text-start">
                <input
                    type="password"
                    wire:model="password"
                    class="form-control @error('password') is-invalid @enderror"
                    placeholder="Password..."
                    required
                    autofocus
                >
                @error('password')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            @if(auth()->user()?->tfa_enabled)
                <div class="mb-3 text-start">
                    <input
                        type="text"
                        wire:model="tfa_code"
                        class="form-control @error('tfa_code') is-invalid @enderror"
                        placeholder="2FA code..."
                        autocomplete="one-time-code"
                        required
                    >
                    @error('tfa_code')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            @endif

            <div>
                <button type="submit" class="btn btn-primary w-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-2 me-1" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 11m0 2a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2z"></path>
                        <path d="M12 16m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>
                        <path d="M8 11v-5a4 4 0 0 1 8 0"></path>
                    </svg>
                    Continue to Admin Area
                </button>
            </div>
        </div>
    </form>
</div>
