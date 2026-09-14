const { chromium } = require('playwright-core');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  await page.goto('https://875051.us16.myftpupload.com/schedule/?nocache=' + Date.now(), { waitUntil: 'load', timeout: 45000 });
  await page.screenshot({ path: 'pv_schedule_desktop.png', fullPage: true });

  const page2 = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  await page2.goto('https://875051.us16.myftpupload.com/?nocache=' + Date.now(), { waitUntil: 'load', timeout: 45000 });
  await page2.screenshot({ path: 'pv_home_desktop.png', fullPage: false });

  console.log('done');
  await browser.close();
})().catch(e => { console.error('FAILED:', e.message); process.exit(1); });
