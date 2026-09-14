const { chromium } = require('playwright-core');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  const p = await b.newPage({ viewport: { width: 1440, height: 1100 } });
  p.on('response', async r => {
    if (r.url().includes('admin-ajax.php') && r.request().method()==='POST') {
      const t = r.request().timing();
      console.log('POST admin-ajax status', r.status(), '| responseEnd ms:', Math.round(t.responseEnd - t.requestStart));
    }
  });
  await p.goto('http://localhost:8081/book/', { waitUntil: 'networkidle' });
  await p.waitForTimeout(1500);
  await p.locator('.amg-cal__cell.is-open').nth(2).click();
  await p.waitForTimeout(1500);
  await p.locator('.amg-slot').first().click();
  await p.fill('#bk_name','Timing Test'); await p.fill('#bk_phone','4805550199');
  const t0 = Date.now();
  await p.click('[data-submit]');
  // wait until status resolves, up to 30s
  try {
    await p.waitForFunction(() => {
      const el = document.querySelector('[data-status]');
      return el && /is-(success|error)/.test(el.className);
    }, { timeout: 30000 });
    console.log('status resolved after', Date.now()-t0, 'ms');
  } catch(e) { console.log('status never resolved within 30s'); }
  console.log('class:', await p.locator('[data-status]').getAttribute('class'));
  console.log('text :', (await p.locator('[data-status]').textContent()).trim());
  await b.close();
})();
