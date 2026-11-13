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
     * `logo` is a path under public/. `match` words are used to find the same OS in a package's checkout OS option so the
     * picker can pre-select it; versions are listed oldest to newest (the newest is the default).
     */
    // The VPS egg (panel nest 8, "Vaded VPS", ysdragon/Pterodactyl-VPS-Egg) is proot-based and
    // installs the distro the customer picks from a console menu after purchase. This list is shown
    // on the VPS page for information only; it does not drive checkout.
    'vps_operating_systems' => [
        'ubuntu' => ['name' => 'Ubuntu', 'logo' => 'assets/common/img/os/ubuntu.svg', 'mark' => 'U', 'versions' => ['18.04', '20.04', '22.04', '24.04'], 'match' => ['ubuntu']],
        'debian' => ['name' => 'Debian', 'logo' => 'assets/common/img/os/debian-color.svg', 'mark' => 'D', 'versions' => ['10', '11', '12', '13'], 'match' => ['debian']],
        'rocky' => ['name' => 'Rocky Linux', 'logo' => 'assets/common/img/os/rockylinux.svg', 'mark' => 'R', 'versions' => ['8', '9'], 'match' => ['rocky']],
        'alma' => ['name' => 'AlmaLinux', 'logo' => 'assets/common/img/os/almalinux-color.svg', 'mark' => 'A', 'versions' => ['8', '9'], 'match' => ['alma']],
        'centos' => ['name' => 'CentOS', 'logo' => 'assets/common/img/os/centos.svg', 'mark' => 'C', 'versions' => ['Stream 9', 'Stream 10'], 'match' => ['centos']],
        'fedora' => ['name' => 'Fedora', 'logo' => 'assets/common/img/os/fedora.svg', 'mark' => 'F', 'versions' => ['40', '41'], 'match' => ['fedora']],
        'alpine' => ['name' => 'Alpine', 'mark' => 'A', 'versions' => ['3.19', '3.20', 'Edge'], 'match' => ['alpine']],
        'arch' => ['name' => 'Arch Linux', 'mark' => 'A', 'versions' => ['Rolling'], 'match' => ['arch']],
    ],
    'vps_default_os' => ['ubuntu', '22.04'],

    'legal' => array_filter([
        'Terms of service' => env('HOSTING_TERMS_URL'),
        'Privacy policy' => env('HOSTING_PRIVACY_URL'),
    ]),
];
