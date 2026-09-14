const { chromium } = require('playwright-core');
(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true,
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
  });
  const page = await context.newPage();
  await page.goto('https://875051.us16.myftpupload.com/?nc=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 40000 });
  await page.waitForTimeout(1000);
  const all = await page.evaluate(() => [...document.querySelectorAll('.pv-filter-btn')].map(b => ({txt: (b.innerText||'').trim(), cls: b.className})));
  console.log('all filter btns:', JSON.stringify(all));

  const r = await page.evaluate(() => {
    const btn = [...document.querySelectorAll('.pv-filter-btn')].find(b => (b.innerText||'').trim().includes('Glendale'));
    if (!btn) return null;
    const s = getComputedStyle(btn);
    return {
      inlineStyle: btn.getAttribute('style'),
      color: s.color, bg: s.backgroundColor, bgImage: s.backgroundImage,
      matchedRules: (function(){
        try { return [...document.styleSheets].some(ss => { try { return [...ss.cssRules].some(r => r.selectorText && r.selectorText.includes('pv-filter-btn')); } catch(e){return false;} }); } catch(e){ return 'err'; }
      })(),
    };
  });
  console.log(JSON.stringify(r, null, 2));

  // Check if there's a style tag INSIDE the page (post content) that also defines pv-filter-btn, potentially loaded after the Additional CSS
  const styleTags = await page.evaluate(() => [...document.querySelectorAll('style')].map(s => s.textContent.includes('pv-filter-btn') ? s.textContent.length : null).filter(Boolean));
  console.log('style tags containing pv-filter-btn, lengths:', styleTags);

  await browser.close();
})().catch(e => { console.error('FAILED:', e.message); process.exit(1); });
