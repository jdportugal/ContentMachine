import React from "react";
import { AbsoluteFill, Sequence, staticFile, useVideoConfig, Video } from "remotion";
import { applyTheme, COLORS, FONTS, isDark, ThemeInput } from "./style-tokens";
import { CUSTOM_BACKGROUNDS } from "./backgrounds";
import { frameProps } from "./primitives";
import { loadDefaultFonts, loadThemeFonts } from "./fonts";
import type { Animation } from "./types";

loadDefaultFonts();

// One video that plays every custom background in turn, each full-screen for a
// beat with its name centered — the backgrounds' answer to the SFX showreel. This
// is a preview-only composition; real clips carry a single `background` instead.

interface ReelEntry {
  slug: string;
  label: string;
  kind: "code" | "video";
  src?: string; // video kind — staged filename (staticFile) or an http url
}

export interface ReelProps {
  entries: ReelEntry[];
  perSeconds: number; // seconds each background is shown
  fps: number;
  width: number;
  height: number;
  theme?: ThemeInput;
  [key: string]: unknown;
}

// The full-screen backdrop for one entry: a generated component or a looping video.
const ReelBackdrop: React.FC<{ entry: ReelEntry; fps: number; durationInFrames: number }> = ({ entry, fps, durationInFrames }) => {
  const { width, height } = useVideoConfig();
  if (entry.kind === "video" && entry.src) {
    const src = /^https?:\/\//.test(entry.src) ? entry.src : staticFile(entry.src);
    return (
      <AbsoluteFill>
        <Video src={src} muted loop style={{ width: "100%", height: "100%", objectFit: "cover" }} />
      </AbsoluteFill>
    );
  }
  const Comp = CUSTOM_BACKGROUNDS[entry.slug];
  if (!Comp) return null;
  const anim: Animation = { start: 0, end: durationInFrames / fps, primitive: entry.slug as Animation["primitive"], text: "", params: {} };
  return (
    <AbsoluteFill>
      <Comp anim={anim} fps={fps} dark={isDark(COLORS.papyrus)} {...frameProps(width, height)} />
    </AbsoluteFill>
  );
};

// A centered name pill, readable over any backdrop (dark translucent + light text).
const NamePill: React.FC<{ label: string }> = ({ label }) => (
  <AbsoluteFill style={{ alignItems: "center", justifyContent: "flex-end", paddingBottom: "12%" }}>
    <div
      style={{
        fontFamily: FONTS.display,
        fontSize: 58,
        letterSpacing: 1,
        color: "rgba(250,243,224,0.96)",
        background: "rgba(20,16,12,0.55)",
        border: "1px solid rgba(250,243,224,0.18)",
        borderRadius: 999,
        padding: "16px 42px",
        backdropFilter: "blur(8px)",
        boxShadow: "0 8px 40px rgba(0,0,0,0.35)",
        maxWidth: "86%",
        textAlign: "center",
      }}
    >
      {label}
    </div>
  </AbsoluteFill>
);

export const BackgroundReel: React.FC<ReelProps> = ({ entries, perSeconds, fps, theme }) => {
  applyTheme(theme);
  loadThemeFonts(theme?.fonts?.display, theme?.fonts?.body, theme?.fonts?.mono);
  const { fps: configFps } = useVideoConfig();
  const per = Math.max(1, Math.round(perSeconds * (fps ?? configFps)));

  return (
    <AbsoluteFill style={{ backgroundColor: COLORS.papyrus }}>
      {entries.map((entry, i) => (
        <Sequence key={i} from={i * per} durationInFrames={per}>
          <ReelBackdrop entry={entry} fps={fps ?? configFps} durationInFrames={per} />
          <NamePill label={entry.label} />
        </Sequence>
      ))}
    </AbsoluteFill>
  );
};
