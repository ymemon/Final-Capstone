const { chromium } = require('playwright-core');
(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2,
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
  });
  const page = await context.newPage();
  await page.goto('https://875051.us16.myftpupload.com/east-valley-location/?nocache=' + Date.now(), { waitUntil: 'load', timeout: 45000 });
  await page.screenshot({ path: 'pv_east_valley_full.png', fullPage: true });
  console.log('done, url:', page.url());
  await browser.close();
})().catch(e => { console.error('FAILED:', e.message); process.exit(1); });
