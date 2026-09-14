const { chromium } = require('playwright-core');

const BASE = 'https://875051.us16.myftpupload.com/';
const PAGES = [
  'home', 'about-us', 'services', 'schedule', 'patient-forms', 'clinical-research',
  'conditions-we-treat', 'east-valley-location', 'estrella-location',
  'scottsdale-location', 'glendale-location', 'your-team', 'medical-oncology',
  'pet-scan-imaging', 'privacy-policy', 'hipaa-notice', 'terms-of-service',
];

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

  const bad = [];
  const seen = new Set();
  document.querySelectorAll('h1,h2,h3,h4,h5,p,li,span,a,td,th,label,strong,em,div,button').forEach(n => {
    if (n.closest('header#masthead, footer, script, style, noscript')) return;
    const txt = (n.innerText || '').trim();
    if (!txt || txt.length < 4) return;
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
      bad.push({ t: txt.slice(0, 46).replace(/\s+/g, ' '), fg: s.color, bg: bg.grad ? 'gradient' : `rgb(${bg.col.r},${bg.col.g},${bg.col.b})`, r: r.toFixed(2), y: Math.round(rc.top + window.scrollY) });
    }
  });

  // Section-level background sampling: walk down through top-level content
  // sections and record each one's background, to reveal jarring color jumps
  // between adjacent sections (the actual "not formatted correctly" symptom).
  const sections = [];
  document.querySelectorAll('body > *, #page > *, .site-content > *, main > *').forEach(n => {
    const rc = n.getBoundingClientRect();
    if (rc.height < 80) return;
    const s = getComputedStyle(n);
    const bg = s.backgroundImage !== 'none' ? 'IMG:' + s.backgroundImage.slice(0, 30) : s.backgroundColor;
    sections.push({ tag: n.tagName.toLowerCase(), cls: (n.className || '').toString().slice(0, 40), bg, h: Math.round(rc.height) });
  });

  return { bodyBg: bodyBg.slice(0, 62), bad: bad.slice(0, 8), badCount: bad.length, sections: sections.slice(0, 25) };
};

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({
    viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2,
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
  });

  let totalIssues = 0;
  const results = [];

  for (const slug of PAGES) {
    const page = await context.newPage();
    try {
      const url = slug === 'home' ? BASE + '?nocache=' + Date.now() : BASE + slug + '/?nocache=' + Date.now();
      await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 40000 });
      await page.waitForTimeout(1200);
      const r = await page.evaluate(probe);
      totalIssues += r.badCount;
      results.push({ slug, bodyBg: r.bodyBg, badCount: r.badCount, bad: r.bad, sections: r.sections });
      console.log(slug.padEnd(32) + 'bg=' + r.bodyBg.padEnd(24) + (r.badCount ? ' CONTRAST:' + r.badCount : ' ok'));
    } catch (e) {
      console.log('ERR ' + slug.padEnd(28) + e.message.slice(0, 60));
      results.push({ slug, error: e.message });
    }
    await page.close();
  }

  console.log('\n=== DETAIL ===\n');
  for (const r of results) {
    if (r.error || (!r.badCount && !r.sections)) continue;
    if (r.badCount) {
      console.log(`--- ${r.slug} (bg=${r.bodyBg}) ---`);
      r.bad.forEach(x => console.log(`   ! ${x.r}:1  ${x.fg} on ${x.bg}  y=${x.y}  "${x.t}"`));
    }
  }

  console.log(`\nSUMMARY: ${results.length} pages checked, ${totalIssues} total contrast findings`);

  require('fs').writeFileSync('pv_audit_full.json', JSON.stringify(results, null, 2));
  await browser.close();
})().catch(e => { console.error('FAILED:', e.message); process.exit(1); });
