import React from "react";
import { Composition } from "remotion";
import { ClipComposition } from "./ClipComposition";
import type { ClipProps } from "./types";

const defaultProps: ClipProps = {
  duration: 6,
  width: 1080,
  height: 1920,
  fps: 30,
  mode: "dense",
  transparent: false,
  animations: [
    { start: 0, end: 2.5, primitive: "kinetic-text", text: "Content Machine", params: {} },
    { start: 2.5, end: 4, primitive: "fleuron-draw", text: "", params: {} },
    { start: 4, end: 6, primitive: "seal-stamp", text: "IATECA", params: {} },
  ],
};

export const Root: React.FC = () => {
  return (
    <Composition
      id="ClipComposition"
      component={ClipComposition}
      defaultProps={defaultProps}
      // Real dimensions/duration come from calculateMetadata below; these are
      // just placeholders required by the Composition API.
      durationInFrames={180}
      fps={30}
      width={1080}
      height={1920}
      calculateMetadata={({ props }) => {
        const fps = props.fps ?? 30;
        const duration = props.duration ?? 6;
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
