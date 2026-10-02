<?php

use App\Models\Currency;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    /** @return Collection<int, Currency> */
    #[Computed]
    public function currencies(): Collection
    {
        return Currency::query()->where('is_active', true)->orderBy('sort_order')->get();
    }

    public function setCurrency(string $currencyCode): void
    {
        if (! $this->currencies->contains('currency', $currencyCode)) {
            return;
        }

        Session::put('currency', $currencyCode);

        $this->redirect(request()->header('Referer', route('dashboard')), true);
    }
};
?>

<div class="vh-currency" x-data="{ open: false }" @click.outside="open = false"
    @keydown.escape.stop="open = false; $refs.trigger.focus()"
    @focusout="$nextTick(() => { if (!$el.contains(document.activeElement)) open = false })"
    @vaded-nav-open.window="open = false" @vaded-nav-close.window="open = false">
    @php($selectedCurrency = session('currency', settings('currency', 'USD')))
    <button id="currency-selector" type="button" class="vh-currency-trigger" x-ref="trigger"
        aria-label="{{ __('Currency: :currency', ['currency' => $selectedCurrency]) }}"
        aria-controls="currency-dropdown-menu" aria-expanded="false" :aria-expanded="open.toString()"
        @click="open = !open"
        @keydown.arrow-down.prevent="open = true; $nextTick(() => $refs.panel.querySelector('button')?.focus())">
        @include('theme::components.currency-flag', ['currencyCode' => $selectedCurrency])
        <span>{{ $selectedCurrency }}</span>
        <svg class="vh-currency-chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m4 6 4 4 4-4" /></svg>
    </button>
    <div id="currency-dropdown-menu" class="vh-currency-panel" x-ref="panel" x-show="open" x-cloak aria-labelledby="currency-selector">
        <p class="vh-currency-heading">{{ __('Select currency') }}</p>
        <ul class="vh-currency-list">
            @foreach ($this->currencies as $currency)
                <li wire:key="currency-{{ $currency->currency }}">
                    <button type="button" wire:click="setCurrency('{{ $currency->currency }}')"
                        wire:loading.attr="disabled" wire:target="setCurrency"
                        class="vh-currency-option" aria-pressed="{{ $selectedCurrency === $currency->currency ? 'true' : 'false' }}">
                        @include('theme::components.currency-flag', ['currencyCode' => $currency->currency])
                        <span class="vh-currency-copy"><strong>{{ $currency->currency }}</strong><small>{{ $currency->display_name }}</small></span>
                        @if ($selectedCurrency === $currency->currency)
                            <svg class="vh-currency-check" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m5 10 3 3 7-7" /></svg>
                        @endif
                    </button>
                </li>
            @endforeach
        </ul>
    </div>
</div>
