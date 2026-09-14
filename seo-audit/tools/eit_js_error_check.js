const { chromium } = require('playwright-core');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';

const PAGES = process.argv.slice(2);

(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  for (const url of PAGES) {
    const p = await b.newPage({ viewport: { width: 1280, height: 900 } });
    const errs = [], failed = [];
    p.on('pageerror', e => errs.push(e.message));
    p.on('requestfailed', r => failed.push(r.url().split('/').pop()));
    p.on('response', r => { if (r.status() >= 400) failed.push(r.status() + ' ' + r.url().split('/').pop()); });
    await p.goto(url, { waitUntil: 'networkidle', timeout: 60000 }).catch(e => errs.push('NAV: ' + e.message));
    await p.waitForTimeout(1500);
    const hasElementor = await p.evaluate(() => typeof window.elementorFrontend !== 'undefined');
    console.log(`\n${url}`);
    console.log('  elementorFrontend defined:', hasElementor);
    console.log('  pageerrors:', errs.length ? errs.slice(0, 4) : 'none');
    console.log('  failed/4xx requests:', failed.length ? failed.slice(0, 6) : 'none');
    await p.close();
  }
  await b.close();
})();
