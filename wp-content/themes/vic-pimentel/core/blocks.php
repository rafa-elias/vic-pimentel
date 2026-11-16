<?php

// Client-safe fallback — the real value is set in app/config.php, loaded before core.
defined('LOAD_LIBRARY_BLOCKS') || define('LOAD_LIBRARY_BLOCKS', false);

/**
 * Whether library/ blocks register — gated by LOAD_LIBRARY_BLOCKS and the
 * "Enable Library Blocks" field on the Feature Toggles options page (both
 * must be on).
 */
function library_blocks_enabled(): bool
{
    if (!LOAD_LIBRARY_BLOCKS) return false;

    return get_field('feature_library_blocks', 'option') !== false;
}

/**
 * Generate block json for ACF Blocks
 *
 * @param $block_name
 * @param $block_dir
 * @param $json_args
 * @return void
 */
function generate_block_json($block_name, $block_dir, $json_args): void
{
    // Do not generate if wp debug is not set to true to avoid admin errors.
    // Lando (local) always generates — its Pantheon config never loads wp-config-local.php.
    if (!WP_DEBUG && getenv('LANDO') !== 'ON') return;

    // Return early if no arguments were provided.
    if (empty($block_name) || empty($block_dir) || empty($json_args)) {
        return;
    }

    // Default args;
    $default_args = [
        'supports' => [
            'align' => false,
            'jsx' => true,
            'customClassName' => true,
            'anchor' => true,
            'reusable' => true,
            'renaming' => false,
        ],
        'acf' => [
            'mode' => 'preview',
            'renderTemplate' => $block_name . '.php',
        ],
    ];

    // Merge args.
    $args = recursive_array_merge($default_args, $json_args);

    // Generate block.json.
    $json_file = $block_dir . '/block.json';
    $json_data = json_encode($args, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $tmp_file = $json_file . '.' . uniqid('', true) . '.tmp';
    file_put_contents($tmp_file, $json_data);
    rename($tmp_file, $json_file);
}

/**
 * Recursively find all block directories under $dir — a block directory is
 * one containing a PHP file matching its own folder name. Category folders
 * (library, custom, inner-blocks, wrappers) and any subcategory folders
 * nested under them (e.g. library/accordion) are walked but never treated
 * as blocks themselves — only leaf directories with a matching PHP file are.
 *
 * @param string $dir
 * @return string[] Absolute paths to block directories.
 */
function scan_block_dirs(string $dir): array
{
    $found = [];
    $entries = array_filter(glob($dir . '/*', GLOB_ONLYDIR) ?: [], 'is_dir');

    foreach ($entries as $entry) {
        $name = basename($entry);

        if (file_exists($entry . '/' . $name . '.php')) {
            $found[] = $entry;
            continue;
        }

        $found = array_merge($found, scan_block_dirs($entry));
    }

    return $found;
}

/**
 * Register all ACF Blocks
 *
 * @return void
 */
function scan_and_register_blocks(): void
{
    // Vars
    $blocks_dir = block_path();
    $blocks = scan_block_dirs($blocks_dir);

    // Loop through blocks
    foreach ($blocks as $block_dir) {
        $config = [];
        $block_name = basename($block_dir);
        $config_file = $block_dir . '/config.php';
        $css_file = get_template_directory() . '/public/blocks/' . $block_name . '.css';
        $js_file = get_template_directory() . '/public/blocks/' . $block_name . '.js';

        // Block's path relative to the blocks root (e.g. 'library/text-1' or, with
        // subcategory folders, 'library/accordion/accordion-1') — the category is
        // always its first segment, regardless of nesting depth.
        $relative_dir = trim(substr($block_dir, strlen($blocks_dir)), '/');
        $category = explode('/', $relative_dir)[0];

        // Library blocks are reference-only unless explicitly enabled — see library_blocks_enabled().
        if ($category === 'library' && !library_blocks_enabled()) {
            continue;
        }

        // Get arguments from the config file. $config variable from this file.
        if (file_exists($config_file)) {
            require $config_file;
        }

        // A block-specific AJAX handler, co-located with the block instead of
        // living in the shared app/ajax/ folder — e.g. listing-3/listing-3-ajax.php.
        // Self-registers its own wp_ajax_{action} hooks, same contract as
        // app/ajax/*.php. Only required once (require_once) since this loop can
        // legitimately re-run within a request (no other guard around it).
        $block_ajax_file = $block_dir . '/' . $block_name . '-ajax.php';
        if (file_exists($block_ajax_file)) {
            require_once $block_ajax_file;
        }

        // Per-block ACF clone sub-field defaults — consumed by the acf/clone_field
        // filter in core/acf.php, not by block.json, so strip before the merge below.
        if (!empty($config['field_defaults']) && is_array($config['field_defaults'])) {
            core_block_field_defaults($block_name, $config['field_defaults']);
            unset($config['field_defaults']);
        }

        // Per-block visible-choice restrictions — consumed by the acf/pre_render_fields
        // filter in core/acf.php, not by block.json, so strip before the merge below.
        if (!empty($config['field_choices']) && is_array($config['field_choices'])) {
            core_block_field_choices($block_name, $config['field_choices']);
            unset($config['field_choices']);
        }

        // Per-block empty-preview message — consumed by the editor bail notice
        // below, not by block.json.
        if (!empty($config['bail_message']) && is_string($config['bail_message'])) {
            core_block_bail_message($block_name, $config['bail_message']);
            unset($config['bail_message']);
        }

        // Per-block initial data (mock repeater rows) — applied as the block's
        // data attribute default at registration (see filter below), not via block.json.
        if (!empty($config['repeater_content']) && is_array($config['repeater_content'])) {
            core_acf_block_initial_data($block_name, $config['repeater_content']);
            unset($config['repeater_content']);
        }

        // Arguments to create block.json
        $default_json_args = [
            'name' =>  'acf/' . $block_name,
            'title' => $config['title'] ?? ucfirst($block_name),
            'description' => $config['description'] ?? '',
            'category' => $config['category'] ?? 'custom-blocks',
            'icon' => $config['icon'] ?? 'admin-comments',
            'keywords' => [$config['keywords'] ?? $block_name],
        ];

        // Build preview image URL
        $preview_image = '/src/content/blocks/' . $relative_dir . '/preview.png';

        // Add preview image attribute to the JSON
        $default_json_args['attributes'] = [
            'preview_image' => [
                'type' => 'string',
                'default' => $preview_image
            ]
        ];

        // Merge json args
        $json_args = recursive_array_merge($default_json_args, $config);

        // Check if CSS file exists
        if (file_exists($css_file)) {
            // Register css file
            wp_register_style($block_name . '-style', get_template_directory_uri() . '/public/blocks/' . $block_name . '.css', [], filemtime($css_file));

            // Add style to the json
            $json_args['style'] = $block_name . '-style';
            $json_args['editorStyle'] = $block_name . '-style';
        }

        // Check if JS file exists
        if (file_exists($js_file)) {
            // Register js file. Only theme-app-script is a real dependency — block JS
            // is plain window.theme.block() frontend behavior, not a Gutenberg edit.js
            // component, so it never touches wp-blocks/wp-i18n/wp-element/wp-editor.
            // Those four were pulling the entire Gutenberg editor bundle (wp-editor's
            // dependency chain: wp-block-editor, wp-components, wp-server-side-render,
            // react, etc.) onto every public page, since 'script' loads in both the
            // editor AND the frontend.
            wp_register_script($block_name . '-script', get_template_directory_uri() . '/public/blocks/' . $block_name . '.js', ['theme-app-script'], filemtime($js_file), true);

            // Add script to the json
            $json_args['script'] = $block_name . '-script';
            $json_args['editorScript'] = $block_name . '-script';
        }

        // Generate block.json
        generate_block_json($block_name, $block_dir, $json_args);

        // Register block
        register_block_type($block_dir);
    }
}
add_action('init', 'scan_and_register_blocks', 10);

/**
 * Set a block's initial data as its 'data' attribute default. Gutenberg applies
 * attribute defaults on insert, so a fresh block carries the declared content
 * in the editor form, the preview, AND the frontend — no runtime value filtering.
 *
 * Must run on register_block_type_args: ACF's own registration (priority 99 on
 * block_type_metadata_settings) overwrites any 'data' attribute coming from
 * block.json with its empty default — this WP filter fires after ACF is done.
 *
 * @param array $args
 * @param string $name
 * @return array
 */
function core_block_register_initial_data(array $args, string $name): array
{
    if (!str_starts_with($name, 'acf/')) {
        return $args;
    }

    $declared = core_acf_block_initial_data(substr($name, 4));

    if (empty($declared)) {
        return $args;
    }

    $data = core_acf_translate_initial_data(substr($name, 4), $declared);

    if (!empty($data)) {
        // WP registry copy — applies the default on frontend renders (WP_Block).
        $args['attributes']['data']['default'] = $data;

        // ACF keeps its OWN settings copy (set at block_type_metadata_settings
        // priority 99, before this filter) and builds the editor JS payload from
        // it — acf.get('blockTypes') is what wp.blocks.registerBlockType receives
        // client-side. Without patching this copy the editor inserts data:{} and
        // fields come up empty (found the hard way).
        $store = function_exists('acf_get_store') ? acf_get_store('block-types') : null;
        $settings = $store ? $store->get($name) : null;

        if (is_array($settings)) {
            $settings['attributes']['data']['default'] = $data;
            $store->set($name, $settings);
        }
    }

    return $args;
}
add_filter('register_block_type_args', 'core_block_register_initial_data', 20, 2);

/**
 * Registry of per-block empty-preview messages, declared as
 * $config['bail_message'] in a block's config.php. Call with $register to
 * store, without to read (null = block uses the default message).
 *
 * @param string $block_name
 * @param string|null $register
 * @return string|null
 */
function core_block_bail_message(string $block_name, ?string $register = null): ?string
{
    static $registry = [];

    if ($register !== null) {
        $registry[$block_name] = $register;
    }

    return $registry[$block_name] ?? null;
}

/**
 * Editor-only notice for blocks that bail (render nothing). Block templates
 * bail early when required content is missing, which leaves the block
 * invisible in the editor preview — impossible to find/click without the
 * sidebar list. This buffers the template render in previews and replaces
 * empty output with a visible placeholder telling the editor what to do.
 *
 * Hooked to both acf/blocks/pre|post_block_template_render; frontend renders
 * ($is_preview false) are never buffered — zero cost outside the editor.
 *
 * @param array $block
 * @param mixed $content
 * @param bool $is_preview
 * @return void
 */
function core_block_empty_preview_notice($block, $content, $is_preview): void
{
    // Buffer-level stack — a stack (not a single value) so nested block
    // renders inside one preview pair correctly.
    static $levels = [];

    if (current_action() === 'acf/blocks/pre_block_template_render') {
        if (!$is_preview) {
            return;
        }

        ob_start();
        $levels[] = ob_get_level();
        return;
    }

    if (!$is_preview || empty($levels)) {
        return;
    }

    // Close down to our buffer level — also recovers buffers a template
    // opened and leaked, instead of leaving them to swallow the preview.
    $target = array_pop($levels);
    $html = '';

    while (ob_get_level() >= $target) {
        $html = ob_get_clean() . $html;
    }

    if (trim($html) !== '') {
        echo $html;
        return;
    }

    $block_name = basename($block['name'] ?? '');
    $message = core_block_bail_message($block_name)
        ?? __('This block has no content yet — fill in its fields to display it.', 'rear-theme');

    printf(
        '<div class="block-empty-notice" data-block="%s" style="padding:24px;border:2px dashed #94a3b8;border-radius:4px;background:#f8fafc;color:#475569;text-align:center;font-family:-apple-system,BlinkMacSystemFont,sans-serif;">
            <strong style="display:block;margin-bottom:4px;">%s</strong>
            <span>%s</span>
        </div>',
        esc_attr($block_name),
        esc_html($block['title'] ?? $block_name),
        esc_html($message)
    );
}
add_action('acf/blocks/pre_block_template_render', 'core_block_empty_preview_notice', 10, 3);
add_action('acf/blocks/post_block_template_render', 'core_block_empty_preview_notice', 10, 3);

/**
 * Closes the first-block image-priority window (theme_first_block_image_window(),
 * core/core.php) once the page's first ACF block finishes rendering — whether or not
 * that block ever called component('image', ...). See theme_first_block_image_window()
 * for why this window exists. Idempotent: fires again on every later block's render,
 * but the window is already closed by then so it's a no-op.
 *
 * Assumes today's block shape — no ACF block renders another ACF block before the
 * page's real first block starts. wrappers/inner-blocks aren't in use yet; if that
 * pattern is introduced, revisit this, since WP's render_block() fully renders child
 * blocks (including their own pre/post hooks) before a parent's own render callback
 * runs, which would close this window on the child block, before the true top-level
 * first block even starts.
 *
 * @return void
 */
function core_close_first_block_image_window(): void
{
    theme_first_block_image_window(true);
}
add_action('acf/blocks/post_block_template_render', 'core_close_first_block_image_window');

/**
 * Render block with custom parameters
 *
 * @param $content
 * @param $attr
 * @param $block
 * @var array $block
 * @return void
 */
function render($content, $block, $attr = null): void
{
    // Vars
    $fields = get_fields() ?: [];
    $block_name = basename($block['name']);
    $block_id = $block['id'];

    // Default section attributes
    $default_attr = [
        'class' => '',
        'id' => $block['anchor'] ?? '',
        'data-block' => $block_name,
        'data-block-id' => $block_id,
    ];

    // Merge default attributes with passed attributes
    $merged_attr = wp_parse_args($attr, $default_attr);

    // Add default classes and block class
    $merged_attr['class'] = 'block block-' . $block_name;
    $merged_attr['class'] .= !empty($block['className']) ? ' ' . $block['className'] : '';
    $merged_attr['class'] .= !empty($attr['class']) ? ' ' . $attr['class'] : '';

    // Per-block config field_defaults (core/acf.php) — fallback for blocks whose
    // data was never saved (get_fields() returns nothing), so an untouched block
    // renders the same bg/spacing the editor form would have pre-selected.
    // A saved value — including an explicit 'default' — always wins.
    $block_defaults = core_block_field_defaults($block_name);

    // Bg classes
    $bg_color = $fields['bg_color'] ?? $block_defaults['bg_color'] ?? 'default';

    if ($bg_color !== 'default') {
        $merged_attr['class'] .= ' bg-' . $bg_color;
        $merged_attr['class'] .= ' ' . bg_color($bg_color);
    }

    // Spacing classes
    $merged_attr['class'] .= ' spacing-top-' . ($fields['spacing_top'] ?? $block_defaults['spacing_top'] ?? 'default');
    $merged_attr['class'] .= ' spacing-bottom-' . ($fields['spacing_bottom'] ?? $block_defaults['spacing_bottom'] ?? 'default');

    // Build the attribute string
    $attr_string = '';
    foreach ($merged_attr as $key => $value) {
        if (!empty($value)) {
            $attr_string .= sprintf(' %s="%s"', esc_attr($key), esc_attr($value));
        }
    }

    // Add top and bottom custom padding variable (config field_defaults fill unsaved values).
    $fields = array_merge(array_intersect_key($block_defaults, array_flip([
        'spacing_top', 'spacing_bottom', 'spacing_top_mobile', 'spacing_top_desktop', 'spacing_bottom_mobile', 'spacing_bottom_desktop',
    ])), $fields);

    if ((isset($fields['spacing_top']) && $fields['spacing_top'] === 'custom') || (isset($fields['spacing_bottom']) && $fields['spacing_bottom'] === 'custom')) {
        $s_top_mobile = !empty($fields['spacing_top_mobile']) ? '--spacing-top-mobile: ' . $fields['spacing_top_mobile'] . '; ' : ' ';
        $s_top_desktop = !empty($fields['spacing_top_desktop']) ? '--spacing-top-desktop: ' . $fields['spacing_top_desktop'] . '; ' : ' ';
        $s_bot_mobile = !empty($fields['spacing_bottom_mobile']) ? '--spacing-bottom-mobile: ' . $fields['spacing_bottom_mobile'] . '; ' : ' ';
        $s_bot_desktop = !empty($fields['spacing_bottom_desktop']) ? '--spacing-bottom-desktop: ' . $fields['spacing_bottom_desktop'] . '; ' : '';
        $attr_string .= 'style="' . $s_top_mobile . $s_top_desktop . $s_bot_mobile . $s_bot_desktop . '"';
    }

    // Initialize script for the admin
    if (is_admin()) {
        print_init_script($block_name, $block_id);
    }

    // Return the complete HTML for the section
    echo sprintf('<section%s>%s</section>', $attr_string, $content);
}

/**
 * Print the init script in the admin to initialize js correctly
 *
 * @param string $block_name
 * @param string $block_id
 * @return void
 */
function print_init_script(string $block_name, string $block_id): void {
    $script = sprintf('window?.initializer?.initBlock("%s", "%s")', $block_name, $block_id);

    echo '<div class="initializer-init-block-js"><script>' . $script . '</script></div>';
}

/**
 * Create custom categories to list custom blocks.
 *
 * @param $block_categories
 * @param $editor_context
 * @return array
 */
function core_template_block_categories($block_categories, $editor_context): array
{
    if (!empty($editor_context->post)) {
        // Library blocks are gated by library_blocks_enabled() (see scan_and_register_blocks) —
        // only show their category when they can actually register into it.
        if (library_blocks_enabled()) {
            array_unshift(
                $block_categories,
                [
                    'slug'  => 'library',
                    'title' => __('Library', 'rear-theme'),
                    'icon'  => null,
                ]
            );
        }

        array_unshift(
            $block_categories,
            [
                'slug'  => 'custom-blocks',
                'title' => __('Custom Blocks', 'rear-theme'),
                'icon'  => null,
            ],
            [
                'slug'  => 'inner-blocks',
                'title' => __('Inner Blocks', 'rear-theme'),
                'icon'  => null,
            ],
            [
                'slug'  => 'wrappers',
                'title' => __('Wrappers', 'rear-theme'),
                'icon'  => null,
            ]
        );
    }
    return $block_categories;
}
add_filter('block_categories_all', 'core_template_block_categories', 10, 2);

/**
 * Enable separate Gutenberg styles on the frontend.
 */
add_filter('should_load_separate_core_block_assets', '__return_true');