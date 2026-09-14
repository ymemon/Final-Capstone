const { chromium } = require('playwright-core');
const EXE = 'C:/Users/yasir/AppData/Local/ms-playwright/chromium-1234/chrome-win64/chrome.exe';
const URL = process.argv[2], OUT = process.argv[3];
const TABS = ['dashboard', 'live', 'search', 'keywords', 'analytics', 'landing', 'opportunities', 'technical', 'gbp', 'ai', 'competitors', 'reports', 'connections'];

(async () => {
  const b = await chromium.launch({ executablePath: EXE });
  const p = await b.newPage({ viewport: { width: 1440, height: 1000 } });
  const errs = [];
  p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
  p.on('pageerror', e => errs.push('PAGEERROR: ' + e.message));

  await p.goto(URL, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await p.waitForTimeout(900);

  const results = [];
  for (const t of TABS) {
    await p.click(`#nav button[data-k="${t}"]`);
    await p.waitForTimeout(320);
    const r = await p.evaluate(() => ({
      h2: (document.querySelector('.view h2') || {}).textContent,
      cards: document.querySelectorAll('.view .card').length,
      rows: document.querySelectorAll('.view table.dt tbody tr').length,
      charts: document.querySelectorAll('.view svg.chart').length,
      bars: document.querySelectorAll('.view .brow').length,
      empty: document.querySelectorAll('.view .empty').length,
      ovf: document.documentElement.scrollWidth > document.documentElement.clientWidth,
    }));
    results.push({ tab: t, ...r });
  }
  console.table(results);
  console.log('ERRORS:', errs.length ? errs : 'none');

  await p.click('#nav button[data-k="dashboard"]');
  await p.waitForTimeout(400);
  await p.screenshot({ path: OUT, fullPage: true });

  const mob = await b.newPage({ viewport: { width: 390, height: 844 } });
  await mob.goto(URL, { waitUntil: 'domcontentloaded' });
  await mob.waitForTimeout(800);
  await mob.click('#hamb');
  await mob.waitForTimeout(400);
  await mob.screenshot({ path: OUT.replace('.png', '-mobile.png') });
  console.log('SHOTS done');
  await b.close();
})();
