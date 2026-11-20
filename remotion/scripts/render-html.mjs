// Prints a page's HTML after its JavaScript has run, for listing sites that are
// single-page apps (the server sends an empty shell and the browser fetches the data).
//
// Same headless Chrome as capture-site.mjs (`npx remotion browser ensure` put it in the image).
//
//   node render-html.mjs --url https://x.com/property/1 [--wait "h1"] [--timeout 30000]

import { ensureBrowser } from '@remotion/renderer';
import puppeteer from 'puppeteer-core';

const arg = (name, fallback = null) => {
  const i = process.argv.indexOf(`--${name}`);
  return i !== -1 && process.argv[i + 1] ? process.argv[i + 1] : fallback;
};

const url = arg('url');
const wait = arg('wait');
const timeout = Number(arg('timeout', '30000'));
if (!url) {
  console.error('render-html: --url is required');
  process.exit(2);
}

let browser;
try {
  const status = await ensureBrowser();
  if (!status?.path) {
    throw new Error('no headless browser available (run `npx remotion browser ensure`)');
  }
  browser = await puppeteer.launch({
    executablePath: status.path,
    headless: true,
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });
  const page = await browser.newPage();
  await page.setViewport({ width: 1366, height: 900 });
  await page.setUserAgent(
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36',
  );
  const resposta = await page.goto(url, { waitUntil: 'networkidle2', timeout });
  if (resposta && resposta.status() >= 400) {
    throw new Error(`HTTP ${resposta.status()}`);
  }
  if (wait) {
    await page.waitForSelector(wait, { timeout });
  }
  // Images that load as the page scrolls: one pass down, then back.
  await page.evaluate(async () => {
    for (let y = 0; y < document.body.scrollHeight; y += 800) {
      window.scrollTo(0, y);
      await new Promise((r) => setTimeout(r, 120));
    }
    window.scrollTo(0, 0);
  });
  await new Promise((r) => setTimeout(r, 800));
  process.stdout.write(await page.content());
} catch (e) {
  console.error(`render-html: ${e?.message ?? e}`);
  process.exitCode = 1;
} finally {
  await browser?.close();
}
