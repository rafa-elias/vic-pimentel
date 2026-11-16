---
name: wp-framework-component-builder
description: Use when creating a new reusable component or modifying an existing one in the rear-theme framework. Covers the component() calling contract, render_component() output, parts_path() delegation, SCSS conventions, and a mandatory impact-check workflow before any edit to an existing component.
---

# Component builder — rear-theme framework

Components are reusable UI pieces shared across blocks, sections, and other components. They live in `src/content/components/{tier}/{name}/` and are loaded via `component()` → `get_template_part()`. This skill covers two modes: building a new component from scratch, and safely editing an existing one.

Blocks are a different thing with their own skill (`.claude/skills/wp-framework-block-builder/SKILL.md`) — if the deliverable is a block that happens to need a new or edited component, both skills apply: this one governs the component work, that one governs the block work.

---

## Mode 1 — Creating a new component

### 1. Folder location, tier, and naming

Components live under one of three tier subfolders:

| Tier | Purpose |
|---|---|
| `style-guide/` | Base design-system building blocks (button, headline, image-video, modal, accordion, tabs, …) |
| `custom/` | Project-specific components (header, footer, mega-menu, card-*, …) |
| `library/` | Reference-only components not chosen for this project — source stays, compiled CSS/JS is excluded from the enqueue automatically (`library_component_names()` in `core/core.php`) |

**Pick the tier before creating any file:** design-system-level building block → `style-guide/`; bespoke to this project → `custom/`; built as a reference but not used here → `library/`. Cards tied to a CPT are always `custom/`.

```
src/content/components/{tier}/{name}/
├── {name}.php          # required
├── {name}.scss         # optional — compiled to public/components/{name}.css
├── {name}.js           # optional — compiled to public/components/{name}.js (see §7)
└── parts/              # only when using parts_path() delegation (see §5)
    └── {part-name}.php
```

Callers never pass the tier — `component('button', …)` resolves the bare name through `resolve_component_folder()` (checks `style-guide/`, then `custom/`, then `library/`, cached per-request). Because resolution is by bare name, **a component name must be unique across all three tiers** — check all of them before creating.

Name is kebab-case, descriptive of the UI element: `rich-text`, `image-video`, `swiper-controls`. No numeric suffixes — components are shared utilities, not variants.

---

### 2. Component PHP skeleton — universal eight-step pattern

```php
<?php
/** @var $args */

// 1. Declare defaults
$default_args = [
    'html_tag' => 'div',
    // component-specific defaults
];

// 2. Inject static class BEFORE wp_parse_args
$args['class'] = 'my-component ' . $args['class'] ?? '';
// Note: the `?? ''` is inert (`.` binds tighter than `??`) — this only works
// because component() always injects a 'class' key before the template loads.
// Safe inside a component file; don't copy this idiom anywhere else.

// 3. Merge — passed args win over defaults
$args = wp_parse_args($args, $default_args);

// 4. Post-merge class manipulation (only when class depends on a merged value)
// $args['class'] .= ' style-' . $args['style'];

// 5. Bail before ob_start if required data is missing
if (empty($args['required_field'])) return;

// 6. Build inner HTML
ob_start();
?>
    <!-- inner markup; component() calls go here -->
<?php

// 7. Render
render_component(ob_get_clean(), $args);
```

**Why the order matters:**

- Step 2 prepends the component's own static class before the merge. After step 3, `$args['class']` = `'my-component {caller-extra-class}'`. Step 4 appends dynamic classes that depend on values only available after defaults are merged.
- `wp_parse_args($args, $default_args)` gives priority to whatever was passed in — defaults only fill gaps. Never swap the argument order; that would let defaults silently overwrite the caller's values.
- Bail happens in step 5, after the merge, so guards can use merged values. Always before `ob_start()` — never inside the buffer.

---

### 3. The `component()` calling contract

`component(string $name, $args1 = null, $args2 = null)` — defined in `core/core.php`.

**Single-arg form** — most common:
```php
component('headline', $fields['title']);
component('rich-text', ['text' => $fields['content']]);
```

**Two-arg form** — data + display config:
```php
component('image-video', $fields['image_video'], ['max_size' => 'large']);
component('image', $args['cover_image'], ['use_default_image' => true, 'lazyload' => true]);
```
`$args2` wins on key collision. Use this when the primary data is an ACF sub-array and the caller needs to impose display settings on top without altering the data array.

**What `component()` always injects into `$args` before loading the template:**

| Key | Value | Source |
|---|---|---|
| `name` | the component name | first argument |
| `class` | normalised with trailing space | merged from `$args1` |
| `block_parent` | `'blocks'` or `'components'` — which content root the calling file lives under | `debug_backtrace()` |
| `block_folder` | the calling file's folder path **relative to that root** — e.g. `library/accordion-1`, `custom/mega-menu`, `style-guide/tabs` | `debug_backtrace()` |
| `file_name` | calling file's name without extension | `debug_backtrace()` |

`component()` anchors the caller's path on the known content roots (`src/content/blocks/`, `src/content/components/`), so `block_folder` carries the full path underneath — category/tier included — and `parts_path()` stays correct at any nesting depth. Callers outside those roots (e.g. `templates/`) fall back to the last two path segments. These three keys are what make `parts_path()` work — they record where the call came from, and callers must never overwrite them. A component calling another component injects its own location, not the outer block's, which is how mega-menu owns the parts for the tabs it embeds (see §5).

---

### 4. `render_component()` — what it emits

`render_component($content, $args)` — defined in `core/components.php`.

```html
<{html_tag} class="component component-{name} {class}" data-component="{name}" {attr...}>
    {content}
</{html_tag}>
```

- `html_tag` defaults to `div`. Override it when semantics require a different element — headline receives `html_tag` from ACF and passes it through; button sets `$args['html_tag'] = 'a'` or `'button'` before calling `render_component`.
- `$args['attr']` is an optional key→value array of extra HTML attributes merged onto the root element. Used by button for `href`, `target`, `rel`, `aria-label`, `type`.
- Silently returns (zero output) if `$content` is empty. A component that bails before `ob_start()` produces no wrapper tag — the caller sees nothing.

---

### 5. When and how to use `parts_path()` delegation

**Use parts when** the component renders a list of items and the per-item markup must vary by caller. The accordion's toggle label and panel body differ completely between `accordion-1` and `accordion-2`. The tabs component's tab label and panel content differ between a block and the mega-menu embedding it.

**Do not use parts** for simple parameterisation — adding an optional arg is almost always simpler and less coupling.

**Guard first:** if parts are required for the component to function at all, refuse to render without the backtrace keys:
```php
if (empty($args['block_parent']) || empty($args['block_folder'])) return;
```

**Strip data before building `$clean_args`:**
```php
$clean_args = $args;
unset($clean_args['data']); // strip the full dataset; parts don't need it
```

**Loop and delegate via `get_template_part` (not `component`):**
```php
foreach ($args['data'] as $index => $item) {
    get_template_part(
        parts_path($args) . '/part-name',
        null,
        ['item' => $item, 'args' => $clean_args]
    );
}
```

Parts are loaded directly — they are not wrapped in `render_component`.

**Standard part file skeleton:**
```php
<?php
/** @var $args */
$item = $args['item'];   // the current data row
$args = $args['args'];   // config originally passed to component()
// echo markup, call component() — never render_component()
```

**How `parts_path()` resolves** (from `core/core.php`) — the path is `src/content/{block_parent}/{block_folder}/parts`:

| Caller | `block_parent` | `block_folder` | Resolved path |
|---|---|---|---|
| `blocks/library/accordion-1/accordion-1.php` | `blocks` | `library/accordion-1` | `src/content/blocks/library/accordion-1/parts` |
| `blocks/custom/my-block/my-block.php` | `blocks` | `custom/my-block` | `src/content/blocks/custom/my-block/parts` |
| `components/custom/mega-menu/mega-menu.php` | `components` | `custom/mega-menu` | `src/content/components/custom/mega-menu/parts` |

(Special case: a caller whose `block_folder` is `templates` resolves to `templates/parts/{file_name}` instead — that's how page templates own parts.)

**Inherited parts — the mega-menu → tabs pattern:** When a component calls a parts-using component (mega-menu calls `component('tabs', ...)`), `debug_backtrace()` captures the outer component as caller. Tabs' parts then resolve to `mega-menu/parts`. A component can own the parts for any nested parts-using component it embeds by placing them in its own `/parts` subfolder.

**Modal's variant — optional parts, different envelope:** Modal supports `'html' => $string` (bypass parts, echo directly) or `'content' => $data` (use parts). The content path loads `parts_path($args) . '/modal'` and passes `$args['content']` flat — no `['item' => …, 'args' => …]` envelope. This is the only exception to the standard envelope. Currently only the `html` path is in use (video-player).

---

### 6. SCSS — `{name}.scss`

Same abstract system as blocks. Mandatory structure:

```scss
@use 'util' as *;

@include component('name') {
    // all component styles go here
}
```

`@include component('name')` compiles to `body .is-root-container .component-{name} { … }`. This scoping matches the `data-component` attribute and prevents leakage into unrelated contexts. Never use `.component-{name}` or `[data-component="name"]` as a direct selector.

The `&` inside the mixin refers to `.component-{name}`. Use it for state and style modifiers:

```scss
@include component('button') {
    // base styles apply to every button

    &.style-primary { … }      // .component-button.style-primary
    &:hover { … }              // .component-button:hover
    &:disabled { … }           // .component-button:disabled

    .button-wrapper { … }      // .component-button .button-wrapper
}
```

For style-variant components (headline, button), modifiers are `&.style-{variant}` matching the class appended in the PHP. For state components (accordion), modifiers are `&.is-active`, `&.has-cover`, etc., matching whatever PHP adds to `$args['class']`.

**Available abstracts** (all in scope via `@use 'util' as *`): same as blocks — `typography()`, `media()`, `fluid()`, `rem()`, `stack()`, `fit()`, gap variables, color variables. Components have no equivalent of `$vertical-default` — they handle their own spacing as the design requires.

**Nest internal-element selectors to match the component's own DOM structure** — same rule as blocks (`wp-framework-block-builder/SKILL.md` §8 "Selector nesting mirrors the DOM"): if the component markup has `.button-wrapper` containing `.icon`, write `.button-wrapper { .icon { … } }`, not flat siblings. The block-level `.headline` flat-at-root exception doesn't apply here — it's about a *block* overriding a component's own style from outside; a component styling its own internal markup nests normally throughout.

**A component that delegates its entire render to another `component()` call — never calling its own `render_component()` — needs two manual adjustments, both because the normal auto-wiring is keyed off that call.** Pattern: a thin wrapper component whose `.php` builds some markup via `ob_start()`/`ob_get_clean()` and hands it straight to `component('modal', ['html' => ob_get_clean()])` (or similar) as its *only* output, the way `video-player` and `member-modal` do — there is no `<div data-component="{name}">` wrapper of its own anywhere in the DOM.

1. **CSS is registered but never enqueued.** `theme_dir_assets()` (`core/assets.php`) registers every component's compiled CSS up front but only *enqueues* it lazily, inside `render_component()`, keyed by the component name passed there. A delegating component's own `render_component()` never runs (only the delegate's does, with a different name), so its stylesheet silently stays registered-but-unloaded — the component's own SCSS file compiles fine and looks correct in the source, but nothing on the page ever requests it. Fix: call `wp_enqueue_style('style-component-{name}')` explicitly, once, in the delegating component's `.php` file. Confirmed missing exactly this way in `member-modal` — the component looked fully unstyled in the browser with zero build errors anywhere to explain why.
2. **`@include component('name')` in the SCSS compiles to a selector that will never match anything.** That mixin scopes to `.component-{name}`, a class `render_component()` adds — which, per point 1, never gets added here. Use the lower-level `@include context('.some-real-class-in-the-markup')` (`_contexts.scss`) instead, scoping directly to a class the delegating component's own markup actually renders (e.g. `member-modal.scss` scopes to `@include context('.member-profile')`, the wrapper div it builds before handing off to `modal`).

---

### 7. JS — `{name}.js` (optional)

Compiled individually to `public/components/{name}.js` and enqueued automatically (except for `library/` tier components, whose assets are excluded). Same entrypoint pattern as blocks, via the component method:

```js
window.theme.component('component-name', el => {
    // el is the outer <{html_tag} data-component="component-name"> wrapper,
    // called once per component instance on DOMContentLoaded.
    // Scope ALL DOM access inside el — never query document directly.
    const toggle = el.querySelector('.js-toggle');
    if (!toggle) return;
});
```

- `window.theme` is defined in `src/scripts/core.js` (imported by `app.js`)
- Only create the JS file if the component needs interactivity
- JS-hooked elements in the PHP markup use `js-` prefix classes (`js-item`, `js-toggle`) — never style these classes, never hook behavior on styling classes
- Third-party packages registered globally are available as `window.theme.packages.*` (e.g. `window.theme.packages.swiper`)
- Reference implementation: `style-guide/accordion/accordion.js`

---

## Mode 2 — Updating an existing component

This mode is **impact-check-first, always**. Do not touch any component file until steps 1–3 are complete and walked through explicitly.

### Step 1 — Find every call site

```bash
grep -rnE "component\(\s*['\"]{name}['\"]" src/ templates/ --include="*.php"
```

`templates/` sits **outside** `src/` — a grep scoped to `src/` alone silently misses page-template call sites (they exist, e.g. `templates/parts/globals/`). The command must cover all four locations:
- `src/content/blocks/` — block render templates
- `src/content/components/` — components calling other components
- `src/content/sections/` — section files
- `templates/` — page templates and their parts

Also identify any **parent component** that delegates to this one internally — `buttons` calls `button`, `image-video` calls `image` and `video-player`, `video-player` calls `modal`. Those are indirect call sites that inherit any breaking change.

### Step 2 — Classify the change

| Class | What it covers | Action |
|---|---|---|
| **style-only** | Class name additions, markup-order tweaks, new CSS-only inner elements — nothing callers pass or depend on structurally | Report the classification, then proceed without waiting |
| **additive-structural** | New optional arg with a default value; new inner HTML callers don't need to interact with | Full Step 3 walkthrough + confirmation required |
| **breaking-structural** | Rename or remove an arg existing callers pass; change a class name that block or component SCSS targets; change `html_tag` default; alter the parts envelope format; remove or invert a bail condition | Full Step 3 walkthrough + confirmation required; must either update every affected call site, or explicitly list which ones need manual attention |

When uncertain between additive and breaking, classify as breaking. When uncertain between style-only and additive, classify as additive.

**Steps 1 and 2 always run — for every Mode 2 edit, no exceptions.** What varies by class is only whether you stop: style-only changes may proceed immediately after stating the classification and call-site count; additive and breaking changes require the Step 3 stop. For the design-sensitive components listed below, the Step 3 stop is required even for style-only changes.

### Step 3 — Walk through before touching any file

Report:
1. Every file that calls the component, with line numbers
2. The change classification and the reasoning
3. For breaking-structural: what changes in each affected file, and whether you will update them automatically or flag them for manual attention

**Stop. Wait for explicit confirmation before any file is modified.**

---

## Design-sensitive components

These components are high-frequency-change and high-impact. Always run the full Mode 2 impact check for them — even for requests that sound minor ("just tweak the markup a little"):

| Component | Why flagged |
|---|---|
| `header` | Sitewide; touches nav state, JS interactions, mobile menu |
| `footer` | Sitewide; legal and link content |
| `buttons` | Called from nearly every block; wraps `button` |
| `button` | Called by `buttons`, `mega-menu/parts/panel`, and directly in some blocks; arg shape is complex (`data` normalisation, `html_tag` switching, `attr` building) |
| `rich-text` | The standard body-text renderer; used in most content blocks |
| `image-video` | Routes between `image` and `video-player`; changing args or bail conditions silently breaks media in many blocks |
| `image` | Called by `image-video`, `mega-menu/parts/panel`, and some blocks directly. Also owns two dynamic mechanisms every caller inherits silently: the page's one first-image eager-loading/`fetchpriority` slot (`is_priority_candidate` arg, `theme_is_first_priority_image()`) and automatic WebP `<source>` serving — see `wp-image-performance/SKILL.md` before changing its arg contract or output markup |
| `swiper-controls` | Shared across all slider blocks; JS targets `.js-prev` / `.js-next` by convention — renaming those classes breaks every slider's navigation |
| `accordion` | Parts pattern; `block_parent`/`block_folder` guard means callers must always provide `parts/toggler.php` and `parts/content.php` |

For these: the impact check is non-negotiable regardless of how the request is phrased.

---

## Checklist

### New component
- [ ] Tier chosen deliberately (`style-guide/` design-system, `custom/` project-specific, `library/` reference-only) — file at `src/content/components/{tier}/{name}/{name}.php`
- [ ] Name checked for uniqueness across all three tiers
- [ ] `/** @var $args */` is the first line
- [ ] Static class injection (`$args['class'] = 'name …'`) happens **before** `wp_parse_args`
- [ ] `wp_parse_args($args, $default_args)` — not `array_merge`; passed args win
- [ ] Bail (if any) is before `ob_start()`, never inside the buffer
- [ ] `render_component(ob_get_clean(), $args)` is the last line
- [ ] No `render()` call — that's blocks only
- [ ] SCSS: `@use 'util' as *` + `@include component('name')` wrapping all rules; no direct `.component-{name}` selector
- [ ] JS (if any) uses `window.theme.component()`, scopes all queries inside `el`, hooks on `js-` classes
- [ ] If using parts: guard on `block_parent`/`block_folder`, strip data from `$clean_args`, use `['item' => …, 'args' => …]` envelope, `get_template_part()` not `component()`

### Existing component edit
- [ ] Grepped `src/` **and** `templates/` for call sites with line numbers
- [ ] Change classified as style-only / additive-structural / breaking-structural
- [ ] Additive/breaking (or any design-sensitive component): impact list and classification walked through with user, explicit confirmation received, before any file was modified
- [ ] Breaking-structural: every affected call site updated or explicitly flagged for manual attention
