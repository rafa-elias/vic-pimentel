<?php
/** @var $block */
$fields = get_fields() ?: [];
$rows = array_filter($fields['rows'] ?? [], fn($row) => !empty($row['label']) || !empty($row['title']));

if (empty($rows)) return;

ob_start();
?>
    <div class="wrapper ember-entrance-group">
        <ol class="timeline-rows ember-entrance">
            <?php foreach ($rows as $row): ?>
                <li class="timeline-row">
                    <h2 class="timeline-label"><?= esc_html($row['label']) ?></h2>

                    <div class="timeline-body">
                        <?php if (!empty($row['title'])): ?>
                            <h3 class="timeline-title"><?= esc_html($row['title']) ?></h3>
                        <?php endif ?>

                        <?php component('rich-text', ['text' => $row['text'] ?? '', 'class' => 'timeline-text']); ?>
                    </div>

                    <?php if (!empty($row['years'])): ?>
                        <p class="timeline-years"><?= esc_html($row['years']) ?></p>
                    <?php endif ?>
                </li>
            <?php endforeach ?>
        </ol>
    </div>
<?php
render(ob_get_clean(), $block);
