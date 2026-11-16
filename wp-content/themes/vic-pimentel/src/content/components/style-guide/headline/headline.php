<?php
/** @var $args */

// Renders a heading. `text` supports *word* → <em>word</em> (italic serif emphasis).
$default_args = [
    'html_tag' => 'h2',
    'style' => 'default',
    'text' => '',
];

// Add custom static class
$args['class'] = 'headline ' . ($args['class'] ?? '');

// Merge default args with passed args
$args = wp_parse_args($args, $default_args);

// Bail if no text
if (empty($args['text'])) return;

// Add dynamic class
if ($args['style'] && $args['style'] !== 'default') {
    $args['class'] .= ' style-' . $args['style'];
}

// Start html
ob_start();

echo vp_emphasis($args['text']);

// Render the component
render_component(ob_get_clean(), $args);
