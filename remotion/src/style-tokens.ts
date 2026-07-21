// IATECA ("Máquina de Conteúdo") visual identity tokens.
// Serif, editorial, calm. Teal is the accent; gold/leather are ornament only.

export const COLORS = {
  papyrus: "#f4ead5",
  vellum: "#faf3e0",
  ink: "#241a12",
  inkSoft: "#5b4636",
  teal: "#1f7a7a",
  tealBright: "#2dbab4",
  leather: "#8b3a2a",
  gold: "#c89b3c",
} as const;

// Font stacks. The display face is loaded via @remotion/google-fonts when
// available (see fonts.ts); these stacks include robust serif fallbacks so
// rendering never fails if a webfont is missing.
export const FONTS = {
  display: '"Cormorant Garamond", "EB Garamond", Georgia, "Times New Roman", serif',
  body: '"EB Garamond", Georgia, "Times New Roman", serif',
  mono: '"JetBrains Mono", "SFMono-Regular", Menlo, Consolas, monospace',
} as const;

// Engraving-style hard shadow used across primitives (no modern blur shadows).
export const ENGRAVE_SHADOW = "2px 2px 0 rgba(36,26,18,.2)";

export type Palette = typeof COLORS;
export type FontStacks = typeof FONTS;
