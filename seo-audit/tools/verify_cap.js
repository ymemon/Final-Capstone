const { chromium } = require('playwright-core');

const TEST_EMAIL = 'claude-cap-' + Date.now() + '@azwebcorp.com';

async function runOnce(context, n) {
  const page = await context.newPage();
  await page.goto('https://azwebcorp.com/free-seo-audit/?azwc_cachebust=' + Date.now(), { waitUntil: 'load', timeout: 45000 });
  const input = page.locator('#az-premium-audit input[name="domain"]');
  await input.waitFor({ state: 'visible', timeout: 15000 });
  await input.fill('azwebcorp.com');

  const [newPage] = await Promise.all([
    context.waitForEvent('page', { timeout: 10000 }),
    page.locator('#az-audit-form button[type="submit"]').click(),
  ]);
  await newPage.waitForLoadState('load');
  await newPage.waitForTimeout(2000);

  const gateVisible = await newPage.locator('#az-gate').isVisible().catch(() => false);
  const gateFormHidden = gateVisible ? await newPage.locator('#az-gate-form').isHidden() : null;
  const gateNote = gateVisible ? await newPage.locator('#az-gate-note').innerText().catch(() => '') : '';
  const realCount = await newPage.locator('.az-check-item.is-in:not(.az-ghost)').count();

  console.log(`RUN ${n}: gateVisible=${gateVisible} formHidden=${gateFormHidden} realItems=${realCount} note="${gateNote.slice(0,80)}"`);

  await page.close();
  await newPage.close();
}

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext();

  // First run: register fresh (uses 1 of 3).
  const page = await context.newPage();
  await page.goto('https://azwebcorp.com/free-seo-audit/?azwc_cachebust=' + Date.now(), { waitUntil: 'load', timeout: 45000 });
  const input = page.locator('#az-premium-audit input[name="domain"]');
  await input.waitFor({ state: 'visible', timeout: 15000 });
  await input.fill('azwebcorp.com');
  const [newPage] = await Promise.all([
    context.waitForEvent('page', { timeout: 10000 }),
    page.locator('#az-audit-form button[type="submit"]').click(),
  ]);
  await newPage.waitForLoadState('load');
  await newPage.waitForTimeout(1500);
  await newPage.locator('#az-gate-name').fill('Cap Test');
  await newPage.locator('#az-gate-email').fill(TEST_EMAIL);
  await newPage.waitForTimeout(2700);
  await newPage.locator('#az-gate-form button[type="submit"]').click();
  await newPage.waitForFunction(() => { const g = document.getElementById('az-gate'); return g && g.hidden; }, { timeout: 15000 });
  console.log('RUN 1 (register): unlocked, uses 1 of 3');
  await page.close();
  await newPage.close();

  // Runs 2-4 reuse the cookie automatically (same context = same cookies).
  await runOnce(context, 2); // should auto-unlock silently (use 2 of 3)
  await runOnce(context, 3); // should auto-unlock silently (use 3 of 3)
  await runOnce(context, 4); // should be capped now

  await browser.close();
})().catch(err => {
  console.error('TEST FAILED:', err.message);
  process.exit(1);
});
