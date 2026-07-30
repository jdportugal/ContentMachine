import React from "react";
import { AbsoluteFill, useCurrentFrame, interpolate, Easing } from "remotion";
import { COLORS, FONTS, headlineGradient, ENGRAVE_SHADOW } from "../style-tokens";
import type { PrimitiveProps } from "../primitives";

type Item = { label: string; highlight: boolean };

const readItems = (raw: unknown): Item[] => {
  if (!Array.isArray(raw)) return [];
  return raw
    .map((entry) => {
      const o = (entry ?? {}) as Record<string, unknown>;
      return { label: String(o.label ?? ""), highlight: Boolean(o.highlight) };
    })
    .filter((it) => it.label.length > 0);
};

const SLAB_H = 190;
const SLAB_W = 900;
const COVER_CY = 430;
const LIST_TOP = 790;
const ROW_H = 180;
const SHRUNK = 0.68;
const CLAMP = { extrapolateLeft: "clamp", extrapolateRight: "clamp" } as const;

const TitleCoverTimeline: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const total = Math.max(1, Math.round((anim.end - anim.start) * fps));
  const params = (anim.params ?? {}) as Record<string, unknown>;
  const caption = String(params.caption ?? anim.text ?? "");
  const items = readItems(params.items);

  const textColor = dark ? COLORS.textOnDark : COLORS.textOnLight;
  const mutedColor = dark ? COLORS.mutedOnDark : COLORS.mutedOnLight;

  const n = Math.max(1, items.length);
  const lead = total * 0.06;
  const step = (total * 0.62) / n;
  const dur = Math.min(step * 1.9, total * 0.5);

  const capIn = interpolate(frame, [0, Math.max(6, total * 0.08)], [0, 1], {
    ...CLAMP,
    easing: Easing.out(Easing.cubic),
  });

  return (
    <AbsoluteFill style={{ fontFamily: FONTS.body }}>
      <div
        style={{
          position: "absolute",
          left: 0,
          right: 0,
          top: COVER_CY - SLAB_H / 2,
          height: SLAB_H,
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          opacity: capIn,
          transform: `translateY(${(1 - capIn) * -28}px)`,
        }}
      >
        <span
          style={{
            fontFamily: FONTS.display,
            fontWeight: FONTS.displayWeight,
            fontSize: 96,
            lineHeight: 0.9,
            textTransform: "uppercase",
            letterSpacing: "0.01em",
            textAlign: "center",
            maxWidth: 860,
            backgroundImage: headlineGradient(),
            WebkitBackgroundClip: "text",
            backgroundClip: "text",
            color: "transparent",
          }}
        >
          {caption}
        </span>
      </div>

      {items.map((it, i) => {
        const local = frame - (lead + i * step);
        if (local < 0) return null;

        const slide = interpolate(local, [0, dur * 0.34], [0, 1], {
          ...CLAMP,
          easing: Easing.out(Easing.cubic),
        });
        const drop = interpolate(local, [dur * 0.58, dur], [0, 1], {
          ...CLAMP,
          easing: Easing.inOut(Easing.cubic),
        });

        const slotCY = LIST_TOP + i * ROW_H + ROW_H / 2;
        const cy = COVER_CY + (slotCY - COVER_CY) * drop;
        const scale = 1 - (1 - SHRUNK) * drop;
        const x = (1 - slide) * -1280;
        const tilt = (1 - slide) * -2.5;
        const accent = it.highlight ? COLORS.teal : COLORS.leather;

        return (
          <div
            key={i}
            style={{
              position: "absolute",
              left: 0,
              right: 0,
              top: 0,
              height: SLAB_H,
              transform: `translateY(${cy - SLAB_H / 2}px) translateX(${x}px) rotate(${tilt}deg) scale(${scale})`,
              willChange: "transform",
            }}
          >
            <div
              style={{
                margin: "0 auto",
                width: SLAB_W,
                height: SLAB_H,
                boxSizing: "border-box",
                display: "flex",
                alignItems: "center",
                gap: 34,
                padding: "0 48px",
                background: COLORS.vellum,
                border: `3px solid ${accent}`,
                borderRadius: 22,
                boxShadow: ENGRAVE_SHADOW,
                overflow: "hidden",
              }}
            >
              <div style={{ width: 18, height: 18, borderRadius: 999, background: accent, flexShrink: 0 }} />
              <span
                style={{
                  fontFamily: FONTS.display,
                  fontWeight: FONTS.displayWeight,
                  fontSize: 88,
                  lineHeight: 0.95,
                  textTransform: "uppercase",
                  letterSpacing: "0.01em",
                  whiteSpace: "nowrap",
                  ...(it.highlight
                    ? {
                        backgroundImage: headlineGradient(),
                        WebkitBackgroundClip: "text",
                        backgroundClip: "text",
                        color: "transparent",
                      }
                    : { color: textColor }),
                }}
              >
                {it.label}
              </span>
              <span
                style={{
                  marginLeft: "auto",
                  fontFamily: FONTS.mono,
                  fontSize: 26,
                  letterSpacing: "0.18em",
                  color: mutedColor,
                  flexShrink: 0,
                }}
              >
                {String(i + 1).padStart(2, "0")}
              </span>
            </div>
          </div>
        );
      })}
    </AbsoluteFill>
  );
};

export default TitleCoverTimeline;