const { chromium } = require('playwright-core');

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext();
  const page = await context.newPage();

  page.on('console', msg => console.log('PAGE LOG:', msg.text()));

  await page.goto('https://azwebcorp.com/free-seo-audit/?azwc_cachebust=' + Date.now(), { waitUntil: 'load', timeout: 45000 });

  const input = page.locator('#az-premium-audit input[name="domain"]');
  await input.waitFor({ state: 'visible', timeout: 15000 });
  await input.fill('azwebcorp.com');

  const [newPage] = await Promise.all([
    context.waitForEvent('page', { timeout: 10000 }),
    page.locator('#az-audit-form button[type="submit"]').click(),
  ]);

  await newPage.waitForLoadState('load');
  console.log('NEW TAB URL:', newPage.url());

  // Wait for the report to actually render.
  const checkItem = newPage.locator('.az-check-item').first();
  await checkItem.waitFor({ state: 'visible', timeout: 30000 });
  const checkCount = await newPage.locator('.az-check-item').count();
  console.log('CHECKS RENDERED:', checkCount);

  // First check status should be a fail/warn now (severity sort).
  const firstStatus = await newPage.locator('.az-status-pill').first().innerText();
  console.log('FIRST CHECK STATUS:', firstStatus);

  const ctaText = await newPage.locator('#azwc-followup-cta').innerText().catch(() => '(not found)');
  console.log('FOLLOWUP CTA TEXT:', ctaText.slice(0, 200));

  await browser.close();
})().catch(err => {
  console.error('TEST FAILED:', err.message);
  process.exit(1);
});
