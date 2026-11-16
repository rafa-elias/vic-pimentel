<?php

/**
 * Native replacement for the "Yoast Duplicate Post" plugin — a nonce-protected
 * "Duplicate" row action (plus a matching bulk action) per allowed post type,
 * cloning post data, every postmeta entry (covers ACF fields and the featured
 * image, which is just regular postmeta under the hood — no special-casing
 * needed for either), and taxonomy terms.
 *
 * Gated by ALLOW_DUPLICATE_POST (app/config.php) AND the "Enable Duplicate
 * Post" field on the Feature Toggles options page — both must be on. Which
 * post types actually get the row/bulk action is chosen via the "Post Types
 * Allowed to Duplicate" field on that same page.
 */

defined('ALLOW_DUPLICATE_POST') || define('ALLOW_DUPLICATE_POST', false);

function duplicate_post_enabled(): bool
{
    if (!ALLOW_DUPLICATE_POST) return false;

    // Same reasoning as svg_upload_enabled() — get_field() returns null
    // (not the field's default_value) until the Feature Toggles page has
    // actually been saved once; only an explicit, saved "off" should count.
    return get_field('feature_duplicate_post', 'option') !== false;
}

function duplicate_post_allowed_post_types(): array
{
    $selected = get_field('duplicate_post_types', 'option');
    return is_array($selected) ? $selected : [];
}

// Populated at render time rather than hardcoded in the field's JSON, so a
// post type added later automatically shows up here without editing ACF.
add_filter('acf/load_field/key=field_d224a05cfafe5', 'core_duplicate_post_type_choices');
function core_duplicate_post_type_choices(array $field): array
{
    $field['choices'] = [];

    foreach (get_post_types(['public' => true], 'objects') as $post_type) {
        if ($post_type->name === 'attachment') continue;
        $field['choices'][$post_type->name] = $post_type->labels->singular_name;
    }

    return $field;
}

add_filter('post_row_actions', 'core_duplicate_post_row_action', 10, 2);
add_filter('page_row_actions', 'core_duplicate_post_row_action', 10, 2);
function core_duplicate_post_row_action(array $actions, WP_Post $post): array
{
    if (!duplicate_post_enabled()) return $actions;
    if ($post->post_status === 'trash') return $actions;
    if (!in_array($post->post_type, duplicate_post_allowed_post_types(), true)) return $actions;

    $post_type_object = get_post_type_object($post->post_type);
    if (!$post_type_object || !current_user_can($post_type_object->cap->create_posts)) return $actions;

    // create_posts is a type-level capability ("can this user make new posts
    // of this type at all") — it says nothing about THIS specific post. A
    // user who can create their own posts isn't automatically allowed to
    // read/clone someone else's; without this instance-level check, an
    // Author-level user could duplicate another user's private or draft
    // content by requesting the admin-post.php URL directly. This mirrors
    // the check Yoast Duplicate Post itself performs.
    if (!current_user_can('edit_post', $post->ID)) return $actions;

    $actions['duplicate_post'] = sprintf(
        '<a href="%s">%s</a>',
        esc_url(wp_nonce_url(
            admin_url('admin-post.php?action=core_duplicate_post&post=' . $post->ID),
            'core_duplicate_post_' . $post->ID
        )),
        esc_html__('Duplicate', 'rear-theme')
    );

    return $actions;
}

add_action('admin_post_core_duplicate_post', 'core_handle_duplicate_post');
function core_handle_duplicate_post(): void
{
    $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
    if (!$post_id) wp_die(esc_html__('Missing post.', 'rear-theme'));

    check_admin_referer('core_duplicate_post_' . $post_id);

    if (!duplicate_post_enabled()) wp_die(esc_html__('Duplicate Post is not enabled.', 'rear-theme'));

    $post = get_post($post_id);
    if (!$post) wp_die(esc_html__('Post not found.', 'rear-theme'));

    if (!in_array($post->post_type, duplicate_post_allowed_post_types(), true)) {
        wp_die(esc_html__('Duplicating this post type is not allowed.', 'rear-theme'));
    }

    $post_type_object = get_post_type_object($post->post_type);
    if (!$post_type_object || !current_user_can($post_type_object->cap->create_posts)) {
        wp_die(esc_html__('Not allowed.', 'rear-theme'));
    }

    // Same instance-level check as the row-action render — the type-level
    // create_posts capability alone doesn't authorize reading/cloning this
    // SPECIFIC post.
    if (!current_user_can('edit_post', $post->ID)) {
        wp_die(esc_html__('Not allowed.', 'rear-theme'));
    }

    $new_id = core_duplicate_post($post);
    if (!$new_id) wp_die(esc_html__('Duplication failed.', 'rear-theme'));

    // Back to the list table the action was triggered from, not the new
    // draft's edit screen — the point of duplicating from the listing is to
    // keep working from that listing (bulk cloning several items in a row).
    $redirect = wp_get_referer() ?: admin_url('edit.php?post_type=' . $post->post_type);
    wp_safe_redirect(add_query_arg('duplicated_post', $new_id, $redirect));
    exit;
}

add_action('admin_notices', 'core_duplicate_post_admin_notice');
function core_duplicate_post_admin_notice(): void
{
    if (empty($_GET['duplicated_post'])) return;

    $new_id = (int) $_GET['duplicated_post'];
    $edit_link = get_edit_post_link($new_id);
    if (!$edit_link) return;

    printf(
        '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
        sprintf(
            /* translators: %s: link to the new draft */
            wp_kses(__('Post duplicated — <a href="%s">edit the copy</a>.', 'rear-theme'), ['a' => ['href' => []]]),
            esc_url($edit_link)
        )
    );
}

// Registered per allowed post type rather than once globally — WordPress's
// bulk-action hooks are named per list-table screen (bulk_actions-edit-{type}),
// there's no single hook that covers every post type at once.
add_action('current_screen', 'core_duplicate_post_register_bulk_action');
function core_duplicate_post_register_bulk_action(): void
{
    if (!duplicate_post_enabled()) return;

    foreach (duplicate_post_allowed_post_types() as $post_type) {
        add_filter("bulk_actions-edit-{$post_type}", 'core_duplicate_post_bulk_actions');
        add_filter("handle_bulk_actions-edit-{$post_type}", 'core_duplicate_post_handle_bulk_action', 10, 3);
    }
}

function core_duplicate_post_bulk_actions(array $actions): array
{
    $actions['core_duplicate_post_bulk'] = __('Duplicate', 'rear-theme');
    return $actions;
}

function core_duplicate_post_handle_bulk_action(string $redirect_to, string $doaction, array $post_ids): string
{
    if ($doaction !== 'core_duplicate_post_bulk' || !duplicate_post_enabled()) return $redirect_to;

    $duplicated = 0;

    foreach ($post_ids as $post_id) {
        $post = get_post($post_id);
        if (!$post || !in_array($post->post_type, duplicate_post_allowed_post_types(), true)) continue;

        $post_type_object = get_post_type_object($post->post_type);
        if (!$post_type_object || !current_user_can($post_type_object->cap->create_posts)) continue;

        // WordPress core already verifies the 'bulk-posts' nonce before this
        // filter runs, but that nonce only proves the request came from this
        // list-table screen — it says nothing about whether the current user
        // may touch EVERY post ID submitted alongside it. Re-checking the
        // instance-level capability per post, not just once for the request,
        // is exactly the check that was missing from Yoast Duplicate Post's
        // own bulk handler (CVE-2026-1217, Contributor+ could bulk-duplicate
        // posts they didn't own).
        if (!current_user_can('edit_post', $post->ID)) continue;

        if (core_duplicate_post($post)) $duplicated++;
    }

    return add_query_arg('bulk_duplicated_posts', $duplicated, $redirect_to);
}

add_action('admin_notices', 'core_duplicate_post_bulk_admin_notice');
function core_duplicate_post_bulk_admin_notice(): void
{
    if (!isset($_GET['bulk_duplicated_posts'])) return;

    $count = (int) $_GET['bulk_duplicated_posts'];
    if ($count < 1) return;

    printf(
        '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
        esc_html(sprintf(
            /* translators: %d: number of duplicated posts */
            _n('%d post duplicated.', '%d posts duplicated.', $count, 'rear-theme'),
            $count
        ))
    );
}

function core_duplicate_post(WP_Post $post): int
{
    // wp_insert_post() calls wp_unslash() on every field internally (it
    // expects $_POST-shaped, slashed input) — passing already-unslashed
    // content through unwrapped corrupts any literal backslash in it, e.g.
    // the \uXXXX escapes ACF blocks store in post_content's JSON comments.
    $new_id = wp_insert_post(wp_slash([
        'post_author' => get_current_user_id(),
        'post_content' => $post->post_content,
        'post_excerpt' => $post->post_excerpt,
        /* translators: %s: original post title */
        'post_title' => sprintf(__('%s (Copy)', 'rear-theme'), $post->post_title),
        'post_status' => 'draft',
        'post_type' => $post->post_type,
        'post_parent' => $post->post_parent,
        'menu_order' => $post->menu_order,
        'comment_status' => $post->comment_status,
        'ping_status' => $post->ping_status,
    ]));

    if (is_wp_error($new_id) || !$new_id) return 0;

    foreach (get_post_meta($post->ID) as $key => $values) {
        if ($key === '_wp_old_slug') continue; // no reason for the clone to carry the original's redirect history

        foreach ($values as $value) {
            add_post_meta($new_id, $key, maybe_unserialize($value));
        }
    }

    foreach (get_object_taxonomies($post->post_type) as $taxonomy) {
        $terms = wp_get_object_terms($post->ID, $taxonomy, ['fields' => 'ids']);
        if (!is_wp_error($terms)) wp_set_object_terms($new_id, $terms, $taxonomy);
    }

    return $new_id;
}
