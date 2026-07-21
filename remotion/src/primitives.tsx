import React from "react";
import {
  AbsoluteFill,
  Easing,
  Img,
  interpolate,
  spring,
  useCurrentFrame,
} from "remotion";
import { COLORS, ENGRAVE_SHADOW, FONTS } from "./style-tokens";
import type { Animation } from "./types";

// Every primitive receives the animation descriptor plus the composition fps.
// `useCurrentFrame()` is LOCAL to the wrapping <Sequence>, i.e. it starts at 0
// when the animation window begins.
export interface PrimitiveProps {
  anim: Animation;
  fps: number;
}

const EASE = Easing.inOut(Easing.cubic);

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

const titleStyle: React.CSSProperties = {
  fontFamily: FONTS.display,
  color: COLORS.ink,
  fontWeight: 600,
  fontSize: 96,
  lineHeight: 1.1,
  textShadow: ENGRAVE_SHADOW,
  margin: 0,
};

// ── fade ────────────────────────────────────────────────────────────────────
const Fade: React.FC<PrimitiveProps> = ({ anim, fps }) => {
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
      <p style={{ ...titleStyle, opacity }}>{anim.text ?? ""}</p>
    </Center>
  );
};

// ── slide ───────────────────────────────────────────────────────────────────
const Slide: React.FC<PrimitiveProps> = ({ anim, fps }) => {
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
      <p style={{ ...titleStyle, transform: `translate(${tx}px, ${ty}px)`, opacity }}>
        {anim.text ?? ""}
      </p>
    </Center>
  );
};

// ── scale ───────────────────────────────────────────────────────────────────
const Scale: React.FC<PrimitiveProps> = ({ anim, fps }) => {
  const frame = useCurrentFrame();
  const dur = winFrames(anim, fps);
  const s = spring({ frame, fps, config: { damping: 14, mass: 0.8 }, durationInFrames: Math.min(dur, fps) });
  const scale = interpolate(s, [0, 1], [0.6, 1]);
  const opacity = interpolate(frame, [0, Math.min(10, dur)], [0, 1], {
    extrapolateRight: "clamp",
  });
  return (
    <Center>
      <p style={{ ...titleStyle, transform: `scale(${scale})`, opacity }}>{anim.text ?? ""}</p>
    </Center>
  );
};

// ── kinetic-text (word fade + rise reveal) ───────────────────────────────────
const KineticText: React.FC<PrimitiveProps> = ({ anim, fps }) => {
  const frame = useCurrentFrame();
  const dur = winFrames(anim, fps);
  const words = (anim.text ?? "").split(/\s+/).filter(Boolean);
  const perWord = words.length > 0 ? Math.max(3, (dur * 0.6) / words.length) : 1;
  return (
    <Center>
      <p style={{ ...titleStyle, display: "flex", flexWrap: "wrap", justifyContent: "center", gap: "0 0.28em" }}>
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
              style={{ display: "inline-block", opacity, transform: `translateY(${rise}px)` }}
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
const Highlight: React.FC<PrimitiveProps> = ({ anim, fps }) => {
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
        <span style={{ ...titleStyle, position: "relative", zIndex: 1, opacity }}>
          {anim.text ?? ""}
        </span>
      </span>
    </Center>
  );
};

// ── fleuron-draw (ornamental divider drawing in) ─────────────────────────────
const FleuronDraw: React.FC<PrimitiveProps> = ({ anim, fps }) => {
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
      <div style={{ display: "flex", alignItems: "center", gap: 18, color: COLORS.leather }}>
        <span style={{ height: 3, width: lineW, background: COLORS.gold, display: "block" }} />
        <span
          style={{
            fontFamily: FONTS.display,
            fontSize: 84,
            transform: `scale(${glyphScale})`,
            opacity: glyphOpacity,
            color: COLORS.leather,
            lineHeight: 1,
          }}
        >
          &#10087;
        </span>
        <span style={{ height: 3, width: lineW, background: COLORS.gold, display: "block" }} />
      </div>
    </Center>
  );
};

// ── seal-stamp (circular library stamp scaling + rotating in) ────────────────
const SealStamp: React.FC<PrimitiveProps> = ({ anim, fps }) => {
  const frame = useCurrentFrame();
  const dur = winFrames(anim, fps);
  const s = spring({ frame, fps, config: { damping: 12, mass: 0.9 }, durationInFrames: Math.min(dur, fps) });
  const scale = interpolate(s, [0, 1], [1.7, 1]);
  const rotate = interpolate(s, [0, 1], [-24, -7]);
  const opacity = interpolate(s, [0, 1], [0, 0.85]);
  const label = anim.text ?? "IATECA";
  return (
    <Center>
      <div
        style={{
          width: 420,
          height: 420,
          borderRadius: "50%",
          border: `8px solid ${COLORS.leather}`,
          boxShadow: `inset 0 0 0 12px ${COLORS.papyrus}, inset 0 0 0 16px ${COLORS.leather}`,
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          transform: `scale(${scale}) rotate(${rotate}deg)`,
          opacity,
          color: COLORS.leather,
          fontFamily: FONTS.display,
          fontWeight: 700,
          letterSpacing: "0.18em",
          fontSize: 64,
        }}
      >
        <span style={{ transform: "translateY(-2px)" }}>{label}</span>
      </div>
    </Center>
  );
};

// ── underline-sweep ──────────────────────────────────────────────────────────
const UnderlineSweep: React.FC<PrimitiveProps> = ({ anim, fps }) => {
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
        <p style={{ ...titleStyle, opacity, marginBottom: 12 }}>{anim.text ?? ""}</p>
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
const CountUp: React.FC<PrimitiveProps> = ({ anim, fps }) => {
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

// ── image-reveal (clip/mask reveal; papyrus panel fallback) ──────────────────
const ImageReveal: React.FC<PrimitiveProps> = ({ anim, fps }) => {
  const frame = useCurrentFrame();
  const dur = winFrames(anim, fps);
  const src = anim.params?.src;
  const reveal = interpolate(frame, [0, dur * 0.7], [0, 100], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
    easing: EASE,
  });
  const clip = `inset(0 ${100 - reveal}% 0 0)`;
  return (
    <Center>
      <div
        style={{
          width: "78%",
          aspectRatio: "4 / 5",
          overflow: "hidden",
          clipPath: clip,
          border: `6px solid ${COLORS.leather}`,
          background: COLORS.vellum,
          boxShadow: ENGRAVE_SHADOW,
        }}
      >
        {src ? (
          <Img src={src} style={{ width: "100%", height: "100%", objectFit: "cover" }} />
        ) : (
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
              letterSpacing: "0.1em",
            }}
          >
            {anim.text ?? "IATECA"}
          </div>
        )}
      </div>
    </Center>
  );
};

// ── ambient (subtle always-present background motion) ────────────────────────
const Ambient: React.FC<PrimitiveProps> = ({ anim, fps }) => {
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

export const PRIMITIVES: Record<string, React.FC<PrimitiveProps>> = {
  fade: Fade,
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
};

export function renderPrimitive(anim: Animation, fps: number): React.ReactNode {
  const Comp = PRIMITIVES[anim.primitive] ?? Fade;
  return <Comp anim={anim} fps={fps} />;
}
