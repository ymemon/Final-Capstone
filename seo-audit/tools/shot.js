const { chromium } = require('playwright-core');
const EXE = 'C:/Users/yasir/AppData/Local/ms-playwright/chromium-1234/chrome-win64/chrome.exe';
const URL = process.argv[2], OUT = process.argv[3];

(async () => {
  const b = await chromium.launch({ executablePath: EXE });
  const p = await b.newPage({ viewport: { width: 1600, height: 1000 } });
  const errs = [];
  p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
  p.on('pageerror', e => errs.push('PAGEERROR: ' + e.message));

  await p.goto(URL, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await p.waitForTimeout(2500);

  const info = await p.evaluate(() => {
    const t = (el) => (el ? el.textContent.trim().slice(0, 90) : null);
    return {
      title: document.title,
      h1: Array.from(document.querySelectorAll('h1')).map(e => t(e)),
      h2: Array.from(document.querySelectorAll('h2')).map(e => t(e)).slice(0, 14),
      navButtons: Array.from(document.querySelectorAll('nav button, #nav button, aside button'))
        .map(e => t(e)).slice(0, 20),
      links: Array.from(document.querySelectorAll('a[href]')).length,
      newTabLinks: Array.from(document.querySelectorAll('a[target="_blank"]')).length,
      clickableCards: Array.from(document.querySelectorAll('[data-open], .card a, .kpi a')).length,
      bodyBg: getComputedStyle(document.body).backgroundColor,
      scrollW: document.documentElement.scrollWidth,
      clientW: document.documentElement.clientWidth,
    };
  });
  console.log(JSON.stringify(info, null, 2));
  console.log('CONSOLE ERRORS:', errs.length ? errs.slice(0, 6) : 'none');

  await p.screenshot({ path: OUT, fullPage: false });
  console.log('shot saved:', OUT);
  await b.close();
})();
