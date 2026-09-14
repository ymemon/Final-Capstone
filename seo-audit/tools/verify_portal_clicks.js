/*
 * Verifies the portal's click-through contract.
 *   node verify_portal_clicks.js <baseUrl>
 * baseUrl may be a file:/// path or an http(s) URL.
 *
 * Checks the things that actually break: that cards are real anchors opening a
 * new tab, that the deep link lands on the right view, and that a real click
 * genuinely spawns a second tab rather than navigating in place.
 */
const { chromium } = require('playwright-core');
const EXE = 'C:/Users/yasir/AppData/Local/ms-playwright/chromium-1234/chrome-win64/chrome.exe';
const BASE = process.argv[2];
const sep = BASE.includes('?') ? '&' : '?';

(async () => {
  const b = await chromium.launch({ executablePath: EXE });
  const ctx = await b.newContext({ viewport: { width: 1600, height: 1000 } });
  const p = await ctx.newPage();
  const errs = [];
  p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
  p.on('pageerror', e => errs.push('PAGEERROR: ' + e.message));

  await p.goto(BASE, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await p.waitForTimeout(1200);

  const info = await p.evaluate(() => ({
    linkedCards: document.querySelectorAll('a.card.linked').length,
    panelLinks: document.querySelectorAll('a.popen').length,
    blank: document.querySelectorAll('a[target="_blank"]').length,
    noopener: document.querySelectorAll('a[target="_blank"][rel~="noopener"]').length,
    hrefs: Array.from(document.querySelectorAll('a.card.linked')).map(a => a.getAttribute('href')),
    overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth,
  }));
  console.log('linked KPI cards :', info.linkedCards);
  console.log('panel open links :', info.panelLinks);
  console.log('target=_blank    :', info.blank, '(with rel=noopener:', info.noopener + ')');
  console.log('kpi hrefs        :', info.hrefs.join(', '));
  console.log('h-overflow       :', info.overflow);

  // A real click must open a SECOND tab, not navigate this one.
  const [popup] = await Promise.all([
    ctx.waitForEvent('page', { timeout: 8000 }).catch(() => null),
    p.locator('a.card.linked').first().click(),
  ]);
  if (!popup) {
    console.log('NEW TAB          : FAILED - no second tab opened');
  } else {
    await popup.waitForTimeout(900);
    const view = await popup.evaluate(() => {
      const h = document.querySelector('.view h2');
      return { h2: h && h.textContent.trim(), url: location.search };
    });
    console.log('NEW TAB          : opened', view.url, '-> view heading:', view.h2);
    await popup.close();
  }

  // Deep link must land on the requested view, not the dashboard.
  const p2 = await ctx.newPage();
  await p2.goto(BASE + sep + 'view=keywords', { waitUntil: 'domcontentloaded' });
  await p2.waitForTimeout(1000);
  const h2 = await p2.evaluate(() => {
    const h = document.querySelector('.view h2');
    return h && h.textContent.trim();
  });
  console.log('deep ?view=keywords ->', h2, h2 === 'Keywords' ? '(PASS)' : '(FAIL)');

  // A bad key must fall back, not blow up.
  const p3 = await ctx.newPage();
  await p3.goto(BASE + sep + 'view=nonsense', { waitUntil: 'domcontentloaded' });
  await p3.waitForTimeout(900);
  const h3 = await p3.evaluate(() => {
    const h = document.querySelector('.view h2');
    return h && h.textContent.trim();
  });
  const fellBack = h3 && /dashboard/i.test(h3);
  console.log('deep ?view=nonsense ->', h3, fellBack ? '(PASS, fell back)' : '(FAIL)');

  console.log('CONSOLE ERRORS   :', errs.length ? errs.slice(0, 5) : 'none');
  await b.close();
})().catch(e => { console.error('TEST FAILED:', e.message); process.exit(1); });
