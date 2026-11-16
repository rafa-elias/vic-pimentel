<?php
/** @var $block */
$fields = get_fields() ?: [];

$chips = array_filter($fields['chips'] ?? [], fn($chip) => !empty($chip['label']));
$list = array_filter($fields['list'] ?? [], fn($item) => !empty($item['title']));
$buttons = array_filter($fields['buttons'] ?? [], fn($button) => !empty($button['link']['url']));

if (empty($fields['heading']) && empty($fields['text']) && !$chips && !$list && !$buttons) return;

$show_divider = !isset($fields['show_divider']) || $fields['show_divider'];

ob_start();
?>
    <div class="wrapper ember-entrance-group">
        <div class="column-content">
            <?php if ($show_divider): ?>
                <span class="text-divider ember-entrance" aria-hidden="true"></span>
            <?php endif ?>

            <div class="text-body ember-entrance">
                <?php if (!empty($fields['heading'])): ?>
                    <h2 class="text-heading"><?= vp_emphasis($fields['heading']) ?></h2>
                <?php endif ?>

                <?php component('rich-text', ['text' => $fields['text'] ?? '', 'class' => 'text-copy']); ?>

                <?php if ($chips): ?>
                    <ul class="text-chips">
                        <?php foreach ($chips as $chip): ?>
                            <?php component('chip', ['label' => $chip['label']]); ?>
                        <?php endforeach ?>
                    </ul>
                <?php endif ?>

                <?php if ($buttons): ?>
                    <div class="text-buttons">
                        <?php foreach ($buttons as $button): ?>
                            <?php component('button', ['style' => $button['style'] ?: 'pill', 'link' => $button['link']]); ?>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
            </div>

            <?php if ($list): ?>
                <ul class="text-list ember-entrance">
                    <?php foreach ($list as $item):
                        $has_link = !empty($item['link']['url']);
                        $tag = $has_link ? 'a' : 'div';
                        ?>
                        <li>
                            <<?= $tag ?> class="text-list-row"<?php if ($has_link): ?> href="<?= esc_url($item['link']['url']) ?>"<?= !empty($item['link']['target']) ? ' target="_blank" rel="noopener noreferrer"' : '' ?><?php endif ?>>
                                <span class="text-list-title">
                                    <span class="text-list-icon"><?php print_svg('icons/' . ($item['icon'] ?: 'square-stack')); ?></span>
                                    <?= esc_html($item['title']) ?>
                                </span>
                                <?php if (!empty($item['text'])): ?>
                                    <span class="text-list-text"><?= esc_html($item['text']) ?></span>
                                <?php endif ?>
                            </<?= $tag ?>>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </div>
    </div>
<?php
render(ob_get_clean(), $block);
