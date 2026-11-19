import React from "react";
import { Composition } from "remotion";
import { BlogShort, BlogShortProps, duracaoBlog } from "./BlogShort";

// Imocena's blog shorts for Reels / TikTok / Shorts, rendered apart from the product's clips
// (npx remotion render src/blog/index.ts BlogShort out.mp4 --props=…).
const exemplo: BlogShortProps = {
  cenas: [
    { tipo: "pergunta", segundos: 3, eyebrow: "fotografia", titulo: "A mesma sala, *duas fotos.*", legenda: "A mesma sala, duas fotos." },
    { tipo: "cta", segundos: 3 },
  ],
};

export const BlogRoot: React.FC = () => (
  <Composition
    id="BlogShort"
    component={BlogShort}
    width={1080}
    height={1920}
    fps={30}
    durationInFrames={duracaoBlog(exemplo)}
    defaultProps={exemplo}
    calculateMetadata={({ props }) => ({ durationInFrames: duracaoBlog(props) })}
  />
);

