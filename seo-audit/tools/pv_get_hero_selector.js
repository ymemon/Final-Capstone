const { chromium } = require('playwright-core');
(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true,
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
  });
  const page = await context.newPage();
  await page.goto('https://875051.us16.myftpupload.com/east-valley-location/?nc=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 40000 });
  await page.waitForTimeout(1000);
  const r = await page.evaluate(() => {
    const all = [...document.querySelectorAll('*')].filter(el => (el.innerText || '').trim().startsWith('Palo Verde Cancer Center'));
    const n = all.sort((a,b) => a.innerText.length - b.innerText.length)[0];
    if (!n) return null;
    const s = getComputedStyle(n);
    return { tag: n.tagName, id: n.id, cls: (n.className||'').toString(), color: s.color, parentCls: n.parentElement?(n.parentElement.className||'').toString():'', parentTag: n.parentElement?n.parentElement.tagName:'' };
  });
  console.log(JSON.stringify(r));
  await browser.close();
})().catch(e => { console.error('FAILED:', e.message); process.exit(1); });
