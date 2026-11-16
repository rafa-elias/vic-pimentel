<?php
/**
 * Portfolio gate document. Variables: $state ('login'|'loading'), $state_data.
 *
 * @var string $state
 * @var array $state_data
 */

$title = $state === 'loading'
    ? vp_access_setting('loading_title', __('This Contains Confidential Information', 'rear-theme'))
    : vp_access_setting('title', __('Welcome To My Portfolio!', 'rear-theme'));
$text = $state === 'loading' ? vp_access_setting('loading_text') : vp_access_setting('text');
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <?php if ($state === 'loading'): ?>
        <meta http-equiv="refresh" content="<?= (int) $state_data['seconds'] + 2 ?>;url=<?= esc_url($state_data['target']) ?>">
    <?php endif ?>
    <title><?= esc_html(wp_strip_all_tags($title) . ' | ' . get_bloginfo('name')) ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<div class="is-root-container">
    <main class="gate">
        <?php component('scene', ['key' => 'login', 'interactive' => false]); ?>

        <div class="gate-inner">
            <?php if ($state === 'loading'): ?>
                <div
                    class="gate-loading js-gate-loading"
                    data-target="<?= esc_url($state_data['target']) ?>"
                    data-seconds="<?= (int) $state_data['seconds'] ?>"
                    role="status">
                    <svg class="gate-progress" viewBox="0 0 40 40" aria-hidden="true" style="--duration: <?= (int) $state_data['seconds'] ?>s">
                        <circle class="gate-progress-track" cx="20" cy="20" r="18.5"/>
                        <circle class="gate-progress-bar" cx="20" cy="20" r="18.5" pathLength="100"/>
                    </svg>

                    <div class="gate-copy">
                        <h1 class="gate-title"><?= vp_emphasis($title) ?></h1>
                        <?php if ($text): ?>
                            <p class="gate-text"><?= esc_html($text) ?></p>
                        <?php endif ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="gate-copy">
                    <h1 class="gate-title"><?= vp_emphasis($title) ?></h1>
                    <?php if ($text): ?>
                        <p class="gate-text"><?= esc_html($text) ?></p>
                    <?php endif ?>
                </div>

                <form class="gate-form js-gate-form" method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>" data-ajax-url="<?= esc_url(admin_url('admin-ajax.php')) ?>" novalidate>
                    <input type="hidden" name="action" value="<?= esc_attr(VP_ACCESS_ACTION) ?>">
                    <input type="hidden" name="redirect_to" value="<?= esc_url($state_data['redirect']) ?>">
                    <?php wp_nonce_field(VP_ACCESS_ACTION, '_vp_access_nonce', false); ?>

                    <label class="sr-only" for="vp-password"><?= esc_html__('Password', 'rear-theme') ?></label>
                    <input
                        class="gate-input<?= $state_data['error'] ? ' has-error' : '' ?>"
                        id="vp-password"
                        name="vp_password"
                        type="password"
                        autocomplete="current-password"
                        required
                        autofocus
                        placeholder="<?= esc_attr(vp_access_setting('placeholder', __('Password', 'rear-theme'))) ?>"
                        aria-describedby="vp-password-error"
                        <?= $state_data['error'] ? 'aria-invalid="true"' : '' ?>>

                    <p class="gate-error js-gate-error" id="vp-password-error" role="alert"<?= $state_data['error'] || $state_data['locked'] ? '' : ' hidden' ?>>
                        <?= esc_html($state_data['locked'] ? vp_access_locked_message() : $state_data['error']) ?>
                    </p>

                    <?php component('button', [
                        'type' => 'button',
                        'submit' => true,
                        'style' => 'glass',
                        'link' => ['title' => vp_access_setting('button', __('Access Now', 'rear-theme'))],
                    ]); ?>
                </form>

                <?php $email = vp_access_setting('contact_email'); ?>
                <?php if ($email): ?>
                    <p class="gate-contact">
                        <?= esc_html(vp_access_setting('contact_text', __("If you don't have a password contact me", 'rear-theme'))) ?>
                        <?php component('copy-email', ['email' => $email, 'style' => 'inline']); ?>
                    </p>
                <?php endif ?>
            <?php endif ?>
        </div>
    </main>
</div>
<?php wp_footer(); ?>
</body>
</html>
