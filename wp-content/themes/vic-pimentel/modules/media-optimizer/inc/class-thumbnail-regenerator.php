<?php

namespace MediaOptimizer;

if (!defined('ABSPATH')) exit;

/**
 * Regenerates every registered image size for an attachment, fresh from the
 * original — the classic "Regenerate Thumbnails" plugin functionality,
 * built in natively so it doesn't depend on a separate plugin. Useful
 * whenever registered image sizes change (a new size added, an existing
 * one's dimensions edited) and existing attachments' generated files need
 * to catch up.
 *
 * If this attachment was ever recompressed (Recompressor), sizes are
 * generated from the pristine backup, not the lossy recompressed copy —
 * same reasoning as Converter reading from Backup_Paths. Since
 * wp_generate_attachment_metadata() writes new size files into the same
 * directory as whatever path it's given, and that must be the live uploads
 * directory (not the backups folder) for the new files to end up in the
 * right place, the pristine bytes are temporarily restored in place first,
 * then re-recompressed afterward if that setting is still on — not read
 * directly from the backup path itself.
 */
class Thumbnail_Regenerator
{
    const ELIGIBLE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /**
     * @return array{status: string, regenerated_sizes: string[], deleted_sizes: string[]}
     */
    public static function process(int $attachment_id, bool $skip_if_correct = true, bool $delete_stale = false): array
    {
        $empty_result = ['regenerated_sizes' => [], 'deleted_sizes' => []];

        if ($skip_if_correct && !self::needs_regeneration($attachment_id)) {
            return ['status' => 'skipped'] + $empty_result;
        }

        $mime = get_post_mime_type($attachment_id);
        if (!in_array($mime, self::ELIGIBLE_MIMES, true)) return ['status' => 'failed'] + $empty_result;

        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) return ['status' => 'failed'] + $empty_result;

        $backup = Backup_Paths::for_path($file);
        if (file_exists($backup)) {
            copy($backup, $file);
            clearstatcache(true, $file);
        }

        $old_metadata = wp_get_attachment_metadata($attachment_id);

        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $metadata = wp_generate_attachment_metadata($attachment_id, $file);
        if (empty($metadata)) return ['status' => 'failed'] + $empty_result;

        wp_update_attachment_metadata($attachment_id, $metadata);

        $regenerated_sizes = self::diff_regenerated_sizes($old_metadata, $metadata);

        $deleted_sizes = $delete_stale
            ? self::delete_stale_files($old_metadata, $metadata, dirname($file))
            : [];

        // Restore the configured on-disk state now that regeneration (which
        // needed the pristine bytes) is done.
        if (Settings::recompress_originals()) {
            Recompressor::process($attachment_id, Settings::recompress_quality());
        }

        if (Settings::generate_webp()) {
            Converter::process($attachment_id, Settings::webp_quality());
        }

        return [
            'status' => 'regenerated',
            'regenerated_sizes' => $regenerated_sizes,
            'deleted_sizes' => $deleted_sizes,
        ];
    }

    /**
     * Which registered size names actually ended up with different
     * dimensions/filenames after regeneration — reported back to the admin
     * so a run's results show exactly what changed per image, not just a
     * pass/fail for the whole attachment.
     *
     * @return string[]
     */
    private static function diff_regenerated_sizes(array $old_metadata, array $new_metadata): array
    {
        $changed = [];

        foreach ($new_metadata['sizes'] ?? [] as $name => $size) {
            $old = $old_metadata['sizes'][$name] ?? null;

            if (
                !$old
                || $old['file'] !== $size['file']
                || (int) $old['width'] !== (int) $size['width']
                || (int) $old['height'] !== (int) $size['height']
            ) {
                $changed[] = $name;
            }
        }

        return $changed;
    }

    /**
     * Checks every currently-registered size against what's actually
     * recorded/on disk for this attachment — used by the "skip already
     * correctly sized" option so a bulk re-run only touches images that
     * genuinely need it (a new size added, an existing one's dimensions
     * changed, a file missing), matching the reference plugin's behavior
     * rather than unconditionally reprocessing the whole library every time.
     */
    public static function needs_regeneration(int $attachment_id): bool
    {
        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) return true;

        $metadata = wp_get_attachment_metadata($attachment_id);
        if (empty($metadata['width']) || empty($metadata['height'])) return true;

        $orig_w = (int) $metadata['width'];
        $orig_h = (int) $metadata['height'];
        $dir = dirname($file);

        $registered = function_exists('wp_get_registered_image_subsizes') ? wp_get_registered_image_subsizes() : [];

        foreach ($registered as $name => $size) {
            $expected = image_resize_dimensions($orig_w, $orig_h, $size['width'], $size['height'], $size['crop']);

            if ($expected === false) {
                // Original is too small for this size — WP wouldn't generate
                // one at all, so a stale leftover here means it needs a pass.
                if (!empty($metadata['sizes'][$name])) return true;
                continue;
            }

            [, , , , $expected_w, $expected_h] = $expected;

            if (empty($metadata['sizes'][$name])) return true;

            $current = $metadata['sizes'][$name];
            if ((int) $current['width'] !== $expected_w || (int) $current['height'] !== $expected_h) return true;

            if (empty($current['file']) || !file_exists("$dir/{$current['file']}")) return true;
        }

        return false;
    }

    /**
     * Deletes generated size files left over from before regeneration that
     * are no longer part of the fresh metadata — covers both a size being
     * removed from registration entirely, and a size's target dimensions
     * changing (which produces a differently-named file, per WP's
     * dimension-suffixed naming convention, leaving the old one orphaned).
     * Opt-in (default off) since a file no longer referenced by fresh
     * metadata may still be hotlinked/cached elsewhere — same reasoning the
     * reference plugin's own warning gives.
     */
    /**
     * @return string[] Size names whose stale files were actually deleted.
     */
    private static function delete_stale_files(array $old_metadata, array $new_metadata, string $dir): array
    {
        if (empty($old_metadata['sizes'])) return [];

        $new_files = [];
        foreach ($new_metadata['sizes'] ?? [] as $size) {
            if (!empty($size['file'])) $new_files[$size['file']] = true;
        }

        $deleted = [];
        foreach ($old_metadata['sizes'] as $name => $size) {
            if (empty($size['file']) || isset($new_files[$size['file']])) continue;

            $path = "$dir/{$size['file']}";
            if (file_exists($path)) {
                @unlink($path);
                $deleted[] = $name;
            }

            // A stale size's WebP sibling (if MediaTrim generated one) is
            // just as orphaned as the size file itself — otherwise it's
            // left behind on disk with nothing left pointing at it.
            $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);
            if ($webp_path !== $path && file_exists($webp_path)) {
                @unlink($webp_path);
            }
        }

        return $deleted;
    }
}
