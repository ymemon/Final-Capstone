const { chromium } = require('playwright-core');
const fs = require('fs');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const OUT = 'C:/Users/yasir/Documents/Final-Capstone/local-sites/aandmgraphics/harvested/fb';

(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  const p = await b.newPage({
    viewport: { width: 1920, height: 1080 },
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
    locale: 'en-US',
  });

  const hits = new Map();
  p.on('response', (res) => {
    const u = res.url();
    if (/video-.*fbcdn|\/v\/t2\/f2\//.test(u) && /efg=/.test(u)) {
      try {
        const efg = new URL(u).searchParams.get('efg');
        const tag = JSON.parse(Buffer.from(efg, 'base64').toString('utf8')).vencode_tag || '';
        if (!/audio/.test(tag)) hits.set(u.split('?')[0], { tag, url: u });
      } catch {}
    }
  });

  await p.goto('https://www.facebook.com/amwrapsusa/videos', { waitUntil: 'domcontentloaded', timeout: 45000 });
  await p.waitForTimeout(4000);
  const links = await p.evaluate(() =>
    Array.from(new Set(Array.from(document.querySelectorAll('a[href*="/videos/"]')).map(a => a.href))).slice(0, 6));
  console.log('video permalinks:', links.length);

  for (const l of links) {
    try {
      await p.goto(l, { waitUntil: 'domcontentloaded', timeout: 40000 });
      await p.waitForTimeout(3000);
      // try to start playback so the player pulls higher-quality tracks
      await p.evaluate(() => document.querySelectorAll('video').forEach(v => { v.muted = true; v.play().catch(()=>{}); }));
      await p.waitForTimeout(7000);
      console.log('  visited', l.slice(-40), '— tracks so far:', hits.size);
    } catch (e) { console.log('  fail', e.message.slice(0, 50)); }
  }

  const out = [...hits.values()];
  fs.writeFileSync(OUT + '/video_hd.json', JSON.stringify(out, null, 2));
  const tags = {};
  out.forEach(h => tags[h.tag] = (tags[h.tag] || 0) + 1);
  console.log('\nvencode tags found:'); console.log(tags);
  await b.close();
})();
