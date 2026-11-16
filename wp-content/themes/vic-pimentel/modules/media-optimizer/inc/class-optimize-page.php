<?php

namespace MediaOptimizer;

if (!defined('ABSPATH')) exit;

/**
 * "Process existing images" — moved here from the Settings page. Unlike the
 * Settings page's simple aggregate progress bar this originally had, this
 * page shows a live, growing image-by-image list as each batch completes
 * (name, thumbnail, before/after size, % saved) — more useful for actually
 * watching what a big run is doing than a single bar ever was.
 */
class Optimize_Page
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
            'media-optimizer-optimize',
            MEDIA_OPTIMIZER_URL . '/assets/optimize.js',
            [],
            filemtime(MEDIA_OPTIMIZER_DIR . '/assets/optimize.js'),
            true
        );
        wp_localize_script('media-optimizer-optimize', 'mediaOptimizerBulk', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(Bulk_Ajax::NONCE_ACTION),
            'i18n' => [
                'fetchingQueue' => __('Finding images to process…', 'rear-theme'),
                'processing' => __('Processing %1$d of %2$d…', 'rear-theme'),
                'done' => __('Done — %d images processed.', 'rear-theme'),
                'cancelled' => __('Cancelled.', 'rear-theme'),
                'nothingToDo' => __('Nothing to process — everything is already optimized.', 'rear-theme'),
                'skipped' => __('Skipped (unsupported file type)', 'rear-theme'),
                'failed' => __('Failed', 'rear-theme'),
                'optimized' => __('Optimized', 'rear-theme'),
                /* translators: %s: an already-formatted duration, e.g. "2m 30s" */
                'timeRemaining' => __('(~%s remaining)', 'rear-theme'),
            ],
        ]);

        wp_enqueue_script(
            'media-optimizer-restore-all',
            MEDIA_OPTIMIZER_URL . '/assets/restore-all.js',
            [],
            filemtime(MEDIA_OPTIMIZER_DIR . '/assets/restore-all.js'),
            true
        );
        wp_localize_script('media-optimizer-restore-all', 'mediaOptimizerRestore', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(Bulk_Ajax::NONCE_ACTION),
            'i18n' => [
                'fetchingQueue' => __('Finding images…', 'rear-theme'),
                'processing' => __('Processing %1$d of %2$d…', 'rear-theme'),
                'done' => __('Done — %d images processed.', 'rear-theme'),
                'cancelled' => __('Cancelled.', 'rear-theme'),
                'nothingToDo' => __('Nothing to do — no images have a backup or generated WebP.', 'rear-theme'),
                'restored' => __('Restored', 'rear-theme'),
                'webpRemoved' => __('WebP removed', 'rear-theme'),
                'confirmRestoreAll' => __('Restore every optimized image in the library to exactly what was originally uploaded? This undoes both re-compression and WebP generation everywhere it was applied.', 'rear-theme'),
                'confirmRemoveWebp' => __('Remove the generated WebP files for every image in the library? Visitors will be served the original images again.', 'rear-theme'),
                /* translators: %s: an already-formatted duration, e.g. "2m 30s" */
                'timeRemaining' => __('(~%s remaining)', 'rear-theme'),
            ],
        ]);
        ?>
        <div class="wrap media-optimizer-settings">
            <h1><?php esc_html_e('MediaTrim — Optimize Images', 'rear-theme'); ?></h1>
            <p class="description"><?php esc_html_e('Applies your current settings to images already in the Media Library.', 'rear-theme'); ?></p>

            <?php Stats::render(); ?>

            <div class="media-optimizer-bulk" data-media-optimizer-bulk>
                <label class="media-optimizer-bulk-force">
                    <input type="checkbox" data-bulk-force>
                    <?php esc_html_e('Re-process images that are already optimized too', 'rear-theme'); ?>
                </label>

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
                            <th><?php esc_html_e('Before', 'rear-theme'); ?></th>
                            <th><?php esc_html_e('After', 'rear-theme'); ?></th>
                            <th><?php esc_html_e('Saved', 'rear-theme'); ?></th>
                        </tr>
                    </thead>
                    <tbody data-bulk-results-body></tbody>
                </table>
            </div>

            <h2><?php esc_html_e('Restore / Undo', 'rear-theme'); ?></h2>
            <p class="description"><?php esc_html_e('Undo optimization across the entire library at once. Images with nothing to restore are simply skipped, so this is safe to run at any time.', 'rear-theme'); ?></p>

            <div class="media-optimizer-bulk" data-media-optimizer-restore>
                <div class="media-optimizer-bulk-controls">
                    <button type="button" class="button" data-restore-start="restore_all"><?php esc_html_e('Restore All Images', 'rear-theme'); ?></button>
                    <button type="button" class="button" data-restore-start="remove_webp"><?php esc_html_e('Remove All WebP', 'rear-theme'); ?></button>
                    <button type="button" class="button" data-restore-cancel hidden><?php esc_html_e('Cancel', 'rear-theme'); ?></button>
                </div>

                <div class="media-optimizer-progress" data-restore-progress-wrap hidden>
                    <div class="media-optimizer-progress-bar">
                        <div class="media-optimizer-progress-fill" data-restore-progress-fill style="width: 0%"></div>
                    </div>
                </div>

                <p class="media-optimizer-bulk-status" data-restore-status></p>

                <table class="wp-list-table widefat fixed striped media-optimizer-results" data-restore-results-table hidden>
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Image', 'rear-theme'); ?></th>
                            <th><?php esc_html_e('Result', 'rear-theme'); ?></th>
                        </tr>
                    </thead>
                    <tbody data-restore-results-body></tbody>
                </table>
            </div>
        </div>
        <?php
    }
}
