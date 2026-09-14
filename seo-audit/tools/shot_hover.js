const { chromium } = require('playwright-core');
const EXE = 'C:/Users/yasir/AppData/Local/ms-playwright/chromium-1234/chrome-win64/chrome.exe';
const [url, selector, outfile] = process.argv.slice(2);

(async () => {
  const b = await chromium.launch({ executablePath: EXE });
  const ctx = await b.newContext({ viewport: { width: 1280, height: 900 } });
  const p = await ctx.newPage();
  await p.goto(url, { waitUntil: 'domcontentloaded', timeout: 40000 });
  await p.waitForTimeout(1500);
  const el = await p.$(selector);
  if (el) {
    await el.scrollIntoViewIfNeeded();
    await el.hover();
    await p.waitForTimeout(700);
    await el.screenshot({ path: outfile });
    console.log('saved (hovered): ' + outfile);
  } else {
    console.log('selector not found: ' + selector);
  }
  await b.close();
})();
