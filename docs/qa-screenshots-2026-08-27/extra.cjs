const {chromium}=require(process.env.PW);
(async()=>{
  const b=await chromium.launch({headless:true});
  const p=await (await b.newContext({viewport:{width:390,height:844},isMobile:true})).newPage();
  await p.goto('https://travel63test.ru/frontend/window/popular-hotels.php',{waitUntil:'domcontentloaded',timeout:60000});
  await p.waitForTimeout(8000);
  const hotels=await p.evaluate(()=>({
    loading:(document.body.innerText||'').includes('Загрузка'),
    cards:[...document.querySelectorAll('a,article,div')].filter(el=>/от\s+\d/.test(el.innerText||'') && el.getBoundingClientRect().height>80).length,
    snippet:(document.body.innerText||'').replace(/\s+/g,' ').match(/Вылет:.*?(.{0,120})/)?.[0]
  }));
  console.log(JSON.stringify(hotels));
  // mobile nav scrollability
  await p.goto('https://travel63test.ru/frontend/index.php',{waitUntil:'domcontentloaded'});
  await p.waitForTimeout(800);
  await p.click('.header__burger');
  await p.waitForTimeout(400);
  const nav=await p.evaluate(()=>{
    const panel=document.querySelector('.site-header-mobile-panel');
    const s=getComputedStyle(panel);
    return {scrollH:panel.scrollHeight, clientH:panel.clientHeight, overflowY:s.overflowY, canScroll:panel.scrollHeight>panel.clientHeight+2, lastText:(panel.innerText||'').trim().split('\n').slice(-5)};
  });
  console.log('nav', JSON.stringify(nav));
  await b.close();
})();
