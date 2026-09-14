const { chromium } = require('playwright-core');
const EXE = 'C:/Users/yasir/AppData/Local/ms-playwright/chromium-1234/chrome-win64/chrome.exe';
const [url] = process.argv.slice(2);

(async () => {
  const b = await chromium.launch({ executablePath: EXE });
  const ctx = await b.newContext({ viewport: { width: 390, height: 844 } });
  const p = await ctx.newPage();
  await p.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
  const dl = Date.now() + 180000;
  while (Date.now() < dl) {
    if (await p.evaluate(() => !!document.querySelector('.az-nav-item'))) break;
    await p.waitForTimeout(3000);
  }
  await p.waitForTimeout(2000);

  const out = await p.evaluate(() => {
    const sel = ['#az-premium-audit', '.az-report', '.az-rep-top', '.az-rep-body', '.az-nav',
                 '.az-nav-list', '.az-nav-item', '.az-viz', '.az-viz-card', '.az-panel', '.az-towers'];
    const vw = document.documentElement.clientWidth;
    return { vw, rows: sel.map(s => {
      const el = document.querySelector(s);
      if (!el) return s + ' : missing';
      const r = el.getBoundingClientRect();
      const cs = getComputedStyle(el);
      return s + ' : left=' + Math.round(r.left) + ' w=' + Math.round(r.width) +
             ' right=' + Math.round(r.left + r.width) + ' minW=' + cs.minWidth + ' disp=' + cs.display;
    })};
  });
  console.log('viewport ' + out.vw);
  out.rows.forEach(r => console.log('  ' + r));
  await b.close();
})();
