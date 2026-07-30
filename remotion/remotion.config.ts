import { Config } from "@remotion/cli/config";

// Rendering configuration for Brand Machine clips.
//
// PNG frames preserve per-pixel alpha, which is required so transparent
// overlays keep their alpha channel through to the encoder.
Config.setVideoImageFormat("png");
Config.setConcurrency(1);

// Alpha auto-enable for transparent overlays.
//
// Remotion 4.x does NOT automatically pick an alpha-capable pixel format for
// ProRes 4444 — without this it falls back to `yuv422p12le` and the alpha
// channel is silently dropped. The Laravel backend invokes transparent renders
// with `--codec=prores --prores-profile=4444` (no explicit --pixel-format), so
// we detect that here and force the alpha pixel format. Opaque H264 renders are
// left untouched.
const cliArgs = process.argv.join(" ");
const isProRes4444 =
  /--codec[=\s]+prores/.test(cliArgs) && /4444/.test(cliArgs);

if (isProRes4444) {
  Config.setPixelFormat("yuva444p10le");
}
