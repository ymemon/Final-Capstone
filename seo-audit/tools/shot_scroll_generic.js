const { chromium } = require('playwright-core');
const EXE = 'C:/Users/yasir/AppData/Local/ms-playwright/chromium-1234/chrome-win64/chrome.exe';

const [url, outdir, prefix, maxShots] = process.argv.slice(2);
const MAX = maxShots ? parseInt(maxShots, 10) : 8;

(async () => {
  const b = await chromium.launch({ executablePath: EXE });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 } });
  const p = await ctx.newPage();
  await p.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await p.waitForTimeout(1800);
  const total = await p.evaluate(() => document.body.scrollHeight);
  const vh = 900;
  let i = 0;
  for (let y = 0; y < total && i < MAX; y += vh) {
    await p.evaluate((yy) => window.scrollTo(0, yy), y);
    await p.waitForTimeout(350);
    await p.screenshot({ path: `${outdir}/${prefix}_${i}.png` });
    i++;
  }
  console.log(`saved ${i} screenshots, total height ${total}`);
  await b.close();
})();
