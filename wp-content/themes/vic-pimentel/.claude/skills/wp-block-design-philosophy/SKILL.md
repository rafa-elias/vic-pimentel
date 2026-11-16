---
name: wp-block-design-philosophy
description: Use before deciding what a new block should look like and contain — its structure, hierarchy, composition, and whether it should exist at all — whether starting from a verbal brief, a wireframe, a screenshot, or a Figma frame. Also use to judge whether a proposed or finished block is genuinely diverse, reuses the right components, and is production-ready. This is the design-thinking layer: it does not cover PHP/ACF/SCSS implementation (see wp-framework-block-builder), component internals (see wp-framework-component-builder), or Figma tooling (see the figma-sync skills) — it covers the reasoning that has to happen before and around that work.
---

# Block design philosophy — rear-theme framework

This skill teaches how to think like the designer of this framework, not how to build in it. Everything mechanical — file layout, `config.php`, ACF JSON, `component()` calls, SCSS scaffolding — belongs to `wp-framework-block-builder` and `wp-framework-component-builder`. Those skills tell you *how* to execute a decision. This one tells you *which* decision to make: what hierarchy a block should express, whether it's structurally different enough from what already exists, whether a component should be reused or created, and whether the result will still make sense on a page you haven't seen, edited by a client you haven't met, next to blocks you didn't design.

Run this skill's reasoning **before** the block-builder's §0 plan-confirm gate — it's the thinking that produces the field list and structure you present at that gate, not a replacement for it. Run it again, as a review lens, after a block is built and before it's called done.

## Two sources of evidence, and which one wins

This skill draws on two different bodies of evidence, and they are not equally authoritative:

1. **The framework itself** — this theme's own token system (`_variables.scss`), its skill docs, its `library/`/`custom/` component tiers. This is the canonical design system. It always wins on conflict.
2. **`.claude/design-references/`** — real, production-quality blocks pasted in verbatim from four real client projects spanning several years: `apcbrh` (a Brazilian dairy-farmers' trade association, institutional/civic), `borealis` (a Brazilian travel/tourism operator), `detal-tech-expert` (a Brazilian B2B industrial-equipment manufacturer), and `southquest-safaris` (an English-language safari/tourism operator). These are evidence of what actually works for real businesses, real content, and real editors — but they predate this framework, were built by different people with different discipline, and were never required to follow its token system. Treat them as **corroborating evidence for design principles** (hierarchy, composition, sequencing, business communication), never as a source of literal values, variable names, or copy-pasteable code.

**The arbitration rule:** whenever a production reference contradicts the framework's own system — a hardcoded color where a semantic token exists, a layout habit the framework has since improved on — classify the conflict before letting it influence anything:

- **(A) a weakness of the old work that should be corrected**, not repeated
- **(B) a limitation specific to that project's tooling/timeline**, irrelevant elsewhere
- **(C) a client-specific exception**, valid there, not a general rule
- **(D) a genuinely better decision** that the framework didn't have yet

Only **D** gets folded into this skill as a principle. A and B and C are noted below only as calibration — what *not* to reverse-engineer — because a production block being "real" doesn't make every decision in it a lesson. Where the evidence is clear and repeats across independent projects, it's cited by name (`client-name/block-name`, matching the citation convention already used in `.claude/design-references/README.md` and `INDEX.md`) so it can be checked, not taken on faith.

---

## 0. Six things this framework has already decided

Read these as load-bearing constraints, not stylistic defaults. Every section below expands on one of them.

1. **Tokens over literals, always.** Every spacing, type, and color decision routes through `fluid()`, `rem()`, the gap scale, the typography scale, or a semantic color alias — never a raw px/hex chosen because it "looked right" in one spot. A design decision that can't be expressed as an existing token is a decision to reconsider, not an excuse to hardcode.
2. **Structure is decided before pixels are.** The SCSS scaffold modes in the block-builder skill (`no-design`, `design-reference`, `screenshot-agency`) all assume the HTML skeleton and component choices are settled first, with only *values* (which scale, which breakpoint, which gap) left as `// TODO: confirm` guesses. Design thinking here means committing to structure with confidence and leaving numbers provisional — never the other way around.
3. **Field order encodes reading order.** Every block's ACF fields follow one canonical sequence (overline → title → primary content → buttons → image/video → layout controls). That order isn't administrative tidiness — it's the framework's default rhetorical shape, and it's the first thing to reason about for any new block (§2).
4. **Blocks are assembled by editors, not laid out by designers.** `bg_color` and `spacing_top`/`spacing_bottom` are global ACF fields on *every* block, freely chosen per instance. A block's design is only half-finished if it only looks right against one imagined neighbor (§1).
5. **Mobile is the base case.** Grids collapse to one column by default and expand at `>=tablet`; two-column layouts put the image first in the DOM so it appears first on mobile regardless of desktop order. Nothing here is "desktop design, then adapted" — it's built from the single-column case upward.
6. **Production confirms the discipline, not just the wireframes.** Four independent real-client codebases, surveyed block-by-block for this skill, show 94–100% adoption of `rem()`/`fluid()`/`@include typography()` across their SCSS — this isn't a rule invented for a sparse wireframe library, it's how real, paid, shipped work in this business actually got built. The one soft spot across all four is color: legacy per-project palette variables (some no longer even declared anywhere), a couple of hand-typo'd variable names, and a small number of deliberate raw-hex escape hatches for editor-controlled colors (see §16). Where the *current* framework has since gone further than any of those four projects ever did — the semantic `$gap-*` scale, which none of them used, all preferring raw `rem()`/`fluid()` pairs chosen per block — that's a framework improvement to keep using, not a gap to "fix" by reverting to the older, looser habit.

---

## 1. Designing for the page, not just the block

This is the constraint most generic design advice misses, because most design systems don't hand every block an independent, editor-chosen background color and vertical spacing. This one does — `bg_color` and `spacing_top`/`spacing_bottom` are global fields (`CLAUDE.md`'s "Three tiers of field groups," tier 1) appended to every block automatically. That has real design consequences:

- **Never design a block that only reads correctly against one assumed background.** A block must hold up on any of the palette's background roles an editor might pick — at minimum a light and a dark one. This is why components lean on the paired semantic tokens `$text-1`/`$text-2` (text on light / text on dark) rather than one hardcoded text color: the component is carrying the adaptability the page-assembly model requires.
- **Never assume what sits immediately above or below.** A hero-style block with a dramatic top margin baked into its own markup (rather than relying on the shared `spacing_top` field) will look broken the day an editor stacks it directly under another full-bleed block with `spacing_top: none`. Vertical rhythm between blocks is the editor's lever (`spacing_*`), not something a block should try to control unilaterally by padding around its own wrapper.
- **Internal whitespace is yours; external whitespace is the editor's.** Everything inside `<div class="wrapper">` is the block's design responsibility. Everything outside it — the gap to the next block, the section background — is deliberately handed to whoever assembles the page. Respect that boundary: it's what lets the same block work in ten different page contexts without a rebuild.
- **A block is one instrument in a page's rhythm, not the whole score.** A page of five consecutive full-bleed dark hero-style blocks reads as monotonous; a page alternating light/dark, dense/sparse, static/sliding sections reads as paced. You can't control what an editor puts around your block, but you *can* make sure your block is legible as a distinct beat — which is exactly what §6 (diversity) is for, and what §14 (sequencing whole pages) makes explicit at the page level.

---

## 2. Content hierarchy and reading order

The recurring field order — overline, title, primary content, buttons, image/video, layout controls — is the framework's default narrative shape, and it maps directly onto a reading order:

| Position | Field | Rhetorical role |
|---|---|---|
| 1 | `overline` | Orientation — tells the reader what category of claim is coming, before the claim itself |
| 2 | `title` | The hook — the one sentence a skimming reader should walk away with |
| 3 | primary content (wysiwyg/repeater) | The explanation — earns the claim made by the title |
| 4 | `buttons` | The conversion moment — what the reader does now that they're convinced |
| 5 | `image_video` | Proof or emotional reinforcement — supports the words, doesn't replace them |
| 6 | layout controls | The editor's presentation choice — orientation, count, style — layered on top of a settled structure |

Default to this order for any new content-led block. **Deviate deliberately, not by omission**, when the content itself has a different natural voice:

- **Testimonials lead with the quote, not the headline** (`testimonial-1`; confirmed by production in both `borealis/slider-testimonials` and `southquest-safaris/video-testimonials`) — because the rhetorical weight of a testimonial is *someone else's words*, and putting a marketing headline in front of them undercuts the credibility the block exists to borrow. Name, role, and photo follow as provenance, not as the lead. See §13 for the two real sub-treatments this takes.
- **Stat blocks lead with the number** (`headline-stats`; confirmed by `apcbrh/grid-numbers`, an independently-built block with the same "number is the message" structure) — the number *is* the message; an overline and a one-line description exist only to contextualize it, not to compete with it for the first glance.
- **Not every "hero" carries the same rhetorical job — decide which one this is before writing the field list.** Across all four production projects, "hero" consistently splits into two distinct jobs that get different content-hierarchy treatment: a **conversion hero** (homepage/landing-page moment — title, supporting copy, a highlights/callout panel, and a CTA; e.g. `southquest-safaris/hero-tour`, `southquest-safaris/hero-accommodation`, `apcbrh/hero-servico`, `apcbrh/hero-associe-se`, `detal-tech-expert/hero-produto`) versus a **wayfinding banner hero** (interior/secondary-page moment — image and overlay only, sometimes literally nothing but a title, no CTA, no content card; e.g. `southquest-safaris/hero-destination`, `apcbrh/hero-image`, `detal-tech-expert/hero-image`, `detal-tech-expert/hero-blog`). Don't give a banner hero a full content-hierarchy treatment it doesn't need (no CTA, no highlights panel — its only job is orientation and brand continuity), and don't starve a conversion hero of the split/CTA structure by copying the banner's restraint. See §8's worked example for the composition difference.
- **A block with no natural "headline" content (a raw feature grid, a bare CTA) can validly drop the title/overline pair** — but say so explicitly when proposing the field list, don't silently produce a block that has no orienting text at all.

Match the reading-order decision to how the content will actually be scanned: a sparse, high-impact block (hero, single stat, single quote) is read center-out or in one Z-sweep; a denser block (a repeater of items, a multi-paragraph section) is read top-to-bottom, left-aligned, F-pattern-style. Choose alignment and grouping (§4) to reinforce whichever scan pattern the content density implies — don't force a dense repeater into a hero's centered, single-focal-point treatment.

---

## 3. Visual hierarchy, contrast, and typographic rhythm

The fluid type scale (`_variables.scss`) already encodes a hierarchy — `$display-*` > `$heading-1` > `$heading-2` … > `$heading-6` > `$overline-*` > `$p-*` — so the design decision is never "how big should this be," it's "which rung of the existing ladder does this content occupy, and why." **This section is rhetorical guidance for reasoning about a block with no design reference yet (or for judging one), not a substitute for checking an actual Figma design.** When a real Figma frame exists, the token for any given text node is whatever that frame's own text style is bound to (read it via `get_design_context`/`get_variable_defs` — see `wp-framework-block-builder` §9) — never assumed from the role names below, and never copied from what an existing block in this codebase happens to do, since that block may have been built against an older design state. Designers rebind text styles over a project's life; treat every "X is always token Y" statement below as "X is *typically* Y, absent other information," not a fixed rule.

- **Overlines are typically the smallest heading-adjacent scale** — currently `$overline-regular`/`$overline-large` in this project's token set, never a `$heading-*` token (that was an earlier, now-stale convention — see `wp-framework-block-builder`'s Typography section for the incident). An overline that competes visually with the title it precedes has failed its job — it's a whisper before the statement, not a second statement. (One production counter-case worth knowing about, not imitating without cause: `detal-tech-expert/hero-produto` sets its overline at `$display-4` in a bold brand-accent color, uppercase — a loud eyebrow. That works there because the whole hero is a single immersive brand moment with almost no other competing text; it is not evidence that overlines should generally compete with titles. Treat it as a rare, deliberate exception (§16), not the rule.)
- **The block's main headline is usually `$heading-2`** (the block-builder skill's own default) because it's calibrated to sit below a page's `$display-*` hero text but above every other heading in the block — reach for `$heading-1` only when the block itself *is* the hero moment (a banner, a big standalone number), and `$heading-3`–`$heading-5` for secondary/nested headings inside a block that already has its own primary title.
- **A number used as content (`headline-stats`) claims the largest scale in the block** (`$heading-1` or a `$display-*`), even though it's not literally a "heading" field — visual hierarchy tracks importance to the reader, not field type.
- **Contrast is a hierarchy tool, not just a legibility requirement.** Weight and size create one axis of contrast (bold `$heading-2` against regular `$p-medium`); color creates another (`$text-1` full-strength body copy against a muted tertiary/`$text-3` caption); and isolation — surrounding a single element in more whitespace than its neighbors get — creates a third. A lone stat number gets its emphasis as much from the emptiness around it as from its font size.
- **Rhythm is the gap scale, used as vocabulary, not as arbitrary numbers.** This is one place the current framework has genuinely moved past its own production history: none of the four real client codebases surveyed used a semantic gap scale at all — every one of them chose raw `rem()`/`fluid()` pairs per block, by feel, block by block. That's not a pattern to reproduce; it's exactly the drift the `$gap-*` scale exists to prevent. Use the named vocabulary:

| Token | Use it for |
|---|---|
| `$gap-nano` | The tightest pairings — an icon glued to its label |
| `$gap-small` | Overline-to-title pairing; anything meant to read as one visual unit |
| `$gap-regular` | Small intra-group spacing, one step looser than nano/small pairs |
| `$gap-medium` | Space between related-but-distinct groups inside a block (header vs. body vs. bottom bar) |
| `$gap-large` | Space between a block's own major layout areas (header vs. the grid/slider below it) |
| `$gap-jumbo` | Deliberately cinematic separation — reach for it rarely, when a block wants to feel spacious on purpose |

Choosing the wrong rung is a real design bug, not a rounding error: using `$gap-large` between an icon and its label reads as "these are unrelated," even if every other signal (shared color, alignment) says otherwise. Let spacing tell the truth about which things belong together.

- **A narrow set of raw-pixel exceptions is legitimate, not a discipline failure.** All four production codebases independently converge on the same two carve-outs, hundreds of times over: hairline borders (`1px`/`2px solid $token`) and small fixed UI chrome — scrollbar thickness, slider pagination-dot sizing (consistently `~18px`/`~8px` across unrelated projects). These are sub-pixel/fixed-hardware-affordance details, exactly the category the framework's own `rem(px)` guidance already carves out for "sub-pixel precision" and "fixed size that respects user font preferences" — production just confirms it's a real, recurring category, not an invented one. It does not extend to section padding, gaps between content groups, or anything that should visually scale with the viewport — those stay on `fluid()`/`rem()`/the gap scale, no exceptions.

---

## 4. Composition and balance

Four blocks studied for this skill's first version — `cta-banner-1`, `content-image-1`, `headline-stats`, `testimonial-1`/`carousel-1` — demonstrate four genuinely different compositional strategies already present in this codebase. Use them as reference silhouettes, not templates to copy structurally. Production evidence across all four client projects confirms and sharpens the same underlying principles with real, load-bearing techniques:

- **Symmetry communicates equivalence; asymmetry communicates emphasis.** `headline-stats` puts three equal-weight stat cards in a symmetric grid because no single stat should outrank the others. `cta-banner-1` puts an unequal image/content split (and lets the split flip via an `image_position` toggle) because one side — usually the image — is meant to dominate and pull the eye first. Don't default to a 50/50 split "for balance" when the content has a clear lead element; don't default to asymmetry for a set of genuinely equal items.
- **The confirmed real-world formula for an asymmetric split is a fixed-ratio side column, not an arbitrary fraction.** Every production project studied independently converged on the same grid shape for split hero/content blocks: `grid-template-columns: minmax(0, 1fr) fluid(N, M, 'tablet')` (a flexible primary column plus a fixed-but-fluid secondary column, sized only at `>=tablet` since it's meaningless below that width) with a `.right`/`order`-based modifier flipping which side carries which content. Confirmed independently in `southquest-safaris/hero-tour` (`fluid(286, 316, 'tablet')`), `southquest-safaris/hero-accommodation` (`fluid(325, 385, 'tablet')` with `order: -1`), `borealis/hero-hotel` (an equal-column variant for content that's genuinely balanced rather than asymmetric — `repeat(2, minmax(0, 1fr))`), and this framework's own `cta-banner-1` (`fluid(340, 400, 'tablet')`). When a block needs an asymmetric two-up layout, this is the formula to reach for — not a guessed percentage split.
- **Visual weight must be balanced across the axis it's split on**, not just area. A large photo on one side is answered by generous internal padding and confident type on the other — not by cramming in more text to "fill the space." Empty space carries weight; use it as the counterbalance instead of manufacturing more content.
- **Alignment is a grouping signal.** A shared left edge between an overline, a title, and body copy tells the reader "these belong to one reading column" without a single border or divider. Centered alignment (`headline-stats`) tells the reader "each item stands alone, judge it individually." Pick alignment to match §2's scan-pattern decision, not by default habit.
- **Proximity groups; distance separates.** The icon-heading pairing in `cta-banner-1` (`$gap-small`) versus its distance from the body copy below (`$gap-medium`) is the whole grouping mechanism — no visual container needed. Prefer spacing-based grouping over adding a border/background box whenever spacing alone can do the job.
- **Every item in a repeated set shares identical structure.** Asymmetry and hierarchy decisions belong at the block level (this stat block vs. that hero block); inside a repeater, every stat card, every carousel slide, every accordion item must be structurally identical to its siblings. A repeater where item 2 has an icon and items 1 and 3 don't isn't asymmetric design — it's a bug in the content model.
- **Visual anchors give the eye a starting point.** A hero image, a giant number, a pull-quote, an icon — pick exactly one thing per block that's allowed to be the biggest, boldest, or most colorful thing on screen, and let everything else be visibly secondary to it. A block with three co-equal "loudest" elements has no anchor and reads as noise.

---

## 5. Whitespace as an intentional tool, not leftover space

The vertical rhythm tokens (`$vertical-default`, `$gap-large`, `$gap-jumbo`) exist because generous space is already a deliberate design decision in this system — not a placeholder waiting to be filled once "real content" arrives.

- **Don't mistake intentional emptiness for an unfinished block.** A stat card with one number and one short line of description, surrounded by the same whitespace as its neighbors, is the finished design — not a block waiting for more copy. Resist the urge to add a border, icon, or extra paragraph just because the block "looks empty" during review.
- **Whitespace must compress, never vanish, on mobile.** The gap and vertical tokens are `fluid()` precisely so spacing scales down at small viewports instead of collapsing to zero — a mobile layout that's merely tighter, not cramped, is the goal.
- **Whitespace is part of the visual-anchor mechanism (§4).** The space immediately around a hero image or a big stat number is what signals "look here first" — treat it as a component of the design, sized deliberately, not as the remainder left over after placing everything else.
- **How much whitespace reads as "premium" is industry-dependent, not universal — see §15.** The two travel/consumer projects studied lean on the most generous whitespace of the four; the B2B technical project deliberately trades whitespace for density where density itself is the credibility signal. Don't import one project's whitespace habit into a block for a different kind of business without asking whether restraint or density is what that audience actually reads as trustworthy.

---

## 6. Diversity — building genuinely different blocks

The failure mode this section exists to prevent: a new block that's really the same block with elements shuffled — `content-image-3` that's `content-image-1` with a different `gap-medium` and a border-radius. That's a config toggle on an existing block (an `image_position`/`style` field), not a reason for a new numbered variant.

Genuine diversity comes from changing one or more of these levers — not from moving boxes around inside the same skeleton:

| Lever | What it changes | Framework example |
|---|---|---|
| **Hierarchy** | Which element is the reader's entry point | `headline-stats` makes the number the entry point; `cta-banner-1` makes the image the entry point; `testimonial-1` makes the quote the entry point |
| **Composition** | The block's overall silhouette | split-screen grid (`cta-banner-1`) vs. centered symmetric column (`headline-stats`) vs. edge-bleeding slider (`testimonial-1`, `carousel-1`) vs. vertical stacked list (`accordion-1`/`-2`) — four structurally distinct shapes on the page |
| **Layout** | The container discipline | CSS grid vs. Swiper slider vs. stacked/collapsible list vs. absolute-positioned overlay (`cta-banner-1`'s title-wrapper over its image) |
| **Rhythm** | Pacing of content delivery | one big idea at once (hero) vs. several small facts side by side at once (stats, carousel) vs. progressive disclosure, one item open at a time (accordion) |
| **Imagery** | Media's role, if present at all | full-bleed background with overlay text (`cta-banner-1`) vs. supporting side panel that never overlaps text (`content-image-1`) vs. small identity photo as provenance (`testimonial-1`) vs. no imagery at all, pure typography (`headline-stats`) |
| **Alignment** | How items sit relative to each other | centered/equal (`headline-stats`) vs. left-column two-up (`content-image-1`) vs. edge-to-edge (slider blocks) |
| **Density** | How much is on screen simultaneously | one message (hero) vs. many facts at once (stats/carousel) vs. one fact at a time by design (accordion hides the rest) |
| **Interaction** | Whether the reader must act to see more | static (`content-image-1`) vs. swipe-driven (`testimonial-1`, `carousel-1`) vs. click-to-expand (`accordion-1`) — each changes reader agency and pacing |
| **Emphasis** | What's allowed to be the single biggest thing on screen | a photo (banner), a number (stats), a quote (testimonial), a headline (text blocks) |

This library's four silhouettes are a starting toolbox, not the ceiling — production evidence across four real projects shows a wider range of genuinely distinct archetypes worth having in mind when reaching for a new lever:

- **Full-bleed cinematic background** (§8) — an immersive, layered image/video backdrop with overlay text, distinct from `cta-banner-1`'s split treatment (`detal-tech-expert/hero-produto`, `apcbrh/hero-video-content`, `southquest-safaris/hero-destination`).
- **Split-with-callout** — the two-column formula from §4 with a distinct highlights/callout panel on the fixed side rather than plain copy (`southquest-safaris/hero-tour`'s `box-highlights`).
- **Listing/grid-of-cards with a filter** (§7) — a query-driven, potentially-filterable grid of a CPT's posts, structurally different from a hand-authored repeater grid like `headline-stats` (every listing block across all four production projects).
- **Comparison/spec table** — a dense, tabular, industry-specific archetype for decision support rather than persuasion (`detal-tech-expert/listing-comparador`, `produto-especificacoes`) — see §15 for when this is (and isn't) worth generalizing.
- **Sticky in-page navigator** (§14) — a persistent anchor menu for long single-item pages, structurally unlike any content block (`borealis/menu-roteiro`, `southquest-safaris/side-links-content`).
- **Chip/pill cloud** — many short claims as equal-weight badges rather than a card grid or a list, useful when items are single words/short phrases with no supporting body copy (`apcbrh/title-balloons`).

**Before finalizing a new block, name its silhouette in one plain sentence** ("a centered column of three equal stat cards with no imagery") and compare that sentence against every existing block's silhouette. If two sentences are essentially the same, the new block isn't earning its own name — pull a different lever from the table, or fold the idea into the existing block as a variant field.

**The corollary rule for when a new block is *not* justified:** if the proposed difference can be produced by an existing block through a field value — an `image_position` toggle, a `style` choice, a different repeater row count, a layout `button_group` — it is not a new block. Extend the existing one (an additive field, per the block-builder skill's edit-impact classification) instead of forking a near-duplicate.

---

## 7. Component philosophy — when to reuse, extend, or create

`wp-framework-component-builder` covers the mechanics (tiers, `component()` contract, Mode 2 impact checks). This section is the judgment call that precedes calling that skill.

Work through these in order for any UI piece a block needs:

1. **Does a `style-guide/` component already do this generic thing?** `headline`, `buttons`/`button`, `image-video`/`image`, `rich-text`, `accordion`, `tabs`, `modal`, `pagination`, `swiper-controls`, `social-media` cover the recurring building blocks of this system. Use it as-is before considering anything else. Production confirms this discipline is worth keeping, not just aspirational: across all four client projects, CTAs are almost never bespoke per block — they're routed through one shared buttons/button component, even in codebases with no other formal component tiering.
2. **Does it almost fit, needing one new optional argument?** That's additive-structural work on the existing component (`wp-framework-component-builder` Mode 2), not a new component. A component that needs a style variant gets a `style` arg and a corresponding SCSS modifier (`&.style-primary`) — never a copy-pasted sibling file for the sake of one different color.
3. **Is the need genuinely project-specific and reused across more than one block or section?** That earns a `custom/` component (`header`, `footer`, `mega-menu`, `card-material`) — but only once actual reuse exists or is clearly imminent; don't manufacture a component preemptively for a single caller with no reuse horizon (mirrors the design-references skill's rule about not promoting a shared folder before a second use case exists).
4. **Is it a one-off visual detail this exact block will ever need** (a decorative shape unique to one hero, a wrapper div that only ever has one caller)? Keep it inline in the block's own markup and SCSS. A component with exactly one caller and no plausible second caller is overhead, not reuse.
5. **Was it built as an exploration but not chosen for this project?** `library/` tier — the source stays as prior art, its compiled assets are excluded from the enqueue automatically.

**Compose downward instead of duplicating markup.** `image-video` already routes between `image` and `video-player`; `buttons` already wraps repeated `button` calls; `mega-menu` already reuses `tabs`. When a new block's need looks like "almost `image-video`, but…", the fix is very likely an argument on `image-video`, not a parallel media-handling block of markup written inline.

**The same judgment applies to tokens as to components.** Reaching for a semantic alias (`$background-6`, `$line-1`, `$gap-medium`) before inventing a new raw value is the token-system equivalent of reusing a component before forking one. A new one-off gap value or hardcoded hex in a block's SCSS is exactly as much a smell as a duplicated component file — it's untracked, un-reusable, and will drift the first time the palette or scale is retuned for a new project.

### The Listing archetype — a recognized macro-pattern, not four unrelated blocks

Every production project studied, independently, converged on the same shape for "browse a CPT's posts": a centered header (title + a body copy line capped to a comfortable reading width), a responsive card grid escalating column count by breakpoint (1 → 2 → 3, sometimes → 4), one shared card component reused by every card in the grid, and an explicit empty/no-results state. This exact shape recurs for blog posts, hotels, tour packages, association members, industrial products, and equipment — completely unrelated content, identical template. Treat "Listing" as a single reusable macro-pattern (header → filterable grid → shared card → empty state), not a one-off per content type, and reach for the CPT card guidance in the block-builder skill (§12) when building one. One real variation worth keeping in the toolbox: when the item set is curated/ordered rather than an open-ended browse (a short "related tours" or "our destinations" set rather than a full catalog), a grid that would get too tall on mobile can legitimately swap to a slider under a breakpoint instead of forcing a single column — confirmed in production (`borealis`'s destination/roteiro grids hide the grid and reveal a Swiper carousel below `tablet`).

### Repeated text rows: a repeater is not the default — ask what the data actually needs

Before giving a block a repeater field for anything that looks like a list (a checklist, a set of bullet points, short feature callouts with no icon/link/image variation per row), check whether the content has any real structured sub-fields first. If every row is just a string with no independent style, link, or media choice, a repeater is unnecessary machinery: it needs its own field group entry, its own render loop, and (per the block-builder skill's parts rules) sometimes its own markup — all to reproduce what a single `wysiwyg` field's native `<ul>` already does for free, with the editor's familiar list-button UI instead of an "Add row" button per line. Style the list via the block's own SCSS scoped under `.rich-text ul` (never the shared `rich-text` component file, which every other block also uses) to match the design's bullet/icon treatment.

Reach for a repeater instead only when rows actually vary structurally — a per-row icon choice, a per-row link/button, a per-row image, or reordering/adding rows independent of surrounding prose. A generic "3-5 short checklist lines under a paragraph" is the flat-text case far more often than not (confirmed while building `tabs-2`'s Vertical archetype: its Figma checklist is plain text rows with a repeated check glyph and no other row-level variation — a repeater was the wrong default, corrected to a `<ul>` inside the existing `content` wysiwyg field after the fact). When unsure which case applies, ask at the block-builder skill's §0 plan checkpoint rather than defaulting to whichever pattern a neighboring block already happens to use.

### Card hover affordance — evidence updates the default

The component-builder skill's example hover treatment (`transform: scale(1.08)` image zoom + a darkening overlay) is a reasonable placeholder, but it is **not** what any of the four real production projects actually do — zero of them scale/zoom a card's image on hover. Two real, validated alternatives repeat instead, and the choice between them tracks content type, not client:

- **Flat opacity-dim** (`&:hover { opacity: 0.7–0.8; }`, no transform) — used across nearly all editorial/browsable cards (blog posts, destinations, hotels) in both `borealis` and `southquest-safaris`. Communicates "this is clickable" without competing for attention.
- **Colored overlay revealing a CTA pill** — a tinted scrim fades in over the image on hover, revealing a small pill-shaped action button (e.g. "Book now") — used specifically for bookable/actionable inventory (`southquest-safaris/card-tour`, `card-accommodation`). Reserve this heavier treatment for cards whose whole point is "click to transact," not for general content browsing, where it would be a jarring surprise.

When designing a new card, choose flat opacity-dim as the default for content browsing and the CTA-reveal overlay only when the card's job is a direct transaction — and don't reach for an image zoom transform as the default; it's unvalidated against real production outcomes.

---

## 8. Full-bleed background media — a validated composition archetype

Distinct from the split-column formula in §4, a second composition archetype recurs independently across every production project studied: a full-bleed image or video backdrop, layered under overlay(s), with content positioned on top via z-index rather than sitting in normal document flow. This is the structure behind conversion heroes (`detal-tech-expert/hero-produto`), CTA banners (`detal-tech-expert/cta-banner`), and immersive video moments (`apcbrh/hero-video-content`, `apcbrh/full-background-video`, `southquest-safaris/hero-video-spotify`).

- **The layering is always the same three parts**: a background layer (`@include fit` image and/or `video-player`, optional dark/tinted overlay for legibility), then content pinned above it with `z-index`, then (sometimes) a further overlay purely for contrast. Reuse this shape rather than inventing a new one when a block calls for an immersive backdrop.
- **Art-directed mobile images are a real, repeatable technique, not a one-off.** A wide desktop background shot often needs a different crop on mobile to keep its subject legible — confirmed independently in `detal-tech-expert/hero-produto`, `detal-tech-expert/cta-banner`, and `apcbrh/hero-image`: a `has-mobile-img` modifier class toggles two separate image fields (desktop/mobile) by breakpoint via plain `display: none`/`block`, rather than relying on `object-fit` alone to rescue a poor crop. Offer this as an optional second image field on any full-bleed hero/CTA block where the source photography is wide and subject-heavy, not as a mandatory field on every block.
- **The overlay direction is a deliberate legibility choice, and there are two validated approaches** — see §16's worked example for when to use which.
- **A full-height or near-full-height background block must account for a sticky/transparent header, using the tokens that already exist for this.** `detal-tech-expert/hero-produto` pads its top by `fluid(110, 170)` specifically to clear header overlap; this framework already ships `$header-height` and `$header-height-sticky` tokens in `_variables.scss` for exactly this purpose (confirmed as a live, still-relevant concern by `borealis/menu-roteiro`'s `top: $header-height-sticky` on its own sticky nav bar, §14). Reach for those tokens rather than guessing a top-padding value from scratch.

---

## 9. Accessibility as a design decision, not just an implementation detail

- **Image-first DOM order in two-column layouts** (already a hard rule in the block-builder skill) is a reading-order decision as much as a technical one: a screen reader and a mobile reader both encounter content in the same order a sighted desktop reader's eye would naturally take on a stacked layout. Decide DOM order at design time, not as an SCSS afterthought.
- **Never design assuming one background.** Because `bg_color` is editor-chosen (§1), a design that only maintains readable contrast against one specific background is an accessibility failure waiting for an editor to trigger it — verify the design's text/background pairing holds for both a light and a dark selection, using the semantic `$text-1`/`$text-2` pairing rather than a single fixed color.
- **Interactive affordance must be visible, not color-only.** Sliders and accordions need a legible, always-visible interaction cue (`swiper-controls`, a toggle icon) — a design that signals "you can click/swipe here" purely through a subtle color shift is incomplete.
- **Not every block needs its own heading tag.** Heading hierarchy (one `h1`, sensibly nested `h2`/`h3`s) is a page-level SEO/accessibility concern, not a per-block one — a trust/supporting block (testimonial, rating, social proof) is correctly built with no `h1`–`h6` at all when its content isn't primary page content. Use the semantically correct element instead (`<blockquote>` for a quote), and lean on ACF WYSIWYG fields with `"toolbar": "basic"` for that copy — it excludes the heading-format dropdown, so an editor can't accidentally inject a stray heading into the outline. Only worry if a whole page ends up with no real heading structure anywhere, which is a page-assembly review, not a reason to force a heading into every block.
- **Motion is the industry norm here, confirmed at scale — and still not load-bearing.** The `ember-entrance`/`ember-entrance-group` scroll-reveal wrapper isn't a rare accent in real work: it appears on nearly every block template across all four production projects (29 of 29 in `borealis`, 33 of 35 in `southquest-safaris` — the two exceptions being a pure above-the-fold hero and a sticky functional nav, both of which correctly have no need for a scroll reveal). Treat scroll-entrance as the expected default for a new block's outer wrapper, not an optional flourish — but the accessibility requirement doesn't change: a block's content and hierarchy must be fully legible with the animation disabled or skipped, exactly as the block-builder skill's §12 hover-affordance guidance treats a hover effect — a nice-to-have layer, never a substitute for static visual hierarchy. One gap worth naming honestly: none of the four surveyed production codebases appeared to gate this animation on `prefers-reduced-motion`. That's a real limitation in the reference material (§16, classification A), not a pattern to repeat — build new reduced-motion respect in rather than copying its absence.

---

## 10. Responsive thinking

- **Mobile is the base case, not a breakpoint.** Every layout decision starts from the single-column/stacked state and adds structure at `>=tablet` and above — never the reverse. Multi-column grids (`headline-stats`'s 3-up stats, `cta-banner-1`'s content split) collapse to one column by default and only gain columns behind an explicit `@include media('>=tablet')`.
- **`fluid()`'s default range (`phone-small` 360 → `desktop-small` 1440) is the right default for anything that should scale continuously across the whole viewport range** — text sizes, standard vertical rhythm. Reach for a custom breakpoint pair only when a layout genuinely changes *shape*, not just size, at a specific width — e.g. a grid column width that's meaningless below `tablet` because the grid itself doesn't exist yet below that width.
- **Sliders are a legitimate responsive strategy for dense content, not just a desktop flourish.** A carousel (`carousel-1`) or a testimonial slider handles content density that would otherwise force an awkward, ever-taller stacked grid on mobile — when density and mobile height are in tension, consider whether a slider is the more honest responsive answer than a forced multi-column grid squeezed into one column. Production confirms this isn't hypothetical: `borealis`'s destination/roteiro grid blocks literally hide the grid and swap in a Swiper carousel below `tablet` (§7).
- **A static Figma frame for a slider/carousel archetype only ever shows one snapshot state — it does not, by itself, specify what happens with a different item count, at the loop boundary, or when a secondary synced element (a pager, a year-rail, a thumbnail strip) doesn't fit the container.** A 4-item mockup evenly spread across the frame doesn't tell you whether the design intends "always exactly this many items" (in which case a fixed non-scrolling layout that happens to become invalid with more items is a real content-model conversation to have, not a bug) or "this many items happened to be in the example, but the pattern must hold for any count" (in which case the layout needs to gracefully spread-when-it-fits and scroll-when-it-doesn't). Guessing wrong here doesn't look like a typo — it looks like a *structurally* different, over-engineered implementation (e.g. reaching for a second Swiper instance with a Controller-based sync to make a simple 4-item rail "slideable," when the archetype only ever needed a plain flex row with `justify-content: space-between` plus native scroll for the overflow case — confirmed the hard way in the Carousel Timeline archetype, 2026-07-09, after two rounds of the swiper-based version failing to match Figma at all). When a slider archetype's item count is genuinely open-ended (a repeater, not a fixed 3-up), say so explicitly before writing any JS, and default to the *simplest* mechanism that satisfies both the shown state and the overflow case — see `wp-framework-block-builder`'s Swiper section for the concrete technical failure modes this pushes you toward if skipped.
- **When a rendered result and a Figma reference disagree and the disagreement isn't obvious from re-reading the code, get an actual screenshot of both at the same viewport width and look at them side by side — don't keep reasoning about CSS math in the abstract.** Several genuine bugs this session (a divider's padding making the first item mismatch the header's left edge; a rail spreading only 2 of 8 items with huge gaps) were mis-diagnosed through pure code review across multiple rounds and only became obvious the moment a real screenshot was placed next to the Figma frame — the visual difference was immediate and unambiguous in a way the CSS alone wasn't. This project's sandbox can produce real headless-browser screenshots (see the `sandbox_no_headless_browser` memory) — use that capability whenever "this doesn't match Figma" is reported, rather than re-theorizing from source.

---

## 11. Production readiness, scalability, and future-proofing

- **A block must handle zero, one, and many repeater rows without looking broken.** Bail cleanly on zero (the block-builder skill's `bail_message` pattern exists precisely for this). One row should still look like a deliberate, complete design — not a grid with two empty slots. Many rows must wrap, scroll, or paginate without silently overflowing.
- **A fixed item count is a valid design choice — but only when it's the intended one, and it should be communicated, not just implied by a hardcoded `repeat(3, 1fr)`.** `headline-stats`'s three-column grid is a real design decision ("this block is for exactly three stats"), not an oversight — but a block designed around a specific count needs that constraint to be legible to whoever's editing it (field labeling, instructions, or a layout that visibly signals "add a fourth and it'll look wrong"). If the intended count is genuinely arbitrary, choose a layout that already tolerates arbitrary counts (a slider, a wrapping grid) rather than hardcoding a column count and hoping nobody adds a fourth item.
- **Future-proof the structure, not the skin.** Library blocks are deliberately wireframe-quality (minimum styling) because the same structure gets reskinned per project by swapping tokens in `_variables.scss` — never by touching the block's markup. A well-designed block's HTML/component structure should survive a total palette, font, and spacing-scale replacement untouched. That means: never hardcode a decorative element as structurally required with no bail state (a brand-specific accent shape baked into the required markup, for instance) — if a future project's design system doesn't want that flourish, the block should degrade gracefully, not require a rebuild.
- **Design for the client you haven't met yet.** This theme is a starting point for multiple future projects (see `CLAUDE.md`'s "Using this as a starting point" framing and the `library`/`custom` block split). A `library/` block's structural decisions should hold up under a design system none of us have seen — which is exactly why token discipline (§0.1) and content-hierarchy discipline (§2) matter more than any specific pixel value: those are the parts that won't get thrown away at the next rebrand. This isn't theoretical: the four production projects surveyed for this skill are proof the same underlying structural instincts (split grids, listing templates, full-bleed heroes) hold up across a civic non-profit, two unrelated travel brands, and a B2B manufacturer — four completely different rebrands of the same reasoning.

---

## 12. Evolving a block without silently redesigning it

This complements the block-builder skill's §13 edit-impact classification (style-only / additive / breaking) with the design-side question that classification alone doesn't ask:

**Does this edit still express the same content hierarchy and compositional intent, or does it quietly turn the block into a different, unlabeled design?** Removing the image side from a hero-with-image block, or collapsing a three-stat grid down to one number, is a *design* change even when it's technically an "additive" or "style-only" field/CSS edit by the block-builder's classification. Flag this before making the change:

- If the edit changes which element is the visual anchor (§4), which reading order applies (§2), or which diversity lever (§6) the block was built around — that's a compositional change, not a tweak. Say so explicitly, and ask whether the result should really still be the same block (same name, same field group) or whether it's earned a new numbered variant (`library/`) or a genuinely distinct name (`custom/`).
- A change that's purely additive by the ACF-field-safety standard (a new optional field, empty everywhere until used) can still be a design regression if the new field, once filled in by every future editor, changes the block's silhouette every time it's used. Consider whether the "optional" field is actually going to be optional in practice.

---

## 13. Trust-building and section sequencing

Trust content — testimonials, partner/press logo walls, team bios, stat/credibility numbers — behaves consistently as *reinforcement*, not *orientation*, across all four production projects: it's placed mid-page, after a value proposition or listing section has already been made, never immediately after the hero. Design trust blocks with that job in mind — they're meant to be encountered once a reader is already leaning in, not as the reader's first impression.

Two validated sub-treatments of the testimonial archetype (§2), chosen by what kind of trust is being built:

- **Rating-card carousel** — name, role/context (e.g. destination visited), a star rating, and the quote, as a repeatable card in a grid or slider (`borealis/slider-testimonials`, `detal-tech-expert/slider-testimonials`). This is a "many people vouch for this" argument — credibility through volume and specificity (a rating, a named context).
- **Cinematic full-bleed quote** — one italicized pull-quote at a time over a large background image/video, no rating, no grid of peers (`southquest-safaris/video-testimonials`). This is a "this one story sells the feeling" argument — credibility through emotional immersion, not volume.

Choose the rating-card treatment when the goal is quantitative reassurance (many reviews, a specific number of stars); choose the cinematic treatment when the goal is aspirational/emotional persuasion and a single strong story does more work than a chorus of short ones.

Other confirmed trust idioms, all mid-page by convention: a stat/number block for institutional credibility (`headline-stats`, cross-confirmed by the independently-built `apcbrh/grid-numbers`); a passive logo/brand wall for partner or press credibility (`apcbrh/slider-parceiros`, `southquest-safaris/logos-slider`, `detal-tech-expert/slider-marcas` — present in three of the four projects); and team/people bios for organizations whose trust argument is "real people stand behind this" (`southquest-safaris/team-members`).

---

## 14. Cross-block relationships — designing whole pages, not isolated blocks

A block's design isn't finished at its own boundary — the same reasoning that governs a block's internal composition should govern how a set of blocks sequences into a whole page or template. Two concrete, evidence-backed patterns worth applying deliberately:

**A page's default shape follows the reading order established in §2, at page scale.** Orientation (hero) → the value proposition or main content → supporting listings/highlights → trust reinforcement (§13) → a conversion moment — is the shape that recurs, block-type by block-type, across unrelated industries in the production evidence. It's a default to reason from, not a rule to enforce mechanically: a technical B2B page reasonably swaps "supporting listings" for a spec/comparison table (§15), and a long single-item page (a product, a tour, a service) adds the second pattern below.

**Long-form single-item pages benefit from a persistent in-page navigator — confirmed by two different real implementations of the same underlying need.** When a page is built from many stacked content blocks about one thing (a product's overview, specs, and compatible materials; a multi-day tour's itinerary, inclusions, and logistics), readers lose their place. Two validated ways to solve this:

1. **A standalone anchor-menu block**, listing links that jump to `id`s set by convention in other blocks on the same page (`detal-tech-expert/menu-produto`: a flat horizontal link list plus a dynamically-inserted "Compare" link; `borealis/menu-roteiro`: the same idea, `position: sticky` and pinned via `top: $header-height-sticky`, so it stays visible while scrolling).
2. **A self-contained side-nav-plus-content combo block**, where the nav and the content sections it points to live inside one block, anchors generated from the same field data (`southquest-safaris/side-links-content`: a repeater of `{title, text}` pairs renders both the nav items and the `id`-tagged content sections from a single source, guaranteeing the links and targets never drift out of sync).

Prefer option 2 when the sectioned content is naturally one block's worth of data (it can't drift, by construction). Prefer option 1 when the "sections" are actually separate, independently-editable blocks elsewhere on the page that already exist for other reasons. Either way, a sticky nav element should anchor its offset to `$header-height`/`$header-height-sticky` rather than a guessed pixel value — the token already exists for this.

---

## 15. Industry adaptation — what "premium" means changes by audience

There is no single universal "premium" aesthetic in this system's real-world use — what reads as trustworthy and high-quality is calibrated to the audience, and the four production projects studied make this concretely visible rather than theoretical:

- **Consumer/lifestyle travel** (`southquest-safaris`, `borealis`): premium reads as *restraint* — large, editorial, photography-led imagery; the most generous whitespace and vertical rhythm of the four projects; flat geometry with no shadows and square (or barely-rounded) corners; italicized, serif-leaning type pairings on quotes. More decoration would read as cluttering a photograph that's meant to sell the destination itself.
- **Institutional/civic/community** (`apcbrh`): premium (in the sense of "trustworthy and well-run") reads as *warmth and legibility* — rounded "balloon" pill badges for short claims, rounded cards, a consistent per-service accent color letting each program carry its own identity while sharing one template, WhatsApp treated as a first-class secondary CTA. A flat, restrained, editorial-photography aesthetic would read as impersonal for an audience of individual dairy-farmer members, not premium.
- **B2B technical/industrial** (`detal-tech-expert`): credibility reads as *density and evidence* — spec-sheet tables, side-by-side comparison tools, brand/logo trust walls, even a manufacturing-process flowchart (`title-fluxo`). For a buyer evaluating equipment, a sparse, whitespace-heavy layout would look thin on evidence, not premium — density itself is the trust signal here.

The general, transferable lesson: **don't default a new block's density, ornamentation level, or reliance on imagery-vs-data to whatever the last project did.** Ask what this specific audience reads as credible before choosing how much whitespace, how much rounding, how much photography, and how much raw data density a block should carry. The framework's token system (spacing scale, radius tokens, palette) is what makes this recalibration cheap — the same block structure can carry three different personalities purely through token values, without touching markup.

One narrower, non-generalizable finding from the B2B project: its dense comparison/spec-table blocks (`listing-comparador`, `produto-especificacoes`, `produto-materiais-compativeis`, `title-fluxo`) solve a real need — decision support for a technical buyer comparing options — that a lifestyle or institutional site simply doesn't have. Treat these as evidence that *some industries need decision-support content instead of purely persuasive marketing content*, a real and worth-remembering principle, without promoting the specific tabular layouts themselves into the shared `library/` — they're a client-specific answer (§16, classification C) to a need that's real but not universal.

---

## 16. Recognizing legacy decisions — what production evidence corrects, and doesn't

Not everything in a real, shipped codebase is a lesson. Applying the A/B/C/D classification from this skill's introduction to the concrete conflicts found while surveying `.claude/design-references/`:

**(A) Weaknesses to correct, not repeat:**
- Legacy, orphaned color-token references — both `borealis` and `detal-tech-expert` reference dozens of SCSS variables (`$branco`, `$azul-escuro`, `$cinza-claro`, etc.) that no longer exist anywhere in this repository's `_variables.scss`. This confirms these are pasted-in snapshots of a dead palette, not a naming convention to port forward — always resolve color through *this* theme's live token file, never carry a legacy variable name across verbatim.
- Small hand-typo'd variable names surviving in production (`$verde_medio` used twice against `$verde-medio` used ten times elsewhere in the same `borealis` codebase) — a copy-paste slip, not a second valid spelling. When adapting a pattern from a reference, don't propagate an inconsistency just because it's "in the source."
- A static two-value `rem()` call where a scaling `fluid()` was almost certainly intended (`borealis/hero-video-content`'s `padding: rem(100, 150) 0;` — `rem()` doesn't interpolate between two values the way `fluid()` does, so this reads as a dropped function name, not a deliberate fixed value). Double-check any two-number sizing call against which function was actually meant.
- Dead, commented-out experimental code left in a shipped file (`borealis/card-roteiro-slide.scss` carries ~40 lines of an abandoned alternate hover treatment) — never carry commentary-as-code forward when porting a pattern; port the pattern that actually shipped, not the scratch work next to it.
- The absence of `prefers-reduced-motion` handling around the near-universal scroll-entrance animation (§9) — a real gap in every production project surveyed, worth fixing going forward rather than treating as "how it's always been done."

**(B) Limitations of that project's own tooling, not a lesson for this framework:**
- `apcbrh/form-web-leite`'s embedded third-party login widget (inline `onclick` handlers, a hardcoded external domain, raw `.btn-primary`/`.btn-secondary` classes outside this theme's button system) is a drop-in integration constraint of that specific external service, not evidence about how forms should be designed here.

**(C) Client-specific exceptions, valid there, not general rules:**
- A single, deliberately palette-breaking accent color on one conversion-critical action (`detal-tech-expert/listing-comparador` overrides its secondary button to an off-palette green, likely for urgency on a "buy" action inside a dense comparison table) — defensible as a rare, singular exception on one specific high-stakes action. The moment a second element also breaks the palette "for emphasis," it's no longer an accent — it's an uncontrolled second palette, and that's a real design bug.
- The B2B comparison/spec-table archetype (§15) — a legitimate, industry-specific answer, not a pattern to fold into the general-purpose block library.

**(D) Genuinely better decisions, already folded into this skill above:** the full-bleed background media layering and mobile art-direction technique (§8), the confirmed asymmetric split-grid formula (§4), the Listing macro-pattern and evidence-based card hover defaults (§7), sticky in-page navigation anchored to `$header-height-sticky` (§14), and trust-content sequencing (§13).

**One place the correction runs the other way — the framework has already improved on its own production history.** None of the four real projects used a semantic gap scale; all of them chose raw `rem()`/`fluid()` spacing values per block, by feel. The current `$gap-*` scale (§3) is the fix for exactly that drift. Don't read "production didn't use it" as license to skip it — read it as the reason it exists.

---

## Worked examples

**What separates a Hero from a CTA block?** A hero orients and sets emotional tone — generous whitespace, usually an image/video anchor, sits high on a page, and offers one primary path forward alongside brand context (overline + title + supporting image, per §2). A CTA block is the conversion moment itself — minimal supporting content (a short headline, at most one line of body), no imagery competing for attention, and it can legitimately repeat further down a page as a closing invitation. If a "CTA" block has grown a hero image and two paragraphs, it's drifted into being a second hero.

**Marketing hero vs. wayfinding banner hero — not every "hero" has the same job (§2).** `southquest-safaris/hero-destination` is a full-bleed image with a dark scrim and a centered headline — no CTA, no content card, because its only job is orientation on a landing page. `southquest-safaris/hero-tour` and `hero-accommodation`, by contrast, are split conversion heroes with a highlights panel and buttons. The same distinction repeats in `apcbrh` (`hero-image` — a plain ~170px-tall banner with nothing but an image and overlay, used on interior pages — versus `hero-servico`/`hero-associe-se`, full content-card conversion heroes) and in `detal-tech-expert` (`hero-image`/`hero-blog` as thin banners versus `hero-produto` as an immersive, CTA-driven brand moment). Decide which job a new hero block is doing before writing its field list — a banner hero that grows a highlights panel it doesn't need, or a conversion hero starved down to "just an image," has confused the two jobs.

**How many CTAs (buttons) belong in one block?** One primary action per screenful of attention. A secondary/tertiary link can coexist through style variance (`buttons`' `primary`/`secondary` style field) rather than through raw quantity — hierarchy comes from style contrast, not from offering three visually equal buttons and letting the reader guess which one matters.

**How should testimonials differ from feature sections?** A testimonial's rhetorical voice is third-person and borrowed — it leads with someone else's words as its anchor (§2), with name/role/photo as provenance, never as the headline act. A feature section's voice is first-person/brand — it leads with its own claim (title first), then substantiates it. Don't write a testimonial block that opens with a marketing headline above the quote; it undercuts exactly the credibility the format exists to lend. See §13 for the two validated testimonial sub-treatments and when to use each.

**Full-bleed cinematic CTA — two overlay strategies, both validated (§8).** A full darken/scrim across the whole image, with center-anchored text (this framework's own `cta-banner-1`), is right when the image is atmospheric/secondary to the message and the text needs to work anywhere on top of it. A directional gradient — solid near the text, fading to transparent toward the rest of the image — with content pinned to one side, is right when the photograph itself is doing marketing work and shouldn't be fully obscured (`detal-tech-expert/cta-banner`: a right-aligned content column over a gradient that clears text-legibility space on the right while leaving the image visible on the left). Choose based on whether the image is backdrop or subject matter.

**How should pricing sections guide attention?** Through visual weight (§4), not labels alone — one tier gets elevation, a border, or a background contrast that makes it read as "the recommended one" before a reader consciously compares numbers. A pricing block where all tiers have identical visual weight is asking the reader to do hierarchy work the design should have done for them.

**How should image placement change with content type?** Emotional/brand content → full-bleed background image with overlay text, image as the entry point (`cta-banner-1`, §8). Explanatory/feature content → image as a supporting side panel that never overlaps the text it supports (`content-image-1`). Proof/credibility content → a small identity photo, not a hero image (`testimonial-1`) — the image's job there is provenance, not spectacle. No natural image need → no image field at all, and don't force one in just because "blocks usually have one" (`headline-stats`).

**When should cards be avoided?** Cards imply "several equivalent, independently-relevant items a reader can scan in any order." Avoid them for content with an inherent sequence or priority — a step-by-step process, a timeline, a ranked list — where equal-weight cards would misrepresent an order that actually matters. Use a stacked, sequential, or explicitly numbered layout instead.

**When should asymmetry be used over a symmetric split?** When one side of a composition should visibly dominate — a brand image against supporting copy, a big number against a short caption. Avoid asymmetry for a set of items that are meant to read as equal peers (a stat set, a feature list, pricing tiers of comparable weight) — symmetry there is doing real communicative work, not just being tidy.

**When is it acceptable for a single element to break the palette?** Rarely, and only once per composition (§16) — a single conversion-critical action (a "buy" button inside a dense comparison table) can justify a deliberately loud, off-palette accent so it can't be missed. The instant a second element also claims an off-palette color "for emphasis," the palette has quietly gained a second, uncontrolled meaning — that's a regression, not a second exception.

**When should full-width/edge-to-edge win over the constrained wrapper?** For imagery, background treatments, and sliders, where bleeding to the viewport edge (`$wrapper-full`) reinforces immersion (a hero image) or signals "there's more to scroll" (a slider peeking past the fold). Constrained-width wrappers (`$wrapper-default`/`-medium`/`-small`) are for reading content, where an unbroken measure keeps body copy legible.

**How should large display typography be balanced?** Pair a big `$display-*`/`$heading-1` line with a short, controlled line count — a headline that wraps to four lines at that scale has lost its impact and probably needed a smaller scale or a rewrite, not more room. Give it generous surrounding space (§5) so it doesn't visually collide with whatever spacing the next block's editor chooses.

**How do you avoid producing repetitive layouts over time?** Run the one-sentence silhouette test from §6 before calling any new block finished, against every block that already exists — not just the most recent one, and not just this theme's own library, but the range of real silhouettes confirmed in `.claude/design-references/` too. Diversity has to be actively defended over the life of a growing library; it erodes by default because reusing a familiar structure is always the path of least resistance.

---

## Mandatory design review

Run this after any block is designed — proposed field list and structure, or a finished implementation — before calling it done:

- [ ] Does this genuinely solve a business or content problem, stated in one sentence, that isn't already solved by an existing block with a different field value?
- [ ] Does it belong to this design system — tokens for every spacing/type/color decision, no raw literals invented for this one block (§0.1, §7)?
- [ ] Does its silhouette (§6) read as genuinely different from every existing block, using at least one real diversity lever — not just a rearrangement of an existing block's elements?
- [ ] Is it unnecessarily similar to another block — could it be produced by an existing block's field/style/count toggle instead of existing as its own block (§6's corollary)?
- [ ] Does it reuse existing `style-guide`/`custom` components before considering a new one, and does any new component clear the reuse bar in §7's decision order?
- [ ] Does it avoid introducing UI that doesn't serve the content — no decoration or component added just because the layout "felt empty" (§5)?
- [ ] Does it follow the field-order/content-hierarchy convention, or does it explicitly justify a deliberate deviation (§2)?
- [ ] Does its typography follow the existing scale ladder with a defensible rung choice, not an arbitrary size (§3)?
- [ ] Does it hold up against an editor-chosen background and spacing it wasn't designed against — no assumption about what precedes or follows it on the page (§1)?
- [ ] Is it genuinely responsive from the single-column base case upward, not adapted down from a fixed desktop layout (§10)?
- [ ] Is it accessible — DOM order matches intended reading order, contrast holds against more than one background, interactive affordances aren't color-only, motion isn't load-bearing (§9)?
- [ ] Is it production-ready for zero/one/many repeater rows, and does a fixed item count (if any) make its constraint legible rather than silently breaking on the next item (§11)?
- [ ] **Would this block fit naturally beside the production blocks in `.claude/design-references/`** — calibrated to the right density/restraint for its industry (§15), not a generic template that could belong to any project?
- [ ] **Would an experienced designer recognize this as part of the same framework**, not a foreign import — same token discipline, same field-order logic, same composition vocabulary?
- [ ] **Is this introducing unnecessary novelty** where an existing, evidence-backed pattern (§6, §7, §8, §13, §14) already solves the problem?
- [ ] **Is this visually memorable**, or does it disappear into generic template noise — does it have exactly one clear visual anchor (§4)?
- [ ] **Does it communicate its business objective within the first glance**, before a reader has read a single word of body copy?
- [ ] **Could this same structure work across a different industry** with new content and retoned tokens, or is it secretly one client's private idiom masquerading as a general-purpose block?
- [ ] **Is this relying on a legacy production shortcut that the framework's current system already supersedes** — an orphaned color reference, a static value where scaling was intended, an off-palette color reached for out of habit rather than deliberate exception (§16)?
- [ ] **Am I copying an implementation detail from a reference block, or the underlying design principle behind it?** (Port the pattern, never the literal code — `.claude/design-references/README.md`.)
- [ ] Could another designer or a future teammate understand the composition's intent by reading the markup and field order alone?
- [ ] Could it become a real WordPress block via the block-builder skill without needing a structural redesign first?
- [ ] **Would this still belong in the framework five years from now**, for a client and a palette nobody involved today has seen — or is it tied to one moment's trend (§11, §16)?
