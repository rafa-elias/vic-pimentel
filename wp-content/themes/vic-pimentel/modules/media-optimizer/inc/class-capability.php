<?php

namespace MediaOptimizer;

if (!defined('ABSPATH')) exit;

/**
 * Detects which WebP encoding backend the host actually supports, so the
 * rest of the module never assumes Imagick is available — confirmed during
 * this project's own optimization work that Imagick's own quality control is
 * more reliable than WP_Image_Editor's, but not every host has Imagick at
 * all. GD is the near-universal fallback; if neither is available, WebP
 * generation genuinely isn't possible server-side, and callers should show
 * missing_message() rather than fail silently.
 */
class Capability
{
    const TRANSIENT_KEY = 'media_optimizer_capability';
    const CACHE_TTL = DAY_IN_SECONDS;

    public static function init(): void
    {
        add_action('admin_notices', [self::class, 'maybe_show_notice']);
    }

    /**
     * @return string One of 'imagick', 'gd', 'none'.
     */
    public static function get(): string
    {
        $cached = get_transient(self::TRANSIENT_KEY);
        if ($cached !== false) return $cached;

        $tier = self::detect();
        set_transient(self::TRANSIENT_KEY, $tier, self::CACHE_TTL);

        return $tier;
    }

    /**
     * Clears the cached result — call after a host capability change, or
     * from the settings page's "re-check" action.
     */
    public static function refresh(): string
    {
        delete_transient(self::TRANSIENT_KEY);
        return self::get();
    }

    private static function detect(): string
    {
        if (self::has_imagick_webp()) return 'imagick';
        if (self::has_gd_webp()) return 'gd';
        return 'none';
    }

    private static function has_imagick_webp(): bool
    {
        if (!extension_loaded('imagick') || !class_exists('Imagick')) return false;

        try {
            $formats = \Imagick::queryFormats('WEBP');
            return !empty($formats);
        } catch (\Exception $e) {
            return false;
        }
    }

    private static function has_gd_webp(): bool
    {
        return function_exists('imagewebp') && function_exists('imagecreatefromjpeg');
    }

    public static function missing_message(): string
    {
        return __(
            'MediaTrim: WebP conversion needs the Imagick or GD PHP extension with WebP support enabled on this server. Ask your host to enable one of these — most managed hosts already have one available; some shared-hosting plans need it turned on explicitly.',
            'rear-theme'
        );
    }

    public static function maybe_show_notice(): void
    {
        if (!current_user_can('manage_options')) return;
        if (self::get() !== 'none') return;

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || !in_array($screen->id, ['upload', 'dashboard', 'settings_page_media-optimizer'], true)) return;

        printf('<div class="notice notice-warning"><p>%s</p></div>', esc_html(self::missing_message()));
    }
}
