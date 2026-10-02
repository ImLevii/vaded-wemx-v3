<?php

return [
    'capabilities' => [
        ['icon' => 'server', 'title' => 'Connected services', 'description' => 'One hosting workspace'],
        ['icon' => 'receipt', 'title' => 'Clear billing', 'description' => 'Review every charge'],
        ['icon' => 'sliders', 'title' => 'Your configuration', 'description' => 'Choose available resources'],
        ['icon' => 'users', 'title' => 'Team access', 'description' => 'Invite your community'],
    ],

    /* Published infrastructure details and reviews must be verified before adding them here. */
    'hardware' => [],
    'locations' => [],
    'reviews' => [],
    'resources' => array_filter([
        'Network status' => env('HOSTING_STATUS_URL'),
        'Knowledge base' => env('HOSTING_KNOWLEDGE_BASE_URL'),
        'Contact support' => env('HOSTING_SUPPORT_URL'),
        'Discord' => env('HOSTING_DISCORD_URL'),
    ]),
    'legal' => array_filter([
        'Terms of service' => env('HOSTING_TERMS_URL'),
        'Privacy policy' => env('HOSTING_PRIVACY_URL'),
    ]),
];
