@props(['current' => 1])
<ol {{ $attributes->class('vh-checkout-steps') }} aria-label="Order progress">
    @foreach(['Choose plan', 'Configure', 'Review', 'Checkout'] as $step)
        <li @if($loop->iteration === $current) aria-current="step" @endif @class(['is-complete' => $loop->iteration < $current])>
            <span>{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>{{ $step }}
        </li>
    @endforeach
</ol>
