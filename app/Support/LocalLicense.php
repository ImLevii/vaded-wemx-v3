<?php

namespace App\Support;

final class LocalLicense
{
    public static function isBypassed(): bool
    {
        return config('app.license_bypass', false) === true
            && (app()->environment('local') || config('app.demo_mode', false) === true);
    }
}
