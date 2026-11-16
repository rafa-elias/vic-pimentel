<?php
/** @var $block */
$fields = get_fields() ?: [];
$rows = array_filter($fields['rows'] ?? [], fn($row) => !empty($row['label']) || !empty($row['value']));

if (empty($rows)) return;

ob_start();
?>
    <div class="wrapper ember-entrance-group">
        <div class="column-content ember-entrance">
            <div class="project-info-card">
                <h2 class="project-info-title"><?= esc_html($fields['title'] ?: __('Project Info', 'rear-theme')) ?></h2>

                <dl class="project-info-rows">
                    <?php foreach ($rows as $row): ?>
                        <div class="project-info-row">
                            <dt><?= esc_html($row['label']) ?></dt>
                            <dd><?= esc_html($row['value']) ?></dd>
                        </div>
                    <?php endforeach ?>
                </dl>
            </div>
        </div>
    </div>
<?php
render(ob_get_clean(), $block);
