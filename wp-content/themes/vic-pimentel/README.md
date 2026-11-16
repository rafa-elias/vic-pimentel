# Vic Pimentel — portfolio theme

Portfolio for Victor Pimentel (Product Designer & Illustrator), built on Rafael Elias's rear-theme base (WordPress + ACF Pro blocks, no framework).

- Design: Figma file in `.theme.json` → `figmaUrl` (designer comments are pink "Designer comment" annotations).
- Conventions and project notes: **[CLAUDE.md](CLAUDE.md)** ("Vic Pimentel project notes").
- Build plan / status: `.claude/plans/vic-pimentel-build-plan.md`.
- Local demo content: `lando wp eval-file wp-content/themes/vic-pimentel/.claude/seed/seed.php` (password gate demo password: `portfolio`).

---

## Requirements

- WordPress with the block editor (Gutenberg) enabled
- PHP 8.0+ (the codebase uses `str_starts_with()` and typed properties)
- **Advanced Custom Fields PRO** — required, not optional. Blocks, options pages, global fields, and cloned field groups all depend on it.
- Node.js + npm, for the asset build (Gulp + Webpack)
- Recommended plugins already bundled in this stack: Yoast SEO, Contact Form 7, Duplicate Post, Regenerate Thumbnails, SVG Support

## Getting started

```bash
# 1. Install JS build dependencies
cd src
npm install

# 2. Start the dev build (clean build + watch)
npm start

# or, for a one-shot production build
npm run build
```

While developing, enable `WP_DEBUG` in `wp-config.php`. Block `block.json` files are only (re)generated on page load when `WP_DEBUG` is `true` — with it `false`, `block.json` is treated as static and never rewritten. **Never hand-edit `block.json`** — it's generated from each block's `config.php`.

Never run a manual `gulp`/`sass` build to "verify" a block or component change — `npm start` is expected to already be running in watch mode during development.

## Architecture at a glance

```
core/       Framework layer — block/component discovery, ACF JSON routing, asset enqueuing, cleanup
app/        Project layer — config.php settings constants, post types, taxonomies, menus, options pages, project-specific utils
src/        Source assets (PHP templates + SCSS + JS) — compiled into public/
public/     Build output. Never edit directly — always edit src/ and let Gulp/Webpack regenerate.
acf-json/   ACF field group JSON (English)
acf-json-br/  Same field groups, Portuguese labels — pick one locale per project, delete the other
templates/  WordPress page templates + template parts
.claude/    Claude Code assets — skills, plans, design-references (all versioned)
```

`functions.php` recursively `require`s every `.php` file under `core/` and `app/` — no manual registration wiring needed for either layer.

### Blocks

ACF Blocks are auto-discovered from `src/content/blocks/**` on `init` (`scan_and_register_blocks()` in `core/blocks.php`), four categories:

| Folder | Purpose |
|---|---|
| `library/` | Shared, wireframe-styled reference blocks (text, banner, carousel, accordion, etc.) — gated by the `LOAD_LIBRARY_BLOCKS` constant, see below |
| `inner-blocks/` | Blocks designed to live inside a wrapper |
| `wrappers/` | InnerBlocks container blocks |
| `custom/` | Project-specific blocks |

**`LOAD_LIBRARY_BLOCKS`** (in `app/config.php`, loaded before `core/`; `core/blocks.php` falls back to `false` if undefined) is a hardcoded `true`/`false` switch — not an ACF option, so a client can never toggle it from wp-admin. Keep it `true` while developing against the shared block library; set it `false` before handing a project to a client so `library/` blocks disappear from the inserter while the source stays in the repo as reference.

Each block's `config.php` also supports optional framework-only keys, collected by `scan_and_register_blocks()` and never written to `block.json`: `field_defaults` (per-block default value for any field, including shared global fields like `bg_color`), `field_choices` (restrict, and optionally relabel, which options a select/radio/checkbox field offers, per block — e.g. limit `bg_color` to a subset of the full palette, with custom labels for that block), `repeater_content` (initial rows a freshly inserted block starts with), and `bail_message` (editor-preview notice when the block renders nothing). See CLAUDE.md's "Block registration" section and `.claude/skills/wp-framework-block-builder/SKILL.md` §3 for syntax and details.

### Components

Reusable UI pieces live in `src/content/components/{tier}/{name}/{name}.php`, in three tiers:

| Tier | Purpose |
|---|---|
| `style-guide/` | Base design-system building blocks (button, headline, image-video, modal, tabs, etc.) |
| `custom/` | Project-specific components (header, footer, mega-menu, card-material, etc.) |
| `library/` | Reference-only components not chosen for this project — source stays, but compiled CSS/JS is excluded from enqueue automatically |

Callers never specify the tier — `component('button', $args)` resolves it automatically (`resolve_component_folder()` in `core/core.php`).

### ACF field groups

Field group JSON auto-routes to the right folder based on the **title prefix** (`Global Field: *`, `Custom Field: *`, `Block: {name}`, etc.) — see CLAUDE.md's "ACF JSON conventions" section for the full routing table. Global fields (spacing, background color) are appended to every block automatically; custom fields (headline, button, link, form...) exist only to be cloned into block-specific field groups.

### Asset pipeline

Gulp compiles SCSS (dart-sass + autoprefixer) and Webpack bundles JS, per content type (blocks, components, sections, templates, global, admin, static) — each compiles independently in watch mode, so only the changed file rebuilds. `.theme.json` at the theme root defines the source paths the build discovers.

SCSS abstracts (`src/styles/abstract/`) provide `rem()` and `fluid()` sizing functions, a breakpoint map, and design tokens — see CLAUDE.md's "Sizing functions" section for when to use which.

### Design references

`.claude/design-references/{client-name}/{block-name}/` holds blocks pasted in from other projects, kept purely as prior art for building new blocks or Figma designs — nothing in it runs. It lives outside `src/`, `core/`, and `app/` specifically so it's never auto-required, registered as a block, or compiled, no matter how similar its folder shape looks to `src/content/blocks/`. See `.claude/design-references/README.md` for the full rules, and check `.claude/design-references/INDEX.md` before starting a new block for relevant prior art.

## Preparing a project for client handoff

1. Set `LOAD_LIBRARY_BLOCKS` and `SHOW_ACF_ADMIN` to `false` in `app/config.php`.
2. Set `WP_DEBUG` to `false` in `wp-config.php`.
3. Pick one ACF JSON locale — rename either `acf-json-br/` to `acf-json/` (replacing it) or just delete `acf-json-br/`, so only one locale is registered.
4. Run `npm run build` for a final production asset build.

## Using this as a starting point for a new project

1. Clone the theme.
2. Update `.theme.json`'s `figmaUrl` to the new project's own Figma file — it starts out pointing at this base theme's shared design system file, and every AI-assisted Figma sync session reads that field directly.
3. Decide the project's ACF JSON locale early (see step 3 above) rather than maintaining both.
4. Build out `custom/` blocks and components; keep `library/` and `style-guide/` as the shared reference layer.
5. Delete `.claude/design-references/` from the clone (or add it to that project's `.gitignore`) — it's a framework-only research library, often containing other clients' code, and has no reason to ship in a specific client's repo.

## Figma design system sync

This theme's SCSS design tokens can be synced to/from a Figma file's Variables collections, and individual built components (e.g. Button) can be synced property-by-property. See `.claude/skills/figma-sync/` — these are living drafts (not yet finalized as slash commands), but cover real, exercised workflows:

- `scss-to-figma.md` — push SCSS tokens to Figma
- `figma-to-scss.md` — pull Figma Variable edits back into SCSS
- `figma-to-component-scss.md` — pull a built component's Figma edits (colors, spacing, states) back into its SCSS/PHP
- `figma-plugin/` — an installable Figma plugin that reapplies variable values to the Style Guide canvas (several Text Style properties are literal snapshots in Figma and silently go stale when the underlying variable changes)

## Where to look next

- **[CLAUDE.md](CLAUDE.md)** — the full framework reference: block/component authoring patterns, `render()`/`component()`/`parts_path()` internals, ACF JSON routing rules, naming conventions, SCSS token functions
- **`.claude/skills/wp-framework-block-builder/SKILL.md`** — step-by-step guide for building a new block end-to-end, including CPT-backed blocks and card components
- **`.claude/skills/figma-sync/`** — Figma ↔ SCSS/component sync workflows
- **`.claude/design-references/README.md`** — the prior-art library of blocks pasted from other projects
