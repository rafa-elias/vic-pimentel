<?php

// Client-safe fallback — the real value is set in app/config.php, loaded before core.
defined('SHOW_ACF_ADMIN') || define('SHOW_ACF_ADMIN', false);

/**
 * Gate the ACF admin UI behind SHOW_ACF_ADMIN and the "Enable ACF Admin"
 * field on the Feature Toggles options page (both must be on) — except for
 * FEATURE_TOGGLES_USER_ID, who always sees it regardless of either, so that
 * user can never lock themselves out of turning it back on.
 *
 * @return bool
 */
function core_acf_show_admin(): bool
{
    if (function_exists('is_feature_toggles_admin') && is_feature_toggles_admin()) return true;
    if (!SHOW_ACF_ADMIN) return false;

    return get_field('feature_show_acf_admin', 'option') !== false;
}
add_filter('acf/settings/show_admin', 'core_acf_show_admin');

/**
 * Registry of per-block clone sub-field default overrides, declared as
 * $config['field_defaults'] in each block's config.php and collected by
 * scan_and_register_blocks(). Call with $register to store, without to read.
 *
 * @param string $block_name
 * @param array|null $register
 * @return array
 */
function core_block_field_defaults(string $block_name, ?array $register = null): array
{
    static $registry = [];

    // Internal: '*' reports whether ANY block registered defaults — cheap
    // global bail for the acf/clone_field filter below.
    if ($block_name === '*') {
        return $registry;
    }

    if ($register !== null) {
        $registry[$block_name] = $register;
    }

    return $registry[$block_name] ?? [];
}

/**
 * Resolve which block a clone field belongs to, by walking up its parent chain
 * (the clone may sit inside a repeater/group) to the field group, then reading
 * the group's "block == acf/{name}" location rule. Returns null for anything
 * that isn't a block-specific group (e.g. global fields with "block == all").
 *
 * @param array $clone_field
 * @return string|null
 */
function core_acf_block_for_clone_field(array $clone_field): ?string
{
    static $cache = [];

    $key = $clone_field['key'] ?? '';

    if ($key === '') {
        return null;
    }

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    // Walk up parent fields until we reach the field group. MUST use the raw
    // local-store lookup here, never acf_get_field(): the filtered lookup
    // re-runs acf/load_field, which for clone-bearing fields re-fires
    // acf/clone_field → this code again → infinite recursion (real incident:
    // exhausted 1GB and took the site down).
    $parent = $clone_field['parent'] ?? '';
    $depth = 0;

    while (is_string($parent) && str_starts_with($parent, 'field_') && $depth < 10) {
        $parent_field = acf_get_local_field($parent);
        $parent = is_array($parent_field) ? ($parent_field['parent'] ?? '') : '';
        $depth++;
    }

    $group = (is_string($parent) && str_starts_with($parent, 'group_')) ? acf_get_local_field_group($parent) : null;

    return $cache[$key] = core_acf_group_block_name($group);
}

/**
 * Read the block name out of a field group's "block == acf/{name}" location
 * rule. Returns null for non-block groups (global fields' "block == all",
 * custom fields' impossible post_type rule, page/template groups).
 *
 * @param array|null $group
 * @return string|null
 */
function core_acf_group_block_name(?array $group): ?string
{
    foreach ((array) ($group['location'] ?? []) as $rule_group) {
        foreach ((array) $rule_group as $rule) {
            if (
                ($rule['param'] ?? '') === 'block'
                && ($rule['operator'] ?? '') === '=='
                && str_starts_with($rule['value'] ?? '', 'acf/')
            ) {
                return substr($rule['value'], 4);
            }
        }
    }

    return null;
}

/**
 * Apply per-block default_value overrides to cloned sub-fields.
 *
 * Runs at priority 5 — before ACF's own clone callback (priority 10) prefixes
 * names — so matching happens on the raw names as written in the field groups:
 * 'html_tag' (any clone in the block) or 'title.html_tag' (scoped to the clone
 * field named 'title'; the scoped form wins on collision).
 *
 * Only affects the pre-selected value of NEW block instances — an editor can
 * still change the field freely, and saved values are never touched.
 *
 * @param array $field The sub-field being cloned.
 * @param array $clone_field The clone field doing the cloning.
 * @return array
 */
function core_acf_clone_field_defaults(array $field, array $clone_field): array
{
    static $running = false;

    // Reentrancy guard — belt-and-braces against any lookup below re-entering
    // this filter through ACF's load pipeline.
    if ($running) {
        return $field;
    }

    // No block declared field_defaults → zero work, zero lookups.
    if (empty(core_block_field_defaults('*'))) {
        return $field;
    }

    // The hook also fires for flexible-content cloning — only act on real clones.
    if (($clone_field['type'] ?? '') !== 'clone') {
        return $field;
    }

    $running = true;
    $block_name = core_acf_block_for_clone_field($clone_field);
    $running = false;

    if (!$block_name) {
        return $field;
    }

    $defaults = core_block_field_defaults($block_name);

    if (empty($defaults)) {
        return $field;
    }

    $scoped = ($clone_field['name'] ?? '') . '.' . ($field['name'] ?? '');

    if (array_key_exists($scoped, $defaults)) {
        $field['default_value'] = $defaults[$scoped];
    } elseif (array_key_exists($field['name'] ?? '', $defaults)) {
        $field['default_value'] = $defaults[$field['name']];
    }

    return $field;
}
add_filter('acf/clone_field', 'core_acf_clone_field_defaults', 5, 2);

/**
 * Registry of per-block visible-choice restrictions for select/radio/checkbox
 * fields, declared as $config['field_choices'] in each block's config.php and
 * collected by scan_and_register_blocks(). Same name/dot-path scoping as
 * core_block_field_defaults(). Call with $register to store, without to read.
 *
 * @param string $block_name
 * @param array|null $register
 * @return array
 */
function core_block_field_choices(string $block_name, ?array $register = null): array
{
    static $registry = [];

    // Internal: '*' reports whether ANY block registered choice restrictions —
    // cheap global bail for the acf/pre_render_fields filter below.
    if ($block_name === '*') {
        return $registry;
    }

    if ($register !== null) {
        $registry[$block_name] = $register;
    }

    return $registry[$block_name] ?? [];
}

/**
 * Registry of per-block initial data (mock repeater rows etc.), declared as
 * $config['repeater_content'] in a block's config.php. Stores the friendly
 * nested declaration; translation to ACF's flat data format happens at block
 * registration (core_acf_translate_initial_data).
 *
 * @param string $block_name
 * @param array|null $register
 * @return array
 */
function core_acf_block_initial_data(string $block_name, ?array $register = null): array
{
    static $registry = [];

    if ($register !== null) {
        $registry[$block_name] = $register;
    }

    return $registry[$block_name] ?? [];
}

/**
 * Translate a friendly nested declaration into ACF's flat block-data format —
 * the exact shape ACF serializes into a saved block's "data" attribute
 * (verified against a real saved accordion-1 block, not derived from theory):
 *
 *   'accordion' => 2                                  repeater = row count
 *   '_accordion' => 'field_xxx'                       every entry gets a _ref
 *   'accordion_0_title' => 'Item'                     rows are name_index_sub
 *   'accordion_0_buttons' => 1                        seamless-cloned repeater
 *   '_accordion_0_buttons' => 'field_cloneKey_field_repKey'  ← prefixed key
 *   'accordion_0_buttons_0_button_style' => 'primary' prefix_name clone subs
 *
 * Key rules (all confirmed against the saved block): direct children of a
 * SEAMLESS clone get '{clone_key}_{own_key}' runtime keys; everything else
 * keeps its original key. Group-display clones and groups emit themselves as
 * '' plus their ref. Uses acf_get_local_fields()/local groups only — never
 * the filtered lookups (see the recursion warning above).
 *
 * @param string $block_name
 * @param array $declared
 * @return array Flat data map, or [] if the block's field group wasn't found.
 */
function core_acf_translate_initial_data(string $block_name, array $declared): array
{
    // Find the block's own field group by its location rule; collect the
    // global groups (block == all) that ACF appends to every block form.
    $group_key = null;
    $global_group_keys = [];

    foreach (acf_get_local_field_groups() as $group) {
        if (core_acf_group_block_name($group) === $block_name) {
            $group_key = $group['key'];
        } elseif (core_acf_group_is_global_block($group)) {
            $global_group_keys[] = $group['key'];
        }
    }

    if (!$group_key) {
        return [];
    }

    // Emit FULL field coverage — declared values where present, saved-form
    // defaults everywhere else — so the data matches what saving an untouched
    // form would store. Partial data is not an option: templates and helpers
    // assume every field key exists (a missing bg_color fataled a preview for
    // real: bg_color(null) TypeError → 500 → editor spinner never resolved).
    $overrides = core_block_field_defaults($block_name);
    $out = [];
    core_acf_emit_field_data(acf_get_local_fields($group_key), $declared, '', null, $out, $overrides);

    // Pass $declared so a seeded bg_color/spacing_* wins over the block's config default.
    foreach ($global_group_keys as $global_key) {
        core_acf_emit_field_data(acf_get_local_fields($global_key), $declared, '', null, $out, $overrides);
    }

    return $out;
}

/**
 * Whether a field group is a global block group (location "block == all") —
 * ACF appends these to every block form, so initial data must cover them too.
 *
 * @param array|null $group
 * @return bool
 */
function core_acf_group_is_global_block(?array $group): bool
{
    foreach ((array) ($group['location'] ?? []) as $rule_group) {
        foreach ((array) $rule_group as $rule) {
            if (
                ($rule['param'] ?? '') === 'block'
                && ($rule['operator'] ?? '') === '=='
                && ($rule['value'] ?? '') === 'all'
            ) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Default value for a leaf field in initial data: a matching field_defaults
 * override wins (same name/dot-path semantics as the pre-render walker —
 * kept as a small standalone matcher on purpose, don't unify), else the field
 * definition's own default_value, else ''.
 *
 * @param array $def
 * @param array $overrides field_defaults map for the block.
 * @param array $ancestors Enclosing field names, nearest last.
 * @return mixed
 */
function core_acf_initial_leaf_default(array $def, array $overrides, array $ancestors)
{
    $name = $def['name'] ?? '';
    $best = null;
    $best_specificity = -1;

    foreach ($overrides as $key => $value) {
        $segments = explode('.', $key);

        if (array_pop($segments) !== $name) {
            continue;
        }

        $chain = $ancestors;
        $matches = true;

        while ($segments) {
            if (array_pop($segments) !== array_pop($chain)) {
                $matches = false;
                break;
            }
        }

        $specificity = substr_count($key, '.');

        if ($matches && $specificity > $best_specificity) {
            $best = $value;
            $best_specificity = $specificity;
        }
    }

    if ($best_specificity >= 0) {
        return $best;
    }

    return $def['default_value'] ?? '';
}

/**
 * Whether a field's conditional logic is met by the data emitted so far
 * (OR of AND-groups). Unknown operators resolve to "shown".
 *
 * @param array $def
 * @param array $out Flat data emitted so far ('name' => value, '_name' => field key).
 * @return bool
 */
function core_acf_conditions_met(array $def, array $out): bool
{
    $groups = $def['conditional_logic'] ?? 0;

    if (empty($groups) || !is_array($groups)) {
        return true;
    }

    // Rules reference field keys; map them back to emitted values via '_name' entries.
    $by_key = [];

    foreach ($out as $out_key => $value) {
        if (str_starts_with($out_key, '_') && is_string($value)) {
            $by_key[$value] = $out[substr($out_key, 1)] ?? null;
        }
    }

    foreach ($groups as $rules) {
        $group_met = true;

        foreach ((array) $rules as $rule) {
            $actual = $by_key[$rule['field'] ?? ''] ?? null;
            $expected = $rule['value'] ?? '';

            $met = match ($rule['operator'] ?? '==') {
                '==' => (string) $actual === (string) $expected,
                '!=' => (string) $actual !== (string) $expected,
                '==empty' => empty($actual),
                '!=empty' => !empty($actual),
                default => true,
            };

            if (!$met) {
                $group_met = false;
                break;
            }
        }

        if ($group_met) {
            return true;
        }
    }

    return false;
}

/**
 * Recursive emitter for core_acf_translate_initial_data(). $seamless_parent_key
 * is the clone field key whose DIRECT children get prefixed runtime keys.
 *
 * @param array $defs Field definitions at this level.
 * @param array $declared Declared values keyed by field name.
 * @param string $prefix Accumulated flat-name prefix ('accordion_0_').
 * @param string|null $seamless_parent_key
 * @param array $out Output map, by reference.
 * @return void
 */
function core_acf_emit_field_data(array $defs, array $declared, string $prefix, ?string $seamless_parent_key, array &$out, array $overrides = [], array $ancestors = []): void
{
    foreach ($defs as $def) {
        $name = $def['name'] ?? '';

        // Skip UI-only fields (accordion/tab dividers have empty names).
        if ($name === '' || !is_string($name)) {
            continue;
        }

        // Required-but-hidden fields (e.g. custom spacing px) emitted empty block the page save.
        if (!empty($def['required']) && !core_acf_conditions_met($def, $out)) {
            continue;
        }

        $has_declared = array_key_exists($name, $declared);
        $value = $has_declared ? $declared[$name] : null;
        $runtime_key = $seamless_parent_key ? $seamless_parent_key . '_' . $def['key'] : $def['key'];
        $type = $def['type'] ?? '';

        // ACF's local store flattens fields — sub_fields live as separate
        // entries linked by parent key, so fetch children through the store.
        $children = !empty($def['sub_fields']) ? $def['sub_fields'] : (array) acf_get_local_fields($def['key']);

        if ($type === 'repeater') {
            $rows = $has_declared ? array_values((array) $value) : [];
            $out[$prefix . $name] = count($rows);
            $out['_' . $prefix . $name] = $runtime_key;

            foreach ($rows as $i => $row) {
                core_acf_emit_field_data($children, (array) $row, $prefix . $name . '_' . $i . '_', null, $out, $overrides, array_merge($ancestors, [$name]));
            }
        } elseif ($type === 'clone') {
            $cloned_defs = [];

            foreach ((array) ($def['clone'] ?? []) as $cloned_group_key) {
                $cloned_defs = array_merge($cloned_defs, (array) acf_get_local_fields($cloned_group_key));
            }

            // The naming shape (bare placeholder + prefixed children, or a flat
            // merge with no extra prefix) is governed by prefix_name, not by
            // display — seamless vs group only changes the admin-form UI.
            // Verified against a real ACF-saved block: a top-level seamless
            // clone with prefix_name:1 (tabs-3's block-level "buttons") stores
            // a bare "buttons":"" placeholder plus "buttons_buttons_0_..."
            // children, exactly like a non-seamless (group) clone would.
            if (!empty($def['prefix_name'])) {
                $out[$prefix . $name] = '';
                $out['_' . $prefix . $name] = $runtime_key;
                core_acf_emit_field_data($cloned_defs, $has_declared ? (array) $value : [], $prefix . $name . '_', null, $out, $overrides, array_merge($ancestors, [$name]));
            } else {
                core_acf_emit_field_data($cloned_defs, $has_declared ? (array) $value : [], $prefix, $def['key'], $out, $overrides, $ancestors);
            }
        } elseif ($type === 'group') {
            $out[$prefix . $name] = '';
            $out['_' . $prefix . $name] = $runtime_key;
            core_acf_emit_field_data($children, $has_declared ? (array) $value : [], $prefix . $name . '_', null, $out, $overrides, array_merge($ancestors, [$name]));
        } else {
            $out[$prefix . $name] = $has_declared ? $value : core_acf_initial_leaf_default($def, $overrides, $ancestors);
            $out['_' . $prefix . $name] = $runtime_key;
        }
    }
}

/**
 * Apply field_defaults to a block's editor form fields — the editor-side half
 * of the mechanism. Runs on acf/pre_render_fields, which only fires when an
 * ACF form is rendered (block forms in the editor), never on frontend page
 * loads, and before values are loaded — so an overridden default_value is
 * what an unsaved field shows.
 *
 * This is what reaches the fields the load-time clone filter can't:
 * global fields (bg_color, spacing_* — not clones, shared across all blocks)
 * and sub-fields of nested clones (button.style — its parent chain points at
 * the source Botões group, not the block).
 *
 * @param mixed $fields Fields about to be rendered.
 * @param mixed $post_id Context id — blocks always use a 'block_'-prefixed string.
 * @return mixed
 */
function core_acf_pre_render_field_defaults($fields, $post_id)
{
    if (empty(core_block_field_defaults('*')) && empty(core_block_field_choices('*'))) {
        return $fields;
    }

    // Blocks always render with a 'block_'-prefixed id (acf_ensure_block_id_prefix) —
    // anything else is a post/options/term form, not ours.
    if (!is_string($post_id) || !str_starts_with($post_id, 'block_') || !is_array($fields) || empty($fields)) {
        return $fields;
    }

    // Identify the block from the block-specific field group among the rendered
    // fields (global groups are "block == all" and can't identify it).
    $block_name = null;

    foreach ($fields as $field) {
        $parent = $field['parent'] ?? '';

        if (is_string($parent) && str_starts_with($parent, 'group_')) {
            $block_name = core_acf_group_block_name(acf_get_local_field_group($parent));

            if ($block_name) {
                break;
            }
        }
    }

    if (!$block_name) {
        return $fields;
    }

    $defaults = core_block_field_defaults($block_name);
    $choices = core_block_field_choices($block_name);

    if (!empty($defaults)) {
        $fields = core_acf_apply_field_defaults($fields, $defaults);
    }

    if (!empty($choices)) {
        $fields = core_acf_apply_field_choices($fields, $choices);
    }

    return $fields;
}
add_filter('acf/pre_render_fields', 'core_acf_pre_render_field_defaults', 10, 2);

/**
 * Recursively apply default overrides to a rendered fields tree.
 *
 * Keys match on the field's original name (_name — clone-expanded fields keep
 * it unprefixed). A dot key like 'button.style' additionally requires the
 * segments before the last to match the field's ancestors contiguously upward
 * ('style' whose direct parent is 'button'). More segments = more specific =
 * wins on collision.
 *
 * @param array $fields
 * @param array $defaults
 * @param array $ancestors Original names of the enclosing fields, nearest last.
 * @return array
 */
function core_acf_apply_field_defaults(array $fields, array $defaults, array $ancestors = []): array
{
    // Ascending specificity so the most-scoped key writes last and wins.
    $keys = array_keys($defaults);
    usort($keys, fn($a, $b) => substr_count($a, '.') <=> substr_count($b, '.'));

    foreach ($fields as &$field) {
        if (!is_array($field)) {
            continue;
        }

        $name = $field['_name'] ?? $field['name'] ?? '';

        foreach ($keys as $key) {
            $segments = explode('.', $key);

            if (array_pop($segments) !== $name) {
                continue;
            }

            // Remaining segments must match the nearest ancestors, walking up.
            $matches = true;
            $chain = $ancestors;

            while ($segments) {
                if (array_pop($segments) !== array_pop($chain)) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                $field['default_value'] = $defaults[$key];
            }
        }

        if (!empty($field['sub_fields']) && is_array($field['sub_fields'])) {
            $field['sub_fields'] = core_acf_apply_field_defaults(
                $field['sub_fields'],
                $defaults,
                array_merge($ancestors, [$name])
            );
        }
    }

    return $fields;
}

/**
 * Recursively restrict (and optionally relabel) which options a
 * select/radio/checkbox field offers in the editor, per $config['field_choices']
 * in a block's config.php. Same name/dot-path matching and specificity rules
 * as core_acf_apply_field_defaults() — most-scoped key wins. Filters the
 * field's own 'choices' array down to the configured keys; keys not present
 * in the field's choices are silently ignored (no accidental option creation
 * from a typo). Only affects the editor form — saved values outside the
 * restricted list (from before the restriction existed, or set elsewhere)
 * are never touched or validated against it.
 *
 * A matched entry is either a plain list of keys (restrict only, original
 * labels kept — `['background-1', 'background-7']`) or an associative
 * `key => label` map (restrict AND relabel just for this block —
 * `['background-1' => 'Cor Principal']`). Distinguished by whether the array
 * is a list (sequential 0-based int keys) — never mix the two shapes in one
 * entry.
 *
 * @param array $fields
 * @param array $choices_config
 * @param array $ancestors Original names of the enclosing fields, nearest last.
 * @return array
 */
function core_acf_apply_field_choices(array $fields, array $choices_config, array $ancestors = []): array
{
    // Ascending specificity so the most-scoped key is the last match found.
    $keys = array_keys($choices_config);
    usort($keys, fn($a, $b) => substr_count($a, '.') <=> substr_count($b, '.'));

    foreach ($fields as &$field) {
        if (!is_array($field)) {
            continue;
        }

        $name = $field['_name'] ?? $field['name'] ?? '';
        $best_key = null;
        $best_specificity = -1;

        foreach ($keys as $key) {
            $segments = explode('.', $key);

            if (array_pop($segments) !== $name) {
                continue;
            }

            // Remaining segments must match the nearest ancestors, walking up.
            $matches = true;
            $chain = $ancestors;

            while ($segments) {
                if (array_pop($segments) !== array_pop($chain)) {
                    $matches = false;
                    break;
                }
            }

            $specificity = substr_count($key, '.');

            if ($matches && $specificity > $best_specificity) {
                $best_key = $key;
                $best_specificity = $specificity;
            }
        }

        if ($best_key !== null && !empty($field['choices']) && is_array($field['choices'])) {
            $requested = (array) $choices_config[$best_key];
            $is_list = array_keys($requested) === range(0, count($requested) - 1);

            if ($is_list) {
                // Plain list of keys — restrict only, keep the field's own labels.
                $field['choices'] = array_intersect_key($field['choices'], array_flip($requested));
            } else {
                // Associative key => label map — restrict AND relabel for this block.
                $field['choices'] = array_intersect_key($requested, $field['choices']);
            }
        }

        if (!empty($field['sub_fields']) && is_array($field['sub_fields'])) {
            $field['sub_fields'] = core_acf_apply_field_choices(
                $field['sub_fields'],
                $choices_config,
                array_merge($ancestors, [$name])
            );
        }
    }

    return $fields;
}

/**
 * Define the folders to save the ACF group json.
 *
 * @param $paths
 * @param $post
 * @return mixed|string[]
 */
function core_custom_acf_json_save_paths($paths, $post): mixed
{
    // Vars
    $first_part = strtok($post['title'], ':');
    $second_part = strtolower(strtok(' '));

    // Save custom fields
    if ($first_part === 'Custom Field' || $first_part === 'Campo Customizado') {
        $paths = [get_stylesheet_directory() . '/acf-json/custom-fields'];
    }

    // Save global fields
    if ($first_part === 'Global Field' || $first_part === 'Campo Global') {
        $paths = [get_stylesheet_directory() . '/acf-json/global-fields'];
    }

    // Save theme settings fields
    if ($first_part === 'Theme Settings' || $first_part === 'Config Site') {
        $paths = [get_stylesheet_directory() . '/acf-json/options-pages'];
    }

    // Save menu fields
    if ($first_part === 'Menu') {
        $paths = [get_stylesheet_directory() . '/acf-json/menu'];
    }

    // Save post type fields
    if ($first_part === 'Post Type') {
        $paths = [get_stylesheet_directory() . '/acf-json/post-types'];
    }

    // Save page template fields
    if ($first_part === 'Template') {
        $paths = [get_stylesheet_directory() . '/acf-json/templates'];
    }

    // Save block fields
    if ($first_part === 'Block' || $first_part === 'Bloco' || $first_part === 'Inner Block' || $first_part === 'Wrappers') {
        // Block dir — scan_block_dirs() walks any nesting depth (e.g. library/accordion/accordion-1),
        // unlike a glob() pattern which can only match a fixed number of path segments.
        $block_dirs = array_filter(scan_block_dirs(block_path()), fn($dir) => basename($dir) === $second_part);

        // Save the json in the right block based on the field name
        if (!empty($block_dirs)) {
            foreach ($block_dirs as $acf_block_dir) {
                $acf_json_dir = $acf_block_dir . '/acf-json';

                if (!file_exists($acf_json_dir)) {
                    mkdir($acf_json_dir, 0755, true);
                }

                $paths = [$acf_json_dir];
            }
        }
    }

    return $paths;
}
add_filter('acf/json/save_paths', 'core_custom_acf_json_save_paths', 10, 2);

/**
 * Define the folders to load the ACF group json.
 *
 * @param $paths
 * @return array
 */
function core_custom_acf_json_load_point($paths): array
{
    $base_paths = [
        get_stylesheet_directory() . '/acf-json',
        get_stylesheet_directory() . '/acf-json/custom-fields',
        get_stylesheet_directory() . '/acf-json/global-fields',
        get_stylesheet_directory() . '/acf-json/menu',
        get_stylesheet_directory() . '/acf-json/options-pages',
        get_stylesheet_directory() . '/acf-json/post-types',
        get_stylesheet_directory() . '/acf-json/templates'
    ];

    $paths = array_merge($paths, $base_paths);

    $find_acf_json_folders = function($dir) use (&$find_acf_json_folders) {
        $acf_paths = [];
        $dirs = array_filter(glob($dir . '/*'), 'is_dir');

        foreach ($dirs as $sub_dir) {
            if (file_exists($sub_dir . '/acf-json')) {
                $acf_paths[] = $sub_dir . '/acf-json';
            }

            $acf_paths = array_merge($acf_paths, $find_acf_json_folders($sub_dir));
        }

        return $acf_paths;
    };

    // Load ACF blocks' acf-json folders.
    $blocks_acf_json_paths = $find_acf_json_folders(block_path());

    // Mirror scan_and_register_blocks()'s library gate — avoids loading unused groups and key collisions.
    if (!library_blocks_enabled()) {
        $library_prefix = block_path() . '/library/';
        $blocks_acf_json_paths = array_filter(
            $blocks_acf_json_paths,
            fn($path) => !str_starts_with($path, $library_prefix)
        );
    }

    return array_merge($paths, $blocks_acf_json_paths);
}
add_filter('acf/settings/load_json', 'core_custom_acf_json_load_point');

/**
 * Rename the acf json file name
 *
 * @param $filename
 * @param $post
 * @param $load_path
 * @return string
 */
function custom_acf_json_filename($filename, $post, $load_path): string
{
    $filename = str_replace(
        [' ', '_'],
        ['-', '-'],
        $post['title']
    );

    return strtolower($filename) . '.json';
}
add_filter('acf/json/save_file_name', 'custom_acf_json_filename', 10, 3);

/**
 * Change order of ACF fields and display only published posts
 *
 * @param $args
 * @param $field
 * @param $post_id
 * @return mixed
 */
function customize_acf_field_queries($args, $field, $post_id) {
    $args['orderby'] = 'date';
    $args['order'] = 'DESC';
    $args['post_status'] = 'publish';

    return $args;
}
add_filter('acf/fields/post_object/query', 'customize_acf_field_queries', 10, 3);
add_filter('acf/fields/relationship/query', 'customize_acf_field_queries', 10, 3);