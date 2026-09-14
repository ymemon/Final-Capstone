const { chromium } = require('playwright-core');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const PAGES = ['/','/about/','/services/','/gallery/','/book/','/get-a-quote/','/contact/'];
(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  for (const [label,w,h] of [['mobile',390,844],['desktop',1440,1000]]) {
    const p = await b.newPage({ viewport:{width:w,height:h} });
    for (const u of PAGES) {
      const errs=[];
      p.removeAllListeners('pageerror'); p.on('pageerror', e=>errs.push(e.message));
      await p.goto('http://localhost:8081'+u, { waitUntil:'networkidle' });
      for (let i=0;i<30;i++){ await p.evaluate(()=>window.scrollBy(0,700)); await p.waitForTimeout(70); }
      await p.waitForTimeout(400);
      const r = await p.evaluate(()=>{
        const els=[...document.querySelectorAll('[data-reveal],[data-reveal-stagger]')];
        return { hidden: els.filter(e=>getComputedStyle(e).opacity==='0').length,
                 sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth };
      });
      const flags=[];
      if (r.hidden) flags.push('HIDDEN:'+r.hidden);
      if (r.sw > r.cw+1) flags.push('OVERFLOW');
      if (errs.length) flags.push('JSERR:'+errs[0].slice(0,40));
      console.log(`  ${label.padEnd(8)} ${u.padEnd(15)} ${flags.length?flags.join(' '):'ok'}`);
    }
    await p.close();
  }
  await b.close();
})();
