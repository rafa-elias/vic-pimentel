<?php

namespace MediaOptimizer;

if (!defined('ABSPATH')) exit;

/**
 * Shared "images optimized / total space saved" summary — shown on both the
 * Settings and Optimize Images pages, so it's its own class rather than
 * living on whichever page happened to need it first.
 */
class Stats
{
    const TRANSIENT = 'media_optimizer_stats';

    public static function get(): array
    {
        $cached = get_transient(self::TRANSIENT);
        if (is_array($cached)) return $cached;

        $ids = get_posts([
            'post_type' => 'attachment',
            'posts_per_page' => -1,
            'post_status' => 'inherit',
            'fields' => 'ids',
            'meta_key' => Tracking::META_STATUS,
            'meta_value' => 'optimized',
        ]);

        $saved_bytes = 0;
        foreach ($ids as $id) {
            $savings = Tracking::get_savings($id);
            if ($savings) $saved_bytes += ($savings['original_bytes'] - $savings['optimized_bytes']);
        }

        $stats = ['count' => count($ids), 'saved_bytes' => $saved_bytes];
        set_transient(self::TRANSIENT, $stats, HOUR_IN_SECONDS);

        return $stats;
    }

    public static function clear_cache(): void
    {
        delete_transient(self::TRANSIENT);
    }

    /**
     * Pre-formatted (locale-aware) values for the AJAX bulk tools to push
     * into the stats cards live, after each batch — so JS doesn't need to
     * reimplement number_format_i18n()/size_format() and risk drifting from
     * how the page itself renders them.
     */
    public static function get_formatted(): array
    {
        $stats = self::get();

        return [
            'count' => number_format_i18n($stats['count']),
            'saved' => size_format($stats['saved_bytes']),
        ];
    }

    /**
     * Renders the two-card summary block — identical markup on both pages.
     */
    public static function render(): void
    {
        $stats = self::get();
        ?>
        <div class="media-optimizer-stats" data-media-optimizer-stats>
            <div class="media-optimizer-stat">
                <span class="media-optimizer-stat-value" data-stat-count><?php echo esc_html(number_format_i18n($stats['count'])); ?></span>
                <span class="media-optimizer-stat-label"><?php esc_html_e('Images optimized', 'rear-theme'); ?></span>
            </div>
            <div class="media-optimizer-stat">
                <span class="media-optimizer-stat-value" data-stat-saved><?php echo esc_html(size_format($stats['saved_bytes'])); ?></span>
                <span class="media-optimizer-stat-label"><?php esc_html_e('Total space saved', 'rear-theme'); ?></span>
            </div>
        </div>
        <?php
    }
}
