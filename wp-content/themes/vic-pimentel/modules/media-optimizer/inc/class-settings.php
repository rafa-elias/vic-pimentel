<?php

namespace MediaOptimizer;

if (!defined('ABSPATH')) exit;

/**
 * Data model for the module's configurable behavior — a single option, not
 * scattered ones, so import/export and reset-to-defaults stay simple.
 * Settings_Page owns the admin UI; this class owns storage + defaults only.
 */
class Settings
{
    const OPTION_KEY = 'media_optimizer_settings';

    const PRESETS = [
        'low' => ['webp_quality' => 60, 'recompress_quality' => 70],
        'balanced' => ['webp_quality' => 75, 'recompress_quality' => 82],
        'high' => ['webp_quality' => 85, 'recompress_quality' => 90],
    ];

    const DEFAULTS = [
        'optimize_on_upload' => true,
        'generate_webp' => true,
        'recompress_originals' => false,
        'quality_preset' => 'balanced', // 'low' | 'balanced' | 'high' | 'custom'
        'webp_quality' => 75,           // only read when quality_preset === 'custom'
        'recompress_quality' => 82,     // only read when quality_preset === 'custom'
    ];

    public static function get_all(): array
    {
        $saved = get_option(self::OPTION_KEY, []);
        return wp_parse_args(is_array($saved) ? $saved : [], self::DEFAULTS);
    }

    /**
     * @return mixed
     */
    public static function get(string $key)
    {
        $all = self::get_all();
        return $all[$key] ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function update(array $values): void
    {
        update_option(self::OPTION_KEY, array_merge(self::get_all(), $values));
    }

    public static function optimize_on_upload(): bool
    {
        return (bool) self::get('optimize_on_upload');
    }

    public static function generate_webp(): bool
    {
        return (bool) self::get('generate_webp');
    }

    public static function recompress_originals(): bool
    {
        return (bool) self::get('recompress_originals');
    }

    public static function webp_quality(): int
    {
        return self::resolve_quality('webp_quality');
    }

    public static function recompress_quality(): int
    {
        return self::resolve_quality('recompress_quality');
    }

    private static function resolve_quality(string $key): int
    {
        $preset = self::get('quality_preset');

        if ($preset !== 'custom' && isset(self::PRESETS[$preset][$key])) {
            return self::PRESETS[$preset][$key];
        }

        return (int) self::get($key);
    }
}
