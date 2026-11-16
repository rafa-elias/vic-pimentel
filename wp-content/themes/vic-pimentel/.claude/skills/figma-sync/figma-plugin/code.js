/**
 * Rear Theme — Design System Refresh
 *
 * Figma cannot bind line-height, font-weight, letter-spacing, or font-family
 * live to a Text Style (confirmed by direct testing, not assumption — see
 * .claude/skills/figma-sync/scss-to-figma.md §5). Editing those variables in the
 * Variables panel changes the documented value but does NOT change what
 * renders on canvas. This plugin closes that gap: run it after editing any
 * Typography Static variable, before trusting what the Style Guide shows.
 *
 * It does two things:
 *   1. Re-applies current Typography Static values (line-height, font-weight,
 *      letter-spacing, font-family) onto every Text Style that belongs to
 *      this design system.
 *   2. Regenerates the caption text under each specimen in the Style Guide's
 *      Typography frame, so the labels match what's actually rendering.
 *
 * Style discovery is dynamic, not hardcoded: a Text Style is recognized as
 * part of this system if its bound font-size variable is named
 * "{style-name}/font-size" — that name is the key used to look up the rest
 * of that style's variables. No style IDs are stored anywhere in this file.
 *
 * IMPORTANT — Figma's plugin sandbox (code.js runs in a restricted JS engine,
 * not a full browser) does not support object/array spread syntax (`...`).
 * Do not reintroduce it here — build objects with explicit property
 * assignment instead, e.g. `var o = {}; o.a = 1;` not `{ ...x, a: 1 }`.
 */

// Standard CSS font-weight scale mapped to Figma's typical style names.
// Not every family has every weight installed — availability is checked
// at runtime via figma.listAvailableFontsAsync(), not assumed from this map.
var WEIGHT_TO_FIGMA_STYLE = {
  100: "Thin",
  200: "Extra Light",
  300: "Light",
  400: "Regular",
  500: "Medium",
  600: "Semi Bold",
  700: "Bold",
  800: "Extra Bold",
  900: "Black",
};

function concatAll(arrays) {
  var result = [];
  for (var i = 0; i < arrays.length; i++) {
    result = result.concat(arrays[i]);
  }
  return result;
}

async function resolveStyleName(textStyle) {
  var bound = textStyle.boundVariables && textStyle.boundVariables.fontSize;
  if (!bound) return null;
  var variable = await figma.variables.getVariableByIdAsync(bound.id);
  if (!variable) return null;
  var parts = variable.name.split("/");
  if (parts.length !== 2 || parts[1] !== "font-size") return null;
  return parts[0];
}

function resolveAliasChain(startValue, allVariablesById) {
  var current = startValue;
  var depth = 0;
  while (current && typeof current === "object" && current.type === "VARIABLE_ALIAS") {
    if (depth++ > 5) throw new Error("Alias chain too deep — possible circular reference");
    var next = allVariablesById.get(current.id);
    if (!next) throw new Error("Broken alias — target variable not found: " + current.id);
    var nextModeId = Object.keys(next.valuesByMode)[0];
    current = next.valuesByMode[nextModeId];
  }
  return current;
}

async function buildVariableIndex() {
  var collections = await figma.variables.getLocalVariableCollectionsAsync();
  var typoStatic = null;
  for (var i = 0; i < collections.length; i++) {
    if (collections[i].name === "Typography Static") typoStatic = collections[i];
  }
  if (!typoStatic) {
    throw new Error('"Typography Static" collection not found — is this the right file?');
  }

  var allIds = concatAll(
    collections.map(function (c) {
      return c.variableIds;
    })
  );
  var allVars = await Promise.all(
    allIds.map(function (id) {
      return figma.variables.getVariableByIdAsync(id);
    })
  );

  var allVariablesById = new Map();
  var byName = new Map();
  for (var j = 0; j < allVars.length; j++) {
    var v = allVars[j];
    if (v) {
      allVariablesById.set(v.id, v);
      byName.set(v.name, v);
    }
  }

  var staticModeId = typoStatic.modes[0].modeId;

  return { byName: byName, allVariablesById: allVariablesById, staticModeId: staticModeId };
}

async function resolveStyleProperties(styleName, index, availableFontsByFamily) {
  var lhVar = index.byName.get(styleName + "/line-height");
  var fwVar = index.byName.get(styleName + "/font-weight");
  var lsVar = index.byName.get(styleName + "/letter-spacing");
  var ffVar = index.byName.get(styleName + "/font-family");

  if (!lhVar || !fwVar || !lsVar || !ffVar) {
    return { error: "missing one or more Typography Static variables" };
  }

  var lineHeight = lhVar.valuesByMode[index.staticModeId];
  var weight = fwVar.valuesByMode[index.staticModeId];
  var letterSpacing = lsVar.valuesByMode[index.staticModeId];
  var fontFamily = resolveAliasChain(ffVar.valuesByMode[index.staticModeId], index.allVariablesById);

  if (typeof lineHeight !== "number" || typeof weight !== "number" || typeof letterSpacing !== "number") {
    return { error: "one or more values did not resolve to a number" };
  }
  if (typeof fontFamily !== "string") {
    return { error: "font-family did not resolve to a string" };
  }

  var guessedWeightStyle = WEIGHT_TO_FIGMA_STYLE[weight];
  if (!guessedWeightStyle) {
    return { error: "unrecognized font-weight value: " + weight + " (supported: " + Object.keys(WEIGHT_TO_FIGMA_STYLE).join(", ") + ")" };
  }

  var installedStyles = availableFontsByFamily.get(fontFamily);
  if (!installedStyles) {
    return { error: 'font family "' + fontFamily + '" is not installed/available in Figma' };
  }

  // Different font families/foundries spell the same weight differently in
  // their installed style names (e.g. Inter ships "Semi Bold" with a space,
  // Plus Jakarta Sans ships "SemiBold" without one). Match case/space-insensitively
  // against what's actually installed instead of requiring an exact string match,
  // and use the real installed name (not the guess) when applying the font.
  var normalize = function (s) {
    return s.toLowerCase().replace(/\s+/g, "");
  };
  var figmaWeightStyle = null;
  for (var s = 0; s < installedStyles.length; s++) {
    if (normalize(installedStyles[s]) === normalize(guessedWeightStyle)) {
      figmaWeightStyle = installedStyles[s];
      break;
    }
  }
  if (!figmaWeightStyle) {
    return { error: '"' + fontFamily + '" has no "' + guessedWeightStyle + '" style installed (available: ' + installedStyles.join(", ") + ")" };
  }

  return {
    lineHeight: lineHeight,
    weight: weight,
    letterSpacing: letterSpacing,
    fontFamily: fontFamily,
    figmaWeightStyle: figmaWeightStyle,
  };
}

async function buildAvailableFontsIndex() {
  var fonts = await figma.listAvailableFontsAsync();
  var byFamily = new Map();
  for (var i = 0; i < fonts.length; i++) {
    var family = fonts[i].fontName.family;
    var style = fonts[i].fontName.style;
    if (!byFamily.has(family)) byFamily.set(family, []);
    byFamily.get(family).push(style);
  }
  return byFamily;
}

async function refreshTextStyles(index, availableFontsByFamily) {
  var textStyles = await figma.getLocalTextStylesAsync();
  var plan = [];
  var skipped = [];

  for (var i = 0; i < textStyles.length; i++) {
    var style = textStyles[i];
    var styleName = await resolveStyleName(style);
    if (!styleName) continue; // not one of this system's type styles

    var props = await resolveStyleProperties(styleName, index, availableFontsByFamily);
    if (props.error) {
      skipped.push(styleName + " (" + props.error + ")");
      continue;
    }

    plan.push({
      style: style,
      styleName: styleName,
      lineHeight: props.lineHeight,
      letterSpacing: props.letterSpacing,
      fontFamily: props.fontFamily,
      figmaWeightStyle: props.figmaWeightStyle,
    });
  }

  var fontsToLoad = new Map();
  for (var p = 0; p < plan.length; p++) {
    var key = plan[p].fontFamily + "::" + plan[p].figmaWeightStyle;
    fontsToLoad.set(key, { family: plan[p].fontFamily, style: plan[p].figmaWeightStyle });
  }
  await Promise.all(
    Array.from(fontsToLoad.values()).map(function (f) {
      return figma.loadFontAsync(f);
    })
  );

  for (var q = 0; q < plan.length; q++) {
    var item = plan[q];
    item.style.fontName = { family: item.fontFamily, style: item.figmaWeightStyle };
    item.style.lineHeight = { unit: "PERCENT", value: item.lineHeight };
    item.style.letterSpacing = { unit: "PERCENT", value: item.letterSpacing };
  }

  return {
    updated: plan.map(function (p) {
      return p.styleName;
    }),
    skipped: skipped,
  };
}

async function refreshCaptions(index, availableFontsByFamily) {
  var styleGuidePage = null;
  for (var i = 0; i < figma.root.children.length; i++) {
    // Page names carry decoration (e.g. "      ❐  Style Guide"), so match by inclusion.
    if (figma.root.children[i].name.indexOf("Style Guide") !== -1) styleGuidePage = figma.root.children[i];
  }
  if (!styleGuidePage) return { updated: [], skipped: ["Style Guide page not found — skipped caption refresh"] };

  await figma.setCurrentPageAsync(styleGuidePage);

  var typographyFrame = null;
  for (var j = 0; j < styleGuidePage.children.length; j++) {
    var child = styleGuidePage.children[j];
    if (child.type === "FRAME" && child.name === "Typography") typographyFrame = child;
  }
  if (!typographyFrame) return { updated: [], skipped: ["Typography frame not found on Style Guide page"] };

  await figma.loadFontAsync({ family: "Inter", style: "Regular" });

  var updated = [];
  var skipped = [];
  var specimenFrames = typographyFrame.findAll(function (n) {
    return n.type === "FRAME" && n.name.indexOf("specimen/") === 0;
  });

  for (var k = 0; k < specimenFrames.length; k++) {
    var block = specimenFrames[k];
    var styleName = block.name.split("/")[1];
    var caption = null;
    for (var c = 0; c < block.children.length; c++) {
      if (block.children[c].type === "TEXT") {
        caption = block.children[c];
        break;
      }
    }
    if (!caption) {
      skipped.push(styleName + " (no caption text node found)");
      continue;
    }

    var props = await resolveStyleProperties(styleName, index, availableFontsByFamily);
    if (props.error) {
      skipped.push(styleName + " (" + props.error + ")");
      continue;
    }

    var segments = caption.getStyledTextSegments(["fontName"]);
    await Promise.all(
      segments.map(function (s) {
        return figma.loadFontAsync(s.fontName);
      })
    );

    caption.characters =
      "$" + styleName + "  ·  " + props.fontFamily + " " + props.figmaWeightStyle + "  ·  weight " + props.weight +
      "  ·  line-height " + props.lineHeight / 100 + "  ·  letter-spacing " + props.letterSpacing / 100 + "em";
    updated.push(styleName);
  }

  return { updated: updated, skipped: skipped };
}

(async function () {
  try {
    var index = await buildVariableIndex();
    var availableFontsByFamily = await buildAvailableFontsIndex();
    var textStyleResult = await refreshTextStyles(index, availableFontsByFamily);
    var captionResult = await refreshCaptions(index, availableFontsByFamily);

    var totalUpdated = textStyleResult.updated.length;
    var totalSkipped = textStyleResult.skipped.length + captionResult.skipped.length;

    var message = "Refreshed " + totalUpdated + " type style" + (totalUpdated === 1 ? "" : "s");
    if (totalSkipped > 0) {
      message += " — " + totalSkipped + " skipped, see plugin console (Plugins → Development → Open Console)";
      console.log("Design System Refresh — skipped (Text Styles):", textStyleResult.skipped);
      console.log("Design System Refresh — skipped (Captions):", captionResult.skipped);
    }

    figma.notify(message, { timeout: 4000 });
  } catch (e) {
    figma.notify("Design System Refresh failed: " + e.message, { error: true, timeout: 6000 });
    console.error(e);
  }
  figma.closePlugin();
})();
