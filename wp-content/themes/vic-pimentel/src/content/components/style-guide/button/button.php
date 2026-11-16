<?php
/** @var $args */

/*
 * Styles (Figma): 'pill' (about-button), 'glass' (login-button, full width),
 * 'text' (button-1 "View All" + chevron), 'download' (download-link + icon).
 */
$default_args = [
    'type' => 'link',
    'style' => 'pill',
    'link' => ['url' => '#'],
    'title' => '',
    'label' => '',
    'icon' => '',
    'submit' => false,
];

// Add custom static class
$args['class'] = 'button ' . ($args['class'] ?? '');

// Format the link fields from the data (Custom Field: Button)
if (!empty($args['data'])) {
    $args['style'] = $args['data']['style'] ?? ($args['style'] ?? 'pill');

    if (isset($args['data']['link_type']) && $args['data']['link_type'] === 'post' && !empty($args['data']['post'])) {
        $args['link'] = [
            'title' => !empty($args['data']['custom_label']) ? $args['data']['custom_label'] : get_the_title($args['data']['post']),
            'url' => get_permalink($args['data']['post']),
            'target' => '',
        ];
    } else {
        if (empty($args['data']['link']['url'])) return;

        $args['link'] = [
            'title' => $args['data']['link']['title'] ?? '',
            'url' => $args['data']['link']['url'],
            'target' => $args['data']['link']['target'] ?? '',
        ];
    }

    unset($args['data']);
}

// Merge default args with passed args
$args = wp_parse_args($args, $default_args);

// Bail if it's a link but no URL was provided.
if ($args['type'] === 'link' && empty($args['link']['url'])) return;

// Default icons per style.
if (empty($args['icon'])) {
    $args['icon'] = match ($args['style']) {
        'text' => 'icons/chevron',
        'download' => 'icons/download',
        default => '',
    };
}

// Define the tag name based on the type.
$args['html_tag'] = $args['type'] === 'link' ? 'a' : 'button';

// Add the style class.
$args['class'] .= ' style-' . $args['style'];

if (!empty($args['icon'])) {
    $args['class'] .= ' has-icon';
}

$args['attr'] = $args['attr'] ?? [];

if (!empty($args['label'])) {
    $args['attr']['aria-label'] = $args['label'];
}

if ($args['type'] === 'link') {
    $args['attr']['href'] = $args['link']['url'];

    if (!empty($args['link']['target'])) {
        $args['attr']['target'] = $args['link']['target'];

        if ($args['link']['target'] === '_blank') {
            $args['attr']['rel'] = 'noopener noreferrer';
        }
    }
} else {
    $args['attr']['type'] = $args['submit'] ? 'submit' : 'button';
}

// Start html
ob_start();
?>
    <?php if (!empty($args['link']['title'])): ?>
        <span class="button-text"><?= esc_html($args['link']['title']) ?></span>
    <?php endif ?>

    <?php if (!empty($args['icon'])): ?>
        <span class="button-icon"><?php print_svg($args['icon']); ?></span>
    <?php endif ?>
<?php
// Render the component
render_component(ob_get_clean(), $args);
