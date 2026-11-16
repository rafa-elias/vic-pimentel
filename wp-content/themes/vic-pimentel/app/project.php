<?php

/**
 * Project (post) helpers shared by the card, listing, single template and blocks.
 *
 * Projects are core `post` entries. Media rule (designer + client decision): an image is
 * always required; a looping video is optional and, when present, is shown instead of
 * the image (the image stays as poster/fallback).
 */

/**
 * Escape a string and turn *word* into <em>word</em> — the italic serif emphasis used
 * across headings ("Get *In Touch*", "Highlights in *This Project*").
 */
function vp_emphasis(?string $text): string
{
    if ($text === null || $text === '') return '';

    $escaped = esc_html($text);

    return preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $escaped);
}

/**
 * Normalise an image + optional video pair into the shape the `media` component expects.
 *
 * @param array|false|null $image ACF image array
 * @param array|false|null $video ACF file array
 */
function vp_media(mixed $image, mixed $video = null): array
{
    return [
        'image' => is_array($image) ? $image : [],
        'video' => is_array($video) && !empty($video['url']) ? $video : [],
    ];
}

/**
 * Hero/thumbnail media of a project.
 */
function vp_project_media(int $post_id): array
{
    $image = get_field('media_image', $post_id);

    if (empty($image) && has_post_thumbnail($post_id)) {
        $image = acf_get_attachment(get_post_thumbnail_id($post_id));
    }

    return vp_media($image, get_field('media_video', $post_id));
}

/**
 * Data needed to render a project card.
 */
function vp_project_card_data(int $post_id): array
{
    $favicon = get_field('show_favicon', $post_id) !== false ? get_field('favicon', $post_id) : null;

    return [
        'id' => $post_id,
        'title' => get_the_title($post_id),
        'url' => get_permalink($post_id),
        'category' => get_primary_term_name($post_id),
        'company' => get_field('company_name', $post_id) ?: get_the_title($post_id),
        'favicon' => $favicon ?: null,
        'media' => vp_project_media($post_id),
    ];
}

/**
 * Keep only published projects, in order, max 16.
 */
function vp_clean_project_ids(array $ids): array
{
    $ids = array_map(fn($p) => is_object($p) ? $p->ID : (int) $p, $ids);

    return array_values(array_slice(array_filter(
        array_unique($ids),
        fn($id) => $id && get_post_type($id) === 'post' && get_post_status($id) === 'publish'
    ), 0, 16));
}

/**
 * Featured project IDs. The list lives on the Project Listing block (its "Featured
 * projects" field); outside that block (e.g. "You May Also Like") the list is read from
 * the listing placed on the front page.
 */
function vp_featured_ids(): array
{
    static $ids = null;

    if ($ids !== null) return $ids;

    $ids = [];
    $front = (int) get_option('page_on_front');

    if (!$front) return $ids;

    $find = function (array $blocks) use (&$find) {
        foreach ($blocks as $block) {
            if ($block['blockName'] === 'acf/project-listing') {
                return (array) ($block['attrs']['data']['featured_posts'] ?? []);
            }

            if (!empty($block['innerBlocks']) && ($found = $find($block['innerBlocks']))) {
                return $found;
            }
        }

        return [];
    };

    return $ids = vp_clean_project_ids($find(parse_blocks((string) get_post_field('post_content', $front))));
}

/**
 * Project IDs for a listing tab: 'featured' or a category term ID (all, latest first).
 */
function vp_listing_ids(string|int $tab, ?array $featured = null): array
{
    if ($tab === 'featured') {
        return $featured !== null ? vp_clean_project_ids($featured) : vp_featured_ids();
    }

    return get_posts([
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'orderby' => 'date',
        'order' => 'DESC',
        'cat' => (int) $tab,
        'no_found_rows' => true,
    ]);
}

/**
 * "You May Also Like": random featured projects, excluding the current one, topped up
 * with the latest projects when there aren't enough featured ones.
 */
function vp_related_ids(int $exclude, int $count = 4): array
{
    $ids = array_values(array_diff(vp_featured_ids(), [$exclude]));
    shuffle($ids);
    $ids = array_slice($ids, 0, $count);

    if (count($ids) < $count) {
        $ids = array_merge($ids, get_posts([
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $count - count($ids),
            'post__not_in' => array_merge($ids, [$exclude]),
            'fields' => 'ids',
            'no_found_rows' => true,
        ]));
    }

    return $ids;
}

/**
 * Keep the WP featured image in sync with the required project image, so Yoast/OG tags
 * and anything else reading the core thumbnail get the same picture.
 */
add_action('acf/save_post', function ($post_id): void {
    if (!is_numeric($post_id) || get_post_type($post_id) !== 'post') return;

    $image = get_field('media_image', $post_id, false);

    if ($image) {
        set_post_thumbnail((int) $post_id, (int) $image);
    }
}, 20);

/**
 * Relabel "Posts" as "Projects" in wp-admin.
 */
add_action('init', function (): void {
    $object = get_post_type_object('post');

    if (!$object) return;

    $labels = $object->labels;
    $labels->name = __('Projects', 'rear-theme');
    $labels->singular_name = __('Project', 'rear-theme');
    $labels->add_new_item = __('Add New Project', 'rear-theme');
    $labels->edit_item = __('Edit Project', 'rear-theme');
    $labels->new_item = __('New Project', 'rear-theme');
    $labels->view_item = __('View Project', 'rear-theme');
    $labels->search_items = __('Search Projects', 'rear-theme');
    $labels->not_found = __('No projects found', 'rear-theme');
    $labels->all_items = __('All Projects', 'rear-theme');
    $labels->menu_name = __('Projects', 'rear-theme');
    $labels->name_admin_bar = __('Project', 'rear-theme');
    $object->menu_icon = 'dashicons-portfolio';

    // Tags aren't used on this site.
    unregister_taxonomy_for_object_type('post_tag', 'post');
});

/**
 * Only the project content blocks are offered on projects; pages get
 * the page blocks. Keeps the inserter focused on what the design actually supports.
 */
add_filter('allowed_block_types_all', function ($allowed, $context) {
    $post = $context->post ?? null;

    if (!$post) return $allowed;

    $project_blocks = ['post-intro', 'post-text', 'post-media', 'post-media-pair', 'post-stats', 'post-project-info'];
    $page_blocks = ['hero-home', 'project-listing', 'about-hero', 'timeline', 'services-list', 'text-section'];
    $names = $post->post_type === 'post' ? $project_blocks : $page_blocks;

    return array_map(fn($name) => "acf/$name", $names);
}, 10, 2);
