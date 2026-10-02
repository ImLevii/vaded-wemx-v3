<?php

namespace App\Support;

use App\Models\PackageConfigOption;
use Illuminate\Support\Str;

class OperatingSystemOptions
{
    public static function recognizes(PackageConfigOption $option): bool
    {
        return in_array($option->type, ['select', 'radio'], true)
            && preg_match('/operating[\s_-]*system|(?:^|[\s_.-])os(?:$|[\s_.-])|(?:os|vm)[\s_.-]?(?:template|image)|distribution|distro/i', $option->key.' '.$option->label) === 1;
    }

    /**
     * @param  list<array{value: string|int, name?: string, description?: string, icon_url?: string, daily_price?: int|float|string}>  $options
     * @return array<string, array{name: string, icon: string|null, options: list<array<string, mixed>>}>
     */
    public static function group(array $options): array
    {
        $families = [
            'debian' => ['Debian', ['debian']],
            'centos' => ['CentOS', ['centos']],
            'rockylinux' => ['Rocky Linux', ['rocky']],
            'almalinux' => ['AlmaLinux', ['alma']],
            'ubuntu' => ['Ubuntu', ['ubuntu']],
            'fedora' => ['Fedora', ['fedora']],
        ];
        $groups = [];

        foreach ($options as $option) {
            $family = 'other';
            $name = Str::lower(($option['name'] ?? '').' '.($option['value'] ?? ''));

            foreach ($families as $key => [$label, $needles]) {
                if (Str::contains($name, $needles)) {
                    $family = $key;
                    break;
                }
            }

            $groups[$family] ??= [
                'name' => $families[$family][0] ?? 'Other',
                'icon' => isset($families[$family]) ? 'assets/common/img/os/'.$family.'.svg' : null,
                'options' => [],
            ];
            $groups[$family]['options'][] = $option;
        }

        $orderedGroups = [];
        foreach ([...array_keys($families), 'other'] as $family) {
            if (isset($groups[$family])) {
                $orderedGroups[$family] = $groups[$family];
            }
        }

        return $orderedGroups;
    }
}
