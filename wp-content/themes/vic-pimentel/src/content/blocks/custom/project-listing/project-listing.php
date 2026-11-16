<?php
/** @var $block */
$fields = get_fields() ?: [];
$fields = wp_parse_args($fields, [
    'show_featured' => true,
    'featured_label' => '',
    'categories' => [],
    'featured_posts' => [],
    'results_label' => 'Showing { %d Results }',
]);

$tabs = vp_listing_tabs($fields);

if (empty($tabs)) return;

// A ?tab=slug in the URL opens that tab (shareable / back-button friendly).
$requested = sanitize_title(wp_unslash($_GET['tab'] ?? ''));
$active = $tabs[0];

foreach ($tabs as $tab) {
    if ($requested && $tab['slug'] === $requested) $active = $tab;
}

$featured = vp_clean_project_ids((array) $fields['featured_posts']);
$ids = vp_listing_ids($active['key'] === 'featured' ? 'featured' : (int) $active['key'], $featured);
$grid_id = 'projects-' . $block['id'];
$label = $fields['results_label'] ?: 'Showing { %d Results }';

ob_start();
?>
    <div class="wrapper ember-entrance-group">
        <div class="listing-bar ember-entrance">
            <div class="listing-tabs" role="tablist" aria-label="<?= esc_attr__('Project categories', 'rear-theme') ?>" data-featured="<?= esc_attr(implode(',', $featured)) ?>">
                <?php foreach ($tabs as $tab): $selected = $tab['key'] === $active['key']; ?>
                    <button
                        type="button"
                        role="tab"
                        class="listing-tab js-listing-tab<?= $selected ? ' is-active' : '' ?>"
                        aria-selected="<?= $selected ? 'true' : 'false' ?>"
                        aria-controls="<?= esc_attr($grid_id) ?>"
                        tabindex="<?= $selected ? '0' : '-1' ?>"
                        data-tab="<?= esc_attr($tab['key']) ?>"
                        data-slug="<?= esc_attr($tab['slug']) ?>">
                        <?= esc_html($tab['label']) ?>
                    </button>
                <?php endforeach ?>
            </div>

            <p class="listing-results js-listing-results" aria-live="polite" data-label="<?= esc_attr($label) ?>">
                <?= esc_html(sprintf($label, count($ids))) ?>
            </p>
        </div>

        <ul class="listing-grid js-listing-grid ember-entrance" id="<?= esc_attr($grid_id) ?>" role="tabpanel" aria-busy="false">
            <?= vp_listing_cards_html($ids) ?>
        </ul>
    </div>
<?php
render(ob_get_clean(), $block);
