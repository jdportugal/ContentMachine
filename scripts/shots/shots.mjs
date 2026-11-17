// CI only: screenshots of "Convide um colega" on a local copy of the site.
import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
const out = process.env.OUT, base = 'http://127.0.0.1:8000';
const php = (...a) => execFileSync('php', a, { encoding: 'utf8' }).trim();
const browser = await chromium.launch();
async function semCookies(page) {
  for (const t of ['Recusar', 'Rejeitar', 'Só essenciais', 'Aceitar']) {
    const b = page.getByRole('button', { name: t });
    if (await b.count()) { await b.first().click().catch(() => {}); return; }
  }
}
for (const [nome, vp] of [['telemovel', { width: 390, height: 844 }], ['computador', { width: 1280, height: 900 }]]) {
  const ctx = await browser.newContext({ viewport: vp, deviceScaleFactor: 2 });
  const page = await ctx.newPage();
  await page.goto(base + '/?agencia=remax');
  await semCookies(page);
  await page.fill('#anuncio', base + '/demo/moradia-lajeosa');
  await page.locator('form[wire\\:submit="pedir"] button[type=submit]').click();
  await page.waitForTimeout(2500);
  php('scripts/shots/pronto.php', process.env.MP4, nome === 'telemovel' ? '' : '');
  await page.reload(); await page.waitForTimeout(1500);
  const card = page.locator('#pedido');
  await card.screenshot({ path: `${out}/${nome}-1-video-pronto.png` });
  await page.fill('input[wire\\:model="meuNome"]', 'Ana Silva');
  await page.fill('input[wire\\:model="meuEmail"]', 'ana@exemplo.pt');
  await page.getByRole('button', { name: 'Criar o meu link de convite' }).click();
  await page.waitForTimeout(1500);
  await card.screenshot({ path: `${out}/${nome}-2-link-de-convite.png` });
  await page.fill('input[wire\\:model="emailColega"]', 'bruno@exemplo.pt');
  await page.getByRole('button', { name: 'Enviar convite' }).click();
  await page.waitForTimeout(1500);
  await card.screenshot({ path: `${out}/${nome}-3-convite-enviado.png` });
  const link = await page.locator('p.select-all').innerText();
  // The colleague, in another browser, by the link.
  const ctx2 = await browser.newContext({ viewport: vp, deviceScaleFactor: 2 });
  const p2 = await ctx2.newPage();
  await p2.goto(link.replace('https://imocena.com', base).replace(/^http:\/\/localhost(:\d+)?/, base));
  await semCookies(p2); await p2.waitForTimeout(800);
  await p2.screenshot({ path: `${out}/${nome}-4-colega-chega.png` });
  await ctx2.close(); await ctx.close();
  php('-r', `require 'vendor/autoload.php'; $a=require 'bootstrap/app.php'; $a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); DB::table('leads')->delete(); DB::table('convites_enviados')->delete();`);
}
php('scripts/shots/emails.php', out);
const ctx = await browser.newContext({ viewport: { width: 600, height: 700 }, deviceScaleFactor: 2 });
const page = await ctx.newPage();
for (const f of ['email-convite', 'email-oferta']) { await page.goto(`file://${out}/${f}.html`); await page.screenshot({ path: `${out}/${f}.png`, fullPage: true }); }
await browser.close();
