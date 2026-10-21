import React from "react";
import { Img, OffthreadVideo } from "remotion";

// Effects are written for photos (<Img>); EffectLibrary swaps their Img import for
// this, so a provided VIDEO (a listing's room clip) plays muted in the same slot.
export const MediaImg: React.FC<React.ComponentProps<typeof Img>> = (props) => {
  const src = String(props.src ?? "");
  return /\.(mp4|mov|webm|m4v)(\?|#|$)/i.test(src) ? (
    <OffthreadVideo src={src} muted style={props.style} className={props.className} />
  ) : (
    <Img {...props} />
  );
};
