@extends('theme::layouts.wrapper', ['activePage' => 'dashboard'])

@section('title', 'Terminate service #'.$order->id)

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-theme::card>
            <h1 class="mb-4 text-2xl font-bold text-gray-900 dark:text-white">Terminate {{ $order->package->name }} (#{{ $order->id }})</h1>
            @if($order->isTerminated())
                <p class="text-gray-600 dark:text-gray-300">This service has already been terminated.</p>
            @elseif($order->termination_requested_at && ! $order->terminate_at?->isFuture())
                <p class="text-gray-600 dark:text-gray-300">Termination is being processed. Contact support if it does not complete.</p>
            @else
                <p class="mb-4 text-gray-600 dark:text-gray-300">Termination permanently deletes your service and its data. Download any backups you need first. Automatic renewal and recurring billing will be cancelled. This does not issue a refund.</p>
                @if($order->termination_requested_at)
                    <x-theme::alert.warning text="{{ 'Termination is scheduled for '.$order->terminate_at->format('d M Y H:i').'. You can choose to terminate sooner.' }}" />
                @endif
                <form method="POST" action="{{ route('orders.terminate', $order) }}" class="flex flex-col gap-4">
                    @csrf
                    <fieldset class="flex flex-col gap-3">
                        <legend class="mb-2 font-medium text-gray-900 dark:text-white">When should this service end?</legend>
                        @if(! $order->termination_requested_at && $order->due_date?->isFuture())
                            <label class="flex items-center gap-3 text-gray-700 dark:text-gray-300">
                                <input type="radio" name="termination_mode" value="due_date" @checked(old('termination_mode', 'due_date') === 'due_date') required>
                                At the next due date: {{ $order->due_date->format('d M Y H:i') }}
                            </label>
                        @endif
                        <label class="flex items-center gap-3 text-gray-700 dark:text-gray-300">
                            <input type="radio" name="termination_mode" value="now" @checked(old('termination_mode', $order->termination_requested_at || ! $order->due_date?->isFuture() ? 'now' : 'due_date') === 'now') required>
                            Right away
                        </label>
                    </fieldset>
                    @error('termination_mode') <x-theme::form.error :text="$message" /> @enderror
                    <label class="flex items-start gap-3 text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="confirm_termination" value="1" required>
                        I understand that my service and its data will be permanently deleted.
                    </label>
                    @error('confirm_termination') <x-theme::form.error :text="$message" /> @enderror
                    <div class="flex flex-wrap gap-3">
                        <x-theme::button.danger type="submit" text="Confirm termination" />
                        <x-theme::button.primary href="{{ route('dashboard') }}" text="Back to services" />
                    </div>
                </form>
            @endif
        </x-theme::card>
    </div>
@endsection
