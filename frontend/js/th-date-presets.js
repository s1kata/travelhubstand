/**
 * Пресеты дат / ночей для поиска туров (Flatpickr).
 * Один тап → готовый диапазон; календарь — только для точных дат.
 */
(function (global) {
  'use strict';

  var MONTHS_RU = [
    'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
    'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'
  ];
  var MONTHS_RU_SHORT = [
    'Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн',
    'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'
  ];

  function startOfDay(d) {
    var x = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    return x;
  }

  function today() {
    return startOfDay(new Date());
  }

  /** Диапазон целого месяца (offset 0 = текущий остаток, 1 = следующий …). */
  function getMonthRange(offset) {
    var t = today();
    var y = t.getFullYear();
    var m = t.getMonth() + (offset | 0);
    var y2 = y + Math.floor(m / 12);
    var m2 = ((m % 12) + 12) % 12;
    var start;
    var end = new Date(y2, m2 + 1, 0);
    if ((offset | 0) === 0) {
      start = t;
      if (start > end) {
        return getMonthRange(1);
      }
    } else {
      start = new Date(y2, m2, 1);
      if (start < t) start = t;
    }
    return [start, end];
  }

  function getRange(preset) {
    var t = today();
    var f = new Date(t);
    var to = new Date(t);
    var key = String(preset || '');

    if (/^m(\d+)$/.test(key)) {
      return getMonthRange(parseInt(RegExp.$1, 10));
    }

    if (key === '3d') {
      f.setDate(t.getDate() + 1);
      to.setDate(t.getDate() + 3);
    } else if (key === '14d' || key === 'soon') {
      f.setTime(t.getTime());
      to.setDate(t.getDate() + 14);
    } else if (key === 'week' || key === '7d') {
      f.setDate(t.getDate() + 1);
      to.setDate(t.getDate() + 7);
    } else if (key === 'month' || key === 'endmonth' || key === 'm0') {
      return getMonthRange(0);
    } else if (key === 'nextmonth' || key === 'm1') {
      return getMonthRange(1);
    } else {
      f.setDate(t.getDate() + 1);
      to.setDate(t.getDate() + 7);
    }
    return [f, to];
  }

  function fmtFlatpickr(d) {
    var dd = String(d.getDate()).padStart(2, '0');
    var mm = String(d.getMonth() + 1).padStart(2, '0');
    return dd + '-' + mm + '-' + d.getFullYear();
  }

  function apply(preset, opts) {
    opts = opts || {};
    var dates = getRange(preset);
    var main = opts.mainPicker || opts.picker;
    var inline = opts.inlinePicker;
    var inp = opts.input;
    if (main && typeof main.setDate === 'function') {
      main.setDate(dates, true);
    } else if (inp) {
      inp.value = fmtFlatpickr(dates[0]) + ' — ' + fmtFlatpickr(dates[1]);
      try { inp.dispatchEvent(new Event('change', { bubbles: true })); } catch (e) {}
    }
    if (inline && typeof inline.setDate === 'function') {
      try { inline.setDate(dates, false); } catch (e2) {}
    }
    if (typeof opts.onApplied === 'function') {
      opts.onApplied(dates, preset);
    }
    return dates;
  }

  /** Быстрые пресеты (без месяцев). */
  function quickPresets() {
    return [
      { key: '14d', label: 'Ближайшие 2 недели', short: '2 недели', hint: 'Самый частый выбор' },
      { key: 'week', label: 'Неделя', short: 'Неделя', hint: '' },
      { key: '3d', label: '3 дня', short: '3 дня', hint: '' }
    ];
  }

  /** Следующие месяцы (включая текущий остаток). */
  function monthPresets(count) {
    count = count || 4;
    var list = [];
    var t = today();
    for (var i = 0; i < count; i++) {
      var m = t.getMonth() + i;
      var y = t.getFullYear() + Math.floor(m / 12);
      var mi = ((m % 12) + 12) % 12;
      var label = MONTHS_RU[mi];
      if (i > 0 && mi === 0) label = label + ' ' + y;
      list.push({
        key: 'm' + i,
        label: label,
        short: MONTHS_RU_SHORT[mi],
        hint: i === 0 ? 'Остаток месяца' : ''
      });
    }
    return list;
  }

  function allPresets() {
    return quickPresets().concat(monthPresets(4));
  }

  function renderChips(container, onSelect, options) {
    if (!container) return;
    options = options || {};
    var withMonths = options.withMonths !== false;
    var presets = withMonths ? allPresets() : quickPresets();

    container.innerHTML = '';
    container.classList.add('tv-date-presets');

    function addGroup(title, items, groupClass) {
      if (!items.length) return;
      if (title) {
        var h = document.createElement('div');
        h.className = 'tv-date-presets__label';
        h.textContent = title;
        container.appendChild(h);
      }
      var row = document.createElement('div');
      row.className = 'tv-date-presets__row' + (groupClass ? ' ' + groupClass : '');
      items.forEach(function (p) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'tv-date-preset-chip';
        btn.setAttribute('data-preset', p.key);
        btn.setAttribute('aria-pressed', 'false');
        btn.textContent = p.short || p.label;
        if (p.hint) btn.title = p.hint;
        btn.addEventListener('click', function () {
          container.querySelectorAll('.tv-date-preset-chip').forEach(function (c) {
            c.classList.remove('active');
            c.setAttribute('aria-pressed', 'false');
          });
          btn.classList.add('active');
          btn.setAttribute('aria-pressed', 'true');
          if (typeof onSelect === 'function') onSelect(p.key, btn);
        });
        row.appendChild(btn);
      });
      container.appendChild(row);
    }

    if (withMonths) {
      addGroup('Быстрый выбор', quickPresets(), 'tv-date-presets__row--quick');
      addGroup('Месяц вылета', monthPresets(4), 'tv-date-presets__row--months');
    } else {
      addGroup('', presets, 'tv-date-presets__row--quick');
    }
  }

  /** Пресеты ночей: один тап → от–до. */
  function nightsPresets() {
    return [
      { from: 7, to: 7, label: '7 ночей' },
      { from: 7, to: 10, label: '7–10' },
      { from: 10, to: 14, label: '10–14' },
      { from: 14, to: 21, label: '14–21' }
    ];
  }

  function renderNightsChips(container, onSelect) {
    if (!container) return;
    container.innerHTML = '';
    container.classList.add('tv-nights-quick');
    nightsPresets().forEach(function (p) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'tv-nights-quick__chip';
      btn.setAttribute('data-nights-from', String(p.from));
      btn.setAttribute('data-nights-to', String(p.to));
      btn.textContent = p.label;
      btn.addEventListener('click', function () {
        container.querySelectorAll('.tv-nights-quick__chip').forEach(function (c) {
          c.classList.remove('active');
        });
        btn.classList.add('active');
        if (typeof onSelect === 'function') onSelect(p.from, p.to, btn);
      });
      container.appendChild(btn);
    });
  }

  /** Дефолт формы: сегодня → +14 дней (ближайшие 2 недели). */
  function getDefaultRange() {
    return getRange('14d');
  }

  global.THDatePresets = {
    getRange: getRange,
    getDefaultRange: getDefaultRange,
    getMonthRange: getMonthRange,
    apply: apply,
    renderChips: renderChips,
    renderNightsChips: renderNightsChips,
    quickPresets: quickPresets,
    monthPresets: monthPresets,
    nightsPresets: nightsPresets,
    fmtFlatpickr: fmtFlatpickr,
    MONTHS_RU: MONTHS_RU
  };
})(typeof window !== 'undefined' ? window : this);
