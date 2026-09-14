const { chromium } = require('playwright-core');
const fs = require('fs');
const path = require('path');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';

const OUT = 'C:/Users/yasir/Documents/Final-Capstone/local-sites/aandmgraphics/screenshots';
const PAGES = [
  ['home', 'http://localhost:8081/'],
  ['about', 'http://localhost:8081/about/'],
  ['services', 'http://localhost:8081/services/'],
  ['gallery', 'http://localhost:8081/gallery/'],
  ['quote', 'http://localhost:8081/get-a-quote/'],
  ['contact', 'http://localhost:8081/contact/'],
];

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const b = await chromium.launch({ executablePath: CHROME, headless: true });

  for (const [name, url] of PAGES) {
    const p = await b.newPage({ viewport: { width: 1440, height: 1000 } });
    await p.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
    await p.waitForTimeout(600);
    await p.screenshot({ path: path.join(OUT, `${name}-desktop.png`) });
    await p.setViewportSize({ width: 390, height: 844 });
    await p.waitForTimeout(300);
    await p.screenshot({ path: path.join(OUT, `${name}-mobile.png`) });
    await p.close();
    console.log('captured', name);
  }

  await b.close();
})();
