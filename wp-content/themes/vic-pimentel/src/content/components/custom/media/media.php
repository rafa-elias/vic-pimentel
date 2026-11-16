<?php
/** @var $args */

/*
 * Image (required) + optional looping video. When a video exists it is shown over the
 * image, which stays as poster/fallback (reduced motion, slow networks).
 *
 * mode:
 *  - 'autoplay' : video loads and plays while in view (post hero, body media, modal)
 *  - 'hover'    : video loads/plays while the pointer is over `.js-media-hover` (cards)
 * show_toggle: play/pause button (Figma thumb-video), top-right.
 */
$default_args = [
    'image' => [],
    'video' => [],
    'mode' => 'autoplay',
    'show_toggle' => false,
    'max_size' => 'large',
    'sizes_attr' => null,
    'lazyload' => true,
    'is_priority_candidate' => true,
];

// Add custom static class
$args['class'] = 'media ' . ($args['class'] ?? '');

// Merge default args with passed args
$args = wp_parse_args($args, $default_args);

$has_video = !empty($args['video']['url']);

// No image falls back to the Default Image option (Site Settings).
$image = $args['image'] ?: [];

$args['class'] .= ' mode-' . $args['mode'];

if ($has_video) {
    $args['class'] .= ' has-video';
}

// Start html
ob_start();

component('image', $image, [
    'max_size' => $args['max_size'],
    'sizes_attr' => $args['sizes_attr'],
    'lazyload' => $args['lazyload'],
    'use_default_image' => true,
    'is_priority_candidate' => $args['is_priority_candidate'],
    'html_tag' => ($args['html_tag'] ?? 'div') === 'span' ? 'span' : 'div',
    'class' => 'media-image',
]);

if ($has_video): ?>
    <video class="media-video js-media-video" muted loop playsinline preload="none" disablepictureinpicture aria-hidden="true" tabindex="-1">
        <source data-src="<?= esc_url($args['video']['url']) ?>" type="<?= esc_attr($args['video']['mime_type'] ?? 'video/mp4') ?>">
    </video>

    <?php if ($args['show_toggle']): ?>
        <button type="button" class="media-toggle js-media-toggle" aria-label="<?= esc_attr__('Play video', 'rear-theme') ?>" data-label-play="<?= esc_attr__('Play video', 'rear-theme') ?>" data-label-pause="<?= esc_attr__('Pause video', 'rear-theme') ?>">
            <span class="icon-play"><?php print_svg('icons/play'); ?></span>
            <span class="icon-pause"><?php print_svg('icons/pause'); ?></span>
        </button>
    <?php endif ?>
<?php endif;

// Render the component
render_component(ob_get_clean(), $args);
