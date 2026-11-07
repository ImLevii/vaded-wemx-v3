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
    /*
     * One-click VPS operating systems shown on the VPS page (OS picker + "VPS Operating Systems").
     * `match` words are used to find the same OS in a package's checkout OS option so the
     * picker can pre-select it; versions are listed oldest to newest (the newest is the default).
     */
    'vps_operating_systems' => [
        'rocky' => ['name' => 'Rocky Linux', 'mark' => 'R', 'versions' => ['8', '9'], 'match' => ['rocky']],
        'alma' => ['name' => 'Alma Linux', 'mark' => 'A', 'versions' => ['8', '9'], 'match' => ['alma']],
        'windows' => ['name' => 'Windows Server', 'mark' => 'W', 'versions' => ['2019', '2022'], 'match' => ['windows'], 'badge' => 'BYOL'],
        'debian' => ['name' => 'Debian', 'mark' => 'D', 'versions' => ['10', '11'], 'match' => ['debian']],
        'ubuntu' => ['name' => 'Ubuntu', 'mark' => 'U', 'versions' => ['18.04', '20.04', '22.04'], 'match' => ['ubuntu']],
        'fedora' => ['name' => 'Fedora', 'mark' => 'F', 'versions' => ['36', '37', '38'], 'match' => ['fedora']],
        'pterodactyl' => ['name' => 'Pterodactyl Preinstall', 'short' => 'Pterodactyl', 'mark' => 'P', 'versions' => ['Rocky Linux base'], 'match' => ['pterodactyl'], 'note' => 'Panel Preinstalls use Rocky Linux as the Base OS.'],
    ],
    'vps_default_os' => ['ubuntu', '22.04'],

    'legal' => array_filter([
        'Terms of service' => env('HOSTING_TERMS_URL'),
        'Privacy policy' => env('HOSTING_PRIVACY_URL'),
    ]),
];
