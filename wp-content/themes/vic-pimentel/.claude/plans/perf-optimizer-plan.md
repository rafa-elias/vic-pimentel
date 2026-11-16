# perf-optimizer module — build plan

Status: not started. Scoped 2026-07-12, not yet executed.

Goal: mimic the highest-ROI parts of WP Rocket's PageSpeed wins as a self-contained module under `modules/perf-optimizer/`, following the same architecture as `modules/media-optimizer/` (own namespace, own `Settings` option, `feature_perf_optimizer` toggle + hard `PERF_OPTIMIZER_ENABLED` constant, deferred `init`, plugin-extractable from day one — see CLAUDE.md's "Native modules" and "MediaTrim module" sections for the exact pattern to replicate). Add its explicit `require_once` to `functions.php` alongside the other `modules/` entries — never let it get picked up by `core/`'s recursive auto-require.

## Architectural note specific to this theme

Per-block/per-component CSS+JS enqueue (only `public/blocks/{name}.css` for blocks actually on the page) already does half of what WP Rocket's "Remove Unused CSS" exists to fix elsewhere. The real render-blocking surface here is the **global stylesheet** (`public/app.css` — abstracts/common/header/footer) and global `app.js`. Scope critical-CSS work there, not per-block.

## Phase 1 — universal, low risk, ship first (~15-25k tokens)

- DB cleanup: revisions, expired transients, trashed posts/spam comments — scheduled + manual, chunked AJAX like MediaTrim's bulk tools
- Heartbeat API throttle (admin-only, configurable interval)
- Preload LCP image / key fonts (`fetchpriority`, `rel=preload`) — extends what the `image` component already does
- DNS-prefetch/preconnect registry for known 3rd-party origins (GTM, YouTube, Google Fonts if not self-hosted)

## Phase 2 — medium complexity (~20-30k tokens)

- Defer non-critical enqueued JS (`defer`/`async` attribute injection with a per-handle exclude list)
- Delay-execution for 3rd-party scripts (GTM, chat widgets, CF7 if below fold) until first scroll/click/timeout — WP Rocket's actual biggest PageSpeed win, moderate complexity (wrap `<script>` tags, replay on interaction)

## Phase 3 — the hard, high-payoff piece (~30-40k tokens)

- Critical CSS for `app.css` only: above-fold extraction (needs headless Chrome — Puppeteer/Playwright, run server-side or via a build-time job, not request-time), inline it, async-load the rest
- Per-template caching of the critical CSS payload (home/single/archive differ)
- Explicitly **not** touching block-scoped CSS — already lean by design

## Phase 4 — optional, host-dependent, off by default with hard warnings

Everything below is gated by an **auto-detect + hard-disable**, same pattern as the SVG-upload fail-closed rule: check `defined('PANTHEON_ENVIRONMENT')` and known markers for WP Engine (`WPE_APIKEY`), Kinsta (`KINSTA_CACHE_ZONE`), Cloudways cache headers — if any match, these modules refuse to activate regardless of the ACF toggle, with an admin notice explaining why.

- **File-based full-page cache + preload crawler** — only meaningful on plain VPS/shared hosting with no edge cache. On Pantheon this doubles-caches, desyncs purges (Pantheon's Varnish purges on save via its own hooks; a second cache layer won't know to purge), and is a known WP Rocket failure mode on exactly these hosts — WP Rocket itself disables its own page cache when it detects Pantheon/WPE/Kinsta.
- **GZIP/Brotli** — server/webserver config, not a PHP plugin's job; ship as a doc snippet (.htaccess/nginx), not runtime code
- **CDN URL rewriting** — actually safe and useful on *any* host including Pantheon (Pantheon has no CDN by default). Keep this one out of the "risky" bucket — just needs the client to already have Cloudflare/Bunny/etc. configured; asset-URL rewrite only, no caching logic

## Sequencing

Default build regardless of host: Phase 1 → 2 → 3. Phase 4 fully opt-in per project, never on by default.
