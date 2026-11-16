<?php
/** @var $args */

/*
 * Copy-to-clipboard email (Figma: copy-email / copy-button).
 * style 'box'    → email + "Copy" button; button shows "Copied" for ~2s.
 * style 'inline' → the email link itself flips to "Copied" for ~2s (login screen).
 */
$default_args = [
    'email' => '',
    'style' => 'box',
    'html_tag' => 'div',
];

// Add custom static class
$args['class'] = 'copy-email ' . ($args['class'] ?? '');

// Merge default args with passed args
$args = wp_parse_args($args, $default_args);

$email = sanitize_email($args['email']);

if (!$email) return;

$args['class'] .= ' style-' . $args['style'];
$args['attr'] = [
    'data-email' => $email,
    'data-copied' => __('Copied', 'rear-theme'),
];

if ($args['style'] === 'inline') {
    $args['html_tag'] = 'span';
}

// Start html
ob_start();
?>
    <?php if ($args['style'] === 'inline'): ?>
        <a class="copy-email-link js-copy-email" href="mailto:<?= esc_attr($email) ?>"><span class="js-copy-label"><?= esc_html($email) ?></span></a>
    <?php else: ?>
        <a class="copy-email-address" href="mailto:<?= esc_attr($email) ?>"><?= esc_html($email) ?></a>

        <button type="button" class="copy-email-button js-copy-email">
            <span class="copy-icon icon-copy"><?php print_svg('icons/copy'); ?></span>
            <span class="copy-icon icon-check"><?php print_svg('icons/check'); ?></span>
            <span class="js-copy-label"><?= esc_html__('Copy', 'rear-theme') ?></span>
        </button>
    <?php endif ?>

    <span class="sr-only js-copy-status" role="status" aria-live="polite"></span>
<?php
// Render the component
render_component(ob_get_clean(), $args);
