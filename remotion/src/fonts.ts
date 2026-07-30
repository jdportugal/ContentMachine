// Font loading for the clip renderer.
//
// Default (NEBULA) faces load from the bundled @remotion/google-fonts packages:
// Anton (display) + Space Grotesk (body). A design system may request ANY Google
// Font by family name — those can't be statically required (the bundler can't
// resolve a computed path), so they're loaded by injecting the Google Fonts
// stylesheet and waiting for the face via delayRender/continueRender. Failures
// fall back silently to the sans-serif stacks in style-tokens, so a render never
// hangs or breaks.

import { continueRender, delayRender } from "remotion";

let defaultsLoaded = false;

/** NEBULA defaults — bundled packages, no network. */
export function loadDefaultFonts(): void {
  if (defaultsLoaded) return;
  defaultsLoaded = true;
  try {
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    require("@remotion/google-fonts/Anton").loadFont();
  } catch {
    /* sans-serif fallback */
  }
  try {
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    require("@remotion/google-fonts/SpaceGrotesk").loadFont();
  } catch {
    /* sans-serif fallback */
  }
}

const requested = new Set<string>();

/** Load design-system fonts by Google-Fonts family name. Safe to call every render. */
export function loadThemeFonts(...families: Array<string | undefined>): void {
  for (const family of families) {
    if (!family || requested.has(family)) continue;
    requested.add(family);
    ensureGoogleFont(family);
  }
}

function ensureGoogleFont(family: string): void {
  // Only meaningful in the render browser; guard for other contexts.
  if (typeof document === "undefined") return;

  const handle = delayRender(`font: ${family}`);
  let settled = false;
  const done = () => {
    if (settled) return;
    settled = true;
    continueRender(handle);
  };

  // Safety net: never let a font stall a render.
  const timeout = setTimeout(done, 8000);

  try {
    const fam = family.trim().replace(/\s+/g, "+");
    const href = `https://fonts.googleapis.com/css2?family=${fam}:wght@400;500;600;700;900&display=swap`;
    const link = document.createElement("link");
    link.rel = "stylesheet";
    link.href = href;
    link.onload = () => {
      const fonts = (document as unknown as { fonts?: { ready: Promise<unknown> } }).fonts;
      if (fonts?.ready) {
        fonts.ready.then(() => {
          clearTimeout(timeout);
          done();
        });
      } else {
        clearTimeout(timeout);
        done();
      }
    };
    link.onerror = () => {
      clearTimeout(timeout);
      done();
    };
    document.head.appendChild(link);
  } catch {
    clearTimeout(timeout);
    done();
  }
}
