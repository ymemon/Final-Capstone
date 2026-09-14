const { chromium } = require('playwright-core');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  for (const [label, w, h] of [['mobile',390,844],['desktop',1440,1000]]) {
    const p = await b.newPage({ viewport: { width: w, height: h } });
    for (const u of ['http://localhost:8081/gallery/','http://localhost:8081/','http://localhost:8081/services/']) {
      await p.goto(u, { waitUntil: 'networkidle' });
      // simulate a real user scrolling through
      for (let i = 0; i < 40; i++) { await p.evaluate(() => window.scrollBy(0, 600)); await p.waitForTimeout(90); }
      await p.waitForTimeout(500);
      const res = await p.evaluate(() => {
        const els = Array.from(document.querySelectorAll('[data-reveal],[data-reveal-stagger]'));
        const hidden = els.filter(e => getComputedStyle(e).opacity === '0');
        return { total: els.length, hidden: hidden.length };
      });
      console.log(label, u.replace('http://localhost:8081',''), 'reveal els:', res.total, 'still hidden:', res.hidden, res.hidden ? '*** BUG ***' : 'ok');
    }
    await p.close();
  }
  await b.close();
})();
