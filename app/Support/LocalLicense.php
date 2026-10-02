<?php

namespace App\Support;

final class LocalLicense
{
    public static function isBypassed(): bool
    {
        return app()->environment('local') && config('app.license_bypass', false) === true;
    }
}
