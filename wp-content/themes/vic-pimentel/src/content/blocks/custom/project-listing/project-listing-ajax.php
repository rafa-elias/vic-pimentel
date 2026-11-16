<?php

/**
 * Project listing — tab data + AJAX tab switching (block-specific, auto-required by
 * scan_and_register_blocks()).
 */

/**
 * Tabs for a listing instance: Featured (optional) + categories that have projects.
 *
 * @return array<int, array{key: string, slug: string, label: string}>
 */
function vp_listing_tabs(array $fields): array
{
    $tabs = [];

    if (($fields['show_featured'] ?? true) && vp_clean_project_ids((array) ($fields['featured_posts'] ?? []))) {
        $tabs[] = ['key' => 'featured', 'slug' => 'featured', 'label' => $fields['featured_label'] ?: __('Featured', 'rear-theme')];
    }

    $term_args = ['taxonomy' => 'category', 'hide_empty' => true, 'orderby' => 'term_order', 'order' => 'ASC'];

    if (!empty($fields['categories'])) {
        $term_args['include'] = array_map('intval', (array) $fields['categories']);
        $term_args['orderby'] = 'include';
    }

    foreach (get_terms($term_args) as $term) {
        if ($term->slug === 'uncategorized') continue;

        $tabs[] = ['key' => (string) $term->term_id, 'slug' => $term->slug, 'label' => $term->name];
    }

    return $tabs;
}

/**
 * Card markup for a list of project IDs.
 */
function vp_listing_cards_html(array $ids): string
{
    ob_start();

    foreach ($ids as $id) {
        component('card-post', ['post_id' => $id, 'html_tag' => 'li']);
    }

    return ob_get_clean();
}

function ajax_vp_project_listing(): void
{
    check_ajax_referer('theme_ajax', 'nonce');

    if (function_exists('vp_has_access') && !vp_has_access()) {
        wp_send_json_error(['message' => 'forbidden'], 403);
    }

    $tab = sanitize_text_field(wp_unslash($_POST['tab'] ?? ''));

    if ($tab !== 'featured') {
        $term = get_term((int) $tab, 'category');

        if (!$term || is_wp_error($term)) {
            wp_send_json_error(['message' => 'invalid tab'], 400);
        }

        $tab = (int) $term->term_id;
    }

    // The Featured list belongs to the block instance, so the client sends it back.
    $featured = array_map('intval', explode(',', sanitize_text_field(wp_unslash($_POST['featured'] ?? ''))));
    $ids = vp_listing_ids($tab, $tab === 'featured' ? $featured : null);

    wp_send_json_success([
        'html' => vp_listing_cards_html($ids),
        'count' => count($ids),
    ]);
}
add_action('wp_ajax_vp_project_listing', 'ajax_vp_project_listing');
add_action('wp_ajax_nopriv_vp_project_listing', 'ajax_vp_project_listing');
