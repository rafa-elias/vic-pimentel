---
name: figma-sync-scss-to-figma
description: Use when asked to push, sync, or mirror this theme's SCSS design tokens (colors, spacing, typography) into Figma as Variables. Covers parsing _variables.scss, extracting fluid() mobile/desktop pairs, the Variable Collection structure that works in Figma, and the incremental build workflow.
---

# SCSS → Figma — push design tokens to Figma Variables

This skill documents how to turn `src/styles/abstract/_variables.scss` into Figma Variable Collections. It is based on an actual build (theme "Base Theme v2", file `Base Theme v2 — Design System Starter`), not a generic Figma-variables guide — every collection name, mode name, and gotcha below was hit and fixed in that session.

**Always run Phase 1 to completion and get explicit confirmation before touching any Figma MCP tool.** Phase 2 is destructive/expensive to redo — extraction mistakes compound across 80+ variables.

---

## 1. Parsing `_variables.scss` and its imports

Token source: `src/styles/abstract/_variables.scss`. It pulls in:

```scss
@use 'functions' as *;   // src/styles/abstract/_functions.scss — fluid(), rem(), no-unit()
@use "sass:color";       // color.adjust() — the project's standard way to express opacity
```

`$line-1`, `$line-2`, and `$image-overlay` use `color.adjust(#hex, $alpha: X)` against a **literal hex**, not a reference to `$primary`/`$dark-1`/`$light-1` — see §3 for why the reference was dropped but the `color.adjust()` mechanism itself stayed.

`_functions.scss` itself does `@use 'breakpoints' as *;` → `src/styles/abstract/_breakpoints.scss`, which defines the named breakpoint map (`phone-small`, `phone-medium`, `phone`, `tablet`, `tablet-wide`, `desktop-small`, `desktop-large`, `desktop-xlarge`) that `fluid()` resolves its breakpoint arguments against.

**Read in this order:**
1. `src/styles/abstract/_breakpoints.scss` — confirm the breakpoint→px map hasn't changed
2. `src/styles/abstract/_functions.scss` — confirm the `fluid()` signature hasn't changed (see §2)
3. `src/styles/abstract/_variables.scss` — the actual token declarations

**Also check** `src/styles/common/_section-spacing.scss` — it builds a `$spacings` map from `$vertical-none/small/medium/large/default`, but defines no *new* tokens, just aliases. Don't double-extract it.

**Token categories found in this file** (grep for `^\$[a-zA-Z_-]*\s*:` to enumerate):
- **Colors** — base palette (`$primary`, `$secondary`, `$tertiary`, `$accent`, `$light-1/2`, `$dark-1/2`), status (`$status-1/2/3`), independent alpha tokens (`$line-1/2`, `$image-overlay` — `color.adjust(#hex, $alpha: X)` against a Figma-authoritative literal hex, not a variable reference, see §3), and semantic aliases that just reference the base palette (`$background-1..7`, `$text-1..3`, `$decoration-1..8`, `$action-1..8`)
- **Fonts** — font stacks (`$font-1/2/3`) and type-style maps (`$display-1..3`, `$heading-1..6`, `$p-small/medium/large`, `$action-small/medium/large`, `$link-small/medium/large`), each a Sass map with `font-family`, `font-size`, `font-weight`, `line-height`, `letter-spacing`, `text-decoration`, `text-transform`
- **Dimensions** — `$gap-*`, `$wrapper-*`, `$horizontal-padding`, `$vertical-*`, `$header-height*`, `$radius-*`, `$border-default`
- **Transitions** — `$quick/fast/slow/smooth/static`, `$ease` — durations and an easing function. **Excluded by default**: Figma has no native duration/easing variable type, and these aren't visual style-guide material. Only include if explicitly asked.

---

## 2. The `fluid()` mixin — argument order and extraction rule

Defined in `src/styles/abstract/_functions.scss:50`:

```scss
@function fluid($min, $max, $min-breakpoint: 'phone-small', $max-breakpoint: 'desktop-small')
```

**Argument order is mobile first, desktop second**: `fluid(40, 100)` means *40px at the mobile breakpoint, 100px at the desktop breakpoint*. Do not assume this — re-read the function signature every time you start this skill, in case it's been changed since this was written.

Default breakpoint range is `phone-small` (360px) → `desktop-small` (1440px). **Check every call site for 3rd/4th arguments** overriding this — a call like `fluid(350, 600, 'tablet')` interpolates over a different range and should be flagged as non-standard in the token table, not silently treated the same as the default-range calls.

**Extraction rule — never collapse a fluid pair to one number:**

```js
// fluid(40, 100) → 
{ mobile: 40, desktop: 100 }

// rem(16) or a bare value with no fluid() wrapper →
16   // single static value, no mobile/desktop split needed
```

This matters even when both values happen to be equal after a later edit (e.g. a type style whose mobile and desktop sizes converge) — keep the pair structure; don't special-case it away during extraction. Decide what to do with equal pairs at the *Figma collection design* stage (§3), not the extraction stage.

**The `rem()`/bare-`0` wrapper is itself meaningful, not just a stripped-away formatting detail.** Every static non-zero dimension in this file is written `rem(value)` — never a bare number — and `0` is always bare, never `rem(0)`. Figma only stores the numeric value (it has no concept of a `rem()` wrapper), so this distinction disappears once pushed to Figma — which is exactly why the reverse direction (`figma-to-scss.md` §4) has to reconstruct it from a fixed rule rather than reading it back from anywhere. Get the rule wrong in either direction and a real bug results: a bare `16` where `rem(16)` was expected compiles fine but silently ignores the user's browser font-size preference, unlike every other static dimension in the file. This was caught for real, not hypothetical — see `figma-to-scss.md` §4 for the incident.

---

## 3. Figma Variable Collection structure that worked

Five collections.

| Collection | Modes | Holds |
|---|---|---|
| **Colors** | `Default` (single mode) | All color tokens — base palette, status, independent alpha tokens, semantic aliases. Colors aren't fluid, so one mode is correct *unless* the SCSS source has dark-mode variants (it doesn't, today). |
| **Dimensions** | `Mobile`, `Desktop` | Genuinely fluid dimension tokens *plus* a few static ones riding along for consistency, organized into 5 sub-folders: `Spacing/gap-*`, `Wrapper/horizontal-padding` + `Wrapper/wrapper-*`, `Header/header-height*`, `Borders/border-default` + `Borders/radius-*`, `Vertical Spacing/vertical-*`. Named "Dimensions," not "Spacing" — it covers wrapper/container widths and border/radius too, not just gap-style spacing. |
| **Typography** | `Mobile`, `Desktop` | **Every property of every type style**, grouped by style name (`display-1/font-size`, `display-1/line-height`, …) — see §4 for naming. Only `font-size` is a real value here; everything else is a read-only-by-convention alias — see below. |
| **Fonts** | `Default` (single mode) | The 3 reusable font stacks: `font-family/font-1`, `font-family/font-2`, `font-family/font-3`. Referenced by alias from Typography Static (not directly from Typography — see below). **The variable's value is a bare Figma font family name (e.g. `Inter`), not the CSS stack** — see the gotcha below for why. |
| **Typography Static** | `Default` (single mode) | The canonical, single-source values backing **all four** of Typography's non-fluid properties — one set per style: `display-1/line-height`, `display-1/font-weight`, `display-1/letter-spacing`, `display-1/font-family`. `font-family` here is itself an alias into Fonts (a 2-hop chain when read from Typography). Not meant to be edited by a designer browsing Typography day-to-day; it's the thing Typography's aliases point at. |

**Inside the Typography collection, only `{style}/font-size` is a genuinely independent value per mode.** Everything else (`line-height`, `font-weight`, `letter-spacing`, `font-family`) is a **variable alias into Typography Static**, with both the Mobile and the Desktop mode slot pointing at the *same* target. This is the answer to "can a designer accidentally set different Mobile/Desktop values for something that should never be fluid": **Figma's Plugin API has no lock/read-only property on variables**, so there's no hard prevention — but aliasing both mode slots to one canonical source means by default they're mirrored and stay mirrored when the canonical value changes. A designer would have to deliberately overwrite one mode's cell with a literal to break the link, and Figma visually distinguishes an aliased cell (shows the linked-variable chip) from a literal one — so a broken link is visible, not silent. This is the strongest mechanism available; it is not a true lock.

Why this exists as a separate `Typography Static` collection instead of literal values directly in Typography's two mode slots: storing the same literal number twice (once per mode) doesn't prevent a designer from editing just one of them. Aliasing both slots to one variable does — there is structurally only one place to change the value. `font-family` was originally an exception (Typography aliased straight into Fonts, skipping Typography Static) but that broke the "every non-fluid property lives in one place" rule, so it was moved: Typography Static now holds the per-style font-family *selection* (an alias into Fonts), and Typography aliases into that selection instead. Resolving `font-family` from Typography is therefore a 2-hop alias chain (Typography → Typography Static → Fonts) — Figma resolves chained aliases transitively without issue, verified by reading the chain end-to-end.

### Color sub-structure

- 8 base primitives + 3 status colors + 3 independent alpha tokens (`line-1`, `line-2`, `image-overlay`) = 14 independent `COLOR` variables.
- 26 semantic names (`background-1..7`, `text-1..3`, `decoration-1..8`, `action-1..8`) are true Figma **aliases** (`{ type: 'VARIABLE_ALIAS', id: <primitive-variable-id> }`) pointing at the 8 base primitives — mirrors the SCSS exactly (`$background-1: $primary;` etc.), and means editing a primitive cascades to every alias automatically, in both Figma and SCSS.
- **`line-1`, `line-2`, `image-overlay` are deliberately *not* aliases, but they keep the `color.adjust()` opacity mechanism.** Figma's variable aliasing is whole-value only — you can't alias RGB while overriding alpha in the same bound value, so aliasing to `dark-1`/`light-1` was never actually possible as a live link. The SCSS side originally referenced the source primitive directly inside `color.adjust($dark-1, $alpha: -0.7)`, but that created exactly the ambiguity a sync needs to avoid: when these three drifted from the source primitive in Figma (an editor recolored them directly rather than editing `dark-1`), there was no way to tell "intentional override" from "stale/forgotten."
  **Current policy**: the hex argument to `color.adjust()` is a Figma-authoritative literal (`color.adjust(#434343, $alpha: -0.6)`), not a reference to `$primary`/`$dark-1`/`$light-1` — so it stays independent of palette edits — but it's still wrapped in `color.adjust()`, because that's this project's standard way to express opacity, not a one-off `rgba()`. Store `line-1`/`line-2`/`image-overlay` as Figma `COLOR` variables holding the **fully resolved** `{r,g,b,a}` (i.e. what `color.adjust()` would produce), since Figma has no equivalent of the adjust function — the SCSS side reverse-engineers the `$alpha` offset from Figma's resolved alpha (`resolved_alpha - 1.0`) when syncing back. See `figma-to-scss.md` §3 for the exact reconstruction steps.

---

## 4. Naming convention

The `/` in a Figma variable name creates a folder in the Variables panel — Figma groups by everything **before** the last `/`. This is the mechanism, and it decides naming order: **group by whatever the designer needs to see together, not by data type.**

- **Colors collection** holds only colors, so the collection name already *is* the category — names stay flat: `$primary` → `primary`, `$background-1` → `background-1`, `$decoration-7` → `decoration-7`. No `color/` prefix.
- **Dimensions collection** holds one kind of *data type* (all FLOAT dimension values) but several distinct *categories* of token, so it's organized into purely organizational sub-folders that don't correspond to any nesting in the SCSS source (these are flat top-level SCSS variables, not a Sass map): `Spacing/gap-none`, `Wrapper/wrapper-default`, `Header/header-height`, `Borders/radius-medium`, `Vertical Spacing/vertical-default`, etc. Unlike Typography's per-style folders, **the leaf name here is always the full, unmodified SCSS variable name** — never strip the redundant-looking prefix (`Wrapper/wrapper-default`, not `Wrapper/default`), because there's no natural map structure to justify it and a uniform "folder + full original name" rule is what keeps the reverse sync mechanical: take the segment after the last `/`, that's the SCSS variable name, every time, no exceptions to remember.
- **Typography collection** is named **style-first, property-second**: `display-1/font-size`, `display-1/line-height`, `display-1/font-weight`, `display-1/letter-spacing`, `display-1/font-family`. This puts every property of one type style in one Figma panel folder — the whole point of the structure in §3. Don't name these `font-size/display-1` (property-first) even though that groups all font-sizes together — that was the original approach and it actively worked against editing one style at a time, which is the more common designer task.
- **Fonts** and **Typography Static** collections stay flat (`font-family/font-1`, `display-1/line-height`, `display-1/font-family`, …) — they're backing stores referenced by alias from Typography, not somewhere a designer browses directly day-to-day, so grouping by what they literally are (a font stack list; a style's canonical static values) is fine.

**Per-style, every property — including `font-family` now.** `font-size`, `line-height`, `font-weight`, `letter-spacing`, and `font-family` each get their own variable per type style — `display-1/line-height`, `heading-4/line-height`, `display-1/font-family`, etc. — even when many styles currently hold the identical value (e.g. `font-weight` is `700` for nine styles and `400` for the other nine; most styles share `font-family/font-1`). This was chosen over de-duplicating into shared `font-weight/bold` / `font-weight/regular` variables: keeping every property inside one style's folder, fully self-contained, was judged more valuable than avoiding the duplication. The actual font *stacks* still have one true shared list — there are only 3 in the whole system (Fonts collection) — but the per-style *selection* of which stack a given style uses lives in Typography Static, not as a direct Typography→Fonts alias, so it's grouped consistently with line-height/font-weight/letter-spacing rather than being the odd one out.

`font-weight`, `line-height`, and `letter-spacing` are also **aliased**, not literal, within the Typography collection itself — both Mobile and Desktop point at the same canonical variable in Typography Static (§3's lock mechanism). So there end up being two ID lookups for these three properties: the Typography-collection variable a designer sees in the `{style}/` folder, and the Typography-Static variable it points to, which holds the actual value. When extracting a token table from Figma, always resolve through to the Typography Static value — don't read the alias wrapper as if it were the value itself.

None of `line-height`, `font-weight`, `letter-spacing`, `font-family` are bound to the live Text Style rendering — applied as literal values instead, same reasoning as `line-height`'s pixel-unit binding bug (§7). This has a real operational consequence — see §5. `letter-spacing` is treated with the same conservative posture as `line-height` even though the pixel-unit bug was only ever *confirmed* for `lineHeight` — `LetterSpacing` has the identical `{value, unit: 'PIXELS'|'PERCENT'}` shape in the typings, so assume the same bug applies until proven otherwise; don't bind it to test the theory. `text-decoration` and `text-transform` are deliberately excluded entirely: every value is `none` in the current SCSS, and Figma's Plugin API has no bindable text field for either, so they'd only ever be inert strings. Add them later if a style actually needs a non-default value.

**`letter-spacing` value convention: CSS `em` × 100.** SCSS expresses letter-spacing in `em` (e.g. `0.02em`); Figma's letter-spacing has no `em` unit, only `PIXELS`/`PERCENT` — and `PERCENT` is the same concept as `em` (1% = 0.01em by definition, since 100% = 1em = the full font size). So a Figma variable value of `2` represents `0.02em`, matching the same "store the percent-equivalent number" pattern already used for `line-height` (CSS `1.2` → Figma `120`). `0` stays `0` regardless of unit, which is why this had no observable effect before any style actually needed non-zero letter-spacing.

---

## 5. The canvas goes stale — install the refresh plugin, don't rely on a script

**This is the single most important operational fact about this file: editing a Typography Static variable (`line-height`, `font-weight`, `letter-spacing`, `font-family`) does *not* change how anything renders on the canvas.** Only `font-size`, colors, and Dimensions bars are genuinely bound — everything else in Typography is a literal snapshot applied once, because Figma can't safely bind those four properties (§7, gotchas 4 and 9). This isn't limited to `line-height`/`letter-spacing` (the confirmed pixel-unit bug) — **`font-family` was directly tested and also failed to propagate**: binding it via `setBoundVariable('fontFamily', ...)` succeeds with no error, but changing the underlying variable afterward does not change the rendered font, even on the actual text node, not just the shared style object. Treat all four properties as equally non-live; don't try to find a clever binding workaround, one was already tried and confirmed not to work.

A designer can edit `display-1/font-weight` in the Variables panel, see the new value there, and the Style Guide will keep showing the old one indefinitely — nothing will look broken, it will just be **wrong and silent**. This already happened twice in this project's real history: a per-style `line-height` split was applied to the variables but never propagated to the actual Text Styles (went unnoticed until an unrelated audit caught it), and separately a designer's `font-weight` test edit produced no visible change until someone thought to check.

**Good news: this does not affect `figma-to-scss.md`'s correctness.** That sync reads variables directly (`getVariableByIdAsync`, following aliases into Typography Static) — it never looks at what's rendered on the canvas. A stale canvas cannot produce a wrong code sync. The risk is purely that a **human reviewing the Style Guide visually** gets misled — including a designer trying to get client sign-off on something that doesn't actually match what the variables say.

**The fix is `.claude/skills/figma-sync/figma-plugin/`** — a real, installable Figma plugin (not a script that requires an agent to run), with a single "Refresh" action a designer can click themselves from Figma's Plugins menu, any time, with no code or terminal access needed. See that folder's `README.md` for install/usage instructions to hand to whoever needs them. It reapplies every current Typography Static value (line-height, font-weight, letter-spacing, font-family) onto every Text Style, and regenerates the Style Guide's caption text to match — both problems in one click. **Make this the standard instruction to anyone editing Typography variables: click Refresh before you trust or share what you see.**

If you're operating as an agent with `use_figma` access and need a one-off refresh without waiting for the plugin to be installed, the plugin's `code.js` is directly adaptable into a `use_figma` script — it already avoids hardcoded style IDs (discovers style names dynamically from each Text Style's bound `font-size` variable name), so it's a better base to copy from than reconstructing the logic from scratch.

---

## 6. Two-phase workflow

**Phase 1 — extract, no Figma calls:**
1. Parse `_variables.scss` and imports per §1.
2. Build one token table covering every category, with fluid values explicit as `{ mobile, desktop }` pairs (§2) and static values as a single number/string.
3. Show the full table to the user. Call out anything ambiguous: custom fluid breakpoints, tokens that look fluid but resolve to equal mobile/desktop values, semantic color groups with gaps (e.g. `background-1..7` skips a primitive that `decoration-1..8`/`action-1..8` include).
4. **Stop. Wait for explicit confirmation** before any Figma MCP call.

**Phase 2 — build, after confirmation:**
1. Load the `figma-use` skill (and `figma-generate-library` if creating new components, not just variables) before any `use_figma` call.
2. Create variable collections + modes first, as their own step (§3).
3. Create variables grouped by collection/category, validating counts after each (`figma.variables.getLocalVariableCollectionsAsync()` → check `variableIds.length` matches expectation).
4. Only then build visual frames/sections referencing those variables — see §7 for call-sizing and validation cadence.

Never skip from "user described tokens" straight to Figma calls — the token table is the contract the user is confirming, not a formality.

---

## 7. Known gotchas (all hit and fixed in the reference build)

1. **`resize(w, h)` resets *both* sizing axes to `FIXED`.** Calling `node.resize(150, 0)` after setting `layoutSizingHorizontal = 'FIXED'` also silently forces `layoutSizingVertical = 'FIXED'` at height `0` — collapsing an auto-layout card to zero height with its children invisible/overlapping. Resize **before** setting any `HUG`/`FILL` sizing mode, never after. If a card needs a fixed width but hugging height, just append its children (sized by their own content) and let the parent hug — don't `resize()` it at all.

2. **`layoutSizingHorizontal/Vertical = 'FILL'` only works after `appendChild` to an auto-layout parent.** Setting `FILL` on an unparented node throws `FILL can only be set on children of auto-layout frames`. Append first, then set sizing.

3. **New TEXT nodes default to unconstrained `textAutoResize` (`WIDTH_AND_HEIGHT`).** A large specimen (e.g. a 100px display style) will silently overflow its parent frame's right edge instead of wrapping. Explicitly set `textAutoResize = 'HEIGHT'` and `layoutSizingHorizontal = 'FILL'` (after appendChild) on any text that should wrap within its container.

4. **Binding a `FLOAT` variable to `lineHeight` forces Figma to interpret the number as raw pixels, regardless of the unit you set on the property beforehand.** Seeding `textStyle.lineHeight = { unit: 'PERCENT', value: 120 }` then calling `setBoundVariable('lineHeight', floatVar)` silently flips the live unit to `PIXELS` — so a variable meant to represent a `1.2` CSS multiplier (stored as `120` for percent) renders as a literal 120px line-height on *every* font size, blowing out anything smaller than ~100px. **Fix: never bind line-height to a variable.** Keep the FLOAT variable as a documentation-only token (description states it's not bound and why), and apply the literal `{ unit: 'PERCENT', value: 120 }` directly on the Text Style/node. Percent, unbound, scales correctly with whatever font-size variable *is* bound — which is the actual goal.

5. **`fontSize` is bindable, but not via the generic `node.setBoundVariable`.** That method's field list (`VariableBindableNodeField`) covers structural properties like `width`/`padding`/`topLeftRadius` — it does not include `fontSize`. Text-specific fields (`fontSize`, `fontFamily`, `fontWeight`, `lineHeight`, `letterSpacing`, …) bind via a different path: `TextStyle.setBoundVariable(field, variable)` on a Text Style object, or `TextNode.setRangeBoundVariable(start, end, field, variable)` on a specific character range of a text node.

6. **`setExplicitVariableModeForCollection` works on *any* `SceneNode`, not just frames.** Every node (including a single TEXT leaf) can be pinned to a specific mode independently — this is how a Mobile/Desktop comparison can live side-by-side inside one parent frame without needing two separate page-level frames. (For a real responsive page mock, set the mode once on the top-level frame instead and let it cascade — see the per-node override only when you specifically need two values visible at once.)

7. **CSS-like `node.query()` selectors break on `/` even inside an exact-match bracket.** `query('FRAME[name=swatch/primary]')` throws `Invalid selector`. Use a starts-with selector without the slash (`query('FRAME[name^=swatch]')`) and filter for the exact name in JS afterward.

8. **Figma variable aliasing is whole-value only — there's no way to alias RGB while overriding alpha in one bound value.** This is why `line-1`/`line-2`/`image-overlay` aren't aliased to `dark-1`/`light-1` (§3). Don't attempt an alias-plus-alpha-override for a new token either; the working pattern for "a color at a different opacity" is: store the fully-resolved `{r,g,b,a}` as an independent Figma `COLOR` variable, and on the SCSS side wrap a literal hex in `color.adjust(#hex, $alpha: X)` — keep the project's standard opacity mechanism, just don't make the hex argument a reference to another color variable.

9. **A CSS `font-family` value is not a valid Figma font name, and Figma has no concept of a fallback chain.** SCSS's `$font-1: "Inter", Arial, sans-serif;` describes a stack with fallbacks; Figma's font system identifies a font by an exact `{family, style}` pair and will not resolve `"Inter", Arial, sans-serif"` as a family name if that string is ever bound to a `fontFamily` property (it would simply fail to match anything in `figma.listAvailableFontsAsync()`). **Always store the bare family name** (`Inter`) as the Fonts-collection variable's value — verify it against `figma.listAvailableFontsAsync()` first, don't assume the CSS name matches. This bug existed silently for a while in this project because nothing was actually binding the variable (font names were always set as a literal `fontName` object on the Text Styles) — it only surfaces once something tries to use the variable's value as a real Figma font reference, so don't assume "nothing broke yet" means the value is correct. **Adobe Fonts (Typekit) names are the most common real-world mismatch** — the CSS `font-family` from a Typekit kit frequently differs from what Figma displays for the same font once synced via Creative Cloud. Verify explicitly with `listAvailableFontsAsync()` for every font in the system; if a CSS name doesn't match anything in Figma's list, flag it to the user rather than guessing which Figma font was meant.

   **Store the full CSS stack in `variable.codeSyntax`, not `description`.** `setVariableCodeSyntax('WEB', '"Inter", Arial, sans-serif')` is Figma's purpose-built field for "how does this design value map to code" — use it instead of embedding the CSS value in a prose description. This matters beyond tidiness: `description` is the field every designer sees as general notes and can casually rewrite (fix a typo, add a comment) with no idea it's secretly load-bearing for a sync script. `codeSyntax` is a distinct, structured, per-platform field (`WEB`/`ANDROID`/`iOS`) surfaced separately in Figma's UI (via Dev Mode / the variable's code-syntax editor, not the general notes field) — much less likely to be touched by accident, and it's read back as a discrete value, not something that needs prose-parsing. Read it with `variable.codeSyntax.WEB`, write it with `variable.setVariableCodeSyntax('WEB', value)`. Leave `description` for actual human documentation (what the font is used for, why it exists) — don't overload it as a second data channel again.

   This pattern is specific to `font-family` today because it's the one token where the Figma-bindable value and the SCSS output genuinely can't be derived from each other by formula — there's no rule that turns `Inter` into `"Inter", Arial, sans-serif` for an arbitrary font (a different font might need different fallbacks entirely). `line-height`'s Figma-value-vs-SCSS-value gap (percent vs. unitless multiplier) doesn't need this — `÷ 100` is a universal, always-correct formula, so a plain conversion rule in the sync skill is sufficient there; don't add `codeSyntax` for tokens where a deterministic formula already does the job. If a future token turns up with the same "no formula, needs an explicit stored mapping" shape as font-family, `codeSyntax` is the mechanism to reach for.

10. **Figma's Plugin API has no way to reorder variable collections programmatically** (confirmed against the typings — reorder methods exist for paint/text/effect/grid styles, not variable collections). If the collection order in the Variables panel matters, it has to be dragged by hand in Figma's UI — create collections in the desired order when possible, since that's the only order you control from code.

11. **Editing a color variable's value resets the opacity of every paint bound to it back to 1 — file-wide.** Confirmed 2026-07-05 with a controlled sequence: 20 paints verified at opacity 0 by readback → only their bound variables' values were rewritten (`setValueForMode`, no node touched) → all 20 read and rendered opacity 1. This applies to *any* value write — re-aliasing, a designer editing a palette color in the UI, or this very skill pushing updated SCSS token values. It retroactively explains every "transparent state mysteriously went solid" regression in this file (Swiper Control ×5, Button, Pagination); those were never random. Two obligations follow:
    - **After any run of this skill that writes color variable values, check components for transparent states that went solid.** As of 2026-07-05 every built component uses the immune pattern below, so nothing *should* revert — but any opacity-0 bound paint introduced since then will.
    - **When building components, never express a transparent state as an opacity-0 paint bound to a variable — use an empty paint array (`fills = []` / `strokes = []`) instead.** Empty arrays are immune to variable edits; the role variable can still exist unbound for designers to apply when they want the state visible. See `component-to-figma.md` §4.15.

---

## 8. `use_figma` call sizing and validation

- **Prerequisite**: the remote Figma MCP server must be connected via the `figma` Claude Code plugin — `claude plugin install figma@claude-plugins-official`. If `whoami` or any `mcp__plugin_figma_figma__*` tool call fails outright, that's the first thing to check, not a code bug.
- Keep each `use_figma` call to roughly **10 logical operations or fewer** (a logical operation = create a node, set its properties, parent it). Bulk variable creation (looping to create 20–40 `COLOR`/`FLOAT` variables in one script) is the accepted exception — that's a flat, repetitive, low-risk operation, not a layout-building one.
- Build order that worked: collections+modes (1 call) → color primitives/status/alpha (1 call) → color semantic aliases (1 call, references IDs from the previous call) → spacing variables (1 call) → typography variables (1 call) → page/frame skeleton with placeholders (1 call) → populate one visual section at a time (1 call per section).
- **Call `get_screenshot` after every section**, not just at the end. Every real bug in the reference build (zero-height cards, text overflow, the line-height pixel bug) was caught by a screenshot immediately after the section that introduced it — not by re-reading the script.
- When a multi-section page grows after a later edit (e.g. typography frame gets taller after fixing line-height), **reflow sibling frame Y-positions in their own follow-up call** — don't assume stale `y` values are still correct after editing an earlier section.

---

## 9. A section/block frame's outer vertical padding binds to `Vertical Spacing/*`, never `Spacing/gap-*`

**Every top-level archetype frame on a Sections Library page** (`{Archetype} / Desktop`, `{Archetype} / Mobile`, and their `(Dark bg_color)` / `(Light bg_color)` counterparts) represents a real block's `render()` wrapper — its `paddingTop`/`paddingBottom` must be bound to one of the five `Vertical Spacing` collection variables: `vertical-default`, `vertical-none`, `vertical-small`, `vertical-medium`, `vertical-large`. Nothing else is a valid binding for this property — not `Spacing/gap-jumbo`, not `Spacing/gap-large`, not a raw unbound number.

**Default to `vertical-default` unless the design has a specific reason for a different one.** This mirrors the code convention: vertical spacing is never hardcoded in a block's SCSS — it comes from the global `spacing_top`/`spacing_bottom` ACF field, and a fresh block's `config.php` declares `field_defaults => ['spacing_top' => 'default', 'spacing_bottom' => 'default']` and only deviates deliberately (block-builder skill §3, §8). When converting an archetype's binding into a real block's `config.php`, translate the Figma variable name to the matching **ACF choice key** — they differ for one tier: `Vertical Spacing/vertical-medium` → `'normal'`, not `'medium'` (`'medium'` isn't a valid stored value and silently no-ops). Every other tier matches by name (`vertical-default` → `'default'`, `vertical-none` → `'none'`, `vertical-small` → `'small'`, `vertical-large` → `'large'`).

**This was violated across nearly every Sections Library page built before 2026-07-05**: every archetype frame was bound to `Spacing/gap-jumbo` (100/50) instead of `Vertical Spacing/vertical-default` (80/40) — a plausible-looking mistake, since `gap-jumbo` is the closest-valued generic spacing token and sat right next to it in the same Dimensions collection, but it is not the token the padding field is supposed to reference. This happened because early section pages were built to visually match an existing code block (`testimonial-1`, since renamed `testimonial-9`) that itself hardcoded `fluid(50, 100)` instead of `$vertical-default` — the Figma-side mistake and the code-side mistake were the same root cause, copied forward by precedent rather than re-derived from the actual token. Don't repeat that shortcut: always bind to the named `Vertical Spacing/*` variable, never to whatever `Spacing/gap-*` value happens to look close.

**Exception — full-bleed / manually-positioned archetypes** (e.g. Hero's Banner/Cinematic/Slider/Showcase, Testimonial's Video): these use `layoutMode: NONE` with an absolutely-positioned overlay card instead of auto-layout padding (the "manual-container recipe" — see `component-to-figma.md`/memory for the pattern). They have no `paddingTop`/`paddingBottom` in play at all, so there is nothing to bind — don't force a `Vertical Spacing` variable onto a frame that isn't using padding as its spacing mechanism.

**A frame with intentional asymmetric padding** (e.g. a `Callout` archetype that sits flush against whatever's above it) still must bind **both** sides — the zero side binds to `vertical-none`, not a bare unbound `0`. An unbound literal, even if numerically correct, fails an audit exactly like this one silently: `boundVariables.paddingTop` being absent looks identical to "nobody thought about this yet."

**How to audit**: for each Sections Library page, read every top-level `FRAME` child's `boundVariables.paddingTop` / `paddingBottom` and confirm the referenced variable's name starts with `Vertical Spacing/`. Fan the audit out across pages per the page-switching rule (`figma-use` skill §2) — one `use_figma` call per page, issued in parallel.

## 10. A section/block frame needs its own nested `Wrapper` node — horizontal padding doesn't belong on the outer canvas

Real code always renders `<div class="wrapper">` (`_wrapper.scss`) as a **second, nested** inset inside the block's `<section>`: `max-width: $wrapper-default` (1360px, static) + `padding: $horizontal-padding` (fluid) + `margin: 0 auto`, with `box-sizing: border-box`. On a 1440px-wide desktop canvas that's **two layers** — a margin from centering the capped 1360px box, *plus* the 40px padding inside it — so visible content is 1280px wide, not 1360px and not 1440px.

**The bug this produces**: modeling only one inset layer — horizontal padding baked directly onto the outer 1440 canvas frame, giving 1360px content. It looks plausible at exactly 1440px canvas width (the numbers coincidentally land close), which is why it went unnoticed across nearly every archetype in the library before it was caught (via `testimonial-1`'s real rendered code not matching its Figma design once wrapper markup was added to the block).

**The fix**: the outer canvas frame (`{Archetype} / Desktop`, `{Archetype} / Mobile`) gets **zero** horizontal padding of its own. Content lives inside a dedicated child frame named `Wrapper`, fixed-width at the intended `Wrapper/*` variant size (1360 default, or 800/1000 for `.wrapper.small`/`.medium`), centered on the canvas (`counterAxisAlignItems: CENTER`), with its own `paddingLeft`/`paddingRight` bound to `Wrapper/horizontal-padding` — that variable resolves to the breakpoint's own value automatically (40 desktop, 20 mobile), so the same binding is correct at both sizes.

**Mobile needs the same nested structure too, even though the numeric bug is a no-op there** (360px canvas never exceeds any `Wrapper/*` max-width, so there's structurally only one inset layer regardless) — model it identically for fidelity to what the code actually renders, not just to get pixels right.

**Exception**: full-bleed elements deliberately left un-wrapped — background images/video, scrolling marquees, an intentional horizontal-scroll overflow (a Filmstrip). These are meant to be edge-to-edge; don't force a `Wrapper` node onto them.

**Gotcha**: `figma.createAutoLayout()` defaults to an opaque white `SOLID` fill. A newly-created `Wrapper` frame with this default fill silently covers dark-background content behind it (only becomes visible as a bug once something renders wrong against a dark variant). This is no longer just a "remember to do X" reminder — run the mandatory stray-fill audit from `component-to-figma.md` §4.2 at the end of any script that creates more than one auto-layout frame; it catches this mechanically instead of relying on remembering `fills = []` on every single node.

**How to audit**: for each top-level archetype frame, confirm the outer canvas has no horizontal padding of its own and that a `Wrapper` child (or equivalently-named consolidating frame) carries the fixed width + `Wrapper/horizontal-padding` binding instead. Re-scan with a fresh `page.children` read before considering a page done — don't trust an earlier exploratory pass as the final inventory on pages with many archetype × light/dark × mobile/desktop combinations.

## 11. Dark bg_color variants — build one only when the dark version requires a decision

Early section pages cloned every archetype into a `(Dark bg_color)` counterpart (the x=2040/x=3600 columns) by default. **That is no longer the default** (policy change 2026-07-06, after the Text page build confirmed the recolor is almost entirely mechanical): dark clones double a page's frame count and silently drift stale whenever the light frame is iterated (§5's staleness problem, doubled), while demonstrating nothing that isn't already a rule. The mechanical recolor ruleset now lives in `wp-framework-block-builder` §8 (background/text/line token pairs, `-Light` button swap, `$primary`→`$secondary` accents, `$background-5`→`Panel/background-dark` elevated surfaces) — a code build can derive a block's dark treatment from the light frame alone.

**Build a dark counterpart only when the archetype introduces something the ruleset doesn't answer**: a new elevated-surface treatment (Hero 5's callout panel is what produced `Panel/background-dark` in the first place), imagery/overlay compositions whose dark legibility needs to be seen, a dark-native archetype, or any element whose dark treatment is a judgment call rather than a substitution. When one is built, follow the established pattern — clone at x+2040 (Desktop) / x+3600 (Mobile), rename `… (Dark bg_color)`, apply the ruleset — and treat it as owned: every future edit to the light frame must be mirrored, or the dark frame deleted.

**Recolor-script gotchas** (both hit on the Text page build): (1) `setProperties()` (variant swap) on an instance invalidates other stale `findAll` references inside the same subtree — sweep in two passes: recolor the frame's own `TEXT`/`RECTANGLE`/`FRAME` nodes first, then do instance swaps in a separate pass, with try/catch guards. (2) Nested `Button` instances inside a `Buttons` instance have an `INSTANCE` ancestor, so a "skip nodes inside instances" guard silently skips their `-Light` swap — handle `INSTANCE` nodes before (or regardless of) the ancestor check.

## 12. Every text node gets a matching Text Style applied (`textStyleId`), not just a bound `fontSize` variable

**The standing "frame recipe" for a new text node** (create → bind `fontSize` to a typography variable → bind `fills` to a color variable → set `lineHeight`/`letterSpacing`/`textCase` literally, per §7 gotcha 4) **produces a pixel-identical result to applying the matching local Text Style, but doesn't link the node to it.** Binding the variable is necessary — it's how the size responds to the Mobile/Desktop mode — but it is not sufficient on its own; it was silently treated as the whole job across every Sections Library page built before 2026-07-07 (Hero, Content Image, Accordion, CTA Banner, Testimonial, Carousel, Tabs, Location, Text, Stats, Listing, Form-1..10 — all of them), because nothing in this doc or the bootstrap memory's frame recipe ever said "…and then also set `textStyleId`."

**This file's own Text Style objects (`Display/Display 1-3`, `Heading/Heading 1-6`, `Paragraph/P Small-Large`, `Action/Action Small-Large`, `Link/Link Small-Large`, `Overline/Overline Regular-Large`) already bind their internal `fontSize` to the exact same Typography variables this doc tells you to bind directly** — confirmed by reading `style.boundVariables.fontSize` on each: it's the same `VariableID:2:7x`/`413:1x` as the variable named in §1's naming convention. That makes the mapping mechanical: whatever typography variable a text node's `fontSize` is bound to, there is (almost always) exactly one Text Style bound to that same variable — apply it.

**The fix — do this as a real step in the frame recipe, not an optional polish pass:**
```js
const styles = await figma.getLocalTextStylesAsync();
const styleByVar = {};
for (const s of styles) {
  const alias = s.boundVariables && s.boundVariables.fontSize;
  // fontSize's bound-variable alias is stored as a 1-element ARRAY, not a bare object — see §7/gotchas.md
  const varId = Array.isArray(alias) ? alias[0] && alias[0].id : (alias && alias.id);
  if (varId) styleByVar[varId] = s;
}
// then, per text node (skip anything inside a component instance — id contains ';'):
const alias = node.boundVariables.fontSize;
const varId = Array.isArray(alias) ? alias[0].id : alias.id;
const style = styleByVar[varId];
if (style) { await figma.loadFontAsync(style.fontName); node.textStyleId = style.id; }
```
Run this **immediately after building each section**, not as a separate cleanup pass at the end — cheaper to catch in the same `get_screenshot` round-trip than to audit 150+ nodes across 8+ archetypes after the fact.

**Known deliberate exceptions — don't force a style onto everything that happens to have a bound fontSize:**
- A node with a bound fontSize but a **different fontName than the style's** (e.g. a decorative wordmark set in `Plus Jakarta Sans Bold` at the `P Medium` size, deliberately breaking from body-text convention for a "logo" treatment) — applying the style would silently overwrite the intentional font override. Exclude these node IDs explicitly rather than letting the automatic pass touch them.
- Form-1/Form-2's "overline" text is bound to `heading-6` (`VariableID:2:76`), not `overline-regular` — a confirmed, deliberate deviation from the usual overline treatment (matches `form-1.scss`/`form-2.scss`'s real `.overline{heading-6}` rule). The automatic pass handles this correctly on its own: it applies **`Heading 6`**, not `Overline Regular`, because it maps by the variable actually bound — don't "fix" this by hand-forcing the overline style, the code convention is the one that's supposed to win.

**How to audit an existing page for this gap**: `frame.findAll(n => n.type === 'TEXT' && !n.id.includes(';'))`, then check `n.textStyleId === ''` — any non-empty-string result with a bound `fontSize` variable and no `textStyleId` is unlinked and should be fixed with the script above.
