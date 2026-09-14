const { chromium } = require('playwright-core');

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 },
    isMobile: true,
    hasTouch: true,
    deviceScaleFactor: 2,
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
  });
  const page = await context.newPage();

  const logs = [];
  page.on('console', msg => logs.push(msg.text()));
  page.on('pageerror', err => logs.push('PAGE ERROR: ' + err.message));

  await page.goto('https://875051.us16.myftpupload.com/?nocache=' + Date.now(), { waitUntil: 'load', timeout: 45000 });
  console.log('innerWidth:', await page.evaluate(() => window.innerWidth));

  // Find a hamburger/menu toggle button - try common selectors.
  const candidates = [
    '.hamburger', '.menu-toggle', '.mobile-menu-toggle', '[aria-label*="menu" i]',
    'button.menu-icon', '.nav-toggle', '.elementor-menu-toggle', '.mobile-nav-toggle'
  ];
  let toggle = null;
  let usedSelector = null;
  for (const sel of candidates) {
    const el = page.locator(sel).first();
    if (await el.count() > 0) {
      toggle = el;
      usedSelector = sel;
      break;
    }
  }

  if (!toggle) {
    console.log('NO TOGGLE FOUND among candidate selectors:', candidates.join(', '));
    await page.screenshot({ path: 'C:\\Users\\yasir\\Documents\\Final-Capstone\\paloverde-cancer\\mobile_menu_before.png', fullPage: false });
    await browser.close();
    return;
  }

  console.log('Found toggle via selector:', usedSelector);
  await page.screenshot({ path: 'C:\\Users\\yasir\\Documents\\Final-Capstone\\paloverde-cancer\\mobile_menu_before.png', fullPage: false });

  await toggle.click({ timeout: 5000 }).catch(e => console.log('CLICK FAILED:', e.message));
  await page.waitForTimeout(1000);

  await page.screenshot({ path: 'C:\\Users\\yasir\\Documents\\Final-Capstone\\paloverde-cancer\\mobile_menu_after.png', fullPage: false });

  console.log('Console/page logs:', JSON.stringify(logs.slice(0, 20)));

  await browser.close();
})().catch(e => { console.error('FAILED:', e.message); process.exit(1); });
