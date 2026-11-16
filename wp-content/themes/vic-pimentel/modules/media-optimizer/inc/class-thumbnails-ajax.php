<?php

namespace MediaOptimizer;

if (!defined('ABSPATH')) exit;

/**
 * Mirrors Bulk_Ajax's shape (queue + batch endpoints) for the Regenerate
 * Thumbnails page — kept separate since the queue itself has no
 * already-optimized filter the way WebP/recompression does; whether a given
 * attachment actually needs work is decided per-image inside
 * Thumbnail_Regenerator::needs_regeneration() (the "skip if correct" option),
 * not by the queue query.
 */
class Thumbnails_Ajax
{
    const NONCE_ACTION = 'media_optimizer_thumbnails';

    public static function init(): void
    {
        add_action('wp_ajax_media_optimizer_thumbnails_get_queue', [self::class, 'get_queue']);
        add_action('wp_ajax_media_optimizer_thumbnails_process_batch', [self::class, 'process_batch']);
    }

    public static function get_queue(): void
    {
        self::verify_request();

        $ids = array_map('intval', get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'post_mime_type' => Thumbnail_Regenerator::ELIGIBLE_MIMES,
            'posts_per_page' => -1,
            'fields' => 'ids',
        ]));

        wp_send_json_success(['ids' => $ids, 'total' => count($ids)]);
    }

    public static function process_batch(): void
    {
        self::verify_request();

        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
        $skip_if_correct = !empty($_POST['skip_if_correct']);
        $delete_stale = !empty($_POST['delete_stale']);
        $results = [];

        foreach ($ids as $id) {
            $result = Thumbnail_Regenerator::process($id, $skip_if_correct, $delete_stale);

            $results[] = [
                'id' => $id,
                'title' => get_the_title($id),
                'thumbnail' => wp_get_attachment_image_url($id, [48, 48]),
                'status' => $result['status'],
                'regenerated_sizes' => $result['regenerated_sizes'],
                'deleted_sizes' => $result['deleted_sizes'],
            ];
        }

        Stats::clear_cache();

        wp_send_json_success(['results' => $results]);
    }

    private static function verify_request(): void
    {
        if (!current_user_can('manage_options')) wp_send_json_error('forbidden', 403);
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
    }
}
