/**
 * Static-export a local WordPress site for client preview hosting.
 *
 * Crawls every internal page, saves it as <path>/index.html, pulls down every
 * referenced local asset, and rewrites absolute local URLs to the deploy path.
 * Adds a noindex directive to every page: a client preview must never compete
 * with the client's real site in search.
 *
 * usage: node static_export.js <srcBase> <outDir> <deployBasePath>
 */
const { chromium } = require('playwright-core');
const fs = require('fs');
const path = require('path');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';

const SRC = process.argv[2];
const OUT = process.argv[3];
const BASE = (process.argv[4] || '').replace(/\/$/, '');

const save = (rel, buf) => {
  const p = path.join(OUT, rel);
  fs.mkdirSync(path.dirname(p), { recursive: true });
  fs.writeFileSync(p, buf);
};

(async () => {
  const browser = await chromium.launch({ executablePath: CHROME, headless: true });
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await ctx.newPage();

  // 1. crawl
  const seen = new Set([SRC + '/']);
  const queue = [SRC + '/'];
  const pages = [];
  while (queue.length) {
    const url = queue.shift();
    await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 });
    await page.waitForTimeout(400);
    const links = await page.evaluate((s) => [...document.querySelectorAll('a[href]')]
      .map(a => a.href.split('#')[0])
      .filter(h => h.startsWith(s) && !h.match(/\.(png|jpe?g|gif|webp|css|js|xml)$/i)), SRC);
    for (const l of links) if (!seen.has(l)) { seen.add(l); queue.push(l); }
    pages.push({ url, html: await page.content() });
    process.stdout.write('.');
  }
  console.log(`\ncrawled ${pages.length} pages`);

  // 2. collect asset URLs from the rendered HTML
  const assets = new Set();
  const assetRe = new RegExp(SRC.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\/(wp-content|wp-includes)\\/[^"\')\\s]+', 'g');
  for (const p of pages) {
    for (const m of p.html.match(assetRe) || []) assets.add(m.split('?')[0]);
  }
  console.log(`${assets.size} local assets referenced`);

  // 3. download assets
  let got = 0;
  for (const a of assets) {
    try {
      const res = await ctx.request.get(a, { timeout: 40000 });
      if (res.status() === 200) {
        save(a.replace(SRC + '/', ''), await res.body());
        got++;
      }
    } catch { /* reported by the count below */ }
  }
  console.log(`downloaded ${got}/${assets.size} assets`);

  // 4. write pages with rewritten URLs + noindex
  const srcRe = new RegExp(SRC.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
  for (const p of pages) {
    let rel = p.url.replace(SRC, '').replace(/^\//, '').replace(/\/$/, '');
    const file = (rel === '' ? 'index.html' : rel + '/index.html');

    let html = p.html.replace(srcRe, BASE);
    // A preview must not be indexed, and must not be mistaken for the live site.
    html = html.replace(
      /<head([^>]*)>/i,
      '<head$1>\n<meta name="robots" content="noindex, nofollow, noarchive">'
    );
    // The form needs PHP; on a static host it would silently do nothing.
    html = html.replace(
      /<form class="formcard"[^>]*>/gi,
      '<form class="formcard" onsubmit="alert(\'This is a design preview \\u2014 the enquiry form is live on the real site.\'); return false;">'
    );
    save(file, Buffer.from(html, 'utf8'));
  }
  console.log(`wrote ${pages.length} html files to ${OUT}`);
  await browser.close();
})();
