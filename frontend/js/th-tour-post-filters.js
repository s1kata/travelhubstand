/**
 * Пост-фильтры выдачи туров (без перезагрузки страницы).
 * window.THTourPostFilters.filterHotels(list, state, helpers)
 */
(function (global) {
  'use strict';

  var MEAL_CODES = ['AI', 'UAI', 'HB', 'HB+', 'FB', 'BB', 'RO', 'SC', 'AL'];
  var MEAL_LABELS_RU = {
    AI: 'Всё включено',
    UAI: 'Ультра всё включено',
    HB: 'Завтрак + ужин',
    'HB+': 'Завтрак + ужин+',
    FB: 'Полный пансион',
    BB: 'Завтрак',
    RO: 'Без питания',
    SC: 'Без питания',
    AL: 'По системе отеля'
  };

  function mealLabelRu(code) {
    var c = String(code || '').toUpperCase();
    return MEAL_LABELS_RU[c] || c;
  }

  function normMeal(h) {
    var tour = (h.tours && h.tours[0]) ? h.tours[0] : {};
    var m = tour.meal || {};
    var raw = (m.name || m.russianName || h.meal || '').toString().trim().toUpperCase();
    if (!raw) return '';
    if (raw.indexOf('ВСЁ') >= 0 || raw.indexOf('ВСЕ') >= 0) return 'AI';
    if (raw.indexOf('УЛЬТРА') >= 0) return 'UAI';
    if (raw.indexOf('ЗАВТРАК') >= 0 && raw.indexOf('УЖИН') >= 0) return 'HB';
    if (raw.indexOf('ЗАВТРАК') >= 0) return 'BB';
    if (raw.indexOf('БЕЗ') >= 0) return 'RO';
    return raw.split(/[\s(,]/)[0].replace(/[^A-Z+]/g, '') || raw;
  }

  function starCategory(h) {
    var c = parseInt(String(h.category || h.hotelCategory || ''), 10);
    if (!isNaN(c) && c >= 1 && c <= 5) return c;
    var name = String(h.name || '');
    var m = name.match(/(\d)\s*★/);
    if (m) return parseInt(m[1], 10);
    return null;
  }

  function regionKey(h) {
    if (h.region && h.region.id != null) return String(h.region.id);
    if (h.region && h.region.name) return 'n:' + String(h.region.name);
    return '';
  }

  function regionLabel(h) {
    return (h.region && h.region.name) ? String(h.region.name) : '';
  }

  function beachLine(h) {
    var v = h.beachLine || h.beach_line || h.beachline;
    if (v != null && v !== '') return parseInt(String(v), 10) || 0;
    var desc = String(h.description || h.hotelDescription || '').toLowerCase();
    if (/1[\s-]*я\s*линия|первая\s*линия|1st\s*line|first\s*line/i.test(desc)) return 1;
    if (/2[\s-]*я\s*линия|вторая\s*линия|2nd\s*line/i.test(desc)) return 2;
    return 0;
  }

  function filterHotels(list, state, helpers) {
    if (!Array.isArray(list)) return [];
    state = state || {};
    helpers = helpers || {};
    var getPrice = typeof helpers.getPrice === 'function' ? helpers.getPrice : function () { return 0; };
    var out = list.slice();

    if (state.stars && state.stars.size > 0) {
      out = out.filter(function (h) {
        var c = starCategory(h);
        if (c === null) return true;
        if (state.stars.has('3plus') && c >= 3) return true;
        return state.stars.has(String(c));
      });
    }

    if (state.meals && state.meals.size > 0) {
      out = out.filter(function (h) {
        var code = normMeal(h);
        if (!code) return true;
        if (state.meals.has(code)) return true;
        return MEAL_CODES.some(function (mc) {
          return state.meals.has(mc) && (code === mc || code.indexOf(mc) === 0);
        });
      });
    }

    if (state.regions && state.regions.size > 0) {
      out = out.filter(function (h) {
        var k = regionKey(h);
        return k && state.regions.has(k);
      });
    }

    var minP = parseInt(String(state.priceMin || ''), 10);
    var maxP = parseInt(String(state.priceMax || ''), 10);
    if (!isNaN(minP) && minP > 0) {
      out = out.filter(function (h) { return getPrice(h) >= minP; });
    }
    if (!isNaN(maxP) && maxP > 0) {
      out = out.filter(function (h) { return getPrice(h) <= maxP; });
    }

    if (state.beachLine) {
      var wantBl = parseInt(String(state.beachLine), 10);
      if (wantBl > 0) {
        out = out.filter(function (h) {
          var bl = beachLine(h);
          return bl === 0 || bl === wantBl;
        });
      }
    }

    return out;
  }

  function collectMeta(hotels) {
    var regions = {};
    var meals = {};
    (hotels || []).forEach(function (h) {
      var rk = regionKey(h);
      var rl = regionLabel(h);
      if (rk && rl) regions[rk] = rl;
      var mc = normMeal(h);
      if (mc) meals[mc] = true;
    });
    return {
      regions: Object.keys(regions).map(function (k) { return { id: k, name: regions[k] }; }).sort(function (a, b) {
        return a.name.localeCompare(b.name, 'ru');
      }),
      meals: Object.keys(meals).sort(),
      hasBeachData: (hotels || []).some(function (h) { return beachLine(h) > 0; })
    };
  }

  function createState() {
    return {
      stars: new Set(),
      meals: new Set(),
      regions: new Set(),
      priceMin: '',
      priceMax: '',
      beachLine: ''
    };
  }

  /**
   * @param {object} opts — rootEl, onChange, getPrice
   */
  function mount(opts) {
    opts = opts || {};
    var root = opts.root;
    if (!root) return null;
    var state = createState();
    var ui = {};

    function emit() {
      if (typeof opts.onChange === 'function') opts.onChange(state);
    }

    function renderRegions(meta) {
      var box = root.querySelector('[data-pf-regions]');
      if (!box) return;
      if (!meta.regions.length) {
        box.innerHTML = '<p class="tv-pf-hint">Появятся после поиска</p>';
        return;
      }
      box.innerHTML = meta.regions.map(function (r) {
        return '<label class="tv-pf-check"><input type="checkbox" data-pf-region="' + r.id.replace(/"/g, '&quot;') + '"><span>' + r.name.replace(/</g, '&lt;') + '</span></label>';
      }).join('');
      box.querySelectorAll('input[data-pf-region]').forEach(function (inp) {
        inp.addEventListener('change', function () {
          var id = inp.getAttribute('data-pf-region');
          if (inp.checked) state.regions.add(id);
          else state.regions.delete(id);
          emit();
        });
      });
    }

    function bindChips(selector, set, multi) {
      root.querySelectorAll(selector).forEach(function (btn) {
        btn.addEventListener('click', function () {
          var val = btn.getAttribute('data-pf-value');
          if (!multi) {
            set.clear();
            root.querySelectorAll(selector).forEach(function (b) {
              b.classList.remove('is-active');
              b.setAttribute('aria-pressed', 'false');
            });
            if (val) {
              set.add(val);
              btn.classList.add('is-active');
              btn.setAttribute('aria-pressed', 'true');
            }
            emit();
            return;
          }
          if (set.has(val)) {
            set.delete(val);
            btn.classList.remove('is-active');
            btn.setAttribute('aria-pressed', 'false');
          } else {
            set.add(val);
            btn.classList.add('is-active');
            btn.setAttribute('aria-pressed', 'true');
          }
          emit();
        });
      });
    }

    bindChips('[data-pf-star]', state.stars, true);
    bindChips('[data-pf-meal]', state.meals, true);

    root.querySelectorAll('[data-pf-beach]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var wasActive = btn.classList.contains('is-active');
        root.querySelectorAll('[data-pf-beach]').forEach(function (b) {
          b.classList.remove('is-active');
          b.setAttribute('aria-pressed', 'false');
        });
        if (!wasActive) {
          state.beachLine = btn.getAttribute('data-pf-beach') || '';
          btn.classList.add('is-active');
          btn.setAttribute('aria-pressed', 'true');
        } else {
          state.beachLine = '';
        }
        emit();
      });
    });

    var minInp = root.querySelector('[data-pf-price-min]');
    var maxInp = root.querySelector('[data-pf-price-max]');
    function onPriceInput() {
      state.priceMin = minInp ? minInp.value : '';
      state.priceMax = maxInp ? maxInp.value : '';
      emit();
    }
    if (minInp) minInp.addEventListener('change', onPriceInput);
    if (maxInp) maxInp.addEventListener('change', onPriceInput);
    if (minInp) minInp.addEventListener('input', onPriceInput);
    if (maxInp) maxInp.addEventListener('input', onPriceInput);

    root.querySelectorAll('[data-pf-budget-quick]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var max = btn.getAttribute('data-pf-budget-quick');
        if (maxInp) maxInp.value = max;
        state.priceMax = max;
        emit();
      });
    });

    var resetBtn = root.querySelector('[data-pf-reset]');
    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        state.stars.clear();
        state.meals.clear();
        state.regions.clear();
        state.priceMin = state.priceMax = state.beachLine = '';
        if (minInp) minInp.value = '';
        if (maxInp) maxInp.value = '';
        root.querySelectorAll('.tv-pf-chip.is-active, [data-pf-beach].is-active').forEach(function (el) {
          el.classList.remove('is-active');
          el.setAttribute('aria-pressed', 'false');
        });
        root.querySelectorAll('input[data-pf-region]').forEach(function (inp) { inp.checked = false; });
        emit();
      });
    }

    return {
      state: state,
      updateFromHotels: function (hotels) {
        var meta = collectMeta(hotels);
        renderRegions(meta);
        var beachGrp = root.querySelector('[data-pf-beach-group]');
        if (beachGrp) beachGrp.style.display = meta.hasBeachData ? '' : 'none';
        var mealBox = root.querySelector('[data-pf-meals]');
        if (mealBox && meta.meals.length) {
          var known = MEAL_CODES.filter(function (c) { return meta.meals.indexOf(c) >= 0; });
          meta.meals.forEach(function (m) {
            if (known.indexOf(m) < 0) known.push(m);
          });
          mealBox.innerHTML = known.map(function (code) {
            return '<button type="button" class="tv-pf-chip" data-pf-meal data-pf-value="' + code + '" aria-pressed="false">' + mealLabelRu(code) + '</button>';
          }).join('');
          bindChips('[data-pf-meal]', state.meals, true);
        }
        return meta;
      },
      reset: function () {
        if (resetBtn) resetBtn.click();
      }
    };
  }

  global.THTourPostFilters = {
    filterHotels: filterHotels,
    collectMeta: collectMeta,
    createState: createState,
    mount: mount,
    starCategory: starCategory,
    normMeal: normMeal,
    MEAL_CODES: MEAL_CODES
  };
})(typeof window !== 'undefined' ? window : this);
