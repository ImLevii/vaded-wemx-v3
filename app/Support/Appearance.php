<?php

namespace App\Support;

/**
 * Site appearance settings shared by the admin "Theme & Appearance" page and
 * the <x-theme::appearance-config /> meta tag that vaded-theme.js reads.
 */
class Appearance
{
    public const EFFECT_NONE = 'none';

    /** Default page effect for every edition, from config('appearance.themes.*.effect'). */
    public static function effectDefaults(): array
    {
        $defaults = [];
        foreach (config('appearance.themes', []) as $key => $theme) {
            $defaults[$key] = self::isEffect($theme['effect'] ?? null) ? $theme['effect'] : self::EFFECT_NONE;
        }

        return $defaults;
    }

    public static function isEffect(mixed $effect): bool
    {
        return is_string($effect) && ($effect === self::EFFECT_NONE || array_key_exists($effect, config('appearance.effects', [])));
    }

    /**
     * The effect for every edition: the saved choice when it is valid, otherwise the edition's default.
     * Unknown editions and effects are dropped, so a stale setting can't reach the page.
     */
    public static function effectMap(mixed $stored = null): array
    {
        if (func_num_args() === 0) {
            $stored = settings('appearance_effect_map');
        }
        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }
        $stored = is_array($stored) ? $stored : [];

        $map = [];
        foreach (self::effectDefaults() as $theme => $default) {
            $map[$theme] = self::isEffect($stored[$theme] ?? null) ? $stored[$theme] : $default;
        }

        return $map;
    }

    /** A saved option value, or the default when the stored value is no longer one of the choices. */
    public static function option(string $setting, string $choices, string $default): string
    {
        $value = (string) settings($setting, $default);

        return array_key_exists($value, config("appearance.{$choices}", [])) ? $value : $default;
    }

    /** Everything the browser needs; this becomes the wemx-appearance meta tag. */
    public static function clientConfig(): array
    {
        $effectsScript = public_path('assets/common/js/vaded-effects.js');

        return [
            'mode' => settings('appearance_mode', 'auto'),
            'accent' => settings('appearance_accent', 'vaded'),
            'seasonal' => settings('appearance_seasonal', 'auto'),
            'motion' => (bool) settings('appearance_motion', true),
            'logoMotion' => (bool) settings('appearance_logo_motion', true),
            'effects' => (bool) settings('appearance_effects', false),
            'effectMap' => self::effectMap(),
            'effectIntensity' => self::option('appearance_effect_intensity', 'effect_intensities', 'normal'),
            'effectSpeed' => self::option('appearance_effect_speed', 'effect_speeds', 'normal'),
            'effectStrength' => self::option('appearance_effect_strength', 'effect_strengths', 'normal'),
            'effectAreas' => self::option('appearance_effect_areas', 'effect_areas', 'client'),
            'effectsScript' => asset('assets/common/js/vaded-effects.js').(is_file($effectsScript) ? '?v='.filemtime($effectsScript) : ''),
        ];
    }
}
