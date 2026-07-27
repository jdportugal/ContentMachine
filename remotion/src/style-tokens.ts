// Visual identity tokens for the clip renderer.
//
// Defaults reproduce the NEBULA design system — deep-space navy backgrounds,
// molten-gold headlines, electric-blue accent, heavy condensed Anton display
// type over Space Grotesk body, starfield texture. At render time `applyTheme()`
// overwrites these fields from the ClipProps `theme` (extracted from the app's
// Sistema de Design), so a clip adapts its colours, fonts and background texture
// to whatever design system is active (Nebula stays the default).
//
// COLORS/FONTS are MUTABLE objects (not `as const`): every component reads them
// live, so mutating the fields re-themes the whole render without threading a
// prop through each primitive. One render = one theme (Remotion boots the bundle
// fresh per render), so the mutation is safe.
//
// Historical token names (papyrus/vellum/ink, teal/leather/gold) are kept as
// stable *slots* so primitives don't need renaming — the Nebula values just fill
// them: papyrus=void, vellum=panel, ink=deep-navy, teal=gold accent,
// leather=electric-blue, gold=warm-orange.

export const COLORS = {
  // background slots the planner picks (`background: papyrus|vellum|ink`)
  papyrus: "#05060E", // void — base/page background
  vellum: "#0C1225", // panel — alternate surface (cards, nodes)
  ink: "#0A1030", // deep-navy — contrast field / terminal wells

  // semantic TEXT tokens — decoupled from the background names. Under Nebula every
  // surface is dark, so both text tokens are star-white; applyTheme keeps this
  // correct for any (light or dark) design system.
  textOnLight: "#EAF0FF", // text on papyrus/vellum (star-white)
  textOnDark: "#EAF0FF", // text on the ink background (star-white)
  mutedOnLight: "#A9B6D6", // muted text on papyrus/vellum
  mutedOnDark: "rgba(169,182,214,0.72)", // muted text on ink

  // accents / ornament (charts, highlights, frames)
  inkSoft: "#A9B6D6",
  teal: "#FFB347", // primary accent — molten-gold mid
  tealBright: "#FFD98A", // bright gold (highlights, active states, caret)
  leather: "#2A3BEB", // electric-blue accent (badges, secondary frames)
  gold: "#FF7A3D", // warm-orange (dividers, arrows, gradient tail)
};

export const FONTS = {
  display: '"Anton", "Arial Narrow", "Helvetica Neue", Arial, sans-serif',
  body: '"Space Grotesk", "Helvetica Neue", Arial, sans-serif',
  mono: '"JetBrains Mono", "SFMono-Regular", Menlo, Consolas, monospace',
  // Design-driven weights (applyTheme overrides). Headings/titles use displayWeight,
  // body text uses bodyWeight — so e.g. "Fraunces 900" renders black, not semibold.
  displayWeight: 400,
  bodyWeight: 400,
};

// Molten-gold gradient — Nebula's signature headline treatment (clipped to text).
// Derived from the accent tokens so a non-Nebula theme recolours it automatically.
export const headlineGradient = (): string =>
  `linear-gradient(100deg, ${COLORS.tealBright} 0%, ${COLORS.teal} 55%, ${COLORS.gold} 100%)`;

// Soft glow used across primitives on the dark Nebula base (no hard engraving
// shadow). applyTheme() swaps to a hard offset shadow for 'hard'-style designs.
export let ENGRAVE_SHADOW = "0 2px 18px rgba(0,0,0,0.35)"; // text shadow
export let PANEL_SHADOW = "0 2px 18px rgba(0,0,0,0.35)";   // card/panel box-shadow

// Treatment tokens (applyTheme overrides). Defaults reproduce the soft, rounded,
// gradient-headline Nebula look; a print/brutalist design flips these to flat
// ink headlines, hard offset shadows, thick borders and square corners.
export const STYLE = {
  headline: "gradient" as "gradient" | "flat",
  shadow: "soft" as "soft" | "hard",
  panelBorder: 0, // px ink border on panels
  sharp: false, // true = 0 corner radius
  uppercaseTitles: false,
};

// Text colour that reads on a given surface: light text on dark surfaces, ink on
// light ones — so a paper panel gets ink text, a dark panel gets star-white.
export const textForBg = (bg: string): string => (isDark(bg) ? COLORS.textOnLight : COLORS.ink);

// Ink border for panels (only when STYLE.panelBorder > 0).
export const panelBorder = (): string | undefined =>
  STYLE.panelBorder > 0 ? `${STYLE.panelBorder}px solid ${COLORS.ink}` : undefined;

// Background texture behind opaque scenes. Nebula default = 'starfield'.
export const TEXTURE = {
  kind: "starfield" as "paper" | "starfield" | "gradient" | "solid",
  css: "" as string, // optional explicit CSS background (overrides kind)
};

export type Palette = typeof COLORS;
export type FontStacks = typeof FONTS;

// Shape of the `theme` prop (mirrors the PHP DesignTheme contract). All optional
// — missing fields keep the IATECA default.
export interface ThemeInput {
  colors?: Partial<{
    bg: string;
    bgAlt: string;
    bgContrast: string;
    textOnBg: string;
    textOnContrast: string;
    mutedOnBg: string;
    mutedOnContrast: string;
    accent: string;
    accent2: string;
    accent3: string;
  }>;
  fonts?: Partial<{ display: string; body: string; mono: string; displayWeight: number; bodyWeight: number }>;
  texture?: { kind?: "paper" | "starfield" | "gradient" | "solid"; css?: string };
  style?: { headline?: "gradient" | "flat"; shadow?: "soft" | "hard"; panelBorder?: number; sharp?: boolean; uppercaseTitles?: boolean };
}

const fontStack = (name: string | undefined, fallback: string): string | undefined =>
  name ? `"${name}", ${fallback}` : undefined;

/** Overwrite the live tokens from a design-system theme. Missing fields keep defaults. */
export function applyTheme(theme?: ThemeInput | null): void {
  if (!theme || typeof theme !== "object") return;

  const c = theme.colors ?? {};
  if (c.bg) COLORS.papyrus = c.bg;
  if (c.bgAlt) COLORS.vellum = c.bgAlt;
  if (c.bgContrast) COLORS.ink = c.bgContrast;
  if (c.textOnBg) COLORS.textOnLight = c.textOnBg;
  if (c.textOnContrast) COLORS.textOnDark = c.textOnContrast;
  if (c.mutedOnBg) {
    COLORS.mutedOnLight = c.mutedOnBg;
    COLORS.inkSoft = c.mutedOnBg;
  }
  if (c.mutedOnContrast) COLORS.mutedOnDark = c.mutedOnContrast;
  if (c.accent) {
    COLORS.teal = c.accent;
    COLORS.tealBright = c.accent;
  }
  if (c.accent2) COLORS.leather = c.accent2;
  if (c.accent3) COLORS.gold = c.accent3;

  const f = theme.fonts ?? {};
  const display = fontStack(f.display, 'Georgia, "Times New Roman", serif');
  const body = fontStack(f.body, 'Georgia, "Times New Roman", serif');
  const mono = fontStack(f.mono, "Menlo, Consolas, monospace");
  if (display) FONTS.display = display;
  if (body) FONTS.body = body;
  if (mono) FONTS.mono = mono;
  if (typeof f.displayWeight === "number") FONTS.displayWeight = f.displayWeight;
  if (typeof f.bodyWeight === "number") FONTS.bodyWeight = f.bodyWeight;

  if (theme.texture?.kind) TEXTURE.kind = theme.texture.kind;
  if (theme.texture?.css) TEXTURE.css = theme.texture.css;

  const st = theme.style ?? {};
  if (st.headline) STYLE.headline = st.headline;
  if (st.shadow) STYLE.shadow = st.shadow;
  if (typeof st.panelBorder === "number") STYLE.panelBorder = Math.max(0, Math.min(8, Math.round(st.panelBorder)));
  if (typeof st.sharp === "boolean") STYLE.sharp = st.sharp;
  if (typeof st.uppercaseTitles === "boolean") STYLE.uppercaseTitles = st.uppercaseTitles;

  // Hard = an offset ink block shadow (print look); soft = a blurred glow.
  if (STYLE.shadow === "hard") {
    ENGRAVE_SHADOW = `4px 4px 0 ${COLORS.ink}`;
    PANEL_SHADOW = `12px 12px 0 ${COLORS.ink}`;
  } else {
    ENGRAVE_SHADOW = isDark(COLORS.papyrus) ? "0 2px 18px rgba(0,0,0,0.35)" : "2px 2px 0 rgba(36,26,18,.2)";
    PANEL_SHADOW = ENGRAVE_SHADOW;
  }
}

/** Rough luminance test on a #rrggbb colour (used to pick shadow/texture style). */
export function isDark(hex: string): boolean {
  const m = /^#?([0-9a-f]{6})$/i.exec(String(hex).trim());
  if (!m) return false;
  const n = parseInt(m[1], 16);
  const r = (n >> 16) & 255;
  const g = (n >> 8) & 255;
  const b = n & 255;
  return 0.299 * r + 0.587 * g + 0.114 * b < 128;
}
