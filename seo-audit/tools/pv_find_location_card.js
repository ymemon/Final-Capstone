const { chromium } = require('playwright-core');
(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true,
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
  });
  const page = await context.newPage();
  await page.goto('https://875051.us16.myftpupload.com/?nc=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 40000 });
  await page.waitForTimeout(1000);
  const r = await page.evaluate(() => {
    const all = [...document.querySelectorAll('*')].filter(n => (n.innerText || '').trim() === 'Glendale' && !n.className.toString().includes('pv-filter-btn'));
    return all.map(n => {
      const rc = n.getBoundingClientRect();
      const s = getComputedStyle(n);
      return {
        tag: n.tagName, cls: (n.className||'').toString(), color: s.color, bg: s.backgroundColor, bgImage: s.backgroundImage.slice(0,60),
        parentCls: n.parentElement?(n.parentElement.className||'').toString():'', parentTag: n.parentElement?n.parentElement.tagName:'',
        y: Math.round(rc.top + window.scrollY),
      };
    });
  });
  console.log(JSON.stringify(r, null, 2));

  const btn = await page.evaluate(() => {
    const b = [...document.querySelectorAll('.pv-doctors-btn')][0];
    if (!b) return null;
    const s = getComputedStyle(b);
    const rc = b.getBoundingClientRect();
    return { color: s.color, bg: s.backgroundColor, bgImage: s.backgroundImage.slice(0,80), y: Math.round(rc.top+window.scrollY) };
  });
  console.log('doctors-btn:', JSON.stringify(btn));

  await browser.close();
})().catch(e => { console.error('FAILED:', e.message); process.exit(1); });
