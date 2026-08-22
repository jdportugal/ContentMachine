import React from "react";
import {
  AbsoluteFill,
  Audio,
  Easing,
  interpolate,
  OffthreadVideo,
  Sequence,
  spring,
  staticFile,
  useCurrentFrame,
  useVideoConfig,
} from "remotion";
import { COLORS, ENGRAVE_SHADOW, FONTS, PANEL_SHADOW, panelBorder, STYLE, textForBg } from "./style-tokens";
import { frameProps, renderPrimitive } from "./primitives";
import type { KaraokeWord, Present, Scene } from "./types";

const EASE = Easing.inOut(Easing.cubic);

const resolveSrc = (src: string): string => (/^https?:\/\//.test(src) ? src : staticFile(src));

// Resolved lazily (at render time) so it reflects the active theme — COLORS is
// mutated by applyTheme() AFTER this module loads, so a captured object would be stale.
const bgColorFor = (bg?: string): string | null => {
  switch (bg) {
    case "vellum":
      return COLORS.vellum;
    case "ink":
      return COLORS.ink;
    case "video":
      return null; // transparent — the source video (overlay) shows through
    default:
      return COLORS.papyrus;
  }
};

// ── punch word (big serif-italic-caps emphasis) ──────────────────────────────
const PunchWord: React.FC<{ text: string; fps: number; dark: boolean; top?: string }> = ({ text, fps, dark, top = "0%" }) => {
  const frame = useCurrentFrame();
  const s = spring({ frame, fps, config: { damping: 14, stiffness: 160 } });
  const opacity = interpolate(frame, [0, 8], [0, 1], { extrapolateRight: "clamp" });
  // Pop in, then keep a very gentle breathing pulse so it stays alive.
  const scale = interpolate(s, [0, 1], [0.9, 1]) * (1 + Math.sin(frame / 60) * 0.006);
  // Shrink longer phrases so they stay in the top band and don't sprawl into the centre.
  const len = text.length;
  const fontSize = len <= 8 ? 118 : len <= 16 ? 84 : 62;
  return (
    <AbsoluteFill style={{ top, justifyContent: "flex-start", alignItems: "center", paddingTop: "12%" }}>
      <div
        style={{
          fontFamily: FONTS.display,
          fontWeight: FONTS.displayWeight,
          textTransform: "uppercase",
          letterSpacing: "0.01em",
          fontSize,
          lineHeight: 0.98,
          color: dark ? COLORS.textOnDark : COLORS.textOnLight,
          textShadow: STYLE.shadow === "hard" ? ENGRAVE_SHADOW : dark ? `0 0 28px ${COLORS.tealBright}66` : `0 0 26px ${COLORS.teal}44`,
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
// scene colour, so there is always subtle motion on screen. When `solid` is false
// the base colour is NOT painted, so the composition's themed BackgroundTexture
// (the same starfield the effect previews show) shows through — only the drifting
// blobs are layered on top for motion.
const LiveBackground: React.FC<{ color: string; solid: boolean }> = ({ color, solid }) => {
  const frame = useCurrentFrame();
  const dx = Math.sin(frame / 70) * 8;
  const dy = Math.cos(frame / 90) * 9;
  const dx2 = Math.cos(frame / 58) * 10;
  // Print/flat designs keep a truly flat field — no drifting colour blobs.
  const flat = STYLE.headline === "flat";
  return (
    <AbsoluteFill style={{ backgroundColor: solid ? color : undefined, overflow: "hidden" }}>
      {flat ? null : (
        <AbsoluteFill
          style={{
            backgroundImage:
              `radial-gradient(circle at ${28 + dx}% ${24 + dy}%, ${COLORS.gold}12, transparent 46%),` +
              `radial-gradient(circle at ${72 + dx2}% ${74 - dy}%, ${COLORS.leather}10, transparent 48%),` +
              `radial-gradient(circle at ${50 - dx}% ${90 + dy}%, ${COLORS.teal}0d, transparent 52%)`,
          }}
        />
      )}
    </AbsoluteFill>
  );
};

const SceneBody: React.FC<{ scene: Scene; fps: number; durSec: number; videoSrc: string | null; transparent: boolean }> = ({ scene, fps, durSec, videoSrc, transparent }) => {
  const frame = useCurrentFrame();
  const { width: compWidth, height: compHeight } = useVideoConfig();
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
  const bgColor = bgColorFor(scene.background ?? "papyrus") ?? COLORS.papyrus;
  const dark = present === "animation" ? scene.background === "ink" : true; // over video → light text
  const layers = Array.isArray(scene.layers) ? scene.layers : [];

  // The composition renders its themed BackgroundTexture (starfield) behind this
  // scene only for an opaque (non-transparent) animation clip with no source video.
  // When it does, let it show through for EVERY animation background so real clips
  // match the effect demos — a flat papyrus/vellum/ink panel would hide the
  // starfield. Paint an opaque base only when there's no backdrop to reveal:
  // over-video (split) scenes and transparent (externally-composited) renders.
  const hasBackdrop = present === "animation" && !transparent;
  const solidBg = !hasBackdrop;

  const video = videoSrc ? (
    <OffthreadVideo
      src={videoSrc}
      startFrom={Math.round((scene.start ?? 0) * fps)}
      muted
      style={{ position: "absolute", left: 0, bottom: 0, width: "100%", height: present === "split" ? "50%" : "100%", objectFit: "cover" }}
    />
  ) : null;

  // The box the layers actually get. A split scene is the TOP HALF of the frame —
  // effects have to lay out for 1080×960, not for the composition's 1080×1920,
  // or they overflow and the bottom is clipped away.
  const box = present === "split"
    ? frameProps(compWidth, Math.round(compHeight / 2), "half")
    : frameProps(compWidth, compHeight);

  const content = (
    <AbsoluteFill
      style={{
        opacity,
        filter: blur ? `blur(${blur}px)` : undefined,
        transform: `translateY(${tY + idleY}px) scale(${entryScale * idleScale})`,
      }}
    >
      {present === "animation" || present === "split" ? <LiveBackground color={bgColor} solid={solidBg} /> : null}
      {present !== "video"
        ? layers.map((layer, i) => {
            const pseudo = { start: 0, end: durSec, primitive: layer.type, text: layer.text, params: layer.params };
            return (
              <React.Fragment key={i}>
                {renderPrimitive(pseudo, fps, dark, box)}
                {layer.audioSrc ? <Audio src={resolveSrc(layer.audioSrc)} volume={0.8} /> : null}
              </React.Fragment>
            );
          })
        : null}
      {scene.punchWord && present !== "split" ? <PunchWord text={String(scene.punchWord)} fps={fps} dark={dark} /> : null}
    </AbsoluteFill>
  );

  if (present === "split") {
    return (
      <AbsoluteFill>
        {video}
        <div style={{ position: "absolute", top: 0, left: 0, width: "100%", height: "50%", overflow: "hidden" }}>
          {content}
        </div>
        {scene.punchWord ? <PunchWord text={String(scene.punchWord)} fps={fps} dark={dark} top="50%" /> : null}
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
// Captions lead the audio by a hair. Frame quantization always rounds a word's
// highlight to appear at/after its onset (never before), and viewers read a
// little ahead — both make an exactly-synced caption feel late. A small lead
// cancels that. ponytail: perceptual tuning knob — nudge up if still late, down
// if it now reads early.
const CAPTION_LEAD = 0.1; // seconds

const KaraokeTrack: React.FC<{ words: KaraokeWord[]; scenes: Scene[]; fps: number }> = ({ words, scenes, fps }) => {
  const frame = useCurrentFrame();
  const t = frame / fps + CAPTION_LEAD;

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
      <div style={{ display: "flex", flexWrap: "wrap", justifyContent: "center", gap: "0 22px", maxWidth: "86%", background: COLORS.vellum, border: panelBorder(), borderRadius: STYLE.sharp ? 0 : 14, padding: "20px 34px", boxShadow: PANEL_SHADOW }}>
        {group.map((w, i) => {
          const globalIdx = line * LINE + i;
          const isActive = globalIdx === idx && t >= w.start && t < w.end;
          const pop = isActive ? interpolate(t, [w.start, w.start + 0.12], [1, 1.12], { extrapolateLeft: "clamp", extrapolateRight: "clamp" }) : 1;
          return (
            <span
              key={i}
              style={{
                fontFamily: FONTS.body,
                fontWeight: 600,
                fontSize: 58,
                lineHeight: 1.2,
                color: isActive ? COLORS.teal : textForBg(COLORS.vellum),
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
export const SceneTrack: React.FC<{ scenes: Scene[]; words: KaraokeWord[]; fps: number; videoSrc: string | null; transparent: boolean }> = ({ scenes, words, fps, videoSrc, transparent }) => {
  return (
    <>
      {scenes.map((scene, i) => {
        const from = Math.round((scene.start ?? 0) * fps);
        const durSec = Math.max(0.1, (scene.end ?? 0) - (scene.start ?? 0));
        const durFrames = Math.max(1, Math.round(durSec * fps));
        return (
          <Sequence key={i} from={from} durationInFrames={durFrames} name={`scene-${i}`}>
            <SceneBody scene={scene} fps={fps} durSec={durSec} videoSrc={videoSrc} transparent={transparent} />
          </Sequence>
        );
      })}
      <KaraokeTrack words={words} scenes={scenes} fps={fps} />
    </>
  );
};
