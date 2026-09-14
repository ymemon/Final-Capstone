const { chromium } = require('playwright-core');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
  p.on('response', r => { if (r.status() >= 400) console.log(r.status(), r.url()); });
  for (const u of ['http://localhost:8081/','http://localhost:8081/get-a-quote/','http://localhost:8081/gallery/']) {
    console.log('--- ' + u);
    await p.goto(u, { waitUntil: 'networkidle' });
    await p.waitForTimeout(600);
  }
  await b.close();
})();
