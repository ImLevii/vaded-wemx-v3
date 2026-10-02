<?php

use Livewire\Volt\Component;
use App\Models\User;

new class extends Component
{
    public $username = '';

    public $password = '';

    public $remember = false;

    public function handleLogin()
    {
        $this->resetErrorBag();

        User::authActions()->loginAsClient([
            'username' => $this->username,
            'password' => $this->password,
            'remember' => $this->remember,
        ]);

        $this->redirect(route('dashboard'));
    }
}

?>


<form class="w-full max-w-md space-y-4 md:space-y-6 xl:max-w-xl" wire:submit="handleLogin">
    <x-theme::text.h5>Welcome back</x-theme::text.h5>

    @foreach(extensionElements(['client-login-top-view']) as $element)
        @includeIf($element['view'])
    @endforeach

    <div class="mb-4">
        <x-theme::form.label for="username" text="Email or username"/>
        <x-theme::form.input type="text" placeholder="Email or username" wire:model="username" id="username" autocomplete="username"/>
        @error('username')
        <x-theme::form.error :text="$message"/>
        @enderror
    </div>

    <div class="mb-4">
        <x-theme::form.label for="password" text="Password"/>
        <x-theme::form.input type="password" placeholder="Password" class="mb-3" wire:model="password" id="password"/>
        @error('password')
        <x-theme::form.error :text="$message"/>
        @enderror
    </div>

    <div class="flex items-center justify-between">
        <x-theme::form.checkbox label="Remember me" id="remember" wire:model="remember"/>
        <x-theme::text.link text="Forgot Password?" class="text-sm" wire:navigate href="{{ route('forgot-password') }}"/>
    </div>
    <x-theme::button.primary type="submit" text="Sign in to your account" class="w-full justify-content-center"/>

    @foreach(extensionElements(['client-login-bottom-view']) as $element)
        @includeIf($element['view'])
    @endforeach

    @if(settings('enable_registrations', true))
        <x-theme::text.p class="text-sm">Don't have an account?
            <x-theme::text.link href="{{ route('register') }}" wire:navigate>Sign Up</x-theme::text.link>
        </x-theme::text.p>
    @endif
</form>
