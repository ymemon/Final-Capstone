const { chromium } = require('playwright-core');
const EXE = 'C:/Users/yasir/AppData/Local/ms-playwright/chromium-1234/chrome-win64/chrome.exe';

const [url, outdir, prefix] = process.argv.slice(2);

(async () => {
  const b = await chromium.launch({ executablePath: EXE });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 } });
  const p = await ctx.newPage();
  await p.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });

  // wait for the progress panel to disappear / results to appear
  const deadline = Date.now() + 240000;
  let ready = false;
  while (Date.now() < deadline) {
    ready = await p.evaluate(() => {
      const prog = document.querySelector('#az-audit-progress, .azwc-progress, [id*="progress"]');
      const progVisible = prog && !prog.hidden && prog.offsetParent !== null;
      const bodyText = document.body.innerText || '';
      const hasScore = /\b(SEO score|Score|checks?)\b/i.test(bodyText) && !/scanning|running/i.test(bodyText);
      return (!progVisible && hasScore) || /out of 100|\/100/i.test(bodyText);
    });
    if (ready) break;
    await p.waitForTimeout(3000);
  }
  console.log('results ready: ' + ready + ' after wait');
  await p.waitForTimeout(2500);

  const total = await p.evaluate(() => document.body.scrollHeight);
  console.log('page height: ' + total);
  let i = 0;
  for (let y = 0; y < total && i < 10; y += 900) {
    await p.evaluate((yy) => window.scrollTo(0, yy), y);
    await p.waitForTimeout(400);
    await p.screenshot({ path: `${outdir}/${prefix}_${i}.png` });
    i++;
  }
  console.log(`saved ${i} screenshots`);
  await b.close();
})();
