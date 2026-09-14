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
    const ul = document.getElementById('ast-hf-mobile-menu');
    const li = ul ? ul.querySelector('li') : null;
    const a = li ? li.querySelector('a') : null;
    function dump(el, name) {
      if (!el) return name + ': NOT FOUND';
      const cs = getComputedStyle(el);
      const r = el.getBoundingClientRect();
      return {
        name, tag: el.tagName, cls: el.className,
        display: cs.display, flexDirection: cs.flexDirection, flexWrap: cs.flexWrap,
        fontSize: cs.fontSize, lineHeight: cs.lineHeight, color: cs.color,
        height: cs.height, minHeight: cs.minHeight, maxHeight: cs.maxHeight,
        padding: cs.padding, margin: cs.margin, overflow: cs.overflow,
        flexBasis: cs.flexBasis, flexGrow: cs.flexGrow, flexShrink: cs.flexShrink,
        rect: { w: r.width, h: r.height },
      };
    }
    return {
      ul: dump(ul, 'ul'),
      li: dump(li, 'li'),
      a: dump(a, 'a'),
      liCount: ul ? ul.children.length : 0,
    };
  });
  console.log(JSON.stringify(info, null, 2));

  await browser.close();
})().catch(e => { console.error('FAILED:', e.message); process.exit(1); });
