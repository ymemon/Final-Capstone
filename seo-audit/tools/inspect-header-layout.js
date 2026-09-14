const { chromium } = require('playwright-core');

const executablePath = 'C:/Users/yasir/AppData/Local/ms-playwright/chromium-1234/chrome-win64/chrome.exe';
const url = process.argv[2] || 'https://azwebcorp.com/seo/';

(async () => {
  const browser = await chromium.launch({ executablePath });
  for (const width of [992, 1024, 1100, 1199, 1200, 1280, 1366, 1440, 1536, 1880]) {
    const page = await browser.newPage({ viewport: { width, height: 900 } });
    await page.goto(`${url}?header-qa=${Date.now()}`, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForTimeout(1800);
    if (process.argv.includes('--candidate')) await page.addStyleTag({ content: `
      @media (min-width: 992px) {
        body header#masthead .bottom-header .row > .col-lg-3 { flex: 0 0 20%; max-width: 20%; }
        body header#masthead .bottom-header .row > .col-lg-9 { flex: 0 0 80%; max-width: 80%; }
        body header#masthead #primary-menu > li { white-space: nowrap; }
        body header#masthead #primary-menu > li > a { padding-right: 12px !important; }
      }
      @media (min-width: 992px) and (max-width: 1199px) {
        body header#masthead #primary-menu > li > a { font-size: 12px !important; padding-right: 4px !important; }
        body header#masthead .header-btn-1.button-primary { font-size: 12px !important; padding-left: 12px !important; padding-right: 12px !important; }
      }
    ` });
    const data = await page.evaluate(() => {
      const rect = (selector) => {
        const element = document.querySelector(selector);
        if (!element) return null;
        const box = element.getBoundingClientRect();
        const style = getComputedStyle(element);
        return {
          x: Math.round(box.x), y: Math.round(box.y), width: Math.round(box.width), height: Math.round(box.height),
          display: style.display, flexWrap: style.flexWrap, gap: style.gap,
        };
      };
      const items = [...document.querySelectorAll('#primary-menu > li')].map((item) => {
        const box = item.getBoundingClientRect();
        const linkStyle = item.firstElementChild ? getComputedStyle(item.firstElementChild) : null;
        return {
          name: item.firstElementChild?.textContent.trim(), x: Math.round(box.x), y: Math.round(box.y), width: Math.round(box.width), height: Math.round(box.height),
          fontSize: linkStyle?.fontSize, paddingLeft: linkStyle?.paddingLeft, paddingRight: linkStyle?.paddingRight,
        };
      });
      return {
        container: rect('#masthead .bottom-header .container'),
        row: rect('#masthead .bottom-header .row'),
        logoColumn: rect('#masthead .bottom-header .col-lg-3'),
        navColumn: rect('#masthead .bottom-header .col-lg-9'),
        navWrap: rect('#masthead .main-navigation-wrap'),
        nav: rect('#masthead .main-navigation'),
        menu: rect('#primary-menu'),
        chat: rect('#masthead .header-btn'),
        items,
        rows: [...new Set(items.map((item) => item.y))],
      };
    });
    console.log(JSON.stringify({ width, ...data }));
    await page.close();
  }
  await browser.close();
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
