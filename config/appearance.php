<?php

return [
    'modes' => ['auto' => 'Day / night', 'system' => 'Device preference', 'light' => 'Light', 'dark' => 'Dark'],
    'accents' => ['vaded' => 'Vaded red', 'emerald' => 'Emerald', 'violet' => 'Violet', 'amber' => 'Amber'],
    'themes' => [
        'spring' => ['name' => 'Spring', 'kind' => 'season', 'window' => 'Mar 20 – Jun 20', 'description' => 'Drifting petals and a fresh green sprout.', 'effect' => 'petals'],
        'summer' => ['name' => 'Summer', 'kind' => 'season', 'window' => 'Jun 21 – Sep 21', 'description' => 'Warm sunlight and a slow golden glint.', 'effect' => 'glints'],
        'autumn' => ['name' => 'Autumn', 'kind' => 'season', 'window' => 'Sep 22 – Dec 20', 'description' => 'Copper leaves drifting around your logo.', 'effect' => 'leaves'],
        'winter' => ['name' => 'Winter', 'kind' => 'season', 'window' => 'Dec 21 – Mar 19', 'description' => 'Soft snow and a frosted crown.', 'effect' => 'snow'],
        'newYear' => ['name' => 'New Year’s', 'kind' => 'holiday', 'window' => 'Dec 31 – Jan 2', 'description' => 'Golden fireworks and falling confetti.', 'effect' => 'confetti'],
        'valentines' => ['name' => 'Valentine’s Day', 'kind' => 'holiday', 'window' => 'Feb 10 – 14', 'description' => 'Little floating hearts with a gentle pulse.', 'effect' => 'hearts'],
        'stPatrickDay' => ['name' => 'St. Patrick’s Day', 'kind' => 'holiday', 'window' => 'Mar 15 – 17', 'description' => 'An emerald shamrock and a touch of luck.', 'effect' => 'clovers'],
        'easter' => ['name' => 'Easter', 'kind' => 'holiday', 'window' => 'Thursday before Easter – Easter Sunday', 'description' => 'A pastel egg with a playful wobble.', 'effect' => 'petals'],
        'july4' => ['name' => 'July 4th', 'kind' => 'holiday', 'window' => 'Jul 1 – 4', 'description' => 'Red, white and blue sparklers.', 'effect' => 'sparkles'],
        'halloween' => ['name' => 'Halloween', 'kind' => 'holiday', 'window' => 'Oct 24 – 31', 'description' => 'A curious ghost and fluttering bat wings.', 'effect' => 'bats'],
        'thanksgiving' => ['name' => 'Thanksgiving', 'kind' => 'holiday', 'window' => 'Fourth Thursday in November + two days before', 'description' => 'A tiny pumpkin pie with rising steam.', 'effect' => 'leaves'],
        'christmas' => ['name' => 'Christmas', 'kind' => 'holiday', 'window' => 'Dec 1 – 26', 'description' => 'A Santa hat, glowing lights and snow.', 'effect' => 'festive'],
    ],
    // Page effects drawn by public/assets/common/js/vaded-effects.js, in the active edition's colours.
    // Each edition's 'effect' above is its default; admins can pick another one (or none) per edition.
    'effects' => [
        'snow' => ['name' => 'Snowfall', 'description' => 'Soft, layered snow with a few crystal flakes.'],
        'festive' => ['name' => 'Festive snow', 'description' => 'Snowfall with twinkling holiday lights.'],
        'confetti' => ['name' => 'Confetti', 'description' => 'Tumbling confetti in the edition colours.'],
        'hearts' => ['name' => 'Floating hearts', 'description' => 'Little hearts rising with a gentle pulse.'],
        'petals' => ['name' => 'Blossom petals', 'description' => 'Petals carried across the page on a breeze.'],
        'leaves' => ['name' => 'Falling leaves', 'description' => 'Maple and oak leaves twirling down.'],
        'clovers' => ['name' => 'Lucky clovers', 'description' => 'Drifting shamrocks and golden glints.'],
        'sparkles' => ['name' => 'Star sparkles', 'description' => 'Twinkling stars that slowly drift down.'],
        'glints' => ['name' => 'Sun glints', 'description' => 'Warm motes of light floating upward.'],
        'bats' => ['name' => 'Bats & embers', 'description' => 'Fluttering bats and rising candle embers.'],
    ],
    'effect_intensities' => ['light' => 'Light', 'normal' => 'Normal', 'heavy' => 'Heavy'],
    'effect_speeds' => ['calm' => 'Calm', 'normal' => 'Normal', 'lively' => 'Lively'],
    'effect_strengths' => ['subtle' => 'Subtle', 'normal' => 'Normal', 'bold' => 'Bold'],
    'effect_areas' => ['client' => 'Storefront & client area', 'admin' => 'Administration only', 'both' => 'Everywhere'],
];
