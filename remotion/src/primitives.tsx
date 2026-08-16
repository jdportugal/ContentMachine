import React from "react";
import {
  AbsoluteFill,
  Easing,
  Img,
  interpolate,
  OffthreadVideo,
  spring,
  staticFile,
  useCurrentFrame,
} from "remotion";
import { fillTextBox, fitText } from "@remotion/layout-utils";
import { COLORS, ENGRAVE_SHADOW, FONTS, headlineGradient, PANEL_SHADOW, panelBorder, STYLE, textForBg } from "./style-tokens";
import type { Animation } from "./types";
import { CUSTOM_PRIMITIVES } from "./effects";

// Every primitive receives the animation descriptor plus the composition fps.
// `useCurrentFrame()` is LOCAL to the wrapping <Sequence>, i.e. it starts at 0
// when the animation window begins.
export interface PrimitiveProps {
  anim: Animation;
  fps: number;
  dark?: boolean; // true when the scene background is dark (ink) → light text
}

const EASE = Easing.inOut(Easing.cubic);

// Background-aware text colors. `dark` = the scene sits on the contrast (ink)
// background. Uses the semantic text tokens so light AND dark design systems
// both read correctly (see style-tokens applyTheme).
const inkC = (dark?: boolean) => (dark ? COLORS.textOnDark : COLORS.textOnLight);
const softC = (dark?: boolean) => (dark ? COLORS.mutedOnDark : COLORS.mutedOnLight);

// Local duration (in frames) of the animation window.
const winFrames = (anim: Animation, fps: number) =>
  Math.max(1, Math.round((anim.end - anim.start) * fps));

// Shared centered layout wrapper.
const Center: React.FC<{ children: React.ReactNode; style?: React.CSSProperties }> = ({
  children,
  style,
}) => (
  <AbsoluteFill
    style={{
      justifyContent: "center",
      alignItems: "center",
      textAlign: "center",
      padding: "0 8%",
      ...style,
    }}
  >
    {children}
  </AbsoluteFill>
);

// ── box-aware text fitting ───────────────────────────────────────────────────
// The renderer runs in a real browser, so we can MEASURE (via layout-utils) how
// text wraps and make it fit — the two levers a human would use: shrink the font,
// and if it still won't fit, trim words (…). `fillTextBox` reports, word by word,
// whether the text spills past `maxLines` lines of `maxWidth` at a given size.

type BoxOpts = {
  maxWidth: number;
  maxLines: number;
  fontFamily: string;
  fontWeight?: number | string;
  letterSpacing?: string;
  textTransform?: "none" | "uppercase";
};

const fitsInBox = (text: string, fontSize: number, o: BoxOpts): boolean => {
  const box = fillTextBox({ maxBoxWidth: o.maxWidth, maxLines: o.maxLines });
  for (const w of text.split(/\s+/).filter(Boolean)) {
    if (box.add({ text: w + " ", fontSize, fontFamily: o.fontFamily, fontWeight: o.fontWeight, letterSpacing: o.letterSpacing, textTransform: o.textTransform }).exceedsBox) {
      return false;
    }
  }
  return true;
};

// Largest font in [min, max] at which `text` fits the box; the floor if none do.
const fitFontToBox = (text: string, maxFontSize: number, minFontSize: number, o: BoxOpts): number => {
  for (let size = Math.round(maxFontSize); size > minFontSize; size -= 2) {
    if (fitsInBox(text, size, o)) return size;
  }
  return minFontSize;
};

// Drop trailing words (adding …) until the text fits the box at `fontSize`.
const truncateToBox = (text: string, fontSize: number, o: BoxOpts): string => {
  if (fitsInBox(text, fontSize, o)) return text;
  const words = text.split(/\s+/).filter(Boolean);
  while (words.length > 1) {
    words.pop();
    const candidate = words.join(" ") + "…";
    if (fitsInBox(candidate, fontSize, o)) return candidate;
  }
  return text; // one giant word — the CSS clamp below still keeps it inside the box
};

// Renders text that ALWAYS fits its box: shrinks the font toward `minFontSize` to
// fit `maxWidth` × `maxLines`, then trims words (…) if it still overflows, with a
// CSS line-clamp as the final safety net. `maxLines` defaults to 1 (single-line
// labels, the old behaviour — just fitted and clamped instead of overflowing).
const FitText: React.FC<{
  text: string;
  maxWidth: number;
  maxFontSize: number;
  minFontSize?: number;
  maxLines?: number;
  fontFamily: string;
  fontWeight?: number | string;
  letterSpacing?: string;
  uppercase?: boolean;
  style?: React.CSSProperties;
}> = ({ text, maxWidth, maxFontSize, minFontSize = 0, maxLines = 1, fontFamily, fontWeight, letterSpacing, uppercase, style }) => {
  const o: BoxOpts = { maxWidth, maxLines, fontFamily, fontWeight, letterSpacing, textTransform: uppercase ? "uppercase" : "none" };
  const floor = Math.max(1, minFontSize);
  const size = fitFontToBox(text || " ", maxFontSize, floor, o);
  const shown = truncateToBox(text || " ", size, o);
  return (
    <span
      style={{
        ...style,
        fontFamily,
        fontWeight,
        letterSpacing,
        textTransform: uppercase ? "uppercase" : undefined,
        fontSize: size,
        maxWidth,
        display: "-webkit-box",
        WebkitBoxOrient: "vertical",
        WebkitLineClamp: maxLines,
        overflow: "hidden",
        whiteSpace: "normal",
      }}
    >
      {shown}
    </span>
  );
};

// Nebula signature: display headlines are Anton uppercase, clipped to the
// molten-gold gradient. `dark` no longer changes the colour (the gold reads on
// any Nebula surface) but is kept in the signature so callers stay unchanged.
const titleStyleFor = (dark?: boolean): React.CSSProperties => {
  const base: React.CSSProperties = {
    fontFamily: FONTS.display,
    fontWeight: FONTS.displayWeight,
    fontSize: 96,
    lineHeight: 0.95,
    textTransform: "uppercase",
    margin: 0,
  };
  // Flat = solid ink/paper colour (surface-appropriate) with an optional hard
  // offset shadow — the print look. Gradient = molten-gold clipped fill (Nebula).
  if (STYLE.headline === "flat") {
    return {
      ...base,
      color: inkC(dark),
      letterSpacing: "-0.02em",
      textShadow: STYLE.shadow === "hard" ? ENGRAVE_SHADOW : undefined,
    };
  }
  return {
    ...base,
    backgroundImage: headlineGradient(),
    WebkitBackgroundClip: "text",
    backgroundClip: "text",
    color: "transparent",
    WebkitTextFillColor: "transparent",
  };
};

// ── data-viz coercion helpers (params may be missing/malformed) ──────────────
const asStr = (v: unknown): string => (typeof v === "string" ? v : "");
const asNum = (v: unknown): number => {
  const n = typeof v === "number" ? v : typeof v === "string" ? Number(v) : NaN;
  return Number.isFinite(n) ? n : 0;
};
const asRecord = (v: unknown): Record<string, unknown> =>
  v && typeof v === "object" ? (v as Record<string, unknown>) : {};

// Resolve an image reference (a public/ filename, staged by the backend, or a URL).
const imgSrc = (v: unknown): string => {
  const s = asStr(v);
  return s ? (/^https?:\/\//.test(s) ? s : staticFile(s)) : "";
};

const coerceTimelineItems = (v: unknown) =>
  (Array.isArray(v) ? v : []).slice(0, 7).map((raw) => {
    const o = asRecord(raw);
    return {
      label: asStr(o.label),
      sublabel: typeof o.sublabel === "string" ? o.sublabel : "",
      highlight: o.highlight === true,
      image: imgSrc(o.image ?? o.img ?? o.src),
    };
  });

const coerceBars = (v: unknown) =>
  (Array.isArray(v) ? v : []).slice(0, 6).map((raw) => {
    const o = asRecord(raw);
    return {
      label: asStr(o.label),
      value: asNum(o.value),
      highlight: o.highlight === true,
      image: imgSrc(o.image ?? o.img ?? o.src),
    };
  });

// Multi-series data for line-chart (each series may target the left or right axis).
// Built lazily so it reflects the active theme (COLORS is themed after load).
const seriesColors = () => [COLORS.teal, COLORS.leather, COLORS.gold, COLORS.inkSoft];
const coerceSeries = (v: unknown) => {
  const SERIES = seriesColors();
  return (Array.isArray(v) ? v : []).slice(0, 4).map((raw, i) => {
    const o = asRecord(raw);
    return {
      label: asStr(o.label),
      color: asStr(o.color) || SERIES[i % SERIES.length],
      points: (Array.isArray(o.points) ? o.points : []).slice(0, 12).map(asNum),
      highlight: o.highlight === true,
      axis: o.axis === "right" ? ("right" as const) : ("left" as const),
    };
  });
};

// Points for a 2-axis scatter / quadrant chart.
const coerceScatter = (v: unknown) =>
  (Array.isArray(v) ? v : []).slice(0, 8).map((raw) => {
    const o = asRecord(raw);
    return { label: clamp(asStr(o.label), 16), x: asNum(o.x), y: asNum(o.y), highlight: o.highlight === true };
  });

// Slices for pie/donut chart.
const coerceSlices = (v: unknown) =>
  (Array.isArray(v) ? v : []).slice(0, 6).map((raw) => {
    const o = asRecord(raw);
    return { label: asStr(o.label), value: Math.max(0, asNum(o.value)), highlight: o.highlight === true };
  });

// Diagram nodes/edges.
const coerceNodes = (v: unknown) =>
  (Array.isArray(v) ? v : []).slice(0, 6).map((raw) => {
    const o = asRecord(raw);
    return {
      label: clamp(asStr(o.label), 18),
      image: imgSrc(o.image ?? o.img ?? o.src),
      transparent: o.transparent === true,
      highlight: o.highlight === true,
    };
  });

const coerceEdges = (v: unknown) =>
  (Array.isArray(v) ? v : []).slice(0, 12).map((raw) => {
    const o = asRecord(raw);
    return { from: Math.round(asNum(o.from)), to: Math.round(asNum(o.to)), label: asStr(o.label) };
  });

// Truncate over-long strings so text never overflows small areas.
const clamp = (s: string, max: number): string => (s.length > max ? s.slice(0, max - 1).trimEnd() + "…" : s);

const coerceStrList = (v: unknown, max: number, maxLen = 0): string[] =>
  (Array.isArray(v) ? v : [])
    .slice(0, max)
    .map((s) => (typeof s === "string" ? s : s == null ? "" : String(s)))
    .map((s) => (maxLen > 0 ? clamp(s, maxLen) : s));

const coerceColumn = (v: unknown) => {
  const o = asRecord(v);
  return { title: clamp(asStr(o.title), 22), points: coerceStrList(o.points, 4, 42), image: imgSrc(o.image ?? o.img ?? o.src) };
};

const fmtValue = (v: number, decimals: number): string =>
  (Number.isFinite(v) ? v : 0).toFixed(decimals);

// Faint placeholder shown when a data-viz primitive has no usable params.
const Placeholder: React.FC = () => (
  <span
    style={{
      fontFamily: FONTS.display,
      color: COLORS.inkSoft,
      opacity: 0.3,
      fontSize: 160,
      lineHeight: 1,
    }}
  >
    &mdash;
  </span>
);

// ── fade ────────────────────────────────────────────────────────────────────
const Fade: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const dur = winFrames(anim, fps);
  const opacity = interpolate(
    frame,
    [0, Math.min(12, dur * 0.3), dur - Math.min(12, dur * 0.3), dur],
    [0, 1, 1, 0],
    { extrapolateLeft: "clamp", extrapolateRight: "clamp", easing: EASE }
  );
  return (
    <Center>
      <p style={{ ...titleStyleFor(dark), opacity }}>{anim.text ?? ""}</p>
    </Center>
  );
};

// ── slide ───────────────────────────────────────────────────────────────────
const Slide: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const dur = winFrames(anim, fps);
  const dir = anim.params?.direction ?? "left";
  const s = spring({ frame, fps, config: { damping: 200 }, durationInFrames: Math.min(dur, fps) });
  const dist = 240;
  const map: Record<string, [number, number]> = {
    left: [-dist, 0],
    right: [dist, 0],
    up: [0, -dist],
    down: [0, dist],
  };
  const axis = map[dir] ?? map.left;
  const tx = dir === "left" || dir === "right" ? interpolate(s, [0, 1], axis) : 0;
  const ty = dir === "up" || dir === "down" ? interpolate(s, [0, 1], axis) : 0;
  const opacity = interpolate(frame, [0, Math.min(10, dur)], [0, 1], {
    extrapolateRight: "clamp",
  });
  return (
    <Center>
      <p style={{ ...titleStyleFor(dark), transform: `translate(${tx}px, ${ty}px)`, opacity }}>
        {anim.text ?? ""}
      </p>
    </Center>
  );
};

// ── scale ───────────────────────────────────────────────────────────────────
const Scale: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const dur = winFrames(anim, fps);
  const s = spring({ frame, fps, config: { damping: 14, mass: 0.8 }, durationInFrames: Math.min(dur, fps) });
  const scale = interpolate(s, [0, 1], [0.6, 1]);
  const opacity = interpolate(frame, [0, Math.min(10, dur)], [0, 1], {
    extrapolateRight: "clamp",
  });
  return (
    <Center>
      <p style={{ ...titleStyleFor(dark), transform: `scale(${scale})`, opacity }}>{anim.text ?? ""}</p>
    </Center>
  );
};

// ── kinetic-text (word fade + rise reveal) ───────────────────────────────────
const KineticText: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const dur = winFrames(anim, fps);
  const words = (anim.text ?? "").split(/\s+/).filter(Boolean);
  const perWord = words.length > 0 ? Math.max(3, (dur * 0.6) / words.length) : 1;
  // Shrink the headline so the whole phrase fits ~3 lines of the frame width.
  const fitFont = fitFontToBox(anim.text ?? " ", 96, 44, { maxWidth: 900, maxLines: 3, fontFamily: FONTS.display, fontWeight: FONTS.displayWeight, textTransform: "uppercase" });
  return (
    <Center>
      <p style={{ ...titleStyleFor(dark), fontSize: fitFont, maxWidth: 900, overflow: "hidden", display: "flex", flexWrap: "wrap", justifyContent: "center", gap: "0 0.28em" }}>
        {words.map((w, i) => {
          const startAt = i * perWord;
          const local = frame - startAt;
          const opacity = interpolate(local, [0, perWord], [0, 1], {
            extrapolateLeft: "clamp",
            extrapolateRight: "clamp",
            easing: EASE,
          });
          const rise = interpolate(local, [0, perWord], [28, 0], {
            extrapolateLeft: "clamp",
            extrapolateRight: "clamp",
            easing: EASE,
          });
          return (
            <span
              key={i}
              style={{
                // The gradient clip lives on each word (not the parent) — an
                // inline-block child would otherwise inherit transparent text
                // with no background of its own and vanish.
                display: "inline-block",
                opacity,
                transform: `translateY(${rise}px)`,
                backgroundImage: headlineGradient(),
                WebkitBackgroundClip: "text",
                backgroundClip: "text",
                color: "transparent",
                WebkitTextFillColor: "transparent",
              }}
            >
              {w}
            </span>
          );
        })}
      </p>
    </Center>
  );
};

// ── highlight (teal sweep behind/under text) ─────────────────────────────────
const Highlight: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const dur = winFrames(anim, fps);
  const opacity = interpolate(frame, [0, Math.min(8, dur)], [0, 1], { extrapolateRight: "clamp" });
  const sweep = interpolate(frame, [Math.min(6, dur), dur * 0.7], [0, 100], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
    easing: EASE,
  });
  return (
    <Center>
      <span style={{ position: "relative", display: "inline-block" }}>
        <span
          style={{
            position: "absolute",
            left: "-4%",
            right: "-4%",
            bottom: "0.08em",
            height: "0.42em",
            background: COLORS.tealBright,
            opacity: 0.45,
            width: `${sweep + 8}%`,
            borderRadius: 2,
            zIndex: 0,
          }}
        />
        <span style={{ ...titleStyleFor(dark), position: "relative", zIndex: 1, opacity }}>
          {anim.text ?? ""}
        </span>
      </span>
    </Center>
  );
};

// ── fleuron-draw (ornamental divider drawing in) ─────────────────────────────
const FleuronDraw: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const dur = winFrames(anim, fps);
  const draw = interpolate(frame, [0, dur * 0.7], [0, 1], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
    easing: EASE,
  });
  const lineW = interpolate(draw, [0, 1], [0, 220]);
  const glyphScale = interpolate(draw, [0.4, 1], [0.2, 1], { extrapolateLeft: "clamp" });
  const glyphOpacity = interpolate(draw, [0.4, 1], [0, 1], { extrapolateLeft: "clamp" });
  return (
    <Center>
      <div style={{ display: "flex", alignItems: "center", gap: 18, color: COLORS.teal }}>
        <span style={{ height: 3, width: lineW, background: COLORS.teal, display: "block", borderRadius: 999 }} />
        <span
          style={{
            fontFamily: FONTS.body,
            fontSize: 72,
            transform: `scale(${glyphScale})`,
            opacity: glyphOpacity,
            color: COLORS.tealBright,
            textShadow: `0 0 22px ${COLORS.teal}88`,
            lineHeight: 1,
          }}
        >
          &#10022;
        </span>
        <span style={{ height: 3, width: lineW, background: COLORS.teal, display: "block", borderRadius: 999 }} />
      </div>
    </Center>
  );
};

// ── seal-stamp (circular library stamp scaling + rotating in) ────────────────
const SealStamp: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const dur = winFrames(anim, fps);
  const s = spring({ frame, fps, config: { damping: 12, mass: 0.9 }, durationInFrames: Math.min(dur, fps) });
  const scale = interpolate(s, [0, 1], [1.7, 1]);
  const rotate = interpolate(s, [0, 1], [-24, -7]);
  const opacity = interpolate(s, [0, 1], [0, 0.92]);
  const label = anim.text ?? "NEBULA";
  return (
    <Center>
      <div
        style={{
          width: 420,
          height: 420,
          borderRadius: "50%",
          border: `8px solid ${COLORS.leather}`,
          boxShadow: `inset 0 0 0 12px ${COLORS.papyrus}, inset 0 0 0 16px ${COLORS.leather}, 0 0 60px ${COLORS.leather}66`,
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          transform: `scale(${scale}) rotate(${rotate}deg)`,
          opacity,
          color: COLORS.teal,
          fontFamily: FONTS.display,
          fontWeight: FONTS.displayWeight,
          textTransform: "uppercase",
          letterSpacing: "0.12em",
          fontSize: 72,
        }}
      >
        <span style={{ transform: "translateY(-2px)" }}>{label}</span>
      </div>
    </Center>
  );
};

// ── underline-sweep ──────────────────────────────────────────────────────────
const UnderlineSweep: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const dur = winFrames(anim, fps);
  const opacity = interpolate(frame, [0, Math.min(8, dur)], [0, 1], { extrapolateRight: "clamp" });
  const w = interpolate(frame, [Math.min(6, dur), dur * 0.8], [0, 100], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
    easing: EASE,
  });
  return (
    <Center>
      <div style={{ display: "inline-block" }}>
        <p style={{ ...titleStyleFor(dark), opacity, marginBottom: 12 }}>{anim.text ?? ""}</p>
        <span
          style={{
            display: "block",
            height: 6,
            width: `${w}%`,
            background: COLORS.teal,
            borderRadius: 3,
            margin: "0 auto",
          }}
        />
      </div>
    </Center>
  );
};

// ── count-up ──────────────────────────────────────────────────────────────────
const CountUp: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const dur = winFrames(anim, fps);
  const to = anim.params?.to ?? 100;
  const from = anim.params?.from ?? 0;
  const prefix = anim.params?.prefix ?? "";
  const suffix = anim.params?.suffix ?? "";
  const value = interpolate(frame, [0, dur * 0.85], [from, to], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
    easing: EASE,
  });
  const opacity = interpolate(frame, [0, Math.min(8, dur)], [0, 1], { extrapolateRight: "clamp" });
  const decimals = Number.isInteger(to) && Number.isInteger(from) ? 0 : 1;
  return (
    <Center>
      <span
        style={{
          fontFamily: FONTS.mono,
          color: COLORS.teal,
          fontSize: 168,
          fontWeight: 700,
          opacity,
          textShadow: ENGRAVE_SHADOW,
        }}
      >
        {prefix}
        {value.toFixed(decimals)}
        {suffix}
      </span>
    </Center>
  );
};

// ── image-reveal (multiple entrance animations, selected by params.variant) ──
//   fullscreen | pan | kenburns → image fills the whole screen (Ken-Burns zoom)
//   drop-float → drops in from above and floats centred
//   rise       → rises in from below and floats centred
//   zoom       → fades + scales up, centred (floating card)
//   slide      → slides in from a side (params.direction: left|right)
//   framed     → the old bordered papyrus panel with a left-to-right wipe
//   (default: fullscreen for photos, float for transparent cut-outs/logos)
const IMG_FULLSCREEN = new Set(["fullscreen", "pan", "kenburns"]);

const ImagePlaceholder: React.FC<{ text?: string }> = ({ text }) => (
  <div
    style={{
      width: "100%",
      height: "100%",
      background: `repeating-linear-gradient(115deg, ${COLORS.papyrus} 0px, ${COLORS.papyrus} 22px, ${COLORS.vellum} 22px, ${COLORS.vellum} 46px)`,
      display: "flex",
      alignItems: "center",
      justifyContent: "center",
      color: COLORS.inkSoft,
      fontFamily: FONTS.display,
      fontSize: 56,
      textTransform: "uppercase",
      letterSpacing: "0.1em",
    }}
  >
    {text ?? "NEBULA"}
  </div>
);

const ImageReveal: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const dur = winFrames(anim, fps);
  const rawSrc = asStr(anim.params?.src);
  // Bare filenames resolve from Remotion's public/ folder; full URLs pass through.
  const src = rawSrc ? (/^https?:\/\//.test(rawSrc) ? rawSrc : staticFile(rawSrc)) : "";
  // A video source plays inset just like an image shows — same fit rules.
  const isVideo = /\.(mp4|mov|webm|m4v)$/i.test(rawSrc);
  const media = (style: React.CSSProperties) =>
    isVideo ? <OffthreadVideo src={src} muted style={style} /> : <Img src={src} style={style} />;
  const caption = asStr(anim.params?.caption);
  const transparent = anim.params?.transparent === true;
  const backing = asStr(anim.params?.backing); // contrasting panel colour when tones clash
  const variant = (asStr(anim.params?.variant) || (transparent ? "float" : "fullscreen")).toLowerCase();

  const opacity = interpolate(frame, [0, Math.min(10, dur)], [0, 1], { extrapolateRight: "clamp" });

  const captionEl = caption ? (
    <div style={{ fontFamily: FONTS.body, fontStyle: "italic", color: softC(dark), fontSize: 38, textAlign: "center", maxWidth: "80%" }}>
      {caption}
    </div>
  ) : null;

  // ── Full-screen (Ken-Burns): the image fills the frame and slowly zooms/pans ──
  if (IMG_FULLSCREEN.has(variant)) {
    const zoom = interpolate(frame, [0, dur], [1.08, 1.18], { extrapolateRight: "clamp" });
    const panX = variant === "pan" ? interpolate(frame, [0, dur], [-3, 3], { extrapolateRight: "clamp" }) : 0;
    return (
      <AbsoluteFill style={{ overflow: "hidden", opacity, background: COLORS.ink }}>
        {src ? (
          <>
            {/* Blurred fill so the frame is never empty behind a non-matching aspect
                ratio — only decorative, so it may crop. */}
            {media({ position: "absolute", inset: 0, width: "100%", height: "100%", objectFit: "cover", transform: `scale(${zoom}) translateX(${panX}%)`, filter: "blur(32px) brightness(0.5)" })}
            {/* The real image/video, shown whole — keeps its proportions, never cut. */}
            <AbsoluteFill style={{ alignItems: "center", justifyContent: "center" }}>
              {media({ maxWidth: "100%", maxHeight: "100%", objectFit: "contain" })}
            </AbsoluteFill>
          </>
        ) : (
          <ImagePlaceholder text={anim.text ?? undefined} />
        )}
        {captionEl ? (
          <AbsoluteFill style={{ justifyContent: "flex-end", alignItems: "center", paddingBottom: 120 }}>
            <div style={{ fontFamily: FONTS.body, fontStyle: "italic", color: "#fff", fontSize: 40, textAlign: "center", maxWidth: "82%", textShadow: "0 2px 18px rgba(0,0,0,0.55)" }}>
              {caption}
            </div>
          </AbsoluteFill>
        ) : null}
      </AbsoluteFill>
    );
  }

  // ── Contained "floating card" variants ──
  const inDur = Math.max(1, Math.round(Math.min(fps * 0.7, dur * 0.5)));
  const entry = spring({ frame, fps, config: { damping: 16, stiffness: 130 }, durationInFrames: inDur });
  const dir = asStr(anim.params?.direction) === "right" ? 1 : -1;
  const idleY = Math.sin(frame / 46) * 6; // gentle float
  const idleScale = 1 + Math.sin(frame / 80) * 0.004;

  let ty = 0; // in vh (composition height)
  let tx = 0; // in vw
  let enterScale = 1;
  if (variant === "drop-float") ty = interpolate(entry, [0, 1], [-75, 0]);
  else if (variant === "rise") ty = interpolate(entry, [0, 1], [75, 0]);
  else if (variant === "slide") tx = interpolate(entry, [0, 1], [70 * dir, 0]);
  else if (variant === "zoom") enterScale = interpolate(entry, [0, 1], [0.72, 1]);
  // "float"/"framed"/unknown → no entrance translate (fade + idle only)

  const framed = variant === "framed";
  // Always contain — the image keeps its proportions and is never cropped.
  const fit: "cover" | "contain" = "contain";
  const boxStyle: React.CSSProperties = framed
    ? { width: "78%", aspectRatio: "4 / 5", overflow: "hidden", border: `6px solid ${COLORS.leather}`, background: COLORS.vellum, boxShadow: PANEL_SHADOW }
    : transparent
      ? { width: "80%", aspectRatio: "1 / 1", overflow: "hidden", display: "flex", alignItems: "center", justifyContent: "center", padding: backing ? 40 : 0, background: backing || undefined, borderRadius: backing ? 24 : 0, boxShadow: backing ? ENGRAVE_SHADOW : undefined }
      : { width: "74%", aspectRatio: "4 / 5", overflow: "hidden", borderRadius: 22, background: COLORS.vellum, boxShadow: PANEL_SHADOW };

  return (
    <Center style={{ flexDirection: "column", gap: 28 }}>
      <div
        style={{
          ...boxStyle,
          opacity,
          transform: `translate(${tx}vw, ${ty}vh) translateY(${idleY}px) scale(${enterScale * idleScale})`,
        }}
      >
        {src ? media({ width: "100%", height: "100%", objectFit: fit }) : <ImagePlaceholder text={anim.text ?? undefined} />}
      </div>
      {captionEl}
    </Center>
  );
};

// ── ambient (subtle always-present background motion) ────────────────────────
const Ambient: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const dur = winFrames(anim, fps);
  const t = frame / Math.max(1, dur);
  const drift = Math.sin(t * Math.PI * 2) * 14;
  const drift2 = Math.cos(t * Math.PI * 2) * 10;
  return (
    <AbsoluteFill style={{ overflow: "hidden" }}>
      <div
        style={{
          position: "absolute",
          inset: "-8%",
          transform: `translate(${drift}px, ${drift2}px)`,
          opacity: 0.5,
          background: `radial-gradient(circle at 30% 25%, rgba(200,155,60,0.10), transparent 42%),
                       radial-gradient(circle at 72% 68%, rgba(139,58,42,0.08), transparent 46%),
                       radial-gradient(circle at 50% 90%, rgba(31,122,122,0.06), transparent 50%)`,
        }}
      />
    </AbsoluteFill>
  );
};

// ── timeline ─────────────────────────────────────────────────────────────────
const Timeline: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const dur = winFrames(anim, fps);
  const items = coerceTimelineItems(anim.params?.items);
  const caption = asStr(anim.params?.caption);
  if (items.length === 0) return <Center><Placeholder /></Center>;

  const line = interpolate(frame, [0, dur * 0.35], [0, 1], {
    extrapolateLeft: "clamp", extrapolateRight: "clamp", easing: EASE,
  });
  const revealStart = dur * 0.12;
  const span = dur * 0.72;
  const step = items.length > 1 ? span / items.length : 0;

  return (
    <Center style={{ alignItems: "stretch", padding: "12% 12%" }}>
      {caption ? (
        <div style={{ fontFamily: FONTS.mono, color: softC(dark), letterSpacing: 4, textTransform: "uppercase", fontSize: 28, textAlign: "center", marginBottom: 50 }}>{caption}</div>
      ) : null}
      <div style={{ position: "relative", flex: 1, display: "flex", flexDirection: "column", justifyContent: "center" }}>
        <div style={{ position: "absolute", left: 60, top: "4%", bottom: "4%", width: 4, background: COLORS.gold, opacity: 0.5, transform: `scaleY(${line})`, transformOrigin: "top" }} />
        {items.map((it, i) => {
          const at = revealStart + i * step;
          const t = interpolate(frame, [at, at + fps * 0.5], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp", easing: EASE });
          const pop = it.highlight ? spring({ frame: frame - at, fps, config: { damping: 11 } }) : 1;
          const accent = it.highlight ? COLORS.teal : softC(dark);
          return (
            <div key={i} style={{ position: "relative", padding: "26px 0", opacity: t, transform: `translateX(${(1 - t) * 36}px)` }}>
              <div style={{ position: "absolute", left: 60, top: "50%", width: it.highlight ? 40 : 26, height: it.highlight ? 40 : 26, borderRadius: "50%", background: it.highlight ? COLORS.teal : COLORS.papyrus, border: `4px solid ${accent}`, transform: `translate(-50%,-50%) scale(${pop})`, boxShadow: it.highlight ? "0 0 0 8px rgba(31,122,122,0.18)" : "none" }} />
              <div style={{ marginLeft: 130, display: "flex", alignItems: "center", gap: 22 }}>
                {it.image ? (
                  <Img src={it.image} style={{ width: 88, height: 88, objectFit: "contain", borderRadius: 8, border: `3px solid ${accent}` }} />
                ) : null}
                <div>
                  <div style={{ fontFamily: FONTS.display, fontWeight: FONTS.displayWeight, color: it.highlight ? COLORS.teal : inkC(dark), fontSize: it.highlight ?  78 : 60, lineHeight: 1.05, textShadow: ENGRAVE_SHADOW }}>{it.label}</div>
                  {it.sublabel ? <div style={{ fontFamily: FONTS.mono, color: softC(dark), fontSize: 26 }}>{it.sublabel}</div> : null}
                </div>
              </div>
            </div>
          );
        })}
      </div>
    </Center>
  );
};

// ── bar-chart ────────────────────────────────────────────────────────────────
const BarChart: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const dur = winFrames(anim, fps);
  const bars = coerceBars(anim.params?.bars);
  const title = asStr(anim.params?.title);
  const unit = asStr(anim.params?.unit);
  if (bars.length === 0) return <Center><Placeholder /></Center>;

  const maxVal = Math.max(...bars.map((b) => b.value), 1);
  const step = (dur * 0.5) / bars.length;

  return (
    <Center style={{ alignItems: "stretch", padding: "12% 12%" }}>
      {title ? <div style={{ fontFamily: FONTS.display, color: inkC(dark), fontWeight: FONTS.displayWeight, fontSize:  82, textAlign: "center", marginBottom: 64, textTransform: STYLE.uppercaseTitles ? "uppercase" : undefined, textShadow: ENGRAVE_SHADOW }}>{title}</div> : null}
      <div style={{ display: "flex", flexDirection: "column", gap: 44 }}>
        {bars.map((b, i) => {
          const g = interpolate(frame, [i * step, i * step + fps * 0.8], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp", easing: EASE });
          const color = b.highlight ? COLORS.teal : COLORS.leather;
          const track = STYLE.panelBorder > 0 ? `${Math.min(3, STYLE.panelBorder)}px solid ${COLORS.ink}` : undefined;
          return (
            <div key={i}>
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 12 }}>
                <span style={{ display: "flex", alignItems: "center", gap: 16 }}>
                  {b.image ? <Img src={b.image} style={{ width: 56, height: 56, objectFit: "contain", borderRadius: 8, border: `2px solid ${color}` }} /> : null}
                  <span style={{ fontFamily: FONTS.display, color: inkC(dark), fontSize:  58 }}>{b.label}</span>
                </span>
                <span style={{ fontFamily: FONTS.mono, color, fontSize:  50 }}>{fmtValue(b.value * g, 0)}{unit}</span>
              </div>
              <div style={{ height: 40, background: track ? COLORS.vellum : "rgba(91,70,54,0.15)", border: track, borderRadius: STYLE.sharp ? 0 : 4, overflow: "hidden" }}>
                <div style={{ height: "100%", width: `${(b.value / maxVal) * 100 * g}%`, background: color, opacity: STYLE.shadow === "hard" || b.highlight ? 1 : 0.7 }} />
              </div>
            </div>
          );
        })}
      </div>
    </Center>
  );
};

// ── comparison ───────────────────────────────────────────────────────────────
const Comparison: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const dur = winFrames(anim, fps);
  const left = coerceColumn(anim.params?.left);
  const right = coerceColumn(anim.params?.right);
  if (!left.title && !right.title && left.points.length === 0 && right.points.length === 0) {
    return <Center><Placeholder /></Center>;
  }
  const divider = interpolate(frame, [0, dur * 0.35], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp", easing: EASE });

  // Portrait: stack the two sides top/bottom (full width) → larger text.
  const maxPts = Math.max(left.points.length, right.points.length);
  const longest = Math.max(0, ...left.points.map((p) => p.length), ...right.points.map((p) => p.length));
  const ptFont = maxPts >= 4 || longest > 30 ? 48 : maxPts >= 3 || longest > 20 ? 56 : 64;

  const block = (col: { title: string; points: string[] }, delay: number, accent: string) => (
    <div style={{ width: "100%", textAlign: "center", display: "flex", flexDirection: "column", alignItems: "center" }}>
      <FitText
        text={col.title}
        maxWidth={860}
        maxFontSize={84}
        minFontSize={44}
        maxLines={2}
        fontFamily={FONTS.display}
        fontWeight={FONTS.displayWeight}
        style={{ color: accent, lineHeight: 1.05, marginBottom: 22, textShadow: ENGRAVE_SHADOW, opacity: interpolate(frame, [delay, delay + fps * 0.4], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp" }) }}
      />
      {col.points.map((p, i) => {
        const at = delay + fps * 0.3 + i * fps * 0.3;
        const t = interpolate(frame, [at, at + fps * 0.4], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp", easing: EASE });
        return (
          <FitText
            key={i}
            text={p}
            maxWidth={860}
            maxFontSize={ptFont}
            minFontSize={30}
            maxLines={2}
            fontFamily={FONTS.body}
            fontWeight={FONTS.bodyWeight}
            style={{ color: inkC(dark), lineHeight: 1.35, marginBottom: 12, opacity: t, transform: `translateY(${(1 - t) * 18}px)` }}
          />
        );
      })}
    </div>
  );

  return (
    <Center style={{ flexDirection: "column", padding: "10% 9%", gap: 44 }}>
      {block(left, 0, COLORS.leather)}
      <div style={{ height: 4, width: "62%", background: COLORS.gold, opacity: 0.6, transform: `scaleX(${divider})` }} />
      {block(right, fps * 0.55, COLORS.teal)}
    </Center>
  );
};

// ── bullet-list ──────────────────────────────────────────────────────────────
const BulletList: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const dur = winFrames(anim, fps);
  const title = asStr(anim.params?.title);
  const items = coerceStrList(anim.params?.items, 6, 52);
  if (items.length === 0) return <Center><Placeholder /></Center>;
  const step = (dur * 0.6) / items.length;
  const itemFont = items.length >= 5 ? 52 : 62;

  return (
    <Center style={{ alignItems: "flex-start", textAlign: "left", padding: "12% 11%" }}>
      {title ? (
        <FitText
          text={title}
          maxWidth={840}
          maxFontSize={88}
          minFontSize={44}
          maxLines={2}
          fontFamily={FONTS.display}
          fontWeight={FONTS.displayWeight}
          style={{ color: inkC(dark), marginBottom: 52, textAlign: "left", textShadow: ENGRAVE_SHADOW }}
        />
      ) : null}
      <div style={{ display: "flex", flexDirection: "column", gap: 40, width: "100%" }}>
        {items.map((it, i) => {
          const t = interpolate(frame, [i * step, i * step + fps * 0.5], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp", easing: EASE });
          return (
            <div key={i} style={{ display: "flex", alignItems: "baseline", gap: 28, opacity: t, transform: `translateX(${(1 - t) * 30}px)` }}>
              <span style={{ color: COLORS.teal, fontSize: 40, lineHeight: 1.2, flexShrink: 0 }}>◆</span>
              <FitText
                text={it}
                maxWidth={760}
                maxFontSize={itemFont}
                minFontSize={32}
                maxLines={2}
                fontFamily={FONTS.body}
                fontWeight={FONTS.bodyWeight}
                style={{ color: inkC(dark), lineHeight: 1.2, textAlign: "left" }}
              />
            </div>
          );
        })}
      </div>
    </Center>
  );
};

// ── card (generic UI card: title + lines) ────────────────────────────────────
const Card: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const title = asStr(anim.params?.title) || asStr(anim.text);
  const lines = coerceStrList(anim.params?.lines ?? anim.params?.items, 6);
  if (!title && lines.length === 0) return <Center><Placeholder /></Center>;

  const s = spring({ frame, fps, config: { damping: 16, stiffness: 120 } });
  const opacity = interpolate(frame, [0, 8], [0, 1], { extrapolateRight: "clamp" });
  const scale = interpolate(s, [0, 1], [0.85, 1]);

  return (
    <Center>
      <div style={{ background: COLORS.vellum, border: panelBorder() ?? "1px solid rgba(91,70,54,0.25)", borderRadius: STYLE.sharp ? 0 : 18, padding: "56px 64px", boxShadow: PANEL_SHADOW, transform: `scale(${scale})`, opacity, maxWidth: "84%", display: "flex", flexDirection: "column", alignItems: "center" }}>
        {title ? (
          <FitText
            text={title}
            maxWidth={760}
            maxFontSize={78}
            minFontSize={40}
            maxLines={2}
            fontFamily={FONTS.display}
            fontWeight={FONTS.displayWeight}
            uppercase={STYLE.uppercaseTitles}
            letterSpacing={STYLE.uppercaseTitles ? "-0.02em" : undefined}
            style={{ color: textForBg(COLORS.vellum), lineHeight: 1.08, marginBottom: lines.length ? 28 : 0, textAlign: "center", textShadow: STYLE.shadow === "hard" ? ENGRAVE_SHADOW : undefined }}
          />
        ) : null}
        {lines.map((l, i) => (
          <FitText
            key={i}
            text={l}
            maxWidth={760}
            maxFontSize={52}
            minFontSize={30}
            maxLines={2}
            fontFamily={FONTS.body}
            fontWeight={FONTS.bodyWeight}
            style={{ color: textForBg(COLORS.vellum), lineHeight: 1.45, opacity: 0.85, textAlign: "center" }}
          />
        ))}
      </div>
    </Center>
  );
};

// ── terminal (mono card with char-by-char type-on) ───────────────────────────
const Terminal: React.FC<PrimitiveProps> = ({ anim }) => {
  const frame = useCurrentFrame();
  const lines = coerceStrList(anim.params?.lines, 8);
  if (lines.length === 0) return <Center><Placeholder /></Center>;

  const full = lines.join("\n");
  const shown = full.slice(0, Math.floor(frame * 2)); // ~2 chars/frame
  const opacity = interpolate(frame, [0, 8], [0, 1], { extrapolateRight: "clamp" });
  const caretOn = Math.floor(frame / 8) % 2 === 0;

  return (
    <Center>
      <div style={{ background: COLORS.ink, borderRadius: 14, padding: "40px 44px", width: "84%", boxShadow: PANEL_SHADOW, opacity }}>
        <div style={{ display: "flex", gap: 10, marginBottom: 24 }}>
          {[COLORS.leather, COLORS.gold, COLORS.tealBright].map((c) => (
            <span key={c} style={{ width: 16, height: 16, borderRadius: "50%", background: c, display: "inline-block" }} />
          ))}
        </div>
        <pre style={{ fontFamily: FONTS.mono, color: COLORS.textOnDark, fontSize: 34, lineHeight: 1.5, whiteSpace: "pre-wrap", margin: 0, textAlign: "left" }}>
          {shown}
          <span style={{ opacity: caretOn ? 1 : 0, color: COLORS.tealBright }}>▋</span>
        </pre>
      </div>
    </Center>
  );
};

// ── line-chart (multi-series line graph, animated draw-in) ───────────────────
const LineChart: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const dur = winFrames(anim, fps);
  const series = coerceSeries(anim.params?.series);
  const title = asStr(anim.params?.title);
  const unit = asStr(anim.params?.unit);
  const unitRight = asStr(anim.params?.unitRight);
  if (series.length === 0 || series.every((s) => s.points.length === 0)) return <Center><Placeholder /></Center>;

  const hasRight = series.some((s) => s.axis === "right");
  const W = 900, H = 620, padL = 40, padR = hasRight ? 44 : 40, padT = 40, padB = 40;
  const maxPts = Math.max(...series.map((s) => s.points.length), 2);
  const maxLeft = Math.max(...series.filter((s) => s.axis !== "right").flatMap((s) => s.points), 1);
  const maxRight = Math.max(...series.filter((s) => s.axis === "right").flatMap((s) => s.points), 1);
  const x = (i: number) => padL + (i / (maxPts - 1)) * (W - padL - padR);
  const y = (v: number, axis: "left" | "right") => padT + (1 - v / (axis === "right" ? maxRight : maxLeft)) * (H - padT - padB);
  const draw = interpolate(frame, [0, dur * 0.75], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp", easing: EASE });
  const revealX = padL + draw * (W - padL - padR);
  const rightColor = series.find((s) => s.axis === "right")?.color ?? COLORS.leather;

  return (
    <Center style={{ flexDirection: "column", padding: "12% 8%" }}>
      {title ? <div style={{ fontFamily: FONTS.display, color: inkC(dark), fontWeight: FONTS.displayWeight, fontSize: 74, marginBottom: 30, textAlign: "center", textShadow: ENGRAVE_SHADOW }}>{title}</div> : null}
      <svg viewBox={`0 0 ${W} ${H}`} style={{ width: "100%" }}>
        {/* baseline + left axis */}
        <line x1={padL} y1={H - padB} x2={W - padR} y2={H - padB} stroke={softC(dark)} strokeOpacity={0.4} strokeWidth={2} />
        <line x1={padL} y1={padT} x2={padL} y2={H - padB} stroke={series[0].color} strokeOpacity={0.5} strokeWidth={3} />
        {unit ? <text x={padL + 8} y={padT + 4} fontFamily={FONTS.mono} fontSize={26} fill={series[0].color}>{unit}</text> : null}
        {/* right axis */}
        {hasRight ? (
          <>
            <line x1={W - padR} y1={padT} x2={W - padR} y2={H - padB} stroke={rightColor} strokeOpacity={0.5} strokeWidth={3} />
            {unitRight ? <text x={W - padR - 8} y={padT + 4} textAnchor="end" fontFamily={FONTS.mono} fontSize={26} fill={rightColor}>{unitRight}</text> : null}
          </>
        ) : null}
        {series.map((s, si) => {
          const pts = s.points.length ? s.points : [0];
          const path = pts.map((v, i) => `${i === 0 ? "M" : "L"} ${x(i)} ${y(v, s.axis)}`).join(" ");
          const len = 2400;
          const dashed = s.axis === "right";
          return (
            <g key={si}>
              <path d={path} fill="none" stroke={s.color} strokeWidth={s.highlight ? 8 : 5} strokeLinejoin="round" strokeLinecap="round" strokeDasharray={dashed ? "14 10" : String(len)} strokeDashoffset={dashed ? 0 : len * (1 - draw)} opacity={dashed ? draw : s.highlight ? 1 : 0.85} />
              {pts.map((v, i) => (x(i) <= revealX + 1 ? <circle key={i} cx={x(i)} cy={y(v, s.axis)} r={s.highlight ? 9 : 6} fill={s.color} /> : null))}
            </g>
          );
        })}
      </svg>
      <div style={{ display: "flex", flexWrap: "wrap", justifyContent: "center", gap: "8px 28px", marginTop: 24 }}>
        {series.map((s, si) => (
          <span key={si} style={{ display: "flex", alignItems: "center", gap: 10, fontFamily: FONTS.body, color: inkC(dark), fontSize: 36 }}>
            <span style={{ width: 26, height: 6, background: s.color, display: "inline-block", borderRadius: 3 }} /> {s.label}{s.axis === "right" ? " (dir.)" : ""}
          </span>
        ))}
      </div>
    </Center>
  );
};

// ── scatter-chart (2-axis comparison / quadrant) ─────────────────────────────
const ScatterChart: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const dur = winFrames(anim, fps);
  const pts = coerceScatter(anim.params?.points);
  const title = asStr(anim.params?.title);
  const xLabel = asStr(anim.params?.xLabel);
  const yLabel = asStr(anim.params?.yLabel);
  if (pts.length === 0) return <Center><Placeholder /></Center>;

  const W = 900, H = 900, pL = 70, pR = 60, pT = 50, pB = 90;
  const xs = pts.map((p) => p.x), ys = pts.map((p) => p.y);
  const minX = Math.min(...xs, 0), maxX = Math.max(...xs, 1);
  const minY = Math.min(...ys, 0), maxY = Math.max(...ys, 1);
  const X = (x: number) => pL + ((x - minX) / ((maxX - minX) || 1)) * (W - pL - pR);
  const Y = (y: number) => (H - pB) - ((y - minY) / ((maxY - minY) || 1)) * (H - pT - pB);
  const axisDraw = interpolate(frame, [0, dur * 0.28], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp", easing: EASE });
  const step = (dur * 0.45) / pts.length;

  return (
    <Center style={{ flexDirection: "column", padding: "9% 6%", gap: 16 }}>
      {title ? <div style={{ fontFamily: FONTS.display, color: inkC(dark), fontWeight: FONTS.displayWeight, fontSize: 72, textAlign: "center", textShadow: ENGRAVE_SHADOW }}>{title}</div> : null}
      <svg viewBox={`0 0 ${W} ${H}`} style={{ width: "100%" }}>
        <line x1={pL} y1={pT} x2={pL} y2={H - pB} stroke={softC(dark)} strokeWidth={4} strokeDasharray={H} strokeDashoffset={H * (1 - axisDraw)} />
        <line x1={pL} y1={H - pB} x2={W - pR} y2={H - pB} stroke={softC(dark)} strokeWidth={4} strokeDasharray={W} strokeDashoffset={W * (1 - axisDraw)} />
        {yLabel ? <text x={pL - 18} y={pT + 6} transform={`rotate(-90 ${pL - 18} ${pT + 6})`} textAnchor="end" fontFamily={FONTS.mono} fontSize={30} fill={softC(dark)}>↑ {yLabel}</text> : null}
        {xLabel ? <text x={W - pR} y={H - 26} textAnchor="end" fontFamily={FONTS.mono} fontSize={30} fill={softC(dark)}>{xLabel} →</text> : null}
        {pts.map((p, i) => {
          const at = dur * 0.3 + i * step;
          const s = spring({ frame: frame - at, fps, config: { damping: 12 } });
          const op = interpolate(frame, [at, at + fps * 0.3], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });
          const color = p.highlight ? COLORS.teal : COLORS.leather;
          const cx = X(p.x);
          const labelLeft = cx > W - 240; // near right edge → put label on the left
          return (
            <g key={i} opacity={op}>
              <circle cx={cx} cy={Y(p.y)} r={(p.highlight ? 20 : 13) * Math.max(0.001, s)} fill={color} />
              <text x={labelLeft ? cx - 26 : cx + 26} y={Y(p.y) + 12} textAnchor={labelLeft ? "end" : "start"} fontFamily={FONTS.display} fontWeight={p.highlight ? 600 : 500} fontSize={p.highlight ? 40 : 34} fill={p.highlight ? COLORS.teal : inkC(dark)}>{p.label}</text>
            </g>
          );
        })}
      </svg>
    </Center>
  );
};

// ── pie-chart (animated donut) ───────────────────────────────────────────────
const PieChart: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const dur = winFrames(anim, fps);
  const slices = coerceSlices(anim.params?.slices);
  const title = asStr(anim.params?.title);
  const total = slices.reduce((a, s) => a + s.value, 0);
  if (slices.length === 0 || total <= 0) return <Center><Placeholder /></Center>;

  const R = 150, C = 2 * Math.PI * R, cx = 200, cy = 200, strokeW = 70;
  const sweep = interpolate(frame, [0, dur * 0.7], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp", easing: EASE });
  const palette = [COLORS.teal, COLORS.leather, COLORS.gold, COLORS.inkSoft, COLORS.tealBright, "#8b6db0"];
  let acc = 0;

  return (
    <Center style={{ flexDirection: "column", padding: "12% 8%", gap: 40 }}>
      {title ? <div style={{ fontFamily: FONTS.display, color: inkC(dark), fontWeight: FONTS.displayWeight, fontSize:  74, textAlign: "center", textShadow: ENGRAVE_SHADOW }}>{title}</div> : null}
      <svg viewBox="0 0 400 400" style={{ width: "62%" }}>
        <g transform="rotate(-90 200 200)">
          {slices.map((s, i) => {
            const frac = s.value / total;
            const start = acc;
            acc += frac;
            const dash = C * frac * sweep;
            const color = s.highlight ? COLORS.teal : palette[i % palette.length];
            return <circle key={i} cx={cx} cy={cy} r={R} fill="none" stroke={color} strokeWidth={s.highlight ? strokeW + 12 : strokeW} strokeDasharray={`${dash} ${C - dash}`} strokeDashoffset={-C * start} />;
          })}
        </g>
      </svg>
      <div style={{ display: "flex", flexDirection: "column", gap: 12, width: "72%" }}>
        {slices.map((s, i) => {
          const color = s.highlight ? COLORS.teal : palette[i % palette.length];
          const pct = Math.round((s.value / total) * 100);
          return (
            <span key={i} style={{ display: "flex", alignItems: "center", gap: 14, fontFamily: FONTS.body, color: inkC(dark), fontSize: 40 }}>
              <span style={{ width: 24, height: 24, background: color, borderRadius: 6, display: "inline-block" }} /> {s.label}
              <span style={{ fontFamily: FONTS.mono, color, marginLeft: "auto" }}>{pct}%</span>
            </span>
          );
        })}
      </div>
    </Center>
  );
};

// ── diagram (nodes + arrows; flow / cycle; optional image nodes) ─────────────
const Diagram: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const dur = winFrames(anim, fps);
  const nodes = coerceNodes(anim.params?.nodes);
  const title = asStr(anim.params?.title);
  const layoutRaw = asStr(anim.params?.layout);
  if (nodes.length === 0) return <Center><Placeholder /></Center>;

  const n = nodes.length;
  const layout = layoutRaw === "horizontal" || layoutRaw === "cycle" ? layoutRaw : "vertical";

  // Node centres in a 1000×1000 space.
  const M = 170;
  const pos = nodes.map((_, i) => {
    if (layout === "cycle") {
      const a = (-90 + (i * 360) / n) * (Math.PI / 180);
      return { x: 500 + 330 * Math.cos(a), y: 500 + 330 * Math.sin(a) };
    }
    const t = n > 1 ? i / (n - 1) : 0.5;
    return layout === "horizontal" ? { x: M + t * (1000 - 2 * M), y: 500 } : { x: 500, y: M + t * (1000 - 2 * M) };
  });

  let edges = coerceEdges(anim.params?.edges).filter((e) => e.from >= 0 && e.from < n && e.to >= 0 && e.to < n && e.from !== e.to);
  if (edges.length === 0) {
    edges = layout === "cycle"
      ? nodes.map((_, i) => ({ from: i, to: (i + 1) % n, label: "" }))
      : nodes.slice(0, -1).map((_, i) => ({ from: i, to: i + 1, label: "" }));
  }

  const nodeStep = (dur * 0.4) / Math.max(1, n);
  const boxW = layout === "cycle" ? 290 : 360;
  const boxH = layout === "cycle" ? 150 : 176;
  const nodeFont = layout === "cycle" ? 30 : 36;

  return (
    <Center style={{ flexDirection: "column", padding: "10% 6%", gap: 20 }}>
      {title ? <div style={{ fontFamily: FONTS.display, color: inkC(dark), fontWeight: FONTS.displayWeight, fontSize: 60, textAlign: "center", textShadow: ENGRAVE_SHADOW }}>{title}</div> : null}
      <div style={{ position: "relative", width: "100%", aspectRatio: "1 / 1" }}>
        <svg viewBox="0 0 1000 1000" style={{ position: "absolute", inset: 0, width: "100%", height: "100%" }}>
          <defs>
            <marker id="arrow" markerWidth="10" markerHeight="10" refX="7" refY="3" orient="auto">
              <path d="M0,0 L7,3 L0,6 Z" fill={COLORS.gold} />
            </marker>
          </defs>
          {edges.map((e, i) => {
            const a = pos[e.from], b = pos[e.to];
            const dx = b.x - a.x, dy = b.y - a.y;
            const dist = Math.hypot(dx, dy) || 1;
            const ux = dx / dist, uy = dy / dist;
            const off = layout === "cycle" ? 95 : 100;
            const x1 = a.x + ux * off, y1 = a.y + uy * off, x2 = b.x - ux * off, y2 = b.y - uy * off;
            const appearAt = Math.max(e.from, e.to) * nodeStep + fps * 0.35;
            const d = interpolate(frame, [appearAt, appearAt + fps * 0.4], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp", easing: EASE });
            const len = Math.hypot(x2 - x1, y2 - y1);
            return <line key={i} x1={x1} y1={y1} x2={x2} y2={y2} stroke={COLORS.gold} strokeWidth={5} markerEnd="url(#arrow)" strokeDasharray={len} strokeDashoffset={len * (1 - d)} opacity={0.85} />;
          })}
        </svg>
        {nodes.map((node, i) => {
          const p = pos[i];
          const at = i * nodeStep;
          const s = spring({ frame: frame - at, fps, config: { damping: 13 } });
          const op = interpolate(frame, [at, at + fps * 0.3], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });
          const border = node.highlight ? COLORS.teal : COLORS.leather;
          return (
            <div key={i} style={{ position: "absolute", left: `${(p.x / 1000) * 100}%`, top: `${(p.y / 1000) * 100}%`, width: `${(boxW / 1000) * 100}%`, height: `${(boxH / 1000) * 100}%`, transform: `translate(-50%,-50%) scale(${0.7 + 0.3 * s})`, opacity: op, background: COLORS.vellum, border: `3px solid ${border}`, borderRadius: 14, boxShadow: PANEL_SHADOW, display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center", gap: 8, padding: 14, overflow: "hidden" }}>
              {node.image ? <Img src={node.image} style={{ maxWidth: "60%", maxHeight: "48%", objectFit: "contain" }} /> : null}
              <span style={{ fontFamily: FONTS.display, color: node.highlight ? COLORS.teal : COLORS.textOnLight, fontWeight: FONTS.displayWeight, fontSize: nodeFont, lineHeight: 1.1, textAlign: "center" }}>{node.label}</span>
            </div>
          );
        })}
      </div>
    </Center>
  );
};

export const PRIMITIVES: Record<string, React.FC<PrimitiveProps>> = {
  fade: Fade,
  card: Card,
  terminal: Terminal,
  slide: Slide,
  scale: Scale,
  "kinetic-text": KineticText,
  highlight: Highlight,
  "fleuron-draw": FleuronDraw,
  "seal-stamp": SealStamp,
  "underline-sweep": UnderlineSweep,
  "count-up": CountUp,
  "image-reveal": ImageReveal,
  ambient: Ambient,
  timeline: Timeline,
  "bar-chart": BarChart,
  "line-chart": LineChart,
  "pie-chart": PieChart,
  "scatter-chart": ScatterChart,
  comparison: Comparison,
  "bullet-list": BulletList,
  diagram: Diagram,
  // Custom, AI-generated SFX (rebuilt from the active clip_effects rows).
  ...CUSTOM_PRIMITIVES,
};

// A generated effect that throws at runtime (on params the test-render didn't
// exercise) must not take the whole clip down with it — render nothing instead.
class EffectBoundary extends React.Component<{ children: React.ReactNode }, { failed: boolean }> {
  state = { failed: false };
  static getDerivedStateFromError() {
    return { failed: true };
  }
  render() {
    return this.state.failed ? null : this.props.children;
  }
}

// A local image staged into public/ (e.g. "clip-asset-<hash>.png") must be loaded
// via staticFile() or Remotion can't serve it → 404. Built-in primitives call
// staticFile themselves; custom (generated) effects use <Img src={params.src}>
// raw, so we resolve every local image path in their params to a staticFile URL
// here. https/absolute paths pass through untouched.
const LOCAL_IMG = /\.(png|jpe?g|webp|gif|bmp|avif)$/i;
const resolveAssetUrls = (value: unknown): unknown => {
  if (typeof value === "string") {
    return LOCAL_IMG.test(value) && !/^https?:\/\//.test(value) && !value.startsWith("/") ? staticFile(value) : value;
  }
  if (Array.isArray(value)) return value.map(resolveAssetUrls);
  if (value && typeof value === "object") {
    return Object.fromEntries(Object.entries(value).map(([k, v]) => [k, resolveAssetUrls(v)]));
  }
  return value;
};

export function renderPrimitive(anim: Animation, fps: number, dark = false): React.ReactNode {
  const isCustom = anim.primitive in CUSTOM_PRIMITIVES;
  // Custom effects load images raw, so hand them staticFile()-resolved paths.
  const a = isCustom && anim.params ? { ...anim, params: resolveAssetUrls(anim.params) as typeof anim.params } : anim;
  const Comp = PRIMITIVES[anim.primitive] ?? Fade;
  const node = <Comp anim={a} fps={fps} dark={dark} />;
  // Isolate custom effects behind an error boundary; built-ins are trusted.
  return isCustom ? <EffectBoundary>{node}</EffectBoundary> : node;
}
