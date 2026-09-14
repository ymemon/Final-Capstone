const { chromium } = require('playwright-core');

const targets = [
  { url: 'east-valley-location', texts: ['Palo Verde Cancer Center', 'Male', 'Cell'] },
  { url: 'schedule', texts: ['How can we reach you?', 'Please have your insurance card ready'] },
  { url: 'home', texts: ['Book Your Appointment', 'Glendale'] },
  { url: 'privacy-policy', texts: ['How We Handle Your Information'] },
  { url: 'patient-forms', texts: ['contact form pv'] },
];

const chainOf = () => (txt) => {
  const els = [...document.querySelectorAll('*')].filter(n => (n.innerText || n.textContent || '').trim().startsWith(txt) && n.children.length === 0 || (n.innerText||'').trim() === txt);
  if (!els.length) return null;
  const n = els[0];
  const s = getComputedStyle(n);
  return {
    tag: n.tagName, id: n.id, cls: (n.className || '').toString(),
    color: s.color,
    parentCls: n.parentElement ? (n.parentElement.className||'').toString() : '',
    parentTag: n.parentElement ? n.parentElement.tagName : '',
  };
};

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true,
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
  });

  for (const t of targets) {
    const page = await context.newPage();
    const url = t.url === 'home' ? 'https://875051.us16.myftpupload.com/?nc=' + Date.now() : `https://875051.us16.myftpupload.com/${t.url}/?nc=` + Date.now();
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 40000 });
    await page.waitForTimeout(1200);
    console.log(`\n=== ${t.url} ===`);
    for (const txt of t.texts) {
      const r = await page.evaluate((txt) => {
        const all = [...document.querySelectorAll('*')];
        const n = all.find(el => (el.innerText || '').trim() === txt || (el.textContent || '').trim() === txt);
        if (!n) return null;
        const s = getComputedStyle(n);
        return {
          tag: n.tagName, id: n.id, cls: (n.className || '').toString(),
          color: s.color,
          parentCls: n.parentElement ? (n.parentElement.className||'').toString() : '',
          parentTag: n.parentElement ? n.parentElement.tagName : '',
          grandparentCls: n.parentElement && n.parentElement.parentElement ? (n.parentElement.parentElement.className||'').toString() : '',
        };
      }, txt);
      console.log(txt + ' ->', JSON.stringify(r));
    }
    await page.close();
  }
  await browser.close();
})().catch(e => { console.error('FAILED:', e.message); process.exit(1); });
