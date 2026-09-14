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
    const ok = await p.evaluate(() => !!document.querySelector('.az-viz-card'));
    if (ok) break;
    await p.waitForTimeout(3000);
  }
  await p.waitForTimeout(2500);

  const report = await p.evaluate(() => {
    const vw = document.documentElement.clientWidth;
    const out = [];
    document.querySelectorAll('body *').forEach(el => {
      const r = el.getBoundingClientRect();
      if (r.width === 0) return;
      const right = r.left + r.width;
      if (right > vw + 1) {
        out.push({
          tag: el.tagName.toLowerCase(),
          cls: (el.className || '').toString().slice(0, 60),
          id: el.id || '',
          left: Math.round(r.left),
          width: Math.round(r.width),
          right: Math.round(right),
          inAudit: !!el.closest('#az-premium-audit')
        });
      }
    });
    return { vw, scrollW: document.documentElement.scrollWidth, items: out.slice(0, 25) };
  });

  console.log('viewport ' + report.vw + '  scrollWidth ' + report.scrollW);
  report.items.forEach(i => {
    console.log((i.inAudit ? 'AUDIT ' : 'THEME ') + i.tag + '.' + i.cls + (i.id ? '#' + i.id : '') +
      '  left=' + i.left + ' w=' + i.width + ' right=' + i.right);
  });
  await b.close();
})();
