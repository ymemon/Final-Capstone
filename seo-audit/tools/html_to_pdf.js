const { chromium } = require('playwright-core');
const path = require('path');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';

const SRC = process.argv[2];
const OUT = process.argv[3];
const FOOTER_TEXT = process.argv[4] || 'AZ Web Corp';

(async () => {
  const browser = await chromium.launch({ executablePath: CHROME, headless: true });
  const page = await browser.newPage();
  await page.goto('file:///' + SRC.replace(/\\/g, '/'), { waitUntil: 'networkidle' });
  await page.pdf({
    path: OUT,
    format: 'A4',
    printBackground: true,
    displayHeaderFooter: true,
    headerTemplate: '<div></div>',
    footerTemplate: `<div style="width:100%;font-size:7.5pt;color:#8a939c;
        font-family:Segoe UI,system-ui,sans-serif;padding:0 14mm;
        display:flex;justify-content:space-between;align-items:center;">
        <span>${FOOTER_TEXT}</span>
        <span>Page <span class="pageNumber"></span> of <span class="totalPages"></span></span>
      </div>`,
    margin: { top: '17mm', bottom: '20mm', left: '14mm', right: '14mm' },
  });
  console.log('PDF written:', OUT);
  await browser.close();
})();
