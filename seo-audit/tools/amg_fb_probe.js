const { chromium } = require('playwright-core');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  const p = await b.newPage({
    viewport: { width: 1440, height: 1000 },
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
    locale: 'en-US',
  });
  await p.goto('https://www.facebook.com/amwrapsusa/', { waitUntil: 'domcontentloaded', timeout: 45000 });
  await p.waitForTimeout(4000);
  console.log('URL:', p.url());
  console.log('TITLE:', await p.title());
  const bodyText = await p.evaluate(() => document.body.innerText.slice(0, 600));
  console.log('--- BODY TEXT ---');
  console.log(bodyText);
  const imgs = await p.evaluate(() =>
    Array.from(document.images).map(i => ({ src: i.currentSrc || i.src, w: i.naturalWidth, h: i.naturalHeight }))
      .filter(i => i.w > 200 && i.h > 200)
  );
  console.log('--- IMAGES >200px:', imgs.length);
  imgs.slice(0, 30).forEach(i => console.log(i.w + 'x' + i.h, i.src.slice(0, 140)));
  await p.screenshot({ path: 'C:/Users/yasir/Documents/Final-Capstone/local-sites/aandmgraphics/screenshots/fb-probe.png' });
  await b.close();
})();
