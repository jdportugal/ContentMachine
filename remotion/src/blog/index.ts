import { registerRoot } from "remotion";
import { BlogRoot } from "./BlogRoot";

// Imocena's blog shorts for Reels / TikTok / Shorts, rendered apart from the product's clips
// (npx remotion render src/blog/index.ts BlogShort out.mp4 --props=…).
registerRoot(BlogRoot);
