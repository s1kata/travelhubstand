/**
 * Город вылета: localStorage → select #tv-departure и связанные поля. Дефолт — Самара.
 * window.TH_DEPARTURE / window.TH_TV_API_BASE задаются в header.php
 */
(function () {
  'use strict';

  var STORAGE_ID = 'th_departure_id';
  var STORAGE_NAME = 'th_departure_name';
  var BLOCKED_DEPARTURE_NAMES = ['красноярск', 'krasnoyarsk'];

  var state = {
    departures: null,
    initialized: false
  };

  function defaultDeparture() {
    if (typeof window.TH_DEPARTURE === 'object' && window.TH_DEPARTURE) {
      return {
        id: String(window.TH_DEPARTURE.id || '7'),
        name: String(window.TH_DEPARTURE.name || 'Самара')
      };
    }
    return { id: '7', name: 'Самара' };
  }

  function safeLsGet(key) {
    try { return localStorage.getItem(key); } catch (e) { return null; }
  }
  function safeLsSet(key, val) {
    try { localStorage.setItem(key, val); } catch (e) {}
  }

  function getTvBase() {
    var b = typeof window.TH_TV_API_BASE === 'string' ? window.TH_TV_API_BASE : '';
    if (!b) return '';
    if (typeof location !== 'undefined' && location.protocol === 'https:' && b.indexOf('http://') === 0) {
      return 'https:' + b.substring(5);
    }
    return b;
  }

  function normalize(s) {
    return String(s || '')
      .toLowerCase()
      .replace(/ё/g, 'е')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function isBlockedDepartureName(name) {
    var n = normalize(name);
    return !n ? false : BLOCKED_DEPARTURE_NAMES.indexOf(n) >= 0;
  }

  function isBlockedDeparture(item) {
    if (!item) return false;
    return isBlockedDepartureName(item.name || item.russianName || '');
  }

  function filterDepartures(list) {
    return (list || []).filter(function (d) { return !isBlockedDeparture(d); });
  }

  function findSamara(list) {
    if (!list || !list.length) return null;
    var def = defaultDeparture();
    var byId = list.find(function (d) { return String(d.id) === String(def.id); });
    if (byId) return byId;
    var byName = list.find(function (d) { return normalize(d.name || d.russianName || '') === 'самара'; });
    if (byName) return byName;
    return list.find(function (d) { return !isBlockedDeparture(d); }) || null;
  }

  function normalizeDepartureId(id) {
    var n = parseInt(String(id || ''), 10);
    if (isNaN(n) || n <= 0) return parseInt(defaultDeparture().id, 10) || 7;
    if (n === 12) return parseInt(defaultDeparture().id, 10) || 7;
    if (n === 28) return 1;
    return n;
  }

  function syncGlobalDeparture(id, name) {
    window.TH_DEPARTURE = {
      id: String(normalizeDepartureId(id)),
      name: String(name || defaultDeparture().name || 'Самара')
    };
  }

  function readFromSelect(el) {
    if (!el || el.tagName !== 'SELECT') return null;
    var sid = String(el.value || '').trim();
    if (!sid) return null;
    var opt = el.options[el.selectedIndex];
    var nm = opt ? (opt.textContent || '').trim() : '';
    return {
      id: String(normalizeDepartureId(sid)),
      name: nm || getSaved().name
    };
  }

  function getSaved() {
    var def = defaultDeparture();
    var id = safeLsGet(STORAGE_ID) || def.id;
    var name = safeLsGet(STORAGE_NAME) || def.name;
    id = String(normalizeDepartureId(id));
    if (isBlockedDepartureName(name)) {
      id = def.id;
      name = def.name;
      safeLsSet(STORAGE_ID, String(id));
      safeLsSet(STORAGE_NAME, name);
    } else if (String(safeLsGet(STORAGE_ID) || '') !== id) {
      safeLsSet(STORAGE_ID, id);
    }
    return { id: id, name: name || 'Самара' };
  }

  /** Актуальный город вылета: приоритет у #tv-departure, затем localStorage. */
  function getActive() {
    var fromSel = readFromSelect(document.querySelector('#tv-departure'));
    if (fromSel && fromSel.id) return fromSel;
    return getSaved();
  }

  function save(id, name) {
    var def = defaultDeparture();
    if (isBlockedDepartureName(name)) {
      id = def.id;
      name = def.name;
    }
    id = String(normalizeDepartureId(id || def.id));
    name = name || def.name || 'Самара';
    safeLsSet(STORAGE_ID, id);
    safeLsSet(STORAGE_NAME, name);
    syncGlobalDeparture(id, name);
    updateHeaderPill();
    window.dispatchEvent(new CustomEvent('th-departure-saved', { detail: { id: id, name: name } }));
  }

  function updateHeaderPill() {
    var btn = document.getElementById('th-departure-header-btn');
    if (btn) btn.hidden = true;
  }

  var SELECTORS = ['#tv-departure', '#country-tv-departure', '#vip-tv-departure'];

  function pickSelectableDepartureId(el, sid, list) {
    sid = String(normalizeDepartureId(sid || defaultDeparture().id));
    if (el && el.tagName === 'SELECT') {
      var ok = Array.prototype.some.call(el.options, function (o) {
        return String(o.value) === sid;
      });
      if (ok) return sid;
      var sam = findSamara(list || state.departures || []);
      if (sam && sam.id != null) {
        sid = String(sam.id);
        ok = Array.prototype.some.call(el.options, function (o) {
          return String(o.value) === sid;
        });
        if (ok) return sid;
      }
      for (var i = 0; i < el.options.length; i++) {
        if (el.options[i].value) return String(el.options[i].value);
      }
    }
    return sid;
  }

  function applyDepartureId(el, sid, dispatch, list) {
    if (!el || !sid) return;
    sid = pickSelectableDepartureId(el, sid, list);
    if (el.tagName === 'SELECT') {
      var ok = Array.prototype.some.call(el.options, function (o) {
        return String(o.value) === sid;
      });
      if (!ok) return;
      el.value = sid;
      el.disabled = false;
    } else {
      el.value = sid;
    }
    if (dispatch) {
      try { el.dispatchEvent(new Event('change', { bubbles: true })); } catch (e) {}
    }
  }

  function applyToSelects(list, id, dispatch) {
    if (!id) return;
    var sid = String(normalizeDepartureId(id));
    SELECTORS.forEach(function (sel) {
      applyDepartureId(document.querySelector(sel), sid, dispatch, list);
    });
    document.querySelectorAll('.th-departure-static-label').forEach(function (n) {
      n.textContent = getSaved().name;
    });
  }

  function resolveDepartureFromList(list) {
    list = filterDepartures(list);
    if (!list || !list.length) return defaultDeparture();
    var saved = getSaved();
    if (saved.id && list.some(function (d) { return String(d.id) === String(saved.id); })) {
      var hit = list.find(function (d) { return String(d.id) === String(saved.id); });
      if (hit && !isBlockedDeparture(hit)) {
        return { id: String(hit.id), name: hit.name || hit.russianName || saved.name };
      }
    }
    var sam = findSamara(list);
    return {
      id: String(sam && sam.id != null ? sam.id : defaultDeparture().id),
      name: (sam && (sam.name || sam.russianName)) || defaultDeparture().name
    };
  }

  function applySavedToPage(list) {
    list = filterDepartures(list || []);
    var tvDep = document.querySelector('#tv-departure');
    var cur = readFromSelect(tvDep);
    if (cur && cur.id && list.length && list.some(function (d) { return String(d.id) === String(cur.id); })) {
      save(cur.id, cur.name);
      applyToSelects(list, cur.id, false);
      return;
    }
    var picked = resolveDepartureFromList(list);
    save(picked.id, picked.name);
    if (list && list.length) applyToSelects(list, picked.id, false);
    else applyToSelects([], picked.id, false);
  }

  function bindDepartureSelectChange() {
    var el = document.querySelector('#tv-departure');
    if (!el || el.__thDepBound) return;
    el.__thDepBound = true;
    el.addEventListener('change', function () {
      var sid = String(el.value || '');
      if (!sid) return;
      var opt = el.options[el.selectedIndex];
      var nm = opt ? (opt.textContent || '').trim() : getSaved().name;
      save(sid, nm);
    });
  }

  function fetchDepartures() {
    var base = getTvBase();
    if (!base) return Promise.resolve([]);
    var sep = base.indexOf('?') >= 0 ? '&' : '?';
    return fetch(base + sep + 'type=departures', { cache: 'no-store' })
      .then(function (r) { return r.text(); })
      .then(function (t) {
        try {
          var j = JSON.parse(t);
          return (j && j.success && Array.isArray(j.data)) ? j.data : [];
        } catch (e) {
          return [];
        }
      })
      .catch(function () { return []; });
  }

  function init() {
    if (state.initialized) return;
    state.initialized = true;
    syncGlobalDeparture(getSaved().id, getSaved().name);
    updateHeaderPill();
    bindDepartureSelectChange();
    applySavedToPage([]);
    fetchDepartures().then(function (list) {
      state.departures = filterDepartures(list);
      applySavedToPage(state.departures);
    });
  }

  function onDeparturesReady(list) {
    state.departures = filterDepartures(list || []);
    applySavedToPage(state.departures);
    bindDepartureSelectChange();
  }

  function matchDeparture(name, list) {
    var arr = filterDepartures(list || state.departures || []);
    if (!arr.length) return findSamara(arr);
    if (isBlockedDepartureName(name)) return findSamara(arr);
    var n = normalize(name);
    if (!n) return null;
    return arr.find(function (d) {
      return normalize(d.name || d.russianName || '') === n;
    }) || null;
  }

  window.THDeparturePreference = {
    init: init,
    getSaved: getSaved,
    getActive: getActive,
    save: save,
    applyToPageSelects: applySavedToPage,
    onDeparturesReady: onDeparturesReady,
    matchDeparture: matchDeparture,
    isBlockedDepartureName: isBlockedDepartureName,
    filterDepartures: filterDepartures,
    normalizeDepartureId: normalizeDepartureId,
    ensureSelectValue: function (el, list) {
      if (!el) return;
      var picked = resolveDepartureFromList(list || state.departures || []);
      applyDepartureId(el, picked.id, false, list || state.departures || []);
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
