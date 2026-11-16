<?php

namespace MediaOptimizer;

if (!defined('ABSPATH')) exit;

/**
 * Per-attachment optimization status, stored as postmeta — deliberately the
 * source of truth instead of file_exists() checks, so serving still works on
 * a site that offloads media to S3/CDN after upload (files removed from
 * local disk, but the DB record persists).
 */
class Tracking
{
    const META_STATUS = '_media_optimizer_status';
    const META_WEBP_URLS = '_media_optimizer_webp_urls'; // ['full' => url, 'thumbnail' => url, ...]
    const META_SAVINGS = '_media_optimizer_savings';      // ['original_bytes' => int, 'optimized_bytes' => int, 'percent' => float]
    const META_TIMESTAMP = '_media_optimizer_timestamp';
    const META_ERROR = '_media_optimizer_error';

    // Recompression (option B) is tracked entirely separately from WebP
    // generation (option A, above) — the two features are independently
    // toggleable, and an attachment can be in either, both, or neither state.
    const META_RECOMPRESS_STATUS = '_media_optimizer_recompress_status';
    const META_RECOMPRESS_SAVINGS = '_media_optimizer_recompress_savings';
    const META_RECOMPRESS_TIMESTAMP = '_media_optimizer_recompress_timestamp';
    const META_RECOMPRESS_ERROR = '_media_optimizer_recompress_error';

    /**
     * @return string One of 'optimized', 'failed', 'unsupported', 'pending'.
     */
    public static function get_status(int $attachment_id): string
    {
        $status = get_post_meta($attachment_id, self::META_STATUS, true);
        return $status ?: 'pending';
    }

    public static function is_optimized(int $attachment_id): bool
    {
        return self::get_status($attachment_id) === 'optimized';
    }

    /**
     * @param string $size Registered size name, or 'full' for the original.
     */
    public static function get_webp_url(int $attachment_id, string $size = 'full'): ?string
    {
        $urls = get_post_meta($attachment_id, self::META_WEBP_URLS, true);
        if (!is_array($urls) || empty($urls[$size])) return null;
        return $urls[$size];
    }

    /**
     * Confirms a WebP file actually exists for this *specific* URL (any
     * registered size, not just 'full') — deliberately not a blind
     * extension-swap-and-hope. Converter can mark an attachment 'optimized'
     * even when one individual size failed (the original/full conversion is
     * what gates success), so serving code must verify the exact URL was
     * recorded as converted, not just that the attachment as a whole is
     * optimized — otherwise a failed thumbnail-size conversion would still
     * get its <img> src swapped to a .webp file that was never generated.
     */
    public static function get_webp_for_url(int $attachment_id, string $original_url): ?string
    {
        $urls = get_post_meta($attachment_id, self::META_WEBP_URLS, true);
        if (!is_array($urls) || empty($urls)) return null;

        $candidate = preg_replace('/\.(jpe?g|png)(\?.*)?$/i', '.webp$2', $original_url);
        if ($candidate === $original_url) return null; // no matching extension

        return in_array($candidate, $urls, true) ? $candidate : null;
    }

    public static function get_savings(int $attachment_id): ?array
    {
        $savings = get_post_meta($attachment_id, self::META_SAVINGS, true);
        return is_array($savings) ? $savings : null;
    }

    public static function get_error(int $attachment_id): string
    {
        return (string) get_post_meta($attachment_id, self::META_ERROR, true);
    }

    /**
     * @param array<string, string> $webp_urls Size name => WebP URL.
     * @param array{original_bytes:int, optimized_bytes:int, percent:float} $savings
     */
    public static function mark_optimized(int $attachment_id, array $webp_urls, array $savings): void
    {
        update_post_meta($attachment_id, self::META_STATUS, 'optimized');
        update_post_meta($attachment_id, self::META_WEBP_URLS, $webp_urls);
        update_post_meta($attachment_id, self::META_SAVINGS, $savings);
        update_post_meta($attachment_id, self::META_TIMESTAMP, time());
        delete_post_meta($attachment_id, self::META_ERROR);
    }

    public static function mark_failed(int $attachment_id, string $reason): void
    {
        update_post_meta($attachment_id, self::META_STATUS, 'failed');
        update_post_meta($attachment_id, self::META_ERROR, $reason);
        update_post_meta($attachment_id, self::META_TIMESTAMP, time());
    }

    public static function mark_unsupported(int $attachment_id, string $reason): void
    {
        update_post_meta($attachment_id, self::META_STATUS, 'unsupported');
        update_post_meta($attachment_id, self::META_ERROR, $reason);
        update_post_meta($attachment_id, self::META_TIMESTAMP, time());
    }

    /**
     * Wipes tracking data — used when re-processing from scratch (a quality
     * setting changed) or when cleaning up generated files.
     */
    public static function clear(int $attachment_id): void
    {
        delete_post_meta($attachment_id, self::META_STATUS);
        delete_post_meta($attachment_id, self::META_WEBP_URLS);
        delete_post_meta($attachment_id, self::META_SAVINGS);
        delete_post_meta($attachment_id, self::META_TIMESTAMP);
        delete_post_meta($attachment_id, self::META_ERROR);
    }

    // --- Recompression (option B) ---

    /**
     * @return string One of 'recompressed', 'restored', 'failed', 'unsupported', 'pending'.
     */
    public static function get_recompress_status(int $attachment_id): string
    {
        $status = get_post_meta($attachment_id, self::META_RECOMPRESS_STATUS, true);
        return $status ?: 'pending';
    }

    public static function is_recompressed(int $attachment_id): bool
    {
        return self::get_recompress_status($attachment_id) === 'recompressed';
    }

    public static function get_recompress_savings(int $attachment_id): ?array
    {
        $savings = get_post_meta($attachment_id, self::META_RECOMPRESS_SAVINGS, true);
        return is_array($savings) ? $savings : null;
    }

    public static function get_recompress_error(int $attachment_id): string
    {
        return (string) get_post_meta($attachment_id, self::META_RECOMPRESS_ERROR, true);
    }

    /**
     * @param array{original_bytes:int, optimized_bytes:int, percent:float} $savings
     */
    public static function mark_recompressed(int $attachment_id, array $savings): void
    {
        update_post_meta($attachment_id, self::META_RECOMPRESS_STATUS, 'recompressed');
        update_post_meta($attachment_id, self::META_RECOMPRESS_SAVINGS, $savings);
        update_post_meta($attachment_id, self::META_RECOMPRESS_TIMESTAMP, time());
        delete_post_meta($attachment_id, self::META_RECOMPRESS_ERROR);
    }

    public static function mark_restored(int $attachment_id): void
    {
        update_post_meta($attachment_id, self::META_RECOMPRESS_STATUS, 'restored');
        delete_post_meta($attachment_id, self::META_RECOMPRESS_SAVINGS);
        update_post_meta($attachment_id, self::META_RECOMPRESS_TIMESTAMP, time());
        delete_post_meta($attachment_id, self::META_RECOMPRESS_ERROR);
    }

    public static function mark_recompress_failed(int $attachment_id, string $reason): void
    {
        update_post_meta($attachment_id, self::META_RECOMPRESS_STATUS, 'failed');
        update_post_meta($attachment_id, self::META_RECOMPRESS_ERROR, $reason);
        update_post_meta($attachment_id, self::META_RECOMPRESS_TIMESTAMP, time());
    }

    public static function mark_recompress_unsupported(int $attachment_id, string $reason): void
    {
        update_post_meta($attachment_id, self::META_RECOMPRESS_STATUS, 'unsupported');
        update_post_meta($attachment_id, self::META_RECOMPRESS_ERROR, $reason);
        update_post_meta($attachment_id, self::META_RECOMPRESS_TIMESTAMP, time());
    }
}
