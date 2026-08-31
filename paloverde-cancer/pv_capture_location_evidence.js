const { chromium } = require('../seo-audit/tools/node_modules/playwright-core');
const fs = require('fs');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  const base = 'https://875051.us16.myftpupload.com';
  const dir = 'C:/Users/yasir/Documents/Final-Capstone/paloverde-cancer/screenshots';
  fs.mkdirSync(dir, { recursive: true });
  const targets = [
    ['estrella', '/estrella-location/', 'WVO-9250-W-Thomas-1.jpg'],
    ['glendale', '/glendale-location/', 'TBO-5601-W-Eugie.jpg'],
    ['scottsdale', '/scottsdale-location/', 'SDO-2-7373-N-Scottsdale.webp'],
    ['gilbert', '/east-valley-location/', 'GTO-1-1488-W-Elliot-East-Valley.jpg'],
  ];
  for (const [name, path, filename] of targets) {
    await page.goto(`${base}${path}?evidence=${Date.now()}`, { waitUntil: 'networkidle', timeout: 90000 });
    await page.locator(`img[src*="${filename}"]`).first().screenshot({ path: `${dir}/michael-confirmation-${name}-office-photo.png` });
  }
  await page.goto(`${base}/pet-scan-imaging/?evidence=${Date.now()}`, { waitUntil: 'networkidle', timeout: 90000 });
  await page.locator('.pv-pet-map iframe').scrollIntoViewIfNeeded();
  await page.waitForTimeout(5000);
  await page.locator('.pv-pet-map').screenshot({ path: `${dir}/michael-confirmation-pet-map.png` });
  await browser.close();
})().catch(error => { console.error(error); process.exit(1); });
