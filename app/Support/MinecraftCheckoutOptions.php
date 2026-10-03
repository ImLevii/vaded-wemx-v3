<?php

namespace App\Support;

use App\Models\PackageConfigOption;
use App\Models\PackagePrice;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MinecraftCheckoutOptions
{
    /** @return list<array<string, mixed>> */
    public static function choices(PackageConfigOption $option): array
    {
        return array_values(array_filter($option->data['options'] ?? [], fn (array $choice): bool => ($choice['available'] ?? true) !== false));
    }

    /** @return array<string, mixed>|null */
    public static function recommendation(PackageConfigOption $option): ?array
    {
        $choices = collect(self::choices($option));

        return $choices->firstWhere('recommended', true)
            ?? $choices->first(fn (array $choice): bool => Str::lower($choice['name'] ?? '') === 'paper' && (float) ($choice['daily_price'] ?? 0) <= 0);
    }

    public static function kind(PackageConfigOption $option): string
    {
        if (! in_array($option->type, ['select', 'radio'], true)) {
            return 'service';
        }

        $name = $option->key.' '.$option->label;

        if (preg_match('/location|region|datacent(?:er|re)/i', $name)) {
            return 'locations';
        }

        if (preg_match('/server[\s_.-]*(?:version|type|software|jar)|(?:^|[\s_.-])(?:egg|jar|software|modpack)(?:[\s_.-]|$)/i', $name)) {
            return 'software';
        }

        return 'service';
    }

    /** @return array<string, list<array<string, mixed>>> */
    public static function softwareGroups(PackageConfigOption $option): array
    {
        return collect(self::choices($option))->groupBy(function (array $choice): string {
            return ($choice['group'] ?? '') === 'modpacks'
                || Str::contains(Str::lower($choice['name'] ?? ''), ['modpack', 'curseforge', 'ftb', 'technic', 'atlauncher'])
                    ? 'Modpacks' : 'Jars';
        })->map->all()->all();
    }

    public static function discount(PackagePrice $price, Collection $prices): ?int
    {
        $monthly = $prices->firstWhere('period_in_days', 30);
        $months = match ((int) $price->period_in_days) {
            30 => 1, 90 => 3, 180 => 6, 360, 365 => 12,
            default => null,
        };

        if (! $monthly || (float) $monthly->price <= 0 || $months === null) {
            return null;
        }

        return max(0, (int) round((1 - (float) $price->price / ((float) $monthly->price * $months)) * 100));
    }
}
