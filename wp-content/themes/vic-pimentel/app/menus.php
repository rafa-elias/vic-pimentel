<?php

/**
 * Register WordPress menus
 */
function theme_register_menu(): void
{
    register_nav_menus([
        'header' => __('Header Navigation', 'rear-theme'),
    ]);
}
add_action('init', 'theme_register_menu');
