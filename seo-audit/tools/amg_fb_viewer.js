const { chromium } = require('playwright-core');
const fs = require('fs');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const OUT = 'C:/Users/yasir/Documents/Final-Capstone/local-sites/aandmgraphics/harvested/fb';

(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  const p = await b.newPage({
    viewport: { width: 1600, height: 1200 },
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
    locale: 'en-US',
  });
  await p.goto('https://www.facebook.com/amwrapsusa/photos', { waitUntil: 'domcontentloaded', timeout: 45000 });
  await p.waitForTimeout(3500);
  for (let i = 0; i < 10; i++) { await p.evaluate(() => window.scrollBy(0, 1400)); await p.waitForTimeout(900); }

  const links = await p.evaluate(() =>
    Array.from(new Set(Array.from(document.querySelectorAll('a[href*="/photo/"], a[href*="fbid="]'))
      .map(a => a.href).filter(h => /fbid=\d+/.test(h))))
  );
  console.log('photo permalinks found:', links.length);
  fs.writeFileSync(OUT + '/photo_links.json', JSON.stringify(links, null, 2));
  await b.close();
})();
