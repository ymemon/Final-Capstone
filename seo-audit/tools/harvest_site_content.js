const { chromium } = require('playwright-core');
const fs = require('fs');
const path = require('path');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';

const OUT = process.argv[2];
const PAGES = process.argv.slice(3);
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
         + '(KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';

(async () => {
  fs.mkdirSync(path.dirname(OUT), { recursive: true });
  const browser = await chromium.launch({ executablePath: CHROME, headless: true });
  const ctx = await browser.newContext({ userAgent: UA, viewport: { width: 1500, height: 1200 } });
  const result = {};

  for (const url of PAGES) {
    const slug = new URL(url).pathname.replace(/^\/|\/$/g, '') || 'home';
    const p = await ctx.newPage();
    try {
      await p.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
      await p.waitForTimeout(2200);

      const data = await p.evaluate(() => {
        const clean = t => (t || '').replace(/\s+/g, ' ').trim();
        const junk = /^(search products|ctrl k|menu|skip to|cookie|accept|close|©|copyright)/i;

        // Strip chrome so we keep page copy, not header/footer/nav boilerplate.
        const drop = ['header', 'nav', 'footer', 'script', 'style', 'noscript', 'form', 'iframe'];
        const root = document.body.cloneNode(true);
        drop.forEach(sel => root.querySelectorAll(sel).forEach(e => e.remove()));

        const blocks = [];
        for (const el of root.querySelectorAll('h1, h2, h3, h4, p, li')) {
          const txt = clean(el.innerText);
          if (!txt || txt.length < 3 || junk.test(txt)) continue;
          if (txt.length > 1400) continue;
          blocks.push({ tag: el.tagName.toLowerCase(), text: txt });
        }

        // De-duplicate repeated nav-ish fragments.
        const seen = new Set();
        const uniq = blocks.filter(b => {
          const k = b.tag + '|' + b.text;
          if (seen.has(k)) return false;
          seen.add(k);
          return true;
        });

        const metaDesc = document.querySelector('meta[name="description"]');
        return {
          title: document.title,
          description: metaDesc ? metaDesc.content : '',
          h1: clean((document.querySelector('h1') || {}).innerText),
          blocks: uniq,
        };
      });

      result[slug] = { url, ...data };
      console.log(`${slug.padEnd(34)} ${data.blocks.length} blocks`);
    } catch (e) {
      console.log(`FAILED ${slug}: ${String(e.message).slice(0, 80)}`);
    }
    await p.close();
  }

  fs.writeFileSync(OUT, JSON.stringify(result, null, 2));
  console.log(`\nwrote ${OUT} (${Object.keys(result).length} pages)`);
  await browser.close();
})();
