// Scroll-captures a web page as a numbered PNG sequence.
//
// Drives the headless Chrome that `npx remotion browser ensure` already put in
// the image (see Dockerfile) — so this adds no browser download, only
// puppeteer-core to speak CDP to it.
//
// Frames only: encoding is left to PHP, which already owns the ffmpeg path and
// its settings. Prints one JSON line describing what it produced.
//
//   node capture-site.mjs --url https://x.com --out /tmp/frames \
//        --width 1920 --height 1080 --frames 90 --settle 1500

import { mkdir, readdir } from 'node:fs/promises';
import { ensureBrowser } from '@remotion/renderer';
import puppeteer from 'puppeteer-core';

const arg = (name, fallback = null) => {
  const i = process.argv.indexOf(`--${name}`);
  return i !== -1 && process.argv[i + 1] ? process.argv[i + 1] : fallback;
};

const url = arg('url');
const outDir = arg('out');
const width = Number(arg('width', '1920'));
const height = Number(arg('height', '1080'));
const frames = Math.max(2, Number(arg('frames', '90')));
const settle = Number(arg('settle', '1500'));

if (!url || !outDir) {
  console.error('capture-site: --url and --out are required');
  process.exit(2);
}

const fail = (message) => {
  console.error(`capture-site: ${message}`);
  process.exit(1);
};

let browser;
try {
  const status = await ensureBrowser();
  if (!status?.path) {
    fail('no headless browser available (run `npx remotion browser ensure`)');
  }

  await mkdir(outDir, { recursive: true });

  browser = await puppeteer.launch({
    executablePath: status.path,
    headless: true,
    // Containers run as root without a big /dev/shm; both flags are required
    // there and harmless locally.
    args: ['--no-sandbox', '--disable-dev-shm-usage', '--hide-scrollbars'],
  });

  const page = await browser.newPage();
  await page.setViewport({ width, height, deviceScaleFactor: 1 });
  // Some sites serve a stripped page to unknown agents.
  await page.setUserAgent(
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36',
  );

  const response = await page.goto(url, { waitUntil: 'networkidle2', timeout: 45000 });
  if (!response || !response.ok()) {
    fail(`the page returned ${response ? response.status() : 'no response'}`);
  }

  // Let fonts, hero images and entrance animations settle before frame 1.
  await new Promise((r) => setTimeout(r, settle));

  // Kill anything that would jitter between frames — a looping hero animation
  // captured over 90 stills reads as flicker, not motion.
  await page.addStyleTag({
    content: `*,*::before,*::after{animation:none!important;transition:none!important;caret-color:transparent!important}`,
  });

  const pageHeight = await page.evaluate(
    () => document.documentElement.scrollHeight || document.body.scrollHeight || 0,
  );
  const travel = Math.max(0, pageHeight - height);

  for (let i = 0; i < frames; i++) {
    // Ease in and out so the scroll starts and stops rather than snapping.
    const t = frames === 1 ? 0 : i / (frames - 1);
    const eased = t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
    const y = Math.round(travel * eased);

    await page.evaluate((top) => window.scrollTo(0, top), y);
    // One rAF is enough for the paint; a fixed sleep would just be slower.
    await page.evaluate(() => new Promise((r) => requestAnimationFrame(() => r(null))));

    await page.screenshot({
      path: `${outDir}/frame-${String(i).padStart(5, '0')}.png`,
      type: 'png',
    });
  }

  const written = (await readdir(outDir)).filter((f) => f.endsWith('.png')).length;

  console.log(
    JSON.stringify({
      ok: true,
      url: page.url(), // after redirects — this is what was really captured
      title: await page.title(),
      frames: written,
      width,
      height,
      page_height: pageHeight,
      scrolled: travel,
    }),
  );
} catch (err) {
  fail(err?.message ?? String(err));
} finally {
  await browser?.close();
}
