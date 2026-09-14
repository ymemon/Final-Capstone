const { chromium } = require('playwright-core');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const URL = process.argv[2];
const OUT = process.argv[3];
const NEW = 'Thank you for your interest in Everything IT';

(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  const p = await b.newPage({ viewport: { width: 1280, height: 1200 } });
  const posted = [];
  p.on('request', r => { if (r.method() === 'POST') posted.push(r.url().split('?')[0]); });

  await p.goto(URL, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await p.waitForTimeout(2500);

  // Fill visible text inputs with clearly-marked test data.
  const fields = await p.$$('input[type=text], input[type=email], input[type=tel], textarea');
  for (const f of fields) {
    if (!(await f.isVisible().catch(() => false))) continue;
    const name = ((await f.getAttribute('name')) || '').toLowerCase();
    const type = ((await f.getAttribute('type')) || '').toLowerCase();
    let v = 'AZ Web Corp test - please ignore';
    if (type === 'email' || /mail/.test(name)) v = 'requests@azwebcorp.com';
    else if (type === 'tel' || /phone|tel/.test(name)) v = '0000000000';
    else if (/name/.test(name)) v = 'AZ Web Corp Test';
    else if (/compan|organis|organiz/.test(name)) v = 'AZ Web Corp';
    await f.fill(v).catch(() => {});
  }

  for (const s of await p.$$('select')) {
    const opts = await s.$$eval('option', os => os.map(o => o.value));
    if (opts.includes('under-15')) { await s.selectOption('under-15'); console.log('selected under-15'); }
    else if (opts.length > 1) { await s.selectOption(opts[1]).catch(() => {}); }
  }
  await p.waitForTimeout(700);

  const btn = await p.$('button[type=submit], input[type=submit], button:has-text("Send")');
  if (btn) { await btn.click().catch(() => {}); console.log('submitted'); }
  await p.waitForTimeout(4000);

  const res = await p.evaluate((needle) => {
    const t = document.body.innerText;
    const i = t.indexOf(needle);
    return { visibleInText: i !== -1, excerpt: i !== -1 ? t.slice(i, i + 260) : null };
  }, NEW);
  console.log('new wording VISIBLE to user:', res.visibleInText);
  if (res.excerpt) console.log('excerpt:', JSON.stringify(res.excerpt));
  console.log('POST requests made:', posted.length ? posted : 'none (no enquiry submitted)');

  const node = await p.$(`text=${NEW}`);
  if (node) { await node.scrollIntoViewIfNeeded().catch(() => {}); await p.waitForTimeout(500); }
  await p.screenshot({ path: OUT });
  console.log('shot:', OUT);
  await b.close();
})();
