---
name: figma-sync-figma-to-component-scss
description: Use when asked to pull component-level design changes from Figma (colors, spacing, radius, states, structure) back into a specific component's SCSS/PHP — e.g. "I edited the Button component in Figma, update the code," or "sync my Figma changes." Companion to figma-to-scss.md, which covers the abstract _variables.scss token layer, not individual components. Covers the full-property-audit rule, the component-by-component workflow, and Plugin API gotchas specific to building/editing component variant sets (as opposed to bare variables).
---

# Figma → component code — pull a built component's design edits back into its SCSS

Companion to `figma-to-scss.md` (token layer) and `scss-to-figma.md` (token layer, push direction). This skill is one layer up: it's for a Figma **component** — a `COMPONENT_SET` with real variants (e.g. Button's `Variant` × `State` × `Breakpoint`), built to mirror an actual `src/content/components/{tier}/{name}/{name}.scss` + `.php` pair (tiers: `style-guide/`, `custom/`, `library/` — Button lives in `style-guide/`) — not the shared design-token collections those live on top of.

Built from the real first end-to-end cycle of this workflow (the Button component, this theme). Every rule below was hit as a real bug or a real question in that build, not written speculatively.

---

## 1. The core rule: audit every property, not just the ones you know changed

**This is the rule that was violated for real in this project's first sync, and it's the single most important thing in this file.** A user reported "you didn't update the border color" after a sync that only re-checked the dimension variables (height/padding/gap/icon-size) that had been the subject of the *previous* conversation turn. The border-color rebinding (a variant's stroke rebound from `action-1` to `status-1`) was a silent, independent edit sitting in a completely different property (`strokes[0].boundVariables.color`) that nothing prompted a check of.

**Do not scope a sync to "the properties that were mentioned as changed."** Every sync must re-read and diff, per variant:

- Fill color (background) — `wrapper.fills[0].boundVariables.color` → resolve to variable name
- Stroke color (border) — `wrapper.strokes[0].boundVariables.color` → resolve to variable name
- Text fill color — `text.fills[0].boundVariables.color`
- Icon stroke color — `icon.strokes[0].boundVariables.color`
- Every bound dimension variable's current `valuesByMode` (height, padding, gap, icon size, border width, radius, opacity — whatever the component's dedicated variables are, see §3)
- `strokeWeight`, corner radius, `opacity`
- Text decoration / other structural style flags (underline, italic, etc.)
- Anything visually distinct between states that isn't captured by the above (e.g. a state that only differs by a CSS pseudo-effect like `text-decoration: underline` on hover)

A single `use_figma` read that walks every variant and extracts all of the above in one pass (see §5 for the script shape) is cheap. Skipping properties because "that's not what we were just talking about" is what caused the miss.

**A second, distinct miss (2026-07-05): "the binding role matches its last-known-documented state" is not the same claim as "the code SCSS is correct."** A Button sync found every Figma binding matching the role mapping recorded from a prior session (Primary→`primary`/`dark-1`, etc.) and concluded no code change was needed, reasoning that the color *hex values* were a deliberate Figma-only palette experiment not meant to sync back (per that project's own memory of an earlier "make Figma look realistic" pass). That memory turned out to be stale — the palette had, in fact, already been merged into the global SCSS token file by then. Because of the stale "Figma-only" assumption, the audit never checked whether `button.scss` was referencing the *correct* semantic variable for each role — it wasn't: `.style-primary` referenced form-validation tokens (`$status-1`/`$status-3`) instead of the actual brand-action tokens (`$action-1`/`$action-7`), a pre-existing bug unrelated to the Figma sync itself, invisible until the palette actually matched and made the wrong-token bug visually obvious (green instead of indigo). **Lesson: a binding-role match only proves the Figma side hasn't drifted from its own documented history — it proves nothing about whether the SCSS variable used in code is the semantically correct one for that role.** Before declaring "no change needed" on a color audit, grep the SCSS variable in question across the codebase (e.g. `grep -rn '\$status-1'`) to confirm it's actually used for the role the component needs, not just a plausible-looking token that happens to resolve to the right family of value. Also: don't take a project memory's "this is intentionally not synced to code" claim at face value when the whole point of the current task is to check sync state — verify directly (`git diff`/`git status` on the token file) rather than trusting a note that may predate a later, untracked change.

---

## 2. Component-by-component, not all components at once

If asked "I have a fully-designed Figma file, what's the best way to bring it into code — one component at a time, or all of them?" — **one component at a time.** Reasons, from direct experience building Button:

1. **The full-property audit in §1 is not free.** It's a handful of `use_figma` calls per component (enumerate variants, extract properties, diff, apply, re-verify). Running it thoroughly for N components in one pass multiplies both the call volume and the chance of skipping a step under time pressure — exactly the failure mode §1 exists to prevent.
2. **Platform quirks surface per-component, and they're easiest to catch immediately after the change that caused them**, not after a batch of five components have all been touched. Every gotcha in §6 was caught by checking the *specific* component right after editing it — a batched sweep across many components would have buried the signal.
3. **Structural/scope questions come up per component** (see §7) — whether a Figma edit implies a SCSS-only change or needs a PHP change too. That's a judgment call best made with one component's context loaded, not five at once.
4. **A clean commit boundary.** Each component's code sync is independently reviewable — "here's what changed in Button" is a much easier diff to sanity-check against memory than "here's what changed across Button, Card, and Modal."

If the user wants to move through several components in one session, treat it as **N sequential single-component syncs** — same steps, same verification, not a bulk special case.

---

## 3. Component-scoped dimension variables

For Button, the project owner asked for *dedicated* variables (`Button/height`, `Button/padding-x`, `Button/gap`, `Button/icon-width`, `Button/icon-height`, `Button/border-width`, `Button/radius`, `Button/disabled-opacity`) rather than having the component's SCSS keep referencing shared global tokens (`$gap-small`, `Borders/radius-small`, etc.) even where the values happened to coincide at build time.

**Why this matters for the sync direction**: it means a component's SCSS values are expected to *diverge* from the shared design-token scale over time — that's the point, it's what "let the designer tune this component independently" means. Don't treat a component's height/padding/radius/etc. differing from the equivalent global token as a discrepancy to flag; it's the intended behavior. Only flag it if the component variable itself doesn't exist where you'd expect one (e.g., a new visual property with no dedicated variable at all — ask whether one should be created, following the same one-variable-per-tunable-property pattern).

**Icon width and height are separate variables, not one square `icon-size`.** This was corrected mid-build — even though the actual SVG (`arrow-alt.svg`) happens to be square today, width and height were split into independent variables specifically so a designer can make an icon non-square later without needing an architecture change. Follow this pattern for any future icon-bearing component: don't collapse width/height into one variable just because they're equal right now.

---

## 4. Naming and value reconstruction

Same fluid-vs-static rule as `figma-to-scss.md` §4 — Mobile ≠ Desktop → `fluid(mobile, desktop)`; Mobile == Desktop and non-zero → `rem(value)`; Mobile == Desktop and zero → bare `0`. This applies per-property, independently — a component's `gap` can flip between static and fluid across syncs as a designer's edits happen to converge or diverge the two mode values (this happened for real: Button's `gap` went `rem(16)` → `fluid(20, 30)` → `rem(20)` across three consecutive syncs in the same session, simply because the Desktop value kept changing relative to Mobile).

**`OPACITY`-scoped variables are 0–100, not 0–1** — this is a hard, confirmed-by-testing exception to how every other FLOAT variable in this system behaves, and it does not follow from anything documented in `figma-to-scss.md`. A variable meant to represent CSS `opacity: 0.4` must store the Figma variable value `40`, not `0.4` — storing `0.4` resolves to an actual rendered opacity of `0.004` (Figma appears to further divide by 100 on top of the node's own 0–1 `opacity` property), which reads as "invisible" rather than "40% dimmed." Confirmed by direct measurement: setting the variable's value to `0.4` made a bound node's live `.opacity` read back as `0.004`; changing the variable to `40` made it read back as `0.4`. When converting a Figma opacity-scoped variable's value into SCSS, **divide by 100** (`40` → `opacity: 0.4;`), the exact inverse of the mistake that caused the original bug.

---

## 5. Reading full component state — script shape

**Step 0 — locate the component and map its structure before extracting anything.** Don't assume a node ID or the inner layer names — they differ per component. First find the `COMPONENT_SET` and list its actual structure:

```js
// Find the set by name (search the components/style-guide page, not the whole file blindly)
const sets = figma.root.findAllWithCriteria({ types: ['COMPONENT_SET'] })
  .map(s => ({ id: s.id, name: s.name, variants: s.children.length }));
return sets; // pick the right one, then dump one variant's layer tree:
// (await figma.getNodeByIdAsync(SET_ID)).children[0].findAll(() => true)
//   .map(n => ({ name: n.name, type: n.type }))
```

Confirm the mapping of named layers (wrapper, text, icon) to the SCSS selectors in `src/content/components/{tier}/{name}/{name}.scss` before running the extraction below — a wrong `findOne` name silently returns `null` and the audit reports "no change" for a property that did change.

```js
const componentSet = await figma.getNodeByIdAsync(COMPONENT_SET_ID);

async function varName(boundVar) {
  if (!boundVar) return null;
  const v = await figma.variables.getVariableByIdAsync(boundVar.id);
  return v ? v.name : null;
}

const results = [];
for (const master of componentSet.children) {
  // if the component has a Breakpoint (or other mode-pinned) property,
  // filter to one representative per logical variant first — color
  // bindings don't vary by breakpoint, only dimension values do, so
  // auditing all N breakpoint copies for color is redundant work.
  const wrapper = master.findOne(n => n.name === 'button-wrapper'); // adjust per component's actual inner structure
  const text = master.findOne(n => n.name === 'button-text');
  const icon = master.findOne(n => n.name === 'arrow-alt');
  const fill = wrapper.fills[0];
  const stroke = wrapper.strokes[0];

  results.push({
    name: master.name,
    bgVar: fill ? await varName(fill.boundVariables?.color) : null,
    borderVar: stroke ? await varName(stroke.boundVariables?.color) : null,
    textVar: await varName(text.fills[0]?.boundVariables?.color),
    iconVar: icon ? await varName(icon.strokes[0]?.boundVariables?.color) : null,
    underline: text.textDecoration,
    opacity: master.opacity,
    strokeWeight: wrapper.strokeWeight,
    cornerRadius: wrapper.topLeftRadius,
  });
}

// Then separately dump every dedicated component variable's valuesByMode
// (see figma-to-scss.md §1's collection-dump pattern, scoped to `Button/*` names).
return results;
```

Diff every field in the returned table against the current SCSS, present as a table (old → new), same as `figma-to-scss.md` §5 — don't silently apply.

---

## 6. Known gotchas specific to building/syncing component variant sets

All hit and fixed in the Button build. These are additional to — not a replacement for — the token-layer gotchas in `scss-to-figma.md` §7.

1. **A `COMPONENT_SET` can only have `COMPONENT` children — no `TEXT`, `RECTANGLE`, or `FRAME`.** `combineAsVariants` output is stricter than a normal frame. Captions and any backing/decoration for a variant grid must live as **siblings in a wrapping frame**, position-computed from each variant's local `x`/`y` plus the component set's own `x`/`y` offset — they cannot be added as children of the set itself, even indirectly via a wrapper frame nested inside it.

2. **Variants inside a `COMPONENT_SET` cannot use auto-layout among themselves — always absolute positioning.** When a bound dimension variable grows, each variant resizes correctly on its own (if its *own* internal structure uses auto-layout, as Button's `button-wrapper` does), but sibling variants and their captions do not reflow — nothing pushes them apart. After any variable edit that affects size, **recompute every position from actual measured `width`/`height`** (read them live, don't assume the last-known numbers), with a generous buffer gap (60px+ between columns, 50px+ between rows worked here), not just fixed offsets. Confirm current sizes are actually uniform across the grid (they were, here, since all masters share the same default label/icon-visible state) before relying on one representative measurement for the whole layout.

3. **Cloning a node with a bound-variable color paint can leave it rendering the placeholder fallback color instead of the resolved variable value — even though `boundVariables` correctly reports the right variable id.** Symptom: a variant that should render white text/stroke (bound to a "light" color variable) instead renders black, while `node.fills[0].boundVariables.color.id` shows the correct variable throughout. Confirmed specific to `TEXT` and `VECTOR` node types — `FRAME` fills/strokes on the same clones resolved correctly. Re-applying the same binding (`setBoundVariableForPaint` again) sometimes fixes a `TEXT` node's fill after one or two extra applications, but never reliably fixed a `VECTOR`'s stroke in testing. **The only fix that worked every time: delete the affected `TEXT`/`VECTOR` node and create a fresh one** with the binding applied at creation time, rather than trying to repair the stale clone in place.

4. **Setting `textDecoration` on a node that has an applied Text Style (`textStyleId`) can leak and mutate every other node sharing that same style — not just the node being edited.** Confirmed: setting `textDecoration = 'UNDERLINE'` on one variant's text immediately caused an *unrelated* variant (different name, no code path connecting them) to also read back `'UNDERLINE'`, within the same script, before any batch/loop was involved. A later loop that set explicit `'UNDERLINE'`/`'NONE'` per-node one at a time (still all sharing the style) collapsed to whichever value was written *last* for the entire set. **Fix: detach the shared style on the node first (`text.textStyleId = ''`) before setting `textDecoration`.** Verified this preserves the node's already-resolved `fontName`, `fontSize` (including its variable binding), and fill — detaching only removes the *link* to the style object, not the values it had already applied. Treat `textDecoration` (and be suspicious of any other text property that isn't obviously part of the standard style bundle) as unsafe to set on a styled node without detaching first.

5. **Frames and component variants clip overflowing children by default (`clipsContent`).** After adding new content that extends past a container's current declared `width`/`height` (e.g. adding a second breakpoint's worth of rows below the first), the new content renders as fully invisible — not cropped-looking, just gone — until the container is explicitly `resize()`'d to fit. This bit twice in the same build: once when repositioning a component set's own variants into a taller grid, and conceptually it's the same class of bug as gotcha 2 (stale size assumptions) but distinct enough to check separately — a resize fixes clipping, but doesn't fix overlap, and vice versa.

6. **A script's own read-back of a value it just wrote is not reliable proof the change persisted correctly**, specifically when gotcha 4's kind of cross-node leak is in play. A script that set 36 nodes' `textDecoration` in a loop and then immediately re-read all 36 *within the same script* reported 0 mismatches — but a **separate, later `use_figma` call** reading the same 36 nodes found they'd all collapsed to one value. Always verify a fix with an independent follow-up call before reporting success, not just a same-script echo.

8. **Resolving an alias to "the SCSS name of the referenced primitive" (§1/§8) means the literal, specific variable the chain terminates at — never a different variable that merely holds an equal value today.** Confirmed as a real mistake (Form component, 2026-07-13): `Form/Light/input-background` resolves directly to the primitive `light-2`; `Form/Light/input-border` resolves directly to `dark-1`. Both were written into SCSS as `$action-6`/`$action-7` instead, because those happened to hold the same values *and* matched the Dark-style block's existing idiom elsewhere in the same file. Caught by the project owner, not self-caught: "you used action-6 and action-7, shouldn't you be matching the variables from figma perfectly?" `$action-6 == $light-2` and `$action-7 == $dark-1` only because `action-6`/`action-7` are themselves *separately* defined as aliases to `light-2`/`dark-1` inside the Colors collection — a different, unrelated hop that has nothing to do with what Form/Light's own chain points to. Two variables being numerically equal today is not license to substitute one for the other; trace every chain independently, every time, even when a sibling role in the same file looks like it should obviously use the same token. See `figma-to-scss.md` §2 for the token-layer version of this rule, and [[feedback_figma_alias_match_literal_variable_not_value]] for the full incident.

9. **A sibling instance can silently lack an explicit Desktop/Mobile mode override that its neighbors all have — binding a new dimension variable to it then resolves to the wrong mode, with no error.** Confirmed (Form component, 2026-07-13): every `Input` instance inside a Form master carried an explicit mode override for the Components collection (`explicitVariableModes: {'VariableCollectionId:167:2': '167:1'}` for Desktop masters), but the `Select` instance in the same master had `explicitVariableModes: {}` — nothing set at all. Binding a newly-created `Form/input-height` variable to it resolved to the Mobile value (40) even inside a *Desktop* master, and the mutation script reported success with no error, since `setBoundVariable` doesn't validate which mode context it'll actually read from. Caught only by screenshotting the result and noticing the dropdown was visibly shorter than the inputs beside it. Fix: `instance.setExplicitVariableModeForCollection(collectionId, modeId)` on the outlier instance, matching whatever its siblings already carry, *before* rebinding. **Whenever binding a new mode-dependent (Mobile/Desktop) variable across a set of sibling instances, check that every sibling actually has the same explicit mode override set — don't assume mode inheritance is uniform just because the instances look identical — and always screenshot-verify the result per breakpoint rather than trusting a clean script return.**

10. **A component can be a real, independent variant property axis instead of a simulated one** when the requirement is "let me edit this directly where it's displayed, don't make me hunt down a separate master." Button's `Breakpoint` (Desktop/Mobile) started as instances-with-mode-override (one master, shown twice via `setExplicitVariableModeForCollection` on wrapping frames) and was deliberately converted to a real `Breakpoint` variant property (doubling the master count) specifically so every visible button in the documentation *is* a master, not an instance — because the alternative (edit an instance, which only creates a local override; the real source sits elsewhere) was the actual complaint. Tradeoff to disclose when this comes up again: once a property like this exists, an instance's *actual* rendered size still follows whichever page/frame mode context it's placed in (variable-bound dimensions resolve independently per node's own ancestry, not from the master it was instantiated from) — so the property documents a fact, it doesn't control anything for future instances. Say this plainly rather than let the user assume the property is load-bearing for future page-building.

---

## 7. Structural changes: SCSS-only by default, flag PHP/JS separately

A Figma edit sometimes implies more than a property value change in one SCSS file — e.g. adding a `Disabled` state's opacity in Figma surfaced that `button.php` never actually outputs an HTML `disabled` attribute anywhere, so the corresponding `&:disabled` CSS rule is presently unreachable dead code for the component's default (`type: 'link'`) rendering path, since `:disabled` cannot match an `<a>` element at all.

**Default to SCSS-only unless explicitly asked to expand scope.** Apply the styling change (it's still correct and forward-compatible), but call out the structural gap as a separate, clearly-labeled note rather than silently also patching the `.php` template or adding JS — that's a bigger, different kind of change (new prop, new markup, possibly a `.is-disabled` class fallback for the non-button-element case) that deserves its own explicit go-ahead.

---

## 8. Output as a diff — same discipline as the token layer

Same as `figma-to-scss.md` §5:

1. Read live Figma state (§5 above) and the current component SCSS.
2. Diff every property in §1's checklist — not just previously-flagged ones.
3. Present old → new per changed line. Skip properties with no change — don't list every unchanged selector.
4. **Stop. Wait for explicit confirmation before editing any file.** "Presented the table first" does not count as permission — the same no-direct-overwrite rule as `_variables.scss` applies here. Partial applies are fine if the user excludes some changes.
5. Apply only the confirmed changes, then re-read the file back to confirm the edit landed as intended.
