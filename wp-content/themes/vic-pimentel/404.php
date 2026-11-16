<?php

$default_404 = options('default_pages')['error_404'] ?? null;

get_header();
?>
    <main class="main-content">
        <?php if (isset($default_404->ID)): ?>
            <?= apply_filters('the_content', get_post($default_404->ID)->post_content); ?>
        <?php else: ?>
            <section class="not-found">
                <?php component('scene', ['key' => 'login']); ?>
                <div class="wrapper not-found-inner">
                    <h1 class="not-found-title"><?= vp_emphasis(__('Page *not found*', 'rear-theme')) ?></h1>
                    <p class="not-found-text"><?= esc_html__('The page you are looking for doesn’t exist or has moved.', 'rear-theme') ?></p>
                    <?php component('button', ['style' => 'pill', 'link' => ['title' => __('Back to work', 'rear-theme'), 'url' => home_url('/')]]); ?>
                </div>
            </section>
        <?php endif ?>
    </main>
<?php
get_footer();
