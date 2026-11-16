<?php

/**
 * Pre-register theme-app-script (and admin-initializer) before blocks register
 * on `init`, since block scripts depend on theme-app-script. Deps must be correct
 * here — WP won't overwrite them on a later re-registration.
 */
add_action('init', function (): void {
    $theme_app_deps = [];
    $theme_app_version = file_exists(get_template_directory() . '/public/app.js') ? filemtime(get_template_directory() . '/public/app.js') : null;

    if (is_admin()) {
        wp_register_script('admin-initializer', get_template_directory_uri() . '/src/scripts/admin/initializer.js', ['jquery'], '', ['in_footer' => true, 'strategy' => 'defer']);
        $theme_app_deps[] = 'admin-initializer';
    }

    wp_register_script('theme-app-script', get_template_directory_uri() . '/public/app.js', $theme_app_deps, $theme_app_version, true);
}, 5);

/**
 * Enqueue frontend assets
 */
function theme_enqueue_frontend_assets(): void
{
    $app_js = get_template_directory() . '/public/app.js';

    wp_enqueue_script('theme-app-script', get_template_directory_uri() . '/public/app.js', [], file_exists($app_js) ? filemtime($app_js) : null, true);

    // Frontend AJAX endpoint — mirrors admin-initializer's adminAjax, but that
    // script only enqueues in wp-admin. Any block/component JS doing an AJAX
    // call on the frontend reads window.themeAjax.ajaxUrl / .nonce. Handlers
    // live in ajax/ (auto-required by functions.php) and must verify this
    // nonce via check_ajax_referer('theme_ajax', 'nonce').
    wp_localize_script('theme-app-script', 'themeAjax', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('theme_ajax'),
        'singularPostId' => is_singular('post') && !current_user_can('edit_posts') ? get_the_ID() : null, // staff views don't count
    ]);
}

add_action('wp_enqueue_scripts', 'theme_enqueue_frontend_assets');

/**
 * Enqueue assets to Admin only
 *
 * @return void
 */
function theme_enqueue_admin_assets(): void
{
    // Admin css
    wp_enqueue_style('style-admin-acf', get_template_directory_uri() . '/public/admin/acf.css');

    // Admin initializer
    wp_enqueue_script('admin-initializer', get_template_directory_uri() . '/src/scripts/admin/initializer.js', ['jquery'], '', ['in_footer' => true, 'strategy' => 'defer']);
    wp_localize_script('admin-initializer', 'initializerData', ['isAdmin' => is_admin(), 'adminAjax' => admin_url('admin-ajax.php')]);

    // App js
    $app_js = get_template_directory() . '/public/app.js';
    wp_enqueue_script('theme-app-script', get_template_directory_uri() . '/public/app.js', ['admin-initializer'], file_exists($app_js) ? filemtime($app_js) : null, true);

    // Block Preview hack.
    wp_enqueue_script('block-preview', get_template_directory_uri() . '/src/scripts/admin/block-preview.js', ['wp-blocks', 'wp-element', 'wp-hooks'], '', true);
    wp_localize_script('block-preview', 'blockPreview', [
        'theme_directory' => get_template_directory_uri()
    ]);
}

add_action('admin_enqueue_scripts', 'theme_enqueue_admin_assets');

/**
 * Enqueue the theme's stylesheets inside the block editor's canvas iframe.
 * admin_enqueue_scripts only reaches the outer admin document; enqueue_block_assets
 * is what WP forwards into the iframe. Styles only — the iframe has no window.theme.
 *
 * @return void
 */
function theme_enqueue_editor_iframe_styles(): void
{
    if (!is_admin()) {
        return;
    }

    $dir = get_template_directory();
    $dir_uri = get_template_directory_uri();

    $app_css = $dir . '/public/app.css';
    wp_enqueue_style('style-app', $dir_uri . '/public/app.css', [], file_exists($app_css) ? filemtime($app_css) : null);

    theme_dir_assets('component', $dir . '/public/components', $dir_uri . '/public/components', false, library_component_names(), true);
    theme_dir_assets('section', $dir . '/public/sections', $dir_uri . '/public/sections', false, [], true);
    theme_dir_assets('static', $dir . '/public/static', $dir_uri . '/public/static', false, [], true);

    // Canvas-only corrections to the editor's own chrome.
    $canvas_css = $dir . '/public/admin/editor-canvas.css';
    if (file_exists($canvas_css)) {
        wp_enqueue_style('style-editor-canvas', $dir_uri . '/public/admin/editor-canvas.css', [], filemtime($canvas_css));
    }
}

add_action('enqueue_block_assets', 'theme_enqueue_editor_iframe_styles');

/**
 * Register or enqueue CSS and JS files from a public directory.
 * Caches glob() results per directory to avoid repeated filesystem calls.
 *
 * @param string $dir_key Handle prefix (e.g. 'component', 'section')
 * @param string $dir_path Absolute filesystem path to the directory
 * @param string $dir_uri Public URI to the directory
 * @param bool $register_css If true, register CSS without enqueueing (lazy load via render_component)
 * @param string[] $exclude Names to skip entirely (e.g. library/ components that shouldn't ship)
 * @param bool $skip_js If true, only handle CSS (editor canvas iframe)
 */
function theme_dir_assets(string $dir_key, string $dir_path, string $dir_uri, bool $register_css = false, array $exclude = [], bool $skip_js = false): void
{
    static $cache = [];

    if (!isset($cache[$dir_key])) {
        $cache[$dir_key] = [
            'css' => glob($dir_path . '/*.css') ?: [],
            'js' => glob($dir_path . '/*.js') ?: [],
        ];
    }

    foreach ($cache[$dir_key]['css'] as $file) {
        $name = pathinfo($file, PATHINFO_FILENAME);
        if (in_array($name, $exclude, true)) continue;

        $handle = "style-{$dir_key}-{$name}";
        $version = filemtime($file) ?: null;
        $url = $dir_uri . '/' . basename($file);

        if ($register_css) {
            wp_register_style($handle, $url, [], $version);
        } else {
            wp_enqueue_style($handle, $url, [], $version);
        }
    }

    if ($skip_js) {
        return;
    }

    foreach ($cache[$dir_key]['js'] as $file) {
        $name = pathinfo($file, PATHINFO_FILENAME);
        if (in_array($name, $exclude, true)) continue;

        $version = filemtime($file) ?: null;
        wp_enqueue_script(
            "script-{$dir_key}-{$name}",
            $dir_uri . '/' . basename($file),
            [],
            $version,
            true
        );
    }
}

/**
 * Print component styles enqueued during block rendering (after wp_head).
 */
function theme_print_late_styles(): void
{
    wp_print_styles();
}

/**
 * Enqueue Frontend and admin assets
 *
 * @return void
 */
function theme_enqueue_assets(): void
{
    $dir = get_template_directory();
    $dir_uri = get_template_directory_uri();

    // Enqueue app.css
    $app_css = $dir . '/public/app.css';
    wp_enqueue_style('style-app', $dir_uri . '/public/app.css', [], file_exists($app_css) ? filemtime($app_css) : null);

    // Frontend: register only — enqueued on demand in render_component() and printed in wp_footer
    // Admin: always enqueue — block preview renders via REST API (separate PHP context), so
    // render_component() enqueues can't reach the editor page's <head>
    // Components still sitting in components/library are reference-only and excluded —
    // they haven't been chosen for this project, so their JS/CSS shouldn't ship.
    theme_dir_assets('component', $dir . '/public/components', $dir_uri . '/public/components', !is_admin(), library_component_names());

    // Header renders above the fold on every page (unlike modal/mega-menu, it has
    // no hidden-by-default state a small critical-CSS duplicate could stand in for),
    // so lazy-loading its real stylesheet in wp_footer means it always paints
    // unstyled first. Enqueue it eagerly here instead, so it prints in <head>
    // alongside app.css and loads in time for first paint.
    // button.css goes with it: header.php always renders its CTA(s) via the
    // button component, so a lazy-loaded button.css left it flashing as a bare
    // unstyled link once the header around it started painting correctly.
    if (!is_admin()) {
        wp_enqueue_style('style-component-header');
        wp_enqueue_style('style-component-button');
        // Above-the-fold on every template: animated backgrounds + project media.
        wp_enqueue_style('style-component-scene');
        wp_enqueue_style('style-component-media');

        // Template stylesheets (src/styles/templates → public/templates).
        $template_css = match (true) {
            is_singular('post') => 'single',
            is_404() => 'not-found',
            default => null,
        };

        if ($template_css && file_exists($dir . "/public/templates/$template_css.css")) {
            wp_enqueue_style("style-template-$template_css", $dir_uri . "/public/templates/$template_css.css", ['style-app'], filemtime($dir . "/public/templates/$template_css.css"));
        }
    }

    // Enqueue sections css and js
    theme_dir_assets('section', $dir . '/public/sections', $dir_uri . '/public/sections');

    // Enqueue static css and js
    // fonts.css is self-hosted (@font-face rules pointing at src/assets/fonts/, no
    // external request) as of the WebP/font-performance pass — no need to special-case
    // it out of the frontend path anymore, it's just a normal small stylesheet now.
    theme_dir_assets('static', $dir . '/public/static', $dir_uri . '/public/static');

    // ember.config.js (GSAP + ember-js + entrance animations, ~128KB) was a plain
    // classic footer script — no defer — so DOMContentLoaded, and therefore every
    // element common/_critical-overlays.scss pre-hides to opacity:0, waited on its
    // full download+execution. Deferring lets the browser fetch it in parallel
    // during HTML parsing instead of blocking on it at the point it's encountered.
    wp_script_add_data('script-static-ember.config', 'strategy', 'defer');

    // Print any component styles enqueued after wp_head during block rendering
    add_action('wp_footer', 'theme_print_late_styles', 5);
}

add_action('wp_enqueue_scripts', 'theme_enqueue_assets');
add_action('admin_enqueue_scripts', function (string $hook): void {
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }
    theme_enqueue_assets();
});

/**
 * Contact Form 7 enqueues its script/style globally on every page by default,
 * regardless of whether the page actually has a form on it. Dequeue them when the
 * current page's content has none — ACF Blocks serialize their field data (including
 * a raw shortcode string like [contact-form-7 ...]) directly into post_content, not
 * just postmeta, so has_shortcode() against $post->post_content is a reliable, cheap
 * check here. Priority 20 so it runs after CF7's own enqueue (default priority 10).
 * Bails (keeps CF7 loaded) whenever it isn't sure — no $post, or the shortcode is
 * found — rather than risk breaking a form.
 */
add_action('wp_enqueue_scripts', function (): void {
    if (!is_singular()) return;

    global $post;
    if (!$post || has_shortcode($post->post_content, 'contact-form-7')) return;

    wp_dequeue_script('contact-form-7');
    wp_dequeue_script('swv');
    wp_dequeue_style('contact-form-7');
}, 20);

/**
 * Exclude certain post types or templates from loading admin CSS
 *
 * @param string $hook_suffix The current admin page hook suffix.
 * @return void
 */
function exclude_custom_css_based_on_template($hook_suffix)
{
    if ($hook_suffix === 'post.php' || $hook_suffix === 'post-new.php') {
        global $post;

        if (isset($post)) {
            $post_type = get_post_type($post);

            $excluded_post_types = ['custom_excluded_post_type_1'];

            if (in_array($post_type, $excluded_post_types)) {
                return;
            }

            $template = get_page_template_slug($post->ID);

            $excluded_templates = [
                'templates/custom_excluded_template.php',
            ];

            if (in_array($template, $excluded_templates)) {
                return;
            }

            wp_enqueue_style('style-admin-blocks', get_template_directory_uri() . '/public/admin/blocks.css');
        }
    }
}

add_action('admin_enqueue_scripts', 'exclude_custom_css_based_on_template');
