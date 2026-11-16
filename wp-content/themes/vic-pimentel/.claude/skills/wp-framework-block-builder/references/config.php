<?php
/**
 * Config options to generate the block.json
 *
 * name, title, description, icon, keywords
 *
 * Framework-only keys (collected by scan_and_register_blocks(), never written
 * to block.json — see the skill's §3 for each):
 *   field_defaults   per-block default_value overrides (clone subs, globals, dot paths)
 *   repeater_content initial data every fresh instance starts with (mock rows etc.)
 *   bail_message     editor-preview notice when the block renders nothing
 */
$config = [
    'title' => 'Block Title',
    'icon'  => 'admin-comments', // Dashicon slug
    // 'category' => 'custom-blocks', // default; use 'inner-blocks' or 'wrappers' when appropriate
    'field_defaults' => [
        'title.html_tag' => 'h2', // h1 only for hero blocks
        'spacing_top' => 'default', // 'none' only for edge-to-edge blocks (e.g. location-1's map)
        'spacing_bottom' => 'default',
    ],
    // 'repeater_content' => ['items' => [['title' => 'Primeiro item']]],
    // 'bail_message' => 'Publique materiais para exibir este bloco.',
];
