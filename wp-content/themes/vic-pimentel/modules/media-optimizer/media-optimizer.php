<?php

/**
 * MediaTrim — media optimization module.
 *
 * Deliberately self-contained: everything this folder needs lives inside it,
 * using only WordPress core functions (no theme component()/render() calls),
 * so it can be lifted out into a standalone plugin later with minimal changes.
 * Loaded from the theme's functions.php, gated by MEDIA_OPTIMIZER_ENABLED
 * (app/config.php) — a dev-only kill switch, not a client-facing setting.
 */

if (!defined('ABSPATH')) exit;

if (!defined('MEDIA_OPTIMIZER_ENABLED') || !MEDIA_OPTIMIZER_ENABLED) return;

define('MEDIA_OPTIMIZER_DIR', __DIR__);
define('MEDIA_OPTIMIZER_URL', get_template_directory_uri() . '/modules/media-optimizer');

require_once MEDIA_OPTIMIZER_DIR . '/inc/class-settings.php';
require_once MEDIA_OPTIMIZER_DIR . '/inc/class-capability.php';
require_once MEDIA_OPTIMIZER_DIR . '/inc/class-tracking.php';
require_once MEDIA_OPTIMIZER_DIR . '/inc/class-stats.php';
require_once MEDIA_OPTIMIZER_DIR . '/inc/class-backup-paths.php';
require_once MEDIA_OPTIMIZER_DIR . '/inc/class-converter.php';
require_once MEDIA_OPTIMIZER_DIR . '/inc/class-recompressor.php';
require_once MEDIA_OPTIMIZER_DIR . '/inc/class-thumbnail-regenerator.php';
require_once MEDIA_OPTIMIZER_DIR . '/inc/class-serving.php';
require_once MEDIA_OPTIMIZER_DIR . '/inc/class-upload-hook.php';
require_once MEDIA_OPTIMIZER_DIR . '/inc/class-admin-menu.php';
require_once MEDIA_OPTIMIZER_DIR . '/inc/class-settings-page.php';
require_once MEDIA_OPTIMIZER_DIR . '/inc/class-optimize-page.php';
require_once MEDIA_OPTIMIZER_DIR . '/inc/class-thumbnails-page.php';
require_once MEDIA_OPTIMIZER_DIR . '/inc/class-media-library-ui.php';
require_once MEDIA_OPTIMIZER_DIR . '/inc/class-bulk-ajax.php';
require_once MEDIA_OPTIMIZER_DIR . '/inc/class-thumbnails-ajax.php';
require_once MEDIA_OPTIMIZER_DIR . '/inc/class-admin-bar.php';

// Deferred to 'init' (rather than called immediately at file-require time,
// above) specifically so the "Enable Media Optimizer" field on the Feature
// Toggles options page can be checked here — ACF field data isn't reliably
// available this early in the request. MEDIA_OPTIMIZER_ENABLED above still
// gates whether any of this module's code loads at all; this is a second,
// independent runtime toggle on top of it.
add_action('init', function () {
    if (get_field('feature_media_optimizer', 'option') === false) return;

    MediaOptimizer\Capability::init();
    MediaOptimizer\Serving::init();
    MediaOptimizer\Upload_Hook::init();
    MediaOptimizer\Admin_Menu::init();
    MediaOptimizer\Settings_Page::init();
    MediaOptimizer\Media_Library_UI::init();
    MediaOptimizer\Bulk_Ajax::init();
    MediaOptimizer\Thumbnails_Ajax::init();
    MediaOptimizer\Admin_Bar::init();
});
