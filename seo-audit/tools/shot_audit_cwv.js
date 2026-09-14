const { chromium } = require('playwright-core');
const EXE = 'C:/Users/yasir/AppData/Local/ms-playwright/chromium-1234/chrome-win64/chrome.exe';
const [url, outdir, prefix] = process.argv.slice(2);

(async () => {
  const b = await chromium.launch({ executablePath: EXE });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 } });
  const p = await ctx.newPage();
  await p.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });

  // wait specifically for the Core Web Vitals card to un-hide
  const deadline = Date.now() + 260000;
  let cwv = false;
  while (Date.now() < deadline) {
    cwv = await p.evaluate(() => {
      const c = document.querySelector('#az-cwv-card');
      return !!(c && !c.hidden && c.innerHTML.trim().length > 40);
    });
    if (cwv) break;
    await p.waitForTimeout(4000);
  }
  console.log('cwv card visible: ' + cwv);

  const mini = await p.evaluate(() => {
    const c = document.querySelector('.az-nav-mini .val');
    return c ? c.style.strokeDashoffset || getComputedStyle(c).strokeDashoffset : 'none';
  });
  console.log('mini donut dashoffset: ' + mini);

  await p.waitForTimeout(1200);
  const el = await p.$('#az-cwv-card');
  if (el && cwv) {
    await el.scrollIntoViewIfNeeded();
    await p.waitForTimeout(600);
    await el.screenshot({ path: `${outdir}/${prefix}_cwv.png` });
  }
  await p.evaluate(() => window.scrollTo(0, 0));
  await p.waitForTimeout(500);
  await p.screenshot({ path: `${outdir}/${prefix}_top.png` });
  console.log('done');
  await b.close();
})();
