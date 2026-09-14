const { chromium } = require('playwright-core');
const fs = require('fs');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const OUT = 'C:/Users/yasir/Documents/Final-Capstone/local-sites/aandmgraphics/harvested/fb';

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  const p = await b.newPage({
    viewport: { width: 1600, height: 1200 },
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
    locale: 'en-US',
  });

  const seen = new Map();
  // Capture every fbcdn image the page actually requests — beats scraping the DOM,
  // since FB swaps srcs as you scroll.
  p.on('response', async (res) => {
    const url = res.url();
    if (/fbcdn\.net\/v\/t39|fbcdn\.net\/v\/t1/.test(url) && /\.jpg|\.png|\.webp/.test(url)) {
      if (!seen.has(url)) seen.set(url, true);
    }
  });

  const targets = [
    'https://www.facebook.com/amwrapsusa/photos',
    'https://www.facebook.com/amwrapsusa/photos_by',
    'https://www.facebook.com/amwrapsusa/',
  ];

  for (const t of targets) {
    try {
      await p.goto(t, { waitUntil: 'domcontentloaded', timeout: 45000 });
      await p.waitForTimeout(3500);
      // Dismiss the login overlay if it blocks scrolling.
      await p.keyboard.press('Escape').catch(() => {});
      for (let i = 0; i < 14; i++) {
        await p.evaluate(() => window.scrollBy(0, 1400));
        await p.waitForTimeout(1100);
      }
      console.log('scrolled', t, '— unique fbcdn urls so far:', seen.size);
    } catch (e) {
      console.log('FAILED', t, e.message.slice(0, 100));
    }
  }

  fs.writeFileSync(OUT + '/urls.json', JSON.stringify([...seen.keys()], null, 2));
  console.log('TOTAL unique fbcdn image urls:', seen.size);
  await b.close();
})();
