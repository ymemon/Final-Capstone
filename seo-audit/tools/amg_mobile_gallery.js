const { chromium } = require('playwright-core');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  const p = await b.newPage({ viewport: { width: 390, height: 844 } });
  await p.goto('http://localhost:8081/gallery/', { waitUntil: 'networkidle' });
  await p.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  await p.waitForTimeout(1200);
  await p.evaluate(() => window.scrollTo(0, 0));
  await p.waitForTimeout(500);
  await p.screenshot({ path: 'C:/Users/yasir/Documents/Final-Capstone/local-sites/aandmgraphics/screenshots/gallery-mobile-full.png', fullPage: true });
  // horizontal overflow check across pages
  for (const u of ['http://localhost:8081/','http://localhost:8081/gallery/','http://localhost:8081/get-a-quote/','http://localhost:8081/services/']) {
    await p.goto(u, { waitUntil: 'networkidle' });
    const o = await p.evaluate(() => ({ sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth }));
    console.log(u.replace('http://localhost:8081',''), 'scrollW', o.sw, 'clientW', o.cw, o.sw > o.cw + 1 ? '*** OVERFLOW ***' : 'ok');
  }
  await b.close();
})();
