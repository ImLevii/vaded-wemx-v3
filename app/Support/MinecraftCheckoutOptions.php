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

        if (preg_match('/(?:minecraft|game|mc)[\s_.-]*version/i', $name)) {
            return 'version';
        }

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

    /**
     * Wiring for a server software value from config('hosting.minecraft_software'), or null when the value is not
     * mapped (then the package's own egg is used and every version is offered).
     *
     * @return array{egg_id: int, version_variable: ?string, environment: array<string, string>, java?: string}|null
     */
    public static function software(?string $value): ?array
    {
        $software = config('hosting.minecraft_software', []);

        return $value !== null && is_array($software[$value] ?? null) ? $software[$value] : null;
    }

    /** Whether the customer picks a Minecraft version for this software (modpacks pick theirs in the panel). */
    public static function choosesVersion(?string $software): bool
    {
        $config = self::software($software);

        return $config === null || ($config['version_variable'] ?? null) !== null;
    }

    /** The Java image label (e.g. "Java 21") a Minecraft version needs, or null when the version is not mapped. */
    public static function javaFor(?string $version): ?string
    {
        $versions = config('hosting.minecraft_versions', []);

        return $version !== null && is_string($versions[$version]['java'] ?? null) ? $versions[$version]['java'] : null;
    }

    /**
     * Version choices the selected software can install. Versions or software that are not mapped in
     * config('hosting.minecraft_versions') are not filtered.
     *
     * @return list<array<string, mixed>>
     */
    public static function versionChoices(PackageConfigOption $option, ?string $software): array
    {
        if (! self::choosesVersion($software)) {
            return [];
        }
        $versions = config('hosting.minecraft_versions', []);
        $mapped = self::software($software) !== null;

        return array_values(array_filter(self::choices($option), function (array $choice) use ($versions, $software, $mapped): bool {
            $supported = $versions[(string) ($choice['value'] ?? '')]['software'] ?? null;

            return ! $mapped || ! is_array($supported) || in_array($software, $supported, true);
        }));
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
