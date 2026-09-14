const { chromium } = require('playwright-core');
const EXE = 'C:/Users/yasir/AppData/Local/ms-playwright/chromium-1234/chrome-win64/chrome.exe';
const [url, outdir] = process.argv.slice(2);

(async () => {
  const b = await chromium.launch({ executablePath: EXE });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 } });
  const p = await ctx.newPage();
  await p.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
  const dl = Date.now() + 200000;
  while (Date.now() < dl) {
    if (await p.evaluate(() => !!document.querySelector('.az-nav-item'))) break;
    await p.waitForTimeout(3000);
  }
  await p.waitForTimeout(2500);

  const r = await p.evaluate(() => {
    const txt = s => { const e = document.querySelector(s); return e ? e.textContent.trim() : null; };
    const legend = [...document.querySelectorAll('.az-legend div')].map(d => d.textContent.trim());
    const tiles = [...document.querySelectorAll('.az-tile')].map(d => d.textContent.trim());
    const towers = [...document.querySelectorAll('.az-tower')].map(d => d.querySelector('.pct').textContent.trim() + ' ' + d.querySelector('.lb').textContent.trim());
    const nav = [...document.querySelectorAll('.az-nav-item')].map(d => d.textContent.trim());
    return {
      legend, tiles, towers, nav,
      towerHead: txt('.az-towers-card .az-panel-head .count'),
      nomeasure: txt('.az-nomeasure') ? txt('.az-nomeasure').slice(0, 150) : null,
      donut: txt('.az-donut-mid b'),
      chip: txt('.az-rep-chip')
    };
  });
  console.log('donut       :', r.donut, '| chip:', r.chip);
  console.log('pie legend  :', r.legend.join(' | '));
  console.log('tiles       :', r.tiles.join(' | '));
  console.log('tower head  :', r.towerHead);
  console.log('towers      :', r.towers.join(' | '));
  console.log('nav         :', r.nav.join(' | '));
  console.log('no-measure  :', r.nomeasure);

  // wait for CWV then capture it
  const dl2 = Date.now() + 200000;
  let cwv = false;
  while (Date.now() < dl2) {
    cwv = await p.evaluate(() => { const c = document.querySelector('#az-cwv-card'); return !!(c && !c.hidden && c.querySelector('.az-cwv-verdict')); });
    if (cwv) break;
    await p.waitForTimeout(4000);
  }
  console.log('cwv bands   :', cwv);
  if (cwv) {
    const v = await p.evaluate(() => [...document.querySelectorAll('.az-cwv-item')].map(i => i.textContent.replace(/\s+/g, ' ').trim()));
    v.forEach(x => console.log('   ', x));
    const el = await p.$('#az-cwv-card');
    await el.scrollIntoViewIfNeeded(); await p.waitForTimeout(500);
    await el.screenshot({ path: `${outdir}/num_cwv.png` });
  }
  const nm = await p.$('.az-nomeasure');
  if (nm) { await nm.scrollIntoViewIfNeeded(); await p.waitForTimeout(400); await nm.screenshot({ path: `${outdir}/num_nomeasure.png` }); }
  await b.close();
})();
