<?php

namespace MediaOptimizer;

if (!defined('ABSPATH')) exit;

/**
 * New feature (not part of the original plan) — regenerate every registered
 * image size for all images from their originals. Requested as a native
 * replacement for a separately-installed "Regenerate Thumbnails"-style
 * plugin, useful whenever registered image sizes change. Options (skip
 * already-correct, delete stale files) and the registered-sizes list below
 * mirror what that reference plugin offered.
 */
class Thumbnails_Page
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) return;

        wp_enqueue_style(
            'media-optimizer-admin',
            MEDIA_OPTIMIZER_URL . '/assets/admin.css',
            [],
            filemtime(MEDIA_OPTIMIZER_DIR . '/assets/admin.css')
        );
        wp_enqueue_script(
            'media-optimizer-thumbnails',
            MEDIA_OPTIMIZER_URL . '/assets/thumbnails.js',
            [],
            filemtime(MEDIA_OPTIMIZER_DIR . '/assets/thumbnails.js'),
            true
        );
        wp_localize_script('media-optimizer-thumbnails', 'mediaOptimizerThumbnails', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(Thumbnails_Ajax::NONCE_ACTION),
            'i18n' => [
                'fetchingQueue' => __('Finding images…', 'rear-theme'),
                'processing' => __('Regenerating %1$d of %2$d…', 'rear-theme'),
                'done' => __('Done — %d images processed.', 'rear-theme'),
                'cancelled' => __('Cancelled.', 'rear-theme'),
                'nothingToDo' => __('No images found.', 'rear-theme'),
                'failed' => __('Failed', 'rear-theme'),
                'regenerated' => __('Regenerated', 'rear-theme'),
                'skipped' => __('Skipped (already correct)', 'rear-theme'),
                'regeneratedSizes' => __('Regenerated', 'rear-theme'),
                'deletedSizes' => __('Deleted', 'rear-theme'),
                /* translators: %s: an already-formatted duration, e.g. "2m 30s" */
                'timeRemaining' => __('(~%s remaining)', 'rear-theme'),
            ],
        ]);
        ?>
        <div class="wrap media-optimizer-settings">
            <h1><?php esc_html_e('MediaTrim — Regenerate Thumbnails', 'rear-theme'); ?></h1>
            <p class="description">
                <?php esc_html_e('Recreates registered image sizes (thumbnail, medium, large, and any custom sizes) from each image\'s original file. Use this after changing image size settings, or if generated sizes look wrong or outdated. The original file itself is never touched.', 'rear-theme'); ?>
            </p>

            <div class="media-optimizer-bulk" data-media-optimizer-thumbnails>
                <label class="media-optimizer-bulk-force">
                    <input type="checkbox" data-thumb-skip-correct checked>
                    <?php esc_html_e('Skip images that already have correctly-sized thumbnails (faster)', 'rear-theme'); ?>
                </label>

                <label class="media-optimizer-bulk-force">
                    <input type="checkbox" data-thumb-delete-stale>
                    <?php esc_html_e('Delete old thumbnail files that no longer match a registered size, to free up server space', 'rear-theme'); ?>
                </label>
                <p class="description media-optimizer-warning">
                    <?php esc_html_e('This may break anything still directly linking to an old size\'s file URL. Leave unchecked unless you specifically want to reclaim disk space.', 'rear-theme'); ?>
                </p>

                <div class="media-optimizer-bulk-controls">
                    <button type="button" class="button button-primary" data-bulk-start><?php esc_html_e('Start', 'rear-theme'); ?></button>
                    <button type="button" class="button" data-bulk-cancel hidden><?php esc_html_e('Cancel', 'rear-theme'); ?></button>
                </div>

                <div class="media-optimizer-progress" data-bulk-progress-wrap hidden>
                    <div class="media-optimizer-progress-bar">
                        <div class="media-optimizer-progress-fill" data-bulk-progress-fill style="width: 0%"></div>
                    </div>
                </div>

                <p class="media-optimizer-bulk-status" data-bulk-status></p>

                <table class="wp-list-table widefat fixed striped media-optimizer-results" data-bulk-results-table hidden>
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Image', 'rear-theme'); ?></th>
                            <th><?php esc_html_e('Status', 'rear-theme'); ?></th>
                            <th><?php esc_html_e('Details', 'rear-theme'); ?></th>
                        </tr>
                    </thead>
                    <tbody data-bulk-results-body></tbody>
                </table>
            </div>

            <h2><?php esc_html_e('Registered Thumbnail Sizes', 'rear-theme'); ?></h2>
            <p class="description"><?php esc_html_e('These are the sizes currently registered on this site — this is what regeneration produces.', 'rear-theme'); ?></p>
            <?php self::render_registered_sizes(); ?>
        </div>
        <?php
    }

    private static function render_registered_sizes(): void
    {
        $sizes = function_exists('wp_get_registered_image_subsizes') ? wp_get_registered_image_subsizes() : [];

        if (empty($sizes)) {
            echo '<p>' . esc_html__('No custom sizes are registered.', 'rear-theme') . '</p>';
            return;
        }
        ?>
        <ul class="media-optimizer-sizes-list">
            <?php foreach ($sizes as $name => $size): ?>
                <li>
                    <strong><?php echo esc_html($name); ?>:</strong>
                    <?php
                    printf(
                        /* translators: 1: width, 2: height, 3: crop/proportional behavior description */
                        esc_html__('%1$d×%2$d pixels (%3$s)', 'rear-theme'),
                        (int) $size['width'],
                        (int) $size['height'],
                        $size['crop']
                            ? esc_html__('cropped to fit', 'rear-theme')
                            : esc_html__('proportionally resized to fit inside dimensions', 'rear-theme')
                    );
                    ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }
}
