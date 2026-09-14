const { chromium } = require('playwright-core');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const OUT='C:/Users/yasir/Documents/Final-Capstone/local-sites/aandmgraphics/screenshots/';
(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  const p = await b.newPage({ viewport: { width: 390, height: 844 } });
  await p.goto('http://localhost:8081/book/', { waitUntil: 'networkidle' });
  await p.waitForTimeout(1800);
  await p.locator('.amg-cal__cell.is-open').nth(1).click();
  await p.waitForTimeout(1500);
  const o = await p.evaluate(()=>({sw:document.documentElement.scrollWidth,cw:document.documentElement.clientWidth}));
  console.log('mobile overflow:', o.sw, 'vs', o.cw, o.sw>o.cw+1?'*** OVERFLOW ***':'ok');
  console.log('slots visible:', await p.locator('.amg-slot').count());
  await p.screenshot({ path: OUT+'booking-mobile.png', fullPage: true });
  await b.close();
})();
