<?php

use Livewire\Volt\Component;
use App\Models\User;

new class extends Component
{
    public $first_name = '';

    public $last_name = '';

    public $username = '';

    public $email = '';

    public $password = '';

    public $password_confirmation = '';

    public function handleRegistration()
    {
        $this->resetErrorBag();

        $user = User::authActions()->registerAsClient([
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'username' => $this->username,
            'email' => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
            'log_user_in' => true,
        ]);

        $this->redirect(route('dashboard'));
    }
}

?>


<div>
    <div class="vh-form-heading"><h1>Build your next server.</h1><p>Create your Vaded account to configure, deploy, and manage your hosting.</p></div>

    @foreach(extensionElements(['client-register-top-view']) as $element)
        @includeIf($element['view'])
    @endforeach

    <form wire:submit="handleRegistration">
        <div class="my-2 grid gap-5 sm:grid-cols-2">
            <div class="mb-4">
                <x-theme::form.label for="first_name" text="First Name"/>
                <x-theme::form.input type="text" placeholder="First Name" wire:model="first_name" id="first_name"/>
                @error('first_name')
                <x-theme::form.error :text="$message"/>
                @enderror
            </div>

            <div class="mb-4">
                <x-theme::form.label for="last_name" text="Last Name"/>
                <x-theme::form.input type="text" placeholder="Last Name" wire:model="last_name" id="last_name"/>
                @error('last_name')
                <x-theme::form.error :text="$message"/>
                @enderror
            </div>
        </div>
        <div class="my-2 grid gap-5 sm:grid-cols-1">
            <div class="mb-4">
                <x-theme::form.label for="username" text="Username"/>
                <x-theme::form.input type="text" placeholder="Username" wire:model="username" id="username"/>
                @error('username')
                <x-theme::form.error :text="$message"/>
                @enderror
            </div>
        </div>
        <div class="my-2 grid gap-5 sm:grid-cols-1">
            <div class="mb-4">
                <x-theme::form.label for="email" text="Email"/>
                <x-theme::form.input type="email" placeholder="Email" wire:model="email" id="email"/>
                @error('email')
                <x-theme::form.error :text="$message"/>
                @enderror
            </div>
        </div>
        <div class="my-2 grid gap-5 sm:grid-cols-2 mb-4">
            <div class="mb-4">
                <x-theme::form.label for="password" text="Password"/>
                <x-theme::form.password autocomplete="new-password" placeholder="Password" wire:model="password" id="password"/>
                @error('password')
                <x-theme::form.error :text="$message"/>
                @enderror
            </div>
            <div class="mb-4">
                <x-theme::form.label for="password_confirmation" text="Confirm Password"/>
                <x-theme::form.password autocomplete="new-password" placeholder="Confirm Password" wire:model="password_confirmation"
                                     id="password_confirmation"/>
                @error('password_confirmation')
                <x-theme::form.error :text="$message"/>
                @enderror
            </div>
        </div>
        <x-theme::button.primary type="submit" text="Create account" wire:loading.attr="disabled" class="w-full justify-content-center mb-4"/>

        @foreach(extensionElements(['client-register-bottom-view']) as $element)
            @includeIf($element['view'])
        @endforeach

        <x-theme::text.p class="text-sm">Already have an account?
            <x-theme::text.link href="{{ route('login') }}" wire:navigate>Sign In</x-theme::text.link>
        </x-theme::text.p>
    </form>
</div>
