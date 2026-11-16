<?php
/** @var $args */

$default_args = [
    'text' => '',
    'style' => 'dark'
];

// Add custom static class
$args['class'] = 'rich-text ' . $args['class'] ?? '';

// Merge default args with passed args
$args = wp_parse_args($args, $default_args);

// Return if text is empty
if (empty($args['text'])) return;

// Add the style class.
$args['class'] .= 'style-' . $args['style'];

// Start html
ob_start();

echo $args['text'];

// Render the component
render_component(ob_get_clean(), $args);
