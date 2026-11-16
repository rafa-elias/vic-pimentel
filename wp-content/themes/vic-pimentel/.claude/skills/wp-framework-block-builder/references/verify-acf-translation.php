<?php
/**
 * Verify an ACF block JSON translation changed display strings ONLY.
 *
 * Usage: php verify-acf-translation.php original.json translated.json
 * Exit 0 = structurally identical (safe translation). Exit 1 = structural drift.
 *
 * Strips every user-facing display string (label, instructions, placeholder,
 * button_label, message, aria-label, ui_on_text, ui_off_text, prepend, append,
 * and choices' display values) from both files, then compares what remains.
 * Anything left differing — a field name, a key, a type, a choices KEY — is a
 * breaking edit, not a translation.
 *
 * default_value is only ignored for text/textarea/wysiwyg fields, where it's
 * free-text display copy. For choice-based fields (select, radio, checkbox,
 * button_group) it's left in the comparison on purpose: it references a
 * choices KEY, so translating it silently breaks the field.
 */

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php verify-acf-translation.php original.json translated.json\n");
    exit(2);
}

function strip_display($v)
{
    if (!is_array($v)) return $v;

    foreach (['label', 'instructions', 'placeholder', 'button_label', 'message', 'aria-label', 'ui_on_text', 'ui_off_text', 'prepend', 'append'] as $k) {
        unset($v[$k]);
    }

    // Choices: display values are translatable, keys are data — keep keys only.
    if (isset($v['choices']) && is_array($v['choices'])) {
        $v['choices'] = array_keys($v['choices']);
    }

    // default_value is free-text display copy only for these types — for
    // choice-based fields it's a choices KEY and must stay in the comparison.
    if (isset($v['type']) && in_array($v['type'], ['text', 'textarea', 'wysiwyg'], true)) {
        unset($v['default_value']);
    }

    return array_map('strip_display', $v);
}

$sides = [];
foreach ([$argv[1], $argv[2]] as $file) {
    $data = json_decode(file_get_contents($file), true);
    if ($data === null) {
        fwrite(STDERR, "FAIL: $file is not valid JSON\n");
        exit(1);
    }
    $sides[] = json_encode(strip_display($data), JSON_PRETTY_PRINT);
}

if ($sides[0] === $sides[1]) {
    echo "OK: only display strings differ — translation is structurally safe\n";
    exit(0);
}

// Show the first differing lines to make the drift findable.
$a = explode("\n", $sides[0]);
$b = explode("\n", $sides[1]);
$max = max(count($a), count($b));
$shown = 0;
for ($i = 0; $i < $max && $shown < 10; $i++) {
    $la = $a[$i] ?? '<missing>';
    $lb = $b[$i] ?? '<missing>';
    if ($la !== $lb) {
        echo "line " . ($i + 1) . ":\n  original:   $la\n  translated: $lb\n";
        $shown++;
    }
}
fwrite(STDERR, "FAIL: structural difference beyond display strings — do not use this translation\n");
exit(1);
