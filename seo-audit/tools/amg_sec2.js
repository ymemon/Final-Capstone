const { chromium } = require('playwright-core');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const OUT='C:/Users/yasir/Documents/Final-Capstone/local-sites/aandmgraphics/screenshots/';
(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  const p = await b.newPage({ viewport: { width: 1440, height: 1000 } });
  await p.goto('http://localhost:8081/', { waitUntil: 'networkidle' });
  await p.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  await p.waitForTimeout(1200);
  for (const [sel,name] of [['.amg-strip','strip'],['.amg-services','services'],['.amg-split','split'],['.amg-process','process']]) {
    const el = await p.$(sel);
    if (el) { await el.scrollIntoViewIfNeeded(); await p.waitForTimeout(600); await el.screenshot({ path: OUT+'sec-'+name+'.png' }); console.log('shot', name); }
  }
  await b.close();
})();
