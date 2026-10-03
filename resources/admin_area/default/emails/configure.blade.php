@extends('admin::layouts.wrapper', [
    'activePage' => 'configure_emails',
])

@section('title', __('messages.configure_smtp'))

@section('content')
    <div class="alert alert-info m-0 mb-3">
        This page allows you to configure an SMTP server to send emails. You can configure other drivers outside of SMTP directly in the .env file.
        <a href="https://laravel.com/docs/12.x/mail" target="_blank" rel="noopener noreferrer" class="alert-link">Learn more</a>
    </div>
    @if(in_array(config('mail.default'), ['log', 'array'], true))
        <div class="alert alert-warning mb-3">Email delivery is disabled: the current mailer is {{ config('mail.default') }}. Save valid SMTP settings below to enable outgoing email.</div>
    @endif
    <div class="alert alert-info mb-3">Queued emails require a running queue worker. Scheduled campaigns require the Laravel scheduler.</div>

    @livewire(admin_view_path('emails.livewire.configure-smtp-form'))
@endsection
