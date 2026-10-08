const { chromium } = require(process.env.PW);
const fs = require('fs');
(async () => {
  const browser = await chromium.launch({headless:true});
  const out = {};
  {
    const ctx = await browser.newContext({viewport:{width:1280,height:800}});
    const page = await ctx.newPage();
    await page.goto('https://travel63test.ru/frontend/index.php', {waitUntil:'networkidle', timeout:60000});
    await page.waitForTimeout(1500);
    out.home = await page.evaluate(() => {
      const logo = document.querySelector('.header__logo');
      const lr = logo && logo.getBoundingClientRect();
      const authVisible = [...document.querySelectorAll('.site-header a, header a, .header a')].filter(a => {
        const r=a.getBoundingClientRect();
        return r.width>0 && r.height>0 && r.right<=innerWidth+1 && r.top < 80 && /Войти|Профиль|Выход|Регистрац|Админ|Менеджер/.test(a.textContent||'');
      }).map(a=>({t:a.textContent.trim().replace(/\s+/g,' '), right:Math.round(a.getBoundingClientRect().right)}));
      const badLinks = [...document.querySelectorAll('a[href]')].map(a=>a.getAttribute('href')).filter(h=>/frontend\/frontend|window\/hotels\.php$|window\/calendar\.php|window\/register\.php|window\/cabinet/.test(h||''));
      return {
        logo: logo ? {text:logo.textContent.trim(), sw:logo.scrollWidth, cw:logo.clientWidth, overflow:logo.scrollWidth>logo.clientWidth+1, rect:{x:Math.round(lr.x),w:Math.round(lr.width)}, cs:{maxW:getComputedStyle(logo).maxWidth, overflow:getComputedStyle(logo).overflow, whiteSpace:getComputedStyle(logo).whiteSpace, textOverflow:getComputedStyle(logo).textOverflow}} : null,
        authVisible,
        badLinks: [...new Set(badLinks)],
        pageScrollW: document.documentElement.scrollWidth,
        vw: innerWidth
      };
    });
    await page.locator('button', {hasText:'Даты вылета'}).first().click();
    await page.waitForTimeout(800);
    out.datesModal = await page.evaluate(() => {
      const pop = document.querySelector('.th-coral-date-popup');
      const cal = document.querySelector('.flatpickr-calendar');
      const info = (el) => {
        if (!el) return null;
        const r=el.getBoundingClientRect(); const s=getComputedStyle(el);
        return {cls:el.className.toString().slice(0,120), hidden:el.classList.contains('hidden'), isOpen:el.classList.contains('is-open'), display:s.display, vis:s.visibility, op:s.opacity, rect:{x:Math.round(r.x),y:Math.round(r.y),w:Math.round(r.width),h:Math.round(r.height)}};
      };
      return {popup:info(pop), cal:info(cal)};
    });
    await page.screenshot({path:'D:/работа/travelhub-v2/docs/qa-screenshots-2026-08-27/desktop-dates-recheck.png'});
    await page.keyboard.press('Escape');
    await page.waitForTimeout(300);
    await page.evaluate(() => [...document.querySelectorAll('[role=tab]')].find(t=>/Отели/.test(t.textContent||''))?.click());
    await page.waitForTimeout(700);
    out.hotelsTab = await page.evaluate(() => ({
      selected: [...document.querySelectorAll('[role=tab][aria-selected=true]')].map(t=>t.textContent.trim()),
      fields: [...document.querySelectorAll('button[aria-current]')].map(b=>(b.textContent||'').trim().replace(/\s+/g,' ').slice(0,70))
    }));
    await page.screenshot({path:'D:/работа/travelhub-v2/docs/qa-screenshots-2026-08-27/desktop-hotels-tab-recheck.png'});
    // profile + passport html links while guest
    for (const [k,u] of [['profile','https://travel63test.ru/frontend/window/profile.php'],['passport','https://travel63test.ru/frontend/window/passport-data.php'],['reg','https://travel63test.ru/frontend/window/registration-desktop.php'],['hotels','https://travel63test.ru/frontend/window/popular-hotels.php'],['calendar','https://travel63test.ru/frontend/window/tour-calendar.php']]) {
      await page.goto(u,{waitUntil:'domcontentloaded'});
      await page.waitForTimeout(700);
      out[k] = await page.evaluate(() => ({
        title: document.title,
        h1: (document.querySelector('h1')||{}).innerText||'',
        statusText: document.body.innerText.slice(0,350),
        pageScrollW: document.documentElement.scrollWidth,
        vw: innerWidth,
        passportLinks: [...document.querySelectorAll('a')].filter(a=>/паспорт|passport|кабинет|личн/i.test((a.textContent||'')+a.href)).map(a=>({t:a.textContent.trim().slice(0,60), href:a.href})).slice(0,8)
      }));
      await page.screenshot({path:`D:/работа/travelhub-v2/docs/qa-screenshots-2026-08-27/desktop-${k}.png`});
    }
    await ctx.close();
  }
  {
    const ctx = await browser.newContext({viewport:{width:390,height:844}, isMobile:true, hasTouch:true});
    const page = await ctx.newPage();
    await page.goto('https://travel63test.ru/frontend/index.php', {waitUntil:'networkidle', timeout:60000});
    await page.waitForTimeout(1200);
    out.mobileHome = await page.evaluate(() => {
      const logo = document.querySelector('.header__logo');
      const toggle = document.querySelector('.site-header-mobile-toggle, .header__burger, .burger, [aria-label*="Меню" i], [aria-label*="меню" i]');
      const panel = document.querySelector('.site-header-mobile-panel');
      return {
        pageScrollW: document.documentElement.scrollWidth, vw: innerWidth,
        logo: logo ? {sw:logo.scrollWidth,cw:logo.clientWidth,overflow:logo.scrollWidth>logo.clientWidth+1,text:logo.textContent.trim()} : null,
        toggle: toggle ? {cls:toggle.className.toString().slice(0,90), aria:toggle.getAttribute('aria-label'), w:Math.round(toggle.getBoundingClientRect().width)} : null,
        panel: panel ? {cls:panel.className.toString().slice(0,90), vis:getComputedStyle(panel).visibility, x:Math.round(panel.getBoundingClientRect().x), w:Math.round(panel.getBoundingClientRect().width)} : null,
        topLinks: [...document.querySelectorAll('a,button')].filter(el=>{const r=el.getBoundingClientRect(); return r.top>=0&&r.top<70&&r.width>0&&r.height>0;}).map(el=>({t:(el.textContent||el.getAttribute('aria-label')||'').trim().slice(0,30), tag:el.tagName, w:Math.round(el.getBoundingClientRect().width)})).slice(0,12)
      };
    });
    // open burger / ещё
    await page.evaluate(() => {
      const t = document.querySelector('.site-header-mobile-toggle, .header__burger, .burger');
      if (t) t.click();
      else {
        const b = [...document.querySelectorAll('button')].find(x=>/Ещё|Меню|меню/.test((x.textContent||'')+(x.getAttribute('aria-label')||'')));
        b && b.click();
      }
    });
    await page.waitForTimeout(500);
    out.mobileNav = await page.evaluate(() => {
      const panel = document.querySelector('.site-header-mobile-panel');
      if (!panel) return {missing:true};
      const s=getComputedStyle(panel); const r=panel.getBoundingClientRect();
      return {cls:panel.className, vis:s.visibility, display:s.display, op:s.opacity, transform:s.transform, rect:{x:Math.round(r.x),w:Math.round(r.width),h:Math.round(r.height)}, text:(panel.innerText||'').slice(0,250), openClass:panel.classList.contains('is-open')||panel.classList.contains('open')};
    });
    await page.screenshot({path:'D:/работа/travelhub-v2/docs/qa-screenshots-2026-08-27/mobile-home-after-toggle.png'});
    for (const [k,u] of [['reg','https://travel63test.ru/frontend/window/registration-desktop.php'],['calendar','https://travel63test.ru/frontend/window/tour-calendar.php'],['hotels','https://travel63test.ru/frontend/window/popular-hotels.php'],['countries','https://travel63test.ru/frontend/window/countries-list.php']]) {
      await page.goto(u,{waitUntil:'domcontentloaded'});
      await page.waitForTimeout(800);
      out['m_'+k] = await page.evaluate(() => ({
        h1:(document.querySelector('h1')||{}).innerText||'',
        pageScrollW:document.documentElement.scrollWidth,
        vw:innerWidth,
        hscroll: document.documentElement.scrollWidth > innerWidth+2
      }));
      await page.screenshot({path:`D:/работа/travelhub-v2/docs/qa-screenshots-2026-08-27/mobile-${k}.png`});
    }
    await ctx.close();
  }
  // wrong url statuses via fetch in page
  {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    out.wrong = {};
    for (const u of ['https://travel63test.ru/frontend/frontend/index.php','https://travel63test.ru/frontend/window/hotels.php','https://travel63test.ru/frontend/window/register.php','https://travel63test.ru/frontend/window/cabinet.php','https://travel63test.ru/frontend/window/passport.php']) {
      const resp = await page.goto(u,{waitUntil:'domcontentloaded', timeout:20000}).catch(e=>({error:String(e)}));
      if (resp && resp.status) out.wrong[u]={status:resp.status(), url:page.url(), title:await page.title()};
      else out.wrong[u]=resp;
    }
    await ctx.close();
  }
  fs.writeFileSync('D:/работа/travelhub-v2/docs/qa-screenshots-2026-08-27/deep.json', JSON.stringify(out,null,2));
  console.log(JSON.stringify(out,null,2));
  await browser.close();
})().catch(e=>{console.error(e); process.exit(1);});
