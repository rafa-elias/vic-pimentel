<?php
/** @var $args */

// Highlight card (Figma: section-thumb). Opens its media in the media modal.
$default_args = [
    'html_tag' => 'button',
    'title' => '',
    'media' => [],
    'modal_id' => '',
];

// Add custom static class
$args['class'] = 'card-section js-media-hover ' . ($args['class'] ?? '');

// Merge default args with passed args
$args = wp_parse_args($args, $default_args);

if (empty($args['media']['image']) || empty($args['modal_id'])) return;

$args['attr'] = [
    'type' => 'button',
    'data-media-modal' => $args['modal_id'],
    'aria-haspopup' => 'dialog',
    'aria-label' => sprintf(__('Open %s', 'rear-theme'), $args['title']),
];

// Start html
ob_start();
?>
    <span class="card-media-box">
        <?php component('media', $args['media'], [
            'mode' => 'hover',
            'html_tag' => 'span',
            'max_size' => 'medium',
            'sizes_attr' => '(min-width: 1024px) 33vw, (min-width: 600px) 50vw, 100vw',
            'class' => 'card-thumb',
        ]); ?>

        <span class="card-expand" aria-hidden="true"><?php print_svg('icons/expand'); ?></span>
    </span>

    <?php if ($args['title']): ?>
        <span class="card-title"><?= esc_html($args['title']) ?></span>
    <?php endif ?>
<?php
// Render the component
render_component(ob_get_clean(), $args);
