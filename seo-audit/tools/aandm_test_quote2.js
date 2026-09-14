const { chromium } = require('playwright-core');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  const p = await b.newPage({ viewport: { width: 1440, height: 1000 } });
  await p.goto('http://localhost:8081/get-a-quote/', { waitUntil: 'networkidle' });
  await p.fill('#full_name', 'Diag Test');
  await p.fill('#phone', '480-555-0100');
  p.on('response', async (res) => {
    if (res.url().includes('admin-post.php')) {
      console.log('RESPONSE', res.status(), await res.text().catch(()=>'(no body)'));
    }
  });
  await p.click('button[type=submit]');
  await p.waitForTimeout(2000);
  console.log('URL after submit:', p.url());
  const html = await p.locator('.amg-form-status').evaluate(el => el.outerHTML);
  console.log('status outerHTML:', html);
  await b.close();
})();
