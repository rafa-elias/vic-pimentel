<?php

/**
 * Get a URL relative to the current theme.
 *
 * @param string $path
 *
 * @return string
 */
function get_url(string $path = ''): string
{
    return get_template_directory_uri() . '/' . $path;
}

/**
 * Get a path relative to the current theme.
 *
 * @param string $path
 *
 * @return string
 */
function get_path(string $path = ''): string
{
    return get_template_directory() . '/' . $path;
}

/**
 * Get a path relative to the root of the WordPress installation.
 *
 * @param string $path
 *
 * @return string
 */
function get_root_path(string $path = ''): string
{
    return ABSPATH . $path;
}

/**
 * Get the block's path.
 *
 * @return string
 */
function block_path(): string
{
    return get_path() . 'src/content/blocks';
}

/**
 * Get the component's path.
 *
 * @return string
 */
function component_path(): string
{
    return 'src/content/components';
}

/**
 * Subfolders searched, in priority order, when resolving a bare component name
 * to its folder on disk. Lets component folders live nested (e.g. style-guide/button)
 * without callers having to know or specify the subfolder.
 *
 * @return string[]
 */
function component_subfolders(): array
{
    return ['style-guide', 'custom', 'library'];
}

/**
 * Names of components currently sitting in components/library — reference-only
 * variants that haven't been chosen for this project. Used to keep their compiled
 * CSS/JS out of the build's enqueue list even though the source files still exist.
 *
 * @return string[]
 */
function library_component_names(): array
{
    static $names = null;

    if ($names !== null) {
        return $names;
    }

    $dirs = glob(get_path(component_path() . '/library') . '/*', GLOB_ONLYDIR) ?: [];

    return $names = array_map('basename', $dirs);
}

/**
 * Resolve a bare component name (e.g. "button") to its folder path relative to
 * component_path() (e.g. "style-guide/button"). Falls back to the bare name for
 * components that still live flat directly under components_path() (back-compat).
 *
 * @param string $name
 * @return string
 */
function resolve_component_folder(string $name): string
{
    static $cache = [];

    if (isset($cache[$name])) {
        return $cache[$name];
    }

    $resolved = $name;

    foreach (component_subfolders() as $subfolder) {
        if (is_dir(get_path(component_path() . '/' . $subfolder . '/' . $name))) {
            $resolved = $subfolder . '/' . $name;
            break;
        }
    }

    return $cache[$name] = $resolved;
}

/**
 * Get the section path.
 *
 * @return string
 */
function section_path(): string
{
    return 'src/content/sections';
}

/**
 * Get the part path.
 *
 * @return string
 */
function part_path(): string
{
    return 'src/content/parts';
}

/**
 * Register the section function to use components through get_template_part
 *
 * @param string $name
 * @param null $args
 * @return false|null
 */
function section(string $name, $args = null): ?bool
{
    return get_template_part(section_path() . '/' . $name . '/' . $name, null, $args);
}

/**
 * Register the part function to use components through get_template_part
 *
 * @param string $name
 * @param $args
 * @return bool|null
 */
function part(string $name, $args = null): ?bool
{
    return get_template_part(part_path() . '/' . $name, null, $args);
}

/**
 * Get ACF field from options page.
 *
 * @param string $page
 * @return string|array
 */
function options(string $page): array|string
{
    $field = get_field($page, 'options');

    if (empty($field)) {
        return [];
    }

    if (is_array($field)) {
        return $field;
    }

    return (string) $field;
}

/**
 * Function to return the proper path of parts.
 *
 * @param array $args
 * @return string
 */
function parts_path(array $args): string
{
    // Return empty string if proper args are not present
    if (empty($args['block_parent']) || empty($args['block_folder']) || empty($args['file_name'])) return '';

    // Path if it is a page template
    if ($args['block_folder'] === 'templates') {
        return 'templates/parts/' . $args['file_name'];
    }

    // Path for blocks and components. $args['block_parent'] is 'blocks' or 'components'
    // (see component()), $args['block_folder'] is the path underneath it relative to
    // that folder (e.g. 'library/text-1', or 'style-guide/tabs' for a nested component) —
    // this keeps parts_path() correct regardless of how deep the caller is nested.
    return 'src/content/' . $args['block_parent'] . '/' . $args['block_folder'] . '/parts';
}

/**
 * Convert a jpg/png attachment URL to its .webp sibling, but only if that sibling
 * actually exists on disk. Attachment files are converted separately (see
 * webp-convert.php-style scripts) — originals are never deleted, so this is a pure
 * progressive-enhancement lookup: an unconverted or non-jpg/png URL returns null and
 * the caller falls back to the original untouched.
 *
 * @param string $url
 * @return string|null
 */
function theme_webp_url_if_exists(string $url): ?string
{
    $webp_url = preg_replace('/\.(jpe?g|png)$/i', '.webp', $url);
    if ($webp_url === $url) return null; // not a jpg/png URL

    $upload_dir = wp_get_upload_dir();
    if (!str_starts_with($webp_url, $upload_dir['baseurl'])) return null;

    $webp_path = $upload_dir['basedir'] . substr($webp_url, strlen($upload_dir['baseurl']));

    return file_exists($webp_path) ? $webp_url : null;
}

/**
 * Rewrites a `srcset` attribute string (URL descriptor, URL descriptor, ...) to its
 * webp equivalents, dropping any entry whose webp sibling doesn't exist yet.
 *
 * @param string $srcset
 * @return string|null Null if none of the entries have a webp sibling.
 */
function theme_webp_srcset_if_exists(string $srcset): ?string
{
    $webp_entries = [];

    foreach (explode(',', $srcset) as $entry) {
        $entry = trim($entry);
        if ($entry === '') continue;

        $space_pos = strrpos($entry, ' ');
        $url = $space_pos === false ? $entry : substr($entry, 0, $space_pos);
        $descriptor = $space_pos === false ? '' : substr($entry, $space_pos);

        $webp_url = theme_webp_url_if_exists($url);
        if ($webp_url) $webp_entries[] = $webp_url . $descriptor;
    }

    return $webp_entries ? implode(', ', $webp_entries) : null;
}

/**
 * Reads or closes the page's first-block image-priority window. The window is open
 * while the page's first ACF block is rendering (opened/closed by
 * core_close_first_block_image_window() in core/blocks.php, hooked to
 * acf/blocks/pre_block_template_render) and closes for good once that block's render
 * template finishes — whether or not it ever called component('image', ...).
 *
 * Without this, theme_is_first_priority_image() would hand the eager/fetchpriority
 * slot to whichever image call happens to execute first in PHP order, even if that's
 * a much later block — e.g. a video-only hero (no image call of its own) followed by
 * a card grid a few blocks down. That later image is very unlikely to be the page's
 * actual LCP element, so eagerly loading it is a straight regression, not a fix.
 *
 * @param bool $close Pass true to close the window for the rest of the request.
 * @return bool
 */
function theme_first_block_image_window(bool $close = false): bool
{
    static $open = true;

    if ($close) $open = false;

    return $open;
}

/**
 * True exactly once per request — for the first image the `image` component renders
 * while the page's first block is still rendering (see theme_first_block_image_window()).
 * Lets that one call skip lazy-loading and get fetchpriority=high without every block
 * needing to know (or guess) whether it happens to be first on the page.
 *
 * @return bool
 */
function theme_is_first_priority_image(): bool
{
    static $claimed = false;

    if ($claimed || !theme_first_block_image_window()) return false;

    $claimed = true;
    return true;
}

/**
 * Register the component function to use components through get_template_part
 *
 * @param string $name
 * @param null $args1
 * @param null $args2
 * @return false|null
 */
function component(string $name, $args1 = null, $args2 = null): ?bool
{
    // Ensure $args1 is always an array
    $args1 = is_array($args1) ? $args1 : [];

    // Add default args
    $args1['name'] = $name;
    $args1['class'] = !empty($args1['class']) ? $args1['class'] . ' ' : '';
    $args = $args1;

    // Merge args if $args2
    if (!empty($args2)) {
        $args2['class'] = !empty($args2['class']) ? $args2['class'] . ' ' : '';
        $args = array_merge($args1, $args2);
    }

    // Add an argument to save the path of the caller.
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
    $caller_file = $backtrace[0]['file'] ?? false;

    if ($caller_file) {
        $path = dirname($caller_file);

        if (is_dir($path)) {
            // Anchor on the known content-type roots so block_folder carries the FULL
            // path relative to that root (e.g. 'style-guide/tabs', 'library/text-1') —
            // this stays correct regardless of how deeply the caller is nested.
            $anchors = [
                get_path(component_path()) . '/' => 'components',
                block_path() . '/' => 'blocks',
            ];

            $block_parent = null;
            $block_folder = null;

            foreach ($anchors as $prefix => $type) {
                if (str_starts_with($path, $prefix)) {
                    $block_parent = $type;
                    $block_folder = substr($path, strlen($prefix));
                    break;
                }
            }

            // Fallback for callers outside src/content/components and src/content/blocks
            // (e.g. page templates in templates/) — same behavior as before this function
            // learned about nested component/block folders.
            if ($block_parent === null) {
                $folder_parts = explode('/', trim($path, '/'));
                $folder_count = count($folder_parts);
                $block_parent = $folder_parts[$folder_count - 2] ?? '';
                $block_folder = $folder_parts[$folder_count - 1] ?? '';
            }

            $args['block_parent'] = basename($block_parent);
            $args['block_folder'] = $block_folder;
            $args['file_name'] = pathinfo($caller_file, PATHINFO_FILENAME);
        }
    }

    // Return template part — resolve_component_folder() finds the (possibly nested)
    // folder a bare name lives in, so callers never need to know or pass a subfolder.
    return get_template_part(component_path() . '/' . resolve_component_folder($name) . '/' . $name, null, $args);
}

/**
 * Register post type
 *
 * @param string $slug
 * @param string $label_singular
 * @param string $label_plural
 * @param array $args
 * @param array $labels
 * @return void
 */
function register_cpt(string $slug, string $label_singular, string $label_plural, array $args = [], array $labels = []): void
{
    if (empty($slug) || empty($label_singular) || empty($label_plural)) return;

    $default_args = [
        'label' => $label_singular,
        'can_export' => false,
        'capability_type' => 'post',
        'has_archive' => false,
        'hierarchical' => false,
        'menu_icon' => 'dashicons-admin-post',
        'menu_position' => 25,
        'public' => true,
        'show_in_rest' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'query_var' => true,
        'supports' => ['title', 'thumbnail', 'editor', 'excerpt', 'author', 'revisions'],
    ];

    $default_labels = [
        'name' => $label_plural,
        'singular_name' => $label_singular,
        'add_new' => sprintf(__('Add New %s', 'rear-theme'), $label_singular),
        'add_new_item' => sprintf(__('Add New %s', 'rear-theme'), $label_singular),
        'edit_item' => sprintf(__('Edit %s', 'rear-theme'), $label_singular),
        'new_item' => sprintf(__('Add New %s', 'rear-theme'), $label_singular),
        'view_item' => sprintf(__('View %s', 'rear-theme'), $label_singular),
        'view_items' => sprintf(__('View %s', 'rear-theme'), $label_plural),
        'search_items' => sprintf(__('Search %s', 'rear-theme'), $label_singular),
        'not_found' => sprintf(__('No %s found', 'rear-theme'), $label_plural),
        'not_found_in_trash' => sprintf(__('No %s found in Trash', 'rear-theme'), $label_plural),
        'all_items' => sprintf(__('All %s', 'rear-theme'), $label_plural),
    ];

    if (!empty($args)) $default_args = wp_parse_args($args, $default_args);
    if (!empty($labels)) $default_labels = wp_parse_args($labels, $default_labels);

    $default_args['labels'] = $default_labels;
    register_post_type($slug, $default_args);
}

/**
 * Register Taxonomy
 *
 * @param string $taxonomy
 * @param $object_types
 * @param string $label_singular
 * @param string $label_plural
 * @param array $args
 * @param array $labels
 * @return void
 */
function register_tax(string $taxonomy, $object_types, string $label_singular, string $label_plural, array $args = [], array $labels = []): void
{
    if (empty($taxonomy) || empty($object_types) || empty($label_singular) || empty($label_plural)) return;
    if (!is_array($object_types)) $object_types = [$object_types];

    $default_args = [
        'label' => $label_plural,
        'public' => true,
        'hierarchical' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => ['slug' => $taxonomy],
    ];

    $default_labels = [
        'name' => $label_plural,
        'singular_name' => $label_singular,
        'search_items' => sprintf(__('Search %s', 'rear-theme'), $label_plural),
        'all_items' => sprintf(__('All %s', 'rear-theme'), $label_plural),
        'parent_item' => sprintf(__('Parent %s', 'rear-theme'), $label_singular),
        'parent_item_colon' => sprintf(__('Parent %s:', 'rear-theme'), $label_singular),
        'edit_item' => sprintf(__('Edit %s', 'rear-theme'), $label_singular),
        'update_item' => sprintf(__('Update %s', 'rear-theme'), $label_singular),
        'add_new_item' => sprintf(__('Add New %s', 'rear-theme'), $label_singular),
        'new_item_name' => sprintf(__('New %s Name', 'rear-theme'), $label_singular),
        'menu_name' => $label_plural,
    ];

    if (!empty($args)) $default_args = wp_parse_args($args, $default_args);
    if (!empty($labels)) $default_labels = wp_parse_args($labels, $default_labels);

    $default_args['labels'] = $default_labels;
    register_taxonomy($taxonomy, $object_types, $default_args);
}

/**
 * Function to be used with get_menu_items to create the children
 *
 * @param $items
 * @param $children
 * @param $level
 * @return void
 */
function attach_children(&$items, $children, $level = 0): void
{
    foreach ($items as &$item) {
        $level_class = ($level === 0) ? 'parent-menu' : 'children-menu level-' . $level;
        $item['classes'][] = $level_class;
        $item['classes'] = implode(' ', $item['classes']);

        if (isset($children[$item['ID']])) {
            $item['children'] = $children[$item['ID']];
            attach_children($item['children'], $children, $level + 1);
        }
    }
}

/**
 * Returns an array of menu items for the specified menu.
 *
 * @param string $menu
 * @return array|null
 */
function get_menu_items(string $menu): ?array
{
    $menu_locations = get_nav_menu_locations();

    if (!$menu_locations || !isset($menu_locations[$menu])) {
        return null;
    }

    // Get the menu object.
    $menu = wp_get_nav_menu_object($menu_locations[$menu]);

    if (!$menu) {
        return null;
    }

    // Get the menu items.
    $menu_items = wp_get_nav_menu_items($menu);

    if (empty($menu_items)) {
        return [];
    }

    // Get the current page URL
    $current_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

    // Build the menu items into a hierarchical array.
    $items = [];
    $children = [];

    foreach ($menu_items as $item) {
        $fields = get_fields($item->ID);

        // Preserve manually added classes
        $classes = array_filter($item->classes);

        // Add the current-menu-item class if the URL matches the current page
        if (untrailingslashit($item->url) === untrailingslashit($current_url)) {
            $classes[] = 'current-menu-item';
        }

        $item_data = [
            'ID' => $item->ID,
            'title' => !empty($item->post_title) ? $item->post_title : $item->title,
            'url' => $item->url,
            'target' => $item->target ?? '',
            'classes' => $classes,
            'data' => $fields ?: [], // get_fields() returns false when the item has no ACF data.
        ];

        if ($item->menu_item_parent) {
            if (!isset($children[$item->menu_item_parent])) {
                $children[$item->menu_item_parent] = [];
            }
            $children[$item->menu_item_parent][] = $item_data;
        } else {
            $items[$item->ID] = $item_data;
        }
    }

    attach_children($items, $children);

    return array_values($items);
}

/**
 * Add ?v=[last modified time] to style sheets
 *
 * @param $relative_url
 * @param string $add_attributes
 * @return void
 */
function versioned_stylesheet($relative_url, string $add_attributes = ''): void
{
    echo '<link rel="stylesheet" href="' . $relative_url . '" ' . $add_attributes . '>' . "\n";
}

/**
 * Add ?v=[last modified time] to javascript
 *
 * @param $relative_url
 * @param string $add_attributes
 * @return void
 */
function versioned_javascript($relative_url, string $add_attributes = ''): void
{
    echo '<script src="' . $relative_url . '" ' . $add_attributes . '></script>' . "\n";
}

/**
 * Frontend-only body marker — absent in wp-admin and the block editor's canvas iframe,
 * so Ember/Lenis/page-wide scripts and the critical pre-hide CSS can key off it.
 */
add_filter('body_class', function (array $classes): array {
    $classes[] = 'is-frontend';

    return $classes;
});

/**
 * Add translations for strings
 */
add_action('after_setup_theme', function () {
    load_theme_textdomain('rear-theme', get_template_directory() . '/languages');
});