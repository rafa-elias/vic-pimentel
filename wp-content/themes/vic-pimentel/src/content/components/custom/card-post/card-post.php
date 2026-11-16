<?php
/** @var $args */

// Project card (Figma: post-card). Pass `post_id`, or pre-built `card` data
// (vp_project_card_data()). Inspired by mobbin's listing cards: glass box with the
// inset thumbnail; a video thumbnail plays on hover and gets a play/pause toggle.
$default_args = [
    'html_tag' => 'article',
    'post_id' => 0,
    'card' => null,
    'heading_tag' => 'h3',
    'sizes_attr' => '(min-width: 1280px) 25vw, (min-width: 600px) 50vw, 100vw',
];

// Add custom static class
$args['class'] = 'card-post js-media-hover ' . ($args['class'] ?? '');

// Merge default args with passed args
$args = wp_parse_args($args, $default_args);

$card = $args['card'] ?: ($args['post_id'] ? vp_project_card_data((int) $args['post_id']) : null);

// Bail if there's no project.
if (empty($card)) return;

// Start html
ob_start();
?>
    <div class="card-media-box">
        <?php component('media', $card['media'], [
            'mode' => 'hover',
            'show_toggle' => true,
            'max_size' => 'medium',
            'sizes_attr' => $args['sizes_attr'],
            'class' => 'card-thumb',
        ]); ?>
    </div>

    <div class="card-meta">
        <?php if (!empty($card['favicon'])): ?>
            <?php component('image', $card['favicon'], [
                'max_size' => 'thumbnail',
                'html_tag' => 'div',
                'class' => 'card-favicon',
                'is_priority_candidate' => false,
            ]); ?>
        <?php endif ?>

        <p class="card-labels">
            <?php if ($card['category']): ?>
                <span class="card-category"><?= esc_html($card['category']) ?></span>
                <span class="card-in"><?= esc_html__('in', 'rear-theme') ?></span>
            <?php endif ?>
            <span class="card-company"><?= esc_html($card['company']) ?></span>
        </p>
    </div>

    <<?= $args['heading_tag'] ?> class="sr-only"><?= esc_html($card['title']) ?></<?= $args['heading_tag'] ?>>

    <a class="card-link" href="<?= esc_url($card['url']) ?>">
        <span class="sr-only"><?= esc_html(sprintf(__('View project: %s', 'rear-theme'), $card['title'])) ?></span>
    </a>
<?php
// Render the component
render_component(ob_get_clean(), $args);
