const { chromium } = require('playwright-core');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const BASE = process.argv[2] || 'http://localhost:8080';
const SHOT_DIR = process.argv[3];
const fs = require('fs');

(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  const p = await b.newPage({ viewport: { width: 1440, height: 1000 } });

  // Breadth-first crawl. A single-level scrape found only the pages linked from
  // the home page and reported "all clean" while nine child pages sat unvisited.
  const seen = new Set([BASE + '/']);
  const queue = [BASE + '/'];
  const urls = [];
  while (queue.length) {
    const url = queue.shift();
    urls.push(url);
    try {
      await p.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
      await p.waitForTimeout(400);
      const links = await p.evaluate((base) => {
        const out = [];
        for (const a of document.querySelectorAll('a[href]')) {
          const h = a.href.split('#')[0];
          if (h.startsWith(base) && !h.match(/\.(png|jpe?g|gif|webp|css|js|xml)$/i)) out.push(h);
        }
        return out;
      }, BASE);
      for (const l of links) {
        if (!seen.has(l)) { seen.add(l); queue.push(l); }
      }
    } catch { /* recorded on the second pass below */ }
  }
  console.log(`discovered ${urls.length} pages by crawl\n`);

  const rows = [];
  const problems = [];

  for (const url of urls) {
    const errs = [];
    p.removeAllListeners('pageerror');
    p.removeAllListeners('response');
    p.on('pageerror', e => errs.push('JS: ' + e.message.slice(0, 70)));
    p.on('response', r => {
      if (r.status() >= 400 && r.url().startsWith(BASE)) errs.push(r.status() + ' ' + r.url().replace(BASE, ''));
    });

    let res;
    try {
      res = await p.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
      await p.waitForTimeout(700);
    } catch (e) {
      problems.push(`${url} -> NAV FAIL ${e.message.slice(0, 60)}`);
      continue;
    }

    const info = await p.evaluate(() => {
      const words = (document.querySelector('main, .entry, body') || document.body).innerText.trim().split(/\s+/).length;
      const desc = document.querySelector('meta[name="description"]');
      return {
        title: document.title,
        h1count: document.querySelectorAll('h1').length,
        h1: (document.querySelector('h1') || {}).innerText || '',
        desc: desc ? desc.content.length : 0,
        words,
        schema: document.querySelectorAll('script[type="application/ld+json"]').length,
        ovf: document.documentElement.scrollWidth > document.documentElement.clientWidth,
        imgNoAlt: [...document.querySelectorAll('img')].filter(i => !i.alt).length,
      };
    });

    const path = url.replace(BASE, '') || '/';
    rows.push({ path, status: res.status(), ...info, errs: errs.length });
    if (res.status() !== 200) problems.push(`${path} -> HTTP ${res.status()}`);
    if (info.h1count !== 1) problems.push(`${path} -> ${info.h1count} h1 tags`);
    if (info.desc === 0) problems.push(`${path} -> no meta description`);
    if (info.words < 60) problems.push(`${path} -> thin (${info.words} words)`);
    if (info.ovf) problems.push(`${path} -> horizontal overflow`);
    if (errs.length) problems.push(`${path} -> ${errs.slice(0, 2).join('; ')}`);
  }

  console.log('path'.padEnd(38) + 'st  h1  desc  words  sch  err');
  for (const r of rows) {
    console.log(
      r.path.slice(0, 37).padEnd(38) +
      String(r.status).padEnd(4) +
      String(r.h1count).padEnd(4) +
      String(r.desc).padEnd(6) +
      String(r.words).padEnd(7) +
      String(r.schema).padEnd(5) +
      String(r.errs)
    );
  }
  console.log(`\n${rows.length} pages audited`);
  console.log(problems.length ? '\nPROBLEMS:\n  ' + problems.join('\n  ') : '\nNo problems found.');

  if (SHOT_DIR) {
    fs.mkdirSync(SHOT_DIR, { recursive: true });
    const mob = await b.newPage({ viewport: { width: 390, height: 844 } });
    await mob.goto(BASE + '/', { waitUntil: 'networkidle' });
    await mob.waitForTimeout(1200);
    const mobOvf = await mob.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
    console.log('mobile 390px horizontal overflow: ' + (mobOvf ? 'YES - FIX' : 'no'));
    await mob.screenshot({ path: SHOT_DIR + '/mobile-home.png', fullPage: true });
  }
  await b.close();
})();
