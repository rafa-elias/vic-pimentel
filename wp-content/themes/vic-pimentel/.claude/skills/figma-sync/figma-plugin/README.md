# Design System Refresh — Figma plugin

## What this does

Some type properties (line-height, font-weight, letter-spacing, font choice) can't be made to update automatically in Figma when you edit them in the Variables panel — that's a real limitation of Figma, not something wrong with this file. Editing a variable changes the stored value, but the text on the Style Guide page keeps showing the old look until something explicitly reapplies it.

This plugin is that "something." One click, and every type style on the Style Guide page updates to match whatever the variables currently say.

**Run it any time after you've edited Typography variables, before you screenshot or present the Style Guide — including before showing anything to a client.** If you skip this step, what you're looking at may not be what the variables actually say.

---

## Installing it (one-time setup, Figma Desktop app only)

This only works in the **Figma desktop app** — the browser version of Figma can't import local plugins like this.

1. Open the Figma desktop app.
2. Open the file (or any file — this only needs to be done once per computer).
3. Go to the top-left **Figma menu → Plugins → Development → Import plugin from manifest…**
   (Or right-click anywhere on the canvas → **Plugins → Development → Import plugin from manifest…**)
4. In the file picker, navigate to this folder and select **`manifest.json`**.
5. Done. The plugin now shows up under **Plugins → Development → Rear Theme — Design System Refresh** in every Figma file you open on this computer.

If a teammate also needs to run this, they each need to do this same one-time import on their own computer — a locally-imported plugin isn't automatically shared with the rest of the team.

---

## Running it

1. Open the Figma file with the Style Guide.
2. **Plugins → Development → Rear Theme — Design System Refresh**
3. That's it. A small notification appears in the bottom of the screen confirming how many type styles were updated. No panel, no questions, no waiting.

If the notification says something was **skipped**, open **Plugins → Development → Open Console** to see exactly which style and why. Two common, harmless reasons:
- A style's `font-weight` was set to a value that isn't installed for that font family — e.g. setting `900` (Black) on a style that uses a font with no Black variant. The console message will list which weights actually are available for that family.
- A variable is missing entirely (renamed or deleted).

All 9 standard weight values are supported (`100` Thin through `900` Black) — you're not limited to just Regular/Bold. Whether a given weight actually works depends on what styles are installed for that font in Figma, which the plugin checks automatically before applying anything.

---

## What it does *not* do

- It doesn't touch the actual website code — it only makes the Figma file honest about what its own variables currently say. Getting a variable change into the real SCSS/CSS is a separate step (ask whoever handles the Figma → code sync).
- It doesn't fix `font-size`, colors, or spacing — those already update live in Figma the normal way and don't need this.
