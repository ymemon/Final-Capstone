const { chromium } = require('playwright-core');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const OUT='C:/Users/yasir/Documents/Final-Capstone/local-sites/aandmgraphics/screenshots/';
(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  const p = await b.newPage({ viewport: { width: 1440, height: 1100 } });
  const errs=[];
  p.on('pageerror', e => errs.push('pageerror: '+e.message));
  p.on('console', m => { if (m.type()==='error') errs.push('console: '+m.text()); });

  await p.goto('http://localhost:8081/book/', { waitUntil: 'networkidle' });
  await p.waitForTimeout(1800);
  await p.screenshot({ path: OUT+'booking-initial.png', fullPage: true });

  const openDays = await p.locator('.amg-cal__cell.is-open').count();
  console.log('selectable days this month:', openDays);

  // pick the 2nd available day (avoids today's partially-consumed day)
  await p.locator('.amg-cal__cell.is-open').nth(1).click();
  await p.waitForTimeout(1500);
  const slotCount = await p.locator('.amg-slot').count();
  const chosenDate = await p.locator('.amg-cal__cell.is-selected').getAttribute('data-date');
  console.log('date picked:', chosenDate, '| slots offered:', slotCount);

  await p.locator('.amg-slot').first().click();
  await p.waitForTimeout(400);
  const slotVal = await p.locator('#amg-booking-form input[name="slot"]').inputValue();
  console.log('slot selected:', slotVal);
  const summary = await p.locator('[data-summary-text]').textContent();
  console.log('summary shows:', summary);

  await p.fill('#bk_name', 'Dana Booking-Test');
  await p.fill('#bk_phone', '480-555-0142');
  await p.fill('#bk_email', 'dana@example.com');
  await p.selectOption('#bk_topic', 'Fleet Graphics');
  await p.fill('#bk_vehicle', '2024 Ram ProMaster');
  await p.fill('#bk_notes', 'Automated booking test.');
  await p.screenshot({ path: OUT+'booking-filled.png', fullPage: true });

  await p.click('[data-submit]');
  await p.waitForTimeout(2600);
  const status = await p.locator('[data-status]').textContent();
  const statusClass = await p.locator('[data-status]').getAttribute('class');
  console.log('RESULT class:', statusClass);
  console.log('RESULT text:', status.trim());
  await p.screenshot({ path: OUT+'booking-confirmed.png', fullPage: false });

  console.log('--- js errors ---'); errs.forEach(e=>console.log(e));
  console.log('BOOKED_SLOT='+slotVal);
  await b.close();
})();
