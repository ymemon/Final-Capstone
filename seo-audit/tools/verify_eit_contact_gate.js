const { chromium } = require('playwright-core');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const URL = process.argv[2];
const OUT = process.argv[3];

const NEW = 'Thank you for your interest in Everything IT';
const OLD = 'Right now we';

(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  const p = await b.newPage({ viewport: { width: 1280, height: 1000 } });
  const errs = [];
  p.on('pageerror', e => errs.push(e.message));

  await p.goto(URL, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await p.waitForTimeout(2500);

  // Find the employee-count control, whatever shape it takes.
  const selects = await p.$$('select');
  let chosen = null;
  for (const s of selects) {
    const txt = (await s.innerText().catch(() => '')) || '';
    if (/15|employee/i.test(txt)) { chosen = s; break; }
  }
  if (chosen) {
    const opts = await chosen.$$eval('option', os => os.map(o => ({ v: o.value, t: o.textContent.trim() })));
    const target = opts.find(o => /fewer|less than|under|1\s*-\s*14|<\s*15/i.test(o.t));
    console.log('options:', JSON.stringify(opts));
    if (target) {
      await chosen.selectOption(target.v);
      console.log('selected:', target.t);
    }
  } else {
    console.log('no <select> matched; looking for radio/button choices');
    const el = await p.$('text=/Fewer than 15|Less than 15|Under 15/i');
    if (el) { await el.click(); console.log('clicked a "fewer than 15" choice'); }
  }

  await p.waitForTimeout(600);
  for (const label of ['Submit', 'Send', 'Continue', 'Next', 'Get Started']) {
    const btn = await p.$(`button:has-text("${label}"), input[type=submit][value*="${label}" i]`);
    if (btn) { await btn.click().catch(() => {}); console.log('clicked button:', label); break; }
  }
  await p.waitForTimeout(3000);

  const text = await p.evaluate(() => document.body.innerText);
  const html = await p.content();
  console.log('NEW wording visible in rendered text:', text.includes(NEW));
  console.log('NEW wording present in DOM       :', html.includes(NEW));
  console.log('OLD wording present in DOM       :', html.includes(OLD));
  console.log('page errors:', errs.length ? errs : 'none');

  // Screenshot whatever region holds the message, else the viewport.
  const node = await p.$(`text=${NEW}`);
  if (node) {
    await node.scrollIntoViewIfNeeded().catch(() => {});
    await p.waitForTimeout(400);
  }
  await p.screenshot({ path: OUT });
  console.log('shot:', OUT);
  await b.close();
})();
