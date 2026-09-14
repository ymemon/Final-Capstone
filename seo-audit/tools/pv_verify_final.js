const { chromium } = require('playwright-core');
(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true,
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
  });
  const page = await context.newPage();
  await page.goto('https://875051.us16.myftpupload.com/?nc=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 40000 });
  await page.waitForTimeout(1000);

  const overlay = await page.evaluate(() => {
    const o = document.querySelector('.pv-doctor-overlay');
    if (!o) return null;
    const s = getComputedStyle(o);
    return { opacity: s.opacity, bgImage: s.backgroundImage.slice(0,60) };
  });
  console.log('overlay:', JSON.stringify(overlay));

  const btn = await page.evaluate(() => {
    const b = document.querySelector('.pv-doctors-btn');
    if (!b) return { found: false };
    const s = getComputedStyle(b);
    return { found: true, cls: b.className, tag: b.tagName, bg: s.backgroundColor, bgImage: s.backgroundImage.slice(0,80), color: s.color };
  });
  console.log('doctors-btn:', JSON.stringify(btn));

  // Double check via full contrast probe reused
  const probeIssues = await page.evaluate(() => {
    const parse = c => { const m = (c||'').match(/[\d.]+/g); if(!m) return null; const a = m.length>3?parseFloat(m[3]):1; return {r:+m[0],g:+m[1],b:+m[2],a}; };
    const lum = c => { const f = v => { v/=255; return v<=0.03928?v/12.92:Math.pow((v+0.055)/1.055,2.4); }; return 0.2126*f(c.r)+0.7152*f(c.g)+0.0722*f(c.b); };
    const ratio = (a,b) => { const l1=lum(a),l2=lum(b); const hi=Math.max(l1,l2),lo=Math.min(l1,l2); return (hi+0.05)/(lo+0.05); };
    const bgOf = node => { let n=node; while(n && n!==document.documentElement){ const s=getComputedStyle(n); if(s.backgroundImage && s.backgroundImage!=='none') return {grad:true,col:parse('rgb(20,24,32)')}; const c=parse(s.backgroundColor); if(c&&c.a>0.55) return {grad:false,col:c}; n=n.parentElement; } return {grad:false,col:parse('rgb(255,255,255)')}; };
    const out = [];
    ['Glendale','Scottsdale','East Valley','Estrella','Book Your Appointment'].forEach(txt => {
      const n = [...document.querySelectorAll('*')].find(el => (el.innerText||'').trim() === txt);
      if (!n) { out.push({txt, found:false}); return; }
      const s = getComputedStyle(n);
      const fg = parse(s.color);
      const bg = bgOf(n);
      const r = ratio(fg, bg.col);
      out.push({txt, found:true, ratio: r.toFixed(2), fg: s.color, bg: bg.grad?'gradient':`rgb(${bg.col.r},${bg.col.g},${bg.col.b})`});
    });
    return out;
  });
  console.log(JSON.stringify(probeIssues, null, 2));

  await browser.close();
})().catch(e => { console.error('FAILED:', e.message); process.exit(1); });
