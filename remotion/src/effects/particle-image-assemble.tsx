import React from "react";
import { AbsoluteFill, Img, useCurrentFrame, interpolate, Easing } from "remotion";
import { COLORS, FONTS, headlineGradient, ENGRAVE_SHADOW } from "../style-tokens";
import type { PrimitiveProps } from "../primitives";

const CLAMP = { extrapolateLeft: "clamp", extrapolateRight: "clamp" } as const;

const mulberry32 = (seed: number) => {
  let a = seed >>> 0;
  return () => {
    a = (a + 0x6d2b79f5) >>> 0;
    let t = Math.imul(a ^ (a >>> 15), 1 | a);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
};

const num = (v: unknown, d: number): number => (typeof v === "number" && isFinite(v) ? v : d);
const str = (v: unknown, d: string): string =>
  typeof v === "string" && v.trim().length > 0 ? v.trim() : d;
const clamp = (v: number, lo: number, hi: number): number => Math.max(lo, Math.min(hi, v));

const ParticleImageAssemble: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const dur = Math.max(1, Math.round((anim.end - anim.start) * fps));
  const p = (anim.params ?? {}) as Record<string, unknown>;

  const src = str(p.src ?? p.image ?? p.imageUrl ?? p.url, "");
  const boxW = clamp(num(p.size, 840), 320, 1000);
  const aspect = clamp(num(p.aspect, 1), 0.5, 2);
  const boxH = boxW / aspect;
  const count = Math.round(clamp(num(p.count, 150), 24, 320));
  const radius = clamp(num(p.radius, 18), 0, 400);
  const solid = p.transparent !== true;

  const text = str(anim.text, "");
  const textColor = dark ? COLORS.textOnDark : COLORS.textOnLight;
  const muted = dark ? COLORS.mutedOnDark : COLORS.mutedOnLight;

  const assembleEnd = Math.max(8, Math.round(dur * 0.55));
  const revealEnd = Math.min(dur, assembleEnd + Math.max(8, Math.round(dur * 0.2)));

  const cols = Math.max(1, Math.round(Math.sqrt(count * aspect)));
  const rows = Math.max(1, Math.ceil(count / cols));
  const cellW = boxW / cols;
  const cellH = boxH / rows;

  // Accent hues only — one gold family plus the electric-blue accent, star-white sparks.
  const hues = [COLORS.tealBright, COLORS.teal, COLORS.gold, COLORS.leather, COLORS.textOnLight];

  const particles = React.useMemo(() => {
    const r = mulberry32(9161 + count * 31 + cols * 7);
    const out: {
      tx: number;
      ty: number;
      sx: number;
      sy: number;
      size: number;
      hueIdx: number;
      delay: number;
      spin: number;
      twinkle: number;
      seed: number;
      round: boolean;
    }[] = [];
    for (let i = 0; i < count; i++) {
      const gx = (i % cols) + 0.5;
      const gy = Math.floor(i / cols) + 0.5;
      const ang = r() * Math.PI * 2;
      const rad = 640 + r() * 760;
      out.push({
        tx: (gx + (r() - 0.5) * 0.5) * cellW - boxW / 2,
        ty: (gy + (r() - 0.5) * 0.5) * cellH - boxH / 2,
        sx: Math.cos(ang) * rad,
        sy: Math.sin(ang) * rad * 1.2,
        size: Math.max(4, Math.min(cellW, cellH) * (0.3 + r() * 0.55)),
        hueIdx: Math.floor(r() * 5),
        delay: Math.floor(r() * assembleEnd * 0.45),
        spin: (r() - 0.5) * 260,
        twinkle: 2.6 + r() * 4.5,
        seed: r(),
        round: r() > 0.35,
      });
    }
    return out;
  }, [count, cols, rows, cellW, cellH, boxW, boxH, assembleEnd]);

  const imgOpacity = interpolate(frame, [assembleEnd - 4, revealEnd], [0, 1], {
    ...CLAMP,
    easing: Easing.inOut(Easing.cubic),
  });
  const pulse = interpolate(frame, [assembleEnd - 4, assembleEnd + 10], [1.05, 1], {
    ...CLAMP,
    easing: Easing.out(Easing.cubic),
  });
  const frameGlow = interpolate(frame, [assembleEnd - 6, assembleEnd + 6, revealEnd], [0, 1, 0.4], CLAMP);
  const capIn = interpolate(frame, [revealEnd - 8, revealEnd + 10], [0, 1], {
    ...CLAMP,
    easing: Easing.out(Easing.cubic),
  });

  const background = solid
    ? `radial-gradient(120% 80% at 50% 12%, ${COLORS.ink} 0%, ${COLORS.papyrus} 62%)`
    : undefined;

  return (
    <AbsoluteFill
      style={{
        background,
        alignItems: "center",
        justifyContent: "center",
        flexDirection: "column",
        gap: 56,
      }}
    >
      <div
        style={{
          position: "relative",
          width: boxW,
          height: boxH,
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
        }}
      >
        {/* assembled image */}
        <div
          style={{
            position: "absolute",
            width: boxW,
            height: boxH,
            borderRadius: radius,
            overflow: "hidden",
            opacity: imgOpacity,
            transform: `scale(${pulse})`,
            boxShadow: ENGRAVE_SHADOW,
            background: COLORS.vellum,
          }}
        >
          {src ? (
            <Img
              src={src}
              style={{ width: "100%", height: "100%", objectFit: "cover", display: "block" }}
            />
          ) : (
            <div
              style={{
                width: "100%",
                height: "100%",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                background: `repeating-linear-gradient(135deg, ${COLORS.vellum} 0px, ${COLORS.vellum} 18px, ${COLORS.ink} 18px, ${COLORS.ink} 36px)`,
                fontFamily: FONTS.mono,
                fontSize: 26,
                letterSpacing: "0.18em",
                color: muted,
              }}
            >
              YOUR IMAGE
            </div>
          )}
        </div>

        {/* accent frame that snaps in on impact */}
        <div
          style={{
            position: "absolute",
            width: boxW,
            height: boxH,
            borderRadius: radius,
            border: `2px solid ${COLORS.teal}`,
            opacity: frameGlow * 0.55,
            transform: `scale(${pulse})`,
            pointerEvents: "none",
          }}
        />

        {/* particle field */}
        {particles.map((pt, i) => {
          const t = frame - pt.delay;
          const span = Math.max(8, assembleEnd - pt.delay);
          const e = interpolate(t, [0, span], [0, 1], {
            ...CLAMP,
            easing: Easing.out(Easing.cubic),
          });
          const x = pt.sx + (pt.tx - pt.sx) * e;
          const y = pt.sy + (pt.ty - pt.sy) * e;
          const appear = interpolate(t, [0, 8], [0, 1], CLAMP);
          const lingering = pt.seed > 0.72;
          const fadeStart = assembleEnd + pt.seed * (revealEnd - assembleEnd) * 0.8;
          const twinkle = 0.5 + 0.45 * Math.sin(frame / pt.twinkle + pt.hueIdx * 1.7);
          const settle = lingering
            ? interpolate(frame, [assembleEnd, revealEnd], [1, twinkle], CLAMP)
            : interpolate(frame, [fadeStart, fadeStart + 12], [1, 0], CLAMP);
          const opacity = appear * settle;
          if (opacity <= 0.01) return null;
          const color = hues[pt.hueIdx % hues.length];
          const scale = 0.55 + 0.45 * e;
          const blur = (1 - e) * 3.5;
          return (
            <div
              key={i}
              style={{
                position: "absolute",
                left: "50%",
                top: "50%",
                width: pt.size,
                height: pt.size,
                marginLeft: -pt.size / 2,
                marginTop: -pt.size / 2,
                borderRadius: pt.round ? "50%" : Math.max(2, pt.size * 0.22),
                background: color,
                boxShadow: `0 0 ${Math.round(pt.size * 1.6)}px ${color}`,
                opacity,
                filter: blur > 0.05 ? `blur(${blur}px)` : undefined,
                transform: `translate(${x}px, ${y}px) scale(${scale}) rotate(${pt.spin * (1 - e)}deg)`,
              }}
            />
          );
        })}
      </div>

      {text ? (
        <div
          style={{
            maxWidth: 900,
            textAlign: "center",
            fontFamily: FONTS.display,
            fontWeight: FONTS.displayWeight,
            fontSize: 78,
            lineHeight: 0.92,
            textTransform: "uppercase",
            letterSpacing: "0.01em",
            backgroundImage: headlineGradient(),
            WebkitBackgroundClip: "text",
            backgroundClip: "text",
            color: "transparent",
            opacity: capIn,
            transform: `translateY(${(1 - capIn) * 26}px)`,
          }}
        >
          {text}
        </div>
      ) : (
        <div style={{ height: 0, color: textColor }} />
      )}
    </AbsoluteFill>
  );
};

export default ParticleImageAssemble;