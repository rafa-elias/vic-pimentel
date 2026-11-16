<?php

/**
 * Render component with custom parameters
 *
 * @param $content
 * @param $args
 * @return void
 */
function render_component($content, $args): void
{
    // Vars
    $name = $args['name'];
    $html_tag = $args['html_tag'] ?? 'div';
    $attr_string = '';

    // Return if there is no name or content
    if (empty($name) || empty($content)) {
        return;
    }

    wp_enqueue_style('style-component-' . $name);

    // Component class
    $class = 'component component-' . $name;
    $class .= !empty($args['class']) ? ' ' . $args['class'] : '';

    // Default attributes
    $default_attr = [
        'class' => $class,
        'data-component' => $name,
    ];

    // Merge default attributes with passed attributes
    $attr = !empty($args['attr']) ? wp_parse_args($args['attr'], $default_attr) : $default_attr;

    // Build the attribute string
    foreach ($attr as $key => $value) {
        if (!empty($value)) {
            $attr_string .= sprintf(' %s="%s"', esc_attr($key), esc_attr($value));
        }
    }

    // Return the complete HTML for the section
    echo sprintf('<%s%s>%s</%s>', $html_tag, $attr_string, $content, $html_tag);
}