<?php
// Vars
/** @var $block */
$fields = get_fields();

// Optional: extract primary data and bail early if missing.
// $items = $fields['items'];
// if (empty($items)) return;

// Start html
ob_start();
?>
    <div class="wrapper">
        <div class="header">
            <?php if (isset($fields['overline']) && $fields['overline']): ?>
                <p class="overline"><?= $fields['overline'] ?></p>
            <?php endif ?>

            <?php if (isset($fields['title'])) component('headline', $fields['title']) ?>
        </div>

        <?php
        if (isset($fields['content']) && $fields['content']) {
            component('rich-text', ['text' => $fields['content']]);
        }

        if (isset($fields['buttons']['buttons']) && $fields['buttons']['buttons']) {
            component('buttons', ['buttons' => $fields['buttons']['buttons']]);
        }
        ?>
    </div>
<?php
// Render the block
render(ob_get_clean(), $block);
