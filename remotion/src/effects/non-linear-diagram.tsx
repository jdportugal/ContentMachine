import React from "react";
import { AbsoluteFill, useCurrentFrame, interpolate, spring, Easing } from "remotion";
import { COLORS, FONTS, headlineGradient, ENGRAVE_SHADOW } from "../style-tokens";
import type { PrimitiveProps } from "../primitives";

// deterministic PRNG so node scatter is stable across renders
const rng = (seed: number) => {
  let s = (seed >>> 0) || 1;
  return () => {
    s = (s * 1664525 + 1013904223) >>> 0;
    return s / 4294967296;
  };
};

const clamp = (v: number, lo: number, hi: number) => Math.min(hi, Math.max(lo, v));

const NonLinearDiagram: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const total = Math.round((anim.end - anim.start) * fps);
  const p = (anim.params ?? {}) as Record<string, unknown>;

  const raw = Array.isArray(p.nodes) ? (p.nodes as unknown[]) : [];
  const given = raw.map((n) => String(n)).filter((n) => n.length > 0).slice(0, 6);
  const labels = given.length > 0 ? given : ["Ideia", "Dados", "Modelo", "Entrega"];
  const hub = String(anim.text ?? "").trim() || "NÚCLEO";
  const eyebrow = typeof p.title === "string" ? (p.title as string) : "";
  const seed = typeof p.seed === "number" ? (p.seed as number) : 7;

  const textColor = dark ? COLORS.textOnDark : COLORS.textOnLight;
  const mutedColor = dark ? COLORS.mutedOnDark : COLORS.mutedOnLight;

  const CX = 540;
  const CY = 1040;

  const rand = rng(seed * 9973 + 17);
  const pts = labels.map((label, i) => {
    const angle = (i / labels.length) * Math.PI * 2 - Math.PI / 2 + (rand() - 0.5) * 0.85;
    const radius = 300 + rand() * 180;
    return {
      label,
      x: clamp(CX + Math.cos(angle) * radius, 170, 910),
      y: clamp(CY + Math.sin(angle) * radius * 1.2, 470, 1620),
      bow: (rand() - 0.5) * 340, // perpendicular control offset — edges never read as straight lines
      drift: 3 + rand() * 4,
      phase: rand() * Math.PI * 2,
    };
  });

  const hubPop = spring({ frame, fps, config: { damping: 13, stiffness: 130 } });
  const outro = total > 30 ? interpolate(frame, [total - 10, total], [1, 0], { extrapolateLeft: "clamp", extrapolateRight: "clamp" }) : 1;
  const eyebrowIn = interpolate(frame, [0, 14], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });

  const laid = pts.map((pt, i) => {
    const start = 12 + i * 7;
    const edge = interpolate(frame, [start, start + 24], [0, 1], {
      extrapolateLeft: "clamp",
      extrapolateRight: "clamp",
      easing: Easing.inOut(Easing.cubic),
    });
    const pop = spring({ frame: frame - (start + 16), fps, config: { damping: 14, stiffness: 120 } });
    const float = Math.sin(frame / (fps * 0.9) + pt.phase) * pt.drift;
    const x = pt.x;
    const y = pt.y + float;
    const mx = (CX + x) / 2;
    const my = (CY + y) / 2;
    const dx = x - CX;
    const dy = y - CY;
    const len = Math.max(1, Math.sqrt(dx * dx + dy * dy));
    const cx = mx + (-dy / len) * pt.bow;
    const cy = my + (dx / len) * pt.bow;
    return { ...pt, x, y, cx, cy, edge, pop };
  });

  return (
    <AbsoluteFill style={{ opacity: outro }}>
      <svg
        width={1080}
        height={1920}
        viewBox="0 0 1080 1920"
        style={{ position: "absolute", left: 0, top: 0 }}
      >
        {laid.map((n, i) => (
          <g key={`edge-${i}`}>
            <path
              d={`M ${CX} ${CY} Q ${n.cx} ${n.cy} ${n.x} ${n.y}`}
              fill="none"
              stroke={COLORS.teal}
              strokeWidth={3}
              strokeLinecap="round"
              strokeOpacity={0.75}
              pathLength={1}
              strokeDasharray={1}
              strokeDashoffset={1 - n.edge}
            />
            <circle
              cx={n.x}
              cy={n.y}
              r={7 * Math.min(1, n.pop)}
              fill={COLORS.tealBright}
            />
          </g>
        ))}
        <circle cx={CX} cy={CY} r={18 * Math.min(1, hubPop)} fill={COLORS.teal} fillOpacity={0.35} />
      </svg>

      {eyebrow.length > 0 ? (
        <div
          style={{
            position: "absolute",
            top: 220,
            left: 0,
            width: 1080,
            textAlign: "center",
            fontFamily: FONTS.body,
            fontWeight: 600,
            fontSize: 26,
            letterSpacing: "0.22em",
            textTransform: "uppercase",
            color: mutedColor,
            opacity: eyebrowIn,
            transform: `translateY(${(1 - eyebrowIn) * 18}px)`,
          }}
        >
          {eyebrow}
        </div>
      ) : null}

      <div
        style={{
          position: "absolute",
          left: CX,
          top: CY,
          transform: `translate(-50%, -50%) scale(${0.8 + Math.min(1, hubPop) * 0.2})`,
          opacity: Math.min(1, hubPop),
          padding: "22px 46px",
          borderRadius: 999,
          background: COLORS.ink,
          border: `2px solid ${COLORS.teal}`,
          boxShadow: ENGRAVE_SHADOW,
          whiteSpace: "nowrap",
        }}
      >
        <span
          style={{
            fontFamily: FONTS.display,
            fontSize: 58,
            lineHeight: 0.95,
            textTransform: "uppercase",
            backgroundImage: headlineGradient(),
            WebkitBackgroundClip: "text",
            backgroundClip: "text",
            color: "transparent",
          }}
        >
          {hub}
        </span>
      </div>

      {laid.map((n, i) => (
        <div
          key={`node-${i}`}
          style={{
            position: "absolute",
            left: n.x,
            top: n.y - 46,
            transform: `translate(-50%, -50%) scale(${0.75 + Math.min(1, n.pop) * 0.25})`,
            opacity: Math.min(1, n.pop),
            padding: "13px 26px",
            borderRadius: 999,
            background: COLORS.vellum,
            border: `1px solid ${COLORS.leather}`,
            boxShadow: ENGRAVE_SHADOW,
            fontFamily: FONTS.body,
            fontWeight: 600,
            fontSize: 29,
            color: textColor,
            whiteSpace: "nowrap",
          }}
        >
          {n.label}
        </div>
      ))}
    </AbsoluteFill>
  );
};

export default NonLinearDiagram;