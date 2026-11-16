<?php
/** @var $block */
$fields = get_fields() ?: [];
$items = array_slice(array_filter($fields['items'] ?? [], fn($item) => !empty($item['image'])), 0, 2);

if (empty($items)) return;

$show_captions = !isset($fields['show_captions']) || $fields['show_captions'];

ob_start();
?>
    <div class="wrapper ember-entrance-group">
        <div class="column-media post-media-pair-grid ember-entrance">
            <?php foreach ($items as $item): ?>
                <figure class="post-media-pair-item">
                    <div class="post-media-box">
                        <?php component('media', vp_media($item['image'], $item['video'] ?? null), [
                            'max_size' => 'medium',
                            'sizes_attr' => '(min-width: 600px) 451px, 100vw',
                            'class' => 'post-media-item',
                        ]); ?>
                    </div>

                    <?php if ($show_captions && !empty($item['caption'])): ?>
                        <figcaption class="post-media-caption"><?= esc_html($item['caption']) ?></figcaption>
                    <?php endif ?>
                </figure>
            <?php endforeach ?>
        </div>
    </div>
<?php
render(ob_get_clean(), $block);
