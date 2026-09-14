const { chromium } = require('playwright-core');

const PAGES = {
  good_reference: 'secure-scalable-network-design',
  procurement: 'procurement',
  cloud_computing: 'cloud-computing',
  professional_services: 'professional-services',
  third_party_services: 'third-party-services',
};

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });

  for (const [label, slug] of Object.entries(PAGES)) {
    const page = await context.newPage();
    try {
      await page.goto(`https://everythingit.ie/${slug}/?nc=${Date.now()}`, { waitUntil: 'domcontentloaded', timeout: 40000 });
      await page.waitForTimeout(1000);
      const title = await page.title();
      const h1 = await page.evaluate(() => document.querySelector('h1')?.innerText || '(no h1)');
      console.log(`${label.padEnd(24)} title="${title}"  h1="${h1}"`);
      await page.screenshot({ path: `eit_${label}_top.png`, clip: { x: 0, y: 0, width: 1280, height: 900 } });
    } catch (e) {
      console.log(`${label} ERROR: ${e.message}`);
    }
    await page.close();
  }
  await browser.close();
})().catch(e => { console.error('FAILED:', e.message); process.exit(1); });
