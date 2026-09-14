const { chromium } = require('playwright-core');
const PAGES = { procurement: 'procurement', professional_services: 'professional-services', third_party_services: 'third-party-services' };
(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  for (const [label, slug] of Object.entries(PAGES)) {
    const page = await context.newPage();
    await page.goto(`https://everythingit.ie/${slug}/?nc=${Date.now()}`, { waitUntil: 'domcontentloaded', timeout: 40000 });
    await page.waitForTimeout(800);
    const height = await page.evaluate(() => document.body.scrollHeight);
    await page.evaluate(h => window.scrollTo(0, h * 0.45), height);
    await page.waitForTimeout(300);
    await page.screenshot({ path: `eit_${label}_cards.png` });
    await page.close();
  }
  await browser.close();
})().catch(e => { console.error(e.message); process.exit(1); });
