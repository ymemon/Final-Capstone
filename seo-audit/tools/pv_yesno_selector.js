const { chromium } = require('playwright-core');
(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true,
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
  });
  const page = await context.newPage();
  await page.goto('https://875051.us16.myftpupload.com/schedule/?nc=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 40000 });
  await page.waitForTimeout(1000);
  const r = await page.evaluate(() => {
    const n = [...document.querySelectorAll('*')].find(el => (el.innerText||'').trim() === 'Yes No');
    if (!n) return null;
    const s = getComputedStyle(n);
    const chain = [];
    let cur = n;
    for (let i=0;i<4 && cur;i++) { const cs=getComputedStyle(cur); chain.push({tag:cur.tagName, cls:(cur.className||'').toString(), color:cs.color}); cur=cur.parentElement; }
    return chain;
  });
  console.log(JSON.stringify(r, null, 2));
  await browser.close();
})().catch(e => { console.error('FAILED:', e.message); process.exit(1); });
