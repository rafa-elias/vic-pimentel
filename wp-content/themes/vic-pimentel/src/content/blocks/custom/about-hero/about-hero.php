<?php
/** @var $block */
$fields = get_fields() ?: [];

if (empty($fields['headline'])) return;

$image = $fields['image'] ?? [];
// GIFs must be served at their original size — WP's resized copies lose the animation.
$is_gif = ($image['mime_type'] ?? '') === 'image/gif';

ob_start();
?>
    <div class="page-light" aria-hidden="true">
        <div class="page-light-sticky"><?php component('scene', ['key' => 'light']); ?></div>
    </div>

    <div class="wrapper ember-entrance-group">
        <div class="about-media ember-entrance">
            <?php component('image', $image, [
                'max_size' => $is_gif ? 'full' : 'small',
                'html_tag' => 'div',
                'class' => 'about-image',
                'use_default_image' => true,
                'sizes_attr' => $is_gif ? null : '(min-width: 1024px) 300px, 190px',
            ]); ?>
        </div>

        <div class="about-content ember-entrance">
            <h1 class="about-headline"><?= vp_emphasis($fields['headline']) ?></h1>
            <?php component('rich-text', ['text' => $fields['text'] ?? '', 'class' => 'about-text']); ?>
        </div>
    </div>
<?php
render(ob_get_clean(), $block);
