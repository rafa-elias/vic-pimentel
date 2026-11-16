<?php
/** @var $block */
$fields = get_fields() ?: [];

if (empty($fields['text'])) return;

ob_start();
?>
    <div class="wrapper ember-entrance-group">
        <p class="column-content post-intro-text ember-entrance"><?= nl2br(esc_html($fields['text'])) ?></p>
    </div>
<?php
render(ob_get_clean(), $block);
