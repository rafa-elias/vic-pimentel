<?php

/**
 * Register custom thumbnails
 */
function thumbs_setup(): void
{
    add_editor_style();
    add_theme_support('post-thumbnails');
    if (function_exists('add_image_size')) {
        add_image_size('small', 414);
        add_image_size('xlarge', 1440);
        add_image_size('xxlarge', 1920);
    }
    add_theme_support('automatic-feed-links');
}
add_action('after_setup_theme', 'thumbs_setup');

/**
 * Remove medium_large
 */
add_filter('intermediate_image_sizes', function($sizes) {
    return array_diff($sizes, ['medium_large']);
});

/**
 * Remove other WP sizes
 *
 * @return void
 */
function remove_default_wp_sizes(): void
{
    remove_image_size('1536x1536');
    remove_image_size('2048x2048');
}
add_action('init', 'remove_default_wp_sizes');

/**
 * Add Excerpt to Pages.
 */
add_post_type_support('page', 'excerpt');

/**
 * Move Yoast Bar below acf fields if there's any.
 */
function theme_yoast_bottom(): string
{
    return 'low';
}
add_filter( 'wpseo_metabox_prio', 'theme_yoast_bottom');

/**
 * Tag to remove auto from image
 */
add_filter('wp_img_tag_add_auto_sizes', '__return_false');