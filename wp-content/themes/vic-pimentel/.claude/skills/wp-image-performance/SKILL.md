---
name: wp-image-performance
description: Use when converting existing media library images to WebP, investigating why an image is/isn't eager-loaded or flagged as fetchpriority=high, an image is flagged as oversized/"improve image delivery" for its display width, debugging an LCP regression tied to images, or optimizing/compressing an mp4 used as background-media/video-player content. Covers the theme_is_first_priority_image() dynamic eager-loading mechanism, the is_priority_candidate arg, how max_size actually controls the sizes-attribute anchor (not the srcset ceiling) and should be checked/fixed before reaching for the sizes_attr override, why sizes_attr isn't named sizes (ACF image field collision), the WebP conversion + <picture><source> serving pipeline, the deferred-loading mechanism for background/inline video (data-src, not preload/autoplay), and the ffmpeg re-encode workflow for shrinking an oversized source mp4.
---

# Image performance — rear-theme framework

Two related, independent mechanisms live in `src/content/components/style-guide/image/image.php` (plus helpers in `core/core.php`): automatic first-image eager-loading, and automatic WebP serving. Both are transparent to every caller of `component('image', ...)` — no block needs to opt in — but both have real gotchas worth understanding before touching `image.php` again (it's already a design-sensitive component per `wp-framework-component-builder/SKILL.md`; this doc is the "why" behind the current contract).

---

## 1. Automatic first-image eager-loading (LCP priority)

### The problem it solves

Every block author has to guess whether their block will land first on a given page — impossible to know in advance. Before this mechanism existed, **no block in the codebase ever passed `lazyload => false`**, meaning every single hero/first-block image (including the header logo) defaulted to `loading="lazy"`, directly hurting LCP on every page.

### How it works

- `theme_is_first_priority_image()` (`core/core.php`) — a one-shot, per-request static flag. Returns `true` exactly once; every subsequent call returns `false`. No page/block needs to know in advance if it's "first."
- In `image.php`: `$is_priority_image = $lazyload && $is_priority_candidate && theme_is_first_priority_image();` — only claims the slot if this call would otherwise default to lazy AND is eligible (see below). The winning call gets `loading="eager"` + `fetchpriority="high"`; everything else stays as it was.
- This is page-order-based, not visual-size-based — PHP has no way to know final rendered dimensions/viewport at render time. It's a heuristic that works well for the common case (first block = one dominant hero/content image) and is deliberately kept simple.
- **Scoped to the page's first block only** — `theme_is_first_priority_image()` also checks `theme_first_block_image_window()`. That window opens for the request and is closed for good by `core_close_first_block_image_window()` (`core/blocks.php`, hooked to `acf/blocks/post_block_template_render`) the moment the first ACF block's render template finishes — whether or not that block ever called `component('image', ...)`. Fixed 2026-07-12: before this, a first block with no image of its own (e.g. `background-media` with `show_video: true` and no fallback `image`, like `hero-4`/`hero-5`) let the slot fall through to whichever image call happened to execute next in PHP order, which could be several blocks down the page — very unlikely to be the real LCP element, so eagerly loading it was a straight regression. Now, if the first block has no eligible image, the slot simply goes unclaimed for the whole page (no image gets `fetchpriority="high"`), rather than being misassigned to a later block.
  - Assumes today's block shape: no ACF block renders another ACF block before the page's real first block starts. `wrappers/`/`inner-blocks/` aren't in active use yet — if that pattern is introduced, this needs revisiting, since WP's `render_block()` fully renders child blocks (their own pre/post hooks included) before a parent's own render callback runs, which would close the window on the child block before the true top-level first block even starts.

### `is_priority_candidate` — the eligibility flag

Default `true`. Set to `false` when a `component('image', ...)` call happens to execute early in PHP order but renders into something **hidden by default** (closed accordion panel, closed mega-menu tab, unopened modal, closed dropdown) — being called first doesn't mean being seen first. Without this flag, a hidden panel's image would wrongly steal the priority slot from the page's real first visible image, and would itself get needlessly eager-loaded despite never being shown at load time.

**Both real production cases so far are in the header** (renders before any block content on every page):
- `header.php`'s two logo `component('image', ...)` calls — explicit `lazyload => false` (the logo is always above the fold, so it should be eager) **and** `is_priority_candidate => false` (it's not the page's dominant image, so it shouldn't claim `fetchpriority="high"` either).
- `mega-menu/parts/panel.php`'s tab-panel image — `is_priority_candidate => false` only (stays genuinely lazy, since the panel is closed by default; it just must not consume the shared slot on its way past).

**Rule of thumb:** if a `component('image', ...)` call sits inside markup that's `display: none`/closed/hidden by default until user interaction, it needs `is_priority_candidate => false`, full stop — regardless of whether it's also eager or lazy itself.

### Multi-image blocks — the known long-term limitation

If a future block has **two candidate images in the same block** — e.g. a full-bleed background plus a smaller inset/content photo — the automatic mechanism can't tell which one is visually dominant, only which `component('image', ...)` call executes first in the template's PHP. If the smaller image happens to be coded first, it would wrongly win the slot.

Today, no block in the library has this shape (`background-media` delegates straight to `component('image', ...)`, so it's already covered by the same mechanism, not a separate path). If one is built:
- Prefer ordering the template so the true dominant image's call comes first (usually already natural — background markup normally precedes nested content).
- Or, if there's real ambiguity, have the block explicitly decide and pass `lazyload => false` on the known-dominant image directly (bypassing the automatic mechanism entirely for that block, same pattern as the header logo) rather than relying on code order.

### Related: skipping entrance animation on the first block, mobile only

A first-block image wrapped in `.ember-entrance-group` stays `opacity: 0` (Chrome doesn't count a fully transparent element as "painted" for LCP) until Ember's full choreography finishes — footer-script load + observer delay + tween duration. On mobile, where this cost matters most, the first block's entrance animation is skipped entirely (not just the opacity pre-hide) via a CSS + JS pair — see `wp-ember-animations/SKILL.md`'s mobile-first-block section for the actual mechanism; this doc is just the reason it exists.

---

## 2. Automatic WebP serving

### How it works

`image.php`'s non-external-image branch builds `src`/`srcset`/`sizes` from the same core WP functions `wp_get_attachment_image()` itself uses (`wp_get_attachment_image_url()`, `wp_get_attachment_image_srcset()`, `wp_get_attachment_image_sizes()`), then checks each URL for a `.webp` sibling on disk via two helpers in `core/core.php`:

- `theme_webp_url_if_exists(string $url): ?string` — maps a jpg/png attachment URL to its `.webp` sibling path (via `wp_get_upload_dir()`), returns the webp URL only if that file actually exists, else `null`.
- `theme_webp_srcset_if_exists(string $srcset): ?string` — same idea, applied entry-by-entry across a full `srcset` string, dropping any entry with no webp sibling.

If either resolves, a `<source type="image/webp" srcset="..." sizes="...">` is emitted **before** the normal `<img>` — pure progressive enhancement. Browsers that support WebP use the source; everything else (including any attachment never converted) falls through to the original `<img>` unchanged. Zero risk, zero block-level changes needed for this to apply everywhere `component('image', ...)` is already used.

### Converting existing media to WebP

There's no automatic pipeline for new uploads (yet) — this is a one-off/periodic script, not a hook. Originals are never touched or deleted; `.webp` files are written as siblings alongside them.

```php
<?php
// Run via: lando wp eval-file <path>
// IMPORTANT: Lando's container does NOT mount /tmp on the host — put this file
// somewhere already mounted, e.g. directly in the theme root, and delete it after.
//
// Uses raw Imagick directly — NOT wp_get_image_editor()/set_quality(). Confirmed by a
// direct A/B test that WP_Image_Editor_Imagick::set_quality() is silently ignored when
// the source is jpg/png and the output format is webp: requesting quality 70 and
// quality 82 through that abstraction produced byte-identical ~112KB output either way,
// while raw Imagick (setImageCompressionQuality()) on the exact same source produced the
// expected, correctly different sizes (71.9KB at q70, 97.9KB at q82). This looks like a
// genuine WordPress core limitation around format-crossing quality settings, not
// something specific to this environment — don't switch back to wp_get_image_editor()
// for this without re-verifying the bug is gone in whatever WP version is current then.

$quality = 70; // re-verify against a real re-encode test before trusting this number on
                // a new project — see the A/B method above; don't just copy this value.

$attachments = get_posts([
    'post_type' => 'attachment',
    'post_mime_type' => ['image/jpeg', 'image/png'],
    'posts_per_page' => -1,
    'post_status' => 'inherit',
]);

function theme_make_webp(string $path, int $quality): bool
{
    if (!file_exists($path)) return false;
    $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);
    if ($webp_path === $path) return false; // no match

    try {
        $im = new Imagick($path);
        $im->setImageFormat('webp');
        $im->setImageCompressionQuality($quality);
        $im->writeImage($webp_path);
        $im->clear();
        $im->destroy();
    } catch (Exception $e) {
        return false;
    }

    return true;
}

foreach ($attachments as $attachment) {
    $file = get_attached_file($attachment->ID);
    if ($file) theme_make_webp($file, $quality);

    $metadata = wp_get_attachment_metadata($attachment->ID);
    if (!empty($metadata['sizes'])) {
        $dir = dirname($file);
        foreach ($metadata['sizes'] as $size) {
            if (!empty($size['file'])) theme_make_webp($dir . '/' . $size['file'], $quality);
        }
    }
}
```

Always re-encodes from the original jpg/png, never from an existing webp (avoids generational compression loss on repeat runs) — this version overwrites any existing `.webp`, so re-running it after a quality change is expected/safe, not merely idempotent-by-skipping like an earlier draft of this script was.

**Don't bother adding an explicit metadata-stripping pass.** Raw Imagick already excludes EXIF/IPTC/XMP by default when encoding to WebP — verified by inspecting a converted file's `Imagick::getImageProperties()` (only 2 internal filesystem timestamp properties remained, no real metadata at all). An explicit `stripImage()` re-encode produces byte-identical output.

**Compression savings vary wildly by source, and that's expected, not a bug.** A barely-compressed test asset can shrink 90%+; an already-reasonably-compressed stock photo might only save 10-15%. The same quality setting applies consistent compression in both cases — the "savings" number just reflects how much slack was in the original JPEG, not an inconsistency in the conversion.

---

## 3. Correcting oversized responsive images — `max_size` first, `sizes_attr` only when that's not enough

### The problem

WordPress's automatic `sizes` calculation always assumes an image renders at "100vw up to its registered max size." That's correct for a full-bleed hero, but wrong for any image sitting in a constrained container (a grid column, a fixed-width card, a multi-slide carousel showing more than one item at once) — the browser trusts `sizes` to pick a `srcset` candidate, so a wrong `sizes` value means a real, measurable oversized download on every page using that block.

### Critical mental model correction: `max_size` does NOT cap the srcset ceiling

It's tempting to assume passing a smaller `max_size` (e.g. `'medium'` instead of `'xlarge'`) restricts which `srcset` candidates get offered. **It doesn't.** `wp_get_attachment_image_srcset()` builds its candidate list from *every* registered size sharing the image's aspect ratio, roughly up to ~2x the requested size — confirmed directly: an image called with `max_size: 'medium'` (768px) still offered 1024w and 1400w candidates in its `srcset`. Bigger candidates stay available for high-DPI screens regardless of `max_size`.

What `max_size` *actually* controls:
1. The `<img>` tag's fallback `src`/`width`/`height` (the non-srcset base attributes).
2. **The anchor point WordPress's automatic `sizes` calculation uses** — the formula is literally `(max-width: {registered-width}px) 100vw, {registered-width}px`, where `{registered-width}` is `max_size`'s own registered pixel width.

That second point is the one that matters here: **if a block's real max display width is already close to some registered size, just pick that size as `max_size` — the built-in "100vw up to X" formula becomes correct on its own, because X is now close to the true cap.** No `sizes_attr` override needed. This was confirmed on `carousel-2` and `carousel-3` — both had `max_size` set far larger than their real constrained display width (`xlarge`/1440px and `large`/1024px, for images that never render past ~480-720px); switching to `'medium'` (768px in this install) alone fixed the Lighthouse finding, because 768 is close enough to both blocks' real caps that the auto-generated `sizes` string stopped over-estimating.

### Decision order — check this before reaching for `sizes_attr`

1. **Is the image's real max width (across all breakpoints) a single fixed pixel value** (a `fluid(min, max)` cap, an explicit `max-width`, anything that doesn't scale as a percentage of the viewport)? → **Just pick the closest registered size at or above that max as `max_size`.** Nothing else to do. This covers most constrained-but-not-proportional layouts (fixed-width cards, single-column-capped content). Registered sizes in this theme: `thumbnail` (150), `small` (414), `medium`/`medium_large` (768 in this install — verify per-project in Settings → Media, it's admin-configurable), `large` (1024), `xlarge` (1440), `xxlarge` (1920).
2. **Does the display width genuinely change as a *proportion* of the viewport** (a 3-column grid, a carousel showing a fixed *count* of slides at once via `slidesPerView`, anything that's `Nvw` rather than a fixed px cap) at one or more breakpoints? → `max_size` alone cannot express this (its formula only ever produces one fixed post-breakpoint pixel value, never a percentage) — **this is the one real case `sizes_attr` exists for.** Confirmed on `carousel-1`, whose swiper shows 1/2/3 slides depending on breakpoint (`33vw`/`50vw`/`100vw`) — no `max_size` choice fixes that, since the actual pixel width scales with the viewport itself, not a fixed number.

In short: review `max_size` first, every time. Only add `sizes_attr` when the layout is genuinely viewport-proportional and changes fraction per breakpoint — not just "the image is in a container," which is the more common, simpler case #1 above.

### How to tell which case you're in, and what value to use

- **Most reliable — no math**: open the block in a browser, resize to each breakpoint, inspect the image, read its **computed width** in the box model panel across a few different viewport widths. If that number stays roughly constant (or grows very slowly) across a wide range of viewports → case 1, fixed cap, fix `max_size`. If it scales roughly linearly with viewport width → case 2, proportional, needs `sizes_attr`.
- **Reading the SCSS directly**: a `fluid(min, max)` value or explicit `max-width` in px → case 1 (use the **max** value to pick the closest `max_size` at or above it — slightly generous is fine, better than blurry). A grid/flex column count, or a carousel's `slidesPerView` (check the block's `.js` for the swiper config) with no `max-width` cap on the wrapper → case 2, write `{100/N}vw` per breakpoint:
  ```php
  'sizes_attr' => '(min-width: 1024px) 33vw, (min-width: 768px) 50vw, 100vw',
  ```
  (widest/most-specific breakpoint first, plain fallback last — the browser reads left to right and stops at the first matching condition.)

**Neither is a one-time decision** — revisit whenever that block's image-container CSS changes (a column-count change, a new breakpoint, a redesign), same category of upkeep as updating the breakpoint value itself.

### `sizes_attr` mechanics, when you do need it

`image.php` accepts an optional `sizes_attr` arg (default `null`). When set, it's used verbatim for both the `<picture><source>` and the `<img>` tag's `sizes` attribute, overriding WordPress's automatic calculation. When left `null` (the default, and correct for full-bleed heroes and — per the decision order above — most constrained-but-fixed-width layouts too, once `max_size` is chosen well), behavior is unchanged.

### Critical gotcha: why it's `sizes_attr`, not `sizes`

**This arg is deliberately not named `sizes`.** An ACF image field's own array already has a *native* key called `sizes` — its own per-registered-size URL lookup table (`sizes.thumbnail`, `sizes.medium-width`, etc., populated by ACF itself, nothing to do with the HTML attribute). `component()`'s two-arg form merges the ACF image array (`$args1`) with the config array (`$args2`), so naming this override `sizes` would mean: on every call that *doesn't* explicitly pass an override (the overwhelming majority — every plain `component('image', $field['image'], [...])` call with no sizes correction), ACF's own array's `sizes` key leaks through as if it were the override, and gets printed literally as `sizes="Array"` in the HTML.

This actually happened — shipped as `sizes` once, and it silently broke `sizes="..."` (real WP-calculated value included) into `sizes="Array"` on **every image on every page using an ACF image field with no explicit override**, not just the one block being tested at the time. Caught by testing one block with the override commented out and noticing the literal text "Array" in the rendered HTML. If you ever see `sizes="Array"` (or any other WordPress/ACF-reserved-looking word) rendered literally in output, suspect exactly this kind of arg-name collision with the data array being merged in — check ACF's field return-array shape before naming a new component arg.

### Known long-term limitation: gaps in the registered-size ladder

Even with `max_size`/`sizes_attr` both correct, if the real display width doesn't have a close registered size available, the browser picks the smallest *sufficient* candidate, which can still be meaningfully bigger than ideal — e.g. an 800px display slot with only 768px and 1024px registered nearby will pick 1024px (768 is insufficient), a real ~64% more pixels than needed at 1x pixel density. This can still show up as a Lighthouse finding even with everything else fixed. Two things affect whether it's worth chasing further:
- **Device pixel ratio matters a lot.** Lighthouse's mobile preset typically emulates 2-3x DPR, meaning an 800px slot actually wants ~1600-2400px — in that case 1024px is adequate or even a little small, not oversized, and won't get flagged. Desktop presets (1x DPR) are where a size-ladder gap actually shows up as real waste.
- **A correction still helps even without a perfect ladder match** — it stops the browser from wrongly assuming 100vw (or an oversized `max_size`) and grabbing something *even bigger* than the closest available step. It just won't reach zero waste on its own.

If a specific display width recurs often enough across blocks to be worth it, registering a dedicated custom size (`add_image_size()` in `app/setup.php`) closes the gap — but that's a case-by-case call (every registered size adds processing time and disk space to every future upload), not a default response to any single gap.

---

## 4. Background/inline video — deferred loading and mp4 re-encoding

Two independent fixes, both found auditing why the hero library test page scored poorly (2026-07-11). Applies to `background-media` (hero-1/4/5, cta-banner-3/4/5) and `video-player` (reached via `image-video` with `show_video: true`) — both `style-guide` components.

### 4.1 Deferred loading — `preload`/`autoplay` are hints, not guarantees

Confirmed by direct network trace: a `<video preload="none">` with **no** `autoplay` attribute still triggered a full ranged fetch of its `<source src="...">` on page load, for an element scrolled several screens below the fold, while the element itself correctly stayed `paused`. Browsers are free to ignore `preload` as a hint — this is documented behavior, not a bug in one browser. The only reliable technique is keeping the URL **out of the DOM entirely** until you actually want it fetched:

```html
<!-- Not this — url is fetched regardless of preload/autoplay: -->
<video preload="none"><source src="video.mp4" type="video/mp4"></video>

<!-- This — nothing to fetch until JS assigns it: -->
<video preload="none"><source data-src="video.mp4" type="video/mp4"></video>
```

Same reasoning `<img data-src>` lazy-loading used before native `loading="lazy"` existed — video has no native equivalent attribute, so the manual technique is still the only option.

- **`background-media.js`**: an `IntersectionObserver` (`rootMargin: '200px 0px'`) on the component root swaps `source.dataset.src` → `source.src`, calls `.load()`, then `.play()` once the element is near-viewport. `.bg-video` stays `opacity: 0` until its native `playing` event fires (`is-playing` class), so the poster image underneath covers the gap — mirrors the pre-existing YouTube-iframe pattern in the same component.
- **`video-player.js`**: the file-based (`<video>`) branch does the identical `data-src` → `src` + `.load()` swap inside the existing `playButton` click handler, right before `.play()`. The iframe/YouTube branch already did this correctly (`video.dataset.src` → `video.src` on click) — the file branch just never got the same treatment; check both branches stay in sync if this component changes again.
- Verify with a real network trace, not just "looks fine" — a `page.on('request', ...)` listener (Playwright) or the browser devtools Network tab, confirming zero requests for the file until scroll/click. A Lighthouse "network-requests" audit alone can look identical whether the fix works or not, since Lighthouse doesn't scroll during a normal run — it's easy to convince yourself a fix works when it's actually a false negative from the audit simply never reaching the element either way.

### 4.2 Re-encoding an oversized source mp4

A background/inline video is usually shipped straight from whatever the client/designer exported — never optimized for actual delivery. Two concrete, independent problems to check for, found on the one video in this project's library (2026-07-11):

1. **A muted background video carrying a full audio track.** `background-media`'s `<video>` is always rendered `muted` (autoplay requirement) — any audio track is pure dead weight, never audible. Confirmed: 256kbps AAC track was ~14% of a 1128KB file's total size for zero perceptual difference.
2. **Bitrate/CRF far more generous than the content needs.** The same 1128KB, 960×540, 5-second clip re-encoded at H.264 CRF 28 (audio stripped, `-preset slow` for max compression efficiency since encode time doesn't matter for a one-time offline job) came out to 215KB — an 81% reduction, confirmed visually indistinguishable via a side-by-side extracted-frame comparison (`ffmpeg -ss <t> -frames:v 1 frame.png` on original vs re-encoded).

```bash
ffmpeg -i original.mp4 -c:v libx264 -crf 28 -preset slow -an -movflags +faststart optimized.mp4
```

- `-an` strips audio entirely — only correct because this component always renders the video `muted`; don't apply this blindly to a video that's meant to have sound.
- `-crf 28` is a reasonable, tested-safe default; `-crf 30` saved further (172KB in testing) at still-indistinguishable quality for this content, but CRF is content-dependent (a busier/higher-motion clip may show artifacts sooner) — always do the frame-comparison check per video, don't assume the exact same CRF is safe for different footage without checking.
- `-movflags +faststart` moves the moov atom to the front of the file so playback can start before the full file downloads — always include it for web delivery.
- Replace the original file in place (same filename/path) after backing it up — WordPress's attachment metadata/URL doesn't need any changes since the file is still an mp4 at the same location; no component code changes needed for this half of the fix.

**This cannot run automatically on upload the way WebP conversion does.** WebP conversion runs via `Imagick`, already available inside the PHP/Lando container. `ffmpeg` is a separate binary, and neither Pantheon nor this project's Lando container has it installed (no shell/package-manager access in either — confirmed via `lando ssh` showing no `sudo`). This has to run on a machine that actually has `ffmpeg` — install it locally (`apt`/`brew`/etc., or a static build like `https://johnvansickle.com/ffmpeg/` if the target machine has no package-manager access either) and run the command above per video, manually, whenever a new background/inline video is added or an existing one is revisited for size.

**WebM/VP9 as a second, smaller `<source>` (mirroring WebP) was evaluated and deliberately not pursued as the default recommendation.** In testing, a well-tuned VP9 encode (CRF 38, default speed settings) didn't beat the CRF 28 H.264 result above (196KB vs 215KB — close, and VP9 needs more encoder tuning effort, e.g. 2-pass + `-cpu-used`, to reliably pull ahead, unlike WebP's clear win margin over JPEG). Given the added complexity of maintaining two encodes per video for a marginal-at-best additional gain, and that a well-optimized mp4 alone gets ~80%+ of the total possible saving, mp4-only re-encoding is the current default. Revisit if this project's video usage grows enough to justify the extra tuning effort.
