// CI only: screenshots of /guias on a local copy of the site.
import { chromium } from 'playwright';
const out = process.env.OUT, base = 'http://127.0.0.1:8000';
const browser = await chromium.launch();
for (const [nome, vp] of [['telemovel', { width: 390, height: 844 }], ['computador', { width: 1280, height: 900 }]]) {
  const ctx = await browser.newContext({ viewport: vp, deviceScaleFactor: 1 });
  const page = await ctx.newPage();
  for (const [f, url] of [['indice', '/guias'], ['idealista', '/guias/video-no-anuncio-idealista'], ['reels', '/guias/ideias-reels-consultor-imobiliario'], ['home', '/']]) {
    await page.goto(base + url); await page.waitForTimeout(800);
    await page.screenshot({ path: `${out}/${nome}-${f}.png`, fullPage: true });
  }
}
await browser.close();
