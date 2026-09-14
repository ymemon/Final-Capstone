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

  const info = await page.evaluate(() => {
    const toggle = document.querySelector('.menu-toggle');
    const out = { toggleClasses: toggle ? toggle.className : null, toggleHTML: toggle ? toggle.outerHTML.slice(0, 300) : null };

    // Look for likely nav/menu containers near the toggle.
    const candidates = document.querySelectorAll('nav, .nav-menu, .mobile-menu, .main-navigation, .menu, ul.nav, .nav-collapse, [class*="menu"]');
    out.candidateCount = candidates.length;
    out.candidates = Array.from(candidates).slice(0, 15).map(el => ({
      tag: el.tagName, cls: el.className, id: el.id,
      display: getComputedStyle(el).display,
      visibility: getComputedStyle(el).visibility,
      height: el.getBoundingClientRect().height,
      opacity: getComputedStyle(el).opacity,
    }));
    return out;
  });
  console.log('BEFORE CLICK:', JSON.stringify(info, null, 2));

  await page.locator('.menu-toggle').first().click();
  await page.waitForTimeout(800);

  const info2 = await page.evaluate(() => {
    const toggle = document.querySelector('.menu-toggle');
    const out = { toggleClasses: toggle ? toggle.className : null };
    const candidates = document.querySelectorAll('nav, .nav-menu, .mobile-menu, .main-navigation, .menu, ul.nav, .nav-collapse, [class*="menu"]');
    out.candidates = Array.from(candidates).slice(0, 15).map(el => ({
      tag: el.tagName, cls: el.className, id: el.id,
      display: getComputedStyle(el).display,
      visibility: getComputedStyle(el).visibility,
      height: el.getBoundingClientRect().height,
      opacity: getComputedStyle(el).opacity,
      transform: getComputedStyle(el).transform,
      zIndex: getComputedStyle(el).zIndex,
      position: getComputedStyle(el).position,
      right: getComputedStyle(el).right,
    }));
    return out;
  });
  console.log('AFTER CLICK:', JSON.stringify(info2, null, 2));

  await browser.close();
})().catch(e => { console.error('FAILED:', e.message); process.exit(1); });
