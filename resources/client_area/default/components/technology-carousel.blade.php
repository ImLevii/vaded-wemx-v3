@php
    $technologies = [
        ['slug' => 'minecraft', 'name' => 'Minecraft', 'wordmark' => true],
        ['slug' => 'pterodactyl', 'name' => 'Pterodactyl', 'wordmark' => false],
        ['slug' => 'amd', 'name' => 'AMD', 'wordmark' => true],
        ['slug' => 'intel', 'name' => 'Intel', 'wordmark' => true],
        ['slug' => 'docker', 'name' => 'Docker', 'wordmark' => false],
    ];
@endphp

<section {{ $attributes->class('vh-technology-carousel') }} aria-label="Technologies we use" x-data="{ paused: false }" :data-paused="paused">
    <div class="vh-technology-heading">
        <span>The technology behind your server</span>
        <button type="button" class="vh-technology-toggle" @click="paused = !paused" :aria-pressed="paused" aria-pressed="false" aria-label="Pause logo carousel" :aria-label="paused ? 'Resume logo carousel' : 'Pause logo carousel'">
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path x-show="!paused" d="M5 4h3v12H5zM12 4h3v12h-3z" />
                <path x-show="paused" x-cloak d="m6 3 11 7-11 7z" />
            </svg>
        </button>
    </div>
    <div class="vh-technology-viewport">
        <div class="vh-technology-track">
            @foreach([false, true] as $isDuplicate)
                <ul class="vh-technology-group" @if($isDuplicate) aria-hidden="true" @endif role="list">
                    @foreach($technologies as $technology)
                        <li class="vh-technology-brand vh-technology-brand--{{ $technology['slug'] }}">
                            <img src="{{ asset('assets/common/img/technology-'.$technology['slug'].'.svg') }}" alt="{{ ! $isDuplicate && $technology['wordmark'] ? $technology['name'] : '' }}" width="{{ $technology['wordmark'] ? 150 : 36 }}" height="36" decoding="async">
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
