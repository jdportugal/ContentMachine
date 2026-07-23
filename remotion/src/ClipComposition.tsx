import React from "react";
import { AbsoluteFill, Audio, Sequence, staticFile, useVideoConfig } from "remotion";
import { applyTheme, COLORS, TEXTURE, ThemeInput } from "./style-tokens";
import { renderPrimitive } from "./primitives";
import { SceneTrack } from "./scenes";
import { loadDefaultFonts, loadThemeFonts } from "./fonts";
import type { ClipProps } from "./types";

loadDefaultFonts();

// Background behind opaque (non-overlay) scenes. Adapts to the active design
// system's texture: 'paper' = IATECA foxing, 'starfield' = dark + stars,
// 'gradient' = base→contrast wash, 'solid' = flat. An explicit texture.css wins.
const BackgroundTexture: React.FC = () => {
  if (TEXTURE.css) {
    return <AbsoluteFill style={{ background: TEXTURE.css }} />;
  }

  if (TEXTURE.kind === "solid") {
    return <AbsoluteFill style={{ backgroundColor: COLORS.papyrus }} />;
  }

  if (TEXTURE.kind === "gradient") {
    return (
      <AbsoluteFill
        style={{ backgroundImage: `linear-gradient(160deg, ${COLORS.vellum} 0%, ${COLORS.papyrus} 55%, ${COLORS.ink} 140%)` }}
      />
    );
  }

  if (TEXTURE.kind === "starfield") {
    return (
      <AbsoluteFill
        style={{
          backgroundColor: COLORS.papyrus,
          backgroundImage: `radial-gradient(circle at 18% 22%, ${COLORS.textOnLight}cc 0 1.4px, transparent 2px),
                            radial-gradient(circle at 64% 38%, ${COLORS.textOnLight}99 0 1.1px, transparent 2px),
                            radial-gradient(circle at 42% 72%, ${COLORS.textOnLight}aa 0 1.3px, transparent 2px),
                            radial-gradient(circle at 82% 84%, ${COLORS.textOnLight}88 0 1px, transparent 2px),
                            radial-gradient(circle at 30% 90%, ${COLORS.textOnLight}99 0 1.2px, transparent 2px),
                            radial-gradient(circle at 88% 16%, ${COLORS.tealBright}55 0 1.6px, transparent 3px),
                            linear-gradient(180deg, ${COLORS.vellum} 0%, ${COLORS.papyrus} 60%, ${COLORS.ink} 100%)`,
          backgroundSize: "300px 300px, 380px 380px, 260px 260px, 420px 420px, 340px 340px, 500px 500px, 100% 100%",
        }}
      />
    );
  }

  // 'paper' — IATECA foxing (blots tinted from the ornament accents).
  return (
    <AbsoluteFill
      style={{
        backgroundColor: COLORS.papyrus,
        backgroundImage: `radial-gradient(circle at 18% 22%, ${COLORS.leather}10 0 2px, transparent 3px),
                          radial-gradient(circle at 64% 38%, ${COLORS.leather}0d 0 3px, transparent 4px),
                          radial-gradient(circle at 42% 72%, ${COLORS.gold}10 0 2px, transparent 3px),
                          radial-gradient(circle at 82% 84%, ${COLORS.leather}0d 0 2px, transparent 3px),
                          radial-gradient(circle at 30% 90%, ${COLORS.gold}0d 0 3px, transparent 4px),
                          linear-gradient(180deg, ${COLORS.vellum} 0%, ${COLORS.papyrus} 100%)`,
        backgroundSize: "540px 540px, 620px 620px, 480px 480px, 700px 700px, 560px 560px, 100% 100%",
      }}
    />
  );
};

export const ClipComposition: React.FC<ClipProps> = ({
  fps,
  transparent,
  audioSrc,
  animations,
  scenes,
  words,
  videoSrc,
  theme,
}) => {
  // Re-theme the live tokens for this render, then load any theme fonts. Runs
  // before children render, so every primitive picks up the design system.
  applyTheme(theme as ThemeInput | undefined);
  const t = theme as ThemeInput | undefined;
  loadThemeFonts(t?.fonts?.display, t?.fonts?.body);

  const { fps: configFps } = useVideoConfig();
  const effectiveFps = fps ?? configFps;

  const resolve = (src?: string) => (src ? (/^https?:\/\//.test(src) ? src : staticFile(src)) : null);
  const resolvedAudio = resolve(audioSrc);
  const resolvedVideo = resolve(videoSrc);

  const useScenes = Array.isArray(scenes) && scenes.length > 0;

  return (
    <AbsoluteFill>
      {/* Opaque animation clips get the themed background. Overlay clips (with a
          source video) let each scene composite the video per its `present` mode. */}
      {!transparent && !resolvedVideo && <BackgroundTexture />}

      {resolvedAudio ? <Audio src={resolvedAudio} /> : null}

      {useScenes ? (
        <SceneTrack scenes={scenes!} words={Array.isArray(words) ? words : []} fps={effectiveFps} videoSrc={resolvedVideo} />
      ) : (
        (animations ?? []).map((anim, i) => {
          const from = Math.round(anim.start * effectiveFps);
          const durationInFrames = Math.max(1, Math.round((anim.end - anim.start) * effectiveFps));
          return (
            <Sequence key={i} from={from} durationInFrames={durationInFrames} name={anim.primitive}>
              {renderPrimitive(anim, effectiveFps)}
            </Sequence>
          );
        })
      )}
    </AbsoluteFill>
  );
};
