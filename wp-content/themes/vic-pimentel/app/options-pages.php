<?php

/**
 * ACF Options Pages
 */
add_action( 'init', 'acf_options');
function acf_options(): void
{
    if(function_exists('acf_add_options_page')) {
        acf_add_options_page([
            'page_title'    => __('Site Settings', 'rear-theme'),
            'menu_title'    => __('Site Settings', 'rear-theme'),
            'menu_slug'     => 'options_template',
            'capability'    => 'edit_posts',
            'icon_url'      => 'dashicons-admin-generic',
            'position'      => 35,
        ]);

        acf_add_options_sub_page([
            'page_title'    => __('Header', 'rear-theme'),
            'menu_title'    => __('Header', 'rear-theme'),
            'menu_slug'     => 'header_template',
            'parent_slug'   => 'options_template',
        ]);

        acf_add_options_sub_page([
            'page_title'    => __('Footer', 'rear-theme'),
            'menu_title'    => __('Footer', 'rear-theme'),
            'menu_slug'     => 'footer_template',
            'parent_slug'   => 'options_template',
        ]);

        acf_add_options_sub_page([
            'page_title'    => __('Portfolio Access', 'rear-theme'),
            'menu_title'    => __('Portfolio Access', 'rear-theme'),
            'menu_slug'     => 'portfolio_access_template',
            'parent_slug'   => 'options_template',
        ]);

        acf_add_options_sub_page([
            'page_title'    => __('Animations', 'rear-theme'),
            'menu_title'    => __('Animations', 'rear-theme'),
            'menu_slug'     => 'animations_template',
            'parent_slug'   => 'options_template',
        ]);

        acf_add_options_sub_page([
            'page_title'    => __('Default Image', 'rear-theme'),
            'menu_title'    => __('Default Image', 'rear-theme'),
            'menu_slug'     => 'default_image_template',
            'parent_slug'   => 'options_template',
        ]);

        acf_add_options_sub_page([
            'page_title'    => __('Default Pages', 'rear-theme'),
            'menu_title'    => __('Default Pages', 'rear-theme'),
            'menu_slug'     => 'default_pages_template',
            'parent_slug'   => 'options_template',
        ]);

        acf_add_options_sub_page([
            'page_title'    => __('Admin Login', 'rear-theme'),
            'menu_title'    => __('Admin Login', 'rear-theme'),
            'menu_slug'     => 'login_template',
            'parent_slug'   => 'options_template',
        ]);

        acf_add_options_sub_page([
            'page_title'    => __('Scripts', 'rear-theme'),
            'menu_title'    => __('Scripts', 'rear-theme'),
            'menu_slug'     => 'scripts_template',
            'parent_slug'   => 'options_template',
        ]);

        acf_add_options_sub_page([
            'page_title'    => __('Feature Toggles', 'rear-theme'),
            'menu_title'    => __('Feature Toggles', 'rear-theme'),
            'menu_slug'     => 'feature_toggles_template',
            'parent_slug'   => 'options_template',
            'capability'    => 'view_feature_toggles',
        ]);
    }
}

/**
 * Restricts the Feature Toggles options page to specific user ID(s) (see
 * FEATURE_TOGGLES_USER_ID in app/config.php) via a dedicated capability
 * rather than a role — this is meant to gate the site's actual primary
 * admin(s) specifically, not "everyone with the Administrator role", and a
 * capability check (unlike hiding the menu item) also blocks direct URL
 * access to the page.
 */
defined('FEATURE_TOGGLES_USER_ID') || define('FEATURE_TOGGLES_USER_ID', 1);

/**
 * FEATURE_TOGGLES_USER_ID accepts either a single ID or an array of IDs —
 * also used by core/acf.php so the primary admin never loses ACF admin
 * access, and by core/blocks.php's library-blocks toggle, regardless of file
 * load order (this is only ever called from inside a hook callback, well
 * after every file has finished loading).
 */
function is_feature_toggles_admin(?int $user_id = null): bool
{
    $user_id = $user_id ?? get_current_user_id();
    $allowed = array_map('intval', (array) FEATURE_TOGGLES_USER_ID);

    return in_array((int) $user_id, $allowed, true);
}

add_filter('user_has_cap', 'core_feature_toggles_capability', 10, 4);
function core_feature_toggles_capability(array $allcaps, array $caps, array $args, WP_User $user): array
{
    if (in_array('view_feature_toggles', $caps, true)) {
        $allcaps['view_feature_toggles'] = is_feature_toggles_admin((int) $user->ID);
    }

    return $allcaps;
}
