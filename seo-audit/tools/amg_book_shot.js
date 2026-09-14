const { chromium } = require('playwright-core');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  const p = await b.newPage({ viewport:{width:1440,height:1000} });
  await p.goto('http://localhost:8081/book/',{waitUntil:'networkidle'});
  await p.waitForTimeout(1800);
  await p.locator('.amg-cal__cell.is-open').nth(2).click();
  await p.waitForTimeout(1600);
  await p.locator('.amg-slot').nth(3).click();
  await p.waitForTimeout(500);
  await p.evaluate(()=>window.scrollTo(0,260));
  await p.waitForTimeout(400);
  await p.screenshot({path:'C:/Users/yasir/Documents/Final-Capstone/local-sites/aandmgraphics/screenshots/booking-final.png'});
  console.log('shot done');
  await b.close();
})();
