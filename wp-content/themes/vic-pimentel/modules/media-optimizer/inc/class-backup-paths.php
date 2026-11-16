<?php

namespace MediaOptimizer;

if (!defined('ABSPATH')) exit;

/**
 * Shared backup-path convention — used by Recompressor (which writes
 * backups before recompressing) and Converter (which reads from a backup
 * when one exists, so WebP is always generated from the best available
 * source rather than an already-recompressed, lossy file). Kept as its own
 * class so neither of those two depends on the other just for this.
 *
 * Backups live at wp-content/uploads/media-optimizer-backups/, mirroring
 * the real file's relative path — inside uploads specifically, not
 * wp-content root, since uploads is the one path guaranteed writable on
 * every host (confirmed: Pantheon's production tier locks down everything
 * else in wp-content).
 */
class Backup_Paths
{
    const SUBDIR = 'media-optimizer-backups';

    public static function for_path(string $path): string
    {
        $upload_dir = wp_get_upload_dir();
        $relative = str_replace($upload_dir['basedir'], '', $path);
        return $upload_dir['basedir'] . '/' . self::SUBDIR . $relative;
    }

    /**
     * The best available source to read pixels from: the pristine backup if
     * one exists (meaning $path itself has since been recompressed and is no
     * longer the highest-quality copy available), otherwise $path itself.
     */
    public static function best_source_for(string $path): string
    {
        $backup = self::for_path($path);
        return file_exists($backup) ? $backup : $path;
    }
}
