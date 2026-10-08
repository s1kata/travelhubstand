const { chromium } = require(process.env.PW_PKG || 'playwright');
const fs = require('fs');
const path = require('path');

const OUT = 'D:/работа/travelhub-v2/docs/qa-screenshots-2026-08-27';
const BASE = 'https://travel63test.ru';
const pages = [
  ['home', `${BASE}/frontend/index.php`],
  ['promotions', `${BASE}/frontend/window/promotions.php`],
  ['countries', `${BASE}/frontend/window/countries-list.php`],
  ['hotels', `${BASE}/frontend/window/popular-hotels.php`],
  ['offices', `${BASE}/frontend/window/offices.php`],
  ['calendar', `${BASE}/frontend/window/tour-calendar.php`],
  ['services', `${BASE}/frontend/window/services.php`],
  ['about', `${BASE}/frontend/window/about.php`],
  ['contacts', `${BASE}/frontend/window/contacts.php`],
  ['profile', `${BASE}/frontend/window/profile.php`],
  ['passport', `${BASE}/frontend/window/passport-data.php`],
  ['registration', `${BASE}/frontend/window/registration-desktop.php`],
  ['login', `${BASE}/frontend/window/login-desktop.php`],
];

function overflowCheck() {
  const issues = [];
  const docW = document.documentElement.scrollWidth;
  const winW = window.innerWidth;
  if (docW > winW + 2) issues.push({type:'page-hscroll', docW, winW});
  const logo = document.querySelector('.header__logo, a.header__logo, .site-header .logo');
  if (logo) {
    const r = logo.getBoundingClientRect();
    if (logo.scrollWidth > logo.clientWidth + 2) issues.push({type:'logo-truncate', text: (logo.textContent||'').trim(), sw: logo.scrollWidth, cw: logo.clientWidth, rect: {x:r.x,w:r.width}});
  }
  const auth = [...document.querySelectorAll('a,button')].filter(a => /Войти|Профиль|Выход|Регистрац/.test((a.textContent||'')+ (a.getAttribute('aria-label')||''))).slice(0,8).map(a => ({t:(a.textContent||'').trim().replace(/\s+/g,' '), href:a.href||null, right:Math.round(a.getBoundingClientRect().right)}));
  const hamburger = !!document.querySelector('.site-header-mobile-toggle, .header__burger, [aria-label*="меню" i], button.menu-toggle, .mobile-nav-toggle');
  const title = document.title;
  const h1 = (document.querySelector('h1')||{}).innerText || '';
  const notFound = /404|не найден|Not Found|страница не найдена/i.test(document.body.innerText.slice(0,1500)+title);
  const header = document.querySelector('header, .site-header, .th-header');
  let headerOverflow = false;
  if (header && header.scrollWidth > header.clientWidth + 2) headerOverflow = true;
  return {title, h1: h1.slice(0,80), auth, hamburger, notFound, headerOverflow, issues, url: location.href, vw: winW, vh: innerHeight};
}

(async () => {
  const browser = await chromium.launch({headless: true});
  const findings = [];
  for (const [vpName, size] of [['desktop',{width:1280,height:800}], ['mobile',{width:390,height:844}]]) {
    const context = await browser.newContext({
      viewport: size,
      userAgent: vpName==='mobile'
        ? 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1'
        : undefined,
      isMobile: vpName==='mobile',
      hasTouch: vpName==='mobile',
    });
    const page = await context.newPage();
    for (const [name, url] of pages) {
      const item = {name, url, viewport: vpName};
      try {
        const resp = await page.goto(url, {waitUntil: 'domcontentloaded', timeout: 45000});
        item.status = resp ? resp.status() : null;
        await page.waitForTimeout(900);
        await page.evaluate(() => {
          document.querySelectorAll('button').forEach(b => {
            const t = (b.textContent||'').trim();
            if (/Закрыть|Позже|Нет|^×$/.test(t) || b.getAttribute('aria-label')==='Закрыть') {
              try { b.click(); } catch(e){}
            }
          });
        });
        await page.waitForTimeout(300);
        item.dom = await page.evaluate(overflowCheck);
        const shot = path.join(OUT, `${vpName}-${name}.png`);
        await page.screenshot({path: shot, fullPage: false});
        item.screenshot = shot;
        if (vpName==='mobile') {
          const toggles = await page.$$('.site-header-mobile-toggle, .header__burger, .burger, [aria-label*="Меню" i], [aria-label*="меню" i], button.menu-toggle');
          item.mobileToggleCount = toggles.length;
          if (toggles[0]) {
            await toggles[0].click().catch(()=>{});
            await page.waitForTimeout(400);
            item.mobileNav = await page.evaluate(() => {
              const p = document.querySelector('.site-header-mobile-panel, .mobile-menu, .header-mobile-panel');
              if (!p) return null;
              const s = getComputedStyle(p); const r = p.getBoundingClientRect();
              return {cls:p.className.toString().slice(0,80), vis:s.visibility, display:s.display, op:s.opacity, w:Math.round(r.width), h:Math.round(r.height), text:(p.innerText||'').slice(0,120)};
            });
            await page.screenshot({path: path.join(OUT, `${vpName}-${name}-nav.png`), fullPage:false}).catch(()=>{});
          }
        }
        if (name==='home' && vpName==='desktop') {
          for (const label of ['Даты вылета', 'Ночей', 'Туристы', 'Фильтры']) {
            const opened = await page.evaluate((lab) => {
              const b = [...document.querySelectorAll('button')].find(x => (x.textContent||'').includes(lab));
              if (!b) return {ok:false, reason:'no-btn'};
              b.click();
              return {ok:true};
            }, label);
            await page.waitForTimeout(500);
            const vis = await page.evaluate(() => {
              const nodes = [...document.querySelectorAll('.th-coral-popup, .tv-filters-modal, .th-sheet, .flatpickr-calendar, [class*="filter"]')];
              return nodes.filter(el => {
                const s=getComputedStyle(el); const r=el.getBoundingClientRect();
                return s.display!=='none' && s.visibility!=='hidden' && Number(s.opacity)>0 && r.width>20 && r.height>20 && !el.classList.contains('hidden');
              }).map(el => ({cls:el.className.toString().slice(0,90), w:Math.round(el.getBoundingClientRect().width), h:Math.round(el.getBoundingClientRect().height), text:(el.innerText||'').slice(0,60)}));
            });
            item['modal_'+label] = {opened, visible: vis};
            await page.screenshot({path: path.join(OUT, 'desktop-home-modal-'+label.replace(/\s+/g,'-')+'.png'), fullPage:false}).catch(()=>{});
            await page.keyboard.press('Escape');
            await page.waitForTimeout(200);
            await page.evaluate(() => {
              document.querySelectorAll('.th-coral-popup__close, button').forEach(b=>{
                if (/Закрыть|^×$/.test((b.textContent||'').trim()) || b.classList.contains('th-coral-popup__close')) try{b.click()}catch(e){}
              });
            });
            await page.waitForTimeout(200);
          }
          await page.evaluate(() => {
            const t = [...document.querySelectorAll('[role=tab]')].find(x=>/Отели/.test(x.textContent||''));
            t && t.click();
          });
          await page.waitForTimeout(500);
          item.hotelsTab = await page.evaluate(() => ({
            selected: ([...document.querySelectorAll('[role=tab][aria-selected=true]')].map(x=>x.textContent.trim())[0]||''),
            fields: [...document.querySelectorAll('button[aria-current]')].map(b=>(b.textContent||'').trim().replace(/\s+/g,' ').slice(0,50))
          }));
          await page.screenshot({path: path.join(OUT, 'desktop-home-hotels-tab.png'), fullPage:false});
        }
      } catch (e) {
        item.error = String(e);
      }
      findings.push(item);
      console.log(JSON.stringify({done: vpName+'/'+name, status: item.status, issues: item.dom && item.dom.issues, auth: item.dom && item.dom.auth, err: item.error}));
    }
    await context.close();
  }
  fs.writeFileSync(path.join(OUT, 'findings.json'), JSON.stringify(findings, null, 2));
  console.log('WROTE', findings.length);
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
