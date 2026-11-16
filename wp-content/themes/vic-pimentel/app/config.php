<?php

/**
 * Project settings — loaded before core/ by functions.php.
 * Hardcoded constants (not ACF options) so a client can never toggle them from wp-admin.
 * Core provides client-safe (false) fallbacks if a constant is missing.
 */

// ACF admin UI (Field Groups screen). Set to false on client handoff — fields keep working, only the editing UI disappears.
// Day-to-day toggle also lives on the Feature Toggles options page (both this constant and that field must be
// on for the admin UI to show) — but it always stays visible to FEATURE_TOGGLES_USER_ID regardless of either,
// so that user can never lock themselves out of turning it back on.
define('SHOW_ACF_ADMIN', true);

// Whether src/content/blocks/library/ blocks register. This project ships no library blocks.
define('LOAD_LIBRARY_BLOCKS', false);

// Whether the modules/media-optimizer/ module (WebP generation, on-upload optimization) is active.
// Day-to-day toggle also lives on the Feature Toggles options page — both this constant and that field must be on.
define('MEDIA_OPTIMIZER_ENABLED', true);

// Whether SVG upload support (modules/svg-support.php) is available at all (client favicons).
// Day-to-day toggle also lives on the Feature Toggles options page — both this constant and that field must be on.
define('ALLOW_SVG_UPLOAD', true);

// Whether the Duplicate Post feature (modules/duplicate-post.php) is available at all.
define('ALLOW_DUPLICATE_POST', true);

// Whether the portfolio password gate (modules/portfolio-gate/) can run at all. The day-to-day
// on/off switch, password, cookie lifetime and copy live on Site Settings → Portfolio Access.
define('PORTFOLIO_GATE_ENABLED', true);

// Unicorn Studio runtime used to render the animated backgrounds (Site Settings → Animations
// can override it). Pinned version — bump deliberately after testing the scenes.
define('VP_UNICORN_SDK', 'https://cdn.jsdelivr.net/gh/hiunicornstudio/unicornstudio.js@v2.6.0/dist/unicornStudio.umd.js');

// Which user ID(s) see the "Feature Toggles" options page — a single ID (int) or an array of IDs.
define('FEATURE_TOGGLES_USER_ID', 1);
