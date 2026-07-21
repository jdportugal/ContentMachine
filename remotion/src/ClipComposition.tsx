import React from "react";
import { AbsoluteFill, Audio, Sequence, useVideoConfig } from "remotion";
import { COLORS } from "./style-tokens";
import { renderPrimitive } from "./primitives";
import { loadFonts } from "./fonts";
import type { ClipProps } from "./types";

loadFonts();

// Subtle "foxing" texture for the papyrus background (opaque clips only).
const FoxingBackground: React.FC = () => (
  <AbsoluteFill
    style={{
      backgroundColor: COLORS.papyrus,
      backgroundImage: `radial-gradient(circle at 18% 22%, rgba(139,58,42,0.06) 0 2px, transparent 3px),
                         radial-gradient(circle at 64% 38%, rgba(139,58,42,0.05) 0 3px, transparent 4px),
                         radial-gradient(circle at 42% 72%, rgba(200,155,60,0.06) 0 2px, transparent 3px),
                         radial-gradient(circle at 82% 84%, rgba(139,58,42,0.05) 0 2px, transparent 3px),
                         radial-gradient(circle at 30% 90%, rgba(200,155,60,0.05) 0 3px, transparent 4px),
                         linear-gradient(180deg, ${COLORS.vellum} 0%, ${COLORS.papyrus} 100%)`,
      backgroundSize: "540px 540px, 620px 620px, 480px 480px, 700px 700px, 560px 560px, 100% 100%",
    }}
  />
);

export const ClipComposition: React.FC<ClipProps> = ({
  fps,
  transparent,
  audioSrc,
  animations,
}) => {
  const { fps: configFps } = useVideoConfig();
  const effectiveFps = fps ?? configFps;

  return (
    <AbsoluteFill>
      {/* Opaque clips get papyrus + foxing; transparent overlays get nothing. */}
      {!transparent && <FoxingBackground />}

      {audioSrc ? <Audio src={audioSrc} /> : null}

      {animations.map((anim, i) => {
        const from = Math.round(anim.start * effectiveFps);
        const durationInFrames = Math.max(
          1,
          Math.round((anim.end - anim.start) * effectiveFps)
        );
        return (
          <Sequence key={i} from={from} durationInFrames={durationInFrames} name={anim.primitive}>
            {renderPrimitive(anim, effectiveFps)}
          </Sequence>
        );
      })}
    </AbsoluteFill>
  );
};
