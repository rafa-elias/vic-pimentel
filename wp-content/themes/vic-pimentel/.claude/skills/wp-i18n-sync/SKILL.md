---
name: wp-i18n-sync
description: Use when the user asks to sync/update translations, regenerate the .pot/.po/.mo files, or check for missing rear-theme text-domain strings after adding or editing __()/_e() calls in the theme. Regenerates languages/rear-theme.pot via WP-CLI, merges it into languages/pt_BR.po, translates any newly-empty strings, and rebuilds pt_BR.mo.
---

# rear-theme i18n sync

Keeps `languages/rear-theme.pot`, `languages/pt_BR.po`, and `languages/pt_BR.mo` in sync with the `rear-theme` text-domain strings actually present in the codebase. Run this whenever new `__()` / `_e()` / `_x()` / `_n()` / `esc_html__()` / `esc_attr__()` calls (or similar) have been added or changed with `'rear-theme'` as the domain.

This theme currently ships a **single locale, pt_BR** (see CLAUDE.md's "Portuguese locale" section) — there is no `en_US.po`, since English is the literal `msgid`/fallback text already.

## 0. Prerequisites

- Lando must be running (`lando start` if not) — WP-CLI's `i18n` commands run inside the container.
- All commands below assume the shell's cwd is the theme root (`wp-content/themes/rear-theme`), but `lando` itself must resolve from the site root (`wordpress-base-br/`) — either `cd` there for the `lando wp` calls or prefix a subshell. The container path for this theme is `/app/wp-content/themes/rear-theme`.

## 1. Regenerate the .pot file

```bash
cd <site-root>   # the folder containing .lando.yml
lando wp i18n make-pot \
  /app/wp-content/themes/rear-theme \
  /app/wp-content/themes/rear-theme/languages/rear-theme.pot \
  --exclude=design-references,src/node_modules,public \
  --domain=rear-theme \
  --skip-block-json
```

Notes on the flags — don't drop them without a reason:
- `--exclude=design-references,src/node_modules,public`: `.claude/design-references/` is foreign prior-art code (never real theme source, see CLAUDE.md), `public/` is compiled Gulp output (never hand-edited, shouldn't be scanned), `src/node_modules` is a dependency tree.
- `--skip-block-json`: block.json `title`/`keywords` strings have no `textDomain` key in this theme's generated block.json files, so they aren't actually `rear-theme`-domain strings — WP-CLI still extracts them by default because it's scanning a theme, which would inject ~150 lines of unrelated block-title noise into the .pot/.po. Only drop this flag if the project deliberately starts wrapping block titles in a textdomain and wants them translated too — that's a separate decision, flag it to the user rather than assuming it.

Ignore the `Warning: ... contains placeholders but has no "translators:" comment` lines for the `core/core.php` CPT-label strings (`Add New %s`, `Edit %s`, etc.) — these are stable WordPress core label placeholders, not new work.

## 2. Diff-check before merging

`git diff languages/rear-theme.pot` and confirm every change is one of:
- A line-number shift (expected after any refactor that moves code — e.g. the component-tiers split changed `src/content/components/{name}/` → `src/content/components/{tier}/{name}/` for every entry)
- A genuinely new `msgid` (new text added since last sync)
- A removed `msgid` (text deleted from source — safe to drop, gettext already stops tracking it)

If anything else changed (e.g. an unrelated flag stripped, a slew of `block.json` entries reappearing), stop and re-check the command before proceeding — don't merge a .pot that pulled in scope beyond text-domain strings.

## 3. Merge into pt_BR.po

```bash
lando wp i18n update-po \
  /app/wp-content/themes/rear-theme/languages/rear-theme.pot \
  /app/wp-content/themes/rear-theme/languages/pt_BR.po
```

This preserves every existing translation and only adds empty `msgstr ""` entries for genuinely new strings (and drops entries for strings no longer in source). It does **not** refresh the file header's `POT-Creation-Date` / `PO-Revision-Date` — update those two lines by hand to match the `.pot`'s new `POT-Creation-Date` and today's date, so the header isn't stale.

## 4. Find and fill untranslated strings

`update-po` leaves new strings as `msgid "..."` / `msgstr ""` pairs. Find them:

```bash
grep -B1 '^msgstr ""$' languages/pt_BR.po | grep '^msgid'
```

(gettext's `msgattrib --untranslated` isn't installed in this environment — the grep above is the fallback.) For each one, write the Portuguese translation:

- Match the tone of existing entries in the file — short, direct, no unnecessary formality (see e.g. `"Nenhum %s encontrado"`, or the `bail_message` example in `src/content/blocks/custom/slider-materiais/config.php`).
- If the English/Portuguese word is identical (`Login`, `Scripts`, `Wrappers`), keep `msgstr` the same as `msgid` — this file already does that throughout, don't leave it empty.
- If the `msgid` itself is already Portuguese text (a literal Portuguese string passed as the first arg to `__()`, e.g. a button label authored directly in Portuguese since the site is Portuguese-first), set `msgstr` to the same text — don't invent a different phrasing.
- Preserve the em-dash style (`—`) used elsewhere in the theme's copy (e.g. block bail messages) rather than a hyphen.

Also check whether the theme name/description header itself changed (`style.css`'s `Theme Name` / `Description`) — `make-pot` re-extracts those, and if the name changed (e.g. "Base Theme" → "Base Theme v2") it'll show up as a new untranslated entry too.

## 5. Rebuild the .mo file

```bash
lando wp i18n make-mo \
  /app/wp-content/themes/rear-theme/languages/pt_BR.po \
  /app/wp-content/themes/rear-theme/languages
```

## 6. Verify

Load the freshly-built `.mo` and echo back each string that was added or changed this run — don't just trust the file diff:

```bash
lando wp eval '
load_textdomain("rear-theme", "/app/wp-content/themes/rear-theme/languages/pt_BR.mo");
echo __("<new or changed msgid>", "rear-theme") . PHP_EOL;
'
```

Confirm each one prints the Portuguese translation, not the English fallback.

## 7. Final review

`git diff languages/` and read it end to end:
- Every previously-translated string should still have its translation (nothing should have gone from a filled `msgstr` to empty).
- Line-number-only changes should track real code moves, not a scan-path mistake.
- New strings should all have non-empty `msgstr` before you consider the sync done.
