import React from "react";
import { AbsoluteFill, useCurrentFrame, interpolate, spring, Easing } from "remotion";
import { COLORS, FONTS, headlineGradient, ENGRAVE_SHADOW } from "../style-tokens";
import type { PrimitiveProps } from "../primitives";

// Deterministic per-seed noise so particle fields are identical across renders.
const rand = (seed: number): number => {
  const x = Math.sin(seed * 127.1 + 311.7) * 43758.5453;
  return x - Math.floor(x);
};

const clamp01 = (v: number): number => Math.max(0, Math.min(1, v));
const lerp = (a: number, b: number, t: number): number => a + (b - a) * t;
const easeOut = Easing.out(Easing.cubic);

// Point on a rectangle perimeter (local coords, centred on 0,0).
const perimPoint = (t: number, w: number, h: number): [number, number] => {
  const per = 2 * (w + h);
  let d = (((t % 1) + 1) % 1) * per;
  if (d < w) return [-w / 2 + d, -h / 2];
  d -= w;
  if (d < h) return [w / 2, -h / 2 + d];
  d -= h;
  if (d < w) return [w / 2 - d, h / 2];
  d -= w;
  return [-w / 2, h / 2 - d];
};

const PARTS_PER_CARD = 26;
const PARTS_PER_ARROW = 12;

const ParticleDiagram: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const total = Math.max(1, Math.round((anim.end - anim.start) * fps));
  const params = (anim.params ?? {}) as Record<string, unknown>;

  const rawTitle = typeof params.title === "string" ? params.title : (anim.text ?? "");
  const title = String(rawTitle ?? "").trim();
  const layout = params.layout === "horizontal" ? "horizontal" : "vertical";

  const labels = (Array.isArray(params.nodes) ? (params.nodes as unknown[]) : [])
    .map((node) => {
      if (typeof node === "string") return node;
      if (node && typeof node === "object") {
        const l = (node as { label?: unknown }).label;
        return typeof l === "string" ? l : "";
      }
      return "";
    })
    .map((s) => s.trim())
    .filter((s) => s.length > 0)
    .slice(0, 6);

  const n = labels.length;
  const textColor = dark ? COLORS.textOnDark : COLORS.textOnLight;
  const mutedColor = dark ? COLORS.mutedOnDark : COLORS.mutedOnLight;
  const accents = [COLORS.tealBright, COLORS.teal, COLORS.gold];

  // ---------- timing ----------
  const intro = Math.round(fps * 0.45);
  const form = Math.max(8, Math.round(fps * 0.62));
  const idealStep = Math.round(fps * 0.52);
  const room = total * 0.78 - intro - form;
  const step = n > 1 ? Math.max(4, Math.min(idealStep, Math.floor(room / (n - 1)))) : idealStep;
  const nodeStart = (i: number): number => intro + i * step;
  const arrowLead = Math.round(fps * 0.24);
  const arrowDur = Math.max(6, Math.round(fps * 0.36));

  // ---------- geometry (generous spacing) ----------
  const W = 1080;
  const hasTitle = title.length > 0;
  const centerY = hasTitle ? 1060 : 960;

  const cardW =
    layout === "horizontal"
      ? Math.max(150, Math.min(300, Math.floor((900 - (n - 1) * 110) / Math.max(1, n))))
      : 700;
  const cardH = layout === "horizontal" ? 250 : n <= 3 ? 178 : n <= 4 ? 152 : 126;
  const gap = layout === "horizontal" ? 110 : n <= 3 ? 215 : n <= 4 ? 170 : 124;

  const blockV = n * cardH + Math.max(0, n - 1) * gap;
  const blockW = n * cardW + Math.max(0, n - 1) * gap;
  const topV = centerY - blockV / 2;
  const leftH = (W - blockW) / 2;

  const centers = labels.map((_, i) =>
    layout === "horizontal"
      ? { x: leftH + i * (cardW + gap) + cardW / 2, y: centerY }
      : { x: W / 2, y: topV + i * (cardH + gap) + cardH / 2 }
  );

  // ---------- title ----------
  const titleSpring = spring({
    frame,
    fps,
    config: { damping: 200 },
    durationInFrames: Math.max(10, Math.round(fps * 0.7)),
  });
  const titleSize = title.length > 22 ? 58 : title.length > 14 ? 68 : 80;

  // ---------- particles ----------
  type Dot = { key: string; x: number; y: number; size: number; opacity: number; color: string };
  const dots: Dot[] = [];

  centers.forEach((c, i) => {
    const start = nodeStart(i);
    for (let k = 0; k < PARTS_PER_CARD; k++) {
      const seed = i * 131 + k * 7 + 3;
      const delay = rand(seed + 3) * 0.35;
      const span = form * (1 - delay);
      const prog = clamp01((frame - start - delay * form) / Math.max(1, span));
      if (prog <= 0 || prog >= 1) continue;
      const [tx, ty] = perimPoint(k / PARTS_PER_CARD + rand(seed) * 0.03, cardW, cardH);
      const ang = rand(seed + 1) * Math.PI * 2;
      const dist = 150 + rand(seed + 2) * 230;
      const e = easeOut(prog);
      const size = 3 + rand(seed + 4) * 4;
      dots.push({
        key: "c" + i + "-" + k,
        x: c.x + lerp(tx + Math.cos(ang) * dist, tx, e),
        y: c.y + lerp(ty + Math.sin(ang) * dist, ty, e),
        size: size * (1 - 0.4 * prog),
        opacity: Math.min(prog / 0.2, 1) * (1 - clamp01((prog - 0.68) / 0.32)),
        color: accents[k % accents.length],
      });
    }
  });

  // ---------- arrows ----------
  type Arrow = { key: string; x1: number; y1: number; x2: number; y2: number; prog: number };
  const arrows: Arrow[] = [];
  const inset = 26;

  for (let i = 1; i < n; i++) {
    const a = centers[i - 1];
    const b = centers[i];
    const seg =
      layout === "horizontal"
        ? { x1: a.x + cardW / 2 + inset, y1: a.y, x2: b.x - cardW / 2 - inset, y2: b.y }
        : { x1: a.x, y1: a.y + cardH / 2 + inset, x2: b.x, y2: b.y - cardH / 2 - inset };
    const aStart = nodeStart(i) - arrowLead;
    const prog = clamp01((frame - aStart) / arrowDur);
    if (prog <= 0) continue;
    arrows.push({ key: "a" + i, ...seg, prog });

    for (let k = 0; k < PARTS_PER_ARROW; k++) {
      const seed = 900 + i * 53 + k * 11;
      const delay = rand(seed + 3) * 0.4;
      const p = clamp01((prog - delay) / Math.max(0.05, 1 - delay));
      if (p <= 0 || p >= 1) continue;
      const t = (k + 0.5) / PARTS_PER_ARROW;
      const tx = lerp(seg.x1, seg.x2, t);
      const ty = lerp(seg.y1, seg.y2, t);
      const spread = (rand(seed + 1) - 0.5) * 220;
      const drift = (rand(seed + 2) - 0.5) * 90;
      const e = easeOut(p);
      dots.push({
        key: "ap" + i + "-" + k,
        x: layout === "horizontal" ? tx + lerp(drift, 0, e) : tx + lerp(spread, 0, e),
        y: layout === "horizontal" ? ty + lerp(spread, 0, e) : ty + lerp(drift, 0, e),
        size: (2.5 + rand(seed + 4) * 3.5) * (1 - 0.35 * p),
        opacity: Math.min(p / 0.22, 1) * (1 - clamp01((p - 0.6) / 0.4)),
        color: accents[(k + i) % accents.length],
      });
    }
  }

  return (
    <AbsoluteFill style={{ width: 1080, height: 1920, overflow: "hidden" }}>
      {hasTitle ? (
        <div
          style={{
            position: "absolute",
            top: 240,
            left: 0,
            right: 0,
            display: "flex",
            flexDirection: "column",
            alignItems: "center",
            gap: 26,
            opacity: titleSpring,
            transform: "translateY(" + (1 - titleSpring) * 26 + "px)",
          }}
        >
          <div
            style={{
              fontFamily: FONTS.display,
              fontWeight: FONTS.displayWeight,
              fontSize: titleSize,
              lineHeight: 0.92,
              letterSpacing: "0.01em",
              textTransform: "uppercase",
              textAlign: "center",
              padding: "0 80px",
              backgroundImage: headlineGradient(),
              WebkitBackgroundClip: "text",
              backgroundClip: "text",
              color: "transparent",
            }}
          >
            {title}
          </div>
          <div
            style={{
              width: 120 * titleSpring,
              height: 4,
              borderRadius: 999,
              backgroundImage: headlineGradient(),
            }}
          />
        </div>
      ) : null}

      {/* arrows */}
      <svg
        width={1080}
        height={1920}
        viewBox="0 0 1080 1920"
        style={{ position: "absolute", left: 0, top: 0 }}
      >
        {arrows.map((ar) => {
          const len = Math.hypot(ar.x2 - ar.x1, ar.y2 - ar.y1);
          const draw = easeOut(clamp01(ar.prog / 0.85));
          const headOpacity = clamp01((ar.prog - 0.62) / 0.38);
          const head =
            layout === "horizontal"
              ? (ar.x2 - 18) + "," + (ar.y2 - 15) + " " + ar.x2 + "," + ar.y2 + " " + (ar.x2 - 18) + "," + (ar.y2 + 15)
              : (ar.x2 - 15) + "," + (ar.y2 - 18) + " " + ar.x2 + "," + ar.y2 + " " + (ar.x2 + 15) + "," + (ar.y2 - 18);
          return (
            <g key={ar.key}>
              <line
                x1={ar.x1}
                y1={ar.y1}
                x2={ar.x2}
                y2={ar.y2}
                stroke={COLORS.teal}
                strokeWidth={3}
                strokeLinecap="round"
                strokeDasharray={len}
                strokeDashoffset={len * (1 - draw)}
                opacity={0.9}
              />
              <polyline
                points={head}
                fill="none"
                stroke={COLORS.teal}
                strokeWidth={3}
                strokeLinecap="round"
                strokeLinejoin="round"
                opacity={headOpacity}
              />
            </g>
          );
        })}
      </svg>

      {/* cards */}
      {centers.map((c, i) => {
        const start = nodeStart(i);
        const raw = clamp01((frame - start - form * 0.5) / Math.max(1, form * 0.5));
        const cp = easeOut(raw);
        if (cp <= 0) return null;
        const label = labels[i];
        const labelSize =
          layout === "horizontal"
            ? label.length > 14
              ? 30
              : 38
            : label.length > 26
              ? 42
              : label.length > 16
                ? 50
                : 58;
        return (
          <div
            key={"card" + i}
            style={{
              position: "absolute",
              left: c.x - cardW / 2,
              top: c.y - cardH / 2,
              width: cardW,
              height: cardH,
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              padding: "0 34px",
              boxSizing: "border-box",
              borderRadius: 22,
              background: COLORS.vellum,
              border: "2px solid " + COLORS.leather,
              boxShadow: "0 24px 60px " + COLORS.ink,
              opacity: cp,
              transform: "scale(" + lerp(0.92, 1, cp) + ")",
              filter: "blur(" + (1 - cp) * 7 + "px)",
            }}
          >
            <div
              style={{
                position: "absolute",
                top: 16,
                left: 22,
                fontFamily: FONTS.mono,
                fontSize: 18,
                letterSpacing: "0.18em",
                color: mutedColor,
              }}
            >
              {(i + 1).toString().padStart(2, "0")}
            </div>
            <div
              style={{
                fontFamily: FONTS.body,
                fontWeight: Math.max(FONTS.bodyWeight, 600),
                fontSize: labelSize,
                lineHeight: 1.15,
                textAlign: "center",
                color: textColor,
                textShadow: ENGRAVE_SHADOW,
              }}
            >
              {label}
            </div>
          </div>
        );
      })}

      {/* particle field (cards + arrows assembling) */}
      {dots.map((d) => (
        <div
          key={d.key}
          style={{
            position: "absolute",
            left: d.x,
            top: d.y,
            width: d.size,
            height: d.size,
            marginLeft: -d.size / 2,
            marginTop: -d.size / 2,
            borderRadius: 999,
            background: d.color,
            boxShadow: "0 0 12px " + d.color,
            opacity: d.opacity,
          }}
        />
      ))}

      {/* keeps interpolate imported-and-used for the ambient settle glow */}
      <div
        style={{
          position: "absolute",
          inset: 0,
          pointerEvents: "none",
          opacity: interpolate(frame, [0, intro, total], [0, 0.12, 0.05], {
            extrapolateLeft: "clamp",
            extrapolateRight: "clamp",
          }),
          background:
            "radial-gradient(60% 40% at 50% " + Math.round(centerY) + "px, " + COLORS.teal + ", transparent 70%)",
        }}
      />
    </AbsoluteFill>
  );
};

export default ParticleDiagram;