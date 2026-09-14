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

  const before = await page.evaluate(() => {
    const el = document.getElementById('ast-mobile-site-navigation');
    if (!el) return 'NOT FOUND';
    const cs = getComputedStyle(el);
    const rect = el.getBoundingClientRect();
    return {
      display: cs.display, visibility: cs.visibility, opacity: cs.opacity,
      maxHeight: cs.maxHeight, height: cs.height, overflow: cs.overflow,
      position: cs.position, top: cs.top, left: cs.left, transform: cs.transform,
      rect: { w: rect.width, h: rect.height, x: rect.x, y: rect.y },
      parentDisplay: el.parentElement ? getComputedStyle(el.parentElement).display : null,
      parentCls: el.parentElement ? el.parentElement.className : null,
    };
  });
  console.log('BEFORE:', JSON.stringify(before, null, 2));

  await page.locator('.menu-toggle').first().click();
  await page.waitForTimeout(800);

  const after = await page.evaluate(() => {
    const el = document.getElementById('ast-mobile-site-navigation');
    if (!el) return 'NOT FOUND';
    const cs = getComputedStyle(el);
    const rect = el.getBoundingClientRect();
    return {
      display: cs.display, visibility: cs.visibility, opacity: cs.opacity,
      maxHeight: cs.maxHeight, height: cs.height, overflow: cs.overflow,
      position: cs.position, top: cs.top, left: cs.left, transform: cs.transform,
      rect: { w: rect.width, h: rect.height, x: rect.x, y: rect.y },
      innerHTMLLength: el.innerHTML.length,
      innerHTMLSnippet: el.innerHTML.slice(0, 400),
    };
  });
  console.log('AFTER:', JSON.stringify(after, null, 2));

  await page.screenshot({ path: 'C:\\Users\\yasir\\Documents\\Final-Capstone\\seo-audit\\tools\\pv_after2.png' });

  await browser.close();
})().catch(e => { console.error('FAILED:', e.message); process.exit(1); });
