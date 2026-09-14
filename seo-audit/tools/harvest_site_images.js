const { chromium } = require('playwright-core');
const fs = require('fs');
const path = require('path');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';

const OUT = process.argv[2];
const PAGES = process.argv.slice(3);

const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
         + '(KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch({ executablePath: CHROME, headless: true });
  const ctx = await browser.newContext({ userAgent: UA, viewport: { width: 1600, height: 1200 } });

  const found = new Map();   // url -> {pages:Set, alt}

  for (const url of PAGES) {
    const p = await ctx.newPage();
    try {
      await p.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
      await p.waitForTimeout(1800);
      // Trigger lazy-loaded imagery.
      await p.evaluate(async () => {
        for (let y = 0; y < document.body.scrollHeight; y += 700) {
          window.scrollTo(0, y);
          await new Promise(r => setTimeout(r, 120));
        }
        window.scrollTo(0, 0);
      });
      await p.waitForTimeout(1200);

      const imgs = await p.evaluate(() => {
        const out = [];
        for (const el of document.querySelectorAll('img')) {
          const src = el.currentSrc || el.src;
          if (src) out.push({ src, alt: el.alt || '', w: el.naturalWidth, h: el.naturalHeight });
        }
        // CSS background images carry the hero art on most templates.
        for (const el of document.querySelectorAll('*')) {
          const bg = getComputedStyle(el).backgroundImage;
          const m = bg && bg.match(/url\(["']?(https?:[^"')]+)/);
          if (m) out.push({ src: m[1], alt: '(css background)', w: 0, h: 0 });
        }
        return out;
      });

      for (const im of imgs) {
        if (!/^https?:/.test(im.src)) continue;
        if (/\.svg($|\?)/i.test(im.src)) continue;
        if (im.w && im.w < 120 && im.h && im.h < 120) continue;  // icons, pixels
        if (!found.has(im.src)) found.set(im.src, { pages: new Set(), alt: im.alt, w: im.w, h: im.h });
        found.get(im.src).pages.add(url);
      }
      console.log(`scanned ${url}  (+${imgs.length} refs)`);
    } catch (e) {
      console.log(`FAILED  ${url}: ${String(e.message).slice(0, 80)}`);
    }
    await p.close();
  }

  console.log(`\n${found.size} unique images. Downloading...`);
  const manifest = [];
  let n = 0, ok = 0;
  for (const [src, meta] of found) {
    n++;
    let host = 'unknown';
    try { host = new URL(src).hostname; } catch {}
    const clean = src.split('?')[0];
    let base = path.basename(clean) || `image-${n}`;
    if (!/\.(jpe?g|png|webp|gif|avif)$/i.test(base)) base += '.jpg';
    base = String(n).padStart(3, '0') + '-' + base.replace(/[^a-zA-Z0-9._-]/g, '_').slice(-70);

    try {
      const res = await ctx.request.get(src, { timeout: 45000 });
      if (res.status() === 200) {
        const buf = await res.body();
        if (buf.length > 3000) {                    // skip trackers / spacers
          fs.writeFileSync(path.join(OUT, base), buf);
          ok++;
          manifest.push({ file: base, source: src, host, alt: meta.alt,
                          bytes: buf.length, natural: `${meta.w}x${meta.h}`,
                          usedOn: [...meta.pages] });
        }
      }
    } catch { /* recorded by omission */ }
  }

  fs.writeFileSync(path.join(OUT, '_manifest.json'), JSON.stringify(manifest, null, 2));
  const byHost = {};
  for (const m of manifest) byHost[m.host] = (byHost[m.host] || 0) + 1;
  console.log(`downloaded ${ok}/${found.size}`);
  console.log('by host:', JSON.stringify(byHost, null, 1));
  console.log('manifest: _manifest.json');
  await browser.close();
})();
