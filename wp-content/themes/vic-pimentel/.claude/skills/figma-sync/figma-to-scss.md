---
name: figma-sync-figma-to-scss
description: Use when asked to pull, sync, or reconcile Figma Variable changes back into this theme's _variables.scss — e.g. "a designer edited variables in Figma, update the SCSS to match." Covers reading variable state from the Figma MCP, reconstructing fluid() calls, producing a reviewable diff instead of overwriting the file, and (opt-in, asked every run) syncing font-family changes into fonts.scss's Google Fonts @import.
---

# Figma → SCSS — pull design token edits back into SCSS

Companion to `scss-to-figma.md`, which documents the structure this skill reads from (the Colors / Dimensions / Typography / Fonts / Typography Static collection split, the style-first naming convention, the alias-based mode lock, the alpha-derived-color limitation). Read that file first if you didn't build the Figma file yourself in this session — this skill assumes that structure exists.

**Never write to `_variables.scss` directly.** Always produce a diff/patch for review and get explicit confirmation before editing the file. A bad write here is much harder to spot than a bad write in Figma — SCSS changes silently affect every block that uses the theme's typography/spacing mixins.

---

## 1. Reading current Figma variable state

### Quick check: `get_variable_defs`

`get_variable_defs(fileKey, nodeId)` returns a flat map of `{ variableName: resolvedValue }` for whatever variables are **actually bound to a visible property** somewhere inside that node's subtree. It's a real, useful sanity check, but it has two confirmed limitations — verified by calling it against this project's own Style Guide file, not assumed:

1. **It collapses multi-mode variables to one value.** Calling it against the Typography section (which shows Mobile and Desktop specimens side by side) returned `"font-size/display-1": "38"` — the Mobile value only. The Desktop value (`90`) never appeared anywhere in the response, for that variable or any other 2-mode variable. **`get_variable_defs` cannot be used alone to reconstruct a `{ mobile, desktop }` pair.**
2. **It omits variables that exist but aren't bound to a rendered property.** Inside the Typography collection, every style's `{style}/line-height`, `{style}/font-weight`, `{style}/letter-spacing`, and `{style}/font-family` are deliberately unbound (see `scss-to-figma.md` §7, gotcha 4 — and §5 for what this means for canvas freshness) — applied as literal values on the Text Styles instead. Only `{style}/font-size` is actually bound. Querying a node that visually uses every Text Style returned **zero** of the unbound variable names; instead it showed a synthesized `Font(...)` description string with the line-height/weight baked in as plain numbers. **`get_variable_defs` will silently miss any variable in this category** — which today is 4 of every style's 5 properties.

Use it for a fast "does this look right" spot-check on a specific frame — not as the source of truth for a full sync.

### Authoritative method: a `use_figma` read-only script

For a complete, mode-accurate, alias-aware dump, read every collection directly via the Plugin API:

```js
const collections = await figma.variables.getLocalVariableCollectionsAsync();
const result = {};
for (const c of collections) {
  const vars = await Promise.all(c.variableIds.map(id => figma.variables.getVariableByIdAsync(id)));
  result[c.name] = {
    modes: c.modes.map(m => ({ name: m.name, id: m.modeId })),
    variables: vars.map(v => ({
      name: v.name,
      type: v.resolvedType,
      valuesByMode: v.valuesByMode,   // keyed by THIS collection's own mode ids
      description: v.description,     // human notes only — don't parse this for data
      codeSyntax: v.codeSyntax,        // { WEB?, ANDROID?, iOS? } — the code-mapping field, e.g. font-family's CSS stack
    })),
  };
}
return result;
```

**Mode IDs are per-collection, not shared.** Dimensions' `Mobile` mode and Typography's `Mobile` mode are different mode objects with different ids, even though both are named `"Mobile"`. Always resolve by name within the collection you're reading (`collection.modes.find(m => m.name === 'Mobile').modeId`), never assume a mode id from one collection applies to another.

A `COLOR` value can be either a literal `{r,g,b,a}` (0–1 range) or `{ type: 'VARIABLE_ALIAS', id }`. Resolve aliases by looking up the referenced variable's own name — that's what tells you whether a SCSS line should stay a variable reference (`$background-1: $primary;`) or become a literal hex.

---

## 2. Mapping collections back to SCSS sections

| Figma collection | SCSS target | Rule |
|---|---|---|
| **Colors** (1 mode) | Colors section of `_variables.scss` | Primitives/status → direct hex. Semantic aliases (`background-*`, `text-*`, `decoration-*`, `action-*`) → resolve the `VARIABLE_ALIAS` to the SCSS name of the referenced primitive, write `$background-1: $primary;` style, **not** a hardcoded duplicate hex. **This means the literal, specific variable the chain terminates at — never a different variable that merely holds an equal value today**, even one that "matches the idiom" of a sibling rule elsewhere in the same file. Confirmed as a real mistake at the component layer (`figma-to-component-scss.md` §6 gotcha 8): a Form-component sync resolved an alias to the primitive `light-2` but wrote `$action-6` instead, because `$action-6 == $light-2` numerically and matched a neighboring block's existing style — wrong, because `action-6` and `light-2` are independent Figma variables that only coincidentally share a value; substituting one for the other silently breaks the moment either is edited on its own. Trace every chain independently, every time. `line-1`/`line-2`/`image-overlay` are a special case — see §3. |
| **Dimensions** (Mobile/Desktop) | `$gap-*`, `$wrapper-*`, `$vertical-*`, `$header-height*`, `$horizontal-padding`, `$radius-*`, `$border-default` | Organized into 5 sub-folders (`Spacing/`, `Wrapper/`, `Header/`, `Borders/`, `Vertical Spacing/`) that are purely Figma-side organization, not part of the SCSS name — **the SCSS variable name is always everything after the last `/`** (`Wrapper/wrapper-default` → `$wrapper-default`, `Borders/radius-medium` → `$radius-medium`). Reconstruct as `fluid(mobile, desktop)` if the pair differs; if equal, `rem(value)` — or bare `0` if the value is zero — **never a bare non-zero number**, see §4. (Collection was renamed from "Spacing" — it also holds wrapper widths and border/radius, not just gap-style spacing.) |
| **Typography** (Mobile/Desktop) | Every key in each type-style map (`$display-*`, `$heading-*`, `$p-*`, `$action-*`, `$link-*`) | Variables are grouped by style folder (`{style}/font-size`, `{style}/line-height`, `{style}/font-weight`, `{style}/letter-spacing`, `{style}/font-family`) — read the whole folder per style, write the whole map. `font-size` uses the same fluid/`rem()`/zero rule as Dimensions (§4) — a static font-size (e.g. `heading-6`) is `rem(value)`, not a bare number. **`line-height`, `font-weight`, `letter-spacing`, `font-family` are all aliases, not literals** — every one of these four entries holds `{ type: 'VARIABLE_ALIAS', id }` in both modes, never resolve them as if they were the value itself. All four resolve into the **Typography Static** collection — `font-family` requires one extra hop from there into **Fonts** to reach the literal stack string (Typography → Typography Static → Fonts). Convert `line-height` back from Figma's percent (e.g. `120`) to the SCSS unitless multiplier (`1.2`) by dividing by 100. Convert `letter-spacing` back from Figma's percent (e.g. `2`) to the SCSS `em` value (`0.02em`) by dividing by 100 and appending `em` — Figma's `PERCENT` unit for letter-spacing is the same concept as CSS `em` (1% = 0.01em), same conversion shape as line-height, different suffix. For `font-family`, write `$font-1`/`$font-2`/`$font-3` (the SCSS variable name, not the literal stack) into the `font-family:` line, same alias-not-literal rule as Colors. |
| **Fonts** (1 mode) | `$font-1`, `$font-2`, `$font-3` | **Don't write the variable's raw value into SCSS as-is.** The value is a bare Figma font family name (`Inter`) — Figma has no fallback-chain concept, so the CSS stack (`"Inter", Arial, sans-serif`) lives in `variable.codeSyntax.WEB`, a structured field Figma provides specifically for "how this maps to code" (`setVariableCodeSyntax`/`codeSyntax`), not in `description` (that's free-text human notes, not a data source — don't parse it). Read `codeSyntax.WEB` for the SCSS output; if it's missing (someone added a new font-family variable without setting it), flag it rather than guessing a fallback chain. Only ever reached as the second hop of a `font-family` chain — nothing in Typography aliases directly into Fonts. |
| **Typography Static** (1 mode) | Not written to directly — it's the resolution target for *all four* of Typography's non-fluid aliases (see Typography row above), including `font-family`, which itself aliases onward into Fonts. If a value here doesn't match what its corresponding Typography-collection alias resolves to, something is structurally broken (an alias got overwritten with a literal) — flag it, don't silently pick one. |

---

## 3. `line-1`, `line-2`, `image-overlay` — Figma-authoritative hex, but keep `color.adjust()`

These three are opacity variants of a base color. The project's standing convention for expressing opacity in SCSS is `color.adjust($color, $alpha: X)` — that mechanism stays. What changed is *what feeds it*: these three used to call `color.adjust($dark-1, ...)` / `color.adjust($light-1, ...)`, a live reference to another token. That reference was dropped because Figma can't truly alias-with-alpha-override (see `scss-to-figma.md` §3 for why), which meant every sync required guessing whether a Figma-side edit was "the source primitive changed" or "someone edited the derived value directly" — a conflict that came up for real on the first sync (Figma's `line-1`/`image-overlay` had been recolored to a different hex than `$dark-1`, with `line-1`'s alpha also changed to 40%).

**Current policy: the *hex* is a Figma-authoritative literal (no longer a reference to `$primary`/`$dark-1`/`$light-1`), but the opacity is still expressed via `color.adjust()`, not a hardcoded `rgba()`.**

```scss
// Base hex synced directly from Figma Variables (Colors collection) — a
// literal, not a reference to $primary/$dark-1/$light-1, so this stays
// independent of palette edits. Opacity still goes through color.adjust(),
// same mechanism as the rest of this system. $alpha is derived from
// Figma's resolved alpha (target - 1.0): update both the hex and the
// $alpha offset together when the Figma source changes.
$line-1: color.adjust(#434343, $alpha: -0.6);
$line-2: color.adjust(#FFFFFF, $alpha: -0.7);
// ...
$image-overlay: color.adjust(#434343, $alpha: -0.7);
```

**Reconstruction steps each sync:**
1. Read the variable's resolved `{r,g,b,a}` from Figma.
2. Convert `{r,g,b}` to a hex literal (e.g. `0.262745, 0.262745, 0.262745` → `#434343`) — write the hex directly, do not try to match it back to an existing named primitive even if it happens to coincide with one.
3. Compute `$alpha` for `color.adjust()` as `a - 1.0` (Figma's alpha minus fully-opaque). E.g. resolved alpha `0.4` → `$alpha: -0.6`; resolved alpha `0.3` → `$alpha: -0.7`.
4. Write `color.adjust(#HEX, $alpha: <computed>)`. Never write a bare `rgba()` literal for these three — that drops the project's standard opacity idiom.

No conflict-flagging, no attempt to reconcile the hex against `$dark-1`/`$light-1` — these three are free to diverge from the base palette; that's expected, not an error. Keep `@use "sass:color";` at the top of `_variables.scss` for `color.adjust()` to resolve.

---

## 4. Reconstructing `fluid()` calls — and the static case, which is not a bare number

Inverse of the extraction rule in `scss-to-figma.md` §2:

```js
// Mobile: 38, Desktop: 90 (differ) →
fluid(38, 90)

// Mobile: 16, Desktop: 16 (equal, non-zero) →
rem(16)   // NOT the bare number 16 — see below, this was a real bug

// Mobile: 0, Desktop: 0 (equal, zero) →
0         // zero is the one exception — no unit needed, never rem(0)
```

**This is a hard rule, not a "check the family and match whatever's there" judgment call — a version of this section that hedged that way produced a real bug** (`gap-small` and `vertical-default` were written as bare `16` / `80` instead of `rem(16)` / `rem(80)`, caught by the project owner testing the pipeline specifically for this). The rule:

- **Mobile ≠ Desktop → `fluid(mobile, desktop)`.** Only case `fluid()` is used.
- **Mobile == Desktop and the value is `0` → bare `0`.** No unit ever needed for zero.
- **Mobile == Desktop and the value is non-zero → `rem(value)`.** Every single time. This matches how every other static dimension in this file is already written (`$gap-nano: rem(8)`, `$wrapper-default: rem(1360)`, `$heading-6` font-size: `rem(16)`) — there is no static non-zero dimension anywhere in `_variables.scss` that's a bare unwrapped number, so don't create the first one.

This applies to **any** static px-based dimension reconstructed from a Mobile/Desktop pair — spacing, wrapper widths, radius, header heights, font-size — not just the Dimensions collection. It does not apply to unitless/non-px values reconstructed elsewhere (`line-height`'s multiplier, `font-weight`'s number, `letter-spacing`'s `em`) — those have their own conversion rules (§2) and were never bare-pixel numbers to begin with.

---

## 5. Output as a diff — never overwrite directly

1. Read live Figma state (§1, authoritative method).
2. Read the current `_variables.scss`.
3. Diff value-by-value against every token documented in `scss-to-figma.md`'s naming convention (§4 there) — not just the ones you remember changing.
4. Present a table: token, old value, new value, for every real change. Skip unchanged tokens entirely — don't list 80 "no change" rows.
5. Show the literal SCSS edit for each changed line (old line → new line), not just the table — that's what actually gets reviewed.
6. **If any `$font-1`/`$font-2`/`$font-3` value changed in this diff, ask the fonts.scss question now (§8) — before applying anything.** This is a mandatory question every single run a font-family changed, not a one-time or remembered preference — a "no" this time doesn't carry forward to the next sync.
7. **Stop. Wait for explicit confirmation** before editing the file(s).
8. Apply only the confirmed changes. If the user wants to exclude one of several changes, do a partial apply — don't treat it as all-or-nothing.

---

## 6. Warn on orphan Figma variables

If a variable exists in Figma with no matching SCSS counterpart — a token a designer added directly in Figma, with no `$` declaration anywhere in `_variables.scss` or its imports — **do not silently invent a new SCSS variable for it.** Call it out as its own section in the diff output:

> **New in Figma, no SCSS counterpart**: `color/accent-2` (`#FF6B35`) — exists in the Colors collection but there's no `$accent-2` in `_variables.scss`. Add it to SCSS, or was this meant to alias an existing token?

Let the user decide whether to add it, map it onto an existing variable, or leave it Figma-only. The same applies to a variable whose *name* changed in Figma — don't assume a renamed variable; ask whether it's a rename of an existing token or a genuinely new one before reconstructing the diff.

---

## 7. Conflict-resolution policy: Figma wins, unless it would break the code

**Standing rule (confirmed by the project owner): when a Figma variable's `value` disagrees with another signal — its own `codeSyntax`, an alias that got overwritten with a literal, a structural pattern established earlier — trust `value` and apply it. Don't stop to ask every time; that was needed the first two times to establish the rule, not as a permanent gate.**

This replaces the earlier "always flag, never guess" default *specifically for value-vs-metadata conflicts* (§6's orphan-variable case still applies as-is — a variable with literally no SCSS counterpart is a different situation, still worth surfacing). Concretely:

- `codeSyntax` disagreeing with `value` (e.g. `font-family/font-1` holding `Space Grotesk` while its `codeSyntax.WEB` still says `"Inter", Arial, sans-serif"`) → **trust `value`**, and update `codeSyntax` to match as part of the sync (keeps Figma internally consistent for the next round, same as fixing a rounding error, not a design decision).
- An alias that got overwritten with an independent literal (e.g. `vertical-small` no longer aliasing `gap-small`, now a standalone `{20, 40}` pair) → **trust the literal**, and restructure the SCSS accordingly (e.g. `$vertical-small: $gap-small;` becomes `$vertical-small: fluid(20, 40);` — a real structural change, still worth calling out clearly in the diff output so the reader knows it's not just a number tweak, but not worth blocking on).

**"Would break the code" is the actual bar for stopping to ask** — not "is this surprising" or "does this diverge from an earlier pattern." Reasons to still stop and ask instead of applying automatically:
- A value that can't be resolved at all (broken alias chain, `codeSyntax.WEB` missing entirely with no fallback, a font name that fails to resolve to a string).
- A genuinely new/orphan variable with no SCSS counterpart (§6) — there's no existing token to "trust the value of."
- A value that would produce invalid SCSS/CSS output (e.g. a non-numeric string where a number is expected).

A visually large or unexpected change (swapping every heading's font family, a color that looks wrong) is **not** by itself a reason to stop — that's exactly the kind of edit a designer testing the pipeline is expected to make. Report it clearly in the summary after applying, so it's visible, but don't gate on it.

---

## 8. `fonts.scss` — syncing self-hosted font files (opt-in, ask every run)

`fonts.scss` (`src/styles/static/fonts.scss`) is a separate file from `_variables.scss` — as of the image/font performance pass, it's a set of self-hosted `@font-face` declarations (not a Google Fonts `@import`) pointing at `.woff2` files in `src/assets/fonts/`. Fonts are **not** loaded from Google's CDN at runtime — this was changed specifically to remove an external render-blocking request. See `.claude/skills/wp-image-performance/SKILL.md` for the fuller performance context; this section is just the Figma-sync workflow for keeping the self-hosted files in sync.

Only the `latin` and `latin-ext` unicode-range subsets are kept (this theme is PT-BR-only — no cyrillic/greek/vietnamese glyphs are ever needed). If the project ever needs a different language's characters, that subset filter (§ below, the `wantedSubsets` set) needs revisiting too — don't assume latin/latin-ext is universally correct for every future project cloned from this theme.

`$font-1`/`$font-2`/`$font-3` in `_variables.scss` only hold the CSS fallback *stack* (`"Inter", Arial, sans-serif`) — they say nothing about whether the font is actually loaded anywhere. `fonts.scss` is what actually loads it. A font-family sync (§2's Fonts row) that only touches `_variables.scss` can silently leave the *old* font's files physically loaded and the *new* one missing.

**Always ask before touching this file — every run, not just the first time.** As soon as §5 step 6 finds a changed `$font-N`, ask explicitly:

> "`$font-N` changed to `{new font}` — want me to update the self-hosted font files in `fonts.scss` too, or are you handling that yourself?"

Wait for an explicit answer before reading or writing `fonts.scss` or `src/assets/fonts/`. A "no" applies to this run only — ask again next time a font-family changes, don't remember it as a standing preference. If the answer is no, or no `$font-N` changed this sync, skip this section entirely.

If yes, per changed font:

1. **Only act on `$font-N` values that actually changed** this pass — a resync where the font-family stayed the same needs no edit here.
2. **Confirm it's actually a Google Font before writing anything.** Take the bare font name as read from Figma (e.g. `Roboto`, not the fallback stack `"Roboto", Arial, sans-serif`), URL-encode spaces as `+`, and fetch `https://fonts.googleapis.com/css2?family={name}:wght@100..900&display=swap` **with a modern browser User-Agent** (Google serves different formats/URLs by UA — without one you may get woff/ttf or a stale format instead of woff2). A successful CSS response confirms it's on Google Fonts. A 400 means it isn't (custom/Adobe/self-hosted font) — don't touch `fonts.scss` for that font; flag it in the sync summary so the user knows to handle loading it themselves.
3. **Bring every weight — standing instruction, don't scan usage or guess a subset.** Request the full variable weight axis first: `family=Name:wght@100..900`. If Google 400s on that range (font isn't a variable font / doesn't span the full axis), fall back to the explicit list of all nine static weights — `family=Name:wght@100;200;300;400;500;600;700;800;900` — and drop whichever weight Google's error response calls out as invalid until the request succeeds.
4. **Parse the response, keep only `latin`/`latin-ext` blocks**, and extract per block: `font-family`, `font-style`, `font-weight` (read the **whole** value after the colon, not just the first number — a variable font's weight is a two-number range like `100 900`; truncating to the first number silently breaks every weight but one), the `gstatic.com` `src` URL, and `unicode-range`.
5. **Deduplicate before downloading.** Google sometimes serves the *same* underlying file for multiple weight declarations (confirmed: Plus Jakarta Sans's static-looking `wght@200;300;400;500;600;700;800` request actually returns **one** identical file, reused across all seven weight blocks — it's a variable font under the hood even though the request used semicolons, not a range). Group by `(family, subset, src URL)` before downloading; if a group has more than one weight number attached, merge them into one `@font-face` block with a `min max` weight range rather than writing duplicate blocks pointing at the same file.
6. **Download each unique file** into `src/assets/fonts/`, named `{family-slug}-{weight-or-range}-{subset}.woff2` (e.g. `plus-jakarta-sans-200-800-latin.woff2`) — replace that font's old files, don't leave them alongside the new ones.
7. **Regenerate `fonts.scss`'s `@font-face` blocks for that font** — `font-family`, `font-style`, `font-weight` (single number or `min max` range per step 5), `font-display: swap`, `src: url('../../src/assets/fonts/{filename}') format('woff2')` (path is relative to the *compiled* `public/static/fonts.css`, not the source file — verify against the compiled output, don't assume), and the original `unicode-range`. Replace that font's blocks; don't append alongside the old ones.
8. **Regenerate that font's metric-matched fallback block too — this is not optional, and it's per-font, not a one-time site constant.** As of 2026-07-11 every `$font-N` role has a paired `"{Font} Fallback"` `@font-face` (size-adjust/ascent-override/descent-override/line-gap-override) so the swap from fallback to real font causes zero layout shift — see fonts.scss's own header comment for the full rationale. These override numbers are computed from the *specific* real font's actual metrics vs a fallback's actual metrics; they are meaningless for a different font. Whenever a font changes, recompute:
   - `cd` to a scratch dir, `npm install fontkit fontaine`.
   - `import { readMetrics, getMetricsForFamily, generateFontFace } from 'fontaine'` — `readMetrics(pathToFileURL(path-to-new-woff2))` for the new real font's metrics (any one weight file is fine — ascent/descent/lineGap don't meaningfully vary across a variable font's weight axis); `getMetricsForFamily('Arial')` for sans-serif roles or `getMetricsForFamily('Courier New')` for monospace roles (fontaine ships known metrics for common system fonts, no file needed).
   - `generateFontFace(realMetrics, {name: '{Font} Fallback', font: 'Arial', metrics: fallbackMetrics})` returns the ready-to-use `@font-face` block with computed percentages.
   - **Manually extend the generated `src: local('Arial')` into a multi-candidate chain** — a single named font only exists on some OSes (confirmed: no Arial, no Helvetica Neue, no Noto Sans on stock Linux/Android in local testing; only Liberation Sans and DejaVu are there by default) — a single-candidate fallback silently never matches on other platforms, and the override just never applies (not a graceful degradation, a silent no-op). Use fontaine's own `DEFAULT_CATEGORY_FALLBACKS` list for the category (sans-serif: `BlinkMacSystemFont, Segoe UI, Helvetica Neue, Arial, Noto Sans`; monospace: `Courier New, Roboto Mono, Noto Sans Mono`), plus `Liberation Sans` for the sans-serif case (the Arial-metric-compatible font most Linux distros actually ship). One representative font's metrics (Arial/Courier New) drive the override numbers for the whole chain — this whole family of system UI faces is metric-similar enough that one set of values is the standard approach (same one fontaine/Next.js's font optimizer use), not a per-OS-exact match.
   - Update `$font-N` in `_variables.scss` to insert `"{Font} Fallback"` between the real font name and its existing generic fallback (e.g. `"Inter", "Inter Fallback", Arial, sans-serif`).
   - Full-page CLS measurement in a local sandbox is too noisy to validate this against (server-response-time variance dominates run to run) — verify instead with an isolated test page rendering the real font, the fallback face, and plain Arial side by side at a fixed width, confirming the fallback's rendered height sits much closer to the real font's than plain Arial's does. Trust field data (Pantheon/CrUX) for the real-world confirmation.
9. Show the before/after diff for `fonts.scss`, its fallback blocks, `_variables.scss`'s `$font-N` line, and the `src/assets/fonts/` file list as part of the diff output (§5) — same "no direct overwrite, confirm first" rule applies here as it does to the rest of `_variables.scss`.
