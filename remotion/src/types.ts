// Shared prop/plan types for the NEBULA clip renderer.

export type PrimitiveName =
  | "fade"
  | "slide"
  | "scale"
  | "kinetic-text"
  | "highlight"
  | "fleuron-draw"
  | "seal-stamp"
  | "underline-sweep"
  | "count-up"
  | "image-reveal"
  | "ambient"
  | "timeline"
  | "bar-chart"
  | "line-chart"
  | "pie-chart"
  | "scatter-chart"
  | "comparison"
  | "bullet-list"
  | "card"
  | "terminal"
  | "diagram";

// ── data-visualization param shapes ─────────────────────────────────────────
// These are the shapes the AI planner emits. Primitives coerce/guard every
// field at runtime, so these types document intent rather than enforce it.
export interface TimelineItem {
  label?: string;
  sublabel?: string;
  highlight?: boolean;
}

export interface BarItem {
  label?: string;
  value?: number;
  highlight?: boolean;
}

export interface ComparisonColumn {
  title?: string;
  points?: string[];
}

export interface AnimationParams {
  to?: number; // count-up target
  from?: number; // count-up start
  src?: string; // image-reveal source
  direction?: "left" | "right" | "up" | "down"; // slide direction
  suffix?: string; // count-up suffix (e.g. "%")
  prefix?: string; // count-up prefix
  // data-viz primitives
  items?: unknown; // timeline: TimelineItem[] | bullet-list: string[]
  caption?: string; // timeline caption
  title?: string; // bar-chart / bullet-list title
  bars?: BarItem[]; // bar-chart bars
  unit?: string; // bar-chart value unit (e.g. "%")
  left?: ComparisonColumn; // comparison left column
  right?: ComparisonColumn; // comparison right column
  [key: string]: unknown;
}

export interface Animation {
  start: number; // seconds
  end: number; // seconds
  primitive: PrimitiveName;
  text?: string;
  params?: AnimationParams;
}

// ── scene model (v2) ─────────────────────────────────────────────────────────
export type SceneBackground = "papyrus" | "vellum" | "ink" | "video";
export type Transition = "cut" | "crossfade" | "whip" | "slide" | "zoom";
// How a scene presents relative to the source video (overlay clips only):
//  animation = full-screen animation (video hidden) · over = animation on top of video
//  video = just the video (+ karaoke) · split = animation top, video bottom
export type Present = "animation" | "over" | "video" | "split";
export type LayerAnim = "rise" | "card-in" | "pop" | "draw" | "fade" | "slide";
export type LayerPosition = "center" | "top" | "bottom";

// A layer reuses the primitive vocabulary as its `type` (incl. card/terminal).
export type LayerType = PrimitiveName;

export interface Layer {
  type: LayerType;
  text?: string;
  params?: AnimationParams;
  anim?: LayerAnim;
  position?: LayerPosition;
}

export interface Scene {
  start: number; // seconds (absolute)
  end: number; // seconds (absolute)
  background?: SceneBackground;
  transitionIn?: Transition;
  transitionOut?: Transition;
  karaoke?: boolean;
  punchWord?: string | null;
  present?: Present; // how it sits relative to the source video (overlay clips)
  layers?: Layer[];
}

export interface KaraokeWord {
  word: string;
  start: number; // seconds (absolute)
  end: number; // seconds (absolute)
}

// Design-system theme (extracted from the app's Sistema de Design). All fields
// optional — applyTheme keeps the NEBULA default for anything missing.
export interface ClipTheme {
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
  fonts?: Partial<{ display: string; body: string; mono: string }>;
  texture?: { kind?: "paper" | "starfield" | "gradient" | "solid"; css?: string };
}

export interface ClipProps {
  duration: number; // seconds
  width: number;
  height: number;
  fps: number;
  mode: "dense" | "sparse";
  transparent: boolean;
  audioSrc?: string;
  theme?: ClipTheme; // design-system tokens (colours, fonts, texture)
  // v2 scene-based model (preferred). If absent, `animations` (v1) is rendered.
  scenes?: Scene[];
  words?: KaraokeWord[]; // drives karaoke captions
  videoSrc?: string; // source video (overlay clips) — scenes composite it per `present`
  animations?: Animation[]; // legacy flat model (backward compatible)
  // Index signature required so ClipProps satisfies Remotion's
  // `Record<string, unknown>` constraint on <Composition> props.
  [key: string]: unknown;
}
