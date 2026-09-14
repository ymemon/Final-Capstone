const { chromium } = require('playwright-core');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  const p = await b.newPage({ viewport: { width: 390, height: 844 } });
  await p.goto('http://localhost:8081/', { waitUntil: 'networkidle' });
  await p.click('.amg-burger');
  await p.waitForTimeout(400);
  await p.screenshot({ path: 'C:/Users/yasir/Documents/Final-Capstone/local-sites/aandmgraphics/screenshots/mobile-nav-open.png' });
  await b.close();
})();
