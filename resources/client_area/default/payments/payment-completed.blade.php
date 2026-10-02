@extends('theme::auth.wrapper')

@section('title', 'Thank you')

@section('standalone-content')
    <main class="vh-payment-shell">
        <div class="vh-payment-layout">
            <a href="{{ route('dashboard') }}" class="vh-payment-brand" aria-label="{{ settings('app_name', config('app.name')) }} dashboard">
                <x-theme::brand-logo width="48" height="48" />
                <span>{{ settings('app_name', config('app.name')) }}</span>
            </a>

            <section class="vh-payment-card" aria-labelledby="payment-thank-you">
                <div class="vh-payment-check" aria-hidden="true">
                    <span class="vh-payment-ripple"></span>
                    <svg width="96" height="96" viewBox="0 0 96 96" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle class="vh-payment-check-ring" cx="48" cy="48" r="42" pathLength="1" />
                        <path class="vh-payment-check-tick" d="M29 48L42 61L67 35" pathLength="1" />
                    </svg>
                </div>

                <span class="vh-payment-status">Payment submitted</span>
                <h1 id="payment-thank-you">Thank you!</h1>
                <p class="vh-payment-message">Thanks for choosing {{ settings('app_name', config('app.name')) }}. Your payment has been submitted and is being processed.</p>

                <div class="vh-payment-actions">
                    <a href="{{ route('dashboard') }}" class="vh-payment-primary">
                        View your services
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M5 12h14m-6-6 6 6-6 6" />
                        </svg>
                    </a>
                    <a href="{{ route('dashboard.payments') }}" class="vh-payment-secondary">View payment history</a>
                </div>

                <p class="vh-payment-note">You can check your payment history for confirmation and your dashboard for service status.</p>
            </section>

            <div class="vh-auth-footer">
                <span>{{ settings('app_name', config('app.name')) }} &copy; {{ now()->year }}</span>
                @if(auth()->user()?->hasPermission('admin.dashboard'))
                    <button type="button" class="vh-auth-theme" onclick="toggleDarkmode()" aria-label="Toggle light and dark theme">Light / Dark</button>
                @endif
            </div>
        </div>
    </main>
@endsection
