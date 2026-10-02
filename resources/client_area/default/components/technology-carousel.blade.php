@php
    $technologies = [
        ['slug' => 'minecraft', 'name' => 'Minecraft', 'wordmark' => true],
        ['slug' => 'pterodactyl', 'name' => 'Pterodactyl', 'wordmark' => false],
        ['slug' => 'amd', 'name' => 'AMD', 'wordmark' => true],
        ['slug' => 'intel', 'name' => 'Intel', 'wordmark' => true],
        ['slug' => 'docker', 'name' => 'Docker', 'wordmark' => false],
    ];
@endphp

<section {{ $attributes->class('vh-technology-carousel') }} aria-label="Technologies we use">
    <div class="vh-technology-heading">
        <span>The technology behind your server</span>
    </div>
    <div class="vh-technology-viewport" tabindex="0" role="group" aria-label="Product logos. Drag or use the arrow keys to browse.">
        <div class="vh-technology-track">
            @foreach([false, true] as $isDuplicate)
                <ul class="vh-technology-group" @if($isDuplicate) aria-hidden="true" @endif role="list">
                    @foreach($technologies as $technology)
                        <li class="vh-technology-brand vh-technology-brand--{{ $technology['slug'] }}">
                            <img src="{{ asset('assets/common/img/technology-'.$technology['slug'].'.svg') }}" alt="{{ ! $isDuplicate && $technology['wordmark'] ? $technology['name'] : '' }}" width="{{ $technology['wordmark'] ? 150 : 36 }}" height="36" decoding="async" draggable="false">
                            @unless($technology['wordmark'])
                                <span>{{ $technology['name'] }}</span>
                            @endunless
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </div>
    </div>
</section>
