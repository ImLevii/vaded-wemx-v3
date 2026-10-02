@extends('theme::auth.wrapper')

@section('content')
    <div class="flex flex-col items-center justify-center">
        <svg class="w-12 h-12 mb-4 text-primary-400 dark:text-primary-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
            <path fill-rule="evenodd" d="M7 6a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-2v-4a3 3 0 0 0-3-3H7V6Z" clip-rule="evenodd"/>
            <path fill-rule="evenodd" d="M2 11a2 2 0 0 1 2-2h11a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-7Zm7.5 1a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5Z" clip-rule="evenodd"/>
            <path d="M10.5 14.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z"/>
        </svg>

        <h1 class="text-2xl font-bold mb-4 text-center">Payment cancelled.</h1>
        <x-theme::text.p class="mb-8 text-center">
            Checkout was interrupted. Review your payment history before trying again, or return to your cart to check your configuration.
        </x-theme::text.p>
        <div class="w-full text-center">
            <x-theme::button.primary href="{{ route('dashboard.payments') }}" text="Review payments" confirm class="w-full sm:py-3.5 mb-4" />
        </div>
    </div>
@endsection
