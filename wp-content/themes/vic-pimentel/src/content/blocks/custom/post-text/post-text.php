<?php
/** @var $block */
$fields = get_fields() ?: [];

if (empty($fields['text'])) return;

ob_start();
?>
    <div class="wrapper ember-entrance-group">
        <div class="column-content ember-entrance">
            <?php if (!empty($fields['heading'])): ?>
                <h2 class="post-text-heading"><?= vp_emphasis($fields['heading']) ?></h2>
            <?php endif ?>

            <?php component('rich-text', ['text' => $fields['text'], 'class' => 'post-text-copy']); ?>
        </div>
    </div>
<?php
render(ob_get_clean(), $block);
