/**
 * Навигация по ссылкам туров; блокировка самоссылки на текущий сайт.
 */
(function (global) {
    'use strict';

    var SELF_MARKERS = /travelhub63\.ru/i;

    function currentHostname() {
        try {
            return String(window.location.hostname || '').toLowerCase();
        } catch (e) {
            return '';
        }
    }

    function isExternalUrl(url) {
        return typeof url === 'string' && /^https?:\/\//i.test(url.trim());
    }

    function isSelfTourLink(url) {
        if (url == null || typeof url !== 'string') {
            return true;
        }
        var s = url.trim();
        if (s === '') {
            return false;
        }
        if (SELF_MARKERS.test(s)) {
            return true;
        }
        try {
            var h = new URL(s, window.location.origin).hostname.toLowerCase();
            var cur = currentHostname();
            if (cur && h === cur) {
                return true;
            }
        } catch (err) {
            return SELF_MARKERS.test(s);
        }
        return false;
    }

    function sanitizeTourLink(link) {
        var u = typeof link === 'string' ? link.trim() : '';
        if (!u) {
            return '';
        }
        if (isSelfTourLink(u)) {
            console.error('Blocked self redirect (tour_link)');
            return '';
        }
        return u;
    }

    /**
     * Внешний URL — полный переход; относительный путь — в той же вкладке (навигация по сайту).
     */
    function openTourLink(url) {
        var u = sanitizeTourLink(url);
        if (!u) {
            return;
        }
        if (isExternalUrl(u)) {
            console.log('Redirect to:', u);
            global.location.href = u;
        } else {
            console.log('Redirect to:', u);
            global.location.assign(u);
        }
    }

    global.TourLinkUtils = {
        isExternalUrl: isExternalUrl,
        isSelfTourLink: isSelfTourLink,
        sanitizeTourLink: sanitizeTourLink,
        openTourLink: openTourLink
    };
})(typeof window !== 'undefined' ? window : this);
