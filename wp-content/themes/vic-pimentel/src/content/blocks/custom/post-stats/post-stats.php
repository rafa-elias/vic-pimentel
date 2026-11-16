<?php
/** @var $block */
$fields = get_fields() ?: [];
$stats = array_slice(array_filter($fields['stats'] ?? [], fn($stat) => !empty($stat['value'])), 0, 3);

if (empty($stats)) return;

ob_start();
?>
    <div class="wrapper ember-entrance-group">
        <dl class="column-content post-stats-row ember-entrance">
            <?php foreach ($stats as $stat): ?>
                <div class="post-stat">
                    <?php if (!empty($stat['overline'])): ?>
                        <dt class="post-stat-overline"><?= esc_html($stat['overline']) ?></dt>
                    <?php endif ?>
                    <dd class="post-stat-value"><?= esc_html($stat['value']) ?></dd>
                    <?php if (!empty($stat['text'])): ?>
                        <dd class="post-stat-text"><?= esc_html($stat['text']) ?></dd>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </dl>
    </div>
<?php
render(ob_get_clean(), $block);
