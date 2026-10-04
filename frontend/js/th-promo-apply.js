/**
 * th-promo-apply.js — единая проверка и применение промокодов на сайте.
 */
(function () {
    'use strict';

    if (window.__TH_PROMO_APPLY_BOOTED) return;
    window.__TH_PROMO_APPLY_BOOTED = true;

    var LS_APP_UNLOCK = 'th_app_promo_unlocked';
    var SS_PENDING = 'th_pending_promo_code';
    var LS_USED_CODES = 'th_promo_used_codes';

    function norm(code) {
        return String(code || '').trim().toUpperCase();
    }

    function getUsedCodesMap() {
        try {
            var raw = localStorage.getItem(LS_USED_CODES);
            if (!raw) return {};
            var parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    function isCodeUsed(code) {
        var u = norm(code);
        if (!u) return false;
        return !!getUsedCodesMap()[u];
    }

    function markCodeUsed(code) {
        var u = norm(code);
        if (!u || isCodeUsed(u)) return;
        var used = getUsedCodesMap();
        used[u] = Date.now();
        try {
            localStorage.setItem(LS_USED_CODES, JSON.stringify(used));
        } catch (e) {}
        if (window.TH_PROMO && typeof window.TH_PROMO.onCodeUsed === 'function') {
            window.TH_PROMO.onCodeUsed(u);
        }
    }

    function isAppPromoUnlocked() {
        try { return localStorage.getItem(LS_APP_UNLOCK) === '1'; } catch (e) { return false; }
    }

    function livePromoCode() {
        return (window.TH_PROMO && window.TH_PROMO.promoCode)
            ? String(window.TH_PROMO.promoCode).toUpperCase()
            : '';
    }

    function getPromoPct(code) {
        var u = norm(code);
        if (!u) return 0;
        if (isCodeUsed(u)) return 0;
        if (u === 'TRAVELAPP') {
            return isAppPromoUnlocked() ? 10 : 0;
        }
        var live = livePromoCode();
        if (u === 'TRAVEL10') {
            if (live && live !== 'TRAVEL10') return 0;
            return 10;
        }
        if (u === 'TRAVEL5') {
            if (live && live !== 'TRAVEL5') return 0;
            return 5;
        }
        return 0;
    }

    function maxDiscount(base, pct) {
        var d = Math.round(base * (pct / 100));
        if (d > 5000) d = 5000;
        return d;
    }

    function calcPriceAfterPromo(base, pct) {
        base = parseInt(base, 10) || 0;
        pct = parseInt(pct, 10) || 0;
        if (!base || !pct) return 0;
        var d = maxDiscount(base, pct);
        var newP = Math.round((base - d) / 100) * 100;
        if (!newP || newP >= base) return 0;
        return newP;
    }

    function invalidMessage(code) {
        var u = norm(code);
        if (isCodeUsed(u)) {
            return 'Этот промокод уже был использован';
        }
        if (u === 'TRAVELAPP') {
            return 'Сначала нажмите «Скачать в App Store» на главной — промокод выдаётся после перехода';
        }
        if (u === 'TRAVEL10' || u === 'TRAVEL5') {
            return 'Промокод недействителен или истёк — проверьте таймер на сайте';
        }
        return 'Промокод недействителен';
    }

    function setPendingCode(code) {
        try { sessionStorage.setItem(SS_PENDING, norm(code)); } catch (e) {}
    }

    function takePendingCode() {
        try {
            var c = sessionStorage.getItem(SS_PENDING);
            sessionStorage.removeItem(SS_PENDING);
            return c ? norm(c) : '';
        } catch (e2) { return ''; }
    }

    function formatRub(num) {
        if (typeof num !== 'number' || !num) return '';
        return num.toLocaleString('ru-RU') + '\u00a0\u20bd';
    }

    window.TH_PROMO_APPLY = {
        getPromoPct: getPromoPct,
        maxDiscount: maxDiscount,
        calcPriceAfterPromo: calcPriceAfterPromo,
        invalidMessage: invalidMessage,
        isCodeUsed: isCodeUsed,
        markCodeUsed: markCodeUsed,
        isAppPromoUnlocked: isAppPromoUnlocked,
        setPendingCode: setPendingCode,
        takePendingCode: takePendingCode,
        formatRub: formatRub,
        triggerTourDetailApply: function () {
            var btn = document.getElementById('th-td-promo-apply');
            if (btn) btn.click();
        },
        fillTourDetailInput: function (code) {
            var input = document.getElementById('th-td-promo-input');
            if (input && code) input.value = code;
        }
    };
})();
