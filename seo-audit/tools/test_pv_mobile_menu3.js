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

  const info = await page.evaluate(() => {
    const targets = {
      header: document.getElementById('masthead'),
      navDesktop: document.getElementById('primary-site-navigation-desktop'),
      mainNav: document.querySelector('.main-navigation'),
      ul: document.querySelector('.main-header-menu'),
      astMenuToggleBtn: document.querySelector('.ast-menu-toggle'),
      mobileHeaderBar: document.querySelector('.ast-mobile-header-inline, .ast-mobile-header'),
    };
    const out = {};
    for (const [k, el] of Object.entries(targets)) {
      if (!el) { out[k] = 'NOT FOUND'; continue; }
      const cs = getComputedStyle(el);
      out[k] = {
        cls: el.className, id: el.id,
        display: cs.display, maxHeight: cs.maxHeight, overflow: cs.overflow,
        visibility: cs.visibility, opacity: cs.opacity, height: cs.height,
        childCount: el.children.length,
      };
    }
    // Is there a SEPARATE mobile-only nav element we haven't found?
    out.allNavIds = Array.from(document.querySelectorAll('nav')).map(n => n.id || n.className);
    out.allElementsWithMobileInClass = Array.from(document.querySelectorAll('[class*="mobile" i]')).map(e => e.tagName + '.' + e.className).slice(0, 30);
    return out;
  });
  console.log(JSON.stringify(info, null, 2));

  await browser.close();
})().catch(e => { console.error('FAILED:', e.message); process.exit(1); });
