const { chromium } = require('playwright-core');
const fs = require('fs');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const OUT = 'C:/Users/yasir/Documents/Final-Capstone/local-sites/aandmgraphics/harvested/fb';
const START = 'https://www.facebook.com/photo/?fbid=1159609099509356&set=a.494086766061590';

(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  const p = await b.newPage({
    viewport: { width: 1600, height: 1200 },
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
  });

  const links = JSON.parse(fs.readFileSync(OUT + '/photo_links.json', 'utf8'));
  await p.goto(links[0], { waitUntil: 'domcontentloaded', timeout: 40000 });
  await p.waitForTimeout(3000);

  const collected = new Map();
  for (let i = 0; i < 90; i++) {
    try {
      const imgs = await p.evaluate(() =>
        Array.from(document.images)
          .map(i => ({ src: i.currentSrc || i.src, w: i.naturalWidth, h: i.naturalHeight }))
          .filter(i => i.w > 600 && /fbcdn/.test(i.src))
          .sort((a, b) => b.w * b.h - a.w * a.h)
      );
      if (imgs[0]) {
        const key = imgs[0].src.split('?')[0];
        if (!collected.has(key)) collected.set(key, imgs[0]);
      }
    } catch {}
    // advance
    const advanced = await p.evaluate(() => {
      const sel = document.querySelector('[aria-label="Next photo"], [aria-label="Next Photo"], a[aria-label*="Next"]');
      if (sel) { sel.click(); return true; }
      return false;
    });
    if (!advanced) { await p.keyboard.press('ArrowRight'); }
    await p.waitForTimeout(1600);
    if (i % 15 === 0) console.log('step', i, 'collected', collected.size);
  }

  const out = [...collected.values()];
  fs.writeFileSync(OUT + '/viewer_big.json', JSON.stringify(out, null, 2));
  console.log('TOTAL uncropped hi-res collected:', out.length);
  out.slice(0, 100).forEach(i => console.log(i.w + 'x' + i.h));
  await b.close();
})();
