// Reads an idealista.pt listing page in a real Chrome and prints its data as JSON.
// idealista sits behind Cloudflare/DataDome: a plain HTTP request and Remotion's
// headless shell both get the challenge page; a full Chrome (headless) passes it.
//
//   node scripts/fetch-idealista.mjs --url <listing url> [--chrome <path>]
//
// Chrome: --chrome, else $CHROME_PATH, else the macOS install.
import puppeteer from 'puppeteer-core';

const arg = (name) => {
  const i = process.argv.indexOf(`--${name}`);
  return i > -1 ? process.argv[i + 1] : undefined;
};
const fail = (msg) => {
  process.stderr.write(msg + '\n');
  process.exit(1);
};

const url = arg('url');
if (!url) fail('missing --url');
const chrome = arg('chrome') || process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

let browser;
try {
  browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--lang=pt-PT'] });
  const page = await browser.newPage();
  // The headless UA says "HeadlessChrome", which the challenge refuses.
  await page.setUserAgent((await browser.userAgent()).replace('HeadlessChrome', 'Chrome'));
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
  // The challenge page reloads into the listing once it has checked the browser.
  await page.waitForFunction(() => typeof window.adMultimediasInfo !== 'undefined', { timeout: 30000 }).catch(() => {});

  const data = await page.evaluate(() => {
    const text = (sel) => document.querySelector(sel)?.innerText.trim() ?? '';
    const texts = (sel) => [...document.querySelectorAll(sel)].map((e) => e.innerText.trim()).filter(Boolean);
    const pics = window.adMultimediasInfo?.fullScreenGalleryPics ?? [];
    return {
      blocked: typeof window.adMultimediasInfo === 'undefined',
      title: window.adDetail?.headerTitle ?? document.title,
      price: window.mortgagesConfig?.initialPrice ?? null,
      description: text('.comment .adCommentsLanguage'),
      location: texts('#headerMap li'),
      features: texts('#details li').filter((t) => !/^Classe energética/i.test(t)),
      // The class is an icon: <span title="d" class="icon-energy-…">.
      energy: document.querySelector('#details [class*="icon-energy"]')?.getAttribute('title') ?? '',
      reference: text('.txt-ref'),
      advertiser: text('.about-advertiser-name'),
      advertiserInfo: texts('.about-advertiser-name ~ span'),
      agentName: document.querySelector('input[name="user-name"]')?.value?.trim() ?? '',
      photos: pics.filter((p) => !p.isPlan).map((p) => p.imageDataService),
    };
  });
  if (data.blocked) fail('idealista showed its bot check instead of the listing — try again in a minute');
  process.stdout.write(JSON.stringify(data));
} catch (e) {
  fail(String(e?.message ?? e).split('\n')[0]);
} finally {
  await browser?.close();
}
