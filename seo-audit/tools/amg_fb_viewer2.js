const { chromium } = require('playwright-core');
const fs = require('fs');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const OUT = 'C:/Users/yasir/Documents/Final-Capstone/local-sites/aandmgraphics/harvested/fb';
const links = JSON.parse(fs.readFileSync(OUT + '/photo_links.json', 'utf8'));

(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  const p = await b.newPage({
    viewport: { width: 1600, height: 1200 },
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
  });
  const found = [];
  for (const link of links.slice(0, 6)) {
    try {
      await p.goto(link, { waitUntil: 'domcontentloaded', timeout: 40000 });
      await p.waitForTimeout(3000);
      const imgs = await p.evaluate(() =>
        Array.from(document.images)
          .map(i => ({ src: i.currentSrc || i.src, w: i.naturalWidth, h: i.naturalHeight }))
          .filter(i => i.w > 600).sort((a,b) => b.w*b.h - a.w*a.h)
      );
      if (imgs[0]) {
        console.log(imgs[0].w + 'x' + imgs[0].h, imgs[0].src.slice(0, 110));
        found.push(imgs[0]);
      } else {
        console.log('no big img on', link.slice(0, 70));
      }
    } catch (e) { console.log('fail', e.message.slice(0, 60)); }
  }
  fs.writeFileSync(OUT + '/viewer_imgs.json', JSON.stringify(found, null, 2));
  await b.close();
})();
