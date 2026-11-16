<?php

/**
 * Create a custom excerpt function to display an excerpt based on the WordPress content.
 *
 * @param $limit
 * @return array|string|string[]|null
 */
function custom_excerpt($limit): array|string|null
{
    $excerpt = explode(' ', get_the_excerpt(), $limit);

    if (count($excerpt)>=$limit) {
        array_pop($excerpt);
        $excerpt = implode(" ", $excerpt) . '...';
    } else {
        $excerpt = implode(" ", $excerpt);
    }

    return preg_replace('`\[[^]]*]`','', $excerpt);
}

/**
 * Print an SVG file.
 *
 * @param string $path
 * @param bool $replace_colors
 * @return void
 */
function print_svg(string $path, bool $replace_colors = true): void
{
    if (!str_contains($path, get_path())) {
        $path = get_path('src/assets/svg/' . $path);
    }

    if (!str_ends_with($path, '.svg')) {
        $path .= '.svg';
    }

    if (!file_exists($path)) {
        return;
    }

    $svg = file_get_contents($path);

    if ($replace_colors) {
        $svg = preg_replace('/(fill|stroke)="(?!transparent|none)#?\w+"/', '$1="currentColor"', $svg);
    }

    echo $svg;
}

/**
 * Function to detect if block background is light or dark
 *
 * @param string|null $field Null (field not present in block data) behaves as 'default'
 * @param bool $icon
 * @param string $default_icon
 * @return string
 */
function bg_color(?string $field, bool $icon = false, string $default_icon = ''): string
{
    $field = $field ?: 'default';

    // Start with making the bg dark
    $class = $icon ? 'light' : 'bg-is-dark';

    // Light bgs. Update if the design changes
    if ($field === 'background-2' || $field === 'background-4' || $field === 'background-5') {
        $class = $icon ? 'dark' : 'bg-is-light';
    }

    // Default
    if ($field === 'default') {
        $class = !empty($default_icon) ? $default_icon : '';
    }

    return $class;
}

/**
 * Recursively merge arrays updating existing keys with the second array.
 *
 * @param $first_array
 * @param $second_array
 * @return mixed
 */
function recursive_array_merge($first_array, $second_array): mixed
{
    foreach ($second_array as $key => $value) {
        if (is_array($value) && isset($first_array[$key]) && is_array($first_array[$key])) {
            $first_array[$key] = recursive_array_merge($first_array[$key], $value);
        } else {
            $first_array[$key] = $value;
        }
    }
    return $first_array;
}

/**
 * Retrieve the primary category of a post based on yoast seo selected category or first.
 */
function get_primary_term_id($post_id, $taxonomy = 'category')
{
    if (empty($post_id)) return false;

    $primary_category = false;

    if (function_exists('yoast_get_primary_term_id')) {
        $primary_category = yoast_get_primary_term_id($taxonomy, $post_id);
    }

    if (empty($primary_category)) {
        $terms = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'ids']);

        if (!empty($terms)) {
            $primary_category = $terms[0];
        }
    }

    return $primary_category;
}

/**
 * Retrieve the primary category name of a post based on yoast seo selected category or first.
 */
function get_primary_term_name($post_id, $taxonomy = 'category'): string
{
    $term_id = get_primary_term_id($post_id, $taxonomy);

    if (empty($term_id)) return '';

    return get_term($term_id)->name ?? '';
}
