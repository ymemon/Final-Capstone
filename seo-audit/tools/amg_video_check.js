const { chromium } = require('playwright-core');
const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
(async () => {
  const b = await chromium.launch({ executablePath: CHROME, headless: true });
  for (const [label,w,h] of [['desktop',1440,900],['mobile',390,844]]) {
    const p = await b.newPage({ viewport:{width:w,height:h} });
    await p.goto('http://localhost:8081/', { waitUntil:'networkidle' });
    await p.waitForTimeout(3500);
    const v = await p.evaluate(() => {
      const el = document.querySelector('.amg-hero__video');
      if (!el) return { found:false };
      return { found:true, paused: el.paused, currentTime: +el.currentTime.toFixed(2),
               readyState: el.readyState, w: el.videoWidth, h: el.videoHeight,
               src: (el.currentSrc||'').split('/').pop() };
    });
    console.log(label, JSON.stringify(v));
    await p.close();
  }
  await b.close();
})();
