const { chromium } = require('playwright-core');
const fs = require('fs');
const path = require('path');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';

const OUT = process.argv[2];
const PAGES = process.argv.slice(3);

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const b = await chromium.launch({ executablePath: CHROME, headless: true });

  const design = {};
  for (const url of PAGES) {
    const slug = (new URL(url).pathname.replace(/\//g, '_') || '_home').replace(/^_|_$/g, '') || 'home';
    // CloudFront in front of this site 403s headless Chrome's default UA
    // ("HeadlessChrome"), returning an error page that screenshots cleanly and
    // looks like a successful capture. Present a normal desktop Chrome UA.
    const p = await b.newPage({
      viewport: { width: 1440, height: 1000 },
      userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
               + '(KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
      locale: 'en-US',
    });
    try {
      await p.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
      await p.waitForTimeout(3000);
      await p.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
      await p.waitForTimeout(1200);
      await p.evaluate(() => window.scrollTo(0, 0));
      await p.waitForTimeout(800);

      // Fail loudly rather than silently screenshotting an error page.
      const blocked = await p.evaluate(() =>
        /403 ERROR|could not be satisfied|Access Denied/i.test(document.body.innerText.slice(0, 400)));
      if (blocked) {
        console.log(`BLOCKED ${url} - got an edge error page, not the site`);
        await p.close();
        continue;
      }

      await p.screenshot({ path: path.join(OUT, `${slug}.png`), fullPage: true });

      // Pull the design tokens actually in use, so the rebuild matches rather than approximates.
      const tokens = await p.evaluate(() => {
        const seen = { colors: {}, fonts: {}, sizes: {} };
        const bump = (o, k) => { if (k) o[k] = (o[k] || 0) + 1; };
        for (const el of [...document.querySelectorAll('*')].slice(0, 2500)) {
          const cs = getComputedStyle(el);
          bump(seen.colors, cs.color);
          if (cs.backgroundColor && cs.backgroundColor !== 'rgba(0, 0, 0, 0)') bump(seen.colors, cs.backgroundColor);
          bump(seen.fonts, cs.fontFamily);
          if (/^(H1|H2|H3|P|A|BUTTON)$/.test(el.tagName)) bump(seen.sizes, el.tagName + ':' + cs.fontSize + '/' + cs.fontWeight);
        }
        const top = (o, n) => Object.entries(o).sort((a, b) => b[1] - a[1]).slice(0, n);
        return {
          title: document.title,
          h1: [...document.querySelectorAll('h1')].map(h => h.innerText.trim()).slice(0, 3),
          nav: [...document.querySelectorAll('nav a, header a')].map(a => a.innerText.trim()).filter(Boolean).slice(0, 20),
          colors: top(seen.colors, 12),
          fonts: top(seen.fonts, 5),
          type: top(seen.sizes, 12),
        };
      });
      design[slug] = { url, ...tokens };
      console.log(`captured ${slug}  <- ${url}`);
    } catch (e) {
      console.log(`FAILED  ${url}: ${String(e.message).slice(0, 90)}`);
    }
    await p.close();
  }

  fs.writeFileSync(path.join(OUT, 'design-tokens.json'), JSON.stringify(design, null, 2));
  console.log('wrote design-tokens.json');
  await b.close();
})();
