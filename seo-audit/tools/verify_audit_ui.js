const { chromium } = require('playwright-core');
const EXE = 'C:/Users/yasir/AppData/Local/ms-playwright/chromium-1234/chrome-win64/chrome.exe';
const [url, outdir] = process.argv.slice(2);

(async () => {
  const b = await chromium.launch({ executablePath: EXE });

  // ---------- desktop: filtering interaction ----------
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 } });
  const p = await ctx.newPage();
  await p.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
  const dl = Date.now() + 180000;
  while (Date.now() < dl) {
    const ok = await p.evaluate(() => !!document.querySelector('.az-nav-item'));
    if (ok) break;
    await p.waitForTimeout(3000);
  }
  await p.waitForTimeout(3000);

  const before = await p.evaluate(() => {
    const l = document.querySelector('#az-check-list');
    return l ? [...l.children].filter(c => c.style.display !== 'none').length : -1;
  });

  // click the second nav item (first real category)
  await p.evaluate(() => {
    const items = document.querySelectorAll('.az-nav-item');
    if (items[1]) items[1].click();
  });
  await p.waitForTimeout(600);
  const after = await p.evaluate(() => {
    const l = document.querySelector('#az-check-list');
    const vis = l ? [...l.children].filter(c => c.style.display !== 'none').length : -1;
    const active = document.querySelector('.az-nav-item.is-on');
    const note = document.querySelector('#az-filter-note');
    return { vis, active: active ? active.textContent.trim() : '', note: note ? note.textContent.trim() : '' };
  });
  console.log('visible before filter: ' + before);
  console.log('visible after filter:  ' + after.vis + '  (active: ' + after.active + ')');
  console.log('filter note: ' + after.note);
  await p.screenshot({ path: `${outdir}/verify_filtered.png` });

  // ---------- mobile ----------
  const mctx = await b.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 });
  const mp = await mctx.newPage();
  await mp.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
  const dl2 = Date.now() + 180000;
  while (Date.now() < dl2) {
    const ok = await mp.evaluate(() => !!document.querySelector('.az-viz-card'));
    if (ok) break;
    await mp.waitForTimeout(3000);
  }
  await mp.waitForTimeout(2500);
  const overflow = await mp.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
  console.log('mobile horizontal overflow: ' + overflow);
  await mp.screenshot({ path: `${outdir}/verify_mobile.png` });

  await b.close();
})();
