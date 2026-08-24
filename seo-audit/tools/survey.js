const { chromium } = require('playwright-core');
const EXE = 'C:/Users/yasir/AppData/Local/ms-playwright/chromium-1234/chrome-win64/chrome.exe';

const PAGES = process.argv.slice(2);

const probe = () => {
  const parse = c => {
    const m = (c || '').match(/[\d.]+/g);
    if (!m) return null;
    const a = m.length > 3 ? parseFloat(m[3]) : 1;
    return { r: +m[0], g: +m[1], b: +m[2], a };
  };
  const lum = c => {
    const f = v => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
    return 0.2126 * f(c.r) + 0.7152 * f(c.g) + 0.0722 * f(c.b);
  };
  const ratio = (a, b) => { const l1 = lum(a), l2 = lum(b); const hi = Math.max(l1, l2), lo = Math.min(l1, l2); return (hi + 0.05) / (lo + 0.05); };

  // Effective background: walk up until something actually paints.
  const bgOf = node => {
    let n = node;
    while (n && n !== document.documentElement) {
      const s = getComputedStyle(n);
      if (s.backgroundImage && s.backgroundImage !== 'none') return { grad: true, col: parse('rgb(20,24,32)') };
      const c = parse(s.backgroundColor);
      if (c && c.a > 0.55) return { grad: false, col: c };
      n = n.parentElement;
    }
    return { grad: false, col: parse('rgb(255,255,255)') };
  };

  const body = getComputedStyle(document.body);
  const bodyBg = body.backgroundImage !== 'none' ? body.backgroundImage : body.backgroundColor;

  // Off-brand background IMAGES.
  //
  // The contrast pass treats any background-image as the dark brand gradient,
  // which is how a lime-to-gold panel and a stock photo hero both scored a
  // clean bill of health. Anything painting a large area with an image that is
  // neither the brand gradient nor a photograph inside a card is reported
  // separately here.
  const BRAND = 'rgb(5, 6, 8)';
  const offbrand = [];
  document.querySelectorAll('body *').forEach(n => {
    if (n.closest('header#masthead, footer')) return;
    const s = getComputedStyle(n);
    const bi = s.backgroundImage;
    if (!bi || bi === 'none') return;
    const rc = n.getBoundingClientRect();
    if (rc.width < 300 || rc.height < 120) return;
    if (bi.includes(BRAND)) return;                 // the brand gradient itself
    const isGradient = bi.includes('gradient');
    const tag = n.tagName.toLowerCase();
    const cls = (n.className || '').toString().trim().split(/\s+/).filter(c => /^elementor-element-[0-9a-z]+$/.test(c))[0]
      || (n.className || '').toString().trim().split(/\s+/)[0] || '';
    offbrand.push((isGradient ? 'GRADIENT ' : 'IMAGE    ') + tag + '.' + cls + '  ' + bi.slice(0, 58));
  });

  const bad = [];
  const seen = new Set();
  document.querySelectorAll('h1,h2,h3,h4,h5,p,li,span,a,td,th,label,strong,em,div').forEach(n => {
    if (n.closest('header#masthead, footer, #azwc-fu-modal, script, style, noscript')) return;
    const txt = (n.innerText || '').trim();
    if (!txt || txt.length < 4) return;
    // only leaf-ish nodes, so a wrapper is not counted for its children's text
    if (n.children.length && [...n.children].some(c => (c.innerText || '').trim().length > 3)) return;
    const rc = n.getBoundingClientRect();
    if (rc.width < 8 || rc.height < 6) return;
    const s = getComputedStyle(n);
    if (s.visibility === 'hidden' || s.display === 'none' || parseFloat(s.opacity) < 0.1) return;
    const fg = parse(s.color);
    if (!fg || fg.a < 0.5) return;
    const bg = bgOf(n);
    const r = ratio(fg, bg.col);
    if (r < 3) {
      const key = s.color + '|' + txt.slice(0, 26);
      if (seen.has(key)) return;
      seen.add(key);
      bad.push({ t: txt.slice(0, 42).replace(/\s+/g, ' '), fg: s.color, bg: bg.grad ? 'gradient' : `rgb(${bg.col.r},${bg.col.g},${bg.col.b})`, r: r.toFixed(2) });
    }
  });
  return { bodyBg: bodyBg.slice(0, 62), bad: bad.slice(0, 6), badCount: bad.length,
           offbrand: [...new Set(offbrand)].slice(0, 5), offbrandCount: new Set(offbrand).size };
};

(async () => {
  const b = await chromium.launch({ executablePath: EXE });
  const ctx = await b.newContext({ viewport: { width: 1280, height: 900 } });
  const GRAD = 'rgb(5, 6, 8)';
  let dark = 0, light = 0, issues = 0, off = 0;

  for (const slug of PAGES) {
    const p = await ctx.newPage();
    try {
      await p.goto('https://azwebcorp.com/' + slug + '?cb=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 40000 });
      await p.waitForTimeout(1500);
      const r = await p.evaluate(probe);
      const isDark = r.bodyBg.includes(GRAD);
      if (isDark) dark++; else light++;
      issues += r.badCount;
      off += r.offbrandCount;
      console.log((isDark ? 'DARK  ' : 'light ') + slug.padEnd(26) + 'bg=' + r.bodyBg.padEnd(40) + (r.badCount ? ' contrast:' + r.badCount : ''));
      r.bad.forEach(x => console.log('        ! ' + x.r.padStart(5) + ':1  ' + x.fg.padEnd(20) + ' on ' + x.bg.padEnd(18) + ' "' + x.t + '"'));
      r.offbrand.forEach(x => console.log('        ~ off-brand bg: ' + x));
    } catch (e) {
      console.log('ERR   ' + slug.padEnd(26) + e.message.slice(0, 40));
    }
    await p.close();
  }
  console.log(`\nSUMMARY: ${dark} dark, ${light} light, ${issues} contrast findings, ${off} off-brand backgrounds`);
  await b.close();
})();
