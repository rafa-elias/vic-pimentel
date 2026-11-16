<?php

/**
 * Require all PHP files in the given directory and its subdirectories
 */
function require_files($directory) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            require_once $file->getPathname();
        }
    }
}

// Project settings must load before core so its constants override core's fallbacks
require_once get_template_directory() . '/app/config.php';

// core/, app/, and the native modules all lean on get_field()/acf_* being defined
// (blocks, options, CPTs, menus, SVG upload, duplicate post, media optimizer) —
// without ACF (free or pro) active, loading any of it fatals on every request,
// admin included. Gate the whole layer on ACF's presence rather than guarding
// each call site individually.
if (class_exists('ACF')) {
    // Require core first
    require_once get_template_directory() . '/core/core.php';

    // Require files from core folder
    require_files(get_template_directory() . '/core');

    // Require files from app folder — includes app/ajax/, site-wide AJAX
    // handlers that self-register their own wp_ajax_{action} hooks (see
    // CLAUDE.md's "AJAX endpoints" section). Block-specific AJAX handlers
    // live inside their own block folder instead and are required by
    // scan_and_register_blocks() (core/blocks.php).
    require_files(get_template_directory() . '/app');

    // Self-contained modules — each gated by its own constant, never blindly
    // auto-required like core/app (would double-load their internal includes)
    require_once get_template_directory() . '/modules/svg-support.php';
    require_once get_template_directory() . '/modules/duplicate-post.php';
    require_once get_template_directory() . '/modules/media-optimizer/media-optimizer.php';
    require_once get_template_directory() . '/modules/portfolio-gate/portfolio-gate.php';
}
