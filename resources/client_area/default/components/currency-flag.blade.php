@php
    $countryCode = [
        'USD' => 'us', 'EUR' => 'eu', 'GBP' => 'gb', 'AUD' => 'au', 'CAD' => 'ca',
        'JPY' => 'jp', 'CHF' => 'ch', 'CNY' => 'cn', 'SEK' => 'se', 'NOK' => 'no',
        'DKK' => 'dk', 'NZD' => 'nz', 'BRL' => 'br', 'MXN' => 'mx', 'INR' => 'in',
        'PKR' => 'pk', 'RUB' => 'ru', 'PLN' => 'pl', 'CZK' => 'cz', 'HUF' => 'hu', 'SAR' => 'sa',
    ][$currencyCode] ?? null;
@endphp

@if ($countryCode)
    <img class="vh-currency-flag" src="{{ asset('assets/common/img/flags/'.$countryCode.'.svg') }}" alt="" width="24" height="18">
@else
    <svg class="vh-currency-flag vh-currency-globe" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18M5 7h14M5 17h14"/></svg>
@endif
