import React from "react";
import { Composition } from "remotion";
import { AdImocena, AdProps, duracaoEmFrames } from "./AdImocena";

const exemplo: AdProps = {
  variante: "preco",
  secoes: [3, 3, 3.5, 3.5, 3].map((segundos) => ({ segundos, audio: null, legenda: "Texto de exemplo para a legenda." })),
  reel: null,
  fotos: [],
};

export const AdsRoot: React.FC = () => (
  <Composition
    id="AdImocena"
    component={AdImocena}
    width={1080}
    height={1920}
    fps={30}
    durationInFrames={duracaoEmFrames(exemplo)}
    defaultProps={exemplo}
    calculateMetadata={({ props }) => ({ durationInFrames: duracaoEmFrames(props) })}
  />
);
