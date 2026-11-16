<?php

namespace MediaOptimizer;

if (!defined('ABSPATH')) exit;

/**
 * Media Library list-view integration: a status column, per-image
 * "Re-optimize"/"Restore Original" row actions, and a native bulk action.
 * Row actions are plain nonce-protected links handled via admin_action_*,
 * not AJAX — deliberately simple for a single image at a time. The bulk
 * action here processes synchronously and is meant for a manually-selected
 * handful of rows; a chunked/async "process everything" tool is a separate,
 * later addition, not a replacement for this.
 *
 * Note: bulk actions (and this column) only appear in the Media Library's
 * List view, not the default Grid view — a native WordPress limitation, not
 * something this class can change.
 */
class Media_Library_UI
{
    const COLUMN_KEY = 'media_optimizer_status';
    const ACTION_REOPTIMIZE = 'media_optimizer_reoptimize';
    const ACTION_RESTORE = 'media_optimizer_restore';
    const ACTION_REMOVE_WEBP = 'media_optimizer_remove_webp';
    const ACTION_RESTORE_ALL = 'media_optimizer_restore_all';
    const BULK_ACTION = 'media_optimizer_bulk_optimize';

    public static function init(): void
    {
        add_filter('manage_media_columns', [self::class, 'add_column']);
        add_action('manage_media_custom_column', [self::class, 'render_column'], 10, 2);

        add_filter('media_row_actions', [self::class, 'add_row_actions'], 10, 2);

        add_action('admin_action_' . self::ACTION_REOPTIMIZE, [self::class, 'handle_reoptimize']);
        add_action('admin_action_' . self::ACTION_RESTORE, [self::class, 'handle_restore']);
        add_action('admin_action_' . self::ACTION_REMOVE_WEBP, [self::class, 'handle_remove_webp']);
        add_action('admin_action_' . self::ACTION_RESTORE_ALL, [self::class, 'handle_restore_all']);

        add_filter('bulk_actions-upload', [self::class, 'add_bulk_action']);
        add_filter('handle_bulk_actions-upload', [self::class, 'handle_bulk_action'], 10, 3);

        add_action('admin_notices', [self::class, 'maybe_show_notice']);
        add_action('admin_enqueue_scripts', [self::class, 'maybe_enqueue_styles']);
    }

    public static function maybe_enqueue_styles(string $hook): void
    {
        if ($hook !== 'upload.php') return;

        wp_enqueue_style(
            'media-optimizer-admin',
            MEDIA_OPTIMIZER_URL . '/assets/admin.css',
            [],
            filemtime(MEDIA_OPTIMIZER_DIR . '/assets/admin.css')
        );
    }

    public static function add_column(array $columns): array
    {
        $columns[self::COLUMN_KEY] = __('Optimization', 'rear-theme');
        return $columns;
    }

    public static function render_column(string $column, int $attachment_id): void
    {
        if ($column !== self::COLUMN_KEY) return;

        $mime = get_post_mime_type($attachment_id);
        if (!in_array($mime, Converter::ELIGIBLE_MIMES, true)) {
            echo '<span class="media-optimizer-badge media-optimizer-badge-neutral">' . esc_html__('N/A', 'rear-theme') . '</span>';
            return;
        }

        $status = Tracking::get_status($attachment_id);
        echo self::status_badge($status, __('WebP', 'rear-theme'));

        $savings = Tracking::get_savings($attachment_id);
        if ($savings) {
            printf(' <span class="media-optimizer-savings">-%s%%</span>', esc_html((string) $savings['percent']));
        }

        $recompress_status = Tracking::get_recompress_status($attachment_id);
        if ($recompress_status !== 'pending') {
            echo '<br>' . self::status_badge($recompress_status, __('Original', 'rear-theme'));

            $recompress_savings = Tracking::get_recompress_savings($attachment_id);
            if ($recompress_savings) {
                printf(' <span class="media-optimizer-savings">-%s%%</span>', esc_html((string) $recompress_savings['percent']));
            }
        }
    }

    private static function status_badge(string $status, string $label): string
    {
        $classes = [
            'optimized' => 'success',
            'recompressed' => 'success',
            'restored' => 'neutral',
            'failed' => 'error',
            'unsupported' => 'neutral',
            'pending' => 'neutral',
        ];
        $class = $classes[$status] ?? 'neutral';

        return sprintf(
            '<span class="media-optimizer-badge media-optimizer-badge-%s">%s: %s</span>',
            esc_attr($class),
            esc_html($label),
            esc_html(ucfirst($status))
        );
    }

    /**
     * @param array<string, string> $actions
     */
    public static function add_row_actions(array $actions, \WP_Post $post): array
    {
        if (!in_array($post->post_mime_type, Converter::ELIGIBLE_MIMES, true)) return $actions;
        if (!current_user_can('manage_options')) return $actions;

        $actions[self::ACTION_REOPTIMIZE] = sprintf(
            '<a href="%s">%s</a>',
            esc_url(self::action_url(self::ACTION_REOPTIMIZE, $post->ID)),
            esc_html__('Re-optimize', 'rear-theme')
        );

        $has_backup = Recompressor::can_restore($post->ID);
        $has_webp = Tracking::is_optimized($post->ID);

        if ($has_backup && $has_webp) {
            $actions[self::ACTION_RESTORE_ALL] = sprintf(
                '<a href="%1$s" onclick="return confirm(\'%2$s\');">%3$s</a>',
                esc_url(self::action_url(self::ACTION_RESTORE_ALL, $post->ID)),
                esc_js(__('Restore this image to exactly what was originally uploaded? This undoes both re-compression and WebP generation.', 'rear-theme')),
                esc_html__('Restore All', 'rear-theme')
            );
        }

        if ($has_backup) {
            $actions[self::ACTION_RESTORE] = sprintf(
                '<a href="%1$s" onclick="return confirm(\'%2$s\');">%3$s</a>',
                esc_url(self::action_url(self::ACTION_RESTORE, $post->ID)),
                esc_js(__('Restore the original file? This replaces the current re-compressed version.', 'rear-theme')),
                esc_html__('Restore Original', 'rear-theme')
            );
        }

        if ($has_webp) {
            $actions[self::ACTION_REMOVE_WEBP] = sprintf(
                '<a href="%1$s" onclick="return confirm(\'%2$s\');">%3$s</a>',
                esc_url(self::action_url(self::ACTION_REMOVE_WEBP, $post->ID)),
                esc_js(__('Remove the generated WebP files? Visitors will be served the original image again.', 'rear-theme')),
                esc_html__('Remove WebP', 'rear-theme')
            );
        }

        return $actions;
    }

    private static function action_url(string $action, int $attachment_id): string
    {
        return wp_nonce_url(
            admin_url("admin.php?action=$action&attachment_id=$attachment_id"),
            "$action-$attachment_id"
        );
    }

    public static function handle_reoptimize(): void
    {
        $attachment_id = self::verify_request(self::ACTION_REOPTIMIZE);

        if (Settings::generate_webp()) Converter::process($attachment_id, Settings::webp_quality());
        if (Settings::recompress_originals()) Recompressor::process($attachment_id, Settings::recompress_quality());

        Stats::clear_cache();
        Admin_Bar::clear_pending_cache();
        self::redirect_back(['media_optimizer_notice' => 'reoptimized']);
    }

    public static function handle_restore(): void
    {
        $attachment_id = self::verify_request(self::ACTION_RESTORE);

        Recompressor::restore($attachment_id);

        Stats::clear_cache();
        Admin_Bar::clear_pending_cache();
        self::redirect_back(['media_optimizer_notice' => 'restored']);
    }

    public static function handle_remove_webp(): void
    {
        $attachment_id = self::verify_request(self::ACTION_REMOVE_WEBP);

        Converter::remove($attachment_id);

        Stats::clear_cache();
        Admin_Bar::clear_pending_cache();
        self::redirect_back(['media_optimizer_notice' => 'webp_removed']);
    }

    public static function handle_restore_all(): void
    {
        $attachment_id = self::verify_request(self::ACTION_RESTORE_ALL);

        // Order matters: restore() regenerates WebP from the freshly-restored
        // pristine file when the setting is on, so remove() must run last to
        // leave the attachment truly untouched — original file, no WebP.
        Recompressor::restore($attachment_id);
        Converter::remove($attachment_id);

        Stats::clear_cache();
        Admin_Bar::clear_pending_cache();
        self::redirect_back(['media_optimizer_notice' => 'restored_all']);
    }

    private static function verify_request(string $action): int
    {
        if (!current_user_can('manage_options')) wp_die(esc_html__('Not allowed.', 'rear-theme'));

        $attachment_id = isset($_GET['attachment_id']) ? (int) $_GET['attachment_id'] : 0;
        if (!$attachment_id) wp_die(esc_html__('Missing attachment.', 'rear-theme'));

        check_admin_referer("$action-$attachment_id");

        return $attachment_id;
    }

    private static function redirect_back(array $extra_args): void
    {
        $redirect = wp_get_referer() ?: admin_url('upload.php');
        wp_safe_redirect(add_query_arg($extra_args, $redirect));
        exit;
    }

    /**
     * @param string[] $actions
     */
    public static function add_bulk_action(array $actions): array
    {
        if (!current_user_can('manage_options')) return $actions;

        $actions[self::BULK_ACTION] = __('Optimize now (MediaTrim)', 'rear-theme');
        return $actions;
    }

    /**
     * @param int[] $attachment_ids
     */
    public static function handle_bulk_action(string $redirect_to, string $action, array $attachment_ids): string
    {
        if ($action !== self::BULK_ACTION) return $redirect_to;

        // Every other entry point (row actions, the AJAX bulk tool, the admin
        // bar) requires manage_options — this native bulk action is the one
        // place that didn't, since WP core only guarantees the surrounding
        // list-table request itself was capability/nonce-checked, not this
        // specific action. Without this, any role that can bulk-act in the
        // Media Library (broader than admins on some setups) could trigger
        // server-side image processing across the whole library.
        if (!current_user_can('manage_options')) return $redirect_to;

        $processed = 0;
        foreach ($attachment_ids as $attachment_id) {
            $mime = get_post_mime_type($attachment_id);
            if (!in_array($mime, Converter::ELIGIBLE_MIMES, true)) continue;

            if (Settings::generate_webp()) Converter::process($attachment_id, Settings::webp_quality());
            if (Settings::recompress_originals()) Recompressor::process($attachment_id, Settings::recompress_quality());
            $processed++;
        }

        Stats::clear_cache();
        Admin_Bar::clear_pending_cache();

        return add_query_arg('media_optimizer_bulk_processed', $processed, $redirect_to);
    }

    public static function maybe_show_notice(): void
    {
        if (!empty($_GET['media_optimizer_bulk_processed'])) {
            $count = (int) $_GET['media_optimizer_bulk_processed'];
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html(sprintf(
                    /* translators: %d: number of images processed */
                    _n('MediaTrim: %d image processed.', 'MediaTrim: %d images processed.', $count, 'rear-theme'),
                    $count
                ))
            );
        }

        if (!empty($_GET['media_optimizer_notice'])) {
            $messages = [
                'reoptimized' => __('MediaTrim: image re-optimized.', 'rear-theme'),
                'restored' => __('MediaTrim: original restored.', 'rear-theme'),
                'webp_removed' => __('MediaTrim: WebP removed, original will be served.', 'rear-theme'),
                'restored_all' => __('MediaTrim: image fully restored to its original upload.', 'rear-theme'),
            ];
            $key = sanitize_key($_GET['media_optimizer_notice']);
            if (isset($messages[$key])) {
                printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html($messages[$key]));
            }
        }
    }
}
