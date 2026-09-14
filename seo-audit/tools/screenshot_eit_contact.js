const { chromium } = require('playwright-core');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1200, height: 1400 } });
  await page.goto('https://everythingit.ie/contact/?nocache=' + Date.now(), { waitUntil: 'load', timeout: 45000 });
  await page.waitForSelector('.eit-eligibility-badge', { timeout: 10000 });
  await page.screenshot({ path: 'C:\\Users\\yasir\\Documents\\Final-Capstone\\seo-audit\\tools\\eit_contact_check.png', fullPage: false });
  console.log('done');
  await browser.close();
})().catch(e => { console.error('FAILED:', e.message); process.exit(1); });
