// Loads the IATECA display/body faces from Google Fonts when the optional
// @remotion/google-fonts package is present. Falls back silently to the serif
// stacks in style-tokens.ts if loading fails, so renders never break.

let loaded = false;

export function loadFonts(): void {
  if (loaded) return;
  loaded = true;
  try {
    // Lazy require so a missing/broken package can never crash a render.
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    const cormorant = require("@remotion/google-fonts/CormorantGaramond");
    cormorant.loadFont();
  } catch {
    // Fallback serif stack is used automatically.
  }
  try {
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    const eb = require("@remotion/google-fonts/EBGaramond");
    eb.loadFont();
  } catch {
    // Fallback serif stack is used automatically.
  }
}
