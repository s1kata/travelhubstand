/**
 * Search v2 — sticky summary chips + step-dot labels.
 * Does not replace TourSearchWizard / Coral; only enhances DOM when body.th-search-ui-v2.
 */
(function () {
  'use strict';

  if (!document.body.classList.contains('th-search-ui-v2')) return;

  var STEP_LABELS = ['Откуда', 'Куда', 'Когда', 'Ночи', 'Туристы'];
  var CHIP_DEFS = [
    { key: 'departure', label: 'Откуда' },
    { key: 'country', label: 'Куда' },
    { key: 'dates', label: 'Когда' },
    { key: 'nights', label: 'Ночи' },
    { key: 'tourists', label: 'Кто' }
  ];
  var PLACEHOLDERS = {
    country: ['Страна', 'Выберите страну'],
    dates: ['Даты', 'Выберите даты']
  };

  function qs(sel, root) {
    return (root || document).querySelector(sel);
  }

  function qsa(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }

  function isPlaceholder(key, text) {
    var t = (text || '').trim();
    if (!t) return true;
    var ph = PLACEHOLDERS[key];
    if (Array.isArray(ph)) return ph.indexOf(t) !== -1;
    return false;
  }

  function readLabel(key, root) {
    var el = qs('[data-th-label="' + key + '"]', root);
    return el ? (el.textContent || '').trim() : '';
  }

  function ensureSummary(root) {
    var existing = qs('.th-search-v2__summary', root);
    if (existing) return existing;
    var bar = qs('.th-wizard__stepbar', root);
    var wrap = document.createElement('div');
    wrap.className = 'th-search-v2__summary';
    wrap.setAttribute('aria-live', 'polite');
    CHIP_DEFS.forEach(function (def) {
      var chip = document.createElement('span');
      chip.className = 'th-search-v2__chip';
      chip.setAttribute('data-thsv2-chip', def.key);
      chip.hidden = true;
      chip.innerHTML =
        '<span class="th-search-v2__chip-key">' + def.label + '</span>' +
        '<span class="th-search-v2__chip-val"></span>';
      wrap.appendChild(chip);
    });
    if (bar && bar.parentNode) {
      bar.parentNode.insertBefore(wrap, bar.nextSibling);
    } else {
      root.insertBefore(wrap, root.firstChild);
    }
    return wrap;
  }

  function ensurePanelCopy(root) {
    var titles = [
      { n: 1, t: 'Откуда вылетаете?', h: 'Город вылета — цены и рейсы зависят от него' },
      { n: 2, t: 'Куда хотите?', h: 'Страна отдыха. Рейтинги гостей покажем в результатах' },
      { n: 3, t: 'Когда летите?', h: 'Выберите диапазон дат вылета' },
      { n: 4, t: 'Сколько ночей?', h: 'Длительность проживания в отеле' },
      { n: 5, t: 'Кто едет?', h: 'Взрослые и дети — для точной цены' }
    ];
    titles.forEach(function (item) {
      var panel = qs('.th-wizard__panel[data-panel="' + item.n + '"]', root);
      if (!panel || qs('.th-search-v2__panel-title', panel)) return;
      var h = document.createElement('h2');
      h.className = 'th-search-v2__panel-title';
      h.textContent = item.t;
      var p = document.createElement('p');
      p.className = 'th-search-v2__panel-hint';
      p.textContent = item.h;
      panel.insertBefore(p, panel.firstChild);
      panel.insertBefore(h, panel.firstChild);
    });
  }

  function ensureTrustLine(root) {
    if (qs('.th-search-v2__trust', root)) return;
    var p = document.createElement('p');
    p.className = 'th-search-v2__trust';
    p.innerHTML = 'Цены и поиск — <strong>Tourvisor</strong>';
    root.appendChild(p);
  }

  function labelDots(root) {
    qsa('.th-wizard__dot[data-thw-goto]', root).forEach(function (btn) {
      var n = parseInt(btn.getAttribute('data-thw-goto') || '0', 10);
      if (n >= 1 && n <= STEP_LABELS.length) {
        btn.setAttribute('data-thsv2-label', STEP_LABELS[n - 1]);
        btn.setAttribute('aria-label', 'Шаг ' + n + ': ' + STEP_LABELS[n - 1]);
      }
    });
  }

  function syncChips(root) {
    CHIP_DEFS.forEach(function (def) {
      var chip = qs('[data-thsv2-chip="' + def.key + '"]', root);
      if (!chip) return;
      var val = readLabel(def.key, root);
      var empty = isPlaceholder(def.key, val);
      chip.hidden = empty;
      var valEl = qs('.th-search-v2__chip-val', chip);
      if (valEl && !empty) valEl.textContent = val;
    });
  }

  function enhanceSortLabel() {
    var sel = document.getElementById('tv-sort');
    if (!sel || sel.getAttribute('data-thsv2-sort') === '1') return;
    sel.setAttribute('data-thsv2-sort', '1');
    var wrap = sel.closest('.tv-sort-rail') || sel.parentNode;
    if (wrap && !qs('.th-search-v2__sort-label', wrap)) {
      var lab = document.createElement('span');
      lab.className = 'th-search-v2__sort-label';
      lab.textContent = 'Сортировка';
      wrap.insertBefore(lab, sel);
    }
  }

  function boot() {
    var root = document.getElementById('tour-search-section');
    if (!root) return;
    root.classList.add('th-search-v2');
    var prog = qs('.th-wizard__progress', root);
    if (prog) prog.classList.remove('sr-only');
    ensureSummary(root);
    ensurePanelCopy(root);
    ensureTrustLine(root);
    labelDots(root);
    syncChips(root);
    enhanceSortLabel();

    var mo = new MutationObserver(function () {
      syncChips(root);
    });
    mo.observe(root, { subtree: true, characterData: true, childList: true });

    document.addEventListener('th:wizard-step', function () {
      syncChips(root);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
