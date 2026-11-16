<?php
/** @var $args */

/*
 * Header (Figma: Navigation → header). Logo + info only live at the top of the page;
 * the nav pill is the only sticky element — top-centre from 1280px, a bottom bar below.
 * It hides while scrolling down and reappears on scroll up (header.js).
 */
$default_args = [
    'html_tag' => 'header',
];

// Add custom static class
$args['class'] = 'header ' . ($args['class'] ?? '');

// Merge default args with passed args
$args = wp_parse_args($args, $default_args);

$settings = options('header') ?: [];
$items = get_menu_items('header') ?: [];
$home = untrailingslashit(home_url('/'));
$path_of = fn($url) => untrailingslashit((string) wp_parse_url($url, PHP_URL_PATH)) ?: '/';
$current_path = $path_of($_SERVER['REQUEST_URI'] ?? '/');

// "Work" (the home link) is the active item on the home page and on every project.
$work_active = is_front_page() || is_home() || is_singular('post') || is_category();

// Start html
ob_start();
?>
    <div class="header-top">
        <a class="header-logo" href="<?= esc_url(home_url('/')) ?>" aria-label="<?= esc_attr(get_bloginfo('name')) ?>">
            <?php if (!empty($settings['logo']['url'])): ?>
                <img src="<?= esc_url($settings['logo']['url']) ?>" alt="" width="186" height="36">
            <?php else: ?>
                <?php print_svg('logo'); ?>
            <?php endif ?>
        </a>

        <div class="header-info">
            <?php if (!empty($settings['location_title']) || !empty($settings['location_subtitle'])): ?>
                <p class="header-location">
                    <span class="title"><?= esc_html($settings['location_title'] ?? '') ?></span>
                    <span class="subtitle"><?= esc_html($settings['location_subtitle'] ?? '') ?></span>
                </p>
            <?php endif ?>
        </div>
    </div>

    <?php if ($items): ?>
        <nav class="header-nav js-header-nav" aria-label="<?= esc_attr__('Main', 'rear-theme') ?>">
            <ul class="nav-pill">
                <?php foreach ($items as $item):
                    $url = untrailingslashit($item['url']);
                    $is_anchor = str_starts_with((string) $item['url'], '#');
                    $is_current = !$is_anchor && (
                        ($url === $home && $work_active)
                        || ($url !== $home && $path_of($item['url']) === $current_path)
                    );
                    ?>
                    <li class="nav-item<?= $is_current ? ' is-current' : '' ?>">
                        <a
                            class="nav-link"
                            href="<?= esc_url($item['url']) ?>"
                            <?= $item['target'] ? 'target="' . esc_attr($item['target']) . '"' : '' ?>
                            <?= $is_current ? 'aria-current="page"' : '' ?>>
                            <?= esc_html($item['title']) ?>
                        </a>
                    </li>
                <?php endforeach ?>
            </ul>
        </nav>
    <?php endif ?>
<?php
// Render the component
render_component(ob_get_clean(), $args);
