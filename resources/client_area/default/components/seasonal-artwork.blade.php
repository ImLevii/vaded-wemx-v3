@props(['edition' => null])

<svg class="vh-logo-artwork" viewBox="0 0 64 64" fill="none" aria-hidden="true" focusable="false" stroke-linecap="round" stroke-linejoin="round">
    @if($edition === null || $edition === 'spring')
    <g data-edition="spring">
        <path class="vh-logo-trail" d="M5 38C-2 19 6 5 25 5M42 58C55 56 63 46 60 33" stroke="#8ac5aa" stroke-width=".65" opacity=".55" />
        <g transform="translate(14 9)"><g class="vh-logo-sway">
            <path d="M-8 1C-10-3-6-7-2-4C-3-11 4-11 5-5C11-7 13 0 7 3C12 8 6 12 2 7C0 13-7 10-5 5C-11 7-13 2-8 1Z" fill="#e99fb9" stroke="#ffe0e9" stroke-width=".6" />
            <path d="M-6 1-2 1M1-6 2-2M7 0 4 1M5 7 3 4M-3 7 0 4" stroke="#fbd1df" stroke-width=".8" />
            <circle cx="1" cy="2" r="2.2" fill="#ffe4a0" /><circle cx="1" cy="2" r=".8" fill="#bb8c42" />
        </g></g>
        <g transform="translate(53 43)"><g class="vh-logo-sway vh-logo-phase">
            <path d="M0 7C-6 4-8-3-5-7C0-6 3-2 0 7Z" fill="#81bca3" /><path d="M0 7C1-1 5-7 11-7C11 0 6 5 0 7Z" fill="#b5d8a5" /><path d="M0 11V3M1 3 6-2M-1 3-4-2" stroke="#4d9379" stroke-width=".7" />
        </g></g>
        <g transform="translate(56 13)"><g class="vh-logo-butterfly"><path d="M0 0C-10-12-13 4-2 4C-9 11 1 12 1 4C6 12 13 7 6 2C15-7 5-12 2-1Z" fill="#c4b0e2" /><path d="M1-2 2 6" stroke="#705a99" stroke-width=".9" /></g></g>
        <g class="vh-logo-petal" fill="#f1b8ce"><path d="M5 31C0 26 8 23 5 31Z" /><path d="M34 3C28-2 37-5 34 3Z" /></g>
        <g class="vh-logo-petal vh-logo-phase" fill="#ffdce8"><path d="M9 52C3 47 12 43 9 52Z" /><path d="M55 29C49 24 58 20 55 29Z" /></g>
    </g>
    @endif
    @if($edition === null || $edition === 'summer')
    <g data-edition="summer">
        <path class="vh-logo-trail" d="M3 31C2 14 14 3 30 3M37 60C50 58 60 49 61 35" stroke="#edc071" stroke-width=".65" opacity=".5" />
        <g transform="translate(50 11)">
            <circle class="vh-logo-aura" r="12" fill="#f7ba59" opacity=".12" />
            <g class="vh-logo-sun-rays" stroke="#eab25c" stroke-width="1.2"><path d="M0-12V-9M0 9V12M-12 0H-9M9 0H12M-8.5-8.5-6.5-6.5M6.5 6.5 8.5 8.5M8.5-8.5 6.5-6.5M-6.5 6.5-8.5 8.5" /></g>
            <circle r="6.5" fill="#efb34d" stroke="#ffe9b6" stroke-width=".8" /><path d="M-4-1A4.5 4.5 0 0 1 1-4" stroke="#fff3d5" stroke-width="1.4" />
        </g>
        <g transform="translate(12 51)"><g class="vh-logo-sway"><path d="M-8 3C-3-5 6-6 10 1L2 7Z" fill="#65b9b7" stroke="#c6eece" stroke-width=".7" /><path d="M-8 3 2 7 10 1M-2-2 2 7 5-3" stroke="#e3f5cc" stroke-width=".7" /><path d="M2 7 1 10" stroke="#519891" /></g></g>
        <g class="vh-logo-wave" stroke="#72c5cc" stroke-width=".9" opacity=".85"><path d="M32 56Q37 53 42 56T52 56M33 59Q38 56 43 59T53 59" /></g>
        <g class="vh-logo-twinkle" fill="#fff2c0"><path d="M8 18 9 21 12 22 9 23 8 26 7 23 4 22 7 21Z" /><circle cx="32" cy="5" r="1" /></g>
    </g>
    @endif
    @if($edition === null || $edition === 'autumn')
    <g data-edition="autumn">
        <g class="vh-logo-autumn-wind" stroke="#d0a36a" stroke-width=".55" opacity=".45">
            <path d="M4 42C-2 34-1 20 4 14M33 3C41 0 49 2 54 7" />
            <path d="M18 59C32 65 53 60 60 49M20 62C30 66 41 65 48 61" />
        </g>
        <path d="M4 24C3 13 9 5 23 5M8 14 4 10M14 7 17 2M19 6 25 9" stroke="#927049" stroke-width=".9" />
        <g transform="translate(11 8)"><g class="vh-logo-autumn-maple">
            <path d="M0 8Q-2 4-6 5L-5 2Q-8 1-9-2L-6-3-7-7-3-5Q-2-8 0-10Q2-7 3-5L7-7 6-3 9-2Q8 1 5 2L6 5Q2 4 0 8Z" fill="#cf8244" stroke="#f1c589" stroke-width=".55" />
            <path d="M0-9Q-1-3 0 7Q-3 2-6 3L-5 0-7-2-4-3-5-5-2-4Z" fill="#e9aa58" />
            <path d="M0 7Q4 2 6 3L4 1 7-1 4-2 5-5 2-4Z" fill="#b46535" opacity=".8" />
            <path d="M0 10V-7M0 2-5-2M0 1 5-3M0-2-2-5M0 4 3 2" stroke="#854b2e" stroke-width=".6" />
            <path d="M-6-5-3-3M1-7 2-5M5-2 7-2" stroke="#f9d49a" stroke-width=".6" />
        </g></g>
        <g transform="translate(25 6) rotate(62)"><g class="vh-logo-autumn-oak">
            <path d="M0 7C-2 5-5 7-5 3C-9 2-7-2-4-1C-7-5-3-7-1-4C-2-10 3-10 3-4C7-7 9-3 5-1C10 0 8 4 5 3C7 7 2 8 0 7Z" fill="#bc7540" stroke="#e5b778" stroke-width=".5" />
            <path d="M0 9 1-6M1 2-4 0M1 0 4-2" stroke="#805332" stroke-width=".6" />
            <path d="M0-6Q-1-2 0 6Q-3 2-4 2L-2 0-3-3Z" fill="#e4ac63" opacity=".65" />
        </g></g>
        <g transform="translate(53 8) rotate(16)"><g class="vh-logo-autumn-acorn">
            <path d="M-4 0C-5 5-2 10 0 11C3 9 5 5 4 0Z" fill="#bd8950" stroke="#efc48a" stroke-width=".55" />
            <path d="M0 2C-1 6 0 9 0 11Q-5 7-4 1Z" fill="#e0b170" />
            <path d="M2 3Q3 6 1 9" stroke="#946039" stroke-width=".7" />
            <path d="M-5 1C-7-6 5-7 6 1Q1 4-5 1Z" fill="#745239" stroke="#b38a5e" stroke-width=".55" />
            <path d="M-3-3 0 0 3-3M-5-1-2 1 1-2 4 1M0-5 0-8" stroke="#be9769" stroke-width=".6" />
            <path d="M-2-4Q0-5 2-4" stroke="#e3bd85" stroke-width=".6" />
        </g></g>
        <path d="M19 56C36 64 57 60 60 44M37 59 40 54M47 58 52 60M53 54 56 49" stroke="#98714b" stroke-width=".85" />
        <g transform="translate(59 40) rotate(24)"><g class="vh-logo-autumn-russet">
            <path d="M0 9C-6 6-7 1-3-2Q-6-5-2-7Q-1-10 2-11C6-6 7-2 4 1C7 4 5 8 0 9Z" fill="#a95137" stroke="#d88a60" stroke-width=".55" />
            <path d="M1-9C-2-4-1 2 0 8Q-4 5-3 2L-1 0-2-3Z" fill="#d17b45" />
            <path d="M0 12 1-7M0 4-3 1M1 0 4-3" stroke="#703f2d" stroke-width=".6" />
            <path d="M2-8 4-5M4 4 2 7" stroke="#e0a56c" stroke-width=".6" />
        </g></g>
        <g transform="translate(46 57) rotate(72)"><g class="vh-logo-autumn-oak vh-logo-phase">
            <path d="M0 6C-2 4-5 5-4 2C-7 1-6-2-3-1C-5-4-2-6 0-4C0-8 4-7 3-3C7-5 8-1 4 0C8 2 6 5 3 3C5 7 2 7 0 6Z" fill="#d7a051" stroke="#f2ca86" stroke-width=".5" />
            <path d="M0 8 1-4M0 2-3 0M1 1 4-1" stroke="#a26c34" stroke-width=".6" />
        </g></g>
        <g transform="translate(32 58) rotate(-65)"><g class="vh-logo-autumn-oak vh-logo-phase-late">
            <path d="M0 6C-6 4-7-3 2-7C7-2 6 5 0 6Z" fill="#aa633e" stroke="#dca576" stroke-width=".5" />
            <path d="M-1 8 2-5M0 2-3 0M1 0 4-2" stroke="#75452f" stroke-width=".6" />
        </g></g>
        <g transform="translate(5 32)"><g class="vh-logo-autumn-falling">
            <path d="M0 4-2 1-4 1-2-1-3-3 0-2 2-4 2-1 4 0 2 2Z" fill="#dca154" stroke="#edc080" stroke-width=".4" /><path d="M0 5 1-2" stroke="#9d683a" stroke-width=".4" />
        </g></g>
        <g transform="translate(60 26)"><g class="vh-logo-autumn-falling vh-logo-phase-late">
            <path d="M0 3C-5 1-3-5 2-5C5-1 4 3 0 3Z" fill="#bc6a3e" stroke="#e8ae70" stroke-width=".4" /><path d="M-1 4 2-3" stroke="#885332" stroke-width=".4" />
        </g></g>
        <g class="vh-logo-autumn-dust" fill="#d8ac65"><circle cx="4" cy="48" r=".7" /><circle cx="38" cy="4" r=".6" /><circle cx="55" cy="54" r=".65" /></g>
        <g class="vh-logo-autumn-dust vh-logo-phase" fill="#edcb90"><circle cx="3" cy="21" r=".55" /><circle cx="62" cy="37" r=".55" /><circle cx="23" cy="60" r=".5" /></g>
    </g>
    @endif
    @if($edition === null || $edition === 'winter')
    <g data-edition="winter">
        <path class="vh-logo-frost" d="M10 14Q12 8 19 10Q23 6 30 10Q35 6 43 10Q50 7 54 14L48 13 45 18 43 13 23 13 19 17 17 13Z" fill="#d9eff8" stroke="#f6fdff" stroke-width=".6" />
        <path d="M15 11 23 10M32 10 39 10M47 11 51 12" stroke="#fff" stroke-width=".9" />
        <g transform="translate(9 39)"><g class="vh-logo-crystal" stroke="#acd6eb" stroke-width=".8"><path d="M0-8V8M-7-4 7 4M-7 4 7-4M-2-6 0-4 2-6M-2 6 0 4 2 6M-6-1-3-2-3-5M3 5 3 2 6 1M-6 1-3 2-3 5M3-5 3-2 6-1" /><circle r="1.5" fill="#eefbff" /></g></g>
        <g transform="translate(55 20)"><g class="vh-logo-crystal vh-logo-phase" stroke="#e1f6ff" stroke-width=".8"><path d="M0-6V6M-5-3 5 3M-5 3 5-3M-2-4 0-2 2-4M-2 4 0 2 2 4" /></g></g>
        <path class="vh-logo-trail" d="M18 58C36 63 57 54 60 37" stroke="#b5deee" stroke-width=".6" opacity=".55" />
        <g class="vh-logo-snow" fill="#deeffa"><circle cx="4" cy="22" r="1" /><circle cx="30" cy="3" r="1.1" /><circle cx="60" cy="43" r="1.2" /></g>
        <g class="vh-logo-snow vh-logo-phase" fill="#fff"><circle cx="16" cy="52" r="1.3" /><circle cx="45" cy="4" r=".8" /><circle cx="49" cy="55" r=".9" /></g>
        <path class="vh-logo-twinkle" d="M53 47 54 50 57 51 54 52 53 55 52 52 49 51 52 50Z" fill="#e3f5ff" />
    </g>
    @endif
    @if($edition === null || $edition === 'newYear')
    <g data-edition="newYear">
        <g transform="translate(12 10)"><g class="vh-logo-firework" stroke="#f1ca7e"><circle class="vh-logo-firework-ring" r="8" stroke-width=".6" opacity=".5" /><path class="vh-logo-firework-rays" d="M0-10V-4M0 4V10M-10 0H-4M4 0H10M-7-7-3-3M3 3 7 7M7-7 3-3M-3 3-7 7" stroke-width="1.1" /><circle r="1.3" fill="#fff3ca" /></g></g>
        <g transform="translate(53 19)"><g class="vh-logo-firework vh-logo-phase" stroke="#c7b7ef"><circle class="vh-logo-firework-ring" r="8" stroke-width=".6" opacity=".5" /><path class="vh-logo-firework-rays" d="M0-9V-4M0 4V9M-9 0H-4M4 0H9M-6-6-3-3M3 3 6 6M6-6 3-3M-3 3-6 6" stroke-width="1" /></g></g>
        <path class="vh-logo-trail" d="M4 38Q8 58 28 59M43 6Q52 7 57 12" stroke="#d3ad66" stroke-width=".65" opacity=".6" />
        <g class="vh-logo-confetti" stroke="#edc375" stroke-width="1.4"><path d="M7 29 9 32M31 4 34 3M47 52 49 55M16 56 18 53" /></g>
        <g class="vh-logo-confetti vh-logo-phase" stroke="#e2d7f7" stroke-width="1.1"><path d="M5 48 8 47M40 3 41 6M57 40 60 42M33 59 35 56" /></g>
        <g class="vh-logo-twinkle" fill="#fff0bc"><path d="M49 49 50 52 53 53 50 54 49 57 48 54 45 53 48 52Z" /><circle cx="10" cy="42" r="1" /></g>
    </g>
    @endif
    @if($edition === null || $edition === 'valentines')
    <g data-edition="valentines">
        <path class="vh-logo-trail" d="M6 38C-3 19 5 6 23 5M42 58C57 56 66 42 59 28" stroke="#d6a0ad" stroke-width=".65" opacity=".55" />
        <g transform="translate(13 13)"><g class="vh-logo-heart"><path d="M0 9C-3 7-11 1-11-5C-11-12-3-14 0-7C3-14 11-12 11-5C11 1 3 7 0 9Z" fill="#d77e99" stroke="#ffe1e9" stroke-width=".6" /><path d="M-7-5C-7-8-4-9-3-7" stroke="#f6bfce" stroke-width="1.4" /><path d="M1 6C5 3 8-1 8-5" stroke="#b55575" stroke-width=".7" /></g></g>
        <g transform="translate(53 45)"><g class="vh-logo-heart vh-logo-phase"><path d="M0 6C-15-3-5-15 0-7C5-15 15-3 0 6Z" fill="#ecb4c8" stroke="#fff1e6" stroke-width=".6" /><path d="M-5-4Q-5-7-3-6" stroke="#fff0f3" stroke-width="1" /></g></g>
        <path class="vh-logo-ribbon" d="M6 52Q12 46 17 53T29 55M45 8Q50 3 58 7" stroke="#dba6ba" stroke-width="1.1" />
        <g class="vh-logo-mote" fill="#eab4c7"><circle cx="5" cy="30" r="1" /><circle cx="36" cy="4" r=".9" /><circle cx="37" cy="58" r="1" /></g>
        <path class="vh-logo-twinkle" d="M54 19 55 22 58 23 55 24 54 27 53 24 50 23 53 22Z" fill="#f2d5b3" />
    </g>
    @endif
    @if($edition === null || $edition === 'stPatrickDay')
    <g data-edition="stPatrickDay">
        <path class="vh-logo-trail" d="M5 36C1 17 10 6 29 4M38 60C53 59 63 46 60 32" stroke="#9ebd80" stroke-width=".65" opacity=".55" />
        <g transform="translate(48 12)"><g class="vh-logo-sway"><path d="M0 1C-17 7-18-10-7-8C-13-22 9-23 5-9C18-15 22 4 7 3Z" fill="#59a580" stroke="#bfe1b3" stroke-width=".7" /><path d="M2 1 0 12" stroke="#438362" stroke-width="1.7" /><path d="M1 0-8-4M2-2-2-11M4 0 11-5" stroke="#90c7a2" stroke-width=".8" /></g></g>
        <g transform="translate(12 49)"><g class="vh-logo-coin"><circle r="7" fill="#c49a50" stroke="#f5d79b" stroke-width="1" /><circle r="5" stroke="#f1cc83" stroke-width=".7" /><path d="M0-3C-4-5-5 1-1 1C-5 5 3 6 2 2C7 2 5-5 2-2ZM1 1 0 4" stroke="#ffe9b4" stroke-width=".8" /></g></g>
        <g class="vh-logo-twinkle" fill="#e9cd84"><path d="M10 15 11 18 14 19 11 20 10 23 9 20 6 19 9 18Z" /><path d="M51 44 52 46 54 47 52 48 51 50 50 48 48 47 50 46Z" /></g>
        <g class="vh-logo-mote vh-logo-phase" fill="#b7d59c"><circle cx="5" cy="34" r=".8" /><circle cx="34" cy="4" r=".8" /><circle cx="42" cy="57" r="1" /></g>
    </g>
    @endif
    @if($edition === null || $edition === 'easter')
    <g data-edition="easter">
        <path class="vh-logo-trail" d="M6 36C0 18 10 5 28 5M39 59Q56 58 60 35" stroke="#b8a5d2" stroke-width=".65" opacity=".55" />
        <g transform="translate(52 46)"><g class="vh-logo-egg"><path d="M-8 0C-8-9-3-17 0-17S8-9 8 0C8 12-8 12-8 0Z" fill="#b8a3d4" stroke="#f4e2f9" stroke-width=".7" /><path d="M-6-6H6M-7 2 7 2" stroke="#f4d6a6" stroke-width="1.5" /><path d="M-7-2-3 0 0-2 3 0 7-2" stroke="#eaf1d2" stroke-width=".9" /><path d="M-4-10Q-2-14 0-13" stroke="#e7d8f2" stroke-width="1.2" /><path d="M-4 6H4" stroke="#997fb8" /></g></g>
        <g transform="translate(13 12)"><g class="vh-logo-sway"><path d="M-3 5C-10-4-8-12-5-12C-2-12 1-3 0 3M3 4C1-4 5-13 8-11C11-9 8-1 5 5" fill="#eee1d7" stroke="#fff8ec" stroke-width=".7" /><path d="M-5-9-2 0M7-8 4 0" stroke="#d9b5c6" stroke-width="1.4" /><path d="M-5 6Q0 0 6 6" stroke="#e2cfbe" stroke-width="1.5" /></g></g>
        <g transform="translate(10 48)"><path d="M0 4C-10 3-5-7 0-3C5-7 10 3 0 4Z" fill="#e9b3c5" /><circle cy="1" r="1.4" fill="#f5d98b" /><path d="M0 5 0 10M0 8Q5 3 7 6Q5 10 0 8" stroke="#a7c9a0" /></g>
        <g class="vh-logo-mote" fill="#e3cdb0"><circle cx="34" cy="3" r="1" /><circle cx="57" cy="18" r=".9" /><circle cx="29" cy="58" r=".9" /></g>
    </g>
    @endif
    @if($edition === null || $edition === 'july4')
    <g data-edition="july4">
        <g transform="translate(11 12)"><g class="vh-logo-firework"><circle class="vh-logo-firework-ring" r="9" stroke="#b7c9e8" stroke-width=".6" opacity=".5" /><path class="vh-logo-firework-rays" d="M0-11V-4M-11 0H-4M4 0H11M0 4V11" stroke="#da8681" stroke-width="1.2" /><path class="vh-logo-firework-rays" d="M-8-8-3-3M3 3 8 8M8-8 3-3M-3 3-8 8" stroke="#dceaff" stroke-width="1.1" /><circle r="1.4" fill="#fff1d2" /></g></g>
        <g transform="translate(53 43)"><g class="vh-logo-firework vh-logo-phase"><circle class="vh-logo-firework-ring" r="9" stroke="#c9d9f4" stroke-width=".6" opacity=".5" /><path class="vh-logo-firework-rays" d="M0-10V-4M0 4V10M-10 0H-4M4 0H10" stroke="#9fbde8" stroke-width="1.2" /><path class="vh-logo-firework-rays" d="M-7-7-3-3M3 3 7 7M7-7 3-3M-3 3-7 7" stroke="#e7a39b" stroke-width="1.1" /></g></g>
        <path class="vh-logo-trail" d="M5 36Q7 56 28 59M38 5Q51 5 58 19" stroke="#9aafd4" stroke-width=".65" opacity=".5" />
        <g class="vh-logo-confetti" stroke="#e3aa9e" stroke-width="1.1"><path d="M7 45 10 47M30 4 32 2M37 58 40 56" /></g>
        <g class="vh-logo-twinkle" fill="#dbe7f7"><path d="M49 9 50 12 53 13 50 14 49 17 48 14 45 13 48 12Z" /><path d="M15 52 16 54 18 55 16 56 15 58 14 56 12 55 14 54Z" /></g>
    </g>
    @endif
    @if($edition === null || $edition === 'halloween')
    <g data-edition="halloween">
        <g transform="translate(32 14)"><g class="vh-logo-bat"><path class="vh-logo-wing-left" d="M-1 1C-8-8-19-14-29-7L-24 1Q-20-4-15 2Q-11-1-7 5Z" fill="#9e82b8" stroke="#d1bde0" stroke-width=".5" /><path class="vh-logo-wing-right" d="M1 1C8-8 19-14 29-7L24 1Q20-4 15 2Q11-1 7 5Z" fill="#9e82b8" stroke="#d1bde0" stroke-width=".5" /><path d="M-3-3-2-7 0-4 2-7 3-3 2 4H-2Z" fill="#705985" /><path d="M-24-7Q-16-5-7 3M24-7Q16-5 7 3" stroke="#745b91" stroke-width=".6" /></g></g>
        <g transform="translate(53 43)"><g class="vh-logo-ghost"><path d="M-7 9V-6C-7-17 8-17 8-6V9L4 6 1 9-3 6Z" fill="#e9e1ef" stroke="#fff8ed" stroke-width=".7" /><path d="M-4-6Q-3-10 0-10" stroke="#fff" stroke-width="1" /><ellipse cx="-2" cy="-4" rx="1.2" ry="1.8" fill="#55405f" /><ellipse cx="4" cy="-4" rx="1.2" ry="1.8" fill="#55405f" /><ellipse cx="1" cy="2" rx="1.3" ry="1.8" fill="#9e87a7" /></g></g>
        <g transform="translate(11 49)"><path d="M-5-3H5V8Q0 11-5 8Z" fill="#d4b78e" stroke="#f3dbb8" stroke-width=".6" /><path d="M-5-3Q-3 0-1-2Q1 2 3-2Q4-1 5-3" stroke="#f3dbb8" /><path class="vh-logo-flame" d="M0-4C-6-7-1-10 0-14C5-9 4-6 0-4Z" fill="#edb86a" /><path d="M0-4V-6" stroke="#675449" stroke-width=".7" /></g>
        <path class="vh-logo-mist" d="M20 57Q28 53 35 57T49 56M5 31Q8 28 12 30" stroke="#ad98c5" stroke-width=".75" opacity=".55" />
        <g class="vh-logo-mote" fill="#c4adc9"><circle cx="5" cy="22" r=".8" /><circle cx="59" cy="24" r=".8" /><circle cx="37" cy="4" r=".7" /></g>
    </g>
    @endif
    @if($edition === null || $edition === 'thanksgiving')
    <g data-edition="thanksgiving">
        <path class="vh-logo-trail" d="M6 35C1 17 11 5 29 4M35 60Q49 60 59 49" stroke="#c6a06e" stroke-width=".65" opacity=".55" />
        <g transform="translate(49 48)"><ellipse cy="8" rx="12" ry="2.5" fill="#99765b" opacity=".25" /><path d="M-10 0-7 7Q0 10 7 7L10 0Z" fill="#b78459" stroke="#edcca2" stroke-width=".6" /><ellipse rx="10" ry="4" fill="#d8a36a" stroke="#f1d9b5" stroke-width="1" /><path d="M-7-1 5 3M-4-3 8 1M-7 2 4-3M-2 3 8-1" stroke="#f1d0a0" stroke-width="1" /><path class="vh-logo-steam" d="M-4-7C-9-11 0-13-4-18" stroke="#d4baa0" stroke-width=".8" /><path class="vh-logo-steam vh-logo-phase" d="M3-6C8-11-1-12 3-17" stroke="#efd5b1" stroke-width=".8" /></g>
        <g transform="translate(12 12)"><g class="vh-logo-sway"><path d="M0 8C-10 3-9-7 0-9C8-5 9 4 0 8Z" fill="#c98b50" stroke="#eac18c" stroke-width=".6" /><path d="M0 11V-6M0 3-5-1M0 0 4-3" stroke="#946040" stroke-width=".8" /></g></g>
        <g transform="translate(51 10)"><g class="vh-logo-sway vh-logo-phase" stroke="#d3b777" stroke-width=".8"><path d="M-3 11 3-9" /><path d="M-1 4C-8 2-7-3 1-1C7-6 9-1-1 4ZM1-3C-5-5-4-9 3-7C8-11 10-6 1-3Z" fill="#dbc18b" /></g></g>
        <g class="vh-logo-mote" fill="#e3b577"><circle cx="5" cy="45" r=".9" /><circle cx="31" cy="3" r=".8" /><circle cx="20" cy="57" r="1" /></g>
    </g>
    @endif
    @if($edition === null || $edition === 'christmas')
    <g data-edition="christmas">
        <g class="vh-logo-hat"><path d="M23 10C27-2 37-5 47 0Q53 3 54 8L48 11Q42 1 40 9Z" fill="#b95d60" stroke="#ebaaa0" stroke-width=".7" /><path d="M28 6Q32 0 38-1" stroke="#d78a82" stroke-width="1.2" /><path d="M21 10Q34 6 49 11L48 17Q34 12 21 16Z" fill="#f4ead8" stroke="#fffaf0" stroke-width=".8" /><path d="M24 12 26 14M30 11 32 13M38 12 40 14M45 13 46 15" stroke="#d9cbbb" stroke-width=".65" /><circle cx="54" cy="8" r="4" fill="#f4ead8" stroke="#fffaf0" stroke-width=".7" /><circle cx="53" cy="7" r="1.6" fill="#fffaf0" /></g>
        <path d="M7 47Q31 61 57 47" stroke="#5f8b70" stroke-width="1.1" />
        <g stroke="#88aa83" stroke-width=".8"><path d="M9 49 10 46M15 52 14 48M22 55 23 50M31 56 32 51M40 55 39 50M48 52 49 48M54 49 54 45" /></g>
        <g class="vh-logo-bulb" transform="translate(13 51)"><path d="M0-2V0" stroke="#b8ba92" /><ellipse cy="2" rx="1.7" ry="2.4" fill="#e3b96c" stroke="#ffe6af" stroke-width=".5" /></g>
        <g class="vh-logo-bulb vh-logo-phase" transform="translate(25 55)"><path d="M0-2V0" stroke="#b8ba92" /><ellipse cy="2" rx="1.7" ry="2.4" fill="#cf7375" stroke="#ffc6bc" stroke-width=".5" /></g>
        <g class="vh-logo-bulb vh-logo-phase-late" transform="translate(39 55)"><path d="M0-2V0" stroke="#b8ba92" /><ellipse cy="2" rx="1.7" ry="2.4" fill="#8db7a0" stroke="#cee8c5" stroke-width=".5" /></g>
        <g class="vh-logo-bulb" transform="translate(51 51)"><path d="M0-2V0" stroke="#b8ba92" /><ellipse cy="2" rx="1.7" ry="2.4" fill="#e3b96c" stroke="#ffe6af" stroke-width=".5" /></g>
        <g transform="translate(7 30)"><g class="vh-logo-crystal" stroke="#e8efe4" stroke-width=".65"><path d="M0-5V5M-4-2 4 2M-4 2 4-2M-1-4 0-2 1-4M-1 4 0 2 1 4" /></g></g>
        <g class="vh-logo-snow" fill="#f8ecdb"><circle cx="5" cy="16" r=".9" /><circle cx="58" cy="26" r="1.2" /><circle cx="35" cy="2" r=".8" /></g>
        <g class="vh-logo-snow vh-logo-phase" fill="#fff6e6"><circle cx="16" cy="41" r=".8" /><circle cx="57" cy="40" r=".8" /></g>
    </g>
    @endif
</svg>
