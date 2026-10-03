<?php

namespace App\Support;

use App\Models\Package;
use App\Models\PackageConfigOption;

class MinecraftOrderOptions
{
    public static function memory(Package $package): ?float
    {
        $memory = data_get($package->data, 'memory_limit');

        if (is_numeric($memory) && (float) $memory > 0) {
            return (float) $memory;
        }

        foreach ([$package->name, ...$package->features->pluck('description')->all()] as $description) {
            if (preg_match('/\b(\d+(?:\.\d+)?)\s*GB\b/i', $description, $matches)
                && ($description === $package->name || preg_match('/memory|ram/i', $description))) {
                return (float) $matches[1];
            }
        }

        return null;
    }

    /**
     * @return array<int, array{additional: int, value: int, dailyPrice: float}>
     */
    public static function threads(Package $package): array
    {
        $option = $package->configOptions->firstWhere('key', 'cpu_limit');
        $included = (int) ($option?->default_value ?? data_get($package->data, 'cpu_limit', 100));
        $choices = [['additional' => 0, 'value' => $included, 'dailyPrice' => 0.0]];

        if (! $option || $included < 100 || $included % 100 !== 0) {
            return $choices;
        }

        if (in_array($option->type, ['select', 'radio'])) {
            $choices = collect($option->data['options'] ?? [])
                ->filter(fn (array $choice): bool => is_numeric($choice['value'] ?? null)
                    && (int) $choice['value'] >= $included
                    && ((int) $choice['value'] - $included) % 100 === 0)
                ->sortBy('value')
                ->map(fn (array $choice): array => [
                    'additional' => (int) (((int) $choice['value'] - $included) / 100),
                    'value' => (int) $choice['value'],
                    'dailyPrice' => max(0, (float) ($choice['daily_price'] ?? 0)),
                ])->values()->all();

            return $choices ?: [['additional' => 0, 'value' => $included, 'dailyPrice' => 0.0]];
        }

        if (! in_array($option->type, ['range', 'number'])) {
            return $choices;
        }

        $minimum = (int) ($option->data['min_value'] ?? $included);
        $maximum = (int) ($option->data['max_value'] ?? $included);
        $step = max(1, (int) ($option->data['step_value'] ?? 100));
        $choices = [];

        for ($additional = 0; $additional <= 5; $additional++) {
            $value = $included + $additional * 100;

            if ($value < $minimum || $value > $maximum || ($value - $minimum) % $step !== 0) {
                continue;
            }

            $choices[] = [
                'additional' => $additional,
                'value' => $value,
                'dailyPrice' => self::dailyPrice($option, $value),
            ];
        }

        return $choices ?: [['additional' => 0, 'value' => $included, 'dailyPrice' => 0.0]];
    }

    private static function dailyPrice(PackageConfigOption $option, int $value): float
    {
        return max(0, $value - (int) ($option->data['free_value'] ?? 0))
            * max(0, (float) ($option->data['daily_price'] ?? 0));
    }
}
