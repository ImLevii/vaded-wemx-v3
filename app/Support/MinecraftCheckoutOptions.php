<?php

namespace App\Support;

use App\Models\Package;
use App\Models\PackageConfigOption;
use App\Models\PackagePrice;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MinecraftCheckoutOptions
{
    /**
     * Choices a customer can pick: available ones that fit the plan (a choice's `min_memory` is in GB, e.g. big modpacks).
     *
     * @return list<array<string, mixed>>
     */
    public static function choices(PackageConfigOption $option): array
    {
        $memory = self::planMemory($option);

        return array_values(array_filter($option->data['options'] ?? [], fn (array $choice): bool => ($choice['available'] ?? true) !== false
            && ($memory === null || ! is_numeric($choice['min_memory'] ?? null) || (float) $choice['min_memory'] <= $memory)));
    }

    /** How many otherwise available choices are hidden because the plan has too little memory. */
    public static function hiddenForMemory(PackageConfigOption $option): int
    {
        return count(self::hiddenChoicesForMemory($option));
    }

    /** @return list<array<string, mixed>> */
    public static function hiddenChoicesForMemory(PackageConfigOption $option): array
    {
        $visible = array_map(fn (array $choice): string => (string) ($choice['value'] ?? ''), self::choices($option));

        return array_values(array_filter($option->data['options'] ?? [], fn ($choice): bool => is_array($choice)
            && ($choice['available'] ?? true) !== false && ! in_array((string) ($choice['value'] ?? ''), $visible, true)));
    }

    private static function planMemory(PackageConfigOption $option): ?float
    {
        $package = $option->package;

        return $package ? MinecraftOrderOptions::memory($package) : null;
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

    /**
     * Server Version choices grouped into Jars and Modpacks. Choices the plan has too little memory for (big modpacks)
     * still show, after the pickable ones and smallest first, as locked cards: `_locked` = true and `_upgrade` = the
     * smallest listed plan in the same category that offers the choice ({name, memory, url}), or null. The url opens
     * that plan with the choice already selected. Locked choices are never pickable here (see choices()).
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function softwareGroups(PackageConfigOption $option): array
    {
        $plans = null;
        $locked = collect(self::hiddenChoicesForMemory($option))
            ->sortBy(fn (array $choice): float => (float) $choice['min_memory'])
            ->map(function (array $choice) use ($option, &$plans): array {
                $plans ??= self::upgradePlans($option);

                return [...$choice, '_locked' => true, '_upgrade' => self::upgradeFor($plans, $option, $choice)];
            })->values()->all();

        return collect([...self::choices($option), ...$locked])->groupBy(function (array $choice): string {
            return ($choice['group'] ?? '') === 'modpacks'
                || Str::contains(Str::lower($choice['name'] ?? ''), ['modpack', 'curseforge', 'ftb', 'technic', 'atlauncher'])
                    ? 'Modpacks' : 'Jars';
        })->map->all()->all();
    }

    /**
     * Other plans a customer can order in the option's category, smallest memory first.
     *
     * @return Collection<int, array{package: Package, memory: float}>
     */
    private static function upgradePlans(PackageConfigOption $option): Collection
    {
        $package = $option->package;
        if (! $package) {
            return collect();
        }

        return Package::query()->with(['features', 'prices', 'configOptions'])
            ->where('category_id', $package->category_id)->whereKeyNot($package->getKey())
            ->visibleToUser(auth()->user(), false)->get()
            ->filter(fn (Package $plan): bool => $plan->prices->contains('is_active', true))
            ->each(fn (Package $plan) => $plan->configOptions->each->setRelation('package', $plan))
            ->map(fn (Package $plan): array => ['package' => $plan, 'memory' => MinecraftOrderOptions::memory($plan)])
            ->filter(fn (array $plan): bool => $plan['memory'] !== null)
            ->sortBy('memory')->values();
    }

    /**
     * The smallest plan with enough memory whose own option (same key) lets the customer pick this choice.
     *
     * @param  Collection<int, array{package: Package, memory: float}>  $plans
     * @param  array<string, mixed>  $choice
     * @return array{name: string, memory: float, url: string}|null
     */
    private static function upgradeFor(Collection $plans, PackageConfigOption $option, array $choice): ?array
    {
        $value = (string) ($choice['value'] ?? '');
        foreach ($plans as ['package' => $plan, 'memory' => $memory]) {
            $planOption = $memory >= (float) $choice['min_memory'] ? $plan->configOptions->firstWhere('key', $option->key) : null;
            if ($planOption && in_array($value, array_map('strval', array_column(self::choices($planOption), 'value')), true)) {
                return [
                    'name' => (string) $plan->name,
                    'memory' => $memory,
                    'url' => route('packages.view', ['package' => $plan->slug, 'config_options' => [$option->key => $value]]),
                ];
            }
        }

        return null;
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

    /**
     * The modpack behind a software choice (provider/id/version_id/minecraft/loader), or null when the choice is not a
     * modpack or its data is incomplete. Only providers the Modpack Installer egg accepts are allowed.
     *
     * @return array{provider: string, id: string, version_id: string, minecraft: ?string, loader: ?string}|null
     */
    public static function modpack(?PackageConfigOption $option, ?string $value): ?array
    {
        if ($option === null || $value === null) {
            return null;
        }
        $choice = collect($option->data['options'] ?? [])->first(fn ($choice): bool => is_array($choice) && (string) ($choice['value'] ?? '') === $value);
        $modpack = is_array($choice['modpack'] ?? null) ? $choice['modpack'] : null;
        if ($modpack === null || ! in_array($modpack['provider'] ?? null, config('hosting.minecraft_modpack_providers', []), true)) {
            return null;
        }
        foreach (['id', 'version_id'] as $key) {
            if (! is_scalar($modpack[$key] ?? null) || trim((string) $modpack[$key]) === '') {
                return null;
            }
        }

        return [
            'provider' => $modpack['provider'],
            'id' => trim((string) $modpack['id']),
            'version_id' => trim((string) $modpack['version_id']),
            'minecraft' => is_string($modpack['minecraft'] ?? null) ? $modpack['minecraft'] : null,
            'loader' => is_string($modpack['loader'] ?? null) ? $modpack['loader'] : null,
        ];
    }

    /** Whether the customer picks a Minecraft version for this software choice (a modpack brings its own). */
    public static function choosesVersion(?string $software, ?PackageConfigOption $softwareOption = null): bool
    {
        if (self::modpack($softwareOption, $software) !== null) {
            return false;
        }
        $config = self::software($software);

        return $config === null || ($config['version_variable'] ?? null) !== null;
    }

    /** Java a Minecraft release needs: 26.x → 25, 1.20.5+ → 21, 1.18–1.20.4 → 17, 1.17 → 16, older → 8. */
    public static function javaForMinecraft(?string $version): ?int
    {
        if ($version === null || ! preg_match('/^(\d+)\.(\d+)(?:\.(\d+))?/', $version, $m)) {
            return null;
        }
        [$major, $minor, $patch] = [(int) $m[1], (int) $m[2], (int) ($m[3] ?? 0)];
        if ($major >= 26) {
            return 25;
        }

        return match (true) {
            $minor > 20 || ($minor === 20 && $patch >= 5) => 21,
            $minor >= 18 => 17,
            $minor === 17 => 16,
            default => 8,
        };
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
    public static function versionChoices(PackageConfigOption $option, ?string $software, ?PackageConfigOption $softwareOption = null): array
    {
        if (! self::choosesVersion($software, $softwareOption)) {
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
