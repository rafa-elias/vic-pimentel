<?php
/** @var $block */
$fields = get_fields() ?: [];

if (empty($fields['image'])) return;

$caption = !empty($fields['show_caption']) ? ($fields['caption'] ?? '') : '';

ob_start();
?>
    <div class="wrapper ember-entrance-group">
        <figure class="column-media ember-entrance">
            <div class="post-media-box">
                <?php component('media', vp_media($fields['image'], $fields['video'] ?? null), [
                    'max_size' => 'large',
                    'sizes_attr' => '(min-width: 1024px) 950px, 100vw',
                    'class' => 'post-media-item',
                ]); ?>
            </div>

            <?php if ($caption): ?>
                <figcaption class="post-media-caption"><?= esc_html($caption) ?></figcaption>
            <?php endif ?>
        </figure>
    </div>
<?php
render(ob_get_clean(), $block);
