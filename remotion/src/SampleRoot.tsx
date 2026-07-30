import React from "react";
import { AbsoluteFill, Composition, useVideoConfig } from "remotion";
import Candidate from "./effects/_candidate";
import { BackgroundTexture } from "./ClipComposition";
import { applyTheme, COLORS, isDark, ThemeInput } from "./style-tokens";
import { loadDefaultFonts, loadThemeFonts } from "./fonts";
import type { Animation, AnimationParams } from "./types";

loadDefaultFonts();

// Isolated preview of the SFX under generation. Renders the candidate primitive
// (remotion/src/effects/_candidate.tsx) full-screen over the themed background,
// exactly how it would look as a scene layer. This entry (src/sample.ts) is the
// ONLY place _candidate is imported, so a broken candidate cannot break the
// production ClipComposition bundle.

interface SampleProps {
  text?: string;
  params?: AnimationParams;
  theme?: ThemeInput;
  duration: number;
  width: number;
  height: number;
  fps: number;
  [key: string]: unknown;
}

const SampleEffect: React.FC<SampleProps> = ({ text, params, theme }) => {
  applyTheme(theme);
  loadThemeFonts(theme?.fonts?.display, theme?.fonts?.body);

  const { fps, durationInFrames } = useVideoConfig();
  const dark = isDark(COLORS.papyrus);
  const anim: Animation = {
    start: 0,
    end: durationInFrames / fps,
    primitive: "_candidate" as Animation["primitive"],
    text: text ?? "",
    params: params ?? {},
  };

  return (
    <AbsoluteFill>
      <BackgroundTexture />
      <Candidate anim={anim} fps={fps} dark={dark} />
    </AbsoluteFill>
  );
};

const defaultProps: SampleProps = {
  text: "SAMPLE",
  params: {},
  duration: 2.5,
  width: 1080,
  height: 1920,
  fps: 30,
};

export const SampleRoot: React.FC = () => {
  return (
    <Composition
      id="SampleEffect"
      component={SampleEffect}
      defaultProps={defaultProps}
      durationInFrames={75}
      fps={30}
      width={1080}
      height={1920}
      calculateMetadata={({ props }) => {
        const fps = props.fps ?? 30;
        const duration = props.duration ?? 2.5;
        return {
          durationInFrames: Math.max(1, Math.ceil(duration * fps)),
          fps,
          width: props.width ?? 1080,
          height: props.height ?? 1920,
        };
      }}
    />
  );
};
