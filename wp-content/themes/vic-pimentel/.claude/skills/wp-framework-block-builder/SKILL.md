---
name: wp-framework-block-builder
description: Use when creating a new WordPress ACF block, editing an existing block, or converting a design into a block within the rear-theme framework. Covers file layout, config, render template, ACF JSON field groups, JS registration, parts, SCSS scaffold modes, and the impact rules for editing published blocks.
---

# Block builder — rear-theme framework

This skill documents the complete pattern for authoring a block in this codebase. All conventions are derived from the existing library blocks (`accordion-1`, `accordion-2`, `content-image-1`, `testimonial-1`). Follow every section in order when building a new block. For editing a block that already exists, see §13.

Components have their own skill (`.claude/skills/wp-framework-component-builder/SKILL.md`). **If block work requires *modifying* an existing component** (adding an arg to `image`, changing `buttons` markup, etc.), stop the block work and run that skill's Mode 2 impact check first — never patch a shared component mid-block-build without it.

---

## 0. Plan → confirm → execute (always, not just with a design reference)

Before writing **any** file — with or without a design reference — present:

1. The block name, category folder, and title
2. The planned ACF field list (names, types, which custom-field clones)
3. The planned HTML structure (wrapper layout, which `component()` calls)
4. Every open question (§9 Step 2 lists the common ones for design references; verbal briefs have the same ambiguities)

**Stop. Wait for explicit confirmation before creating any file.** The §15 checklist is post-hoc verification — it does not replace this gate.

---

## 1. Folder location and naming

Blocks live under a category subfolder:

| Category | Path |
|---|---|
| Library | `src/content/blocks/library/{block-name}/` |
| Custom | `src/content/blocks/custom/{block-name}/` |
| Inner blocks | `src/content/blocks/inner-blocks/{block-name}/` |
| Wrappers | `src/content/blocks/wrappers/{block-name}/` |

**Naming — `library/`:**
- kebab-case with a numeric suffix: `hero-1`, `content-image-2`, `accordion-1`
- The suffix distinguishes layout variants of the same concept — increment it, don't add adjectives
- Library blocks are wireframe-quality with minimum styling assumptions

**Naming — `custom/`:**
- kebab-case, descriptive of the block's design intent: `content-image`, `hero-banner`, `team-grid`
- No numeric suffix unless the project explicitly has multiple variants of the same design
- If you're given a folder name, keep it exactly as-is

The folder name is always the block name and must match the PHP/SCSS/JS filenames exactly.

---

## 2. Required file layout

```
{block-name}/
├── config.php                      # block metadata → drives block.json generation
├── {block-name}.php                # render template
├── {block-name}.scss               # styles (optional — omit if no block-specific CSS needed)
├── {block-name}.js                 # JS (optional — omit if no interactivity needed)
├── block.json                      # AUTO-GENERATED — never edit by hand
├── preview.png                     # editor preview image — required before production-ready; placeholder OK at wireframe stage
└── acf-json/
    └── block-{block-name}.json     # ACF field group for this block
```

Add a `parts/` subfolder only when the block uses a component that delegates item rendering back to the block (see [§7 Parts](#7-parts)).

If a design reference has been provided, it lives in:
```
{block-name}/
└── design-reference/
    └── mockup.png   # any image filename — not compiled or served
```
When `design-reference/` exists and `preview.png` does not, copy the design image as `preview.png` in the block root. At wireframe stage the editor preview and design reference are the same file.

---

## 3. `config.php`

See reference: [`references/config.php`](references/config.php)

The two required keys:

```php
$config = [
    'title' => 'My Block',
    'icon'  => 'admin-comments', // any Dashicon slug
];
```

`category` defaults to `custom-blocks` — only set it explicitly for `inner-blocks` or `wrappers`. `keywords` defaults to the block name.

Four optional framework keys, each collected by `scan_and_register_blocks()` and stripped before `block.json` generation (they never appear in the JSON): `field_defaults`, `field_choices`, `repeater_content`, and `bail_message` — see their sections below. Never add any other key unless you have a specific reason derived from `core/blocks.php`.

`block.json` is generated from this config by `generate_block_json()` on every page load when `WP_DEBUG` is true. Never edit `block.json` manually — your changes will be overwritten.

### `field_defaults` — per-block defaults for any block field

ACF has no native per-block default override — a cloned `html_tag` always defaults to what the source custom-field group says (`h2`), and global fields (`bg_color`, `spacing_*`) share one default across every block. To override per block, add `field_defaults` to `config.php`; it's collected by `scan_and_register_blocks()` and applied by `core/acf.php` (it never reaches `block.json`):

```php
$config = [
    'title' => 'Banner Principal',
    'icon'  => 'cover-image',
    'field_defaults' => [
        'title.html_tag' => 'h1',            // scoped: only the clone field named 'title'
        'html_tag'       => 'h2',            // bare: any field named html_tag anywhere in the block
        'bg_color'       => 'background-6',  // global fields work too
        'spacing_top'    => 'none',
        'button.style'   => 'primary-light', // nested: 'style' whose direct parent field is 'button'
    ],
];
```

**Key syntax:** a bare name matches any field with that original name anywhere in the block's field tree. A dot path (`button.style`, `title.html_tag`) additionally requires the preceding segments to match the field's enclosing fields, nearest first, contiguously. More segments = more specific = wins on collision (needed when a block clones the same group twice — `cta-banner-1` has both `title` and `subtitle`).

**Rules:**
- Values must be valid stored values (a choices **key** like `h1` or `background-6`, never a display label)
- **This only sets the pre-selected value for new block instances** — editors change fields freely, and saved values (including an explicit `'default'`) are never touched
- `header_style` is **not** coverable: it's a per-page sidebar field (location: every post type), not a block field — its default lives in its own field group JSON
- Sub-fields of a plain `group`-type field (e.g. `video.target` inside image-video) work via the dot path in the editor form

**How it's applied** (three cooperating layers in `core/`, all bailing instantly when no block declares defaults):
| Layer | Hook | Covers |
|---|---|---|
| Load-time | `acf/clone_field` (priority 5, before ACF prefixes names) | Top-level sub-fields of the block's own clones — editor **and** frontend `get_fields()` fallback |
| Editor form | `acf/pre_render_fields` (block forms only — `block_`-prefixed context id) | Everything by name/path: global fields, nested clones like `button.style` |
| Frontend render | `render()` in `core/blocks.php` | `bg_color`/`spacing_top`/`spacing_bottom` for blocks inserted but never edited (empty data) |

Repeater sub-field defaults (like `button.style`) need no frontend layer: a repeater row can only come into existence through the editor, so its values are always saved with the row.

**Hard rule for anyone touching `core/acf.php`:** the block-resolution walk must only ever use `acf_get_local_field()` / `acf_get_local_field_group()` — the filtered `acf_get_field()` re-enters `acf/clone_field` and recurses until memory exhausts (real production outage). A reentrancy guard and empty-registry bail exist as backstops; don't remove them.

**Hand-authoring saved block instances (seeding page content outside the editor):** when a block instance is placed onto a page by writing the `<!-- wp:acf/{name} {"data":{...}} -->` comment directly (e.g. via `wp post update`) rather than inserting it through the actual block editor, `field_defaults` has nothing to hook — there is no "fresh insert" event, and an explicitly-saved value (including `'default'`) is never touched by any of the three layers above. **Mirror `config.php`'s `field_defaults` values exactly in the hand-authored JSON**, unless deliberately deviating for that one instance — and if deviating, say so explicitly to the user rather than silently substituting a different value for an unrelated reason (visual consistency with a neighboring block, a guess, etc.). Silently overriding it defeats the very default just declared in `config.php`, with nothing in the diff to catch it. (Caught in `testimonial-2`: `config.php` correctly set `spacing_top`/`spacing_bottom => 'large'` matching the Figma frame, but both hand-authored page instances explicitly saved `'default'` instead, unflagged — the live page never showed what `config.php` declared.)

**A `true_false` field's hand-authored value must be the PHP string `'0'`/`'1'`, never a native `bool`/`int`.** Real ACF form saves always store true_false values as the HTML-form string `"1"`/`"0"` — passing native `true`/`false` (or bare `1`/`0`) into the declared array that feeds `core_acf_translate_initial_data()`/`wp_json_encode()` instead produces bare JSON `true`/`false`/`1` in the saved block comment. `get_fields()` and `render_block()` read this fine (PHP's loose truthiness doesn't care), so the block renders correctly and this is easy to ship undetected. This alone does not reliably cause the block-editor "N fields require attention" symptom below (that turned out to have a different, bigger cause — see next paragraph), but it's still wrong data and worth fixing on sight: `grep -o '"[a-z_]*":[^,}"]*' page-content.txt` (any bare `true`/`false`/unquoted `1`/`0` on a field you know is `true_false` is the bug).

**The global `spacing_top`/`spacing_bottom` vertical-spacing fields have four sub-fields — `spacing_top_mobile`, `spacing_top_desktop`, `spacing_bottom_mobile`, `spacing_bottom_desktop` — that are `required: 1` in their own field-group JSON despite being conditionally hidden unless the parent is `'custom'` (`acf-json/global-fields/global-field-espacamento-vertical.json`).** Real ACF form saves never write these 4 keys into a block's data at all until a human actually opens that accordion panel and switches to "Custom" — so a freshly-editor-inserted block, with `spacing_top`/`spacing_bottom` left at their normal `'default'`/`'none'`/etc. value, simply never has them in its saved attributes, and the (never-evaluated) required flag is moot. `core_acf_translate_initial_data()`'s "full coverage" emission (§3 above, `repeater_content`) — and by extension any hand-authored script copying that pattern — writes **every** field of every global group into the saved data, including these 4, as empty strings. Empty + required + (in ACF's actual save-validation path) apparently not correctly exempted for being conditionally hidden = ACF flags exactly 4 fields on **every single block**, hand-authored or seeded via `repeater_content`, regardless of that block's own fields — this is the real explanation for a generic, ever-present "Falha na validação: 4 campos requerem atenção" banner that doesn't clear even after re-editing a field in the UI, and that shows up on every block on every affected page. `get_fields()`/`render_block()` give zero signal either way (`!empty($fields['spacing_top_mobile'])` is `core/blocks.php`'s only use of it, so a missing key behaves identically to an empty one at render time). **Fix: never write `spacing_top_mobile`/`spacing_top_desktop`/`spacing_bottom_mobile`/`spacing_bottom_desktop` into hand-authored data unless the corresponding parent is literally `'custom'`** — strip them (and their `"_key"` reference pairs) from whatever `core_acf_translate_initial_data()` emitted before saving. (Caught 2026-07-08 on the Hero and Tabs pages — affected every hand-authored block on both, including ones seeded in earlier sessions before this rule existed; fixed by walking every block's saved `data` and deleting the 4 keys whenever their parent wasn't `'custom'`.)

**If hand-authoring via a PHP script that calls `wp_update_post()`/`wp_insert_post()` directly (an `eval-file` script, not the block editor or `wp post update <id> - < file.html`), the `post_content` string MUST be wrapped in `wp_slash()` immediately before the call — every single time, with no exceptions.** `wp_update_post()`/`wp_insert_post()` unconditionally run `wp_unslash()` (`stripslashes_deep()`) on their input first, assuming the caller passed "slashed" data (WordPress's universal convention, mirroring raw `$_POST`). Any real backslash in your content — most commonly a `<`/`>` JSON escape inside a block's own `data` attribute, which every wysiwyg field containing `<p>`/`<ul>` tags needs — gets silently stripped: `<` collapses to the five bare characters `u003c`, which then prints as literal garbage text on the frontend instead of being decoded. `JSON_UNESCAPED_UNICODE` on your own `wp_json_encode()` call only prevents *new* unicode escapes in the JSON you're generating (accented characters, if you skip `JSON_HEX_TAG` too) — it does **not** protect any `<`-style sequences already present in the string you're saving, including a real Gutenberg-editor-saved block's `<p>`/`<ul>` content you read via `get_post()` and are now re-saving alongside new blocks.
```php
$result = wp_update_post(wp_slash(['ID' => $id, 'post_content' => $content]), true);
```
**If you're only appending new blocks to a page that already has content, this bug corrupts the *pre-existing* blocks too, not just the new ones** — the whole concatenated `post_content` string goes through the same `wp_unslash()` call in one pass. Fetching `$page->post_content`, concatenating your new block comments onto it, and saving the combined string without `wp_slash()` will silently mangle every `<p>`/`<ul>` tag in every block that was already on the page, including ones you never touched. (Recurred 2026-07-07 building `tabs-3`/`tabs-4`/`tabs-5`: appending them to the Tabs page corrupted `tabs-1`/`tabs-2`'s already-correct wysiwyg content in the same update — fixed by restoring from a `post_content` snapshot taken before the edit, this time saving through `wp_slash()`.) Before running the real update against a page with existing content, either test the round-trip on a throwaway draft first, or just always add `wp_slash()` — it is a no-op on content with no backslashes (freshly generated JSON using `JSON_UNESCAPED_UNICODE` with no `<`/`>` escaping has none), so there's no reason to ever omit it.

**Verify by curling the rendered frontend page and grepping for the corruption pattern** (`grep -oE '[a-zA-Z]u00[0-9a-f]{2}'` — any match is corrupted output) **and for the expected accented words** — not just by checking that `wp post get --field=post_content` contains plausible-looking JSON. A corrupted save produces valid JSON (the corruption is in the *string values*, not the JSON structure), so JSON-validity checks alone won't catch it.

**A clone field (`image_video`, `title`, `buttons`, etc.) needs its own placeholder entry in the hand-authored `data`, not just its prefixed sub-fields.** Writing `"image_video_image":553,"_image_video_image":"field_6697ecf889df3"` alone is not enough — `get_fields()` silently returns an empty/missing `image_video` array and every `isset($fields['image_video']['image'])` guard in the render template fails, so the whole clone renders as if empty. The clone also needs its own top-level pair: `"image_video":"","_image_video":"field_<the_blocks_own_clone_field_key>"` (the key from that block's own `acf-json` — e.g. `field_cc0ca30c7c3da` for `testimonial-4`, not the shared custom-field group's key). Same requirement for `title` (already visible in every existing hand-authored example — `"title":"","_title":"field_..."` alongside `title_text`/`title_style`/`title_html_tag`), it's just easy to miss on a clone you're adding for the first time. Verify by actually rendering the page (curl the real URL, not just checking the JSON is valid) — a missing parent entry produces no PHP error, just silently empty output.

**A dark-native block's icon/rating-style component still needs an explicit fallback when `bg_color` is `'default'`.** `bg_color($field, true)` (the `$icon = true` branch used to pick a component's light/dark style variant) returns `''` for the `'default'` choice unless a third argument is passed — it has no way to know a particular block's `'default'` actually renders dark. A block whose own hardcoded background is dark (§8's dark-native pattern) must call `bg_color($fields['bg_color'] ?? null, true, 'light')` — the third arg supplies the fallback for exactly the `'default'` case, matching the block's real native look. Omitting it silently renders the component in its light-background style (e.g. `rating`'s default `$decoration-1` stars) on a dark page background. (Caught in `testimonial-8`: the Wall archetype's star rating rendered indigo-on-navy instead of white-on-navy until the third argument was added.)

### `field_choices` — restrict (and optionally relabel) which options a select/radio/checkbox field offers, per block

Global fields like `bg_color` share one option list across every block. To limit which options an editor can pick **for a specific block**, add `field_choices` to `config.php` — same name/dot-path syntax as `field_defaults` (§3 above), applied by the same `core/acf.php` machinery:

```php
$config = [
    'title' => 'Banner Principal',
    'field_choices' => [
        'bg_color' => ['background-1', 'background-7'], // bare, plain list: restricts any field named bg_color, keeps its own labels
        'button.style' => [                              // bare, assoc map: restricts AND relabels, scoped to the clone field named 'button'
            'primary' => 'Botão Principal',
            'primary-light' => 'Botão Claro',
        ],
    ],
];
```

**Two value shapes per entry:**
- **Plain list** (`['background-1', 'background-7']`) — restrict only; the surviving options keep the field's own labels
- **Associative `key => label` map** (`['background-1' => 'Cor Principal']`) — restrict AND relabel, just for this block's form; the stored value is still the same key, so nothing else (SCSS, other blocks, the global field's own label) is affected
- Never mix the two shapes within one entry — a plain list is detected by having sequential `0, 1, 2…` keys, so a stray string key flips the whole entry into relabel mode

**Rules:**
- Values are always stored choice **keys** (never display labels) — this key must match a key the field's own `choices` already defines; you can restrict/relabel existing options, not invent new ones
- Keys not present in the field's own `choices` are silently ignored — no accidental option creation from a typo
- **Editor-only.** It filters what `acf/pre_render_fields` renders in the block form; it doesn't touch already-saved values (an instance saved before the restriction existed, or with a value from outside the list, keeps working and rendering — nothing enforces or migrates it) and has no frontend counterpart, since `render()`/`get_fields()` just read whatever was saved
- If a block also sets `field_defaults` for the same field, make sure the default value is inside the `field_choices` list — nothing cross-checks this for you, and a default outside the restricted list still gets pre-selected on new instances (ACF renders whatever `default_value` says, even if it's absent from `choices`)

### `repeater_content` — initial rows a fresh block starts with

`field_defaults` can't pre-create repeater rows (rows are values, not definitions) or vary content per row. `repeater_content` can: it declares initial data that every **newly inserted** instance starts with — visible immediately in the editor form, the preview, and the frontend, no clicking "add row". Reference: `accordion-2/config.php`.

```php
'repeater_content' => [
    'accordion' => [
        [
            'title'   => 'Primeiro item',
            'content' => '<p>Conteúdo de exemplo.</p>',
            // Outer 'buttons' = the seamless clone; inner 'buttons' = the repeater it exposes
            'buttons' => ['buttons' => [
                ['button' => ['style' => 'secondary', 'link_type' => 'link', 'link' => ['title' => 'Saiba mais', 'url' => '#', 'target' => '']]],
            ]],
        ],
        ['title' => 'Segundo item', 'content' => '<p>Outro conteúdo.</p>'],
    ],
],
```

**Structure mirrors the field tree by field name**: repeaters take a list of row maps; clones and groups take a nested map (regardless of `prefix_name` — the translator handles meta-name prefixing); link fields take `['title' =>, 'url' =>, 'target' =>]`; choices take the stored key. Despite the name, it's not just repeaters — any top-level field can be declared (`'overline' => '…'`, `'title' => ['text' => '…']`). Skip attachment fields (image/file) — IDs aren't portable across installs.

**Full coverage is mandatory and automatic**: the translator emits *every* field of the block (own group + the global `block == all` groups), using declared values where present and defaults everywhere else (a `field_defaults` override wins over the field's own default), so the data equals what saving an untouched form would store. This is not cosmetic — partial data caused a real production-class bug: a template called `bg_color($fields['bg_color'])` on data that only held repeater rows, the `null` hit a `string` type hint, the preview fetch 500'd, and the editor showed an unresolvable loading spinner. Never emit partial data.

**How it works**: `scan_and_register_blocks()` collects it; a `register_block_type_args` filter (`core/blocks.php`) sets it as the block's `data` attribute **default**, translated to ACF's flat format by `core_acf_translate_initial_data()` (`core/acf.php` — format verified against a real saved block, and the translated output verified through ACF's own `get_fields()` pipeline). It must stay on that WP filter: ACF's own registration overwrites any `data` attribute declared in `block.json`.

**There are two registrations to patch, and both matter.** ACF keeps its own block-settings store (snapshotted at `block_type_metadata_settings` priority 99, *before* WP's `register_block_type_args` filter fires) and builds the editor JS payload from it — `acf.get('blockTypes')` is what `wp.blocks.registerBlockType()` receives client-side. The filter therefore patches both WP's registry (frontend `WP_Block` rendering) and ACF's store (editor inserts). Patching only WP's copy produces a confirmed failure mode: fields come up empty on every insert while everything verifies fine server-side.

**Semantics**: Gutenberg applies attribute defaults on insert, so this is per-*new-instance* starting content — existing saved blocks are untouched; the editor can edit or delete every row (deleting all rows saves a real `0` count, which sticks); mock text that isn't replaced ships to the live page, same caveat as any pre-filled value. Blocks with `repeater_content` won't show the `bail_message` notice — they're never empty.

### Derive a filter/category list from content fields instead of maintaining a second synced repeater

If a block needs a category/filter chip list that's supposed to match the categories actually used across a `cards`/`items` repeater, don't add a second `filters` repeater the editor has to hand-populate and keep in sync with each card's own category field — it drifts (a chip with no matching cards, or a card category with no chip) the moment either list is edited alone. Derive it in the render template instead:

```php
$categories = array_values(array_unique(array_filter(array_column($cards, 'category'))));
```

One field per card stays the single source of truth; the chip list is always exactly what's in use, with zero editor upkeep (confirmed: `carousel-15` — removed a `filters` repeater this way).

### `bail_message` — editor notice when the block renders nothing

A block that bails early (`if (empty($posts)) return;`) is invisible in the editor preview — nothing to click, findable only via the sidebar list. `core/blocks.php` buffers every **preview** render (`acf/blocks/pre|post_block_template_render`; frontend is never buffered) and replaces empty output with a placeholder showing the block title plus a message.

The default message is generic English (translatable, `rear-theme` domain): *"This block has no content yet — fill in its fields to display it."* Override it per block when the bail has a specific cause the editor must act on:

```php
$config = [
    'title' => 'Slider - Materiais',
    'icon'  => 'slides',
    'bail_message' => 'Nenhum material para exibir — publique materiais ou selecione-os manualmente nos campos do bloco.',
];
```

Write the message as the *action the editor should take* (publish posts, add rows, pick an image), not a restatement that the block is empty. Blocks whose bail depends on CPT posts existing (§11's auto/manual pattern) always deserve a custom message — the generic "fill in its fields" is wrong advice when the fix is publishing posts. On the frontend a bailing block still renders nothing, as before.

**PT-BR builds:** check which ACF JSON locale is active — `acf-json/` vs `acf-json-en/` (or `acf-json-br/`), per `CLAUDE.md`'s ACF JSON conventions section. If Portuguese is active, `title` must be in Portuguese even though the folder/file names stay English kebab-case as usual (e.g. folder `headline-projects-slider/` → `'title' => 'Título e Slider de Projetos'`). Reference: `slider-materiais/config.php` → `'title' => 'Slider - Materiais'`. This PT-BR requirement also applies to CPT slugs — see §10.

---

## 4. Render template — `{block-name}.php`

See reference: [`references/block-render-template.php`](references/block-render-template.php)

The six-step skeleton, in order:

```
1. /** @var $block */ docblock
2. $fields = get_fields()
3. (optional) extract primary data + bail early if empty
4. ob_start()
5. HTML inside <div class="wrapper"> using component() calls
6. render(ob_get_clean(), $block)
```

**Rules:**
- `/** @var $block */` is always the first line — it tells the IDE about the injected `$block` variable
- `$fields = get_fields()` is always the second line, before any logic
- Bail early (`if (empty($x)) return;`) **before** `ob_start()`, never inside the buffer
- The outermost element inside the buffer is always `<div class="wrapper">`
- **Don't nest a redundant full-width `.content`/`.inner` div directly inside `.wrapper`.** `.wrapper` itself is the layout container — give it `display: grid`/`flex` directly rather than adding a second wrapping div with no distinct purpose (caught in `testimonial-1`, which had `.wrapper > .content` doing the actual layout while `.wrapper` did nothing). Only add a nested container when it has a real, different job than `.wrapper` (e.g. a max-width inner column distinct from the grid, or a part that needs its own independent stacking context).
- Never echo directly — all output goes through `ob_start()` / `ob_get_clean()`
- `render(ob_get_clean(), $block)` is always the last line; it wraps everything in `<section class="block block-{name} ...">` and applies global spacing/bg fields automatically

**Accessing fields:**
- `$fields['overline']` — raw text, echo directly
- `$fields['title']` — pass the whole sub-array to `component('headline', $fields['title'])`
- `$fields['buttons']['buttons']` — when buttons clone uses `prefix_name: 1`; check `isset` before passing
- `$fields['image_video']` — pass the sub-array to `component('image-video', $fields['image_video'], ['max_size' => '…'])` — **always pass `max_size` explicitly**, see the dedicated rule below
- Repeater fields return an array of row arrays; loop over them in the template
- **Any `wysiwyg` field must be rendered through `component('rich-text', ['text' => $fields['name']])`, never echoed raw (`<?= $fields['content'] ?>`).** A raw echo outputs ACF's own `<p>` wrapper unstyled by anything the block declares — the global `p { @include typography($p-medium) }` rule (`_typography.scss`) targets that `<p>` directly and wins over any typography the block sets on an ancestor div, because a direct rule on the element itself always beats inherited values regardless of specificity. `rich-text.scss` exists specifically to neutralize this (`p, h1..h6 { font-size/font-weight/line-height/font-family: inherit; }`), letting the component's own wrapper typography (`.rich-text { @include typography($token) }`) actually take effect. (Caught 2026-07-06: five new `stats-*` blocks all echoed their `description`/`quote` wysiwyg fields raw; harmless everywhere the intended scale happened to be Inter like the leaked default, but silently wrong the first time a block styled `.rich-text` with a non-Inter scale — `stats-6`'s quote rendered in Inter instead of Plus Jakarta Sans until switched to the component.)
- **Every `component('image-video', …)` / `component('image', …)` call must pass an explicit `max_size` — the component's own default is `'thumbnail'`.** Omitting it silently requests WordPress's smallest generated size (150×150 by default) and lets the browser upscale that into whatever large box the block's CSS gives the image — soft/blurry on any real screen, not something a wireframe-stage editor preview will show at a glance.

    **`max_size` is not "pick something safely big."** `wp_get_attachment_image_srcset()` already includes every same-ratio registered size up to ~2x whatever `max_size` you pass — it is NOT capped at `max_size`. What `max_size` actually controls is (a) the `<img>` fallback `src`/`width`/`height`, and (b) the anchor for WordPress's auto-`sizes` formula: `(max-width: {registered-width}px) 100vw, {registered-width}px`. Passing `xlarge` (1440) for an image that only ever renders at 480px wide doesn't just waste a little — it tells the browser the slot is 1440px wide, so it picks a ~1440px (or ~2880px at 2x) srcset candidate that then gets downscaled by CSS, a real Lighthouse "improve image delivery" hit. This exact mistake shipped across ~35 call sites library-wide (carousel/content-image/accordion/tabs/timeline/stats/testimonial/hero/cta-banner) before being caught and corrected 2026-07-11 — every one of them had used a size 1–3 tiers larger than the image's real rendered width.

    **Process, every time:**
    1. Read the block's own `.scss` (not a similar-looking block's) to find the actual CSS width of the image's container at each breakpoint — `fluid(min,max)`'s plateaued `max`, a `rem()`/`max-width` cap, or a CSS grid/flex column's real resolved width. Don't guess from the "rendered role" — measure it.
    2. Registered sizes: `thumbnail`=150, `small`=414, `medium`/`medium_large`=768, `large`=1024, `xlarge`=1440, `xxlarge`=1920.
    3. Pick the smallest registered size at or above the real max width you found. Don't round up "to be safe" — that's the oversizing bug.
    4. **If the container's real max width is genuinely different above vs. below a CSS breakpoint** (the single most common miss: mobile stacks to one column at up to ~viewport width, desktop becomes a narrower fixed column, e.g. 600px) — `max_size` alone can't express two different regimes; WordPress's formula only has one breakpoint switch, anchored at the registered size's own width. Add an explicit `sizes_attr` override so the browser gets the right slot in both regimes:
        - Fixed desktop cap: `'sizes_attr' => '(min-width: 1024px) 600px, 100vw'` (breakpoint must match the block's own CSS breakpoint, not always 1024 — use whatever `@include media(...)` the block actually uses)
        - True viewport-proportional (swiper `slidesPerView`, vw-based grid column with no pixel ceiling): `'sizes_attr' => '(min-width: 1024px) 33vw, (min-width: 768px) 50vw, 100vw'`
        - Full-bleed background with no cap at all: `'sizes_attr' => '100vw'`
        - Don't add `sizes_attr` when the container has one consistent pixel cap across all breakpoints — correcting `max_size` alone is sufficient and preferred (see `.claude/skills/wp-image-performance/SKILL.md` §3 for the full mechanics and the ACF `sizes`-key naming collision to avoid).
    5. `image-video` and `video-player` both forward `sizes_attr` straight through to the underlying `image` component — pass it at the `image-video` call site same as `max_size`.

    | Real max container width | `max_size` |
    |---|---|
    | ≤~150px (small icon/badge) | `thumbnail` |
    | ~150–400px (avatar, logo, small inline photo) | `small` |
    | ~400–1000px (standard content image, most side-by-side layouts) | `medium` |
    | ~1000–1400px (wide column, near-full mobile-stacked width) | `large` |
    | ~1400–1900px (large showcase, near-full wrapper width ~1280px) | `xlarge` |
    | Genuinely full-bleed, no wrapper cap | `xxlarge` |

    (Caught 2026-07-08: `accordion-9` shipped with no `max_size` at all. Caught 2026-07-11: a full-library audit found the *opposite* mistake far more often — `large`/`xlarge`/`xxlarge` applied to images that measured out to `medium` or smaller once their real CSS container was actually checked.)

**HTML ordering for two-column layouts:** When a block has a side-by-side image + content layout, put the image-wrapper first in the HTML. Mobile stacks it naturally (image on top), and CSS grid with `order` handles the desktop reordering. Never rely on visual-only ordering that hides media below the fold on mobile:

```html
<div class="wrapper right">
    <div class="image-wrapper">...</div>   <!-- first in DOM → top on mobile -->
    <div class="content-wrapper">...</div> <!-- second → below on mobile -->
</div>
```

**Which side is "default" is per-block, not a fixed rule — match whichever side the block's own Figma archetype shows natively, then add the opposite side as the modifier class.** The mechanism is always the same (`order: 2` on `.image-wrapper` inside a modifier class, unset back to `initial`/removed for the non-modifier state), but the default and modifier swap depending on the archetype:
- `content-image-1` (Split archetype defaults to image-left): default grid has no override, `image_position` field default is `"left"`; `&.right { grid-template-columns: minmax(0,1fr) fluid(...); .image-wrapper { order: 2; } }` is the modifier.
- `content-image-4` (Checklist archetype defaults to image-right): default grid already has image in the second column (image-wrapper gets an unconditional `order: 2` at `>=tablet`), `image_position` field default is `"right"`; `&.left { grid-template-columns: fluid(...) minmax(0,1fr); .image-wrapper { order: initial; } }` is the modifier.

Whichever direction is default, the ACF field always offers both `left`/`right` choices (button_group, `return_format: value`) so an editor can flip it per instance — the field exists on every side-by-side block, only its `default_value` changes to match that block's native design.

**Verify DOM order actually lands each element in the track Figma gives it — don't just assume it from a similar-looking block.** With no `order` override, grid auto-placement assigns the *first* DOM child to the *first* `grid-template-columns` track. If the flexible (`minmax(0,1fr)`) track is declared first but the element meant to be flexible is *second* in the DOM (e.g. `.image-wrapper` written first out of habit), the image silently gets the flexible track and the content column gets squeezed into the fixed-width track — backwards from the design, and nothing errors. (Caught in `content-image-5`: the Intro row's `.image-wrapper` was first in the markup while `minmax(0,1fr)` was the first column, meaning the image stretched to fill space and the text got crushed into 400–560px — the opposite of Figma, where Content is flexible and Image is fixed. Fixed by swapping the DOM order to match Content-first, since that block's own Figma layer order has Content before Image on both breakpoints.) Check the actual Figma DOM order per block — it isn't always "image first" (§9's image-first default is for stacking on mobile, not a universal ordering rule).

**Layout div naming convention** (semantic, not prescriptive):

| Purpose | Class name |
|---|---|
| Text header area (overline + title) | `header` |
| Main text content + buttons | `content-wrapper` |
| Image or video area | `image-wrapper` |
| Slider/carousel container | `slider-wrapper` |
| JS-hooked elements | `js-{purpose}` prefix |

### Ember entrance animations — required on every block

Every library block scrolls into view with an Ember/GSAP fade (`src/scripts/static/ember.config.js`; pre-hide rule in `src/styles/common/_critical-overlays.scss`). This is markup-only — **no SCSS or JS is needed in the block itself**, just two classes placed correctly:

- **`ember-entrance-group`** goes on the element that contains all the animating pieces — almost always `.wrapper` itself (`text-1`, `content-image-1`, `stats-1`). If the block nests a real sub-container below `.wrapper` that holds the actual content (e.g. `cta-banner-1`'s `.banner`), the group class goes there instead — it just needs to be an ancestor of every `.ember-entrance` element.
- **`ember-entrance`** goes on each direct structural child of the group — `header`, `content-wrapper`, `image-wrapper`, `slider-wrapper`, a divider `<span class="line">`, etc. Reference any existing library block (`content-image-1`, `carousel-1`, `stats-1`) for the exact placement per layout shape.

**Repeated items (grids, sliders, lists) animate as one unit, not per item.** Put `ember-entrance` on the container that wraps the repeated items (`.grid`, `.slider-wrapper`, `.status`) — never inside the `foreach` loop on each card/slide. See `stats-1`'s `.status`, `carousel-1`'s `.slider-wrapper`, `testimonial-9`'s `.slider-wrapper` — none of them tag the individual card/slide markup.

**When the entrance element is rendered via `component()`** (e.g. a top-level `buttons` clone with no wrapping div in the template), pass the class through the component call instead of adding a wrapping div:

```php
component('buttons', ['buttons' => $fields['buttons']['buttons'], 'class' => 'ember-entrance']);
```

A block with only one visual section still needs both classes on two different elements — wrap the single section if nothing else already nests it (see `location-1`, which wraps its single `.iframe-location` div specifically so `ember-entrance-group`/`ember-entrance` land on two separate elements).

---

## 5. ACF JSON — `acf-json/block-{block-name}.json`

See reference: [`references/block-acf-json.json`](references/block-acf-json.json)

### Title convention

```
"title": "Block: Block-Name"
```

- `Block: ` prefix is mandatory — it controls where ACF saves and loads the file
- The name after the colon must be title-cased and match the block folder name (e.g. `accordion-1` → `Accordion-1`, `content-image-1` → `Content-Image-1`)
- **The prefix stays English even on PT-BR builds.** `core/acf.php` accepts `Bloco:` too, but the convention in this codebase is English `Block:` prefixes with Portuguese field *labels* (see `slider-materiais`) — don't switch prefixes for locale consistency; only `config.php` titles, CPT slugs, and field labels are Portuguese

### Location rule

```json
"location": [[{
    "param": "block",
    "operator": "==",
    "value": "acf\/block-name"
}]]
```

The value must match the WP block name exactly: `acf/{block-name}`.

### Standard field boilerplate

Every field, regardless of type, uses these defaults unless there's a specific reason to change them:

```json
"required": 0,
"conditional_logic": 0,
"wrapper": { "width": "", "class": "", "id": "" }
```

### Field ordering

Follow this order consistently:

| Position | Field name | Type | Notes |
|---|---|---|---|
| 1 | `overline` | `text` | Optional subtitle/eyebrow |
| 2 | `title` | `clone` | Headline custom field (`group_667e1dc9e62a0`), `display: "group"`, `prefix_name: 1` |
| 3 | Primary content | `wysiwyg` or `repeater` | The block's main data |
| 4 | `buttons` | `clone` | Buttons custom field (`group_669bd330e1844`), `display: "seamless"` |
| 5 | `image_video` | `clone` | Image/Video custom field (`group_6697ecf84e484`), `display: "group"`, `prefix_name: 1` |
| 6 | Layout controls | `button_group` | e.g. `image_position` with left/right choices |

Not every block needs all positions — omit what the block doesn't use. Keep the order of whatever you do include.

### Clone field conventions

**Headline clone** — always `display: "group"`, `prefix_name: 1`. Fields become `title_text`, `title_style`, `title_html_tag` under `$fields['title']`.

**Image/Video clone** — always `display: "group"`, `prefix_name: 1`. Fields become `image_video_image`, etc., under `$fields['image_video']`.

**Buttons clone** — always `display: "seamless"`. When `prefix_name: 1` (top-level), access as `$fields['buttons']['buttons']`. When inside a repeater with `prefix_name: 0`, access as `$item['buttons']` directly.

### Repeater fields

```json
{
    "type": "repeater",
    "layout": "block",
    "pagination": 0,
    "collapsed": "field_KEY_OF_FIRST_SUBFIELD",
    "button_label": "Adicionar linha",
    "rows_per_page": 20,
    "sub_fields": [ ... ]
}
```

Each sub-field must include `"parent_repeater": "field_KEY_OF_PARENT_REPEATER"`.

Set `"collapsed"` to the key of the first meaningful sub-field (usually the title/name field) so the repeater row shows a useful label when collapsed in the editor.

### Keys

`group_` and `field_` keys must be globally unique across the entire ACF installation. Generate them as random hex strings in the format used by ACF: `group_` + 13 hex chars, `field_` + 13 hex chars. Never reuse a key from another field group.

**Verify, don't assert.** After writing the JSON, run both checks — the reference template uses `field_REPLACE_*` placeholders, and copying an existing block's JSON without regenerating keys is the classic failure:

```bash
# Must output nothing — any line printed is a duplicated key:
grep -rh '"key"' acf-json/ src/content/blocks/ | sort | uniq -d

# Must output nothing — a surviving placeholder means keys were never generated:
grep -rn "REPLACE" src/content/blocks/{category}/{block-name}/acf-json/
```

(References to keys — `"clone"` arrays, `"collapsed"`, `"parent_repeater"` — don't match the `"key"` grep, so the check only flags duplicated declarations, which is exactly what matters. Verified to output nothing on a clean repo.)

### Library-block locale — single source, translate on adoption

Library blocks keep **one** ACF JSON, authored with PT-BR display strings. There is no `acf-json-br/`-style mirror at block level, deliberately — the two files would be ~95% identical and every field edit would have to land twice with nothing to catch divergence. When a library block is adopted in an **English** project, translate it in place at adoption time.

**Translatable — display strings only (what the editor *sees*):**
- Field `label`, `instructions`, `placeholder`, `aria-label`
- Repeater `button_label` (e.g. `"Adicionar linha"` → `"Add row"`)
- `message` field text, `true_false`'s `ui_on_text`/`ui_off_text`
- `prepend`/`append` (only when they're real words — leave symbols/units like `R$`, `%` alone)
- `choices` **values** (`"left": "Esquerda"` → `"left": "Left"`)
- `default_value` **only** on `text`/`textarea`/`wysiwyg` fields — there it's free-text display copy, not a reference
- `config.php`'s `'title'` — same pass, same rule

**Never translated — data, not display (what the code *reads*):**
- Field `name` — slugs are always English, in both locales; the render template reads `$fields['overline']` regardless of what the label says
- `key`, `type`, structure, `clone` references, `location`, `collapsed`, `parent_repeater`
- `choices` **keys**, and `default_value` on choice-based fields (`select`, `radio`, `checkbox`, `button_group`) — `default_value: "auto"` refers to a choice key; translating it silently breaks the field
- The field group `title`'s `"Block: Name"` form — already locale-neutral (English prefix, folder-derived name)

Cloned fields (headline, buttons, image-video) need **no** translation — their labels come from whichever custom-fields locale folder (`acf-json/` vs `acf-json-br/`) the project kept at setup, so they localize automatically.

**Verify every translation before using it** — a translation must be structurally byte-identical to the original once display strings are stripped:

```bash
php .claude/skills/wp-framework-block-builder/references/verify-acf-translation.php original.json translated.json
```

Exit 0 (`OK`) means only display strings changed. Exit 1 prints the first structural drifts — a renamed `name`, a translated choices key — and means the translation must not be used. (Script tested against `accordion-1` with both a clean label-only translation and a deliberately renamed field.)

Translate lazily — only the blocks the project actually uses, not the whole library upfront.

See reference: [`references/block-script.js`](references/block-script.js)

```js
window.theme.block('block-name', el => {
    // el is the block's <section> element
    // scope all querySelector calls inside el
});
```

- Only create the JS file if the block needs interactivity
- `el` is already the block's `<section>` — never query `document` directly
- Use `el.querySelector` / `el.querySelectorAll` for all DOM access
- Access globally registered packages via `window.theme.packages` (e.g. `window.theme.packages.swiper`)
- JS hooks in the HTML use the `js-` prefix class convention

### Swiper carousels — known-fragile option combinations and required defaults

Confirmed the hard way building the Carousel library (10 blocks, 2026-07-09) — every one of these caused a real, user-visible bug, not a theoretical risk:

- **Always pass `observer: true, observeParents: true` to every `new Swiper(...)` call.** A carousel's outer wrapper almost always also carries `ember-entrance` (per the rule above), which hides/animates it on scroll-into-view. If Swiper initializes before that reveal settles, it measures the wrong container width and — unlike a plain CSS layout — never self-corrects, because Swiper caches slide/container sizes at init time. Symptoms look unrelated to animation at all (a rail showing huge gaps between only 2 items instead of a full evenly-spread row, a card rendering mid-sentence with its left edge cut off) and are easy to misdiagnose as a math/config bug when the real cause is measurement timing. `observer`/`observeParents` make Swiper re-measure via ResizeObserver whenever the container's size or attributes change, which covers the ember-reveal race generically instead of chasing each symptom individually.
- **Never combine `centeredSlides: true` with an even numeric `slidesPerView`.** An even count has no true center slot — Swiper renders a phantom empty slot before the first (index-0) slide to try to center it, which looks like "half the items are missing" (confirmed: `slidesPerView: 4` + `centeredSlides` showed only 2 of 8 items, with a large dead gap where the phantom slot sat). Use an odd number (`3`, `5`) if centering is genuinely needed, or drop `centeredSlides` entirely for a simple left-to-right rail/pager.
- **`loop: true` + `slidesPerView: 'auto'` is fragile, especially navigating backward with a modest slide count.** Swiper's loop mode works by cloning slides and computing their offsets from the container's measured width at creation time — with variable (`'auto'`) widths and few real slides, that calculation can render a slide starting mid-content (its left portion cut off) or jump to the wrong visual order when going in reverse. If a true seamless loop is required, `loopAdditionalSlides` set generously (`8`+) helps but isn't guaranteed; `rewind: true` (a plain transition back to the first/last slide, no cloning at all) is far more reliable and the right default unless the "continues in the same direction forever" feel is a hard requirement — in which case see the marquee point below instead of fighting loop mode further.
- **Never combine `freeMode` with `loop` for a continuous/always-moving marquee.** The combination is unstable (stutters, inconsistent speed, jump at the loop seam). Build a true always-moving marquee as **pure CSS**, not Swiper at all: render the item list twice back-to-back in the DOM (two identical `.track-group`s inside a `.track`), then `@keyframes { to { transform: translateX(-50%); } }` on the track with `animation: <name> linear infinite` — translating by exactly `-50%` of a track holding two identical copies is always a mathematically seamless loop regardless of item count or viewport width, with zero Swiper involvement. Compute `animation-duration` in JS from the measured width of one `.track-group` divided by a target px/second constant, so visual speed stays constant no matter how much content is in the repeater. Respect `prefers-reduced-motion` on the animation.
- **The gap between two adjacent slides — including a decorative divider between them — belongs in `spaceBetween` (JS) or a real CSS `gap`, never as `padding` inside each slide.** Padding-based spacing makes the *first* slide's content sit inset from whatever sits above it (a header, a wrapper edge), because there's nothing to pad against on that side, while every other slide gets the padding on both sides — breaking both left-alignment with the block's own header and equal content-width across slides simultaneously. If a divider needs to render between slides, make it a decorative absolutely-positioned pseudo-element (`:not(:first-child)::before`) offset by half the real gap value into the gap area, not a `border-left` baked into the slide's padded box. (Confirmed in `carousel-8`: padding-based dividers pushed the first card in from the header's left edge and weren't confirmed as a real Figma mismatch until a rendered screenshot was diffed directly against the Figma frame at the same viewport width — see the screenshot-comparison rule in §9 area below.)
- **A container that needs `overflow-x: auto` for native horizontal scroll cannot also have decorative children that intentionally overflow past its own box on the Y axis.** Per the CSS spec, setting one overflow axis to anything other than `visible` forces the other axis's *used* value to become non-`visible` too (there's no way around this with `!important` or a different value) — so an absolutely-positioned decorative element (e.g. a marker dot on a rail, offset outward via `transform: translateY(50%)` to visually straddle a line) gets its overflowing half silently clipped the moment the container also needs to scroll. Keep any such decoration fully inside its own element's box (anchor it at `bottom: 0` with no vertical translate, and shift the *line* it needs to align with instead) rather than letting it straddle the box edge.
- **Before reaching for the most technically elaborate solution (a second Swiper instance, a Controller sync, `centeredSlides` math), check what the Figma archetype actually shows.** A "make the pager/rail itself slideable" requirement does not imply it needs to be *built* as a second synced Swiper — a plain flex row with `justify-content: space-between` (spreads evenly to fill the width when items fit, matching a static few-item Figma mockup exactly) plus native `overflow-x: auto` (kicks in automatically once items overflow, no configuration needed) is simpler, has none of the above Swiper-specific failure modes, and was — after two rounds of an over-engineered Swiper-based rail failing to match Figma — the actual correct answer for the Carousel Timeline archetype's year rail.
- **Don't carry a `loopedSlides` override between blocks/effects.** It's occasionally needed to fix a specific loop-count edge case (e.g. `effect: 'coverflow'` with very few slides), but if copied into a different block using `effect: 'creative'` + `loop: true`, it causes navigation to freeze after a few clicks — and looks exactly like "creative effect is fundamentally incompatible with loop mode." It isn't; plain `loop: true` with no `loopedSlides` override works fine with `creative`. Re-test without the override before concluding the combination itself is broken (confirmed in `carousel-13`).
- **`loop: true` breaks `activeIndex` and `slides.length` — read `realIndex` instead, and capture the real slide count before Swiper mutates the DOM.** Loop mode works by cloning slides into a padded internal array; `activeIndex` and `slides.length` reflect *that* padded array, not the real 1-based position among your actual images, so a fraction/counter UI built on them can get stuck or jump around. Use `swiper.realIndex` (Swiper's own loop-aware index) for the current position, and read the real total via `el.querySelectorAll('.swiper-slide').length` *before* constructing the Swiper instance (afterwards, `slides.length` includes the clones). Confirmed in `gallery-4`: a "01 — 08" counter built on `activeIndex + 1` / `slides.length` stayed stuck at "05 — 08" no matter how many times next was clicked, once the block's own JS was changed from `rewind: true` to `loop: true`.
- **A duplicated-DOM marquee/track (two copies of the same items for a seamless CSS loop, see the freeMode point above) breaks lightGallery if only one copy gets `<a>` wrappers scanned by its `selector` option.** The un-wrapped duplicate half is dead — roughly 50% of what's visible at any moment doesn't open the gallery. Give the duplicate copy its own `<a>` wrappers too, but route them through the *already-initialized* gallery's `.openGallery(index)` API via a `data-lg-index="<?= $index ?>"` attribute + click handler, rather than letting lightGallery's `selector` scan them as separate slides (which would double the gallery to 2x the real item count). Use `array_slice($images, 0, $middle, true)` (`preserve_keys: true`) when building the duplicate half so its row index maps directly to the real gallery index (confirmed in `carousel-12`).

### lightGallery lightboxes — known-fragile combinations and required defaults

Confirmed the hard way building the Gallery library (8 blocks, 2026-07-13). Earlier guidance in this doc said to import lightGallery directly inside each consuming block's own `.js` file rather than registering it globally, reasoning that only one block used it at the time. **That guidance was wrong once a second block adopted it, and is superseded — lightGallery must be registered globally, for a correctness reason, not just a bundle-size one:**

- **Register lightGallery once in `src/scripts/global/lightgallery.js` via `window.theme.registerPackages({ lightGallery, lgZoom })`, imported from `app.js` — never `import lightgallery from 'lightgallery'` inside a block's own `.js` file.** lightGallery keeps a module-scoped auto-increment counter (`var lgId = 0`) that generates each instance's container id (`#lg-container-N`). Webpack compiles each block's JS as a *separate* bundle, so a per-block `import` gives each block its own independent copy of that counter — every block's first lightGallery instance becomes `lg-container-1`, the second becomes `lg-container-2`, etc., **colliding on identical DOM ids across every block type that imports the library** the moment more than one such block is on the same page. The visible symptom is bizarre and easy to misdiagnose as a click-handler/selector-scoping bug: clicking one gallery's image pops open a lightbox showing multiple stacked counters/toolbars/zoom-icons on top of each other, because several instances' internal lookups-by-id resolve to the same (first-matching) DOM node. Verified the fix by checking `document.querySelectorAll('.lg-outer')` container ids are unique (`lg-container-1`..`lg-container-8` across 4 blocks × 2 instances) only *after* switching every consumer to `window.theme.packages.lightGallery(...)`.
- **`zoomFromOrigin`'s (default `true`) opening "fly from thumbnail" animation reuses whatever `src` is *currently rendered* on the page as a low-res placeholder — which is the small, `object-fit: cover`-cropped grid thumbnail, not the real photo.** Since the placeholder is stretched via a CSS `scale3d()` transform to the real image's uncropped final size/aspect ratio, a cropped thumbnail visibly warps into shape before the sharp real image finishes loading and swaps in — reading as a duplicate/ghost image flashing behind the real one. Fix: add a `data-thumb="<?= esc_url($item['image']['url']) ?>"` attribute (the same full-size URL already used for `href`) to every `.js-lg-item`, and pass `exThumbImage: 'data-thumb'` in the lightGallery config — the flight-animation placeholder and the final slide are then the same asset, so there's nothing to swap.
- **The distortion isn't only cosmetic on open — it's mathematically guaranteed on *close* too, and can't be fixed by tuning `data-thumb`.** `getTransform()` computes `scaleX = tileWidth / photoWidth` and `scaleY = tileHeight / photoHeight` **independently per axis**, then applies a non-uniform `scale3d(scaleX, scaleY, 1)`. Whenever the grid tile's cropped aspect ratio differs from the real photo's aspect ratio (i.e. almost always, for a uniform-tile grid), `scaleX !== scaleY` and the image visibly stretches/squishes while animating — both flying in *and* flying back out to the tile on close. There is no setting that keeps a cropped uniform grid **and** true `zoomFromOrigin` **and** zero distortion simultaneously; it's a real geometric conflict, not a bug to tune away. The fix that actually eliminates the close-side warp: set `instance.zoomFromOrigin = false` inside an `lgBeforeClose` listener on the gallery's container element (lightGallery dispatches this as a real native `CustomEvent`, synchronously, before `closeGallery()`'s own zoom-check runs) — this skips the whole transform-based return flight for *that one close* in favor of a plain fade, and lightGallery itself resets `zoomFromOrigin` back to the original setting at the end of every `closeGallery()` call, so the next open is unaffected. Do **not** try to "fix" this by shortening `startAnimationDuration` instead — that only makes the same distortion faster, it doesn't remove it.
- **Reserve space for the auto-generated caption (`getCaptionFromTitleOrAlt`, on by default — pulls from each image's `alt`/`title`) via the `defaultCaptionHeight` setting, never via CSS padding on `.lg-img-wrap`.** lightGallery computes the displayed image's max size in JS from the *unpadded* container (`getMediaContainerPosition()` measures the toolbar and `.lg-sub-html` caption element's real height and feeds it into the same size calculation used for the flight transform). A CSS-only padding added to `.lg-img-wrap` shrinks the actually-available box *after* that JS math already ran, so the browser has to visibly correct the image's size once real layout applies — reading as a second, wrongly-sized image flashing in. `defaultCaptionHeight: 60` (or whatever matches the real one-line caption height) makes the library's own size math account for the gap correctly, with no correction jump.
- **Filtering or paginating a lightGallery-enabled grid client-side requires calling `instance.refresh()` after every visibility change.** `this.items` is computed once via `this.el.querySelectorAll(this.settings.selector)` at construction time and is never reactively re-scanned when the DOM changes — toggling `.is-hidden` on cards without calling `.refresh()` leaves the lightbox showing a stale item set (including cards no longer visible on the current filter/page). `refresh()` re-runs the selector query and re-attaches click listeners to whatever currently matches. Confirmed in `gallery-7`: filtering to a 3-item category correctly showed "1/3" in the lightbox only once `instance.refresh()` was added inside the filter/pagination render function.
- **A pagination or filter click that shrinks the visible content can strand its own controls off-screen — scroll back to the grid after the DOM updates.** A card grid showing 6 items collapses to a much shorter box on a last page with only 1-2 items; if the user was scrolled down to reach the pagination controls, clicking to a shorter page can leave those same controls scrolled out of view above the new (shorter) viewport position. Fix: call `grid.scrollIntoView({ behavior: 'smooth', block: 'start' })` after re-rendering on a page change, and set `scroll-margin-top: $header-height-sticky` on the grid element so the scroll lands below the sticky site header instead of underneath it.

### Client-side search/pill filtering — `card.hidden = true` silently does nothing if the card has its own `display` rule

Any grid that toggles visibility via the native `hidden` IDL property in JS (`card.hidden = !show;` — the pattern this framework's own filterable blocks use, no library involved) depends on the browser's UA stylesheet rule `[hidden] { display: none; }` actually winning the cascade. It doesn't, the moment the block's own SCSS puts *any* `display` value on that same element — `@include stack('v')` (→ `display: flex`), a plain `display: grid`, anything. Author stylesheet rules apply **after** the UA stylesheet at equal specificity, and a class selector (`.card`) has the *same* specificity as an attribute selector (`[hidden]`) — so the later, more specific-by-source-order author rule wins, and the "hidden" card just… stays visible, full opacity, taking up its grid cell, with zero console error anywhere to explain why. The JS logic is completely correct (`card.hidden` really does flip to `true`); only the paint is wrong, so this reads as "filtering doesn't work" even though it does, structurally.

**Fix: any element a block's own JS hides via `.hidden = true`/`hidden` attribute needs an explicit same-selector override, everywhere that element has its own `display` value:**

```scss
.card {
    @include stack('v'); // display: flex

    &[hidden] {
        display: none;
    }
}
```

This isn't optional polish — it's required the instant the hidden element has ANY block-authored `display` rule on it, which in practice is almost always (cards/results in a filterable grid are laid out with flex/grid by design). Confirmed recurring across `location-2`'s `.card` and `location-3`'s `.result` (both `@include stack('v')`) — caught only when a user reported "clicking the filter does nothing" after an automated test had already verified the `hidden` *property* flipped correctly and wrongly reported the feature as working. **Verify with the actual computed style, not the IDL property**: `getComputedStyle(card).display` must be `none` after filtering, not just `card.hidden === true`.

---

## 7. Parts (optional)

Create a `parts/` subfolder when the block uses a component that delegates item rendering back to the block. Two components do this today: `accordion` (`parts/toggler.php` + `parts/content.php`) and `tabs` (`parts/tab.php` + `parts/panel.php`), both via `parts_path($args)`.

**How it works:** `component()` injects caller path info into `$args`. The component reads that info via `parts_path($args)` and calls `get_template_part()` pointing into the block's own `parts/` folder. This lets multiple blocks (`accordion-1`/`accordion-2`, `tabs-1`/`tabs-2`) share the same component while rendering different item layouts.

**Parts receive `item`, `index`, `total`, and `args` — but unwrap them differently per component, so check the component's own `get_template_part()` call, not just this doc:**
```php
// accordion's toggler.php / content.php — args gets reassigned to the clean_args
/** @var $args */
$item = $args['item'];   // the current repeater row
$index = $args['index']; // 0-based position in the data array
$args = $args['args'];   // reassigns $args — grab index/item BEFORE this line

// tabs's tab.php / panel.php — args is never reassigned
/** @var $args */
$item = $args['item'];
$index = $args['index'];
$total = $args['total']; // count of the full tabs array — for "Step N of M" style meta
```

`index`/`total` exist specifically so a part can render position-dependent content (a step number, "N of M" meta, a numbered badge) without the block having to precompute and stamp it onto every row itself before calling `component()`. **Don't reintroduce that block-level workaround** — foreach-ing over the data array to inject `$item['index']`/`$item['_number']` before passing it to `component('accordion'/'tabs', ...)` was a real, twice-recurring mistake (`tabs-4`, then `accordion-13`) before both components were fixed to pass position data through directly (2026-07-09). If a future shared component that delegates to parts needs the same thing and doesn't have it yet, fix the component (an additive change — new args-array key, nothing existing reads it) rather than repeating the block-level pattern a third time.

Parts make `component()` calls and echo markup. They do **not** call `render()` or `render_component()`.

Only add parts when you're using a component that explicitly calls `parts_path($args)`. Check the component's source before adding a `parts/` folder.

**The `tabs` component's `enable_select` mobile fallback echoes `$tab['title']` raw inside an `<option>` — that repeater sub-field must be a plain `text` field, never a `clone`.** `tabs.php`'s `<select>` branch (only rendered when `enable_select: true`) does `<?= $tab['title'] ?>` with no array handling; if the repeater's `title` is a headline clone (an array of `text`/`style`/`html_tag`), it echoes "Array" instead of the label. This is invisible if `enable_select` is `false` (the branch never renders), which is exactly how it went unnoticed while building `tabs-1` (Classic, `enable_select: false`) and only mattered once `tabs-2` (Vertical, `enable_select: true`) used the same repeater-title convention. Before giving any repeater sub-field a clone type, check whether any component the block passes that repeater to has a raw/scalar expectation for it — read the component's source, don't assume from a neighboring block that already happens to not exercise that code path. (Fixed pre-emptively while building `tabs-1`/`tabs-2`: both blocks' repeater `title` is plain text, matching `accordion-8`'s existing convention of keeping repeater-row titles as text and reserving `clone` for the block-level header only.)

---

## 8. SCSS — `{block-name}.scss`

Every block that needs styling gets an SCSS file. It is compiled individually to `public/blocks/{block-name}.css` and registered automatically.

### Mandatory structure

Every block SCSS file must start with `@use 'util' as *;` and wrap all rules in `@include block('block-name')`. No other top-level selectors.

```scss
@use 'util' as *;

@include block('block-name') {
    background: $light-1;

    // all block styles go here
}
```

`@use 'util' as *` forwards all five abstracts (`breakpoints`, `contexts`, `functions`, `mixins`, `variables`) into scope with no namespace prefix.

`@include block('block-name')` (defined in `_contexts.scss`) compiles to:
```css
body .is-root-container section.block-block-name { ... }
```
This scoping ensures styles work correctly in both the block editor and the frontend, and prevents leakage into unrelated contexts. Never use `[data-block="..."]` or `.block-block-name` directly as top-level selectors.

### Selector nesting mirrors the DOM — except `.headline`

Every selector for an element nests inside its actual DOM parent's selector, matching the render template's div structure exactly. If `.header` is a DOM child of `.wrapper`, the SCSS is `.wrapper { .header { ... } }` — never flat siblings (`.wrapper {...} .header {...}`) both sitting as direct children of `@include block(...)`. This applies all the way down: `.overline` nests inside `.header`, `.image-wrapper` nests inside whatever div actually wraps it in the `.php` file, etc.

```scss
@include block('my-block') {
    .headline { @include typography($heading-2); } // exception — see below, always flat

    .wrapper {
        .header {
            .overline { @include typography($overline-regular); }
        }

        .rich-text { @include typography($p-medium); }
    }
}
```

**The one exception is `.headline`** (rendered via `component('headline', ...)`) — it always stays a flat, direct child of `@include block(...)`, never nested inside `.wrapper`/`.header`/any wrapper, for the specificity reason detailed under Typography below. If two independent `.headline`-rendering instances exist in one block, give them distinct classes (via the component call's `class` arg) and keep both flat at block root — same reasoning, since nesting either under a container would defeat the same specificity requirement.

A shared element that legitimately appears at more than one unrelated DOM location with one common base rule (e.g. a `.number` used in both a featured stat and a supporting-stats list) is the other case where flat-at-root is correct, not a violation — nesting it under either parent alone would silently drop the shared style from the other location. Only leave it flat when the rule is genuinely shared with no divergent per-location values; if each location's value actually differs, write two distinct nested rules instead of one flat one.

`&.bg-is-dark`/`&.bg-is-light` overrides must repeat the *exact* nesting path of the base rule they override, not just the modifier class — see the specificity note further down in this section (`&.bg-is-dark { .panel {...} }` losing to a deeper base rule). Write the dark override selector by copying the base rule's full selector chain, prefixed with `&.bg-is-dark`.

(Caught 2026-07-08: the entire `library/` had drifted to flat-sibling selectors — even blocks with correct dark-mode color values had them silently never applying because the override was shallower than the base rule. Fixed across all library blocks; this is the convention going forward for every new block.)

### Vertical spacing — never hardcode it in the block's SCSS

`spacing_top`/`spacing_bottom` (the global `bg_color`/`spacing_*` fields, tier 1) are the **only** thing that sets a block's top/bottom padding. `_section-spacing.scss`'s `$spacings` map (`none`, `small`, `normal`, `large`, `default` → `$vertical-none/-small/-medium/-large/-default`) generates `.block.spacing-top-{size}` / `.block.spacing-bottom-{size}` rules with `!important`, applied globally to every block regardless of category. A block's own SCSS must **never** set `padding-top`/`padding-bottom` (or a shorthand `padding`) on the `@include block(...)` root — it would either be silently overridden by the `!important` global rule (dead code) or, worse, fight it.

Declare the block's intended spacing in `config.php` instead, via `field_defaults` (see §3) — this is a per-block *editorial* default, exactly like `title.html_tag`:

```php
'field_defaults' => [
    'spacing_top' => 'default',      // the common case — most blocks want this
    'spacing_bottom' => 'default',
],
```

Use `'none'` only for blocks that are visually edge-to-edge by design (e.g. `location-1`'s embedded map iframe) — never as a stylistic tweak; editors can always override per instance in the block sidebar.

**Naming mismatch to watch for:** the ACF field's stored choice keys are `default`/`none`/`small`/`normal`/`large`, but the SCSS tokens and Figma's `Vertical Spacing` variable collection use `default`/`none`/`small`/`medium`/`large` — `normal` (ACF) and `medium` (SCSS/Figma) are the same tier under different names. When reading a Figma archetype's `paddingTop`/`paddingBottom` binding to translate it into `field_defaults` (e.g. the frame is bound to `Vertical Spacing/vertical-medium`), write it as `'spacing_top' => 'normal'`, not `'medium'` — `'medium'` is not a valid stored value and will silently no-op.

### Typography

Never write `font-size`, `font-weight`, `font-family`, or `line-height` directly. Always use `@include typography($scale)`.

```scss
.headline { @include typography($heading-2); }
.overline  { @include typography($overline-regular); }
.rich-text { @include typography($p-medium);  }
```

**Never hardcode which scale a text *role* uses — read it off the actual Figma binding, every time, per node.** The example above (`.overline` → `$overline-regular`) is what *this project's current Figma file* binds that role to — it is not a rule that "overline = `$overline-regular`" forever. A designer can rebind any text node to any text style at any point, and a past version of this doc asserted "overline is always `$heading-6`" just as confidently, which is exactly how a `content-image-1` Figma rewrite (and reportedly another session before it) ended up styling a real `overline-regular`-bound node with `$heading-6` — the doc's role→token claim was trusted over checking the design. Two ways to read the real binding, both already used elsewhere in this skill (§9): `get_design_context`'s generated markup inlines it directly (`text-[length:var(--overline-regular/font-size,16px)]`, and its "styles contained in the design" list names the text style: `Overline/Overline Regular: Font(...)`), or call `get_variable_defs` on the node. Whatever name comes back, use the SCSS variable of that exact name — don't round it to the nearest role-table entry below, and don't copy a neighboring/legacy block's choice on the assumption it's still current. Most existing library blocks (`accordion-1`, `content-image-2`, `testimonial-2/3`, `carousel-1`, `text-1/2`, `form-1/2`, `headline-stats`…) still style `.overline` with `$heading-6` — that's what an *older* Figma state bound it to, now stale; it's legacy to leave alone if untouched, never a pattern to copy into a block you're building or rewriting from the current design.

**Apply a component-driven element's default typography flat, at the block root — never nested inside an intermediate wrapper div.** `.headline` (rendered via `component('headline', ...)`) carries its own per-style CSS (`headline.scss`'s `&.style-heading-3`, etc.) that must win when an editor picks a non-default value in the ACF `title.style` field. That override only wins if it is at least as specific as the block's default rule — nesting the block's rule inside a wrapper (`.header .headline { ... }`) adds an extra class and out-specifies the component's own per-style selector, silently locking every instance to the block's default no matter what style the editor picks. Always place it as a direct child of `@include block(...)`, exactly like `content-image-1.scss` / `hero-2.scss` / `hero-5.scss` (see also "Selector nesting mirrors the DOM" above):

```scss
@include block('my-block') {
    .headline { @include typography($heading-2); } // flat — sibling of .header, not nested inside it

    .header {
        .overline { @include typography($overline-regular); } // fine — overline is a plain <p>, not a styleable component
    }
}
```

(Caught in `testimonial-2`, which nested `.headline` under `.header`; the extra ancestor class defeated every non-default `title.style` choice.)

**Read `src/styles/abstract/_variables.scss` before choosing scales — never trust remembered sizes/weights; the palette and type system get re-themed per project and any hardcoded value in this doc would rot.** What is stable is the scale *names* and their typical roles — **typical, not fixed**: this table is only the fallback for the no-design case (§8's next section) or a first guess before you've looked. The moment a Figma/screenshot reference exists, its actual bound variable per node wins, even if it lands on a different row than the "usual" one below:

| Variable | Typical role |
|---|---|
| `$display-1/2/3` | Hero headings |
| `$heading-1..6` | Section/item headings, descending size; `$heading-2` is the usual block section title (`.headline`) |
| `$overline-regular/large` | Overline/eyebrow/kicker text (`.overline`) in this project's *current* Figma file — a design can rebind this role to a different scale, so confirm per node rather than assuming |
| `$p-small/medium/large` | Body text; `$p-medium` is the usual rich-text scale |
| `$action-small/medium/large` | Button text |
| `$link-small/medium/large` | Link text |

### Responsive breakpoints

Use `@include media()` from the include-media library (available via `util`). Always mobile-first (`>=`):

```scss
@include media('>=tablet') { ... }
@include media('>=phone')  { ... }
```

Available names: `phone-small` (360px), `phone-medium` (414px), `phone` (768px), `tablet` (1024px), `tablet-wide` (1280px), `desktop-small` (1440px), `desktop-large` (1600px), `desktop-xlarge` (1920px).

### Gap variables

Use these instead of raw values for all spacing/gap properties. Current values live in `_variables.scss` — read them there; some are `fluid()`, some static `rem()`, and which is which changes per project:

| Variable | Role (ascending size) |
|---|---|
| `$gap-nano` | Tightest details |
| `$gap-small` | Overline–title pairing |
| `$gap-regular` | Small intra-group spacing |
| `$gap-medium` | Intra-section spacing |
| `$gap-large` | Between major layout areas |
| `$gap-jumbo` | Very large spacing |

Two-axis gap (row / column): `gap: $gap-medium $gap-nano`.

### Color variables

Actual hex values are per-project — read `_variables.scss`, never assume the palette. Stable roles:

| Variable | Role |
|---|---|
| `$light-1` / `$light-2` | Default / alternate light background |
| `$primary`, `$secondary`, … | Brand palette |
| `$line-1` / `$line-2` | Borders on light / dark backgrounds (opacity-derived) |
| `$text-1` / `$text-2` | Text on light / dark backgrounds |
| `$background-*`, `$decoration-*`, `$action-*` | Semantic aliases onto the palette — prefer these over raw primitives where a semantic name fits |

**A block's dark (`bg-is-dark`) treatment is derivable by rule — a per-block Figma dark frame is only needed when a real decision is involved.** The mechanical recolor ruleset, validated across every Sections Library dark variant built to date (Hero, Content Image, Testimonial, Text):

| On light | On dark (`bg-is-dark`) |
|---|---|
| `$background-4` block background | `$background-7` |
| `$text-1` headings/body | `$text-2` |
| `$text-3` muted text (overlines, metas, captions) | unchanged |
| `$line-1` hairlines/dividers | `$line-2` |
| `primary`/`secondary`/`tertiary` button styles | `-light` counterpart, 1:1 same order (fixed-light-surface exception below) |
| `$primary` accent elements — emphasized text, quote rules, active indicators, icon glyphs | `$secondary` — `$primary` indigo fails contrast on dark backgrounds (~1.8:1 vs `$dark-2`), `$secondary` holds (~5.3:1) |
| `$background-5` elevated cards/panels | `$panel-dark` — never `$dark-1`/`$dark-2`, which recede into the page background |

Only two situations justify consulting (or requesting) a real dark Figma frame instead of applying this table: a **new elevated-surface treatment** not already covered by `$panel-dark`, or an **imagery/overlay/dark-native composition** where the dark rendering is a design judgment (see the dark-native and fixed-light-surface paragraphs below). When a dark archetype frame *does* exist in Figma, it wins over this table — read it (`get_variable_defs`/`get_design_context`), don't assume.

**In practice, "$primary accent elements → $secondary" is the single most likely row in this table to be wrong for any given element, and a dark archetype frame already existing in Figma is reason enough to check it — don't reserve verification for only the two "new decision" cases above.** Building the Location library's dark variants, the row was applied on faith to two different elements (`location-6`'s numbered office index, `location-8`'s transport-mode icons) that both turned out to bind to `$decoration-5` (white) in their real Figma dark frames, not `$decoration-2`/secondary — caught only after a user reported the wrong color and the actual `405:*` dark frame was pulled. A design can legitimately use `$primary→$secondary` for one accent role (an active-state indicator, say) and plain white for another (a decorative number, an icon) on the *same* dark frame — there's no way to tell which from the table alone. **The opposite mistake is just as real**: don't assume an element needs *any* dark override just because its siblings do. `location-2`/`location-3`'s search `Input` was given a `background: $panel-dark` dark override that Figma's actual dark frame never called for — the Input is a deliberately fixed light surface, byte-identical in both frames, and the correct fix was deleting the override, not correcting its color. When a dark frame exists (this design system's Sections Library pages almost always have one, per archetype), treat pulling it as the default move for any element carrying a semantic color or icon fill, not a last resort.

**Dark-mode `&.bg-is-dark` card/panel fills: read the exact value from Figma, never approximate.** A card that reuses `$background-6`/`$background-7` directly can collide invisibly with the block's own `bg_color` when an editor picks that same dark option (card == page background). Don't guess a fix with opacity math (`color.adjust($light-1, $alpha: -0.9)`, etc.) — call `get_variable_defs`/`get_design_context` on the Figma archetype's actual dark counterpart frame and read the real bound value. Dark "elevated surface" colors are frequently their own dedicated token in Figma's `Components Colors` collection (not the global `Colors` collection — see CLAUDE.md's Figma non-negotiables), precisely because `dark-1`/`dark-2` recede into a dark page instead of reading as a distinct card. If the value is reused across more than one archetype/component (check other `figma-*-library-status` memories), promote it to a real named variable in `_variables.scss` next to `$dark-1`/`$dark-2` — mirroring the dedicated Figma variable — rather than inlining a literal or an opacity guess in the block's own SCSS. (Caught in `testimonial-2`: the Cards archetype's dark card binds to Figma's `Panel/background-dark` = `#383838`, a flat literal unrelated to `$dark-1`/`$dark-2`, promoted to `$panel-dark`.)

**A shallow `&.bg-is-dark { .panel {...} }` override loses to a deeper base rule — match the base selector's full nesting path, not just the modifier class.** `&.bg-is-dark` compiles onto the block root (`section.block-{name}.bg-is-dark`), so if the panel's default background is declared several levels deep (e.g. `.wrapper .content-wrapper .checklist-panel { background: $background-5; }`), a dark override written as `&.bg-is-dark { .checklist-panel { background: $panel-dark; } }` compiles to a *shorter* selector (fewer classes) than the base rule and silently loses the specificity comparison — the panel stays on its light background even though `bg-is-dark` is present on the element, while the section's own `color: $text-2 !important` swap still takes effect normally (inherited text goes white), producing an illegible white-on-light-panel combination. Fix by nesting the override through the same intermediate path as the base rule: `&.bg-is-dark { .wrapper .content-wrapper .checklist-panel { background: $panel-dark; } }`. After writing it, verify by comparing the two compiled selectors' class counts (not just confirming the override rule exists in the output) — `grep` for the selector text and count classes, or check computed styles in a real browser. (Caught in `content-image-4`: the Checklist panel's dark counterpart never rendered because the override was 4 classes deep against the base rule's 5; fixed by matching the full `.wrapper .content-wrapper` path, making the override 6 classes.)

**A hardcoded `color: $text-1` on any element with its own explicit color rule silently blocks the section-root dark swap — and it's easy to add several across a block without noticing, since each one looks harmless in isolation.** The section root's `color: $text-2 !important` (`_block.scss`) only reaches elements that have *no* color rule of their own — inheritance stops at the first element with an explicit `color` declaration, `!important` or not, since importance only wins comparisons between rules targeting the *same* element. A block that writes `.body { color: $text-1; }`, `.rich-text { color: $text-1; }`, or `.quote { color: $text-1; }` "just to be explicit" (all three read like defensive styling, not a bug) permanently pins that text dark, invisible against a dark `bg_color`, with no `&.bg-is-dark` override anywhere to catch in a spot-check. The fix in nearly every case is to **delete the hardcoded `color: $text-1` entirely** rather than pair it with a dark override — the global `body:not(.wp-admin) { color: $text-1; }` rule (`_globals.scss`) already supplies the light-mode default via inheritance, exactly like every other library block (`content-image-4`, `testimonial-2`, etc. set no explicit body-text color at all). Only keep an explicit color when it's genuinely state-dependent (e.g. an active/hover nav link that must differ from an inactive sibling's `$text-3`) — and in that case it still needs a same-depth `&.bg-is-dark` counterpart per the paragraph above, not just deletion. **Before shipping any block with a `&.bg-is-dark` section at all, grep the whole file for every `color:`/`background:` declaration and classify each one**: inherits fine (no override needed, delete the hardcoded value if redundant), stays constant by design (`$text-3` muted text, unchanged both modes — leave alone), or genuinely needs a swap (add the override, matching nesting depth). Don't rely on remembering which elements need it while writing the file top-to-bottom. (Caught 2026-07-06 across three new blocks in the same session: `text-3`'s `.body`, `text-6`'s `.rich-text`/`.quote`, and `text-8`'s `.content` all had a redundant `color: $text-1` with zero dark override — plus `text-8`'s active-nav-link/indicator overrides were separately under-specified by one missing `.nav-items` level, the same class of bug as the `content-image-4` incident above recurring in a fresh block.)

**This audit step is easy to skip even right after reading it — actually run the grep, don't just remember to "be careful."** Building `tabs-1`/`tabs-2` (2026-07-06), both blocks' `&.tab.is-active .tab-label` rule set `color: $text-1` with no `&.bg-is-dark` counterpart, in the same session and same file where every *other* active-state color (indicator, active background) did get a dark override — the one property was simply missed while writing the rest top-to-bottom, exactly the failure mode this section already warns about. It only surfaced because the user opened both dark instances in a browser; a code-only review of the diff didn't catch it. Treat the grep-and-classify pass as a mandatory, separate step done *after* the SCSS file is otherwise finished — not something to trust yourself to remember mid-write.

**Third recurrence (2026-07-09, `carousel-1`/`-2`/`-3` batch): dark mode wasn't retrofitted until the user explicitly asked for a dark demo instance, several review rounds after the blocks were declared "done."** Mechanical verification (block registers, ACF field count matches, SCSS/JS compile with no errors) gives zero signal about dark-mode correctness — it is invisible to grep/curl and only shows up when someone actually looks at a `bg_color: dark` instance. Treat "does this block hold up on a dark `bg_color`" as part of a block's done-criteria from the *first* build pass, not a follow-up task to do only when asked — building the light instance and the dark instance together (even just mentally tracing every hardcoded color while writing the file, per the audit above) is far cheaper than retrofitting five blocks after the fact once a pattern of hardcoded colors has already propagated across them by copy-paste.

**`$background-7`/`$background-6` are page-background tokens, not elevated-surface tokens — a dark card/panel that reuses them recedes into the section background instead of reading as a distinct surface.** This is the exact case the table above and the `testimonial-2` callout already document (`$panel-dark`, not `$dark-1`/`$dark-2`) — and it recurred anyway in the same `carousel-1`/`-9`/`-10` batch, all three defaulting to `$background-7` for a dark card fill in a fresh build with no Figma dark frame to check against. When a block's own light-mode card already uses a *dedicated* surface token (`$background-5`, not the page's own `$background-4`), its dark counterpart needs the equivalent dedicated dark surface token (`$panel-dark`) — reaching for a `$background-*`/`$decoration-*` numbered alias "because it sounds like a background" isn't the same lookup as checking whether a purpose-built surface token already exists in `_variables.scss`.

**Changing a container's `align-items` to fix one breakpoint's layout can silently break the other breakpoint's sizing — re-verify both, not just the one you're changing.** Flex children default to `align-items: stretch` (full cross-axis width) unless overridden; switching a container to `align-items: center` (e.g. to shrink-wrap and center a group on desktop, matching Figma's `shrink-0` sizing) makes every child — including ones on *other* breakpoints — shrink to content width instead of stretching, unless that child has its own explicit `width: 100%`. (Caught in `tabs-1`: adding `align-items: center` to `.component-tabs` to center the desktop tab list also shrank `.tab-list-wrapper` on mobile, where it needs to stay `width: 100%` so `overflow-x: auto` has a bounded box to scroll within — the mobile "scrollable tab row" silently stopped scrolling because the wrapper just grew to fit all tabs instead of clipping them. Fixed by giving `.tab-list-wrapper` an explicit `width: 100%` that only relaxes to `width: auto` at the breakpoint where centering is actually wanted.) After any change to a shared flex container's `align-items`/`justify-content`, mentally re-run every breakpoint that container serves, not just the one motivating the change.

**When simplifying/refactoring an implementation, port forward any Figma-derived values the old version already got right — don't treat the replacement as a blank slate.** Replacing `tabs-2`'s ACF `checklist` repeater with a plain `<ul>` typed directly into the `content` wysiwyg field (see §12-adjacent judgment call below) correctly dropped the repeater's markup and fields, but also silently dropped the row-to-row gap and paragraph-to-list gap that had already been read from Figma (`spacing/gap-small` between rows, `spacing/gap-medium` between the intro paragraph and the list) — the new CSS started from nothing instead of carrying those two values over, and both had to be re-added after the fact. When replacing one implementation of a design detail with another, explicitly list every already-confirmed Figma value the old code encoded before deleting it, then confirm each one still has a home in the new code.

**A dark-native block (its own hardcoded default background is dark, not editor-chosen) needs both `background` AND `color` set explicitly at the block root — and reacts to `&.bg-is-light`, not `&.bg-is-dark`.** Every block built so far defaults to a light background, so the page's own inherited default text color (dark-on-light) already happens to match without any block ever needing to declare its own `color`. A block whose Figma archetype natively renders dark (no `bg_color` override needed to see it — e.g. `content-image-8`'s "Process" archetype, native `background-6`) is the first case where that coincidence breaks: `@include block(...) { background: $dark-1; color: $text-2; ... }` must set `color` explicitly too, or text falls back to the page's dark-on-light default and reads dark-on-dark. Text elements without their own explicit color still inherit correctly from this block-root declaration; elements that must stay fixed regardless of bg_color (an overline, a muted number/label) still need their own hardcoded color same as always. Because `bg_color: "default"` produces no `bg-is-dark`/`bg-is-light` class at all (`app/utils.php`'s `bg_color()` — only a real non-default choice produces one), and only picking a *light* `bg_color` (`background-2`/`-4`/`-5`) maps to `bg-is-light`, any non-inherited property that must flip when the editor opts into light (a hairline `background`, same reasoning as the `&.bg-is-dark` case above) goes under `&.bg-is-light` — the mirror image of every other block's `&.bg-is-dark`, with the same full-nesting-path specificity requirement.

**A dark `bg_color` instance of a block with buttons always uses the `-light` button style variant, same order — unless the buttons sit on a fixed light surface that never changes with the page background.** The `button.style` choices are `primary`/`primary-light`/`secondary`/`secondary-light`/`tertiary`/`tertiary-light` (`custom-field-botao.json`) — `primary`/`secondary`/`tertiary` are calibrated for a light page, and read wrong (low-contrast border/text) once the page itself goes dark. When building or hand-authoring a block instance with `bg_color` set to a dark option (`background-6`/`background-7`, or whatever renders `bg-is-dark`), map each button to its `-light` counterpart 1:1 in the same position — first button `primary` → `primary-light`, second `secondary` → `secondary-light`, and so on. This isn't optional per-instance judgment; it's the same mechanical swap every time. **Exception**: a button sitting inside a card/panel whose own fill is hardcoded (never swaps with `bg_color` — e.g. `content-image-9`'s floating Spotlight card, always `$light-1` regardless of the page) keeps the plain, non-`-light` style even when the block's `bg_color` is dark, since the button's actual visual surface never goes dark. Verify this against the Figma archetype's own dark `bg_color` variant rather than assuming the mechanical swap — `content-image-9`'s dark variant confirms the card's button stays plain `Tertiary`, not `Tertiary-Light`.

### Other useful mixins

```scss
@include stack('v')   // flex column (display: flex; flex-flow: column)
@include stack('>')   // flex row
@include fit          // position: absolute; inset: 0; width/height: 100%; object-fit: cover
@include chamfer(1,1,1,1)  // border-radius utility, sizes: 'default' | 'large'
```

### `fluid()` in block SCSS

Column widths that only exist at a certain breakpoint take a custom `$min-breakpoint`:

```scss
grid-template-columns: fluid(350, 600, 'tablet') 1fr;
```

Use `rem()` only for fixed-size UI elements (borders, avatars, progress bars) where viewport scaling would be wrong.

### Column layouts: grid over flex, and sizing the image column

For a side-by-side image/content split, default to CSS grid on `.wrapper` itself, not flex — see `content-image-1`, `form-1`, `text-2` for the canonical pattern:

```scss
.wrapper {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: $gap-large;

    @include media('>=tablet') {
        grid-template-columns: fluid(min, max, 'tablet') minmax(0, 1fr);
        align-items: center;
    }
}
```

The `fluid()` column is whichever side should shrink at smaller viewports rather than dominate the other (usually the image) — the `minmax(0, 1fr)` side takes the remaining space and never gets squeezed by its own content (text overflow, long words). Flex remains fine for simple in-row groups that aren't a two-column split (buttons, badges, a stack of stats).

**Never use CSS `column-count` to split a repeated list (reviews, items) into two columns when dividers sit between items.** The browser balances content across `column-count` columns however it wants (based on total height), not a predictable N/2 split — a `:last-child`/`:not(:last-child)` divider rule then lands on the wrong item (e.g. a 4-item list balanced 3/1 instead of 2/2 shows a stray divider under the 3rd item, with nothing visibly wrong in the CSS itself). If the design has genuine per-column dividers (check the Figma frame for explicit `Column` groupings, not just a visual 2-up grid), split the data into explicit column arrays in PHP (`array_chunk($items, ceil(count($items) / 2))`) and render two real `.column` containers, each scoping its own `:not(:last-child)` divider rule — this also matches Figma's actual layer structure instead of approximating it with CSS reflow. (Caught in `testimonial-8`: the Wall archetype's 4-review list used `column-count: 2`, producing a divider under the 3rd card that isn't in the design — fixed by splitting into two explicit `.column` divs.)

**Pulling the `fluid()` desktop max from a Figma design-reference:** don't estimate it — read the real dimension off the frame with `get_metadata`/`get_design_context` (e.g. the "Portrait" frame's `width` in a testimonial spotlight). The desktop value is exact from Figma; the smaller/tablet end of the range is your best estimate (there is usually no tablet-specific frame to measure).

**A real bulleted `<ul>`/`<ol>` needs an explicit marker — the global reset (`src/styles/common/_reset.scss`) sets `ol, ul { list-style: none }` site-wide.** Native `list-style: disc` will never just appear; a block with a genuine bullet list (as opposed to a wysiwyg-authored one, which goes through `rich-text` and is a separate concern) needs its own marker, e.g. a `::before` circular dot: `background: currentColor` (so it auto-matches the surrounding text color in both light and dark modes, no extra `&.bg-is-dark` override needed) sized with `rem()`, `border-radius: rem(999)` per the no-percent-radius rule. (Caught in `hero-5`: the highlights list rendered with zero visible bullets — the `<li>` markup was correct, the CSS just never re-added a marker after the global reset removed it.)

### `.image-wrapper` sizing an `image-video`/`image` component always needs `position: relative` + `@include fit` at all three nested levels

Whichever sizing approach is used below (`aspect-ratio` or fluid `height`), `.image-wrapper` must set `position: relative` and apply `@include fit` to `.image-video, .image, .img` together — matching `content-image-1`/`content-image-2` exactly:

```scss
.image-wrapper {
    position: relative;
    height: fluid(220, 560); // or aspect-ratio, per the choice below
    background: $primary; // loading-state fallback color

    .image-video,
    .image,
    .img {
        @include fit;
    }
}
```

`@include fit` sets `position: absolute; inset: 0; width/height: 100%; object-fit: cover` — it positions against the *nearest positioned ancestor*. Omitting `position: relative` on `.image-wrapper` doesn't error or visibly break in the editor's cramped preview iframe, but on the real front-end the image escapes to whatever positioned element happens to sit further up the DOM (often rendering outside the block entirely, sized to its own intrinsic `width`/`height` attributes instead of the intended box). Applying `fit` at all three levels (`.image-video` — the component wrapper, `.image` — the nested image component, `.img` — the actual `<img>` tag) is required together, not just the innermost one: `image-video.scss`'s own component styles only force-fit its *video* child (`.component-video-player`), not the plain-image path, so without the block re-applying `fit` at all three levels the image renders in-flow at its natural size instead of filling the wrapper. Don't add `overflow: hidden` on `.image-wrapper` itself — `.component-image-video` already sets it via its own component SCSS, and `fit`'s exact `inset: 0` sizing means there's nothing to clip at the wrapper level anyway. (Caught 2026-07-06 in `stats-3`: `.image-wrapper` had `height`/`overflow: hidden` and a `:is(img, video, .image, .image-video) { @include fit }` selector but no `position: relative` — the image rendered outside the block on the real page, invisible in curl/HTML-only verification since the bug is purely a rendered-layout issue, caught only when the user looked at the actual page.)

**A `position: static` sibling of a `position: absolute` element paints *behind* it regardless of z-index — give the static sibling its own `position` too, or it silently disappears under a photo/overlay.** This bites any block that layers a full-cover background image (`.image-wrapper`/`.slat-media`-style `inset: 0`) alongside plain, non-positioned text (a `<span>`/`<p>` with no `position` declared) inside the *same* parent — even a *low* `z-index` (`0`, or `auto`) on the absolutely-positioned element still beats a `position: static` sibling, because static content occupies an earlier paint layer than *any* positioned content, independent of the z-index number. An element that already has its own `position` from elsewhere (e.g. this framework's `icon.scss` sets `position: relative` on `.has-style`) is unaffected and paints correctly above the image; it's specifically the plain-text siblings that get silently covered. Give every text/UI element that must render above a layered background image an explicit `position: relative` (or higher), not just an eyeballed z-index number. (Caught 2026-07-09 in `accordion-13`'s Slats archetype: a per-item background photo, absolutely positioned inside the same toggle button as the item's number/rotated-title `<span>`s, completely covered both — invisible in curl/HTML-only verification since the markup was correct and only the paint order was wrong; only surfaced once the user looked at the real page.)

**Never `position: absolute` an element whose height is driven by real, variable-length embedded content (a CF7 form, an oEmbed, anything not authored in the block itself) — it silently breaks the moment that content grows past whatever height the layout assumed.** A registration-card-over-hero-photo archetype (`form-5`) initially absolutely positioned the card (`top:0; right:0`) inside a `.wrapper` sized by a fixed `min-height`, matching the Figma mockup's static card height exactly. This looks correct until the real page's CF7 form (validation errors, more fields than the mockup showed, browser font-size differences) renders taller than that assumed height — since an absolutely-positioned element is removed from normal flow, nothing forces its container to grow to fit it, so the card either overflows past the section's visible bottom edge or gets clipped, with no error or warning anywhere. Fix: give the card real, normal-flow layout instead — a CSS Grid two-column row (`.wrapper { display:grid; grid-template-columns: minmax(0,1fr) fluid(...); align-items:end; } .card { align-self:start; }`, no `position:absolute` at all) so the row's height — and therefore the section's, and therefore a `position:absolute;inset:0` background photo sized off the section — naturally grows with whatever the embedded form actually renders at, in both directions, with nothing to overflow. `min-height` is still fine to keep as a floor for the *typical*-height case; the bug is specifically trusting it as an implicit ceiling via `position:absolute`, not the min-height value itself. Generalizes beyond forms to any block embedding oEmbeds, third-party widgets, or other content whose real rendered height the block author can't fully control.

**When a block-level SCSS rule overrides one property a shared component already sets on the same selector, explicitly redeclare every *related* property too — the cascade applies per-property, not per-rule, so anything left unset still resolves to the component's own value.** A more-specific block selector wins property-by-property, not wholesale: if the component sets `display: grid; grid-template-columns: 1fr auto;` on `.some-class` and a block's own (more specific) rule for that same selector sets `display: grid; grid-template-rows: auto 1fr auto;` without also declaring `grid-template-columns`, the component's `1fr auto` two-column template still applies — silently breaking auto-placement of the block's own grid items into the wrong cells. The fix is always the same: when reusing a shared component's element for a fundamentally different layout, redeclare the *whole* related property group (all of `grid-template-columns`/`-rows`/`-areas`, or all four `margin`/`padding` sides, etc.), not just the one property that happens to differ. (Caught 2026-07-09 in `accordion-13`: overriding `.accordion-item-toggle` — normally the shared `accordion` component's simple `display: grid; grid-template-columns: 1fr auto;` title+icon row — into a 3-row vertical grid left `grid-template-columns` unset, so the inherited 2-column template silently misplaced the number/title/icon into the wrong grid cells.)

### Image ratio: `aspect-ratio` vs fluid height

Two valid ways to size an `.image-wrapper`, pick per image:

- **`aspect-ratio: W / H`** (the plain CSS property, not the `@mixin ratio()` padding-top hack) — use when cropping matters: a face/portrait, a logo, anything where an unpredictable fluid height risks cutting off content that shouldn't be cut. Take `W`/`H` from the actual Figma frame dimensions, the same way as the `fluid()` max above (e.g. a 480×560 portrait frame → `aspect-ratio: 480 / 560`), not a round guess like `1 / 1`.
- **`height: fluid(min, max)`** — still the right default for full-bleed/background-style photography (`content-image-1`, card thumbnails) where the image is wide open scenery/product shots and a crop from `object-fit: cover` is safe.

**Stacked (single-column) state needs its own cap when using `aspect-ratio`.** Below the two-column breakpoint, `.image-wrapper` is typically `width: 100%` — combined with a locked `aspect-ratio`, its height grows unbounded as the viewport approaches the breakpoint (e.g. an oversized portrait on a tablet-width single-column layout). Cap it with the image's natural desktop width, centered, and hand sizing back to the grid track at the breakpoint:

```scss
.image-wrapper {
    width: 100%;
    max-width: rem(480); // the image's natural (desktop) width
    margin-inline: auto;
    aspect-ratio: 480 / 560;

    @include media('>=tablet') {
        max-width: none;
        margin-inline: 0;
    }
}
```

Prefer this over moving the two-column breakpoint earlier (e.g. `tablet` → `phone`) to "fix" the same symptom — forcing two columns sooner instead cramps the text column into the `fluid()` range's minimum width at exactly the viewport where it most needs room, which reads worse than staying stacked a little longer.

### SCSS scaffold modes

Every block's SCSS is written in exactly one of three modes — decide which before writing a line, and say which one you're in at the §0 checkpoint:

| Mode | Input | Fidelity expectation |
|---|---|---|
| **no-design** | Verbal brief only | Full system usage with plausible placeholder decisions, every guess marked `// TODO: confirm against design` — see below |
| **design-reference** | Wireframe or prior-art image in `design-reference/` | Structure and layout match the image; token choices (scales, gaps, colors) are still placeholder decisions with TODO markers — the image shows *shape*, not values |
| **screenshot-agency** | Final-design screenshot (agency handoff) in `design-reference/` | Pixel-faithful: measure sizes/spacing from the image and map them to the nearest existing tokens; TODO markers only for what a static image genuinely can't show (hover/focus states, exact breakpoints, fluid endpoints) |

Which of the two image modes applies is not guessable from the file alone — **ask at §9 Step 2 whether the image is a wireframe or the final design** if it isn't stated. A Figma URL is not a mode: switch to the Figma MCP workflow (§9, last subsection) and work from real tokens — a TODO marker there means you skipped looking something up.

### Mode: no-design (wireframe-stage) SCSS

When no design reference exists yet, still write SCSS that uses the full system — not minimal flat CSS. The goal is placeholder decisions in the right structure, not structural shortcuts.

**Typography:** Apply `@include typography($scale)` to every text element. Choose the most plausible scale for the role and mark it:

```scss
.number { @include typography($heading-1); } // TODO: confirm scale against design
.label  { @include typography($p-medium);  } // TODO: confirm scale against design
```

Default choices when unsure — these are guesses for the no-design case only, always marked with a TODO; the moment a real design (screenshot or Figma) exists, §9's "read the actual binding" rule replaces every one of these:
- Primary block title (`.headline`) → `$heading-2`
- Secondary heading / item title → `$heading-3` or `$heading-5`
- Overline / eyebrow / label → `$overline-regular`
- Body / rich-text → `$p-medium`
- Large display number → `$heading-1` or `$display-3`

**Layout and breakpoints:** Any layout with more than one column, or a flex direction that would reasonably change on mobile, needs at least one `@include media()` breakpoint. Default to stacking on mobile and switching to multi-column at `>=tablet`. Mark guessed breakpoints:

```scss
.stats-wrapper {
    @include stack('v');
    gap: $gap-medium;

    @include media('>=tablet') { // TODO: confirm breakpoint against design
        flex-flow: row;
        flex-wrap: wrap;
    }
}
```

**Spacing:** Use `fluid()` for any spacing that plausibly scales with viewport (large vertical gaps, column widths). Use gap variables (`$gap-large`, `$gap-medium`, etc.) rather than raw values. Only reach for raw `rem()` for fixed-size UI elements.

**Colors:** Use the nearest neutral variable rather than a hardcoded value. When unsure which fits, default to `$light-1` for backgrounds and existing semantic variables (`$line-1`, `$text-1`) for borders and text.

---

## 9. Design-reference workflow

Use this workflow when a `design-reference/` folder exists inside the block directory.

### Step 1 — Read the design
Open every image in `design-reference/` and infer:
- Which custom field clones the block needs (headline, buttons, image-video)
- Whether an overline, wysiwyg, repeater, or layout-control field is present
- The HTML structure — how many columns, what nests inside what
- Visual details that will affect SCSS: rounded corners, overlays, background colour

Defaults when reading an image:
- Use **one wysiwyg** unless two clearly distinct text areas are separated in the design
- Use the **image-video clone** (not a plain image field) whenever media is shown — video is always optional and defaults to image
- When a side-by-side layout is shown, the image side goes first in the HTML (see §4)

### Step 2 — Surface ambiguities before writing anything
Call out everything you cannot determine from the image alone:
- **Is this image a wireframe/prior-art reference or the final design?** This decides the SCSS mode (§8) — design-reference vs screenshot-agency — and therefore how much is measured vs placeholder
- Is bold text a field variant (headline `**syntax**`) or just design styling?
- Image only, or should video be an option?
- Which side does the image default to?
- Does the block name match the folder name you were given?

Wait for answers to all open questions before proceeding to Step 3.

### Step 3 — Write files, then copy preview.png
Write all block files following the normal skill conventions. Afterwards:
- If `preview.png` does not already exist in the block root, copy the design reference image as `preview.png`

### When to use Figma MCP instead
Use this screenshot workflow when a static image has been dropped into `design-reference/`. Switch to Figma MCP when the user provides a Figma URL or frame reference — the MCP gives accurate token and component names that a screenshot cannot.

**Every typography scale comes from the node's actual bound variable, never from a role assumption ("overlines are `$heading-6`", "titles are always `$heading-2`") or from copying what a neighboring/legacy block did.** `get_design_context`'s generated markup inlines the bound variable directly (e.g. `text-[length:var(--overline-regular/font-size,16px)]`), and its "styles contained in the design" list names the text style too (`Overline/Overline Regular: Font(...)`); `get_variable_defs` on the node works too if that's ambiguous. Match the name you get back to the SCSS variable of the same name — don't round it to whatever §8's role table says is "typical," and don't assume it matches what an existing block in this codebase already does, since that block may have been built against an older Figma state. Designers rebind text styles; a hardcoded role→token rule goes stale silently and confidently. (This is the same class of mistake as §9's image-export gotcha below and §8's dark-mode card-fill rule — Figma's own data is always the source of truth over a remembered convention.)

### Pulling real images from Figma for CPT/demo content

When populating real posts (CPT rows, seed content) with images sourced from a Figma design, don't reuse the image URLs returned by `get_design_context`/`get_screenshot` — those are flattened renders of the node *as displayed* (already cropped/masked to whatever shape the frame applies, e.g. a circular avatar baked into a 96×96 PNG). Uploading that gives the WP media library a tiny, pre-cropped image with no room for the code's own sizing/`border-radius`/`object-fit` to work with responsively.

Use `download_assets` on the specific image-fill node instead — its `rawImages` array returns the original uploaded source file (full resolution, uncropped, correct native format) before Figma applied any mask or frame-level crop. Import that into WP media, and let the block's own SCSS (`aspect-ratio`/`object-fit: cover`/`border-radius: rem(999)`, etc.) handle the display shape — same principle as §8's image ratio guidance, just applied to the source asset instead of a hand-drawn placeholder. (Caught in `testimonial-2`: the first pass uploaded `get_design_context`'s 96×96 circle-masked avatar exports; re-pulled via `download_assets` to get the real 400×400 source photos.)

**Check a freshly-downloaded raw image against every already-uploaded attachment before importing it — Figma's placeholder photo library reuses the same source file across unrelated archetypes.** After `download_assets` returns a `rawImages` URL, `curl` it to a temp file and `md5sum` it against the temp copies (or `curl` the live `wp-content/uploads/.../*.jpg` files) of images already uploaded this session, before deciding to import. A visually-similar photo across two archetypes is not evidence either way — only the checksum is. When it matches, reuse the existing attachment ID instead of re-uploading a duplicate; when it doesn't, upload once with the correct final filename (never a placeholder/temp name that needs a later rename-and-reimport cycle). Confirmed recurring in the Content Image library: `content-image-7`'s two collage photos turned out to be the exact same fjord/canoe-portrait images already uploaded for `content-image-2`/`content-image-4`, and `content-image-3`'s second row reused the same mountain/canyon photo already uploaded for `content-image-8` — both caught by checksum before a redundant upload happened.

---

## 10. Custom Post Types (CPT) for post-driven blocks

Use this when a block needs to display posts from a CPT (sliders, grids, listings) rather than ACF-authored repeater rows.

**Check for an existing CPT first** — look in `app/post-types/` before registering a new one.

**Registering a new CPT** — create `app/post-types/{slug}.php`:

```php
<?php

/**
 * Register the {slug} CPT
 *
 * @return void
 */
function register_{slug}_cpt(): void
{
    register_cpt('{slug}', '{Singular label}', '{Plural label}', [
        'menu_icon' => 'dashicons-{icon}',
    ]);
}
add_action('init', 'register_{slug}_cpt', 10);
```

- File name matches the CPT slug exactly (`material.php` → `material`, `projeto.php` → `projeto`)
- No ACF field group is needed unless the block requires fields beyond title/thumbnail — `register_cpt()`'s defaults already include `title`, `thumbnail`, `editor`, `excerpt`, `author`, `revisions` support
- **PT-BR builds:** the slug itself (first argument) must be Portuguese too, not just the labels — e.g. `projeto`, not `project`. Check the active locale as in §3. File and function names follow the slug, so they end up Portuguese too — one of the few places non-English identifiers are correct in this codebase
- No manual registration wiring needed — `app/` is scanned recursively by `functions.php`

---

## 11. Post query fields — auto/manual toggle

Use this ACF field pattern whenever a block lists a CPT's posts. Reference: `slider-materiais`.

Fields, in order, nested inside an `accordion` field labeled with the CPT's plural name:

| Field | Type | Notes |
|---|---|---|
| *(accordion)* | `accordion` | `name: ""` — groups the fields below visually in the editor |
| `query_type` | `button_group` | Choices `auto` / `manual`, `default_value: "auto"` |
| *(message)* | `message` | Shown only when `query_type == auto`; text: `"Os últimos {plural} serão exibidos automaticamente."` |
| `{plural}` | `relationship` | Shown only when `query_type == manual`; `post_type: ["{slug}"]`, `post_status: ["publish"]`, `filters: ["search"]`, `return_format: "object"` |

The relationship field's `name` is the plural CPT name (PT-BR builds: Portuguese, e.g. `materiais`, `projetos`) — this is the array key the render template reads.

**Render template pattern:**

```php
$fields = get_fields();
$posts = $fields['{plural}'] ?? [];

if ($fields['query_type'] === 'auto') {
    $query = new WP_Query([
        'post_type' => '{slug}',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    ]);
    $posts = $query->posts;
    wp_reset_postdata();
}

if (empty($posts)) return;
```

Both branches leave `$posts` as an array of `WP_Post` objects — the relationship field's `return_format: "object"` matches `WP_Query::$posts` shape, so the rest of the template never has to branch on query type again.

**Call `setup_postdata($post)` inside the render loop (both branches) and `wp_reset_postdata()` after it — not just after an auto-mode `WP_Query`.** Any per-item rendering that calls a template tag relying on the *global* `$post` — `get_the_excerpt()` (and this codebase's `custom_excerpt()`, which wraps it with no `$post` argument of its own), `the_title()`, `the_permalink()`, etc. — silently reads whichever post the global happened to be pointing at, not the post you're looping over, unless the global is explicitly updated per iteration:

```php
foreach ($posts as $post) {
    setup_postdata($post);
    component('card-{name}', ['post' => $post]);
}
wp_reset_postdata();
```

This applies identically to manual mode (an array from the `relationship` field) — it's not a `WP_Query`-only concern, since `setup_postdata()` is what updates the global, not the query object itself. **This is a real, already-shipped bug, not a hypothetical**: several `.claude/design-references/` blocks (e.g. `borealis/grid-blog`) call `custom_excerpt()` inside a bare `foreach($posts->posts as $post)` with no `setup_postdata()` — every card on those pages silently rendered the excerpt of whatever page the block happened to sit on, not the excerpt of the post the card was actually for. Don't port that omission when adapting one of those blocks into this framework; add the `setup_postdata()`/`wp_reset_postdata()` pair even though the reference doesn't have it.

---

## 12. Card components for CPT posts

When a block renders a repeated item tied to a single CPT post (thumbnail + title, etc.), stop and ask the user two things before writing any markup — don't assume either:

1. **Does the card link to the post's detail page?** Default assumption is yes (`<a href="<?= get_permalink($post->ID) ?>">` wrapping the whole card) — CPT cards link out unless the user says it's a purely visual grid/slider with no link.
2. **Should this be a reusable component** (`src/content/components/custom/card-{name}/` — cards are project-specific, so they belong in the `custom/` tier, not `style-guide/`; see `CLAUDE.md`'s Component pattern section) **or stay inline** in the block's own template? This varies per block — sometimes the same card design is reused across blocks and earns a component, sometimes it's a one-off. Ask; don't default to either.

**Standard shape** (when built as a component) — reference: `card-material` (`src/content/components/custom/card-material/`):

```php
<?php
/** @var $args */

$default_args = [
    'html_tag' => 'article',
    'max_size' => 'medium',
];

$args = wp_parse_args($args, $default_args);

if (!$args['post']) return;

$post = $args['post'];

ob_start();
?>
    <a class="{name}" href="<?= get_permalink($post->ID) ?>" title="<?= $post->post_title ?>">
        <div class="image-wrapper">
            <?php component('image', ['post_id' => $post->ID, 'max_size' => $args['max_size']]) ?>
        </div>

        <p class="label"><?= $post->post_title ?></p>
    </a>
<?php
render_component(ob_get_clean(), $args);
```

Called from the block as `component('card-{name}', ['post' => $post])` per item.

**SCSS shape:**

```scss
.{name} {
    position: relative;
    overflow: hidden; // clips the hover zoom below
    display: block;
    height: fluid(280, 360); // TODO: confirm ratio against design

    .image-wrapper {
        @include fit;

        .image,
        .img {
            @include fit;
        }

        &::after {
            @include fit; // the mixin already sets content: '' — don't repeat it

            background: linear-gradient(to bottom, rgba(0, 0, 0, 0) 60%, rgba(0, 0, 0, 0.7) 100%);
        }
    }
}
```

**Hover affordance — nice to have by default.** Add a subtle image zoom and/or overlay darken on hover, transitioning with the timing tokens in `_variables.scss` (`$quick` 100ms, `$fast` 250ms, `$slow` 500ms, `$smooth` 1000ms, `$ease` — `cubic-bezier(0.5, 0, 0, 1)`):

```scss
.img { transition: transform $slow $ease; }
.image-wrapper::after { transition: opacity $slow $ease; }

&:hover {
    .img { transform: scale(1.08); }
    .image-wrapper::after { opacity: 0.85; }
}
```

**Fading text inside a container that's also resizing (an expand/collapse, an accordion item growing) needs an asymmetric transition, not one shared duration for both directions — otherwise the text visibly reflows into more/fewer lines *during* the fade, which reads as broken rather than smooth.** If the text box's width tracks the animating container (e.g. `left`/`right` insets, or `width: 100%`), a symmetric `transition: opacity $slow $ease` on both the "hidden" and "visible" states means the text stays partially visible for the whole resize, so the viewer sees it re-wrap live. Fix by giving the *closing* transition (the rule for the state you're animating away from) a fast, undelayed fade, and the *opening* transition (the rule for the state you're animating to) a fast fade with a `transition-delay` equal to the container's own resize duration — text disappears before the box has visibly changed size, and only reappears once the box has already settled at its final width:

```scss
// Base/closed state — governs the transition *away from* this state (closing)
.item-content {
    opacity: 0;
    transition: opacity $fast $ease; // fast, no delay — gone before the resize is visible
}

// Active/open state — governs the transition *into* this state (opening)
&.is-active .item-content {
    opacity: 1;
    transition: opacity $fast $ease $slow; // waits out the container's own resize, then fades in fast
}
```

CSS picks up whichever rule's `transition` the element is animating *toward* — the closing rule's duration governs fade-out, the opening rule's governs fade-in — so this asymmetry is a normal, supported pattern, not a hack. (Caught 2026-07-09 in `accordion-13`'s Slats: the open item's answer text is `left`/`right`-anchored inside the item, whose own `flex-basis` transitions over `$slow`; a symmetric `$slow` opacity fade on the text made the paragraph visibly re-wrap mid-fade every time an item opened or closed.)

---

## 13. Editing an existing block

Editing is more dangerous than creating — a published block has live content stored against its field names on real pages. Before touching an existing block, classify the change:

| Class | Examples | Risk / required action |
|---|---|---|
| **style-only** | SCSS edits, class additions the PHP mirrors, markup reorder with same fields | Safe — proceed after stating the classification |
| **additive** | New optional ACF field, new optional markup | Safe for existing content (new field is empty everywhere) — confirm the render template guards the empty case, then proceed |
| **breaking** | Renaming or deleting an ACF field `name`; changing a field's `type`; changing the field group `location` value; renaming the block folder; changing a class name the SCSS or JS targets | **Stop and walk through before editing** — see below |

**Why field renames are breaking:** ACF stores post content keyed by field `name`. Renaming `title` to `headline` in the JSON does not migrate anything — every existing page silently loses its value (the data stays in the DB under the old name, invisible to the editor and the render template). Renaming the block folder is worse: the WP block name `acf/{name}` changes, and every page using the old name shows a "block not found" error.

For a breaking change, report before editing: what breaks, which pages/data are affected (or that you can't determine it from code alone — content lives in the DB), and the migration story (keep the old field name; or accept data loss explicitly). **Wait for explicit confirmation.**

Also applies here:
- Changing what the block passes to a shared component is a *component* edit contract question — check the component's arg handling; if the component itself must change, that's the component-builder skill's Mode 2
- `block.json` remains auto-generated — edit `config.php`, never `block.json`
- Hand-edited ACF JSON takes effect at runtime immediately (local JSON has priority over the DB copy), but the wp-admin field-group editor keeps showing the stale DB copy until it's synced (ACF → Field Groups → Sync available). Flag this after any hand edit: if someone saves the group from the admin UI *without* syncing first, the UI's stale copy overwrites the hand edit

---

## 14. Publishing a library block category's demo page

Every `library/` block category (Accordion, Carousel, Content + Image, Listing, Timeline, …) has a corresponding demo page in wp-admin, and most of that scaffolding **already exists before you start building** — check for it before creating anything new.

**The pages live as children of the "Blocks" page** (title "Blocks", slug `block-listing`). Before creating a demo page for a new block/category, check for an existing child page first:

```bash
wp post list --post_type=page --post_parent=<Blocks page ID> --fields=ID,post_title,post_name,post_status
```

These child pages are frequently **pre-provisioned ahead of the actual block work**, seeded with leftover scaffold content from whatever block template was used to create them (a real, already-seen example: a page titled "Listing" existed with a `stats-1` block full of lorem-ipsum/placeholder values still in it). Creating a fresh top-level page instead of finding and reusing this one produces a confusing duplicate — always search first, and if a matching child page exists:

1. Read its current `post_content` — if it's placeholder/scaffold content unrelated to the block you're building, clear it (`wp post update <id> --post_content=""`) before inserting the real content. Don't leave old scaffold content sitting alongside the new blocks.
2. Populate it with the real block(s) for that category (see §3's "Hand-authoring saved block instances" for the exact `<!-- wp:acf/{name} {"data":{...}} /-->` JSON shape).

**Writing PT-BR accented content through WP-CLI:** don't pass post content as an inline `--post_content="…"` argument when it contains non-ASCII characters or the `<!-- wp:acf/… {...} /-->` JSON block syntax — shell quoting reliably mangles both. Generate the content in a small PHP script using `json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)` for each block's `data` payload, write the full page content to a file, and pipe it in: `wp post update <id> - < content.html` (the bare `-` argument reads `post_content` from STDIN). Verify afterward with `wp post get <id> --field=post_content` that accents survived intact.

**Navigation**: the Primary menu's "Dropdown" custom item lists every one of these demo pages, **alphabetically ordered by title**. When you add a new category's demo page, add a corresponding item:

```bash
wp menu item add-post primary <page-id> --title="<Title>" --parent-id=<Dropdown db_id> --position=<n>
```

**`--position` on `wp menu item add-post` does not reliably land at an absolute slot** — it appeared to behave as a relative insert in practice, leaving neighboring items at unexpected positions rather than the clean shift you'd expect. After adding (or reordering) items, don't trust the position you passed — re-list the whole menu (`wp menu item list primary --fields=db_id,title,menu_item_parent,position`) and explicitly re-assert every affected item's absolute position with individual `wp menu item update <db_id> --position=<n>` calls until the listed order matches the intended alphabetical sequence. Verify by re-listing once more.

---

## 15. Checklist

When building a new block, verify:

- [ ] §0 checkpoint happened: field list, structure, SCSS mode, and open questions were confirmed by the user **before** any file was written
- [ ] Folder is at the correct path under `src/content/blocks/{category}/{block-name}/`
- [ ] `config.php` sets `title` and `icon`, and `field_defaults` for `title.html_tag` (h2 unless the block is a hero) and `spacing_top`/`spacing_bottom` (`default` unless the block is deliberately edge-to-edge, e.g. `none`); no other extra keys unless necessary
- [ ] `{block-name}.php` follows the six-step skeleton; `render()` is the last line
- [ ] `preview.png` present — required before a block is production-ready; a placeholder PNG is acceptable during early wireframe development. If `design-reference/` exists and `preview.png` does not, copy the design image as `preview.png`
- [ ] `acf-json/block-{block-name}.json` title prefix is exactly `"Block: "` followed by the title-cased name
- [ ] ACF location value matches `acf/{block-name}` exactly
- [ ] Key checks from §5 were actually run: the duplicate-key grep printed nothing and no `REPLACE` placeholder survived
- [ ] Field order follows: overline → title clone → primary content → buttons clone → image clone → layout controls
- [ ] Repeater sub-fields each have `parent_repeater` set
- [ ] JS file only exists if the block needs interactivity; uses `window.theme.block()` pattern
- [ ] `block.json` is not manually edited
- [ ] SCSS starts with `@use 'util' as *;`, wraps everything in `@include block('block-name')`, and never sets `padding-top`/`padding-bottom`/`padding` on that root — vertical spacing comes only from `config.php`'s `field_defaults` (see §8)
- [ ] Every SCSS selector is nested inside its actual DOM parent selector (matching the `.php` div structure), except `.headline`, which stays a flat direct child of `@include block(...)`; any `&.bg-is-dark`/`&.bg-is-light` override repeats the base rule's full nesting path, not just the modifier class (see §8 "Selector nesting mirrors the DOM")
- [ ] `config.php`'s `title` (and any new CPT slug) is in Portuguese if the project's active ACF JSON locale is PT-BR — see §3, §10
- [ ] If the block lists CPT posts, checked `app/post-types/` for an existing CPT before registering a new one
- [ ] If the block loops over queried/related posts, `setup_postdata($post)` is called per-iteration (both auto and manual branches) and `wp_reset_postdata()` after the loop — not just after an auto-mode `WP_Query` — see §11
- [ ] If the block shows a card tied to a CPT post, asked the user whether it links to the detail page and whether it should be a reusable component before writing markup — see §12
- [ ] If this is a new library block category, checked for an existing pre-provisioned demo page under "Blocks" before creating one, cleared any leftover scaffold content, and added/repositioned its Primary-menu Dropdown entry alphabetically — see §14
- [ ] If a library block was adopted in an English project: only display strings were translated (labels, `button_label`, `message`, choices values, `config.php` title — never `name`/`key`/choices keys/`default_value`), verified with `references/verify-acf-translation.php` — see §5
- [ ] If the work modified an existing shared component, the component-builder skill's Mode 2 impact check was run first — never patched inline
- [ ] If editing an existing block, the change was classified per §13 and breaking changes were confirmed before any edit
- [ ] Any component-driven element's default typography (`.headline`, etc.) is a flat, direct child of the block root — never nested inside `.header` or another wrapper (see §8)
- [ ] `ember-entrance-group` is on the block's outer content container and `ember-entrance` is on each top-level structural section (header, content-wrapper, image-wrapper, slider-wrapper, etc.) — repeated items inside a grid/slider/list share one `ember-entrance` on their container, not one each (see §4)
- [ ] Any `&.bg-is-dark` card/panel fill **or decoration/icon accent color** was read from Figma's actual dark counterpart frame (`get_variable_defs`/`get_design_context`), not approximated with opacity math or the generic `$primary→$secondary` swap table — the table is a fallback for when no dark frame exists, not a substitute for checking one that does (see §8); promote reused card/panel values to a real `_variables.scss` variable; the override selector's nesting path matches the base rule's (not just the modifier class), verified by comparing compiled selector class counts, not just that the rule exists
- [ ] If the block has any `&.bg-is-dark` section, every `color:`/`background:` declaration in the whole file was audited: inherits fine (delete a redundant hardcoded `$text-1`), stays constant by design (`$text-3`), has a same-depth dark override, **or is a deliberately unchanged fixed-light-surface element (e.g. an Input) that needs no override at all** — not just the properties that felt like they needed one while writing top-to-bottom (see §8)
- [ ] Any element a block's own JS hides via `.hidden`/`[hidden]` (search/pill/category filtering) has an explicit `&[hidden] { display: none; }` wherever that element also carries its own `display` value (`@include stack()`, `display: grid`, etc.) — otherwise the author rule silently beats the UA stylesheet and nothing visually hides; verify with `getComputedStyle(el).display`, not just the `hidden` IDL property (see §4/§8)
- [ ] When converting from a real design (Figma/screenshot), every typography scale was read off the node's actual bound variable/text style — not assumed from §8's role table or copied from a neighboring/legacy block (see §8 Typography, §9's Figma MCP section)
- [ ] Any dark-`bg_color` instance of a block with buttons uses the `-light` style variant for every button, same position/order (see §8)
- [ ] Any image pulled from Figma into real CPT/demo content came from `download_assets`'s `rawImages` (original source), not a flattened/cropped `get_design_context`/`get_screenshot` export (see §9)
- [ ] If block instances were hand-authored onto a page (not inserted via the editor), every `field_defaults`-covered field in the saved JSON matches `config.php` exactly, unless a deviation was explicitly confirmed with the user (see §3)
- [ ] Every `wysiwyg` field is rendered via `component('rich-text', ['text' => ...])`, never echoed raw — see §4
- [ ] Every `component('image-video'/'image', ...)` call passes an explicit `max_size` measured from the block's own `.scss` container width (not guessed/copied), and `sizes_attr` is added only if the real max width genuinely differs between mobile and desktop regimes — see §4
- [ ] Any `.image-wrapper` sizing an `image-video`/`image` component has `position: relative` and applies `@include fit` to all three of `.image-video, .image, .img` together — verify on the real front-end render, not just the editor preview (see §8)
