const { chromium } = require('playwright-core');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const URL = process.argv[2];
const OUT = process.argv[3];
const NEW = 'Thank you for your interest in Everything IT';

(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  const p = await b.newPage({ viewport: { width: 1280, height: 1100 } });
  await p.goto(URL, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await p.waitForTimeout(2500);

  // Locate the container holding the new copy and describe how it is hidden.
  const info = await p.evaluate((needle) => {
    const all = [...document.querySelectorAll('*')];
    const node = all.find(e => e.children.length === 0 && e.textContent.includes(needle))
              || all.find(e => e.textContent.includes(needle));
    if (!node) return { found: false };
    const chain = [];
    let cur = node;
    for (let i = 0; i < 6 && cur && cur !== document.body; i++) {
      const cs = getComputedStyle(cur);
      chain.push({
        tag: cur.tagName.toLowerCase(),
        cls: (cur.className || '').toString().slice(0, 60),
        display: cs.display, visibility: cs.visibility, opacity: cs.opacity,
        height: cur.getBoundingClientRect().height,
      });
      cur = cur.parentElement;
    }
    return { found: true, text: node.textContent.trim().slice(0, 90), chain };
  }, NEW);
  console.log('BEFORE selection:', JSON.stringify(info, null, 1));

  // Select "Fewer than 15" only - deliberately NOT submitting, to avoid
  // creating a fake enquiry in the client's inbox.
  const sel = await p.$('select');
  const selects = await p.$$('select');
  for (const s of selects) {
    const opts = await s.$$eval('option', os => os.map(o => o.value));
    if (opts.includes('under-15')) { await s.selectOption('under-15'); console.log('selected under-15'); break; }
  }
  await p.waitForTimeout(1200);

  const after = await p.evaluate((needle) => {
    const all = [...document.querySelectorAll('*')];
    const node = all.find(e => e.children.length === 0 && e.textContent.includes(needle))
              || all.find(e => e.textContent.includes(needle));
    if (!node) return { visible: false, reason: 'node gone' };
    const r = node.getBoundingClientRect();
    const cs = getComputedStyle(node);
    return { visible: r.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none',
             height: r.height, display: cs.display, visibility: cs.visibility };
  }, NEW);
  console.log('AFTER selection:', JSON.stringify(after));

  const node = await p.$(`text=${NEW}`);
  if (node) await node.scrollIntoViewIfNeeded().catch(() => {});
  await p.waitForTimeout(500);
  await p.screenshot({ path: OUT, fullPage: false });
  console.log('shot:', OUT);
  await b.close();
})();
