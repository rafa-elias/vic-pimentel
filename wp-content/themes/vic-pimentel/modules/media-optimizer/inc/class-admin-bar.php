<?php

namespace MediaOptimizer;

if (!defined('ABSPATH')) exit;

/**
 * A "MediaTrim" node in the WordPress admin bar (wp-admin and the front end,
 * wherever the bar shows) — the always-available manual override regardless
 * of whether the on-upload processing (shutdown hook or WP-Cron) actually
 * fired on a given host. Shows the current pending/unoptimized count as a
 * badge with a pulsing indicator, and a "Process now" action that runs the
 * same batch pipeline as the Optimize Images page's bulk tool, from
 * wherever the admin currently is.
 */
class Admin_Bar
{
    const PENDING_TRANSIENT = 'media_optimizer_pending_count';

    public static function init(): void
    {
        add_action('admin_bar_menu', [self::class, 'add_node'], 100);
        add_action('wp_enqueue_scripts', [self::class, 'maybe_enqueue']);
        add_action('admin_enqueue_scripts', [self::class, 'maybe_enqueue']);
    }

    public static function add_node(\WP_Admin_Bar $admin_bar): void
    {
        if (!current_user_can('manage_options')) return;

        $pending = self::get_pending_count();

        $title = '<span class="ab-icon dashicons dashicons-images-alt2"></span>';
        $title .= '<span class="media-optimizer-ab-label">' . esc_html__('MediaTrim', 'rear-theme') . '</span>';
        if ($pending > 0) {
            $title .= sprintf('<span class="media-optimizer-ab-badge">%d</span>', $pending);
        }

        $admin_bar->add_node([
            'id' => 'media-optimizer',
            'title' => $title,
            'href' => admin_url('admin.php?page=' . Admin_Menu::OPTIMIZE_SLUG),
            'meta' => ['class' => 'media-optimizer-ab-node' . ($pending > 0 ? ' has-pending' : '')],
        ]);

        if ($pending > 0) {
            $admin_bar->add_node([
                'id' => 'media-optimizer-process-now',
                'parent' => 'media-optimizer',
                'title' => sprintf(
                    /* translators: %d: number of pending images */
                    esc_html__('Process %d pending image(s) now', 'rear-theme'),
                    $pending
                ),
                'href' => '#',
                'meta' => ['class' => 'media-optimizer-process-now'],
            ]);
        }

        $admin_bar->add_node([
            'id' => 'media-optimizer-optimize-link',
            'parent' => 'media-optimizer',
            'title' => esc_html__('Optimize Images', 'rear-theme'),
            'href' => admin_url('admin.php?page=' . Admin_Menu::OPTIMIZE_SLUG),
        ]);

        $admin_bar->add_node([
            'id' => 'media-optimizer-settings-link',
            'parent' => 'media-optimizer',
            'title' => esc_html__('Settings', 'rear-theme'),
            'href' => admin_url('admin.php?page=' . Admin_Menu::SETTINGS_SLUG),
        ]);
    }

    public static function maybe_enqueue(): void
    {
        if (!is_admin_bar_showing() || !current_user_can('manage_options')) return;

        wp_enqueue_style(
            'media-optimizer-admin-bar',
            MEDIA_OPTIMIZER_URL . '/assets/admin-bar.css',
            [],
            filemtime(MEDIA_OPTIMIZER_DIR . '/assets/admin-bar.css')
        );
        wp_enqueue_script(
            'media-optimizer-admin-bar',
            MEDIA_OPTIMIZER_URL . '/assets/admin-bar.js',
            [],
            filemtime(MEDIA_OPTIMIZER_DIR . '/assets/admin-bar.js'),
            true
        );
        wp_localize_script('media-optimizer-admin-bar', 'mediaOptimizerAdminBar', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(Bulk_Ajax::NONCE_ACTION),
            'i18n' => [
                'processing' => __('Processing…', 'rear-theme'),
                'done' => __('Done!', 'rear-theme'),
            ],
        ]);
    }

    public static function get_pending_count(): int
    {
        $cached = get_transient(self::PENDING_TRANSIENT);
        if ($cached !== false) return (int) $cached;

        $ids = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_mime_type' => Converter::ELIGIBLE_MIMES,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'OR',
                ['key' => Tracking::META_STATUS, 'compare' => 'NOT EXISTS'],
                ['key' => Tracking::META_STATUS, 'value' => 'optimized', 'compare' => '!='],
            ],
        ]);

        $count = count($ids);
        set_transient(self::PENDING_TRANSIENT, $count, 5 * MINUTE_IN_SECONDS);

        return $count;
    }

    public static function clear_pending_cache(): void
    {
        delete_transient(self::PENDING_TRANSIENT);
    }
}
