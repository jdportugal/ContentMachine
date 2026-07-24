import React from "react";
import { AbsoluteFill, Img, useCurrentFrame, interpolate, Easing } from "remotion";
import { COLORS, FONTS, headlineGradient, ENGRAVE_SHADOW } from "../style-tokens";
import type { PrimitiveProps } from "../primitives";

const num = (v: unknown, fallback: number): number =>
  typeof v === "number" && isFinite(v) ? v : fallback;

const CLAMP = { extrapolateLeft: "clamp", extrapolateRight: "clamp" } as const;

const ImageZoomIn: React.FC<PrimitiveProps> = ({ anim, fps, dark }) => {
  const frame = useCurrentFrame();
  const total = Math.max(2, Math.round((anim.end - anim.start) * fps));
  const params = (anim.params ?? {}) as Record<string, unknown>;

  const src = typeof params.src === "string" ? params.src : "";
  const from = num(params.from, 1);
  const to = num(params.to, 1.35);
  const panX = num(params.panX, 0);
  const panY = num(params.panY, 0);
  const fit = params.fit === "contain" ? "contain" : "cover";
  const radius = num(params.radius, 18);
  const label =
    typeof params.label === "string" && params.label.length > 0
      ? params.label
      : "IMAGE / PLACEHOLDER";
  const caption = typeof anim.text === "string" ? anim.text : "";

  const text = dark ? COLORS.textOnDark : COLORS.textOnLight;
  const muted = dark ? COLORS.mutedOnDark : COLORS.mutedOnLight;

  // the growth itself — eased so it never reads as a linear slider
  const t = interpolate(frame, [0, total - 1], [0, 1], {
    ...CLAMP,
    easing: Easing.bezier(0.22, 1, 0.36, 1),
  });
  const scale = from + (to - from) * t;

  const fadeIn = interpolate(frame, [0, Math.min(12, total / 3)], [0, 1], CLAMP);
  const fadeOut = interpolate(frame, [total - 8, total - 1], [1, 0], CLAMP);
  const opacity = Math.min(fadeIn, fadeOut);

  const captionY = interpolate(frame, [0, Math.min(20, total / 2)], [26, 0], {
    ...CLAMP,
    easing: Easing.out(Easing.cubic),
  });

  const frameW = 900;
  const frameH = caption ? 1080 : 1240;

  return (
    <AbsoluteFill
      style={{
        alignItems: "center",
        justifyContent: "center",
        flexDirection: "column",
        gap: 44,
        padding: 48,
      }}
    >
      <div
        style={{
          width: frameW,
          height: frameH,
          borderRadius: radius,
          overflow: "hidden",
          position: "relative",
          border: `1px solid ${COLORS.leather}`,
          boxShadow: ENGRAVE_SHADOW,
          backgroundColor: COLORS.vellum,
          opacity,
        }}
      >
        {src ? (
          <Img
            src={src}
            style={{
              width: "100%",
              height: "100%",
              objectFit: fit,
              transform: `scale(${scale}) translate(${panX * t}%, ${panY * t}%)`,
              transformOrigin: "center center",
            }}
          />
        ) : (
          <div
            style={{
              width: "100%",
              height: "100%",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              transform: `scale(${scale}) translate(${panX * t}%, ${panY * t}%)`,
              transformOrigin: "center center",
              background: `repeating-linear-gradient(45deg, ${COLORS.vellum} 0px, ${COLORS.vellum} 22px, ${COLORS.ink} 22px, ${COLORS.ink} 44px)`,
            }}
          >
            <span
              style={{
                fontFamily: FONTS.mono,
                fontSize: 22,
                letterSpacing: "0.18em",
                textTransform: "uppercase",
                color: muted,
                padding: "12px 22px",
                borderRadius: 8,
                border: `1px solid ${COLORS.leather}`,
                backgroundColor: COLORS.papyrus,
              }}
            >
              {label}
            </span>
          </div>
        )}
      </div>

      {caption ? (
        <div
          style={{
            maxWidth: frameW,
            textAlign: "center",
            fontFamily: FONTS.display,
            fontSize: 72,
            lineHeight: 0.92,
            textTransform: "uppercase",
            backgroundImage: headlineGradient(),
            WebkitBackgroundClip: "text",
            backgroundClip: "text",
            color: "transparent",
            opacity,
            transform: `translateY(${captionY}px)`,
          }}
        >
          {caption}
        </div>
      ) : (
        <span style={{ display: "none", color: text }} />
      )}
    </AbsoluteFill>
  );
};

export default ImageZoomIn;