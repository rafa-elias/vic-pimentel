<?php
/** @var $args */

// Footer (Figma: Navigation → footer). Fluid with no max width; animated background.
$default_args = [
    'html_tag' => 'footer',
];

// Add custom static class
$args['class'] = 'footer ' . ($args['class'] ?? '');

// Merge default args with passed args
$args = wp_parse_args($args, $default_args);

$settings = options('footer') ?: [];
$year = wp_date('Y');
$fill_year = fn($text) => str_replace('{year}', $year, (string) $text);

$args['attr'] = ['id' => 'contact'];

// Start html
ob_start();
?>
    <div class="footer-panel">
        <?php component('scene', ['key' => 'footer']); ?>

        <div class="footer-row footer-contact">
            <?php if (!empty($settings['heading'])): ?>
                <h2 class="footer-heading"><?= vp_emphasis($settings['heading']) ?></h2>
            <?php endif ?>

            <?php component('copy-email', ['email' => $settings['email'] ?? '']); ?>
        </div>

        <?php if (!empty($settings['columns'])): ?>
            <div class="footer-row footer-columns">
                <?php foreach ($settings['columns'] as $column): ?>
                    <nav class="footer-column" aria-label="<?= esc_attr($column['title']) ?>">
                        <p class="footer-column-title"><?= esc_html($column['title']) ?></p>

                        <?php if (!empty($column['links'])): ?>
                            <ul class="footer-links">
                                <?php foreach ($column['links'] as $item):
                                    if (empty($item['link']['url'])) continue;
                                    $is_download = !empty($item['is_download']);
                                    ?>
                                    <li>
                                        <a
                                            class="footer-link<?= $is_download ? ' is-download' : '' ?>"
                                            href="<?= esc_url($item['link']['url']) ?>"
                                            <?= !empty($item['link']['target']) ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                                            <?= $is_download ? 'download' : '' ?>>
                                            <span><?= esc_html($item['link']['title']) ?></span>
                                            <?php if ($is_download): ?>
                                                <span class="footer-link-icon"><?php print_svg('icons/download'); ?></span>
                                            <?php endif ?>
                                        </a>
                                    </li>
                                <?php endforeach ?>
                            </ul>
                        <?php endif ?>
                    </nav>
                <?php endforeach ?>
            </div>
        <?php endif ?>

        <div class="footer-row footer-bottom">
            <p class="footer-signature"><?= vp_emphasis($settings['signature'] ?? '') ?></p>

            <?php if (!empty($settings['text'])): ?>
                <p class="footer-text"><?= esc_html($fill_year($settings['text'])) ?></p>
            <?php endif ?>

            <p class="footer-label"><?= vp_emphasis($fill_year($settings['label'] ?? '')) ?></p>
        </div>
    </div>
<?php
// Render the component
render_component(ob_get_clean(), $args);
