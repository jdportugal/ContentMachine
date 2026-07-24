import React from "react";
import { AbsoluteFill, useCurrentFrame, interpolate, spring } from "remotion";
import { ENGRAVE_SHADOW } from "../style-tokens";
import type { PrimitiveProps } from "../primitives";

const num = (v: unknown, fallback: number): number =>
  typeof v === "number" && Number.isFinite(v) ? v : fallback;
const str = (v: unknown): string => (typeof v === "string" ? v : "");

const ImageDropBounce: React.FC<PrimitiveProps> = ({ anim, fps }) => {
  const frame = useCurrentFrame();
  const p = anim.params ?? {};

  const src = str(p.src) || str((p as Record<string, unknown>).image) || str((p as Record<string, unknown>).url);
  const size = num(p.size, 720);
  const radius = num(p.radius, 0);
  const bounceAmp = num(p.bounce, 26);

  // Entrance: decelerating drop from above the frame that overshoots and settles.
  const entrance = spring({
    frame,
    fps,
    config: { damping: 12, mass: 1.1, stiffness: 90 },
  });
  const dropY = interpolate(entrance, [0, 1], [-(1920 / 2 + size / 2 + 200), 0]);

  // Persistent gentle bounce once it has arrived (amplitude ramps in with the entrance).
  const idleY = Math.sin((frame / fps) * Math.PI * 1.6) * bounceAmp * entrance;

  const translateY = dropY + idleY;

  return (
    <AbsoluteFill style={{ alignItems: "center", justifyContent: "center" }}>
      {src ? (
        <img
          src={src}
          alt=""
          style={{
            width: size,
            height: size,
            objectFit: "cover",
            borderRadius: radius,
            transform: `translateY(${translateY}px)`,
            boxShadow: ENGRAVE_SHADOW,
            display: "block",
          }}
        />
      ) : null}
    </AbsoluteFill>
  );
};

export default ImageDropBounce;