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
    'locations' => [
        ['city' => 'Miami, Florida', 'region' => 'United States · Southeast', 'status' => 'Available on all plans'],
    ],
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

    /*
     * Minecraft checkout -> Pterodactyl. The package's `server_software` option values map to a panel egg (nest 1)
     * here, and `minecraft_version` values to the Java image each release needs and the software that can install it.
     * The option rows (names, descriptions, prices) live in the store DB; this is the wiring that must stay in code.
     * Verified against the egg install sources on 2026-11-21 (fill.papermc.io v3, Mojang manifest, Forge promotions).
     */
    'minecraft_software' => [
        // version_variable: the egg variable that receives the chosen Minecraft version (null = no version choice).
        'paper' => ['egg_id' => 5, 'version_variable' => 'MINECRAFT_VERSION', 'environment' => ['BUILD_NUMBER' => 'latest']],
        'vanilla' => ['egg_id' => 4, 'version_variable' => 'VANILLA_VERSION', 'environment' => []],
        'forge' => ['egg_id' => 1, 'version_variable' => 'MC_VERSION', 'environment' => ['BUILD_TYPE' => 'recommended']],
        'folia' => ['egg_id' => 46, 'version_variable' => 'MINECRAFT_VERSION', 'environment' => ['BUILD_NUMBER' => 'latest']],
        // Modpacks are picked in the panel's Modpacks tab (Modpack Installer extension), which switches the server to the
        // installer egg itself. The server starts as Forge on the latest release until a pack is installed.
        'modpack' => ['egg_id' => 1, 'version_variable' => null, 'environment' => ['MC_VERSION' => 'latest', 'BUILD_TYPE' => 'recommended'], 'java' => 'Java 25'],
    ],
    'minecraft_versions' => [
        'latest' => ['java' => 'Java 25', 'software' => ['paper', 'vanilla', 'forge', 'folia']],
        '26.3' => ['java' => 'Java 25', 'software' => ['paper', 'vanilla', 'forge', 'folia']],
        '1.21.11' => ['java' => 'Java 21', 'software' => ['paper', 'vanilla', 'forge', 'folia']],
        '1.21.1' => ['java' => 'Java 21', 'software' => ['paper', 'vanilla', 'forge']],
        '1.20.4' => ['java' => 'Java 17', 'software' => ['paper', 'vanilla', 'forge', 'folia']],
        '1.20.1' => ['java' => 'Java 17', 'software' => ['paper', 'vanilla', 'forge', 'folia']],
        '1.19.2' => ['java' => 'Java 17', 'software' => ['paper', 'vanilla', 'forge']],
        '1.18.2' => ['java' => 'Java 17', 'software' => ['paper', 'vanilla', 'forge']],
        '1.16.5' => ['java' => 'Java 8', 'software' => ['paper', 'vanilla', 'forge']],
        '1.12.2' => ['java' => 'Java 8', 'software' => ['paper', 'vanilla', 'forge']],
        '1.8.8' => ['java' => 'Java 8', 'software' => ['paper', 'vanilla']],
    ],

    'legal' => array_filter([
        'Terms of service' => env('HOSTING_TERMS_URL'),
        'Privacy policy' => env('HOSTING_PRIVACY_URL'),
    ]),
];
