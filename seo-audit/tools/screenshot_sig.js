const { chromium } = require('playwright-core');
const path = require('path');

const previewPath = 'C:\\Users\\yasir\\Documents\\Final-Capstone\\azwebcorp-email\\signature\\preview.html';
const outPath = 'C:\\Users\\yasir\\Documents\\Final-Capstone\\azwebcorp-email\\signature\\preview.png';

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 700, height: 1000 } });
  await page.goto('file:///' + previewPath.replace(/\\/g, '/'));
  await page.screenshot({ path: outPath, fullPage: true });
  await browser.close();
  console.log('done');
})();
