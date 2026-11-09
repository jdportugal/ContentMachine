import { registerRoot } from "remotion";
import { AdsRoot } from "./AdsRoot";

// Imocena's own Facebook/Instagram ads, rendered apart from the product's clips
// (npx remotion render src/ads/index.ts AdImocena out.mp4 --props=…).
registerRoot(AdsRoot);
