const {chromium}=require(process.env.PW);
(async()=>{
  const b=await chromium.launch({headless:true});
  const p=await (await b.newContext({viewport:{width:390,height:844},isMobile:true})).newPage();
  await p.goto('https://travel63test.ru/frontend/index.php',{waitUntil:'domcontentloaded'});
  await p.waitForTimeout(1000);
  await p.click('.header__burger');
  await p.waitForTimeout(500);
  const info=await p.evaluate(()=>{
    const panel=document.querySelector('.site-header-mobile-panel');
    const links=[...panel.querySelectorAll('a')].map(a=>{
      const r=a.getBoundingClientRect();
      return {t:a.textContent.trim().slice(0,40), top:Math.round(r.top), bottom:Math.round(r.bottom), inView:r.top>=0&&r.bottom<=innerHeight&&r.height>0};
    });
    return {vh:innerHeight, clipped:links.filter(l=>!l.inView), last5:links.slice(-5), first3:links.slice(0,3)};
  });
  console.log(JSON.stringify(info,null,2));
  await p.screenshot({path:'D:/работа/travelhub-v2/docs/qa-screenshots-2026-08-27/mobile-nav-full.png', fullPage:false});
  // registration sticky overlap
  await p.goto('https://travel63test.ru/frontend/window/registration-desktop.php',{waitUntil:'domcontentloaded'});
  await p.setViewportSize({width:1280,height:800});
  await p.waitForTimeout(800);
  const overlap=await p.evaluate(()=>{
    const submit=[...document.querySelectorAll('button,a')].find(b=>/Зарегистрироваться/.test(b.textContent||''));
    const bars=[...document.querySelectorAll('*')].filter(el=>{
      const s=getComputedStyle(el); const r=el.getBoundingClientRect();
      return (s.position==='fixed') && r.height>40 && r.bottom>innerHeight-80 && r.width>200 && /Позвонить|MAX|Чат/.test(el.innerText||'');
    });
    const sr=submit&&submit.getBoundingClientRect();
    const bar=bars[0]&&bars[0].getBoundingClientRect();
    return {
      submit: sr&&{top:Math.round(sr.top),bottom:Math.round(sr.bottom),inView:sr.top<innerHeight&&sr.bottom>0},
      bar: bar&&{top:Math.round(bar.top),bottom:Math.round(bar.bottom),text:(bars[0].innerText||'').slice(0,40)},
      overlaps: !!(sr&&bar&&sr.bottom>bar.top&&sr.top<bar.bottom)
    };
  });
  console.log('regOverlap', JSON.stringify(overlap));
  await b.close();
})();
