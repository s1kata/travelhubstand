const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const OUT = __dirname;

(async () => {
  const b = await chromium.launch({ headless: true });
  const extras = [];

  const ctx = await b.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await ctx.newPage();

  await page.goto('https://travel63test.ru/frontend/index.php', { waitUntil: 'networkidle', timeout: 60000 });
  await page.waitForTimeout(1500);
  await page.evaluate(() => {
    document.querySelectorAll('button').forEach((btn) => {
      if (/Закрыть|Позже/.test((btn.textContent || '').trim())) btn.click();
    });
  });

  // Quick search for tour detail link
  await page.evaluate(() => {
    const fields = ['Самара', 'Таиланд'];
    for (const txt of ['Откуда', 'Куда']) {
      const b = [...document.querySelectorAll('button')].find((x) => (x.textContent || '').includes(txt));
      b && b.click();
    }
  });
  await page.waitForTimeout(300);
  await page.evaluate(() => {
    const s = [...document.querySelectorAll('button, li')].find((x) => /Самара/.test(x.textContent || ''));
    s && s.click();
  });
  await page.waitForTimeout(300);
  await page.evaluate(() => {
    const t = [...document.querySelectorAll('button, li')].find((x) => /Таиланд/.test(x.textContent || ''));
    t && t.click();
  });
  await page.waitForTimeout(300);
  await page.evaluate(() => {
    const btn = [...document.querySelectorAll('button')].find((b) => /Найти/.test(b.textContent || ''));
    btn && btn.click();
  });
  await page.waitForTimeout(12000);

  const link = await page.evaluate(() => {
    const a = [...document.querySelectorAll('a[href]')].find(
      (x) =>
        /tour-detail|hotel-detail|detail\.php/.test(x.href || '') ||
        /Подробнее|Выбрать|Смотреть/.test(x.textContent || '')
    );
    return a ? { href: a.href, t: (a.textContent || '').trim().slice(0, 50) } : null;
  });

  if (link) {
    const r = await page.goto(link.href, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForTimeout(1500);
    await page.screenshot({ path: path.join(OUT, 'desktop-tour-detail.png') });
    const seo = await page.evaluate(() => {
      const c = document.querySelector('link[rel="canonical"]');
      return { canonical: c ? c.getAttribute('href') : null, title: document.title };
    });
    extras.push({ tourDetail: { status: r.status(), url: page.url(), link, seo } });
  } else {
    await page.screenshot({ path: path.join(OUT, 'desktop-search-no-detail-link.png') });
    extras.push({ tourDetail: 'no detail link on results page' });
  }

  for (const u of [
    'https://travel63test.ru/frontend/window/turkey-vip-hotels.php',
    'https://travel63test.ru/frontend/window/vip.php',
  ]) {
    const r = await page.goto(u, { waitUntil: 'domcontentloaded', timeout: 20000 }).catch((e) => ({ err: String(e) }));
    if (r && r.status) {
      await page.screenshot({ path: path.join(OUT, `desktop-${path.basename(u, '.php')}.png`) });
      extras.push({ page: u, status: r.status(), final: page.url(), title: await page.title() });
    }
  }

  const mctx = await b.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  const mp = await mctx.newPage();
  await mp.goto('https://travel63test.ru/frontend/index.php');
  await mp.waitForTimeout(1000);
  const sticky = await mp.evaluate(() => {
    const bar = document.querySelector('.th-lead-bar, [class*="lead-bar"]');
    const findBtn = [...document.querySelectorAll('button')].find((b) => /Найти/.test(b.textContent || ''));
    const filters = [...document.querySelectorAll('button')].find((b) => /Фильтры/.test(b.textContent || ''));
    const info = (el) => {
      if (!el) return null;
      const r = el.getBoundingClientRect();
      return { top: Math.round(r.top), bottom: Math.round(r.bottom) };
    };
    const barR = bar ? bar.getBoundingClientRect() : null;
    const overlap = (el) => {
      if (!barR || !el) return false;
      const r = el.getBoundingClientRect();
      return r.bottom > barR.top && r.top < barR.bottom;
    };
    return {
      bar: info(bar),
      findOverlap: overlap(findBtn),
      filtersOverlap: overlap(filters),
      vh: innerHeight,
    };
  });
  await mp.screenshot({ path: path.join(OUT, 'mobile-home-sticky-check.png') });
  extras.push({ mobileSticky: sticky });

  fs.writeFileSync(path.join(OUT, 'extras.json'), JSON.stringify(extras, null, 2));
  console.log(JSON.stringify(extras, null, 2));
  await b.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
