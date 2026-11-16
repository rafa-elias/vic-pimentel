<?php
/** @var $args */

// Media modal (Figma: media-modal) — a native <dialog>, so focus trapping and Esc come
// for free. Opened by any element with data-media-modal="{id}" (card-section).
$default_args = [
    'html_tag' => 'dialog',
    'id' => '',
    'title' => '',
    'media' => [],
];

// Add custom static class
$args['class'] = 'media-modal js-media-modal ' . ($args['class'] ?? '');

// Merge default args with passed args
$args = wp_parse_args($args, $default_args);

if (empty($args['id']) || empty($args['media']['image'])) return;

$title_id = $args['id'] . '-title';

$args['attr'] = [
    'id' => $args['id'],
    'aria-labelledby' => $title_id,
    'data-lenis-prevent' => 'true',
];

// Start html
ob_start();
?>
    <div class="modal-panel">
        <div class="modal-bar">
            <p class="modal-title" id="<?= esc_attr($title_id) ?>"><?= esc_html($args['title']) ?></p>

            <button type="button" class="modal-close js-media-modal-close" aria-label="<?= esc_attr__('Close', 'rear-theme') ?>">
                <?php print_svg('icons/close'); ?>
            </button>
        </div>

        <?php component('media', $args['media'], [
            'mode' => 'autoplay',
            'max_size' => 'xlarge',
            'sizes_attr' => '(min-width: 1440px) 1000px, 90vw',
            'is_priority_candidate' => false,
            'class' => 'modal-media',
        ]); ?>
    </div>
<?php
// Render the component
render_component(ob_get_clean(), $args);
