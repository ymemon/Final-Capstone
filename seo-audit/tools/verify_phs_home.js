const { chromium } = require('playwright-core');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const OUT = process.argv[2];

(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  const p = await b.newPage({ viewport: { width: 1440, height: 1000 } });
  const errs = [];
  p.on('pageerror', e => errs.push(e.message));
  p.on('response', r => {
    if (r.status() >= 400 && r.url().includes('localhost')) errs.push(r.status() + ' ' + r.url().split('/').pop());
  });

  await p.goto('http://localhost:8080/?v=' + Date.now(), { waitUntil: 'networkidle', timeout: 60000 });
  await p.waitForTimeout(2000);

  const used = await p.evaluate(() => {
    const nodes = [...document.querySelectorAll('.hero, .thumb')];
    return nodes.map(e => {
      const bg = getComputedStyle(e).backgroundImage;
      const m = bg.match(/\/([^\/"')]+\.(?:jpe?g|png|webp))/i);
      return m ? m[1] : 'NONE';
    });
  });

  console.log('images chosen:');
  used.forEach((u, i) => console.log('  slot ' + i + ': ' + u));
  console.log('slots filled: ' + used.filter(u => u !== 'NONE').length + '/' + used.length);
  console.log('errors: ' + (errs.length ? JSON.stringify(errs.slice(0, 4)) : 'none'));

  await p.screenshot({ path: OUT, fullPage: true });
  console.log('shot: ' + OUT);
  await b.close();
})();
