---
name: wp-ember-animations
description: Use when adding, choosing, or debugging ember-js scroll-entrance animations on a block or component, or when asked to work with the animation library itself. Covers the ember-entrance/ember-entrance-group class convention, the default (don't pick one) rule for ordinary new-block work, the full 25-animation library, and every gotcha found hardening it.
---

# Ember entrance animations — rear-theme framework

`ember-js` (`src/scripts/static/ember.config.js`) + GSAP drive scroll-triggered entrance animations site-wide. An element fades/moves/reveals itself the first time it scrolls into view. This skill covers the class convention, the animation library, and the non-obvious bugs each animation type is prone to.

---

## 1. Default behavior for ordinary block work — READ THIS FIRST

**When building or editing a block and it's not specifically an animation task, just use the plain classes with no `data-entrance-anim` override:**

```html
<div class="wrapper ember-entrance-group">
    <div class="content ember-entrance">...</div>
</div>
```

No animation name means it resolves to the `primary` category, which defaults to `fade-motion` (`ember.defaults.defaultAnimations.primary` in `ember.config.js`). This is the long-standing convention across the library blocks (content-image, text, timeline, stats, etc.) and stays the default going forward.

**Do not assign a specific named animation (`data-entrance-anim="..."`) to a new block unless the user explicitly asks to customize or explore animations for that block.** The 10 hero blocks (`src/content/blocks/library/hero/hero-1` … `hero-10`) got creative, hand-picked animation pairings as a one-time demonstration exercise — that was a deliberate exploration, not a new standing convention. Animation choice is expected to vary per client project.

---

## 2. How the system works

- `src/scripts/ember-animations/*.js` — one file per animation, each a plain object: `{name, initial, completed, defaultOptions, always?, onSetup?, onGetNodes?, onComplete?, onParseOptions?}`. `initial`/`completed` are either a plain GSAP-vars object or a `({element, options}) => {...}` function.
- `src/scripts/static/ember.config.js` — imports and registers every animation via `window.ember.registerAnimations('entrance', [...])`, plus global defaults (`rootMargin`, `delayBetween`, per-animation duration overrides) and a DOMContentLoaded correction pass (see §4.7).
- `src/styles/common/_critical-overlays.scss` — pre-hides every `.ember-entrance` element (`opacity: 0`, plain rule, no `!important`) so the page doesn't flash fully visible before JS applies the real initial state.

**Markup convention:**
```html
<div class="wrapper ember-entrance-group">
    <div class="ember-entrance" data-entrance-anim="blur-fade">...</div>
    <div class="ember-entrance">...</div> <!-- no attr = default fade-motion -->
</div>
```
`.ember-entrance-group` is the scroll-observed ancestor; `.ember-entrance` elements inside it animate individually (staggered via the group's `delayBetween`) once the group enters view. `data-entrance-anim="{name}"` picks a specific registered animation by exact name — this takes priority over any class-based `ember-entrance--{category}` lookup, so always prefer the data attribute over `ember-entrance--*` classes.

**To target a specific element (e.g. just the headline, not its wrapper) instead of a whole wrapper div**, use the two-arg `component()` form:
```php
component('headline', $fields['title'], [
    'class' => 'ember-entrance',
    'attr' => ['data-entrance-anim' => 'line-reveal'],
]);
```
`render_component()` (`core/components.php`) merges `attr` into the rendered tag's attributes — works on any style-guide component (headline, rich-text, buttons, image, image-video, etc.), not just headline.

---

## 3. The animation library (25)

**Whole-element-safe** (transform/opacity/filter/clip-path only — safe on any wrapper regardless of how many children it has, safe on images):
`fade-motion`, `fade`, `roll`, `blur-fade`, `scale-fade`, `rotate-in`, `mask-wipe`, `skew-fade`, `elastic-pop`, `flip-in`, `spring-slide`, `iris-reveal`, `focus-pull`, `corner-peel`.

**Text-only, decorative** (whole-element-safe technically, but conceptually only make sense on text):
`highlight-sweep` (background sweep behind text), `underline-grow` (fades in + underline bar draws beneath).

**Text-splitting** (rewrite `element.innerText`/`innerHTML` — see §4.4, only ever target a single isolated text node):
`fade-letters`, `fade-words`, `slide-words`, `glitch-scramble`, `line-reveal`, `typewriter`, `stagger-pop`, `wave-letters`, `letter-flip`.

Full descriptions of each: `templates/ember-animations-text.php` and `templates/ember-animations-image.php` (see §5).

---

## 4. Gotchas — each of these caused a real, shipped bug

### 4.1 Container opacity never restored
`_critical-overlays.scss` pre-hides `.ember-entrance` via plain `opacity: 0`. If an animation's `completed` state never sets opacity back to `1` **on `element.node` itself**, the element stays invisible forever. This happens two ways:
- The animation never includes `opacity` in its state at all (pure clip-path/transform reveals like `roll`).
- `onGetNodes` retargets the GSAP tween onto split child `<span>`s (any text-splitting animation) — the opacity change lands on the spans, never on the parent that's actually hidden by CSS.

Fix pattern, used throughout the text-splitting and `roll`/`slide-words` animations:
```js
completed: ({element}) => {
    element.node.style.opacity = '1';
    return { /* real gsapState */ };
},
```

### 4.2 clip-path unit mismatch silently breaks the tween
GSAP needs matching units at each `clip-path` value position to interpolate it. Mixing `100%` on one side with a unitless `0` on the other makes it snap instead of animate (no error, no warning — it just doesn't visibly tween). Always use the same unit (usually `%` or `px`) on both `initial` and `completed`. Bit `mask-wipe` and `slide-words` before being caught.

### 4.3 Never animate literal `top`/`left`/`right`/`bottom`
Even under `position: relative`, Chrome's Layout Instability API counts changes to these as a layout shift (hurts CLS) and they force a layout recalc every tween frame — `transform` (GSAP's `x`/`y`) doesn't. `fade-motion`, `fade-letters`, `fade-words`, and `slide-words` originally used `'{direction}': '{offset}'` (a literal CSS property) and were converted to a small `AXIS_OFFSET` map returning `{x}`/`{y}` instead. Follow that pattern for any new directional animation.

### 4.4 Text-splitting animations destroy non-text/multi-child markup
`fade-letters`, `fade-words`, `slide-words`, `glitch-scramble`, `line-reveal`, `typewriter`, `stagger-pop`, `wave-letters`, `letter-flip` all read `element.node.innerText` and rewrite `innerHTML` in `onSetup`.
- On an **image wrapper**: `innerText` is empty, so `onSetup` wipes the image out to blank.
- On a **wrapper bundling multiple children** (e.g. a `.header` div with both an overline `<p>` and a headline): flattens them into one blob, losing the overline's own tag/class and typography.

Only ever apply these to a single, isolated text element — target the actual component (headline, a single-paragraph rich-text) directly via the two-arg `attr` pattern (§2), never a wrapper that also holds siblings.

### 4.5 Animating an image/background's own container hides the placeholder color
A `transform: scale()`/`clip-path` on an element scales/clips its *entire* rendering, background-color included — so if the same element that's animated also carries a placeholder `background-color` (used so the page's light background doesn't show through mid-reveal), that color shrinks/clips right along with it. Structure it as: a **static** outer wrapper carrying `background-color` (and `overflow: hidden` — see §4.6), with the `ember-entrance` animation on an **inner child**:
```html
<div class="image-wrapper">  <!-- static: background-color + overflow: hidden -->
    <div class="ember-entrance" data-entrance-anim="elastic-pop">
        <?php component('image-video', ...) ?>
    </div>
</div>
```
```scss
.image-wrapper {
    background: $primary;
    overflow: hidden;

    > .ember-entrance {
        @include fit; // REQUIRED — see next gotcha
    }
}
```

### 4.6 The inner entrance wrapper needs explicit sizing
That inner `.ember-entrance` div is a plain block with nothing sizing it. If its only child is `@include fit`'d (i.e. `position: absolute` — true of `image`, `image-video`, `background-media`'s own root), the div collapses to **zero height** (an absolutely-positioned child contributes nothing to a normal-flow parent's height). A zero-height clip-path reference box breaks `clip-path` shapes entirely (they clip to nothing regardless of progress) and makes scale/rotate transforms meaningless. Always give it `@include fit` too, as shown above. Missed this once in `hero-5` and once in `templates/ember-animations-image.php` — both had to be patched after the fact, so double-check it every time this pattern is used.

### 4.7 Scale overshoot needs `overflow: hidden`
`elastic-pop` and other `back`/`elastic`-eased scale animations briefly overshoot past 100%. Without `overflow: hidden` on the static outer wrapper (§4.5), that overshoot can push past the layout box and cause a scrollbar / visible layout shift.

### 4.8 Reload-mid-scroll skip recovery
`ember-js`'s own `_buildEntranceGroup()` permanently skips (never calls `gsap.set()`/`.to()` on) any group already scrolled past when `ember.init()` runs — e.g. a page refresh while mid-scroll. `ember.config.js`'s `DOMContentLoaded` handler detects this and manually replays both `initial` and `completed` phases for every element in a skipped group, so nothing is left stuck. If you ever touch that correction block, keep it doing a full phase replay, not just an opacity patch — an opacity-only patch left elements stuck mid-transform (blurred/scaled/rotated/clipped) for any animation using more than opacity.

### 4.9 `glitch-scramble` is the one animation that reflows while visible
Every other text-splitting animation touches `innerHTML`/`innerText` exactly twice — once in `onSetup` (while still hidden behind the CSS pre-hide, before paint) and once in `onComplete` to restore plain text. `glitch-scramble` is different: its `resolveScramble()` rewrites text on a `setInterval` *while already visible*, and random substitute characters can render at different widths than the real text, occasionally shifting a line wrap mid-reveal. It locks `minHeight` (measured with the real text, before scrambling) for the duration to contain that — keep that lock if you touch this file.

### 4.10 The first block skips entrance animation entirely, mobile only

The first block on the page (`.block:first-of-type` — only ever present on a rendered block's own `<section>` wrapper, so this reliably means "first block") is almost always the LCP element. Chrome doesn't count an `opacity: 0` element as "painted" for LCP purposes, so on mobile — where this cost matters most for Core Web Vitals — the first block's entrance animation is skipped **entirely**, not just the opacity pre-hide, via two paired changes:

- `common/_critical-overlays.scss` — below the `'phone'` breakpoint, `.block:first-of-type .ember-entrance-group .ember-entrance` (and `[class*=" ember-entrance--"]`) gets `opacity: 1 !important`, overriding the normal pre-hide. This alone only covers the brief window before JS runs.
- `ember.config.js`'s `DOMContentLoaded` handler — on the same breakpoint (`window.innerWidth < 768`, kept in sync with `'phone'`), strips the `ember-entrance-group` class from `document.querySelector('.block')`'s own entrance groups **before** `ember.init()` runs. This is enough on its own: `_gatherEntranceElements()` (`ember-js/modules/ember-entrances-module.js`) only ever discovers entrance elements by querying `.ember-entrance-group` at the top level, then looking for `.ember-entrance` descendants *within* each matched group — there's no separate document-wide query for `.ember-entrance` itself. Once the group class is gone, ember-js never reaches those descendants, whatever classes they still carry (removing them individually is unnecessary).

Both pieces are required: the CSS alone doesn't stop Ember from later applying a non-opacity hidden state (`transform`/`filter`/`clip-path`, depending on animation type) a moment after first paint, which would cause a flash-then-hide-then-fade-in instead of no animation at all. Desktop is untouched by either change. See `wp-image-performance/SKILL.md` for the fuller LCP/priority-loading picture this is part of.

---

## 5. Where to test

- `templates/ember-animations-text.php` / `templates/ember-animations-image.php` — static page templates (not ACF blocks) showcasing every animation on text and on an image, with descriptions. Assign via a page's template dropdown (or `_wp_page_template` postmeta = `templates/ember-animations-text.php`). Built for showing designers/clients the available options.
- Both templates have matching scoped SCSS in `src/styles/templates/`.
