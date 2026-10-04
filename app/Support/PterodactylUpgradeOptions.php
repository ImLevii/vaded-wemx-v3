<?php

namespace App\Support;

class PterodactylUpgradeOptions
{
    private const JavaHeapPattern = <<<'REGEX'
/(?:(?:"(?:\\.|[^"\\])*")|(?:'(?:\\.|[^'\\])*'))(*SKIP)(*F)|(\s)(-Xm[sx])([0-9]+)([kKmMgG]?)(?=\s|$)/
REGEX;

    public static function normalizedStartup(string $startup): string
    {
        if (! self::isJavaStartup($startup)) {
            return $startup;
        }

        return preg_replace(self::JavaHeapPattern, '$1$2{heap}', $startup);
    }

    /** Resize plan-managed Java heap values while retaining custom arguments and heap overrides. */
    public static function resizedStartup(string $startup, string $sourceStartup, string $targetStartup, int $memory): string
    {
        if ($memory <= 0 || ! self::isJavaStartup($startup) || ! self::isJavaStartup($sourceStartup)) {
            return $startup;
        }
        $sourceHeaps = self::heapValues($sourceStartup);
        $targetHeaps = self::heapValues($targetStartup);

        return preg_replace_callback(self::JavaHeapPattern, function (array $matches) use ($sourceHeaps, $targetHeaps, $memory): string {
            $flag = $matches[2];
            if (! isset($sourceHeaps[$flag], $targetHeaps[$flag]) || $sourceHeaps[$flag] === $targetHeaps[$flag]
                || self::heapBytes($matches[3], $matches[4]) !== $sourceHeaps[$flag]) {
                return $matches[0];
            }

            return $matches[1].$flag.$memory.'M';
        }, $startup);
    }

    public static function allowsCpuPinning(?string $source, ?string $target): bool
    {
        $sourceRanges = self::cpuRanges($source);
        $targetRanges = self::cpuRanges($target);
        if ($sourceRanges === null || $targetRanges === null) {
            return false;
        }
        if ($targetRanges === []) {
            return true;
        }
        if ($sourceRanges === []) {
            return false;
        }
        foreach ($sourceRanges as [$start, $end]) {
            $nextCore = $start;
            foreach ($targetRanges as [$targetStart, $targetEnd]) {
                if ($targetStart > $nextCore) {
                    break;
                }
                if ($targetEnd >= $nextCore) {
                    $nextCore = $targetEnd + 1;
                }
                if ($nextCore > $end) {
                    break;
                }
            }
            if ($nextCore <= $end) {
                return false;
            }
        }

        return true;
    }

    private static function isJavaStartup(string $startup): bool
    {
        return preg_match('/^(?:\S*\/)?java(?:\.exe)?\s/', ltrim($startup)) === 1;
    }

    /** @return array<string, int> */
    private static function heapValues(string $startup): array
    {
        preg_match_all(self::JavaHeapPattern, $startup, $matches, PREG_SET_ORDER);
        $values = [];
        foreach ($matches as $match) {
            $values[$match[2]] = self::heapBytes($match[3], $match[4]);
        }

        return $values;
    }

    private static function heapBytes(string $value, string $unit): int
    {
        return (int) $value * match (strtolower($unit)) {
            'g' => 1024 * 1024 * 1024,
            'm' => 1024 * 1024,
            'k' => 1024,
            default => 1,
        };
    }

    /** @return list<array{int, int}>|null */
    private static function cpuRanges(?string $pinning): ?array
    {
        if (trim($pinning ?? '') === '') {
            return [];
        }
        $ranges = [];
        foreach (explode(',', $pinning) as $range) {
            if (! preg_match('/^([0-9]+)(?:-([0-9]+))?$/', trim($range), $matches)) {
                return null;
            }
            $start = (int) $matches[1];
            $end = (int) ($matches[2] ?? $matches[1]);
            if ($end < $start) {
                return null;
            }
            $ranges[] = [$start, $end];
        }
        usort($ranges, fn (array $left, array $right): int => $left[0] <=> $right[0]);

        return $ranges;
    }
}
