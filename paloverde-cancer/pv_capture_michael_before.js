const { chromium } = require('../seo-audit/tools/node_modules/playwright-core');
const fs = require('fs');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1 });
  const base = 'https://875051.us16.myftpupload.com';
  const targets = [
    ['home', '/'],
    ['team', '/your-team/'],
    ['estrella', '/estrella-location/'],
    ['glendale', '/glendale-location/'],
    ['scottsdale', '/scottsdale-location/'],
    ['gilbert', '/east-valley-location/'],
    ['pet', '/pet-scan-imaging/'],
  ];
  const output = [];
  for (const [name, path] of targets) {
    await page.goto(`${base}${path}?after=${Date.now()}`, { waitUntil: 'networkidle', timeout: 90000 });
    const file = `C:/Users/yasir/Documents/Final-Capstone/paloverde-cancer/screenshots/michael-after-${name}.png`;
    fs.mkdirSync('C:/Users/yasir/Documents/Final-Capstone/paloverde-cancer/screenshots', { recursive: true });
    await page.screenshot({ path: file, fullPage: true });
    output.push({ name, path, url: page.url(), title: await page.title(), file, bodyWidth: await page.locator('body').evaluate(el => el.scrollWidth), bodyText: (await page.locator('body').innerText()).slice(0, 500) });
  }
  console.log(JSON.stringify(output, null, 2));
  await browser.close();
})();
