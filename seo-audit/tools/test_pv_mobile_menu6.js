const { chromium } = require('playwright-core');

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 },
    isMobile: true,
    hasTouch: true,
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
  });
  const page = await context.newPage();
  await page.goto('https://875051.us16.myftpupload.com/?nocache=' + Date.now(), { waitUntil: 'load', timeout: 45000 });
  await page.locator('.menu-toggle').first().click();
  await page.waitForTimeout(800);

  const chain = await page.evaluate(() => {
    let el = document.getElementById('ast-mobile-site-navigation');
    const out = [];
    while (el) {
      const cs = getComputedStyle(el);
      const r = el.getBoundingClientRect();
      out.push({
        tag: el.tagName, id: el.id, cls: el.className,
        overflow: cs.overflow, overflowY: cs.overflowY, overflowX: cs.overflowX,
        height: cs.height, maxHeight: cs.maxHeight,
        position: cs.position, zIndex: cs.zIndex,
        rectH: r.height, rectY: r.y,
        clipsPath: cs.clipPath, clip: cs.clip,
      });
      el = el.parentElement;
    }
    return out;
  });
  console.log(JSON.stringify(chain, null, 2));

  await browser.close();
})().catch(e => { console.error('FAILED:', e.message); process.exit(1); });
