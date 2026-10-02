<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;

return static function (Application $app): void {
    $storagePath = sys_get_temp_dir().'/wemx';
    $app->useStoragePath($storagePath);
    $app->addAbsoluteCachePathPrefix($storagePath);

    $filesystem = new Filesystem;

    foreach (['app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs', 'bootstrap/cache'] as $directory) {
        $filesystem->ensureDirectoryExists($storagePath.'/'.$directory);
    }

    foreach (['CONFIG' => 'config', 'EVENTS' => 'events', 'PACKAGES' => 'packages', 'ROUTES' => 'routes-v7', 'SERVICES' => 'services'] as $type => $filename) {
        $variable = 'APP_'.$type.'_CACHE';
        $path = $storagePath.'/bootstrap/cache/'.$filename.'.php';

        $_ENV[$variable] = $path;
        $_SERVER[$variable] = $path;
        putenv($variable.'='.$path);
    }
};
