import React from "react";
import { AbsoluteFill, useCurrentFrame, interpolate, spring, Easing } from "remotion";
import { COLORS, FONTS, headlineGradient, ENGRAVE_SHADOW } from "../style-tokens";
import type { PrimitiveProps } from "../primitives";

const toLabels = (v: unknown): string[] =>
  Array.isArray(v) ? v.map((x) => String(x ?? "").trim()).filter(Boolean) : [];

const num = (v: unknown, fallback: number): number =>
  typeof v === "number" && isFinite(v) ? v : fallback;

const CircularDiagram: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const total = Math.max(1, Math.round((anim.end - anim.start) * fps));
  const params = anim.params ?? {};

  const given = toLabels(params.items);
  const labels = (given.length ? given : ["Coletar", "Analisar", "Automatizar", "Escalar"]).slice(0, 8);
  const n = labels.length;

  const centerText = typeof anim.text === "string" ? anim.text : "";
  const eyebrow = typeof params.eyebrow === "string" ? params.eyebrow : "";
  const radius = Math.max(160, Math.min(390, num(params.radius, 330)));

  const text = dark ? COLORS.textOnDark : COLORS.textOnLight;
  const muted = dark ? COLORS.mutedOnDark : COLORS.mutedOnLight;

  const CX = 540;
  const CY = 960;
  const circumference = 2 * Math.PI * radius;
  const gradId = `cd-ring-${Math.round(anim.start * 1000)}`;

  const draw = interpolate(frame, [0, Math.round(fps * 1.1)], [0, 1], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
    easing: Easing.bezier(0.16, 1, 0.3, 1),
  });

  const fade = interpolate(frame, [total - Math.round(fps * 0.35), total], [1, 0], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
  });

  const orbitAngle = -Math.PI / 2 + ((frame / Math.max(1, fps * 4)) % 1) * Math.PI * 2;

  const nodes = labels.map((label, i) => {
    const a = -Math.PI / 2 + (i * 2 * Math.PI) / n;
    const s = spring({
      frame: frame - Math.round(fps * 0.45) - i * Math.round(fps * 0.12),
      fps,
      config: { damping: 14, mass: 0.7 },
    });
    return {
      label,
      s,
      x: CX + Math.cos(a) * radius,
      y: CY + Math.sin(a) * radius,
    };
  });

  const centerIn = spring({
    frame: frame - Math.round(fps * 0.25),
    fps,
    config: { damping: 16, mass: 0.8 },
  });

  return (
    <AbsoluteFill style={{ opacity: fade }}>
      <svg
        width={1080}
        height={1920}
        viewBox="0 0 1080 1920"
        style={{ position: "absolute", left: 0, top: 0 }}
      >
        <defs>
          <linearGradient id={gradId} x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stopColor={COLORS.tealBright} />
            <stop offset="55%" stopColor={COLORS.teal} />
            <stop offset="100%" stopColor={COLORS.gold} />
          </linearGradient>
        </defs>

        <circle cx={CX} cy={CY} r={radius} fill="none" stroke={muted} strokeOpacity={0.2} strokeWidth={2} />

        <circle
          cx={CX}
          cy={CY}
          r={radius}
          fill="none"
          stroke={`url(#${gradId})`}
          strokeWidth={6}
          strokeLinecap="round"
          strokeDasharray={circumference}
          strokeDashoffset={circumference * (1 - draw)}
          transform={`rotate(-90 ${CX} ${CY})`}
        />

        <circle
          cx={CX + Math.cos(orbitAngle) * radius}
          cy={CY + Math.sin(orbitAngle) * radius}
          r={11}
          fill={COLORS.tealBright}
          opacity={draw}
        />
      </svg>

      <div
        style={{
          position: "absolute",
          left: CX,
          top: CY,
          transform: `translate(-50%, -50%) scale(${0.85 + 0.15 * centerIn})`,
          opacity: centerIn,
          width: radius * 1.3,
          display: "flex",
          flexDirection: "column",
          alignItems: "center",
          gap: 12,
          textAlign: "center",
        }}
      >
        {eyebrow ? (
          <div
            style={{
              fontFamily: FONTS.body,
              fontWeight: 600,
              fontSize: 24,
              letterSpacing: "0.22em",
              textTransform: "uppercase",
              color: muted,
            }}
          >
            {eyebrow}
          </div>
        ) : null}

        {centerText ? (
          <div
            style={{
              fontFamily: FONTS.display,
              fontSize: 84,
              lineHeight: 0.9,
              textTransform: "uppercase",
              backgroundImage: headlineGradient(),
              WebkitBackgroundClip: "text",
              backgroundClip: "text",
              color: "transparent",
            }}
          >
            {centerText}
          </div>
        ) : null}
      </div>

      {nodes.map((nd, i) => (
        <div
          key={i}
          style={{
            position: "absolute",
            left: nd.x,
            top: nd.y,
            transform: `translate(-50%, -50%) scale(${0.6 + 0.4 * nd.s})`,
            opacity: Math.min(1, nd.s),
            display: "flex",
            flexDirection: "column",
            alignItems: "center",
            gap: 14,
          }}
        >
          <div
            style={{
              width: 148,
              height: 148,
              borderRadius: 999,
              background: COLORS.vellum,
              border: `3px solid ${COLORS.teal}`,
              boxShadow: ENGRAVE_SHADOW,
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
            }}
          >
            <div
              style={{
                fontFamily: FONTS.display,
                fontSize: 62,
                lineHeight: 0.9,
                backgroundImage: headlineGradient(),
                WebkitBackgroundClip: "text",
                backgroundClip: "text",
                color: "transparent",
              }}
            >
              {i + 1}
            </div>
          </div>

          <div
            style={{
              fontFamily: FONTS.body,
              fontWeight: 600,
              fontSize: 28,
              letterSpacing: "0.1em",
              textTransform: "uppercase",
              color: text,
              textShadow: ENGRAVE_SHADOW,
              maxWidth: 240,
              textAlign: "center",
            }}
          >
            {nd.label}
          </div>
        </div>
      ))}
    </AbsoluteFill>
  );
};

export default CircularDiagram;