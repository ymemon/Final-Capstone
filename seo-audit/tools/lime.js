const { chromium } = require('playwright-core');
const EXE = 'C:/Users/yasir/AppData/Local/ms-playwright/chromium-1234/chrome-win64/chrome.exe';

/**
 * Find every surviving lime/olive from the old palette.
 *
 * Brand gold is rgb(230,184,77) - red exceeds green. Lime (221,228,35) and
 * olive (75,83,24) are the opposite: green >= red, with blue well below both.
 * That single test separates the two families without needing a list of
 * specific hex values, and it does not flag gold, white, or any grey.
 */
const probe = () => {
  const isLime = (r, g, b) => g >= r && b < g * 0.62 && g > 40 && (r + g + b) > 60;

  const hits = [];
  const scan = (n, prop, value) => {
    if (!value) return;
    const rx = /rgba?\(([\d.]+),\s*([\d.]+),\s*([\d.]+)(?:,\s*([\d.]+))?\)/g;
    let m;
    while ((m = rx.exec(value))) {
      const [r, g, b] = [+m[1], +m[2], +m[3]];
      const a = m[4] === undefined ? 1 : +m[4];
      if (a < 0.06) continue;
      if (!isLime(r, g, b)) continue;
      const rc = n.getBoundingClientRect();
      if (rc.width < 12 || rc.height < 8) continue;
      const cls = (n.className || '').toString().trim().split(/\s+/).slice(0, 2).join('.');
      hits.push(n.tagName.toLowerCase() + '.' + cls + '  ' + prop + ': rgb(' + r + ',' + g + ',' + b + ')'
        + (a < 1 ? ' a=' + a : ''));
      break;
    }
  };

  document.querySelectorAll('body *').forEach(n => {
    const s = getComputedStyle(n);
    scan(n, 'bg', s.backgroundColor);
    scan(n, 'bg-image', s.backgroundImage);
    scan(n, 'color', s.color);
    scan(n, 'border', s.borderTopColor + ' ' + s.borderLeftColor);
    scan(n, 'shadow', s.boxShadow);
    scan(n, 'fill', s.fill);
    scan(n, 'stroke', s.stroke);
  });
  return [...new Set(hits)];
};

(async () => {
  const b = await chromium.launch({ executablePath: EXE });
  const ctx = await b.newContext({ viewport: { width: 1280, height: 900 } });
  const agg = new Map();

  for (const slug of process.argv.slice(2)) {
    const p = await ctx.newPage();
    try {
      await p.goto('https://azwebcorp.com/' + slug + '?cb=' + Date.now(), { waitUntil: 'domcontentloaded', timeout: 40000 });
      await p.waitForTimeout(1600);
      const r = await p.evaluate(probe);
      r.forEach(x => agg.set(x, (agg.get(x) || 0) + 1));
    } catch (e) { console.log('ERR ' + slug); }
    await p.close();
  }

  if (!agg.size) { console.log('CLEAN - no lime/olive found on any page scanned'); }
  else {
    console.log('Surviving lime/olive (green >= red), by frequency:');
    [...agg.entries()].sort((a, b) => b[1] - a[1]).slice(0, 30)
      .forEach(([k, v]) => console.log('  ' + String(v).padStart(3) + 'x  ' + k));
  }
  await b.close();
})();
