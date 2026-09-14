const { chromium } = require('playwright-core');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
  await p.goto('http://localhost:8081/', { waitUntil: 'networkidle' });
  await p.waitForTimeout(700);
  await p.screenshot({ path: 'C:/Users/yasir/Documents/Final-Capstone/local-sites/aandmgraphics/screenshots/hero-check.png' });
  await b.close();
})();
