const { chromium } = require('../seo-audit/tools/node_modules/playwright-core');
const fs = require('fs');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const base = 'https://875051.us16.myftpupload.com';
  const shotDir = 'C:/Users/yasir/Documents/Final-Capstone/paloverde-cancer/screenshots';
  fs.mkdirSync(shotDir, { recursive: true });
  const desktop = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  const results = {};

  await desktop.goto(`${base}/?verify=${Date.now()}`, { waitUntil: 'networkidle', timeout: 90000 });
  results.carousel = await desktop.evaluate(() => ({
    images: [...document.querySelectorAll('.hero-carousel .carousel-image')].map(i => i.src),
    dots: document.querySelectorAll('.hero-carousel .dot').length,
  }));
  await desktop.locator('.hero-carousel .dot').nth(4).click();
  await desktop.waitForTimeout(800);
  const hero = desktop.locator('.hero-carousel');
  await hero.screenshot({ path: `${shotDir}/michael-confirmation-gilbert-carousel.png` });
  results.carousel.active = await desktop.locator('.hero-carousel .carousel-image.active').getAttribute('src');

  await desktop.goto(`${base}/your-team/?verify=${Date.now()}`, { waitUntil: 'networkidle', timeout: 90000 });
  results.team = await desktop.evaluate(() => ({
    cards: document.querySelectorAll('.pv-team__card').length,
    names: [...document.querySelectorAll('.pv-team__body h2')].map(e => e.textContent.trim()),
    mamaniLinks: [...document.querySelectorAll('a')].filter(a => /Mamani/i.test(a.closest('article')?.innerText || '')).map(a => a.href),
  }));
  await desktop.locator('.pv-team').screenshot({ path: `${shotDir}/michael-confirmation-team-mamani.png` });

  const locationPaths = ['estrella-location', 'glendale-location', 'scottsdale-location', 'east-valley-location'];
  results.locations = {};
  for (const slug of locationPaths) {
    await desktop.goto(`${base}/${slug}/?verify=${Date.now()}`, { waitUntil: 'networkidle', timeout: 90000 });
    const image = desktop.locator('img').filter({ has: desktop.locator('xpath=.') });
    results.locations[slug] = await desktop.evaluate(() => [...document.images]
      .filter(i => /WVO-9250|TBO-5601|SDO-2-7373|GTO-1-1488/.test(i.src))
      .map(i => ({ src: i.src, naturalWidth: i.naturalWidth, naturalHeight: i.naturalHeight })));
  }

  await desktop.goto(`${base}/pet-scan-imaging/?verify=${Date.now()}`, { waitUntil: 'networkidle', timeout: 90000 });
  results.pet = await desktop.evaluate(() => ({
    mapFrames: document.querySelectorAll('iframe[title="Map to Palo Verde PET Scan Imaging"]').length,
    mapSrc: document.querySelector('iframe[title="Map to Palo Verde PET Scan Imaging"]')?.src,
    hasAllLocationsBlock: /Our Locations\s*[—-]\s*For Your Convenience/i.test(document.body.innerText),
    addressPresent: document.body.innerText.includes('16641 N. 40th St., Phoenix, AZ 85032'),
  }));
  await desktop.locator('.pv-pet-page').screenshot({ path: `${shotDir}/michael-confirmation-pet-page.png` });

  const mobile = await browser.newPage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  results.mobile = {};
  for (const path of ['/your-team/', '/pet-scan-imaging/', '/east-valley-location/']) {
    await mobile.goto(`${base}${path}?mobile=${Date.now()}`, { waitUntil: 'networkidle', timeout: 90000 });
    results.mobile[path] = await mobile.evaluate(() => ({ viewport: innerWidth, bodyWidth: document.body.scrollWidth, documentWidth: document.documentElement.scrollWidth }));
  }

  const report = JSON.stringify(results, null, 2);
  fs.writeFileSync('C:/Users/yasir/Documents/Final-Capstone/paloverde-cancer/michael-corrections-verification.json', report + '\n', 'utf8');
  console.log(report);
  await browser.close();
})().catch(error => { console.error(error); process.exit(1); });
