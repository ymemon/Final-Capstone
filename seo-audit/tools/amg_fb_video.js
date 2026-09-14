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

  const vids = new Map();
  p.on('response', (res) => {
    const u = res.url();
    if (/\.mp4|video\.xx\.fbcdn|\/v\/t42\./.test(u)) {
      if (!vids.has(u.split('?')[0])) { vids.set(u.split('?')[0], u); }
    }
  });

  for (const target of [
    'https://www.facebook.com/amwrapsusa/videos',
    'https://www.facebook.com/amwrapsusa/reels',
    'https://www.facebook.com/amwrapsusa/',
  ]) {
    try {
      await p.goto(target, { waitUntil: 'domcontentloaded', timeout: 45000 });
      await p.waitForTimeout(4000);
      for (let i = 0; i < 8; i++) { await p.evaluate(() => window.scrollBy(0, 1200)); await p.waitForTimeout(1200); }
      const pageVids = await p.evaluate(() => Array.from(document.querySelectorAll('video')).map(v => v.src || v.currentSrc).filter(Boolean));
      pageVids.forEach(v => vids.set(v.split('?')[0], v));
      console.log(target.replace('https://www.facebook.com/amwrapsusa','') || '/', '-> video urls so far:', vids.size);
    } catch (e) { console.log('fail', target, e.message.slice(0,60)); }
  }

  fs.writeFileSync(OUT + '/video_urls.json', JSON.stringify([...vids.values()], null, 2));
  console.log('TOTAL video urls:', vids.size);
  [...vids.values()].slice(0,15).forEach(v => console.log(v.slice(0,130)));
  await b.close();
})();
