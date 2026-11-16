<?php

namespace MediaOptimizer;

if (!defined('ABSPATH')) exit;

/**
 * Two endpoints powering Optimize_Page's bulk tool and the admin bar's
 * "Process now" action: one to fetch the queue of attachment IDs up front
 * (lightweight — IDs only), one to process a small batch of them per
 * request. The JS drives the chunking and progress UI; this class only ever
 * sees one batch at a time, so a big library can't time out a single
 * request the way one giant "process everything now" call would.
 */
class Bulk_Ajax
{
    const NONCE_ACTION = 'media_optimizer_bulk';

    public static function init(): void
    {
        add_action('wp_ajax_media_optimizer_get_queue', [self::class, 'get_queue']);
        add_action('wp_ajax_media_optimizer_process_batch', [self::class, 'process_batch']);
        add_action('wp_ajax_media_optimizer_get_restore_queue', [self::class, 'get_restore_queue']);
        add_action('wp_ajax_media_optimizer_process_restore_batch', [self::class, 'process_restore_batch']);
    }

    public static function get_queue(): void
    {
        self::verify_request();

        $force = !empty($_POST['force']);

        $args = [
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_mime_type' => Converter::ELIGIBLE_MIMES,
            'posts_per_page' => -1,
            'fields' => 'ids',
        ];

        if (!$force) {
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key' => Tracking::META_STATUS,
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => Tracking::META_STATUS,
                    'value' => 'optimized',
                    'compare' => '!=',
                ],
            ];
        }

        $ids = array_map('intval', get_posts($args));

        wp_send_json_success(['ids' => $ids, 'total' => count($ids)]);
    }

    public static function process_batch(): void
    {
        self::verify_request();

        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
        $results = [];

        foreach ($ids as $id) {
            $mime = get_post_mime_type($id);

            if (!in_array($mime, Converter::ELIGIBLE_MIMES, true)) {
                $results[] = ['id' => $id, 'title' => get_the_title($id), 'status' => 'skipped', 'savings' => null];
                continue;
            }

            if (Settings::recompress_originals()) {
                Recompressor::process($id, Settings::recompress_quality());
            }

            if (Settings::generate_webp()) {
                Converter::process($id, Settings::webp_quality());
            }

            // Reflects the real tracked outcome, same status the Media
            // Library column would show — not a separately-computed guess.
            $results[] = [
                'id' => $id,
                'title' => get_the_title($id),
                'thumbnail' => wp_get_attachment_image_url($id, [48, 48]),
                'status' => Tracking::get_status($id),
                'savings' => Tracking::get_savings($id),
            ];
        }

        Stats::clear_cache();
        Admin_Bar::clear_pending_cache();

        wp_send_json_success(['results' => $results, 'stats' => Stats::get_formatted()]);
    }

    /**
     * Queue for the library-wide "Restore All Images" / "Remove All WebP"
     * tools — only attachments that actually have something to undo for the
     * requested type, so a big library doesn't iterate untouched images for
     * nothing (Recompressor::restore()/Converter::remove() are harmless
     * no-ops on an untouched attachment, but there's no reason to spend a
     * request on one).
     */
    public static function get_restore_queue(): void
    {
        self::verify_request();

        $type = isset($_POST['type']) ? sanitize_key($_POST['type']) : '';
        if (!in_array($type, ['restore_all', 'remove_webp'], true)) wp_send_json_error('invalid_request', 400);

        $meta_query = $type === 'remove_webp'
            ? [['key' => Tracking::META_STATUS, 'value' => 'optimized']]
            : [
                'relation' => 'OR',
                ['key' => Tracking::META_STATUS, 'value' => 'optimized'],
                ['key' => Tracking::META_RECOMPRESS_STATUS, 'value' => 'recompressed'],
            ];

        $ids = array_map('intval', get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_mime_type' => Converter::ELIGIBLE_MIMES,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => $meta_query,
        ]));

        wp_send_json_success(['ids' => $ids, 'total' => count($ids)]);
    }

    public static function process_restore_batch(): void
    {
        self::verify_request();

        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
        $type = isset($_POST['type']) ? sanitize_key($_POST['type']) : '';
        if (!in_array($type, ['restore_all', 'remove_webp'], true)) wp_send_json_error('invalid_request', 400);

        $results = [];
        foreach ($ids as $id) {
            if ($type === 'restore_all') {
                // Same ordering reasoning as Media_Library_UI::handle_restore_all()
                // — restore() may regenerate WebP from the pristine file, so
                // remove() has to run last to leave the attachment fully
                // untouched.
                Recompressor::restore($id);
                Converter::remove($id);
            } else {
                Converter::remove($id);
            }

            $results[] = ['id' => $id, 'title' => get_the_title($id)];
        }

        Stats::clear_cache();
        Admin_Bar::clear_pending_cache();

        wp_send_json_success(['results' => $results, 'stats' => Stats::get_formatted()]);
    }

    private static function verify_request(): void
    {
        if (!current_user_can('manage_options')) wp_send_json_error('forbidden', 403);
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
    }
}
