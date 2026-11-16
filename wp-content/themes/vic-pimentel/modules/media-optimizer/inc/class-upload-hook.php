<?php

namespace MediaOptimizer;

if (!defined('ABSPATH')) exit;

/**
 * Processes a new upload right after it's saved. Confirmed directly (real
 * bug report, not theoretical): relying on WP-Cron alone silently never
 * runs anything on a host with DISABLE_WP_CRON set — which is Pantheon's own
 * standard/recommended configuration, not an edge case. WP-Cron's pseudo-cron
 * only ever fires from an actual page-view checking for due jobs; with that
 * constant on, no number of page visits ever triggers it.
 *
 * Primary mechanism: a `shutdown` callback, releasing the HTTP connection
 * first via fastcgi_finish_request() (available on PHP-FPM — which both this
 * Lando setup and Pantheon run) so the upload appears to finish instantly
 * for the user, then actually running the conversion in that same request's
 * "epilogue". No cron, no extra HTTP round trip, no dependency on anything
 * external. Falls back to running synchronously (blocking the response
 * slightly longer, but still correct) on a host without FPM.
 *
 * The WP-Cron event is still scheduled too, as a harmless safety net — it
 * has no effect on a host with pseudo-cron disabled, but still works if a
 * real system cron is pointed at wp-cron.php directly (disabling the
 * pseudo-cron auto-trigger doesn't disable wp-cron.php's own execution).
 *
 * The admin bar's "Process Now" action (Admin_Bar class) is the manual,
 * always-available override regardless of which of the above did or didn't
 * fire on a given host.
 */
class Upload_Hook
{
    const CRON_HOOK = 'media_optimizer_process_attachment';

    public static function init(): void
    {
        add_filter('wp_generate_attachment_metadata', [self::class, 'schedule_processing'], 10, 2);
        add_action(self::CRON_HOOK, [self::class, 'run_processing']);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function schedule_processing(array $metadata, int $attachment_id): array
    {
        if (!Settings::optimize_on_upload()) return $metadata;

        $mime = get_post_mime_type($attachment_id);
        if (!in_array($mime, Converter::ELIGIBLE_MIMES, true)) return $metadata;

        if (!wp_next_scheduled(self::CRON_HOOK, [$attachment_id])) {
            wp_schedule_single_event(time(), self::CRON_HOOK, [$attachment_id]);
        }

        add_action('shutdown', function () use ($attachment_id): void {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            self::run_processing($attachment_id);
        });

        return $metadata;
    }

    /**
     * Recompression runs first (if enabled) so WebP generation reads
     * whichever bytes end up on disk last — the smallest available source —
     * rather than generating WebP from the pre-recompression original and
     * then recompressing it separately.
     */
    public static function run_processing(int $attachment_id): void
    {
        if (Settings::recompress_originals()) {
            Recompressor::process($attachment_id, Settings::recompress_quality());
        }

        if (Settings::generate_webp()) {
            Converter::process($attachment_id, Settings::webp_quality());
        }

        Admin_Bar::clear_pending_cache();
    }
}
