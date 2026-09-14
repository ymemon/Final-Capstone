const { chromium } = require('playwright-core');
(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true,
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
  });
  const page = await context.newPage();
  await page.goto('https://875051.us16.myftpupload.com/conditions-we-treat/?nc=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 40000 });
  await page.waitForTimeout(1200);
  const height = await page.evaluate(() => document.body.scrollHeight);
  console.log('page height:', height);
  // Scroll to each position and screenshot just the viewport (avoids clip
  // coordinate mismatches from lazy-loaded content).
  for (let y = 0; y < Math.min(height, 4000); y += 800) {
    await page.evaluate((sy) => window.scrollTo(0, sy), y);
    await page.waitForTimeout(400);
    await page.screenshot({ path: `pv_ctw_${y}.png` });
  }
  await browser.close();
})().catch(e => { console.error('FAILED:', e.message); process.exit(1); });
