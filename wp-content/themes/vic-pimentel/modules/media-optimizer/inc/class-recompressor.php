<?php

namespace MediaOptimizer;

if (!defined('ABSPATH')) exit;

/**
 * Re-compresses the original file (and each registered size) in place —
 * genuinely destructive, unlike Converter's additive WebP siblings, so every
 * file is backed up (see Backup_Paths) before it's touched. Off by default;
 * only runs when explicitly enabled (see Upload_Hook). Backups are pure
 * local safety copies, never served publicly, so — unlike WebP serving —
 * checking them via file_exists() is fine even on a site that offloads its
 * public media elsewhere.
 */
class Recompressor
{
    const ELIGIBLE_MIMES = ['image/jpeg', 'image/png'];
    const DEFAULT_QUALITY = 82; // gentler than WebP's default — this may be the only copy left once a backup is cleared

    public static function process(int $attachment_id, ?int $quality = null): bool
    {
        $quality = $quality ?? self::DEFAULT_QUALITY;

        $mime = get_post_mime_type($attachment_id);
        if (!in_array($mime, self::ELIGIBLE_MIMES, true)) {
            Tracking::mark_recompress_unsupported($attachment_id, "Unsupported mime type: $mime");
            return false;
        }

        $capability = Capability::get();
        if ($capability === 'none') {
            Tracking::mark_recompress_unsupported($attachment_id, Capability::missing_message());
            return false;
        }

        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) {
            Tracking::mark_recompress_failed($attachment_id, 'Original file not found on disk.');
            return false;
        }

        // Original must succeed for the attachment to count as recompressed
        // at all — mirrors Converter's own "one size failing doesn't fail
        // the whole attachment, but the main file does" pattern.
        $main_result = self::recompress_file($file, $mime, $quality, $capability);
        if ($main_result === null) {
            Tracking::mark_recompress_failed($attachment_id, 'Re-compression failed for the original file (or its backup could not be created).');
            return false;
        }

        $original_bytes = $main_result['original_size'];
        $optimized_bytes = $main_result['new_size'];

        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!empty($metadata['sizes'])) {
            $dir = dirname($file);
            foreach ($metadata['sizes'] as $size_data) {
                if (empty($size_data['file'])) continue;

                $size_result = self::recompress_file("$dir/{$size_data['file']}", $mime, $quality, $capability);
                if ($size_result === null) continue; // don't fail the whole attachment for one size

                $original_bytes += $size_result['original_size'];
                $optimized_bytes += $size_result['new_size'];
            }
        }

        $percent = $original_bytes > 0 ? round((1 - $optimized_bytes / $original_bytes) * 100, 1) : 0.0;

        Tracking::mark_recompressed($attachment_id, [
            'original_bytes' => $original_bytes,
            'optimized_bytes' => $optimized_bytes,
            'percent' => $percent,
        ]);

        return true;
    }

    /**
     * Reverts the original + every registered size from backup. Also
     * regenerates WebP siblings afterward (if Converter is in use) — they
     * were generated from the recompressed bytes, so restoring the original
     * without refreshing them would leave a stale, inconsistent WebP source.
     */
    public static function restore(int $attachment_id): bool
    {
        $file = get_attached_file($attachment_id);
        if (!$file) return false;

        $paths = [$file];
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!empty($metadata['sizes'])) {
            $dir = dirname($file);
            foreach ($metadata['sizes'] as $size_data) {
                if (!empty($size_data['file'])) $paths[] = "$dir/{$size_data['file']}";
            }
        }

        $restored_any = false;
        foreach ($paths as $path) {
            $backup_path = Backup_Paths::for_path($path);
            if (file_exists($backup_path) && copy($backup_path, $path)) {
                clearstatcache(true, $path); // Converter reads this file's size again right after
                $restored_any = true;
            }
        }

        if (!$restored_any) return false;

        Tracking::mark_restored($attachment_id);

        // Any existing WebP was generated from the (now replaced) recompressed
        // bytes — regenerate from the pristine file, but only if the setting
        // is still on; otherwise leave WebP generation off, same as it would
        // be for a fresh upload.
        if (Settings::generate_webp()) {
            Converter::process($attachment_id, Settings::webp_quality());
        }

        return true;
    }

    public static function has_backup(int $attachment_id): bool
    {
        $file = get_attached_file($attachment_id);
        return $file ? file_exists(Backup_Paths::for_path($file)) : false;
    }

    /**
     * Whether there's a pristine backup actually worth restoring — i.e. a
     * backup exists AND it hasn't already been restored. Shared by every
     * caller that needs to decide whether to show a "Restore Original"-style
     * action (Media_Library_UI, Bulk_Ajax), so the condition only lives once.
     */
    public static function can_restore(int $attachment_id): bool
    {
        return self::has_backup($attachment_id) && Tracking::get_recompress_status($attachment_id) !== 'restored';
    }

    /**
     * Deletes backups for one attachment — always an explicit, deliberate
     * action (never automatic), e.g. once a client has confirmed quality is
     * good and wants to reclaim the disk space.
     */
    public static function delete_backup(int $attachment_id): bool
    {
        $file = get_attached_file($attachment_id);
        if (!$file) return false;

        $paths = [$file];
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!empty($metadata['sizes'])) {
            $dir = dirname($file);
            foreach ($metadata['sizes'] as $size_data) {
                if (!empty($size_data['file'])) $paths[] = "$dir/{$size_data['file']}";
            }
        }

        $deleted_any = false;
        foreach ($paths as $path) {
            $backup_path = Backup_Paths::for_path($path);
            if (file_exists($backup_path)) {
                @unlink($backup_path);
                $deleted_any = true;
            }
        }

        return $deleted_any;
    }

    /**
     * @return array{original_size: int, new_size: int}|null
     */
    private static function recompress_file(string $path, string $mime, int $quality, string $capability): ?array
    {
        if (!file_exists($path)) return null;

        $backup_path = Backup_Paths::for_path($path);
        if (!self::ensure_backup($path, $backup_path)) return null;

        $original_size = filesize($path);

        $success = $capability === 'imagick'
            ? self::recompress_with_imagick($path, $quality)
            : self::recompress_with_gd($path, $mime, $quality);

        if (!$success) {
            copy($backup_path, $path); // don't leave a half-written file in place
            clearstatcache(true, $path);
            return null;
        }

        // Without this, filesize() below returns the pre-write cached value
        // — confirmed directly: Imagick::writeImage() overwriting a path in
        // place leaves PHP's stat cache stale for the rest of the request,
        // silently reporting 0% savings every time.
        clearstatcache(true, $path);

        return [
            'original_size' => $original_size,
            'new_size' => filesize($path),
        ];
    }

    private static function ensure_backup(string $path, string $backup_path): bool
    {
        if (file_exists($backup_path)) return true; // already backed up — e.g. re-running after a quality change

        $backup_dir = dirname($backup_path);
        if (!file_exists($backup_dir) && !wp_mkdir_p($backup_dir)) return false;

        return copy($path, $backup_path);
    }

    private static function recompress_with_imagick(string $path, int $quality): bool
    {
        try {
            $im = new \Imagick($path);
            $im->setImageCompressionQuality($quality);
            $im->writeImage($path);
            $im->clear();
            $im->destroy();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private static function recompress_with_gd(string $path, string $mime, int $quality): bool
    {
        $image = $mime === 'image/jpeg' ? @imagecreatefromjpeg($path) : @imagecreatefrompng($path);
        if (!$image) return false;

        $success = $mime === 'image/jpeg'
            ? imagejpeg($image, $path, $quality)
            : imagepng($image, $path, self::png_quality_to_compression($quality));

        imagedestroy($image);

        return $success;
    }

    /**
     * GD's PNG compression scale is 0 (none) to 9 (max) — inverted from, and
     * a much coarser scale than, a 0-100 "quality" input.
     */
    private static function png_quality_to_compression(int $quality): int
    {
        return (int) round((100 - $quality) / 100 * 9);
    }
}
