import React from "react";
import { AbsoluteFill, Audio, Sequence, staticFile, useCurrentFrame, useVideoConfig, Video } from "remotion";
import { applyTheme, COLORS, isDark, TEXTURE, ThemeInput } from "./style-tokens";
import { renderPrimitive } from "./primitives";
import { CUSTOM_BACKGROUNDS } from "./backgrounds";
import { SceneTrack } from "./scenes";
import { loadDefaultFonts, loadThemeFonts } from "./fonts";
import type { Animation, ClipProps } from "./types";

loadDefaultFonts();

// Deterministic pseudo-random in [0,1) from a seed — no Math.random (Remotion
// re-renders each frame, so runtime randomness would flicker).
const rand = (n: number): number => {
  const x = Math.sin(n * 12.9898) * 43758.5453;
  return x - Math.floor(x);
};

// A field of drifting, twinkling particles. Positions/sizes are baked once from
// the seed; each frame only moves (gentle oscillation) and fades (twinkle) them.
const STAR_COUNT = 110;
const STARS = Array.from({ length: STAR_COUNT }, (_, i) => ({
  x: rand(i * 3.1 + 0.5) * 100, // base position, %
  y: rand(i * 7.7 + 1.5) * 100,
  size: 1 + rand(i * 2.3 + 2.5) * 2.4, // px
  ax: 2.5 + rand(i * 1.7 + 3.5) * 6, // float amplitude, % — roam more
  ay: 2 + rand(i * 4.3 + 4.5) * 5,
  wf: 0.01 + rand(i * 5.5 + 5.5) * 0.02, // float angular speed
  wt: 0.02 + rand(i * 6.2 + 6.5) * 0.04, // twinkle angular speed
  phase: rand(i * 9.1 + 7.5) * Math.PI * 2,
  bright: rand(i * 8.4 + 8.5), // brightest few get a glow + accent tint
}));

const Starfield: React.FC = () => {
  const frame = useCurrentFrame();
  return (
    <AbsoluteFill style={{ backgroundColor: COLORS.papyrus, overflow: "hidden" }}>
      {/* depth wash behind the particles */}
      <AbsoluteFill
        style={{ backgroundImage: `linear-gradient(180deg, ${COLORS.vellum} 0%, ${COLORS.papyrus} 60%, ${COLORS.ink} 100%)` }}
      />
      {STARS.map((s, i) => {
        const left = s.x + Math.sin(frame * s.wf + s.phase) * s.ax;
        const top = s.y + Math.cos(frame * s.wf * 0.8 + s.phase) * s.ay;
        // Gentle twinkle only — keep brightness fairly constant (was a big fade).
        const twinkle = 0.7 + 0.3 * (0.5 + 0.5 * Math.sin(frame * s.wt + s.phase));
        const glow = s.bright > 0.82;
        const color = glow ? COLORS.tealBright : COLORS.textOnLight;
        return (
          <div
            key={i}
            style={{
              position: "absolute",
              left: `${left}%`,
              top: `${top}%`,
              width: s.size,
              height: s.size,
              borderRadius: "50%",
              background: color,
              opacity: twinkle,
              boxShadow: glow ? `0 0 ${s.size * 3}px ${color}` : undefined,
            }}
          />
        );
      })}
    </AbsoluteFill>
  );
};

// Background behind opaque (non-overlay) scenes. Adapts to the active design
// system's texture: 'paper' = Brand Machine foxing, 'starfield' = dark + stars,
// 'gradient' = base→contrast wash, 'solid' = flat. An explicit texture.css wins.
export const BackgroundTexture: React.FC = () => {
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
    return <Starfield />;
  }

  // 'paper' — Brand Machine foxing (blots tinted from the ornament accents).
  return (
    <AbsoluteFill
      style={{
        // Flat base + a fine halftone dot grid (ink-tinted) — a print texture, no
        // gradient wash. The dot ink adapts to the theme's contrast colour.
        backgroundColor: COLORS.papyrus,
        backgroundImage: `radial-gradient(circle at 2px 2px, ${COLORS.ink}22 0 1.6px, transparent 2px)`,
        backgroundSize: "15px 15px",
      }}
    />
  );
};

// A custom full-clip backdrop chosen for this clip — a generated component (by
// slug) or a looping video — replacing the themed BackgroundTexture. Fills the
// whole frame behind every scene; scenes that show the source video composite
// over it. The video loops so it fills a clip of any length.
const ClipBackground: React.FC<{ background: NonNullable<ClipProps["background"]>; fps: number }> = ({ background, fps }) => {
  const { durationInFrames } = useVideoConfig();
  if (background.kind === "video") {
    const src = /^https?:\/\//.test(background.src) ? background.src : staticFile(background.src);
    return (
      <AbsoluteFill>
        <Video src={src} muted loop style={{ width: "100%", height: "100%", objectFit: "cover" }} />
      </AbsoluteFill>
    );
  }
  const Comp = CUSTOM_BACKGROUNDS[background.slug];
  if (!Comp) return <BackgroundTexture />; // slug vanished (project switched?) → themed fallback
  const anim: Animation = { start: 0, end: durationInFrames / fps, primitive: background.slug as Animation["primitive"], text: "", params: {} };
  return (
    <AbsoluteFill>
      <Comp anim={anim} fps={fps} dark={isDark(COLORS.papyrus)} />
    </AbsoluteFill>
  );
};

export const ClipComposition: React.FC<ClipProps> = ({
  fps,
  transparent,
  audioSrc,
  musicSrc,
  musicVolume,
  animations,
  scenes,
  words,
  background,
  videoSrc,
  theme,
}) => {
  // Re-theme the live tokens for this render, then load any theme fonts. Runs
  // before children render, so every primitive picks up the design system.
  applyTheme(theme as ThemeInput | undefined);
  const t = theme as ThemeInput | undefined;
  loadThemeFonts(t?.fonts?.display, t?.fonts?.body, t?.fonts?.mono);

  const { fps: configFps } = useVideoConfig();
  const effectiveFps = fps ?? configFps;

  const resolve = (src?: string) => (src ? (/^https?:\/\//.test(src) ? src : staticFile(src)) : null);
  const resolvedAudio = resolve(audioSrc);
  const resolvedMusic = resolve(musicSrc);
  const resolvedVideo = resolve(videoSrc);

  const useScenes = Array.isArray(scenes) && scenes.length > 0;

  return (
    <AbsoluteFill>
      {/* Backdrop: a custom background (behind animation AND overlay scenes) wins;
          otherwise opaque animation clips get the themed texture, and overlay clips
          (source video) let each scene composite the video per its `present` mode. */}
      {!transparent && (background ? <ClipBackground background={background} fps={effectiveFps} /> : (!resolvedVideo && <BackgroundTexture />))}

      {resolvedAudio ? <Audio src={resolvedAudio} /> : null}
      {resolvedMusic ? <Audio src={resolvedMusic} volume={musicVolume ?? 0.1} loop /> : null}

      {useScenes ? (
        <SceneTrack scenes={scenes!} words={Array.isArray(words) ? words : []} fps={effectiveFps} videoSrc={resolvedVideo} transparent={!!transparent} />
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
