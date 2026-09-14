const { chromium } = require('playwright-core');
const EXE = 'C:/Users/yasir/AppData/Local/ms-playwright/chromium-1234/chrome-win64/chrome.exe';

const URL = process.argv[2];
const OUT = process.argv[3] || 'dashboard-shot.png';

(async () => {
  const browser = await chromium.launch({ executablePath: EXE });
  const page = await browser.newPage({ viewport: { width: 1280, height: 1000 } });
  const errors = [];
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  page.on('pageerror', e => errors.push('PAGEERROR: ' + e.message));

  await page.goto(URL, { waitUntil: 'networkidle', timeout: 60000 });
  await page.waitForTimeout(1200);

  const stats = await page.evaluate(() => ({
    kpis: document.querySelectorAll('.kpi').length,
    kpiValues: [...document.querySelectorAll('.kpi .value')].map(e => e.textContent),
    clicksPath: !!document.querySelector('#chart-clicks path.line-path'),
    imprPath: !!document.querySelector('#chart-impr path.line-path'),
    queryRows: document.querySelectorAll('#tbl-queries tbody tr').length,
    pageRows: document.querySelectorAll('#tbl-pages-gsc tbody tr').length,
    deviceBars: document.querySelectorAll('#device-bars .bar-row').length,
    ga4Bars: document.querySelectorAll('#ga4-bars .bar-row').length,
    channelBars: document.querySelectorAll('#channel-bars .bar-row').length,
    ga4Note: (document.querySelector('#ga4-note') || {}).textContent,
    synced: (document.querySelector('#synced-meta') || {}).textContent,
    scrollW: document.documentElement.scrollWidth,
    clientW: document.documentElement.clientWidth,
  }));

  console.log(JSON.stringify(stats, null, 2));
  console.log('CONSOLE ERRORS:', errors.length ? errors : 'none');
  console.log('H-OVERFLOW:', stats.scrollW > stats.clientW ? 'YES (' + stats.scrollW + '>' + stats.clientW + ')' : 'no');

  await page.screenshot({ path: OUT, fullPage: true });
  console.log('SHOT', OUT);
  await browser.close();
})();
