<?php

namespace MediaOptimizer;

if (!defined('ABSPATH')) exit;

/**
 * Registers the 3-page menu structure — this class only owns menu
 * registration; each page's own rendering/handling lives in its own class
 * (Settings_Page, Optimize_Page, Thumbnails_Page).
 */
class Admin_Menu
{
    const SETTINGS_SLUG = 'media-optimizer';
    const OPTIMIZE_SLUG = 'media-optimizer-optimize';
    const THUMBNAILS_SLUG = 'media-optimizer-thumbnails';

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register']);
    }

    public static function register(): void
    {
        add_menu_page(
            __('MediaTrim', 'rear-theme'),
            __('MediaTrim', 'rear-theme'),
            'manage_options',
            self::SETTINGS_SLUG,
            [Settings_Page::class, 'render'],
            'dashicons-images-alt2',
            80
        );

        add_submenu_page(
            self::SETTINGS_SLUG,
            __('Settings', 'rear-theme'),
            __('Settings', 'rear-theme'),
            'manage_options',
            self::SETTINGS_SLUG,
            [Settings_Page::class, 'render']
        );

        add_submenu_page(
            self::SETTINGS_SLUG,
            __('Optimize Images', 'rear-theme'),
            __('Optimize Images', 'rear-theme'),
            'manage_options',
            self::OPTIMIZE_SLUG,
            [Optimize_Page::class, 'render']
        );

        add_submenu_page(
            self::SETTINGS_SLUG,
            __('Regenerate Thumbnails', 'rear-theme'),
            __('Regenerate Thumbnails', 'rear-theme'),
            'manage_options',
            self::THUMBNAILS_SLUG,
            [Thumbnails_Page::class, 'render']
        );
    }
}
