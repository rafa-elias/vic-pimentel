<?php

// Remove the welcome widget from WordPress.
remove_action('welcome_panel', 'wp_welcome_panel');

// Remove admin bar
add_filter('show_admin_bar', '__return_false');

// Remove the quick draft widget and the WordPress news widget.
add_action('wp_dashboard_setup', function () {
    remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
    remove_meta_box('dashboard_primary', 'dashboard', 'side');
});

// Remove the comments from the admin bar.
add_action('wp_before_admin_bar_render', function () {
    global $wp_admin_bar;
    $wp_admin_bar->remove_menu('comments');
});

// Remove the default comments.
add_action('admin_menu', function () {
    remove_menu_page('edit-comments.php');
});

// Disable the customizer menu.
add_action('admin_menu', function () {
    $customizer_url = add_query_arg(
        'return',
        urlencode(remove_query_arg(wp_removable_query_args(), wp_unslash($_SERVER['REQUEST_URI']))),
        'customize.php'
    );

    remove_submenu_page('themes.php', $customizer_url);
    remove_submenu_page('themes.php', 'theme-editor.php');
}, 999);