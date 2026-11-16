<?php
/** @var $block */
$fields = get_fields() ?: [];

if (empty($fields['intro'])) return;

$words = array_filter([
    $fields['headline_left'] ?? '',
    $fields['headline_center'] ?? '',
    $fields['headline_right'] ?? '',
], fn($word) => $word !== '');

ob_start();
?>
    <div class="hero-panel">
        <?php component('scene', ['key' => 'hero']); ?>

        <div class="hero-content ember-entrance-group">
            <p class="hero-intro ember-entrance">
                <?= esc_html($fields['intro']) ?>
                <?php if (!empty($fields['intro_muted'])): ?>
                    <span class="muted"><?= esc_html($fields['intro_muted']) ?></span>
                <?php endif ?>
            </p>

            <?php if ($words): ?>
                <h1 class="hero-headline ember-entrance">
                    <?php foreach ($words as $word): ?>
                        <span><?= esc_html($word) ?></span>
                    <?php endforeach ?>
                </h1>
            <?php else: ?>
                <h1 class="sr-only"><?= esc_html(get_bloginfo('name')) ?></h1>
            <?php endif ?>
        </div>
    </div>
<?php
render(ob_get_clean(), $block);
