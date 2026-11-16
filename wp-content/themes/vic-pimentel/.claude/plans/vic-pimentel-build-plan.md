# Vic Pimentel Portfolio: Build Plan

Source of truth: **your copy** of the Figma file, `QAZnKg9XaNh8sOhZbxXgEl` (`.theme.json` → `figmaUrl`). It's in your "Rafa" Pro team, where you hold a Full seat. Victor's original file is `ghWCQXro2GePD7ZGOAnUQ2`.
Designer: Victor Pimentel. Every open comment and on-canvas "Global Notes" box was read on 2026-11-12. They're digested in §1.

**Designer comments in the copy.** Duplicating a file drops its comments, so all 38 unresolved ones (replies merged in) were restored as **annotations** in the "Designer comment" category (pink). Each sits on the node it pointed at and keeps Victor's name and the date. Node IDs survived the duplication, so every ID below works in both files. To see them, turn on annotations in Dev Mode or with Design's annotation toggle. Comments from the Archive pages (v1–v3) were not restored; they're superseded.

| Page | Node | Frames (1920 · 1440 · 1280 · 1024 · 600 · 380) |
|---|---|---|
| Home | `289:5300` | `289:5968` · `289:5969` · `289:6509` · `289:7025` · `289:7585` · `289:8179` |
| Post Detail | `289:14962` | `289:16752` · `289:17062` · `289:17558` · `289:18082` · `289:18576` · `289:19067` |
| Media Modal | `289:21582` | `289:23076` … |
| About | `289:37819` | `289:40926` · `289:41346` · `289:41549` · `289:41752` · `289:41955` · `289:42158` |
| Login | `289:46185` | Login `289:46186`…`46191` · Loading `289:46260`…`46265` |

Design system:
- Foundations `206:7459`.
- Components `284:1951`.
- Sections Library `283:2190`: Navigation `286:3722` (header `286:3984`, footer `289:719`), Hero `289:848`, Listing `289:1708`, Blog Post `289:10550`, About `289:32281`, Login `289:44631`, Media Modal `289:20511`.

## Status — 2026-11-12

**Built and verified locally** (Lando; screenshots at 2560 / 1920 / 1440 / 1280 / 1024 / 380):
- Cleanup (§3) done: library blocks, CPTs, unused components/scripts/npm deps, PT ACF locale and design references removed; `style.css` renamed (author: Rafael).
- Foundations (§2): 6 Figma breakpoints, multi-stop `fluid()` (+ `$extrapolate`), tokens as `:root` custom properties, Instrument Serif + Inter self-hosted, glass surfaces.
- Content model (§4), components (§5), blocks + `single.php`/`404.php` (§6), password gate (§7), placeholder art + mp4 loops + seeded pages (§8), interactions (§9): header hide/show, hover video, tabs (AJAX, `?tab=`), copy email (2s revert), media modal (`<dialog>`), "confidential" loading screen.

**Round 2 (same day)**: loader ring fills over the hold time; featured list moved into the Project Listing block; password stored/visible as plain text; Portfolio Access tabs on the left; Unicorn embed-ID support + dark-tinted public test scene (mouse-reactive, watermarked); project-editor spacing; WP paragraph block removed; admin login logo + readable button.

**Round 3**: animated tab switching (sliding pill, fade-out, skeletons while loading, staggered fade-in); own WebGL flow background replacing the Unicorn examples; AJAX password check; "Vp" site icon.

**Round 4 (2026-11-16)**: Victor's Unicorn exports wired in from `src/animations/` (runtime bumped to 2.6.0 to match the exports).

**Pending on the designer**
- Intro micro-animation reference (sections use the default `ember-entrance` for now).

**Not covered / follow-ups**
- The gate can't protect direct media URLs (`/wp-content/uploads/...`); needs a server/CDN rule if required.
- Real content, CV/digital-card PDFs, social URLs, final password.
- `wp-i18n-sync` not run yet; figma-sync skill docs still describe the 2-mode (Mobile/Desktop) token sync.
- Before handoff: `SHOW_ACF_ADMIN` → false.

---

## 1. Designer requirements (from Figma comments + notes)

### Global
- **References:** ozgur.design (primary), framer.com homepage examples, Apple Newsroom articles, mobbin.com (cards, post hero, sections, modal), cosmos.so/explore (filters), motionsites.ai (copy button), lukebaffait.fr (hero bg FX), kierkegaard.framer.website/about (about media box).
- **Width rules:**
  - Hero, header and footer are fluid with **no max width** and keep scaling past 1920.
  - The home hero follows viewport **height and width**.
  - The About hero follows width only past 1920; its height doesn't track the viewport.
  - The post-detail section is fluid because of the animated top light, but its **content** has a max width.
  - Every other section: **max-width 1920px**.
- **Intro micro-animations** on page enter and per-section intros (ozgur.design style). The designer still owes the exact reference. The title hover that "rewrites" the copy is **excluded**.
- **Unicorn Studio** background scenes (designer still owes the assets; exported as JSON + Unicorn SDK):
  - Home hero: flow FX, **mouse tracking**.
  - Footer: flow FX, **mouse tracking**.
  - Login: same flow FX, **no** mouse tracking.
  - Post-detail and About heroes: a **sticky, looping** "light" at the top of the hero.

### Header (`289:5968`, `289:7025`, Navigation `286:3722`)
- Desktop has three parts:
  - Logo on the left.
  - Nav pill in the middle (Work / About / Contact).
  - "Based in Italy / *Working Globally*" on the right.
- Logo and right copy appear **only at the top of the page**, inside the hero. They don't stick.
- **Only the middle nav is sticky.** It hides while scrolling down and reappears on scroll up (adidas.it pattern).
- Tablet: logo and copy stay on top; the nav moves to a **bottom-fixed bar**.
- Mobile: the logo alone stays on top; the nav sits in the bottom bar.

### Home
- Hero:
  - Centered intro paragraph in two tones (white, then white/60).
  - "Creative & Cool Stuff" set in Instrument Serif h-3xl, split across the width.
- Filter bar (cosmos.so):
  - Tabs: **Featured** · Product Design · Branding · Illustrations, plus "Showing (N Results)" on the right.
  - The bar is **not sticky**.
  - **Featured** is not "latest". It's an ACF **relationship**, **max 16**, in manual order.
  - Each category tab lists **all** posts in that category, **latest first**.
  - Categories at launch: Product Design, Branding, Illustration. More will be added later, so the tabs must be generated from the taxonomy.
- Project cards (mobbin):
  - Thumbnail is a video or an image. A video plays **on hover**.
  - A pause button appears top-right **only if the thumb is a video**.
  - Footer row: client favicon · category *in* Company Name.
  - The thumbnail is the hero's first frame.
  - If hover videos get too heavy, fall back to static thumbs. The designer explicitly allowed this.

### Post Detail
- Hero:
  - Category | "Posted on {date}".
  - Client favicon (**toggle on/off**) and the title.
  - Hero media is a looping video (autoplay) **or** an image. Branding/illustration posts may have no video.
- Body:
  - Lead paragraph.
  - Text sections (serif heading + paragraph): About the Project / Challenge / Solution / Results.
  - Single image box with a caption (**caption toggle**).
  - Two-column images, **max 2**, each with a caption.
  - Stats row: **min 1, max 3** ("Up To / 48x Faster / text").
  - **Project Info** table: **toggle on/off**, any number of rows.
  - Brand and illustration images go in the body as regular image fields.
- **Highlights in This Project** (mobbin "sections"):
  - Per-post **toggle**. Not every project has it.
  - Grid of "Section Name" cards. Clicking one opens the **Media Modal**.
- **You May Also Like:** 4 **random posts from Featured**, excluding the current post, plus "View All" (→ home).

### Media Modal
- Header with "Section Name" and a close button.
- One large image or video.
- Blurred, dimmed page behind it.
- Pattern: mobbin sections modal.

### About
- Hero:
  - Media box on the left is a **GIF or image**.
  - Serif headline with italic emphasis ("Hi! I'm Victor, a *Designer and Illustrator* …"), then a paragraph.
- Story rows: label · title + text · years. Rows are repeatable, and the hero light continues down here.
- Main Services: numbered rows [01] title · text, repeatable.
- My Approach: text.
- Skills & Tools: chips, repeatable.
- What Inspires Me: text, plus a list panel (icon · title · description) with repeatable rows.
- Future Goals: text and buttons (Get In Touch / Download My Curriculum / Check My LinkedIn).

### Footer
- "Get *In Touch*" plus the **copy-email button**:
  - Inspired by the motionsites "copy full prompt" button.
  - States: enabled → pressed → "Copied", then back to enabled after about **2s**.
  - No loading state.
- Columns:
  - Network (LinkedIn / Instagram / Behance).
  - Site Map (Work / About Me).
  - Quick Links (Download curriculum ↓, Download digital card ↓).
- Bottom row: *Vic*Pimentel logo · short text · "*Portfolio* 2026".

### Login (portfolio access gate, not the WP login)
- "Welcome To My Portfolio!", a text, a **password-only** field, an "Access Now" button, and "If you don't have a password contact me {email}".
- That email link uses the same copy behaviour: it changes to "Copied" and reverts after a few seconds.
- After a correct password:
  - A loading screen shows a spinner, "This Contains Confidential Information" and a paragraph.
  - It holds for **3–5s** so the visitor can read it, then redirects to home.
- After logging in, the visitor stays logged in for **1–2 days**. This is a configurable option.

---

## 1b. Figma inspection: components and sections (1920 values; token names in parentheses)

### Surfaces
The site uses **translucent glass, not gray fills**:
- Cards, the post media box, the info card and the project-info card use `white/4` fill, a `white/2` 1px border, `backdrop-filter: blur(20px)` and `radius-l`.
- On hover these go to `white/8` and `white/4`.
- Solid grays appear in only a few places:
  - `mid-gray` on the nav pill, the copy-email box and the About media box.
  - `heavy-gray` on the modal panel.
  - `low-gray` on the footer panel.
- Page bg is `black/100`.

So §2.3's semantic roles become: page bg = black, surface = `white/4`, surface-hover = `white/8`, border = `white/2`, border-hover = `white/4`, divider = `white/10`. Text tiers are white/100, /80, /60, /50 and /30.

### Components (`284:1951`)
Every component has 6 size variants (one per breakpoint) plus states.

| Figma | Spec | Theme target |
|---|---|---|
| `menu-button` | pill, px `space-s`, h32, `action/a-m`. enabled = white/30 text, hover = white/60, active = white/4 bg + drop shadow + white text | `nav-pill` item |
| `navigation` (in header) | `mid-gray` pill, p4, `white/2` border | `nav-pill` |
| `category-tab` | pill, p8/16, h40, `action/a-m`. enabled = white/30, hover = white/60, active = white/4 bg. backdrop blur 20 | `filter-chip` |
| `copy-button` | h36, px8, gap8, `radius-m`, white/4 → hover white/8, blur 20, 14px icon + "Copy". Pressed swaps to a check icon + "Copied" | `copy-email` |
| `copy-email` | `mid-gray` box, `white/8` border, `radius-m`, p8/8/8/16, gap16, email `paragraph/p-l` + `copy-button` | `copy-email` |
| `about-button` | pill h36, px16, white/8 → white/10, text white/60 → white/80, `action/a-l` | `button` ghost |
| `login-button` | pill h50, white/8 → white/10, blur 20, text white/60 → white/80, `action/a-l`, full width (300) | `button` solid-glass |
| `button-1` | text link + chevron, white/80 → white, `action/a-l` ("View All") | `link` with arrow |
| `download-link` | `link/l-m` white/50 → white/80 + download icon, gap4 | `link` download |
| `thumb-video` (play/pause), `expand-modal`, `close-modal` | 24px circle, white/8 + `white/2` border + blur 20 + small shadow; icon white/60 → white/80 | `icon-button` |
| `about-label` | pill h32, `white/50` 1px border, `paragraph/p-m-bold` white/50 | `filter-chip` static variant |
| `login-input` | h50, w300, `radius-m`, white/8 bg, `white/2` border (focus: white/10 + `white/4`), placeholder white/60 → white/80, `paragraph/p-s` | gate form field |
| `post-card` | media box p48, `radius-l`, glass; inner thumb `radius-m` + drop shadow 20; play/pause button top-right p8; meta row gap8: favicon 24px `radius-s` · category `special/s-2` · "in" `special/s-1` white/60 · company `special/s-2` | `card-post` |
| `section-thumb` | same box as post-card; title `special/s-2`; `expand-modal` button appears **on hover** | `card-section` (highlights) |
| `loading` | 40px ring: `white/8` track + white arc | spinner (CSS) |

### Sections

**Header** (`286:3984`)
- 1920: three equal columns, logo 186×36 / nav / location, px `horizontal-padding/default`, height 100.
- 1440 and 1280: logo shrinks to 155×30.
- 1024: height 80; logo 124×24; the right side gains a **second text column, "Product Designer / Illustrator"**, next to "Based in Italy / *Working Globally*".
- 600 and 380: height 50, logo 83×16.
- Separate `style=navigation` variant at ≤1024: a **bottom bar** 74 tall, p16, with a **linear-gradient bg** (transparent → black) behind the pill.

**Home hero** (`289:1495`)
- Outer p16 inset.
- `container` is `radius-xl`, p48, and its image fill is the static stand-in for the Unicorn scene.
- Intro is `paragraph/p-2xl-bold` (750 wide, two-tone).
- Headline row is `h-3xl`.

**Post Listing** (`289:5097`)
- Section pt24 / pb64, `wrapper` max 1920, px default, gap24.
- Tabs row (gap 2) + "Showing { N Results }" in `paragraph/p-xs` white/30.

| Grid | 1920 | 1440 | 1280 | 1024 | 600 | 380 |
|---|---|---|---|---|---|---|
| Post Listing (`289:5097`) | 4 | 4 | 2 | 2 | 1 | 1 |
| Project Highlights (`289:9312`) | 3 | 3 | 3 | 2 | 1 | 1 |
| You May Also Like (`289:10457`) | 4 | 4 | 4 | 2 | 1 | 1 |

- Every section above uses `vertical-padding/xl` and a `heading/h-m` headline. "View All" sits right-aligned.

**Post detail** (`289:14707`)
- `fx-background` image at the top, 628 tall: the stand-in for the sticky Unicorn light.
- **Two column widths:** media 950 max (hero thumb, single-media, duo-media), text 850 max (intro, body, stats, project-info), both centered.
- Hero:
  - `post-info` (category `p-xs-bold` | 2px divider | date `p-xs` white/60).
  - `main-title` (favicon 40 `radius-m` + `heading/h-l`).
  - `detail-thumbnail` (glass box p48 holding an 800×450 media).
- `intro-text` `p-2xl-bold`. `body`: `heading/h-xs` + `paragraph/p-l` white/60, gap24.
- `single-media` / `duo-media`: glass box (no padding, `white/4` border) + caption `paragraph/p-2xs` white/60.
- Duo is a two-up row with gap48, square ratio.
- `stats-card`: overline `p-l-bold` white/60, number `p-xl-bold`, text `p-l-bold` white/60. Row gap48.
- `project-info`: glass card p24/4, header "Project Info" `p-s-bold`, rows p0/24 label / value.

**About** (`289:37756`)
- `fx-background` 643 tall (Unicorn light).
- Hero: pt128, three columns 400 / 928 / 400 (the third is an empty spacer). Media box 300² `mid-gray` `radius-l`; headline `heading/h-xl` max 850.
- Timeline: divider `white/10` 2px; 3 columns, 330 (label `h-s` + year `h-xs`) / 1068 (subtitle `p-2xl-bold` + text `p-l` white/60) / 330 (year right-aligned). One column is hidden per breakpoint; check 1024 and 600.
- Text 1 (services): `h-s` heading; rows p24 with `[01]` `p-l` white/60 + title `p-l-bold`, 250 wide; text `p-l` white/60, 576 wide; divider between rows. Column 850.
- Text 2–5: divider + `h-s` heading + `p-l` text, pb64, column 850, optionally followed by:
  - chips (Text 3),
  - an info-card list (Text 4: icon 16 + `p-s-bold` title | `p-s` white/60 desc, dividers `white/2`),
  - or `about-button` row (Text 5).

  This confirms the single reusable `text-section` block (§6.3) with optional chips / list / buttons.

**Footer** (`289:719`)
- Outer p16 inset (0 at ≤600). Panel `low-gray`, `radius-xl`, p48, gap `2xl`.
- The static stand-in is a black ellipse plus a `white/4` rect with **blur 300 + noise** effect. Use that as the CSS fallback until Unicorn arrives.
- Rows: "Get In Touch" `heading/h-m` + `copy-email`; 3 link columns; bottom row with **`heading/h-2xl`** "*Vic*Pimentel" left and "*Portfolio* 2026" right, short text centered on desktop.

**Login / Loading** (`289:46134` / `289:45890`)
- Full viewport `black/100`; `fx` image = Unicorn stand-in.
- Content column max 500, centered.
- Login: headline `heading/h-s` + text `p-s` white/60, gap16; then a 64 gap before input → button → contact line.
- Loading: spinner 40 → 64 gap → headline `h-s` + text `p-s` white/60.

**Media Modal** (`289:20510`)
- Backdrop `black/50` + backdrop blur 20.
- Panel: max 1440 (1344 inside px default), `heavy-gray`, `white/4` border, `radius-xl`, p64.
- Media 1000×623 `radius-m` with drop shadow.
- Top bar p16: title `paragraph/p-xs-bold` + `close-modal`.

---

## 2. Design tokens (Figma variables → SCSS)

### 2.1 Breakpoints (6 Figma modes)
The Figma modes are desktop-large 1920 · desktop-medium 1440 · desktop-small 1280 · tablet 1024 · mobile-large 600 · mobile-small 380.

Replace `_breakpoints.scss` with this map:
```scss
$breakpoints: (
  'mobile-small': rem(380), 'mobile-large': rem(600), 'tablet': rem(1024),
  'desktop-small': rem(1280), 'desktop-medium': rem(1440), 'desktop-large': rem(1920),
);
```
- Migrate the existing `media()` calls in the kept components (about 54 uses, mostly `>=tablet` and `>=phone`): `phone` → `mobile-large`, and `tablet-wide` → `desktop-small`.
- Delete the old names. Don't alias them.

### 2.2 Multi-stop `fluid()`
Requirement: interpolate linearly across **any number** of breakpoint stops, in one CSS expression and with no media queries. The current 2-argument calls must keep working.

**Recommended:** a map-based piecewise-linear `fluid()` that takes `(breakpoint: value)` pairs. It sums one clamped ramp per segment:

```
v(vw) = v0 + Σ_i (v_{i+1} − v_i) · clamp(0, (100vw − bp_i) / (bp_{i+1} − bp_i), 1)
```

```scss
// New: font-size: fluid((mobile-small: 24, mobile-large: 32, tablet: 54, desktop-small: 54, desktop-medium: 104, desktop-large: 150));
// Legacy: fluid(16, 24) / fluid(16, 24, 'mobile-large', 'tablet') → converted to a 2-stop map internally.
@function fluid($stops-or-min, $max: null, $min-bp: 'mobile-small', $max-bp: 'desktop-medium', $extrapolate: false) { … }
```

How it behaves:
- Each segment emits one `clamp(0, …, 1)` term. A flat segment (equal values) emits nothing.
- Below the first stop the value pins to the first value. Above the last stop it pins to the last value. Non-monotonic sequences (up, then down) work.
- `$extrapolate: true` drops the clamp on the last segment so the value keeps scaling with `vw` past 1920. Use it for the hero, header and footer typography, which have no max width.
- Stops accept breakpoint names or raw px. Values accept px, rem or 0, and come out in rem.
- Generate the map from the token map automatically. A token defined as a 6-value list `(150 104 54 54 32 24)` is zipped with the 6 breakpoints, so the token file reads like the Figma table.
- Expose the tokens as **CSS custom properties on `:root`** (`--fs-h-3xl`, `--gap-2xl`, …) and keep the SCSS variables pointing to `var(--…)`. The heavy `calc` is emitted once instead of per usage, and DevTools stays readable.
- Update the `fluid()` docs in CLAUDE.md, plus the figma-sync skill docs, which currently assume 2 modes (Mobile/Desktop). A 6-mode Dimensions collection needs a new mapping section in `figma-to-scss.md`/`scss-to-figma.md`.

> The rejected alternative was stepped values via 6 media queries. They're simpler, but they jump at every breakpoint, and you asked for fluid.

### 2.3 Colors
The palette is monochrome dark. Each base has an opacity ramp (90…2, none) built as Figma aliases with opacity.

| Figma | Hex |
|---|---|
| black/100 | `#030303` |
| heavy-gray/100 | `#0B0B0B` |
| mid-gray/100 | `#141414` |
| low-gray/100 | `#1B1B1B` |
| white/100 | `#FFFFFF` |

- Replace `$primary…$status-*` with the 5 bases plus a `tint($base, $pct)` helper built on `color.adjust($base, $alpha: …)`, which is the CLAUDE.md rule. Don't use rgba literals.
- Map the semantic roles onto it:
  - Backgrounds: `background-1` black, `-2` heavy-gray, `-3` mid-gray, `-4` low-gray.
  - Text: white/100, /80, /60, /50, /30.
  - Surfaces are translucent glass (see §1b): surface `white/4`, surface-hover `white/8`, border `white/2`, border-hover `white/4`, divider `white/10`.
  - Add a `glass` mixin: surface + border + `backdrop-filter: blur(20px)`.
- Restrict the `bg_color` global-field choices to these.
- Drop light-theme tokens: the site is dark-only. Keep `$status-3` (error red) for the login error state only. The designer defined none, so confirm with them.

### 2.4 Typography
- Fonts:
  - **Instrument Serif** 400 regular + *italic* for headings and special s-1. The italic is used for emphasis words.
  - **Inter** 400 and 600.
  - Self-host woff2 in `static/fonts.scss` and regenerate the metric fallbacks.
  - Remove Plus Jakarta Sans and IBM Plex Mono.
- Styles: replace `$display-*`, `$heading-*`, `$p-*`, `$action-*`, `$link-*` and `$overline-*` with the Figma ramp, using the values in §2.6.
  - heading: h-3xl … h-xs
  - paragraph: p-2xl … p-2xs, each with a `-bold` (600) variant
  - action: a-xl … a-s
  - link: l-xl … l-s
  - special: s-1 (serif) and s-2 (Inter 600)
- Line height: 1.1 for heading, action, link and special; 1.3 for paragraph.
- Letter spacing: −0.03em everywhere. Figma's −3% equals −0.03em.
- Italic emphasis: let `headline`/`rich-text` turn `<em>`/`<i>` inside serif headings into Instrument Serif Italic ("Get *In Touch*", "Highlights in *This Project*").
- Two-tone hero paragraph: wrap the dimmed portion in a `<span class="muted">` via a rich-text format, or two text fields.

### 2.5 Spacing, radius, layout
- Spacing: `gap/*`, `horizontal-padding/*` and `vertical-padding/*` share **one** scale (3xs…2xl). Implement it as a single `$space-*` scale plus `$horizontal-padding: $space-l` (the default: 48→16).
  - `$vertical-*` maps onto it: none, s, m, l, xl, 2xl. Update the `spacing_top`/`spacing_bottom` global-field choices.
- Radius: s / m / l / xl / full / none (desktop 2/4/8/12, mobile 1/2/4/6).
- Wrappers:
  - `.wrapper` = max-width 1920 with `$horizontal-padding`.
  - `.wrapper.full` = no max width (hero, header, footer).
  - `.wrapper.media` = 950 max (post media column).
  - `.wrapper.content` = 850 max (post/about text column).
- Inset panels: the hero and footer sit as **16px inset rounded panels** on desktop and go full-bleed at ≤1024. Make that a shared `panel-inset` mixin.

### 2.6 Token values (px per mode: 1920 / 1440 / 1280 / 1024 / 600 / 380)

**Headings**

| Token | 1920 | 1440 | 1280 | 1024 | 600 | 380 |
|---|---|---|---|---|---|---|
| h-3xl | 150 | 104 | 54 | 54 | 32 | 24 |
| h-2xl | 74 | 64 | 54 | 44 | 32 | 24 |
| h-xl | 64 | 54 | 44 | 44 | 32 | 24 |
| h-l | 48 | 40 | 40 | 42 | 32 | 22 |
| h-m | 40 | 40 | 40 | 32 | 24 | 22 |
| h-s | 32 | 32 | 32 | 26 | 24 | 22 |
| h-xs | 26 | 26 | 26 | 24 | 22 | 22 |

**Paragraph, action, link, special**

| Token | 1920 | 1440 | 1280 | 1024 | 600 | 380 |
|---|---|---|---|---|---|---|
| p-2xl | 24 | 24 | 24 | 20 | 18 | 18 |
| p-xl | 22 | 22 | 22 | 20 | 18 | 18 |
| p-l | 20 | 20 | 20 | 18 | 16 | 16 |
| p-m | 18 | 18 | 18 | 16 | 14 | 14 |
| p-s | 16 | 16 | 16 | 16 | 14 | 14 |
| p-xs | 14 | 14 | 14 | 12 | 12 | 12 |
| p-2xs | 12 | 12 | 12 | 12 | 12 | 12 |
| a-xl, l-xl | 20 | 20 | 20 | 18 | 16 | 16 |
| a-l, l-l | 18 | 18 | 18 | 16 | 14 | 14 |
| a-m, l-m | 16 | 16 | 16 | 16 | 14 | 14 |
| a-s, l-s | 14 | 14 | 14 | 14 | 14 | 14 |
| s-1, s-2 | 16 | 14 | 12 | 12 | 12 | 12 |

**Spacing and radius**

| Token | 1920 | 1440 | 1280 | 1024 | 600 | 380 |
|---|---|---|---|---|---|---|
| space-2xl | 128 | 128 | 128 | 64 | 48 | 48 |
| space-xl | 64 | 64 | 64 | 48 | 24 | 24 |
| space-l | 48 | 48 | 48 | 24 | 16 | 16 |
| space-m | 24 | 24 | 24 | 16 | 16 | 16 |
| space-s | 16 | 16 | 16 | 16 | 16 | 16 |
| space-xs | 8 | 8 | 8 | 8 | 8 | 8 |
| space-2xs | 4 | 4 | 4 | 4 | 4 | 4 |
| space-3xs | 2 | 2 | 2 | 2 | 2 | 2 |
| radius-xl | 12 | 12 | 12 | 12 | 6 | 6 |
| radius-l | 8 | 8 | 8 | 8 | 4 | 4 |
| radius-m | 4 | 4 | 4 | 4 | 2 | 2 |
| radius-s | 2 | 2 | 2 | 2 | 1 | 1 |

### 2.7 Figma issues to report to the designer (not blocking)
- `text-style/paragraph/*/line-height` variables alias to `letter-spacing/paragraph` instead of `line-height/paragraph`. The text styles themselves correctly use 130%, so the code uses 1.3.
- `page-size/wrapper` equals the frame width in every mode. It's not a real max-width token, so the 1920 cap from the comments is applied instead.
- The 380px Post Detail frame is named "Post Detail - 600px".
- h-l goes up at tablet (40 → 42). It's probably a typo. Keep it as-is unless the designer corrects it.

---

## 3. Cleanup (project fork from base theme)

1. **`style.css`**
   - Theme Name `Vic Pimentel`.
   - Author / Author URI: confirm whether it's you or Victor.
   - Description: "Portfolio of Victor Pimentel — Designer & Illustrator".
   - Version 1.0.0.
   - Keep the `rear-theme` text domain: renaming it touches every string for no gain.
   - Replace `screenshot.png` with a 1200×900 export of the home hero.
2. **Library blocks:** delete all of `src/content/blocks/library/` (16 categories, about 389 PHP files) and `templates/block-listing.php`. Set `LOAD_LIBRARY_BLOCKS` to `false` as a safety net.
3. **CPTs and taxonomies:**
   - Delete everything in `app/post-types/` (depoimento, documento, evento, faq, mega-menu, membro-equipe, unidade, video).
   - Delete their `acf-json*/post-types/*.json` and the matching `LOAD_*_FEATURES` constants and `*_enabled()` helpers.
   - Remove the Integrations options page and the location exclusion in `scan_and_register_blocks()`.
4. **Components:**
   - Delete the whole `components/library/` tier.
   - Delete these `custom/` components: card-documento, card-material, card-member, card-project, card-video, mega-menu, member-modal.
   - Delete these `style-guide/` components: accordion, comparison-handle, form, pagination, rating, swiper-controls, tabs.
   - Keep and restyle: button, buttons, headline, icon, image, image-video, link, modal, rich-text, video-player, background-media, filter-chip, social-media.
   - Rebuild header, footer and card-post.
5. **Scripts:**
   - Remove the `google-maps`, `member-modal`, `post-views` (+ `app/ajax/post-views.php`), `swiper`, `lightgallery` and `input-mask` imports and files.
   - Remove the `axios`, `d3-color`, `swiper` and `lightgallery` npm deps.
   - Keep `lenis`, `scroll-to`, `vh-fix` and `ember-js`.
6. **Templates:** delete `templates/ember-animations-*.php` and their styles/scripts (showcase pages).
7. **ACF locale:** the site copy is English, so promote `acf-json-en/` → `acf-json/` and delete the PT folder (per CLAUDE.md). Run `wp-acf-translate` on any kept block JSON.
8. **Custom fields:** drop `formulario`, unless a form is added later.
9. **`.claude/design-references/`:** delete it (per CLAUDE.md). It contains other clients' code.
10. **Other:**
    - Update the README.
    - Strip the CLAUDE.md sections about removed features: location/testimonial/etc. gating and the listing-3 AJAX reference. Add a reference to the new AJAX handler.
11. **Modules:**
    - Keep MediaTrim: WebP + mp4 workflow for heavy thumbnails.
    - Keep SVG upload: client favicons.
    - Keep Duplicate Post: seeding mock projects.

---

## 4. Content model

### Posts = Projects
Use native `post`, relabel it "Projects" in the admin, and keep core categories.
- **Taxonomy:** core `category`. Seed Product Design, Branding, Illustration.
- **ACF "Post Type: Project"** (sidebar plus a main group):
  - `company_name` (text)
  - `favicon` (image) + `show_favicon` (true/false)
  - `hero_media`: clone of `image-video`, either an image or a looping mp4 + poster. The poster doubles as the card thumbnail (designer: "first frame from hero").
  - `card_media_override` (optional image/mp4), for when the hero video is too heavy for hover.
  - `lead` (textarea)
  - `show_highlights` (true/false) + `highlights` repeater (`title`, `media` image/video), feeding the Media Modal.
- **Body:** `post_content` built from the new ACF blocks in §6.2. Restrict the post-type allowed block list to those blocks plus core paragraph.

### Options pages
Restructure them under Site Settings:

| Page | Fields |
|---|---|
| Header | logo (SVG), location line 1 / line 2 (with italic), nav menu (WP menu `primary`) |
| Footer | "Get In Touch" heading (rich/em), email, short text, right label ("*Portfolio* 2026"; year auto by default), menus `footer-network` / `footer-sitemap` / `footer-quick-links` |
| Downloads | curriculum (file), digital card (file). Used by the footer + About buttons. |
| Social Media | LinkedIn, Instagram, Behance |
| Featured Projects | `featured_posts` relationship (posts, max **16**, ordered) |
| Default Image | placeholder image (§8) |
| Default Pages | 404 page, home/work page, about page |
| Portfolio Access | see §7 |
| Login (wp-admin) | existing `login_styles`: logo, black bg, white button, link colors |
| Scripts | analytics |
| Feature Toggles | unchanged |

---

## 5. Components (`custom/` unless noted)

| Component | Notes |
|---|---|
| `header` | Top bar (logo + location) sits absolute in page flow. Separate `nav-pill` component sticky at top on desktop and fixed bottom on ≤1024. JS: hide on scroll-down / show on scroll-up via Lenis `scroll` event with native fallback and a threshold. Active-state pill for the current page. Contact → `#contact` (footer) via `scroll-to`. |
| `footer` | `.wrapper.full`, inset panel, Unicorn bg (mouse tracking), `copy-email`, 3 menus, bottom row. `id="contact"`. |
| `copy-email` | States enabled / pressed / copied. `navigator.clipboard.writeText` with a `execCommand` fallback. Reverts after 2000ms. `aria-live="polite"`. Two variants: button (footer) and inline link (login). |
| `card-post` | Media frame (glass surface, inset thumb with p48), favicon + "category *in* company", title (a11y + mobbin hover). Video variant: `preload="none"`, `data-src` loaded on first hover/in-view (wp-image-performance deferred pattern), play on `pointerenter`, pause/reset on leave, top-right pause toggle, no autoplay on touch (tap = navigate). |
| `unicorn-scene` | Lazy-loads the Unicorn Studio SDK (pinned version, cdn or local) only when the element enters the viewport. Params: `json` (uploaded scene JSON/URL), `interactive` (mouse), `sticky`. Pauses offscreen via IntersectionObserver. `prefers-reduced-motion` → static poster image. Guarded by `isFrontend()`. The scene JSON lives in the media library (allow `.json` upload for admins only) or `src/assets/unicorn/`. |
| `media-modal` (extend style-guide `modal`) | Title + close + image/video, blurred dimmed backdrop (`backdrop-filter`), Esc/overlay close, focus trap, `data-lenis-prevent`, video autoplays on open and pauses on close. |
| `filter-chip` (style-guide, restyle) | Pill tab used by the listing filter and the About skills chips (non-interactive variant). |
| `button` (style-guide, restyle) | Variants: pill-solid (Access Now), pill-ghost (Get In Touch / Download / LinkedIn), icon (close/pause). |
| `stat`, `info-table` | Small parts used by post blocks. |

---

## 6. Blocks (`custom/`, category custom-blocks)

### 6.1 Home / generic
- **`hero-home`:**
  - Full-viewport (`100svh`, `vh-fix`) inset panel.
  - Unicorn bg with mouse tracking.
  - Two-tone intro text; headline split into 3 fields (left / center / right: "Creative" "&" "Cool Stuff").
  - Header top-bar overlays it.
- **`project-listing`:**
  - Tabs: Featured (from options) + each category with posts (generated).
  - "Showing (N Results)".
  - Grid columns per breakpoint: 4/4/2/2/1/1 (see §1b).
  - Tab switch via `project-listing-ajax.php` (block-specific, `nopriv`, nonce, sanitized term ID) returning card HTML.
  - First tab rendered server-side (SEO + no CLS).
  - Category tabs load all posts, latest first. Add a "Load more" only if the count grows past about 24.
  - Tab state reflected in `?tab=` for shareable/back-button behaviour.

### 6.2 Post body (allowed only on posts)

| Block | Notes |
|---|---|
| `post-text` | Optional serif heading + rich text. Also covers the lead paragraph when no heading is set. |
| `post-media` | Single image/video in the framed box, caption toggle |
| `post-media-pair` | Exactly 2 media + captions; stacks on mobile |
| `post-stats` | Repeater 1–3 (`label`, `value`, `text`) |
| `post-project-info` | Title + repeater rows (label / value) |

### 6.3 About

| Block | Notes |
|---|---|
| `about-hero` | GIF/image box + headline (em → italic) + text. Sticky Unicorn light on top. |
| `story-rows` | Repeater: label, title, text, years. Divider lines. Intro animation per row. |
| `services-list` | Optional heading + repeater (title, text). Numbering auto `[01]`. |
| `text-section` | Heading (with em) + rich text + optional buttons + optional chips. Covers Approach, Skills & Tools, Inspires and Future Goals, so no separate block per section (block-design-philosophy: reuse). |
| `link-list` | Rounded panel rows: icon + title + description (+ optional link) |

### 6.4 Templates (PHP, not blocks)
- **`single.php`:**
  - Post hero from fields, with the sticky Unicorn light.
  - `the_content()`.
  - Highlights grid + `media-modal`, if enabled.
  - **You May Also Like:** 4 random from `featured_posts` minus the current post, fallback to latest. Shuffle with `shuffle()` in PHP, not `orderby=rand`, so the page stays cacheable-friendly.
  - Footer.
- **`page.php`:** generic, blocks only (About, Home).
- **`404.php`:** uses the Default Pages option. Simple serif message + back-home button, consistent with the login layout.
- **`templates/access-gate.php`:** see §7.

---

## 7. Portfolio access gate (`modules/portfolio-gate/`)

A self-contained module in the same pattern as svg-support/duplicate-post. It's gated by the `PORTFOLIO_GATE_ENABLED` constant **and** an `enable_gate` field.

**Options page "Portfolio Access":**

| Group | Fields |
|---|---|
| Behaviour | `enable_gate`, `password` (stored as a `wp_hash_password` hash on save; the field never re-displays plaintext), `cookie_days` (number, default **2**, min 1, max 30) |
| Login screen copy | `title`, `text`, `placeholder`, `button_label`, `contact_text`, `contact_email` |
| Loading screen | `title`, `text`, `duration_seconds` (default 4, min 1, max 10) |
| Redirect | `redirect_target`: home (default) / originally requested URL |
| Scope | gate whole site (default) vs. listed pages/posts only |

**Flow:**
1. On `template_redirect`, an unauthorised visitor gets the gate template with HTTP **200** + `noindex` + `Cache-Control: no-store`. Requests that are bypassed: logged-in users with `edit_posts`, `wp-admin`, `wp-login`, REST, AJAX, cron, `robots.txt`, `sitemap`, and the favicon.
2. The form POSTs to `admin-post.php` with a nonce. The password is checked with `wp_check_password`.
3. Brute-force protection: a per-IP transient rate limit (for example 5 tries / 15 min).
4. On success, set an HMAC-signed cookie: `hash_hmac(expiry | hash_of_password_hash, wp_salt)`. Changing the password invalidates every session. Flags: `Secure`, `HttpOnly`, `SameSite=Lax`, expiry = `cookie_days`.
5. The gate then renders the **loading state** (spinner + confidential copy) and redirects with a JS timer after `duration_seconds` to the target. A `<meta refresh>` fallback covers no-JS. The success response itself is a lightweight page, not a server sleep.
6. Error state: inline message + shake. Copy from options, with a `$status-3` red.

**Pantheon (critical):**
- Name the cookie `STYXKEY_vp_access`. Pantheon's edge cache varies on `STYXKEY*` cookies; otherwise a gated or ungated page gets served to the wrong visitor.
- Send `no-store` on gate responses.
- Verify on the Pantheon dev env with `curl -I` (Age / X-Cache headers).

The gate also renders the login Unicorn scene (no mouse tracking) and the copy-email link.

---

## 8. Placeholder assets + seed content
- Generate placeholders locally and keep them in `src/assets/images/placeholders/`:
  - Dark gradient/grain images matching the palette: 16:10 card thumb, 16:9 hero, 1:1 About box, square favicon (monogram "C").
  - A 6s seamless gradient loop **mp4** (ffmpeg, H.264, ~300KB) + poster, to exercise the video states.
  - The Figma file only has transparent checkerboards, so these replace them.
- Upload them to the media library:
  - Set Default Image (the theme fallback for empty image fields).
  - Use them for the seed content.
- Seed via WP-CLI script (`.claude/scripts/seed.sh` or a one-off `wp eval-file`):
  - 3 categories.
  - About 20 projects with mixed image/video thumbs, favicon on/off, a full body block set, and highlights on half of them.
  - Featured = 16.
  - About page with every block.
  - Menus.
  - Options filled in (email, socials, downloads with placeholder PDFs, gate copy from Figma).

---

## 9. Animation & interaction checklist
- **Unicorn scenes** (§5) when the assets arrive. Until then, show a static gradient poster that matches the screenshots.
- **Intro animations:** load the `wp-ember-animations` skill.
  - Default `ember-entrance` on sections.
  - Page-load intro on the hero (headline words, intro text, nav) once the designer sends the reference.
  - Keep the ozgur-style section intros. No rewrite-on-hover.
- **Header** hide/show on scroll.
- **Card** hover video and hover surface (heavy-gray → mid-gray).
- **Copy-email** states.
- **Modal** open/close transitions.
- **Reduced motion:** respect it everywhere. Lenis already does.

---

## 10. Execution order
1. **Phase 0, prerequisites:**
   - Unicorn Studio scenes + intro reference from the designer.
   - Real email/socials/CV.
   - Font files (both fonts are OFL on Google Fonts).
2. **Phase 1, cleanup (§3).** Run `npm run build`, then smoke-test that the site renders with an empty page.
3. **Phase 2, foundations (§2):**
   - Breakpoints.
   - Multi-stop `fluid()` with a unit test page (dev-only template rendering every token at each width).
   - Tokens → `:root` custom properties, colors, typography, fonts, wrapper/panel mixins, global dark styles (`body` bg, selection, focus ring, scrollbar).
   - Update the figma-sync docs for 6 modes.
4. **Phase 3, content model (§4):** ACF groups, options pages, menus, Default Image.
5. **Phase 4, components (§5).** Use the component-builder skill and its impact check for each one.
6. **Phase 5, blocks + templates (§6).** Use the block-design-philosophy + block-builder skills, then the auditor where useful. Build in this order: home → single → about.
7. **Phase 6, access gate (§7)**, then a Pantheon cache verification.
8. **Phase 7, placeholders + seed (§8).**
9. **Phase 8, animations (§9)** as the assets arrive.
10. **Phase 9, QA:**
    - Every Figma width (380/600/1024/1280/1440/1920) plus 2560 for the no-max-width hero, header and footer.
    - Touch vs hover.
    - Reduced motion.
    - Lighthouse: LCP is the hero text, and the Unicorn SDK must not block it.
    - Card video payload: decide whether to keep hover video or use the static fallback.
    - Accessibility: tabs = `role="tablist"`, modal focus trap, copy-button `aria-live`.
    - Cookie expiry.
    - i18n sync (`wp-i18n-sync`).
    - Set `SHOW_ACF_ADMIN`/`LOAD_LIBRARY_BLOCKS` before handoff.

---

## 11. Open decisions
1. Author in `style.css`: you, or Victor?
2. Gate scope default: whole site, or projects only (About/Home public)? The plan assumes **whole site**, matching "Welcome To My Portfolio".
3. Hover video on cards versus static thumbs. The plan starts with hover video and measures it in QA, as the designer allowed.
