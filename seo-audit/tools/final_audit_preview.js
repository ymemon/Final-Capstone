/**
 * Pre-send audit of the client preview.
 *
 * Goes beyond the status-code sweep: checks for leftover placeholder text,
 * broken images, dead internal links, empty pages and mobile layout, so the
 * only things left to explain to the client are the ones we chose to leave.
 */
const { chromium } = require('playwright-core');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const BASE = process.argv[2];

const PLACEHOLDER_PATTERNS = [
  /has not been migrated yet/i,
  /lorem ipsum/i,
  /\bTODO\b/,
  /\bFIXME\b/,
  /placeholder text/i,
  /\[insert/i,
  /coming soon/i,
];

(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  const p = await b.newPage({ viewport: { width: 1440, height: 1000 } });

  // crawl
  const seen = new Set([BASE + '/']);
  const queue = [BASE + '/'];
  const urls = [];
  while (queue.length) {
    const url = queue.shift();
    urls.push(url);
    try {
      await p.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
      await p.waitForTimeout(300);
      const links = await p.evaluate((base) => [...document.querySelectorAll('a[href]')]
        .map(a => a.href.split('#')[0])
        .filter(h => h.startsWith(base) && !h.match(/\.(png|jpe?g|css|js|xml)$/i)), BASE);
      for (const l of links) if (!seen.has(l)) { seen.add(l); queue.push(l); }
    } catch { /* caught below */ }
  }

  const issues = [];
  let totalImgs = 0, brokenImgs = 0, totalWords = 0;

  for (const url of urls) {
    const path = url.replace(BASE, '') || '/';
    const errs = [];
    p.removeAllListeners('pageerror');
    p.removeAllListeners('response');
    p.on('pageerror', e => errs.push('JS: ' + e.message.slice(0, 60)));
    p.on('response', r => {
      if (r.status() >= 400 && r.url().startsWith(BASE)) errs.push(r.status() + ' ' + r.url().replace(BASE, ''));
    });

    let res;
    try {
      res = await p.goto(url, { waitUntil: 'networkidle', timeout: 45000 });
      await p.waitForTimeout(600);
    } catch (e) {
      issues.push(`${path}: NAV FAIL`);
      continue;
    }
    if (res.status() !== 200) issues.push(`${path}: HTTP ${res.status()}`);

    const info = await p.evaluate(() => {
      const imgs = [...document.querySelectorAll('img')];
      const bgs = [...document.querySelectorAll('.hero, .thumb')];
      return {
        text: document.body.innerText,
        words: document.body.innerText.trim().split(/\s+/).length,
        imgTotal: imgs.length,
        imgBroken: imgs.filter(i => i.complete && i.naturalWidth === 0).length,
        bgTotal: bgs.length,
        bgEmpty: bgs.filter(e => getComputedStyle(e).backgroundImage === 'none').length,
        h1: document.querySelectorAll('h1').length,
        desc: (document.querySelector('meta[name="description"]') || {}).content || '',
        robots: (document.querySelector('meta[name="robots"]') || {}).content || '',
        title: document.title,
        ovf: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
      };
    });

    totalImgs += info.imgTotal; brokenImgs += info.imgBroken; totalWords += info.words;

    for (const re of PLACEHOLDER_PATTERNS) {
      if (re.test(info.text)) issues.push(`${path}: PLACEHOLDER TEXT matching ${re}`);
    }
    if (info.imgBroken) issues.push(`${path}: ${info.imgBroken} broken <img>`);
    if (info.bgEmpty) issues.push(`${path}: ${info.bgEmpty} empty image panel(s)`);
    if (info.h1 !== 1) issues.push(`${path}: ${info.h1} h1 tags`);
    if (!info.desc) issues.push(`${path}: no meta description`);
    if (!/noindex/i.test(info.robots)) issues.push(`${path}: NOT noindexed`);
    if (info.words < 60) issues.push(`${path}: thin (${info.words} words)`);
    if (info.ovf) issues.push(`${path}: horizontal overflow`);
    if (!info.title || info.title.length < 10) issues.push(`${path}: weak title`);
    if (errs.length) issues.push(`${path}: ${errs.slice(0, 2).join('; ')}`);
  }

  console.log(`pages crawled : ${urls.length}`);
  console.log(`total words   : ${totalWords}`);
  console.log(`images        : ${totalImgs} (${brokenImgs} broken)`);

  // mobile
  const mob = await b.newPage({ viewport: { width: 390, height: 844 } });
  await mob.goto(BASE + '/', { waitUntil: 'networkidle' });
  await mob.waitForTimeout(1000);
  const mobOvf = await mob.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
  const navWorks = await mob.evaluate(() => !!document.querySelector('.navtoggle'));
  console.log(`mobile overflow: ${mobOvf ? 'YES' : 'no'}   nav toggle present: ${navWorks ? 'yes' : 'NO'}`);
  if (mobOvf) issues.push('mobile: horizontal overflow');
  if (!navWorks) issues.push('mobile: no nav toggle');

  console.log(issues.length ? `\n${issues.length} ISSUE(S):\n  ` + issues.join('\n  ') : '\nNO ISSUES FOUND.');
  await b.close();
})();
