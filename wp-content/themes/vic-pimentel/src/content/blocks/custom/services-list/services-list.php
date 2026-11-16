<?php
/** @var $block */
$fields = get_fields() ?: [];
$items = array_filter($fields['items'] ?? [], fn($item) => !empty($item['title']));

if (empty($items)) return;

ob_start();
?>
    <div class="wrapper ember-entrance-group">
        <div class="column-content">
            <?php if (!empty($fields['heading'])): ?>
                <h2 class="services-heading ember-entrance"><?= vp_emphasis($fields['heading']) ?></h2>
            <?php endif ?>

            <ol class="services-items ember-entrance">
                <?php foreach (array_values($items) as $index => $item): ?>
                    <li class="services-item">
                        <h3 class="services-title">
                            <span class="services-number">[<?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?>]</span>
                            <?= esc_html($item['title']) ?>
                        </h3>

                        <?php if (!empty($item['text'])): ?>
                            <p class="services-text"><?= nl2br(esc_html($item['text'])) ?></p>
                        <?php endif ?>
                    </li>
                <?php endforeach ?>
            </ol>
        </div>
    </div>
<?php
render(ob_get_clean(), $block);
