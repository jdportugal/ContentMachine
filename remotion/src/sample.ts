import { registerRoot } from "remotion";
import { SampleRoot } from "./SampleRoot";

// Isolated entry used only to test-render a candidate SFX (SampleEffect).
// Kept separate from src/index.ts so an unproven/broken candidate never enters
// the production ClipComposition bundle.
registerRoot(SampleRoot);
