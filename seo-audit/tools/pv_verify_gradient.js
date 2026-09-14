const { chromium } = require('playwright-core');

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true,
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
  });
  const page = await context.newPage();
  await page.goto('https://875051.us16.myftpupload.com/about-us/?nocache=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 40000 });
  await page.waitForTimeout(1200);

  const info = await page.evaluate(() => {
    const els = [...document.querySelectorAll('*')].filter(n => (n.innerText || '').trim() === 'Our Mission');
    if (!els.length) return { found: false };
    const n = els[0];
    let chain = [];
    let cur = n;
    for (let i = 0; i < 6 && cur; i++) {
      const s = getComputedStyle(cur);
      chain.push({ tag: cur.tagName, cls: (cur.className || '').toString().slice(0, 60), bg: s.backgroundColor, bgImage: s.backgroundImage.slice(0, 80), color: s.color });
      cur = cur.parentElement;
    }
    return { found: true, chain };
  });
  console.log(JSON.stringify(info, null, 2));

  await page.screenshot({ path: 'pv_about_mission.png', clip: { x: 0, y: 2700, width: 390, height: 300 } });
  await browser.close();
})().catch(e => { console.error('FAILED:', e.message); process.exit(1); });
