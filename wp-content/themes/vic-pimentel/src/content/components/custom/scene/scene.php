<?php
/** @var $args */

/*
 * Animated background (Unicorn Studio scene) with a static CSS fallback.
 *
 * key: 'hero' (mouse-tracking flow), 'footer' (mouse-tracking flow), 'login' (flow, no
 * mouse), 'light' (looping top light on post/about). Each scene is either the JSON exported
 * from Unicorn Studio or a published embed ID (+ optional variables), set on Site Settings →
 * Animations; until one is set, the static fallback renders.
 */
$default_args = [
    'key' => 'hero',
    'interactive' => null,
];

// Add custom static class
$args['class'] = 'scene ' . ($args['class'] ?? '');

// Merge default args with passed args
$args = wp_parse_args($args, $default_args);

$key = sanitize_key($args['key']);
$settings = options('animations') ?: [];
$scene = $settings[$key . '_scene'] ?? null;
$scene_url = is_array($scene) ? ($scene['url'] ?? '') : '';
$embed_id = $scene_url ? '' : preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($settings[$key . '_embed'] ?? ''));
$vars = trim((string) ($settings[$key . '_vars'] ?? ''));
$vars = $vars && is_array(json_decode($vars, true)) ? wp_json_encode(json_decode($vars, true)) : '';

// Victor's exports, shipped with the theme (src/animations/). Used when Site Settings →
// Animations has no upload/embed for that scene. Hero and login share the same scene.
$theme_scenes = [
    'hero' => 'login-homepage_background.json',
    'login' => 'login-homepage_background.json',
    'footer' => 'footer_background.json',
    'light' => 'about-resources_background.json',
];

if (!$scene_url && !$embed_id && isset($theme_scenes[$key]) && file_exists(get_template_directory() . '/src/animations/' . $theme_scenes[$key])) {
    $file = '/src/animations/' . $theme_scenes[$key];
    $scene_url = add_query_arg('v', filemtime(get_template_directory() . $file), get_template_directory_uri() . $file);
}

$has_scene = $scene_url || $embed_id;
// Mouse input follows each export's own settings; only the login screen forces it off
// (designer: "Don't follow the mouse here").
$interactive = $args['interactive'] ?? ($key !== 'login');

$args['class'] .= ' scene-' . $key;
$args['attr'] = ['aria-hidden' => 'true', 'data-key' => $key];

if ($has_scene) {
    $args['class'] .= ' has-scene';
    $args['attr']['data-sdk'] = !empty($settings['sdk_url']) ? $settings['sdk_url'] : VP_UNICORN_SDK;
} else {
    // Built-in WebGL stand-in (flow.js) until a Unicorn scene is set.
    $args['class'] .= ' has-flow';
    $args['attr']['data-interactive'] = $interactive ? '1' : '0';
}

// Start html
ob_start();
?>
    <span class="scene-fallback"></span>
    <span class="scene-grain"></span>

    <?php if ($has_scene): ?>
        <span
            class="scene-canvas js-scene-canvas"
            id="<?= esc_attr(uniqid('scene-')) ?>"
            <?= $scene_url ? 'data-us-project-src="' . esc_url($scene_url) . '"' : 'data-us-project="' . esc_attr($embed_id) . '"' ?>
            <?= $vars ? "data-us-vars='" . esc_attr($vars) . "'" : '' ?>
            data-us-scale="1"
            data-us-dpi="1.5"
            <?= $key === 'light' ? '' : 'data-us-lazyload="true"' ?>
            <?= $interactive ? '' : 'data-us-disablemouse="true"' ?>
            <?= $key === 'light' ? 'data-us-fixed="true"' : '' ?>></span>
    <?php endif ?>
<?php
// Render the component
render_component(ob_get_clean(), $args);
