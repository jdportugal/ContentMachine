// Shared prop/plan types for the IATECA clip renderer.

export type PrimitiveName =
  | "fade"
  | "slide"
  | "scale"
  | "kinetic-text"
  | "highlight"
  | "fleuron-draw"
  | "seal-stamp"
  | "underline-sweep"
  | "count-up"
  | "image-reveal"
  | "ambient";

export interface AnimationParams {
  to?: number; // count-up target
  from?: number; // count-up start
  src?: string; // image-reveal source
  direction?: "left" | "right" | "up" | "down"; // slide direction
  suffix?: string; // count-up suffix (e.g. "%")
  prefix?: string; // count-up prefix
  [key: string]: unknown;
}

export interface Animation {
  start: number; // seconds
  end: number; // seconds
  primitive: PrimitiveName;
  text?: string;
  params?: AnimationParams;
}

export interface ClipProps {
  duration: number; // seconds
  width: number;
  height: number;
  fps: number;
  mode: "dense" | "sparse";
  transparent: boolean;
  audioSrc?: string;
  animations: Animation[];
  // Index signature required so ClipProps satisfies Remotion's
  // `Record<string, unknown>` constraint on <Composition> props.
  [key: string]: unknown;
}
