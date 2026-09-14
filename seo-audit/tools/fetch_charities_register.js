const { chromium } = require('playwright-core');
const fs = require('fs');
const OUT = process.argv[2];
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';

const TARGETS = [
  ['https://www.charitiesregulator.ie/media/d52jwriz/register-of-charities.csv', OUT + '/roc.csv'],
  ['https://www.charitiesregulator.ie/media/yeia3rfc/charity-annual-reports.csv', OUT + '/annual.csv'],
];

(async () => {
  const ctx = await chromium.launchPersistentContext(OUT + '/chrome-profile', {
    executablePath: CHROME,
    headless: false,
    viewport: { width: 1280, height: 900 },
    acceptDownloads: true,
    args: ['--disable-blink-features=AutomationControlled'],
  });
  const page = ctx.pages()[0] || await ctx.newPage();

  await page.goto('https://www.charitiesregulator.ie/', { waitUntil: 'domcontentloaded', timeout: 120000 });
  for (let i = 0; i < 45; i++) {
    const t = await page.title().catch(() => '');
    if (!/just a moment|attention required/i.test(t)) break;
    await page.waitForTimeout(2000);
  }
  console.log('landing title:', await page.title());

  for (const [url, out] of TARGETS) {
    const res = await ctx.request.get(url, { timeout: 120000 });
    const body = await res.body();
    const head = body.slice(0, 60).toString();
    if (res.status() === 200 && !/^<!DOCTYPE|^<html/i.test(head)) {
      fs.writeFileSync(out, body);
      console.log('OK  ', out, res.status(), body.length, 'bytes');
    } else {
      console.log('FAIL', out, res.status(), body.length, 'bytes', JSON.stringify(head));
    }
  }
  await ctx.close();
})();
