const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const OUT = path.join(__dirname);
const BASE = 'https://travel63test.ru';
const findings = [];
let fid = 0;

function addFinding(severity, where, steps, expected, actual, screenshot) {
  findings.push({
    id: `TH-${String(++fid).padStart(3, '0')}`,
    severity,
    where,
    steps,
    expected,
    actual,
    screenshot: screenshot ? path.basename(screenshot) : '',
  });
}

const passChecklist = [];

async function shot(page, name) {
  const p = path.join(OUT, name);
  await page.screenshot({ path: p, fullPage: false }).catch(() => {});
  return p;
}

async function dismissPopups(page) {
  await page.evaluate(() => {
    document.querySelectorAll('button').forEach((b) => {
      const t = (b.textContent || '').trim();
      if (/Закрыть|Позже|Нет|^×$/.test(t) || b.getAttribute('aria-label') === 'Закрыть') {
        try { b.click(); } catch (e) {}
      }
    });
  });
  await page.waitForTimeout(200);
}

async function getSeo(page) {
  return page.evaluate(() => {
    const canon = document.querySelector('link[rel="canonical"]');
    const og = document.querySelector('meta[property="og:url"]');
    return {
      canonical: canon ? canon.getAttribute('href') : null,
      ogUrl: og ? og.getAttribute('content') : null,
      title: document.title,
      badLinks: [...document.querySelectorAll('a[href]')]
        .map((a) => a.getAttribute('href'))
        .filter((h) => h && /frontend\/frontend/.test(h)),
    };
  });
}

async function pageHealth(page, vp) {
  return page.evaluate(() => {
    const docW = document.documentElement.scrollWidth;
    const winW = window.innerWidth;
    const h1 = (document.querySelector('h1') || {}).innerText || '';
    const notFound = /404|не найден|Not Found|страница не найдена/i.test(
      document.body.innerText.slice(0, 2000) + document.title
    );
    const stickyBar = document.querySelector('.th-lead-bar, .lead-bar, [class*="lead-bar"]');
    let stickyOverlap = null;
    if (stickyBar) {
      const sr = stickyBar.getBoundingClientRect();
      const submit = [...document.querySelectorAll('button[type="submit"], input[type="submit"]')]
        .find((el) => {
          const r = el.getBoundingClientRect();
          return r.width > 0 && r.height > 0;
        });
      if (submit) {
        const br = submit.getBoundingClientRect();
        stickyOverlap = br.bottom > sr.top && br.top < sr.bottom;
      }
    }
    return {
      hscroll: docW > winW + 2,
      docW,
      winW,
      h1: h1.slice(0, 100),
      notFound,
      stickyOverlap,
    };
  });
}

async function testPages(browser) {
  const pages = [
    ['home', `${BASE}/frontend/index.php`],
    ['countries-list', `${BASE}/frontend/window/countries-list.php`],
    ['country-turkey', `${BASE}/frontend/window/countries/turkey.php`],
    ['popular-hotels', `${BASE}/frontend/window/popular-hotels.php`],
    ['promotions', `${BASE}/frontend/window/promotions.php`],
    ['tour-calendar', `${BASE}/frontend/window/tour-calendar.php`],
    ['services', `${BASE}/frontend/window/services.php`],
    ['about', `${BASE}/frontend/window/about.php`],
    ['offices', `${BASE}/frontend/window/offices.php`],
    ['offices-samara', `${BASE}/frontend/window/offices/samara-offices.php`],
    ['offices-moscow', `${BASE}/frontend/window/offices/moscow-offices.php`],
    ['login', `${BASE}/frontend/window/login.php`],
    ['registration', `${BASE}/frontend/window/registration.php`],
    ['login-desktop', `${BASE}/frontend/window/login-desktop.php`],
    ['registration-desktop', `${BASE}/frontend/window/registration-desktop.php`],
    ['contacts', `${BASE}/frontend/window/contacts.php`],
    ['vip', `${BASE}/frontend/window/vip-tours.php`],
  ];

  for (const [vpName, vpOpts] of [
    ['desktop', { viewport: { width: 1280, height: 800 } }],
    [
      'mobile',
      {
        viewport: { width: 390, height: 844 },
        isMobile: true,
        hasTouch: true,
        userAgent:
          'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
      },
    ],
  ]) {
    const ctx = await browser.newContext(vpOpts);
    const page = await ctx.newPage();
    const consoleErrors = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error') consoleErrors.push(msg.text().slice(0, 200));
    });
    page.on('pageerror', (err) => consoleErrors.push(String(err).slice(0, 200)));

    for (const [name, url] of pages) {
      const item = { name, url, viewport: vpName, consoleErrors: [] };
      try {
        const resp = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
        item.status = resp ? resp.status() : null;
        await page.waitForTimeout(name.includes('hotels') ? 8000 : 900);
        await dismissPopups(page);
        item.seo = await getSeo(page);
        item.health = await pageHealth(page);
        item.consoleErrors = [...new Set(consoleErrors)].slice(0, 15);
        consoleErrors.length = 0;

        const sc = await shot(page, `${vpName}-${name}.png`);
        item.screenshot = sc;

        if (item.status === 200 && !item.health.notFound) {
          passChecklist.push(`${vpName}: ${name} — HTTP 200, renders`);
        } else {
          addFinding(
            item.status !== 200 ? 'High' : 'Med',
            `${vpName} ${name}`,
            `GET ${url}`,
            'HTTP 200, page content',
            `status=${item.status}, notFound=${item.health.notFound}`,
            sc
          );
        }

        if (item.health.hscroll) {
          addFinding('Med', `${vpName} ${name}`, 'Load page', 'No horizontal scroll', `scrollWidth=${item.health.docW} > vw=${item.health.winW}`, sc);
        }

        if (name === 'home' && item.seo.canonical && /frontend\/frontend|http:\/\//.test(item.seo.canonical)) {
          addFinding('High', `${vpName} home SEO`, 'Inspect canonical', 'https://travel63test.ru/frontend/index.php', item.seo.canonical, sc);
        }

        if (name === 'country-turkey' && item.seo.canonical && /frontend\/frontend|countries\/frontend/.test(item.seo.canonical)) {
          addFinding('High', `${vpName} turkey canonical`, 'Inspect canonical', 'Single path https', item.seo.canonical, sc);
        }

        if (item.health.stickyOverlap && (name.includes('login') || name.includes('registration'))) {
          addFinding('High', `${vpName} ${name}`, 'Scroll to submit', 'Submit visible above sticky bar', 'Submit overlaps sticky lead bar', sc);
        }

        if (name === 'tour-calendar') {
          const cal = await page.evaluate(() => {
            const months = [...document.querySelectorAll('h2, h3, .calendar-month, [class*="month"]')]
              .map((el) => (el.textContent || '').trim())
              .filter((t) => /январ|феврал|март|апрел|май|июн|июл|август|сентябр|октябр|ноябр|декабр/i.test(t));
            return { monthNames: months.slice(0, 6), count: months.length };
          });
          item.calendar = cal;
          if (cal.count === 0) {
            addFinding('Med', `${vpName} calendar`, 'Open tour-calendar', 'Month names visible', 'No month headings found', sc);
          } else {
            passChecklist.push(`${vpName}: calendar — ${cal.count} month labels`);
          }
        }

        if (name === 'home' && vpName === 'mobile') {
          await testMobileNav(page, sc);
          await testHomeModals(page, vpName);
        }
        if (name === 'home' && vpName === 'desktop') {
          await testHomeModals(page, vpName);
        }
      } catch (e) {
        item.error = String(e);
        addFinding('High', `${vpName} ${name}`, `Navigate ${url}`, 'Page loads', String(e), '');
      }
      if (!global.pageResults) global.pageResults = [];
      global.pageResults.push(item);
    }
    await ctx.close();
  }
}

async function testMobileNav(page, homeSc) {
  await page.goto(`${BASE}/frontend/index.php`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(800);
  await dismissPopups(page);
  const toggle = page.locator('.site-header-mobile-toggle, .header__burger, .burger, [aria-label*="меню" i]').first();
  if (await toggle.count()) {
    await toggle.click().catch(() => {});
    await page.waitForTimeout(500);
    const navData = await page.evaluate(() => {
      const panel = document.querySelector('.site-header-mobile-panel, .mobile-menu, .header-mobile-panel');
      if (!panel) return { missing: true };
      const s = getComputedStyle(panel);
      const links = [...panel.querySelectorAll('a, button')].map((el) => {
        const r = el.getBoundingClientRect();
        return {
          t: (el.textContent || '').trim().slice(0, 40),
          top: Math.round(r.top),
          bottom: Math.round(r.bottom),
          inView: r.top >= 0 && r.bottom <= innerHeight,
        };
      });
      const clipped = links.filter((l) => l.bottom > innerHeight && l.top < innerHeight);
      const belowFold = links.filter((l) => l.top >= innerHeight);
      const canScroll = s.overflowY === 'auto' || s.overflowY === 'scroll';
      return {
        overflowY: s.overflowY,
        canScroll,
        panelH: Math.round(panel.getBoundingClientRect().height),
        vh: innerHeight,
        clipped: clipped.map((l) => l.t),
        belowFold: belowFold.map((l) => l.t),
        loginVisible: links.some((l) => /Войти/.test(l.t) && l.inView),
        regVisible: links.some((l) => /Регистрац/.test(l.t) && l.inView),
      };
    });
    const sc = await shot(page, 'mobile-nav-open.png');
    if (navData.belowFold?.length && !navData.canScroll) {
      addFinding(
        'High',
        'mobile hamburger menu',
        'Open burger, inspect bottom items',
        'All nav items reachable (scroll or fit)',
        `overflow-y=${navData.overflowY}; below fold: ${navData.belowFold.join(', ')}`,
        sc
      );
    } else if (navData.canScroll || navData.belowFold?.length === 0) {
      passChecklist.push('mobile: hamburger menu — items reachable or scrollable');
    }
  }
}

async function clickByText(page, texts) {
  return page.evaluate((txts) => {
    for (const txt of txts) {
      const btn = [...document.querySelectorAll('button, [role=button]')].find((b) =>
        (b.textContent || '').includes(txt)
      );
      if (btn) {
        btn.click();
        return { ok: true, label: txt };
      }
    }
    return { ok: false };
  }, texts);
}

async function testHomeModals(page, vp) {
  await page.goto(`${BASE}/frontend/index.php`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1000);
  await dismissPopups(page);

  const modalTests = [
    { keys: ['Откуда', 'Город вылета', 'Самара'], file: 'departure', labels: ['Откуда', 'Город'] },
    { keys: ['Куда', 'Страна', 'Турция'], file: 'destination', labels: ['Куда', 'Страна'] },
    { keys: ['Даты вылета', 'Даты', 'Когда'], file: 'dates', labels: ['Даты'] },
    { keys: ['Ночей', 'ноч'], file: 'nights', labels: ['Ноч'] },
    { keys: ['Туристы', 'турист'], file: 'tourists', labels: ['Турист'] },
    { keys: ['Фильтры', 'фильтр'], file: 'filters', labels: ['Фильтр'] },
  ];

  for (const mt of modalTests) {
    const opened = await clickByText(page, mt.keys);
    await page.waitForTimeout(600);
    const vis = await page.evaluate(() => {
      const nodes = [
        ...document.querySelectorAll(
          '.th-coral-popup, .th-sheet, .flatpickr-calendar, .tv-filters-modal, [class*="popup"], [class*="modal"], [class*="sheet"]'
        ),
      ];
      return nodes
        .filter((el) => {
          const s = getComputedStyle(el);
          const r = el.getBoundingClientRect();
          return (
            s.display !== 'none' &&
            s.visibility !== 'hidden' &&
            Number(s.opacity) > 0.1 &&
            r.width > 30 &&
            r.height > 30 &&
            !el.classList.contains('hidden')
          );
        })
        .map((el) => ({
          cls: el.className.toString().slice(0, 80),
          w: Math.round(el.getBoundingClientRect().width),
          h: Math.round(el.getBoundingClientRect().height),
        }));
    });
    const sc = await shot(page, `${vp}-modal-${mt.file}.png`);
    if (opened.ok && vis.length > 0) {
      passChecklist.push(`${vp}: home modal ${mt.file} opens`);
    } else if (!opened.ok) {
      addFinding('Low', `${vp} home ${mt.file}`, `Click ${mt.keys.join('/')}`, 'Modal/sheet opens', 'Trigger button not found', sc);
    } else {
      addFinding('Med', `${vp} home ${mt.file}`, `Click ${opened.label}`, 'Modal visible', 'No visible popup after click', sc);
    }
    await page.keyboard.press('Escape');
    await page.waitForTimeout(200);
    await page.evaluate(() => {
      document.querySelectorAll('.th-coral-popup__close, button').forEach((b) => {
        if (/Закрыть|^×$/.test((b.textContent || '').trim())) try { b.click(); } catch (e) {}
      });
    });
  }

  // Hotels tab
  const hotelsTab = await page.evaluate(() => {
    const t = [...document.querySelectorAll('[role=tab]')].find((x) => /Отели/.test(x.textContent || ''));
    if (t) {
      t.click();
      return true;
    }
    return false;
  });
  await page.waitForTimeout(500);
  if (hotelsTab) {
    passChecklist.push(`${vp}: hotels tab clickable`);
    await shot(page, `${vp}-hotels-tab.png`);
  }

  // Charter/direct toggles in filters
  await clickByText(page, ['Фильтры']);
  await page.waitForTimeout(400);
  const toggles = await page.evaluate(() =>
    [...document.querySelectorAll('label, button, span')]
      .map((el) => (el.textContent || '').trim())
      .filter((t) => /чартер|прямой|direct|charter/i.test(t))
      .slice(0, 6)
  );
  if (toggles.length) passChecklist.push(`${vp}: charter/direct toggles visible: ${toggles.join(', ')}`);
  await shot(page, `${vp}-filters-toggles.png`);
  await page.keyboard.press('Escape');
}

async function testSearchFlow(browser) {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await ctx.newPage();
  const consoleErrors = [];
  page.on('console', (m) => { if (m.type() === 'error') consoleErrors.push(m.text()); });

  try {
    await page.goto(`${BASE}/frontend/index.php`, { waitUntil: 'networkidle', timeout: 60000 });
    await page.waitForTimeout(1500);
    await dismissPopups(page);

    // Departure: Samara or Moscow
    await clickByText(page, ['Откуда', 'Самара']);
    await page.waitForTimeout(500);
    await page.evaluate(() => {
      const item = [...document.querySelectorAll('button, li, [role=option]')].find((el) =>
        /Самара|Moscow|Москва/.test(el.textContent || '')
      );
      item && item.click();
    });
    await page.waitForTimeout(400);

    // Destination Turkey or Thailand
    await clickByText(page, ['Куда', 'Страна']);
    await page.waitForTimeout(500);
    await page.evaluate(() => {
      const item = [...document.querySelectorAll('button, li, [role=option]')].find((el) =>
        /Турци|Таиланд|Turkey|Thailand/.test(el.textContent || '')
      );
      item && item.click();
    });
    await page.waitForTimeout(400);

    // Dates - click first available in calendar if open, else open dates
    await clickByText(page, ['Даты вылета', 'Даты']);
    await page.waitForTimeout(600);
    await page.evaluate(() => {
      const day = document.querySelector('.flatpickr-day:not(.flatpickr-disabled):not(.prevMonthDay):not(.nextMonthDay)');
      day && day.click();
      setTimeout(() => {
        const day2 = [...document.querySelectorAll('.flatpickr-day:not(.flatpickr-disabled)')].find(
          (d, i, arr) => i > arr.indexOf(day) + 3
        );
        day2 && day2.click();
      }, 200);
    });
    await page.waitForTimeout(800);
    await page.keyboard.press('Escape');
    await page.waitForTimeout(200);

    // Nights
    await clickByText(page, ['Ночей']);
    await page.waitForTimeout(400);
    await page.evaluate(() => {
      const n = [...document.querySelectorAll('button')].find((b) => /^7$|^10$|^14$/.test((b.textContent || '').trim()));
      n && n.click();
    });
    await page.waitForTimeout(300);
    await page.keyboard.press('Escape');

    await shot(page, 'desktop-search-filled.png');

    // Search
    await page.evaluate(() => {
      const btn = [...document.querySelectorAll('button')].find((b) =>
        /Найти|Искать|Поиск/.test(b.textContent || '')
      );
      btn && btn.click();
    });

    await page.waitForTimeout(12000);
    const results = await page.evaluate(() => {
      const cards = document.querySelectorAll(
        '.tv-result, .search-result, [class*="result-card"], [class*="hotel-card"], .th-search-result'
      );
      const prices = [...document.body.innerText.matchAll(/(\d[\d\s]{2,})\s*₽|от\s+(\d[\d\s]+)\s*₽/gi)].slice(0, 10);
      const loading = /Загрузка|Ищем|подождите/i.test(document.body.innerText.slice(0, 3000));
      const noResults = /ничего не найден|нет туров|не найдено/i.test(document.body.innerText);
      return {
        cardCount: cards.length,
        priceSamples: prices.map((m) => m[0]).slice(0, 5),
        loading,
        noResults,
        snippet: document.body.innerText.slice(0, 500),
      };
    });
    const sc = await shot(page, 'desktop-search-results.png');

    if (results.cardCount > 0 || results.priceSamples.length > 0) {
      passChecklist.push(`search: results appeared (cards=${results.cardCount}, prices=${results.priceSamples.length})`);
    } else if (results.loading) {
      addFinding('Med', 'desktop search', 'Fill form + Найти', 'Results with prices >0', 'Still loading after 12s', sc);
    } else if (results.noResults) {
      addFinding('Low', 'desktop search', 'Samara/Moscow + Turkey + dates', 'Some results', 'Explicit no-results message', sc);
    } else {
      addFinding('Med', 'desktop search', 'Fill + search', 'Tour cards/prices', `cards=${results.cardCount}`, sc);
    }

    if (consoleErrors.length > 5) {
      addFinding('Med', 'search console', 'Run search', 'No breaking JS errors', `${consoleErrors.length} console errors`, sc);
    }

    global.searchResult = { results, consoleErrors: consoleErrors.slice(0, 10) };
  } catch (e) {
    addFinding('High', 'search flow', 'Full search', 'Results', String(e), '');
  }
  await ctx.close();
}

async function testRedirects(browser) {
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  const redirects = [
    ['login.html', `${BASE}/frontend/window/login.html`],
    ['registration.html', `${BASE}/frontend/window/registration.html`],
    ['double-frontend', `${BASE}/frontend/frontend/index.php`],
  ];
  global.redirectResults = {};
  for (const [name, url] of redirects) {
    const resp = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 20000 }).catch((e) => ({ err: String(e) }));
    if (resp && resp.status) {
      global.redirectResults[name] = { status: resp.status(), final: page.url() };
      if (name.includes('html') && resp.status() === 200 && page.url().includes('.html')) {
        addFinding('Med', `legacy ${name}`, `GET ${url}`, '301 to .php', `200 stub at ${page.url()}`, '');
      } else if (name === 'double-frontend') {
        if (resp.status() === 404) passChecklist.push('double /frontend/frontend/ returns 404 (confirms bad canonical URL)');
      }
    }
  }
  await ctx.close();
}

async function testHeaderFooterLinks(browser) {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await ctx.newPage();
  await page.goto(`${BASE}/frontend/index.php`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1000);
  const links = await page.evaluate(() => {
    const header = document.querySelector('header, .site-header, .th-header');
    const footer = document.querySelector('footer, .site-footer, .th-footer');
    const pick = (root) =>
      root
        ? [...root.querySelectorAll('a[href]')].map((a) => ({
            t: (a.textContent || '').trim().slice(0, 50),
            href: a.href,
          }))
        : [];
    return { header: pick(header).slice(0, 25), footer: pick(footer).slice(0, 30) };
  });
  global.navLinks = links;
  const broken = [];
  for (const group of ['header', 'footer']) {
    for (const l of links[group].slice(0, 12)) {
      if (!l.href || l.href.startsWith('javascript:') || l.href.startsWith('tel:') || l.href.startsWith('mailto:')) continue;
      try {
        const r = await page.goto(l.href, { waitUntil: 'domcontentloaded', timeout: 20000 });
        const st = r ? r.status() : 0;
        if (st >= 400) broken.push({ ...l, status: st });
      } catch (e) {
        broken.push({ ...l, error: String(e).slice(0, 80) });
      }
    }
  }
  if (broken.length) {
    addFinding('Med', 'header/footer nav', 'Follow first 12 links', 'All 200/301', `${broken.length} broken: ${broken.map((b) => b.t).join(', ')}`, '');
  } else {
    passChecklist.push('header/footer: sampled links OK');
  }
  await ctx.close();
}

function generateReport() {
  const deployNote =
    findings.some((f) => f.actual && /frontend\/frontend|http:\/\//.test(f.actual)) ||
    (global.pageResults || []).some((p) => p.seo?.canonical && /http:\/\//.test(p.seo.canonical))
      ? '**Deploy looks like OLD/stale:** canonical still uses `http://` and/or doubled `/frontend/frontend/` paths (known B1/B2 issues from prior QA). Recent SEO fixes may NOT be on this server.'
      : 'Deploy appears to include recent SEO fixes (no obvious double-frontend in spot checks).';

  let md = `# QA Full Smoke — travel63test.ru\n\n`;
  md += `**Date:** 2026-08-27  \n`;
  md += `**Base URL:** ${BASE}  \n`;
  md += `**Tool:** Playwright Chromium headless  \n`;
  md += `**Viewports:** desktop 1280×800, mobile 390×844  \n`;
  md += `**Screenshots:** \`docs/qa-full-smoke-2026-08-27/\`  \n\n`;
  md += `## Executive summary\n\n`;
  md += `- Findings: **${findings.length}** (${findings.filter((f) => f.severity === 'High').length} High, ${findings.filter((f) => f.severity === 'Med').length} Med, ${findings.filter((f) => f.severity === 'Low').length} Low)  \n`;
  md += `- PASS checklist items: **${passChecklist.length}**  \n`;
  md += `- ${deployNote}\n\n`;

  md += `## Severity table\n\n`;
  md += `| ID | Severity | Where | Steps | Expected | Actual | Screenshot |\n`;
  md += `|----|----------|-------|-------|----------|--------|------------|\n`;
  for (const f of findings) {
    md += `| ${f.id} | **${f.severity}** | ${f.where} | ${f.steps} | ${f.expected} | ${f.actual} | ${f.screenshot || '—'} |\n`;
  }
  if (!findings.length) md += `| — | — | — | — | — | No blocking issues recorded | — |\n`;

  md += `\n## PASS checklist\n\n`;
  for (const p of passChecklist) md += `- [x] ${p}\n`;

  md += `\n## SEO spot-check\n\n`;
  const home = (global.pageResults || []).find((p) => p.name === 'home' && p.viewport === 'desktop');
  if (home?.seo) {
    md += `- **Home canonical:** \`${home.seo.canonical || 'missing'}\`\n`;
    md += `- **Home og:url:** \`${home.seo.ogUrl || 'missing'}\`\n`;
    md += `- **Bad in-page links (frontend/frontend):** ${home.seo.badLinks?.length || 0}\n`;
  }
  const turkey = (global.pageResults || []).find((p) => p.name === 'country-turkey' && p.viewport === 'desktop');
  if (turkey?.seo) md += `- **Turkey canonical:** \`${turkey.seo.canonical || 'missing'}\`\n`;

  md += `\n## Search flow\n\n`;
  if (global.searchResult) {
    md += `\`\`\`json\n${JSON.stringify(global.searchResult, null, 2)}\n\`\`\`\n`;
  }

  md += `\n## Legacy redirects\n\n`;
  if (global.redirectResults) {
    md += `\`\`\`json\n${JSON.stringify(global.redirectResults, null, 2)}\n\`\`\`\n`;
  }

  md += `\n## Page status matrix\n\n`;
  md += `| Viewport | Page | HTTP | H-scroll | Canonical issue |\n`;
  md += `|----------|------|------|----------|----------------|\n`;
  for (const p of global.pageResults || []) {
    const canonBad = p.seo?.canonical && (/frontend\/frontend|http:\/\//.test(p.seo.canonical)) ? 'YES' : '';
    md += `| ${p.viewport} | ${p.name} | ${p.status} | ${p.health?.hscroll ? 'YES' : 'no'} | ${canonBad} |\n`;
  }

  fs.writeFileSync(path.join(path.dirname(OUT), 'QA-full-smoke-2026-08-27.md'), md);
  fs.writeFileSync(path.join(OUT, 'findings.json'), JSON.stringify({ findings, passChecklist, pageResults: global.pageResults, searchResult: global.searchResult, redirectResults: global.redirectResults, navLinks: global.navLinks }, null, 2));
}

(async () => {
  global.pageResults = [];
  const browser = await chromium.launch({ headless: true });
  try {
    await testPages(browser);
    await testSearchFlow(browser);
    await testRedirects(browser);
    await testHeaderFooterLinks(browser);
    generateReport();
    console.log('DONE findings=', findings.length, 'pass=', passChecklist.length);
  } finally {
    await browser.close();
  }
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
