import React from "react";
import {
  AbsoluteFill,
  Easing,
  interpolate,
  OffthreadVideo,
  Sequence,
  spring,
  useCurrentFrame,
} from "remotion";
import { COLORS, ENGRAVE_SHADOW, FONTS } from "./style-tokens";
import { renderPrimitive } from "./primitives";
import type { KaraokeWord, Present, Scene } from "./types";

const EASE = Easing.inOut(Easing.cubic);

const BG_COLOR: Record<string, string | null> = {
  papyrus: COLORS.papyrus,
  vellum: COLORS.vellum,
  ink: COLORS.ink,
  video: null, // transparent — the source video (overlay) shows through
};

// ── punch word (big serif-italic-caps emphasis) ──────────────────────────────
const PunchWord: React.FC<{ text: string; fps: number; dark: boolean }> = ({ text, fps, dark }) => {
  const frame = useCurrentFrame();
  const s = spring({ frame, fps, config: { damping: 14, stiffness: 160 } });
  const opacity = interpolate(frame, [0, 8], [0, 1], { extrapolateRight: "clamp" });
  // Pop in, then keep a very gentle breathing pulse so it stays alive.
  const scale = interpolate(s, [0, 1], [0.9, 1]) * (1 + Math.sin(frame / 60) * 0.006);
  // Shrink longer phrases so they stay in the top band and don't sprawl into the centre.
  const len = text.length;
  const fontSize = len <= 8 ? 118 : len <= 16 ? 84 : 62;
  return (
    <AbsoluteFill style={{ justifyContent: "flex-start", alignItems: "center", paddingTop: "12%" }}>
      <div
        style={{
          fontFamily: FONTS.display,
          fontStyle: "italic",
          fontWeight: 600,
          textTransform: "uppercase",
          letterSpacing: "0.03em",
          fontSize,
          lineHeight: 1.02,
          color: dark ? COLORS.papyrus : COLORS.ink,
          textShadow: dark ? "0 0 24px rgba(45,186,180,0.35)" : ENGRAVE_SHADOW,
          transform: `scale(${scale})`,
          opacity,
          textAlign: "center",
          padding: "0 8%",
        }}
      >
        {text}
      </div>
    </AbsoluteFill>
  );
};

// ── one scene ────────────────────────────────────────────────────────────────
// A background that never sits still — slow-drifting ink-blot gradients over the
// scene colour, so there is always subtle motion on screen.
const LiveBackground: React.FC<{ color: string }> = ({ color }) => {
  const frame = useCurrentFrame();
  const dx = Math.sin(frame / 70) * 8;
  const dy = Math.cos(frame / 90) * 9;
  const dx2 = Math.cos(frame / 58) * 10;
  return (
    <AbsoluteFill style={{ backgroundColor: color, overflow: "hidden" }}>
      <AbsoluteFill
        style={{
          backgroundImage:
            `radial-gradient(circle at ${28 + dx}% ${24 + dy}%, rgba(200,155,60,0.07), transparent 46%),` +
            `radial-gradient(circle at ${72 + dx2}% ${74 - dy}%, rgba(139,58,42,0.06), transparent 48%),` +
            `radial-gradient(circle at ${50 - dx}% ${90 + dy}%, rgba(31,122,122,0.05), transparent 52%)`,
        }}
      />
    </AbsoluteFill>
  );
};

const SceneBody: React.FC<{ scene: Scene; fps: number; durSec: number; videoSrc: string | null }> = ({ scene, fps, durSec, videoSrc }) => {
  const frame = useCurrentFrame();
  const tin = scene.transitionIn ?? "cut";
  const inDur = tin === "whip" ? 8 : tin === "cut" ? 0 : 6;

  // Entry: fade + a spring "pop"; each transition adds its own flavour.
  const entry = spring({ frame, fps, config: { damping: 13, stiffness: 120 } });
  const opacity = inDur ? interpolate(frame, [0, inDur], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp", easing: EASE }) : 1;
  let blur = 0;
  let tY = 0;
  let entryScale = interpolate(entry, [0, 1], [0.96, 1]); // gentle pop by default
  if (tin === "whip") {
    blur = interpolate(frame, [0, inDur / 2, inDur], [16, 5, 0], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });
    entryScale = interpolate(frame, [0, inDur], [1.06, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });
  } else if (tin === "zoom") {
    entryScale = interpolate(entry, [0, 1], [1.14, 1]);
  } else if (tin === "slide") {
    tY = interpolate(entry, [0, 1], [90, 0]);
  }

  const idleY = Math.sin(frame / 46) * 3.4;
  const idleScale = 1 + Math.sin(frame / 75) * 0.0025;

  // How the scene sits relative to the source video (overlay clips only).
  const present: Present = videoSrc ? (scene.present ?? "video") : "animation";
  const bgColor = BG_COLOR[scene.background ?? "papyrus"] ?? COLORS.papyrus;
  const dark = present === "animation" ? scene.background === "ink" : true; // over video → light text
  const layers = Array.isArray(scene.layers) ? scene.layers : [];

  const video = videoSrc ? (
    <OffthreadVideo
      src={videoSrc}
      startFrom={Math.round((scene.start ?? 0) * fps)}
      muted
      style={{ position: "absolute", left: 0, bottom: 0, width: "100%", height: present === "split" ? "50%" : "100%", objectFit: "cover" }}
    />
  ) : null;

  const content = (
    <AbsoluteFill
      style={{
        opacity,
        filter: blur ? `blur(${blur}px)` : undefined,
        transform: `translateY(${tY + idleY}px) scale(${entryScale * idleScale})`,
      }}
    >
      {present === "animation" || present === "split" ? <LiveBackground color={bgColor} /> : null}
      {present !== "video"
        ? layers.map((layer, i) => {
            const pseudo = { start: 0, end: durSec, primitive: layer.type, text: layer.text, params: layer.params };
            return <React.Fragment key={i}>{renderPrimitive(pseudo, fps, dark)}</React.Fragment>;
          })
        : null}
      {scene.punchWord ? <PunchWord text={String(scene.punchWord)} fps={fps} dark={dark} /> : null}
    </AbsoluteFill>
  );

  if (present === "split") {
    return (
      <AbsoluteFill>
        {video}
        <div style={{ position: "absolute", top: 0, left: 0, width: "100%", height: "50%", overflow: "hidden" }}>
          {content}
        </div>
      </AbsoluteFill>
    );
  }

  return (
    <AbsoluteFill>
      {present !== "animation" ? video : null}
      {content}
    </AbsoluteFill>
  );
};

// ── karaoke captions (word-synced, global across karaoke scenes) ──────────────
const KaraokeTrack: React.FC<{ words: KaraokeWord[]; scenes: Scene[]; fps: number }> = ({ words, scenes, fps }) => {
  const frame = useCurrentFrame();
  const t = frame / fps;

  const active = scenes.some((s) => s.karaoke && t >= s.start && t < s.end);
  if (!active || words.length === 0) return null;

  // current word: the one whose window contains t, else the most recent passed.
  let idx = words.findIndex((w) => t >= w.start && t < w.end);
  if (idx < 0) {
    for (let i = words.length - 1; i >= 0; i--) {
      if (t >= words[i].start) { idx = i; break; }
    }
  }
  if (idx < 0) idx = 0;

  const LINE = 4;
  const line = Math.floor(idx / LINE);
  const group = words.slice(line * LINE, line * LINE + LINE);

  return (
    <AbsoluteFill style={{ justifyContent: "flex-end", alignItems: "center", paddingBottom: "13%" }}>
      <div style={{ display: "flex", flexWrap: "wrap", justifyContent: "center", gap: "0 22px", maxWidth: "86%", background: "rgba(250,243,224,0.9)", borderRadius: 14, padding: "20px 34px", boxShadow: ENGRAVE_SHADOW }}>
        {group.map((w, i) => {
          const globalIdx = line * LINE + i;
          const isActive = globalIdx === idx && t >= w.start && t < w.end;
          const pop = isActive ? interpolate(t, [w.start, w.start + 0.12], [1, 1.12], { extrapolateLeft: "clamp", extrapolateRight: "clamp" }) : 1;
          return (
            <span
              key={i}
              style={{
                fontFamily: FONTS.display,
                fontWeight: 600,
                fontSize: 58,
                lineHeight: 1.2,
                color: isActive ? COLORS.teal : COLORS.ink,
                transform: `scale(${pop})`,
                display: "inline-block",
              }}
            >
              {w.word}
            </span>
          );
        })}
      </div>
    </AbsoluteFill>
  );
};

// ── the scene track ──────────────────────────────────────────────────────────
export const SceneTrack: React.FC<{ scenes: Scene[]; words: KaraokeWord[]; fps: number; videoSrc: string | null }> = ({ scenes, words, fps, videoSrc }) => {
  return (
    <>
      {scenes.map((scene, i) => {
        const from = Math.round((scene.start ?? 0) * fps);
        const durSec = Math.max(0.1, (scene.end ?? 0) - (scene.start ?? 0));
        const durFrames = Math.max(1, Math.round(durSec * fps));
        return (
          <Sequence key={i} from={from} durationInFrames={durFrames} name={`scene-${i}`}>
            <SceneBody scene={scene} fps={fps} durSec={durSec} videoSrc={videoSrc} />
          </Sequence>
        );
      })}
      <KaraokeTrack words={words} scenes={scenes} fps={fps} />
    </>
  );
};
