const { chromium } = require('playwright-core');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  const p = await b.newPage({ viewport: { width: 1440, height: 1100 } });
  await p.goto('http://localhost:8081/', { waitUntil: 'networkidle' });
  await p.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  await p.waitForTimeout(900);
  await p.evaluate(() => {
    const el = document.querySelector('.amg-gallery');
    if (el) el.scrollIntoView({ block: 'center' });
  });
  await p.waitForTimeout(900);
  await p.screenshot({ path: 'C:/Users/yasir/Documents/Final-Capstone/local-sites/aandmgraphics/screenshots/home-gallery-check.png' });
  const count = await p.locator('.amg-gallery__item').count();
  console.log('featured tiles:', count);
  await b.close();
})();
