/**
 * session-manager.js — TravelHub
 * Сохраняет позицию скролла и последнюю карточку тура при переходе в tour-detail.
 * Восстанавливает при «Назад к результатам» (tv_restore=1) и при bfcache.
 */
(function () {
    'use strict';

    var EXTRA_KEY = 'tv_extra_state_v1';
    var LAST_CARD_KEY = 'th_last_tour_card_id_v1';
    var TTL_MS = 45 * 60 * 1000;

    var RESULT_CONTAINER_IDS = [
        'tv-results-wrapper',
        'promo-tours-results',
        'country-tv-results-wrapper',
        'vip-tv-results-wrapper',
        'country-promo-results'
    ];

    function safeGet(id) {
        var el = document.getElementById(id);
        return el ? el.value : '';
    }
    function safeSet(id, value, silent) {
        var el = document.getElementById(id);
        if (!el) return;
        if (id === 'tv-departure') {
            setSelectValueSafe(el, value, silent);
            return;
        }
        el.value = value;
        if (silent) return;
        try { el.dispatchEvent(new Event('change', { bubbles: true })); } catch (_) {}
    }
    function normalizeStoredDepartureId(v) {
        if (window.THDeparturePreference && typeof window.THDeparturePreference.normalizeDepartureId === 'function') {
            return String(window.THDeparturePreference.normalizeDepartureId(v));
        }
        var n = parseInt(String(v || ''), 10);
        if (isNaN(n) || n <= 0) return String((window.TH_DEPARTURE && window.TH_DEPARTURE.id) || 7);
        if (n === 12) return String((window.TH_DEPARTURE && window.TH_DEPARTURE.id) || 7);
        if (n === 28) return '1';
        return String(n);
    }

    function setSelectValueSafe(el, value, silent) {
        if (!el) return;
        var v = String(value || '');
        if (el.id === 'tv-departure') v = normalizeStoredDepartureId(v);
        var ok = el.tagName === 'SELECT'
            ? Array.prototype.some.call(el.options, function (o) { return String(o.value) === v; })
            : true;
        if (!ok && el.tagName === 'SELECT') {
            var def = normalizeStoredDepartureId((window.TH_DEPARTURE && window.TH_DEPARTURE.id) || 7);
            ok = Array.prototype.some.call(el.options, function (o) { return String(o.value) === def; });
            if (ok) v = def;
            else {
                for (var i = 0; i < el.options.length; i++) {
                    if (el.options[i].value) { v = String(el.options[i].value); ok = true; break; }
                }
            }
        }
        if (!ok) return;
        el.value = v;
        if (!silent) {
            try { el.dispatchEvent(new Event('change', { bubbles: true })); } catch (_) {}
        }
    }

    function waitForOptions(id, value, maxTries, silent) {
        var tries = 0;
        function attempt() {
            var el = document.getElementById(id);
            if (!el) return;
            if (el.options.length > 1 || tries >= maxTries) {
                setSelectValueSafe(el, value, silent);
            } else {
                tries++;
                setTimeout(attempt, 350);
            }
        }
        attempt();
    }

    function normalizeReturnPath(pathOrUrl) {
        try {
            var u = String(pathOrUrl || '').indexOf('http') === 0
                ? new URL(pathOrUrl)
                : new URL(pathOrUrl || '/', window.location.origin);
            u.searchParams.delete('tv_restore');
            var q = u.searchParams.toString();
            return u.pathname + (q ? '?' + q : '');
        } catch (_) {
            return String(pathOrUrl || '').replace(/([?&])tv_restore=[^&]*&?/g, '$1').replace(/[?&]$/, '');
        }
    }

    function hasRestoreParam() {
        try {
            var tr = new URLSearchParams(window.location.search).get('tv_restore');
            return tr === '1' || String(tr || '').toLowerCase() === 'true';
        } catch (_) {
            return false;
        }
    }

    function cleanRestoreParam() {
        try {
            if (!hasRestoreParam()) return;
            var sp = new URLSearchParams(window.location.search);
            sp.delete('tv_restore');
            var np = sp.toString();
            var clean = window.location.pathname + (np ? '?' + np : '') + window.location.hash;
            if (history.replaceState) history.replaceState(null, '', clean);
        } catch (_) {}
    }

    function getVisibleResultsContainer() {
        for (var i = 0; i < RESULT_CONTAINER_IDS.length; i++) {
            var el = document.getElementById(RESULT_CONTAINER_IDS[i]);
            if (!el || el.classList.contains('hidden')) continue;
            if (el.querySelector('.th-tour-card, .tv-tour-card, [data-th-hotel-id]')) return el;
        }
        return null;
    }

    function findCardByStoredId(lastCardId) {
        if (!lastCardId) return null;
        var esc = String(lastCardId).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
        return document.querySelector(
            '.th-tour-card[data-th-hotel-id="' + esc + '"],' +
            '.tv-tour-card[data-th-hotel-id="' + esc + '"],' +
            '[data-th-hotel-id="' + esc + '"]'
        );
    }

    function highlightCard(card) {
        if (!card) return;
        card.classList.add('th-tour-card--return-highlight');
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(function () { card.classList.remove('th-tour-card--return-highlight'); }, 2200);
    }

    function buildReturnUrl() {
        try {
            var u = new URL(window.location.href);
            u.searchParams.set('tv_restore', '1');
            return u.pathname + u.search;
        } catch (_) {
            return normalizeReturnPath(window.location.pathname + window.location.search);
        }
    }

    function saveExtra() {
        try {
            var state = {
                ts: Date.now(),
                path: normalizeReturnPath(window.location.pathname + window.location.search),
                scrollY: window.scrollY || window.pageYOffset || 0,
                departure: safeGet('tv-departure'),
                country: safeGet('tv-country'),
                meal: safeGet('tv-meal'),
                region: safeGet('tv-region'),
                category: safeGet('tv-category'),
                nightsFrom: (typeof window.tvNightsFrom !== 'undefined') ? window.tvNightsFrom : 6,
                nightsTo: (typeof window.tvNightsTo !== 'undefined') ? window.tvNightsTo : 9,
                adults: (typeof window.tvAdultsCount !== 'undefined') ? window.tvAdultsCount : 2,
                childAges: Array.isArray(window.tvChildrenAges) ? window.tvChildrenAges.slice() : [],
                datesRaw: safeGet('tv-dates')
            };
            sessionStorage.setItem(EXTRA_KEY, JSON.stringify(state));
        } catch (_) {}
    }

    function saveOnTourClick(link) {
        saveExtra();
        try {
            var card = link.closest('.th-tour-card, .tv-tour-card, [data-th-hotel-id]');
            var hid = card && card.getAttribute('data-th-hotel-id');
            if (hid) sessionStorage.setItem(LAST_CARD_KEY, hid);
            else sessionStorage.removeItem(LAST_CARD_KEY);
        } catch (_) {}
        if (typeof window.saveTvMainSearchSnapshot === 'function') {
            window.saveTvMainSearchSnapshot();
        }
    }

    function readExtraState() {
        try {
            var raw = sessionStorage.getItem(EXTRA_KEY);
            if (!raw) return null;
            var s = JSON.parse(raw);
            if (!s || !s.ts || (Date.now() - s.ts) > TTL_MS) {
                sessionStorage.removeItem(EXTRA_KEY);
                try { sessionStorage.removeItem(LAST_CARD_KEY); } catch (_) {}
                return null;
            }
            return s;
        } catch (_) {
            return null;
        }
    }

    function shouldRestore() {
        if (hasRestoreParam()) return true;
        var s = readExtraState();
        if (!s) return false;
        var cur = normalizeReturnPath(window.location.pathname + window.location.search);
        return !s.path || s.path === cur;
    }

    function hasPendingScrollRestore() {
        try {
            if (sessionStorage.getItem(LAST_CARD_KEY)) return true;
            var s = readExtraState();
            return !!(s && s.scrollY > 80);
        } catch (_) {
            return false;
        }
    }

    function scrollResultsBlockIntoView(behavior) {
        var w = document.getElementById('tv-results-wrapper');
        if (w && !w.classList.contains('hidden')) {
            w.scrollIntoView({ behavior: behavior || 'smooth', block: 'start' });
            return true;
        }
        for (var i = 0; i < RESULT_CONTAINER_IDS.length; i++) {
            var el = document.getElementById(RESULT_CONTAINER_IDS[i]);
            if (!el || el.classList.contains('hidden')) continue;
            el.scrollIntoView({ behavior: behavior || 'smooth', block: 'start' });
            return true;
        }
        return false;
    }

    function finishRestore() {
        try { sessionStorage.removeItem(EXTRA_KEY); } catch (_) {}
        try { sessionStorage.removeItem(LAST_CARD_KEY); } catch (_) {}
        cleanRestoreParam();
        window.__tvRestoringFromBack = false;
    }

    function restoreExtra() {
        if (!shouldRestore()) return;

        window.__tvRestoringFromBack = true;

        var s = readExtraState();
        var targetY = (s && s.scrollY) ? s.scrollY : 0;
        var lastCardId = '';
        try { lastCardId = sessionStorage.getItem(LAST_CARD_KEY) || ''; } catch (_) {}

        if (s) {
            if (s.nightsFrom) {
                window.tvNightsFrom = s.nightsFrom;
                window.tvNightsTo = s.nightsTo || s.nightsFrom;
                var nightLbl = document.getElementById('tv-nights-summary-text');
                if (nightLbl) {
                    nightLbl.textContent = window.tvNightsFrom === window.tvNightsTo
                        ? String(window.tvNightsFrom)
                        : window.tvNightsFrom + ' — ' + window.tvNightsTo;
                }
            }
            if (s.adults) window.tvAdultsCount = s.adults;
            if (Array.isArray(s.childAges)) window.tvChildrenAges = s.childAges;
            /* Без change — иначе на главной снова запускается performTvSearch */
            if (s.departure) waitForOptions('tv-departure', s.departure, 15, true);
            if (s.country) waitForOptions('tv-country', s.country, 15, true);
            if (s.meal) waitForOptions('tv-meal', s.meal, 10, true);
            if (s.region) waitForOptions('tv-region', s.region, 10, true);
            if (s.category) safeSet('tv-category', s.category, true);
            if (s.datesRaw && window.tvDatePicker) {
                try { window.tvDatePicker.setDate(s.datesRaw, true); } catch (_) {}
            }
        }

        if (!lastCardId && targetY <= 80) {
            finishRestore();
            return;
        }

        var attempts = 0;
        var maxAttempts = 100;
        var iv = setInterval(function () {
            var card = findCardByStoredId(lastCardId);
            var container = getVisibleResultsContainer();
            var resultsRoot = document.getElementById('tv-search-results');

            if (card) {
                clearInterval(iv);
                highlightCard(card);
                finishRestore();
                return;
            }

            if (lastCardId && resultsRoot && attempts > 4 && !resultsRoot.querySelector('.th-tour-card, [data-th-hotel-id]')) {
                /* Снимок ещё не отрисован — ждём дольше */
                if (++attempts >= maxAttempts) {
                    clearInterval(iv);
                    finishRestore();
                }
                return;
            }

            if (container && targetY > 80 && !lastCardId) {
                clearInterval(iv);
                window.scrollTo({ top: targetY, behavior: attempts > 2 ? 'auto' : 'smooth' });
                finishRestore();
                return;
            }

            if (container && targetY > 80 && lastCardId && attempts > 20) {
                clearInterval(iv);
                if (!scrollResultsBlockIntoView('auto') && targetY > 80) {
                    window.scrollTo({ top: targetY, behavior: 'auto' });
                }
                finishRestore();
                return;
            }

            if (!lastCardId && container && attempts > 8) {
                clearInterval(iv);
                scrollResultsBlockIntoView('smooth');
                finishRestore();
                return;
            }

            if (++attempts >= maxAttempts) {
                clearInterval(iv);
                finishRestore();
            }
        }, 200);
    }

    function onTourLinkClick(e) {
        var link = e.target.closest('a[href]');
        if (!link || !link.href || link.href.indexOf('tour-detail') === -1) return;
        var card = link.closest('.th-tour-card, .tv-tour-card, [data-th-hotel-id]');
        if (!card) return;
        saveOnTourClick(link);
    }

    function init() {
        document.addEventListener('click', onTourLinkClick, true);
        document.addEventListener('tourOverlayOpen', saveExtra);

        var searchBtn = document.getElementById('tv-search-btn');
        if (searchBtn) {
            searchBtn.addEventListener('click', function () {
                try {
                    sessionStorage.removeItem(EXTRA_KEY);
                    sessionStorage.removeItem(LAST_CARD_KEY);
                } catch (_) {}
            });
        }

        if (shouldRestore()) {
            window.__tvRestoringFromBack = true;
            /* На главной scroll/карточку восстанавливает index.php после tryRestoreTvMainSearchFromSnapshot */
            if (!document.getElementById('tv-search-results')) {
                setTimeout(restoreExtra, 500);
            }
        }

        window.addEventListener('pageshow', function (ev) {
            if (!ev.persisted) return;
            if (!shouldRestore()) return;
            setTimeout(restoreExtra, 200);
        });
    }

    window.TourSessionManager = {
        save: saveExtra,
        restore: restoreExtra,
        buildReturnUrl: buildReturnUrl,
        hasPendingScrollRestore: hasPendingScrollRestore
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
