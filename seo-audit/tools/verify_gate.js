const { chromium } = require('playwright-core');

const TEST_EMAIL = 'claude-verify-' + Date.now() + '@azwebcorp.com';

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext();
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

  // Wait for the teaser + ghosts to finish animating in.
  await newPage.waitForTimeout(1500);

  const realItems = await newPage.locator('.az-check-item.is-in:not(.az-ghost)').count();
  const ghostItems = await newPage.locator('.az-check-item.az-ghost.is-in').count();
  console.log('REAL ITEMS SHOWN (should be 3):', realItems);
  console.log('GHOST ITEMS SHOWN (should be 3):', ghostItems);

  const gateVisible = await newPage.locator('#az-gate').isVisible();
  console.log('GATE VISIBLE:', gateVisible);

  const ctaHiddenBefore = await newPage.locator('#azwc-followup-cta').isHidden();
  console.log('FOLLOWUP CTA HIDDEN BEFORE UNLOCK:', ctaHiddenBefore);

  // Fill and submit the gate form.
  await newPage.locator('#az-gate-name').fill('Claude Verify');
  await newPage.locator('#az-gate-email').fill(TEST_EMAIL);
  // Anti-bot elapsed check needs >=2.5s on screen.
  await newPage.waitForTimeout(2700);
  await newPage.locator('#az-gate-form button[type="submit"]').click();

  await newPage.waitForFunction(() => {
    const g = document.getElementById('az-gate');
    return g && g.hidden;
  }, { timeout: 15000 });

  await newPage.waitForTimeout(4000); // let the rest of the reveal animate in

  const totalRealItems = await newPage.locator('.az-check-item.is-in:not(.az-ghost)').count();
  console.log('TOTAL REAL ITEMS AFTER UNLOCK (should be 40):', totalRealItems);

  const remainingGhosts = await newPage.locator('.az-check-item.az-ghost').count();
  console.log('GHOST ITEMS REMAINING AFTER UNLOCK (should be 0):', remainingGhosts);

  const ctaVisibleAfter = await newPage.locator('#azwc-followup-cta').isVisible();
  console.log('FOLLOWUP CTA VISIBLE AFTER UNLOCK:', ctaVisibleAfter);

  const cookies = await context.cookies();
  const leadCookie = cookies.find(c => c.name === 'azwc_lead');
  console.log('LEAD COOKIE SET:', !!leadCookie, leadCookie ? leadCookie.value : '');

  await browser.close();
})().catch(err => {
  console.error('TEST FAILED:', err.message);
  process.exit(1);
});
