const { chromium } = require('playwright-core');
(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true,
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
  });
  const page = await context.newPage();
  await page.goto('https://875051.us16.myftpupload.com/', { waitUntil: 'load', timeout: 45000 });
  await page.locator('.menu-toggle').first().click();
  await page.waitForTimeout(1000);

  const info = await page.evaluate(() => {
    const masthead = document.getElementById('masthead');
    const wrap = document.querySelector('.ast-main-header-wrap');
    const barWrap = document.querySelector('.main-header-bar-wrap');
    function dump(el) {
      if (!el) return 'NOT FOUND';
      return { style: el.getAttribute('style'), cls: el.className, rect: el.getBoundingClientRect() };
    }
    return {
      masthead: dump(masthead),
      wrap: dump(wrap),
      barWrap: dump(barWrap),
      bodyHasNavOpen: document.body.className.includes('ast-main-header-nav-open'),
    };
  });
  console.log(JSON.stringify(info, null, 2));
  await browser.close();
})().catch(e => { console.error('FAILED:', e.message); process.exit(1); });
