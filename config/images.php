<?php

return [
    'permissions' => [
        'admin.settings.index',
        'admin.categories.create',
        'admin.categories.edit',
        'admin.packages.create',
        'admin.packages.edit',
        'admin.gateways.configs.create',
        'admin.gateways.configs.edit',
    ],
    'upload_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    'gallery_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'],
    'max_files' => 12,
    'max_size_kb' => 6144,
];
