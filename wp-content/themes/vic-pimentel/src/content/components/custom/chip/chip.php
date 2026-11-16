<?php
/** @var $args */

// Outlined label (Figma: about-label) — Skills & Tools.
$default_args = [
    'html_tag' => 'li',
    'label' => '',
];

// Add custom static class
$args['class'] = 'chip ' . ($args['class'] ?? '');

// Merge default args with passed args
$args = wp_parse_args($args, $default_args);

if (empty($args['label'])) return;

render_component(esc_html($args['label']), $args);
