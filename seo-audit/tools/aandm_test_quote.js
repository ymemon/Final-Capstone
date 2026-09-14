const { chromium } = require('playwright-core');
const path = require('path');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';

(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  const p = await b.newPage({ viewport: { width: 1440, height: 1000 } });
  const logs = [];
  p.on('console', msg => logs.push(`[console] ${msg.type()}: ${msg.text()}`));
  p.on('pageerror', err => logs.push(`[pageerror] ${err.message}`));

  await p.goto('http://localhost:8081/get-a-quote/', { waitUntil: 'networkidle' });

  await p.fill('#full_name', 'Jordan Test');
  await p.fill('#company', 'Test Fleet Co');
  await p.fill('#phone', '480-555-0199');
  await p.fill('#email', 'jordan@example.com');
  await p.fill('#vehicle', '2023 Ford Transit');
  await p.selectOption('#wrap_type', 'Fleet Graphics');
  await p.fill('#description', 'Automated test submission — please ignore. Testing the file drop feature end to end.');

  // Attach a real local test file via the hidden file input.
  const testFile = path.join(__dirname, 'aandm_test_upload.png');
  const fs = require('fs');
  if (!fs.existsSync(testFile)) {
    // 1x1 png
    const buf = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', 'base64');
    fs.writeFileSync(testFile, buf);
  }
  await p.setInputFiles('input[type=file]', testFile);
  await p.waitForTimeout(300);

  const chipCount = await p.locator('.amg-file-chip').count();
  console.log('file chips shown:', chipCount);

  await p.click('button[type=submit]');
  await p.waitForTimeout(2500);

  const statusText = await p.locator('.amg-form-status').textContent();
  const statusClass = await p.locator('.amg-form-status').getAttribute('class');
  console.log('status class:', statusClass);
  console.log('status text:', statusText);
  console.log('---page logs---');
  logs.forEach(l => console.log(l));

  await p.screenshot({ path: 'C:/Users/yasir/Documents/Final-Capstone/local-sites/aandmgraphics/screenshots/quote-after-submit.png', fullPage: false });

  await b.close();
})();
