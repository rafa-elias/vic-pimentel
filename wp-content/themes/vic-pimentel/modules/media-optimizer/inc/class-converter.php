<?php

namespace MediaOptimizer;

if (!defined('ABSPATH')) exit;

/**
 * Generates WebP siblings for an attachment's original file plus every
 * registered size — additive only (originals are never touched by this
 * class; see class-recompressor.php for the separate, opt-in destructive
 * path). Uses raw Imagick when available — confirmed during this project's
 * earlier optimization work that WP_Image_Editor_Imagick::set_quality() is
 * silently ignored when converting jpg/png to webp (a real WordPress core
 * limitation, not specific to this environment) — falling back to GD's
 * imagewebp() on hosts without Imagick.
 *
 * Reads pixels from Backup_Paths::best_source_for() rather than the live
 * file directly — if Recompressor has already run on this attachment, the
 * live file is itself a lossy recompression, and generating WebP from that
 * would compound two rounds of lossy compression (the same artifact-stacking
 * problem as repeatedly re-saving a JPEG). The pristine backup, when one
 * exists, is always the better source. The webp file's own location and the
 * reported "original size" for savings-% purposes still follow the live
 * path, though — that's the file a non-WebP browser actually receives, so
 * it's the correct baseline to report savings against.
 */
class Converter
{
    const ELIGIBLE_MIMES = ['image/jpeg', 'image/png'];
    const DEFAULT_QUALITY = 75;

    public static function process(int $attachment_id, ?int $quality = null): bool
    {
        $quality = $quality ?? self::DEFAULT_QUALITY;

        $mime = get_post_mime_type($attachment_id);
        if (!in_array($mime, self::ELIGIBLE_MIMES, true)) {
            Tracking::mark_unsupported($attachment_id, "Unsupported mime type: $mime");
            return false;
        }

        $capability = Capability::get();
        if ($capability === 'none') {
            Tracking::mark_unsupported($attachment_id, Capability::missing_message());
            return false;
        }

        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) {
            Tracking::mark_failed($attachment_id, 'Original file not found on disk.');
            return false;
        }

        $webp_urls = [];
        $original_bytes = 0;
        $optimized_bytes = 0;

        $result = self::convert_file($file, $quality, $capability);
        if ($result === null) {
            Tracking::mark_failed($attachment_id, 'Conversion failed for the original file.');
            return false;
        }
        $original_bytes += $result['original_size'];
        $optimized_bytes += $result['webp_size'];
        $webp_urls['full'] = self::path_to_url($result['webp_path']);

        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!empty($metadata['sizes'])) {
            $dir = dirname($file);
            foreach ($metadata['sizes'] as $size_name => $size_data) {
                if (empty($size_data['file'])) continue;

                $size_result = self::convert_file("$dir/{$size_data['file']}", $quality, $capability);
                if ($size_result === null) continue; // one size failing shouldn't fail the whole attachment

                $original_bytes += $size_result['original_size'];
                $optimized_bytes += $size_result['webp_size'];
                $webp_urls[$size_name] = self::path_to_url($size_result['webp_path']);
            }
        }

        $percent = $original_bytes > 0 ? round((1 - $optimized_bytes / $original_bytes) * 100, 1) : 0.0;

        Tracking::mark_optimized($attachment_id, $webp_urls, [
            'original_bytes' => $original_bytes,
            'optimized_bytes' => $optimized_bytes,
            'percent' => $percent,
        ]);

        return true;
    }

    /**
     * @return array{webp_path: string, original_size: int, webp_size: int}|null
     */
    private static function convert_file(string $live_path, int $quality, string $capability): ?array
    {
        if (!file_exists($live_path)) return null;

        $webp_path = self::webp_path_for($live_path);
        if ($webp_path === null) return null;

        $source_path = Backup_Paths::best_source_for($live_path);
        if (!file_exists($source_path)) return null;

        $original_size = filesize($live_path); // the file a non-WebP browser actually receives, not necessarily $source_path

        $success = $capability === 'imagick'
            ? self::convert_with_imagick($source_path, $webp_path, $quality)
            : self::convert_with_gd($source_path, $webp_path, $quality);

        if (!$success || !file_exists($webp_path)) return null;

        return [
            'webp_path' => $webp_path,
            'original_size' => $original_size,
            'webp_size' => filesize($webp_path),
        ];
    }

    private static function webp_path_for(string $path): ?string
    {
        $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);
        return ($webp_path !== $path) ? $webp_path : null;
    }

    private static function convert_with_imagick(string $path, string $webp_path, int $quality): bool
    {
        try {
            $im = new \Imagick($path);
            $im->setImageFormat('webp');
            $im->setImageCompressionQuality($quality);
            $im->writeImage($webp_path);
            $im->clear();
            $im->destroy();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private static function convert_with_gd(string $path, string $webp_path, int $quality): bool
    {
        $mime = wp_check_filetype($path)['type'] ?? '';
        $image = null;

        if ($mime === 'image/jpeg') {
            $image = @imagecreatefromjpeg($path);
        } elseif ($mime === 'image/png') {
            $image = @imagecreatefrompng($path);
            if ($image) {
                // Preserve transparency — without this, PNG alpha channels
                // flatten to black/solid on conversion.
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
            }
        }

        if (!$image) return false;

        $success = imagewebp($image, $webp_path, $quality);
        imagedestroy($image);

        return $success;
    }

    private static function path_to_url(string $path): string
    {
        $upload_dir = wp_get_upload_dir();
        return str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $path);
    }

    /**
     * Deletes every WebP file this class generated for an attachment and
     * resets tracking to 'pending' — the undo path for "Generate WebP" (the
     * additive option). Since that option never touches the original file,
     * there's nothing to restore; removing the WebP siblings and clearing
     * the status is sufficient for Serving to fall back to the original.
     * Reads paths from the tracked URLs rather than re-deriving them, so it
     * only ever deletes files this class actually confirmed it wrote.
     */
    public static function remove(int $attachment_id): void
    {
        $urls = get_post_meta($attachment_id, Tracking::META_WEBP_URLS, true);
        if (is_array($urls)) {
            $upload_dir = wp_get_upload_dir();
            $basedir = realpath($upload_dir['basedir']);

            foreach ($urls as $url) {
                $path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
                $real = realpath($path);

                // Defense in depth: this postmeta isn't writable through any
                // current UI/REST path for a non-admin, but unlink() should
                // never trust a stored value without confirming it actually
                // resolves inside the uploads directory first.
                if ($real && $basedir && str_starts_with($real, $basedir . DIRECTORY_SEPARATOR)) {
                    @unlink($real);
                }
            }
        }

        Tracking::clear($attachment_id);
    }
}
