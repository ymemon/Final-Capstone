const { chromium } = require('playwright-core');
const EXE = 'C:/Users/yasir/AppData/Local/ms-playwright/chromium-1234/chrome-win64/chrome.exe';
const urls = process.argv.slice(2);

(async () => {
  const b = await chromium.launch({ executablePath: EXE });
  for (const url of urls) {
    const ctx = await b.newContext({ viewport: { width: 390, height: 844 } });
    const p = await ctx.newPage();
    try {
      await p.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
      await p.waitForTimeout(2500);
      const r = await p.evaluate(() => {
        const vw = document.documentElement.clientWidth;
        const offenders = [];
        document.querySelectorAll('body *').forEach(el => {
          const b = el.getBoundingClientRect();
          if (b.width && b.left + b.width > vw + 1) {
            offenders.push(((el.className || '').toString().split(' ')[0] || el.tagName.toLowerCase()));
          }
        });
        return { vw, sw: document.documentElement.scrollWidth, first: offenders.slice(0, 3) };
      });
      console.log(url.split('?')[0] + '  vw=' + r.vw + ' scrollW=' + r.sw + '  overflow=' + (r.sw > r.vw + 1) + '  first=' + r.first.join(','));
    } catch (e) {
      console.log(url.split('?')[0] + '  ERROR ' + e.message.slice(0, 60));
    }
    await ctx.close();
  }
  await b.close();
})();
