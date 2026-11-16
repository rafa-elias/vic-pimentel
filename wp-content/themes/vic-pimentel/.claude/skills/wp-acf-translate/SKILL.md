---
name: wp-acf-translate
description: Use when the user wants to translate ACF field group JSON display strings (labels, instructions, choices, messages) between English and Brazilian Portuguese — never the field `name` slugs, `key`s, or the field group `title`. Always asks source→target language and which acf-json scope (root, blocks, or both) before touching any file.
---

# ACF field translation

Translates the human-facing strings inside ACF field-group JSON — `label`, `instructions`, `placeholder`, `message`, `choices` values, and a few narrower cases — without touching anything the code or ACF's own save/load routing actually reads. Reuses the verification tool built for the block-builder skill's library-block-locale workflow (`.claude/skills/wp-framework-block-builder/references/verify-acf-translation.php`) so every edit is checked mechanically, not just by eye.

This is a different job from `.claude/skills/wp-i18n-sync/SKILL.md`: that one syncs gettext `__()`/`_e()` PHP strings via `.pot`/`.po`/`.mo`. This one edits ACF JSON field groups directly, in place — there's no separate compiled artifact, WordPress reads the JSON straight off disk.

---

## 0. Always ask first — every run, no assumptions

Never infer these from context or a prior run. Ask both before touching any file:

1. **Direction** — "Portuguese (Brazil) → English" or "English → Portuguese (Brazil)"
2. **Scope**:
   - **Blocks only** — `src/content/blocks/**/acf-json/*.json` (the common case — say so, but don't default to it without asking)
   - **Root only** — `acf-json/**/*.json` (global fields, custom fields, options pages, menus, post types, templates)
   - **Both**

If the user names specific blocks or files instead of picking a scope bucket ("just the header and footer blocks"), translate only those and skip the scope question — they've already answered it.

Exclude `.remove-me` placeholder files and `acf-json/index.php` from every scope.

---

## 1. What's translatable vs. what's never touched

**Translatable — display strings only (what the editor *sees*):**
- Field `label`, `instructions`, `placeholder`, `aria-label`
- Repeater/flexible-content `button_label` (e.g. `"Adicionar linha"` ↔ `"Add row"`)
- `message` field text
- `true_false`'s `ui_on_text` / `ui_off_text`
- `prepend` / `append` — **only** when they're real words (`"por mês"` ↔ `"per month"`); leave symbols and units alone (`R$`, `%`, `px`)
- `choices` **values** — the human-readable side of each pair (`"left": "Esquerda"` ↔ `"left": "Left"`)
- `default_value` — **only** on `text`, `textarea`, or `wysiwyg` fields, where it's real display copy, not a reference

**Never touched — data, or routing, not display:**
- `name` — the slug the render template reads (`$fields['overline']`); always stays as-is regardless of language
- `key`, `type`, `clone` references, `location`, `conditional_logic`, `collapsed`, `parent_repeater`, `wrapper`
- `choices` **keys**, and `default_value` on choice-based fields (`select`, `radio`, `checkbox`, `button_group`) — it references a choices key; translating it silently breaks the field
- **The field group `title` — at every scope, always.** For block-level groups it's the locale-neutral `"Block: {Name}"` form tying the group to its block folder. For root-level groups (`acf-json/global-fields/`, `custom-fields/`, `options-pages/`, etc.) the title is the literal source `core/acf.php` derives the on-disk filename from (see CLAUDE.md's ACF JSON conventions table) — translating it doesn't rename the file, so the next time someone re-saves that field group from wp-admin, ACF writes a *new* file under the new title's filename and orphans the old one. Never translate it, even if it reads as ordinary text like `"Cor de Fundo"`.
- Cloned fields (`headline`, `buttons`, `image-video`, etc. in `acf-json/custom-fields/`) don't need translating from inside a block's own JSON — the clone field's own `label` is fair game, but the referenced group lives in its own file and gets its own pass if it needs one.

Recursion is generic, not path-specific: walk every nested array in the field tree (`sub_fields`, `layouts.*.sub_fields`, whatever the field type nests under) and translate the listed keys wherever they appear. Don't hand-write separate logic per field type (repeater vs. group vs. flexible content) — a single recursive walk reaches all of them the same way the verification script's `strip_display()` does.

---

## 2. Workflow, per file

1. **Snapshot** the current content before editing — `git show HEAD:<path>` into a scratchpad temp file if the file is tracked and clean, otherwise just copy it. This is the "original" side for verification; do this before writing anything.
2. **Translate in place**: walk the field tree, translate only the §1 keys in the requested direction, leave everything else — including key order and 4-space indentation — byte-identical. Don't run the file through a generic JSON pretty-printer that reorders keys; only change string values.
3. **Verify**:
   ```bash
   php .claude/skills/wp-framework-block-builder/references/verify-acf-translation.php <snapshot> <edited-file>
   ```
   Must print `OK`. A `FAIL` means the edit touched something structural (a slug, a choices key, a `default_value` it shouldn't have) — undo and redo that file rather than forcing it through or hand-waving the diff.

No build step follows — ACF reads JSON straight off disk (`acf/settings/load_json`), there's nothing to compile or cache-bust. Confirm by reloading the field group / block editor screen.

---

## 3. After all files pass

- `git diff --stat` on every touched file — each should show only modified lines, never added/removed lines (a translation never changes the number of keys).
- Report: how many files translated, which direction, which scope, and any files skipped (empty string, or a string that already looked like it was in the target language — flag those for a human glance rather than silently skipping).
