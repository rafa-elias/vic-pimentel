---
name: wp-block-design-auditor
description: Use AFTER a block design has been generated in Figma (by an AI workflow such as figma-generate-design/figma-generate-library, or by a human working from a brief) and BEFORE it is built as a real WP block via wp-framework-block-builder or promoted into the shared component/block library. This is the final quality gate on the Figma side — audits the generated frames against this framework's design language, component/variable integrity, and production feasibility; classifies every defect by severity and by which reusable layer should absorb the fix (component, variable, pattern, block, or instance); applies safe corrections directly in Figma; and issues one of four verdicts. Complements wp-block-design-philosophy (which governs design decisions before/during generation, not after) — this skill governs whether the *result* has earned its place in the framework. Does not build the WP block itself (wp-framework-block-builder), does not teach Plugin API mechanics (figma-sync/*.md), and does not redesign — it reviews, corrects reusable layers, and gates.
---

# Block design auditor — rear-theme framework

You are acting as the lead design system architect responsible for this framework's long-term integrity — the person who has to live with every decision made today for years, across clients neither of you have met yet. Think the way the maintainers of Material Design, Shopify Polaris, or the Atlassian Design System think: an individual screen shipping on time is not the job. The system staying coherent after a thousand more screens ship is the job.

**Assume every AI-generated block contains mistakes.** Not "might contain" — assume it does, and go looking. Nothing here is trusted by default: not the layout, not the component choices, not the variable bindings, not the naming, not the claim that it's "responsive," not the claim that it's "done." Everything earns its place through this review, or it doesn't ship.

## Where this sits in the pipeline

| Stage | Skill | Job |
|---|---|---|
| Before generation | `wp-block-design-philosophy` | Decide what the block *should* be — hierarchy, composition, diversity lever, whether it should exist at all |
| Generation | `figma-generate-design` / `figma-generate-library` (or a human) | Produce the actual Figma frames from that decision |
| **This gate** | `wp-block-design-auditor` | Decide whether what got generated actually *is* that decision, executed to the framework's standard — and fix the reusable layers when it isn't |
| After approval | `wp-framework-block-builder` / `wp-framework-component-builder` | Turn the approved Figma design into real PHP/ACF/SCSS |

**Two things this skill is not.** It is not a second pass at `wp-block-design-philosophy` — if the fundamental concept is wrong (wrong silhouette, redundant with an existing block, wrong content hierarchy for the content type), send it back to that skill's reasoning rather than trying to patch it here; Pass 1/2 below exist to *check* the concept survived generation intact, not to re-derive it from scratch. And it is not a redesign tool — the mandate is "does this execution earn its place," not "how would I have designed this instead." An auditor who rewrites every layout to their own taste isn't gatekeeping quality, they're substituting their opinion for the brief, and that erodes trust in the gate itself.

---

## 0. Before starting — load real state, never memory

Values, token names, and the block/component roster drift as the framework and its projects evolve. Pull live state every time; never answer from what a past session remembered.

- **The generated frames themselves** — `get_screenshot` for a visual read, `get_metadata` for structure, `get_design_context` for the full node tree and any bound variables/component instances.
- **`src/styles/abstract/_variables.scss`** — the live token roster. Read the actual values; the *names* (`$gap-*`, `$heading-*`, `$background-*`, etc.) are stable across projects, the values are not.
- **`get_variable_defs`** scoped to whatever collections the generated frames touch — confirms what the design actually bound to, not what it should have.
- **The existing block roster** — `ls src/content/blocks/library/ src/content/blocks/custom/` — and `.claude/design-references/INDEX.md`, so Pass 6 has real material to compare against, not a guess.
- **`wp-block-design-philosophy/SKILL.md` in full** — this skill leans on its diversity levers (§6), token discipline (§0.1), page-assembly constraints (§1), and A/B/C/D evidence classification (§16) without restating them. Read it fresh; don't rely on a summary from an earlier turn.
- **Whatever brief or plan produced this generation** — the field list, structure, and open questions that should have been confirmed before generation started, per the philosophy skill's and block-builder's own plan→confirm gates. If no such record exists, that's itself a Pass 5/7 finding, not something to reconstruct after the fact.

---

## 1. Hierarchy of corrections

Always fix the highest reusable layer capable of solving the problem. A defect that lives in a component or a variable and gets patched on one instance instead is not fixed — it's hidden, and it will resurface on the next block that reuses the same component correctly.

| Priority | Layer | Fix here when… |
|---|---|---|
| 1 | **Components** | The problem is in the master (`COMPONENT_SET`), not the instance — wrong default variant, missing state/variant, broken internal auto-layout, wrong Fill/Hug, stale bounding box, wrong constraints |
| 2 | **Variables** | A value is hardcoded where a token exists, a component-scoped variable (`Components`/`Components Colors`, per `component-to-figma.md` §2) is missing for a tunable property, or an existing variable is bound to the wrong role |
| 3 | **Shared styles** | A text style, effect style, or grid style used inconsistently across instances that should all share one |
| 4 | **Shared patterns** | A recurring structural idiom (the Listing macro-pattern, the asymmetric split-grid formula, the full-bleed media layering from `wp-block-design-philosophy` §4/§7/§8) applied inconsistently or reinvented instead of reused |
| 5 | **Blocks** | The problem is specific to this block's own structure/field list and doesn't generalize |
| 6 | **Individual instances** | Last resort — a true one-off that isn't a symptom of anything upstream |

**Worked example.** A generated hero block's mobile variant uses the desktop-sized Button component with no mode override. Two possible root causes with different fixes: (a) the Button master's `Breakpoint` variant exists and the generation just picked the wrong one → **instance-level fix**, swap to the correct master; (b) the Button component has no dedicated mobile variant at all, or its dimensions aren't bound to the `Components`-collection Mobile/Desktop modes described in `component-to-figma.md` §2 → **component-level fix**, and every other block that has ever used Button on mobile has the same latent bug, whether or not it's been noticed yet. Diagnose which one it is before touching anything — check the master, not just the instance in front of you. Never patch (b) as if it were (a).

**Never manually patch an instance when the underlying reusable asset should evolve instead.** This is the single most important discipline in this skill: it's what makes "AI generates fifty blocks" not add up to fifty subtly-inconsistent Buttons.

---

## 2. The seven passes

Run them in order — later passes assume earlier ones didn't surface a block-concept-level rejection that makes the rest moot. Don't skip a pass because the block "looks fine" on a quick glance; that instinct is exactly what this skill exists to override.

### Pass 1+2 — Design fit and framework fit

Run `wp-block-design-philosophy`'s own **Mandatory design review** checklist (its final section) against the *actual generated result* — screenshots and real structure, not the plan that was approved before generation. A plan can be sound and the execution can still drift from it (wrong scale picked, wrong component reused, an extra decorative element nobody asked for). That checklist already covers hierarchy, composition, whitespace, typography, diversity (§6's silhouette test), reuse-before-creation (§7), field order (§2), page-assembly resilience (§1), accessibility (§9), and responsiveness (§10) — don't re-derive any of it here.

Add these audit-specific questions, which are about *judging a finished result* rather than *making a design decision*, so they don't belong in the philosophy skill itself:

- **The survivorship test.** If the current library had to shrink by 20% tomorrow, would this block be one of the ones kept? A block that's merely "fine" — no real defect, no real distinction — fails this even with a clean checklist.
- **The five-years test.** For a client and a palette nobody involved today has seen, does this still make sense structurally? (Philosophy skill §11/§16 — apply it here as a verdict input, not just a design-time consideration.)
- **The legacy-shortcut test.** Does anything here trace to a `.claude/design-references/` habit that philosophy skill §16 already classified as (A) a weakness or (B) a tooling limitation — an orphaned color reference, a static value where `fluid()` was clearly intended, a raw hex reached for out of habit? Generation pulling from reference material inherits reference material's flaws unless explicitly told not to.

If Pass 1+2 surfaces a concept-level problem (wrong silhouette, redundant with an existing block, wrong content hierarchy for what the content actually is), stop here — route it back through `wp-block-design-philosophy`'s reasoning rather than continuing to audit an execution of the wrong idea. Passes 3–7 are about whether a *sound* concept was executed to standard.

### Pass 3 — Component integrity

For every component instance the generated block uses, determine whether an observed problem originates in the **instance's usage** or in the **component's own design** — and fix the component when it's the latter (§1).

Checklist, run per instance and per master it points to:

- [ ] **Correct variant for context** — a Desktop-sized master used inside a mobile frame (or vice versa) is the single most common failure mode across every component this project has built (per `component-to-figma.md` §7's own caveat: an instance's rendered size follows its own ancestry, not the master it was copied from — a wrong pick doesn't self-correct)
- [ ] **Correct default state** — not left on `Hover`/`Active`/`Disabled` because that's what happened to be selected when the instance was placed
- [ ] **No detached instances** — `get_metadata`/`get_design_context` should show every instance still linked to its master; a detached copy silently stops inheriting any future component-level fix
- [ ] **No duplicated components** — a hand-drawn lookalike of an existing master (a second "button" built from a rectangle + text instead of the real Button instance) defeats the entire point of a component system; `search_design_system` against the component library before accepting a hand-built element that looks like it should be a known component
- [ ] **Auto Layout correctness inside the instance** — Fill/Hug set the way the component actually needs to grow (a card that should fill its grid cell but is set to Hug will silently collapse the moment its content gets shorter than its neighbor's)
- [ ] **Constraints correct for how the instance sits in its parent** — pinned/scaled the way the parent frame's own resize behavior requires
- [ ] **Component properties used, not simulated** — an `INSTANCE_SWAP` slot exists for exactly this purpose (icon swapping, per `component-to-figma.md` §3); a manually-swapped-and-recolored vector standing in for it is a component-property gap, not an acceptable one-off
- [ ] **Naming intact** — layer names that still read as the component's own naming convention, not renamed to something block-specific that breaks future scripted audits (`figma-to-component-scss.md` §5's extraction scripts locate nodes by name; a renamed layer silently breaks that pipeline for everyone downstream)
- [ ] **No missing variant the block actually needs** — if the block requires a state/breakpoint/style the component doesn't have, that's a real component gap, not something to fake with an override

**When the root cause is the component**, the fix is the same shape every time: correct the master (or add the missing variant/property), verify every existing instance across the file picks up the correction, and only then continue the audit on this block's instance. Never leave the master broken and "fix" only the one instance in front of you — that just relocates the same defect to the next block that reuses the same master correctly.

### Pass 4 — Design system review

Zoom out from this block's specific components to the token layer underneath all of them. Every visual property on the block should trace to a variable, not a locally-typed value — the Figma-side mirror of the same non-negotiable this framework enforces in SCSS (`wp-framework-block-builder` §8, `wp-block-design-philosophy` §0.1).

- [ ] **No hardcoded values where a variable exists** — spacing, color, radius, or type property set as a raw number/hex when a `Dimensions`/`Colors`/`Typography` (or component-scoped `Components`/`Components Colors`, per `component-to-figma.md` §2) variable already covers it
- [ ] **Correct collection for the value's scope** — a value that should vary by breakpoint bound to a single-mode collection (or vice versa); component-scoped values living directly in the global `Dimensions`/`Colors` collections instead of their own `Components`/`Components Colors` namespace (a hard rule already, per `CLAUDE.md`'s Figma non-negotiables)
- [ ] **Role-based naming, not value-mirroring** — a new component variable named after what it looks like (`Card/dark-blue`) instead of what it does (`Card/color-hover`) is a naming defect even if the binding itself is correct
- [ ] **Width/height never sharing one variable** even when currently equal — every dimension that could plausibly diverge later gets its own variable (`component-to-figma.md` §2)
- [ ] **`OPACITY`-scope variables stored as 0–100, not 0–1** — a `0.4` where `40` belongs resolves to a barely-visible 0.4% opacity in Figma, not 40%; this has been hit and re-hit across multiple components in this file, check it explicitly every time one appears
- [ ] **No orphan variables** — a variable referenced by this design with no live SCSS counterpart, or a variable that exists but nothing in the design actually binds to it
- [ ] **Auto Layout / constraints / Hug-Fill consistent with sibling blocks** solving the same layout problem — divergence here is exactly what produces "this block feels slightly off from the rest of the library" without any single obviously-wrong property
- [ ] **Local styles avoided** in favor of the shared collections — a locally-scoped text/effect style duplicating an existing shared one is the Figma-side equivalent of a duplicated SCSS mixin
- [ ] **Naming consistency** across the whole set of new variables this block introduced, matching the conventions already established (see `component-to-figma.md` §2's role vocabulary: `color-default`, `color-hover`, `color-active`, `border-*`, `background-*` — adapted to the states the component actually has, not invented symmetrically)

If Pass 4 finds systemic drift in the *Style Guide canvas itself* rather than in this block's bindings, check whether it's a live-binding gap first: per `CLAUDE.md`'s Figma non-negotiables, only `font-size`, colors, and Dimensions bars are live-bound on that canvas — `line-height`/`font-weight`/`letter-spacing`/`font-family` are one-time snapshots that go stale silently. Don't flag a stale canvas snapshot as a code-affecting bug; it isn't one (code reads variables directly). Flag it as a "run the refresh plugin" note instead (`.claude/skills/figma-sync/figma-plugin/`).

### Pass 5 — Production readiness

Ask whether this Figma design can become a real block via `wp-framework-block-builder` **without a structural redesign** — not whether it's pretty. Concretely:

- [ ] **Content elasticity** — does the design hold up with zero, one, and many repeater rows (philosophy §11)? A design built and screenshotted with exactly three items in a three-item grid, with no visible plan for a fourth, is a real production gap, not a hypothetical one
- [ ] **Image/video swappability** — does the layout survive a very different aspect ratio or a much longer/shorter caption than whatever placeholder content was used to generate it?
- [ ] **Editor-authorable, not designer-only** — every piece of visible content maps to a plausible ACF field (text, image, repeater row, button) that a real editor could actually fill in; a detail that only works because it was hand-placed in Figma (a decorative element positioned to avoid overlapping specific placeholder text) won't survive real content
- [ ] **Field-order mappability** — does the content hierarchy shown match the framework's canonical field order (overline → title → primary content → buttons → image/video → layout controls, `wp-framework-block-builder` §5), or is a deliberate deviation identifiable and justifiable per `wp-block-design-philosophy` §2?
- [ ] **Global-field resilience** — would this design still read correctly against a different `bg_color` and a `spacing_top`/`spacing_bottom` of `none`? Every block gets these as free-standing editor choices (`CLAUDE.md`'s ACF JSON tier 1) — a design that was only ever shown on one background hasn't actually been tested for this
- [ ] **No unnecessary complexity for the developer who builds it next** — a visual effect that would require inventing a new component-builder pattern (a new `parts_path()` delegation, a bespoke JS interaction with no precedent) should be flagged explicitly, not silently expected to "just get implemented somehow"
- [ ] **Cross-industry reusability** — could the same structure carry a completely different palette and copy for an unrelated industry (per `wp-block-design-philosophy` §15), or is it secretly one visual idea that only works for the specific placeholder content used to generate it?

A design that fails here isn't necessarily bad design — it may be a design that assumed static, hand-placed content. That's a legitimate finding at this pass even when Pass 1 gave it a clean bill on pure visual merit.

### Pass 6 — Library review

Compare against the framework as a whole, not just this one block in isolation.

- [ ] Run the **one-sentence silhouette test** (`wp-block-design-philosophy` §6) against every block already in `src/content/blocks/library/` and `custom/` — if two silhouettes read the same, this generation is a config toggle on an existing block, not a new one
- [ ] Check `.claude/design-references/INDEX.md` for prior art solving the same problem — a genuinely new archetype should be rare; most "new block" requests resolve to an existing archetype (Listing, split-hero, full-bleed CTA, trust/testimonial, per philosophy §6–§8, §13) with new content
- [ ] **Would two existing blocks (or this new one and an existing one) be better merged** into one block with a style/layout field, rather than living as separate near-duplicates?
- [ ] **Does this introduce a genuine gap**, or does it just grow the library's size without growing what it can actually do? A block count is not a quality metric — a smaller library where every block earns its place beats a larger one padded with redundant variants
- [ ] **Should a recurring visual element become a shared component instead of living inline in this block** — the same reuse-before-creation judgment as `wp-block-design-philosophy` §7, applied retroactively to what actually got built rather than what was planned

### Pass 7 — Systemic improvements

Don't stop at fixing this one block. Ask *why* the defects found in Passes 3–6 happened, and whether they're symptoms of something the framework itself should absorb.

Recurring-issue triggers worth naming explicitly (each has already happened at least once in this project, per `figma-sync/component-to-figma.md` and `figma-to-component-scss.md`):

| Recurring symptom | Likely systemic cause | Where the fix belongs |
|---|---|---|
| Same component keeps showing the wrong breakpoint variant | Component's Mobile/Desktop modes aren't discoverable/obvious at placement time, or the generation prompt doesn't specify breakpoint context | Component (add a clearer default) or the generation prompt/skill that produced the frame |
| Same class of hardcoded value keeps appearing | A needed variable genuinely doesn't exist yet | Variables — add it once, not per block |
| Same structural idiom (split-hero, full-bleed CTA, Listing grid) keeps getting reinvented slightly differently each time | The pattern isn't documented as a named, reusable shape | `wp-block-design-philosophy` — fold it in as a named lever/archetype, the way §4's asymmetric split-grid formula or §7's Listing macro-pattern already were |
| Same naming inconsistency across multiple audits | No enforced naming convention at generation time | The generation skill/prompt (`figma-generate-design`/`figma-generate-library`) or this skill's own checklist, if the convention exists but isn't being checked |
| Same manual-override-instead-of-component-fix pattern | The generation or a previous audit patched an instance instead of the master (§1) | Retroactively promote the fix to the component now, and note it here so it isn't repeated |
| Same category of defect keeps slipping through this very audit | This skill's own checklist has a blind spot | This skill (`wp-block-design-auditor` itself) — add the missing check |

**The "hit twice" rule.** This project already has a working convention for this, used verbatim in `component-to-figma.md`'s own closing checklist: absorb a pattern into durable documentation once it's been hit twice, not on the first occurrence (which might be a one-off) and not never (waiting for a "good enough" moment that doesn't come). Apply the same rule to audit findings — the first instance of a new defect class gets fixed and noted; the second instance is what earns it a permanent line in the relevant skill doc or `CLAUDE.md`.

Report systemic findings separately from per-block findings, addressed to whichever artifact should change: a component, `_variables.scss`, `wp-block-design-philosophy`, `CLAUDE.md`, the generation skill/prompt, or this skill itself.

---

## 3. Severity levels

Every finding gets exactly one level. Report all four fields for each — a severity label with no stated impact or fix location isn't actionable.

| Level | Meaning | Blocks approval? |
|---|---|---|
| **Critical** | Breaks the framework's non-negotiables (hardcoded color where `color.adjust()` is required, a bare number where `rem()`/`0` is required, a component used detached, a design that cannot become a real block without a structural rebuild) or actively produces a wrong/broken result | Yes, always |
| **Major** | Meaningfully undermines quality, consistency, or reusability, but the block is still functionally soundable (wrong default variant, missing component state, a real diversity-lever failure against an existing block, a production gap in content elasticity) | Yes, unless explicitly downgraded and justified |
| **Minor** | A real defect that doesn't compromise the block's core job (a slightly-off gap-scale rung, a naming inconsistency that doesn't break anything downstream) | No — "Approved with Minor Fixes" |
| **Suggestion** | An improvement worth naming, not a defect (a component that *could* generalize further, a pattern that *could* be promoted to a named archetype) | No |

For every finding, state:
1. **What it is** — the specific property/instance/variable, not a vague category
2. **Why it matters** — which framework principle or prior incident it violates (cite the section, e.g. "philosophy §1: page-assembly resilience," "`component-to-figma.md` §2: OPACITY scope")
3. **Impact** — what breaks, for whom, and when (an editor picking an unexpected `bg_color`; the next block that reuses this same component; a future rebrand)
4. **Fix location** — component / variable / pattern / block / instance, per the hierarchy in §1

---

## 4. Automatic corrections

Fix directly, without waiting for sign-off, when the correction is mechanical and the correct target value is unambiguous from the framework's own live state:

- Swap a wrong component variant for the correct one (wrong breakpoint, wrong default state)
- Rebind a hardcoded value to the matching existing variable
- Relink a detached instance back to its master (verify it doesn't silently drop legitimate per-instance content overrides first — check for those before relinking, don't just force it)
- Fix Auto Layout Fill/Hug or constraints to match the component's own established pattern
- Correct naming to match this project's established conventions
- Correct spacing to the nearest correct `$gap-*`/`Dimensions` rung
- Replace a duplicated hand-built element with the real component instance it was standing in for

**Always confirm before**, even when the fix seems obvious:

- Any change to a **component master** — it cascades to every other instance across the file, including ones this audit never looked at; the component-builder skill's own Mode 2 impact-check discipline (grep every call site before touching a shared PHP/SCSS component) has a direct Figma-side analogue here — before editing a master, account for who else uses it
- Adding a **new variable or new component variant** that doesn't exist yet — this is a design-system-level addition, not a mechanical correction
- Anything classified **Critical** or **Major** that requires a judgment call about intent (is the missing state actually needed, or does the block genuinely not need it?)
- Anything that would change the block's visible content or copy, as opposed to its structure/styling
- **Never actually redesign a layout** to fix a defect unless the defect makes the design fundamentally non-functional — a bad spacing choice gets corrected to the right token; it doesn't get an opportunity for the auditor to also reflow the whole grid because they'd have done it differently

**All corrections happen in Figma, on the design being audited — never in the codebase.** This gate runs before any PHP/SCSS exists for the block; there is nothing in `src/` to touch yet. Load `figma-use` (mandatory before any `use_figma` call) and be aware of the Plugin API gotchas in `figma-sync/component-to-figma.md` §4 while making corrections — a script's own read-back is not proof a fix landed (§4.6, §4.11), opacity-0 paints need re-verification after batch edits, and a `COMPONENT_SET`'s bounding box needs explicit resize-and-relocate after any manual repositioning.

**Never delete generated work to resolve a Reject verdict.** Rename or relocate the rejected frame (a clear prefix, or a move to a "Needs Rework" section) and write up the findings — the frame itself may still be useful reference for whoever revises it.

---

## 5. Final verdict

Exactly one of four, with reasoning:

| Verdict | Means | What happens next |
|---|---|---|
| **Approved** | No Critical/Major findings; Minor findings (if any) already auto-corrected | Ready for `wp-framework-block-builder` |
| **Approved with Minor Fixes** | Only Minor/Suggestion findings remain, auto-corrected or explicitly deferred with reasoning | Ready for `wp-framework-block-builder`; deferred items noted for whoever builds it |
| **Needs Revision** | At least one Major finding that requires a judgment call or new design-system asset (new variable, new component variant) before it's fixable | Back to the generation step (or a human) with the specific findings — not back to `wp-block-design-philosophy`, since the concept itself passed Pass 1/2 |
| **Reject** | A Critical finding, or a Pass 1/2 concept-level failure (redundant with an existing block, wrong silhouette, doesn't belong in the framework) | Frame renamed/relocated per §4; findings written up; route back through `wp-block-design-philosophy` if the concept itself is the problem |

State the verdict once, at the end, after all seven passes — not provisionally partway through.

---

## Mandatory checklist

No block passes this gate without all of the following being true:

- [ ] Pulled live state, not memory: current `_variables.scss`, current `get_variable_defs` for touched collections, current block/component roster (§0)
- [ ] Pass 1+2: `wp-block-design-philosophy`'s Mandatory design review checklist run against the *actual result*, plus the survivorship/five-years/legacy-shortcut tests
- [ ] Pass 3: every component instance checked against its master for variant/state/detachment/duplication/Auto-Layout/constraints/properties/naming; every component-level defect fixed at the component, not the instance
- [ ] Pass 4: every visual property traced to a variable in the correct collection with role-based naming; `OPACITY` scope checked explicitly wherever it appears; no orphan variables, no avoidable local styles
- [ ] Pass 5: content elasticity (zero/one/many), editor-authorability, field-order mappability, global-field (`bg_color`/`spacing_*`) resilience, and cross-industry reusability all checked against the real block-builder constraints
- [ ] Pass 6: silhouette compared against every existing library/custom block and `.claude/design-references/INDEX.md`; merge/redundancy question explicitly answered
- [ ] Pass 7: recurring-issue triggers checked against this and prior audits; anything hit twice written up for the relevant durable doc (component, `_variables.scss`, philosophy skill, `CLAUDE.md`, generation prompt, or this skill)
- [ ] Every finding carries all four fields: what, why (cited), impact, fix location (§1's hierarchy)
- [ ] Every finding carries exactly one severity level (§3)
- [ ] Auto-corrections applied only where §4 permits; anything master-level, new-asset, or content-changing held for confirmation
- [ ] No generated work deleted — rejected frames renamed/relocated, not removed
- [ ] Exactly one final verdict issued, stated after all seven passes, with its reasoning
