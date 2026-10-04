/**
 * Глобальная отладка кэша — логи в консоль браузера
 * Page Cache + Tourvisor API Cache
 */
(function() {
    'use strict';
    console.log('%c[CACHE DEBUG] Скрипт загружен', 'color: #6366f1; font-weight: bold');
    const logs = [];
    const apiStats = { hits: 0, misses: 0, n_a: 0, errors: 0 };

    function log(msg, data) {
        const entry = { msg, data, t: new Date().toISOString() };
        logs.push(entry);
        console.log('%c[CACHE DEBUG] ' + msg, 'color: #6366f1; font-weight: bold', data || '');
    }

    function logPageCache() {
        if (typeof window.__CACHE_DEBUG === 'object' && window.__CACHE_DEBUG) {
            const d = window.__CACHE_DEBUG;
            const status = d.page || '?';
            const color = status === 'HIT' ? '#22c55e' : (status === 'MISS' ? '#f59e0b' : '#94a3b8');
            console.log('%c[Page Cache] ' + status, 'color: ' + color + '; font-weight: bold', d);
            log('Page Cache: ' + status, d);
            if (d.pageCacheFiles != null) {
                console.log('%c  → Страниц в кэше: ' + d.pageCacheFiles, 'color: #64748b');
            }
            if (d.age != null) {
                console.log('%c  → Возраст (сек): ' + d.age + ' / TTL: ' + (d.ttl || 3600), 'color: #64748b');
            }
        }
    }

    const origFetch = window.fetch;
    window.fetch = function(url, opts) {
        const urlStr = typeof url === 'string' ? url : (url && url.url) || '';
        const isTv = urlStr.indexOf('tourvisor-proxy') !== -1;
        return origFetch.apply(this, arguments).then(function(r) {
            if (isTv && r.headers) {
                const cache = r.headers.get('X-Tourvisor-Cache') || 'n/a';
                const success = r.headers.get('X-Tourvisor-Success') || '?';
                const type = r.headers.get('X-Tourvisor-Type') || '?';
                const items = parseInt(r.headers.get('X-Tourvisor-Items') || '0', 10);
                const saved = r.headers.get('X-Tourvisor-Cache-Saved') || 'no';
                if (cache === 'hit') apiStats.hits++;
                else if (cache === 'miss') apiStats.misses++;
                else apiStats.n_a++;
                if (success !== 'yes') apiStats.errors++;
                const typeShort = (urlStr.match(/type=([^&]+)/) || [])[1] || type;
                const status = cache === 'hit' ? 'HIT' : (cache === 'miss' ? 'MISS' : 'n/a');
                const color = cache === 'hit' ? '#22c55e' : (cache === 'miss' ? '#f59e0b' : '#94a3b8');
                let msg = '[Tourvisor] ' + typeShort + ': ';
                if (cache === 'hit') {
                    msg += 'загружено из кэша: ' + items + ' элементов';
                } else if (cache === 'miss' && saved === 'yes') {
                    msg += 'найдено ' + items + ', отложено в кэш ✓';
                } else if (cache === 'miss') {
                    msg += 'найдено ' + items + ' (API)';
                } else {
                    msg += items + ' элементов';
                }
                console.log('%c' + msg, 'color: ' + color + '; font-weight: bold');
                log(msg, { cache, items, saved });
            }
            return r;
        }).catch(function(e) {
            if (isTv) apiStats.errors++;
            return Promise.reject(e);
        });
    };

    function logSummary() {
        const total = apiStats.hits + apiStats.misses + apiStats.n_a;
        console.log('%c═══════════════════════════════════════', 'color: #6366f1');
        console.log('%c[CACHE] ИТОГО (отладка кэша)', 'color: #6366f1; font-size: 14px; font-weight: bold');
        console.log('%c  Page Cache: ' + (window.__CACHE_DEBUG ? (window.__CACHE_DEBUG.page || '?') : '—'), 'color: #94a3b8');
        console.log('%c  Tourvisor: запросов ' + total + ' | из кэша (hit): ' + apiStats.hits + ' | отложено в кэш (miss): ' + apiStats.misses + (apiStats.n_a ? ' | n/a: ' + apiStats.n_a : '') + (apiStats.errors ? ' | ошибок: ' + apiStats.errors : ''), 'color: #94a3b8');
        console.log('%c═══════════════════════════════════════', 'color: #6366f1');
    }

    function init() {
        logPageCache();
        if (!window.__CACHE_DEBUG) {
            console.log('%c[CACHE DEBUG] window.__CACHE_DEBUG не найден', 'color: #ef4444');
        }
        setTimeout(logSummary, 3000);
        setTimeout(logSummary, 8000);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.__CACHE_DEBUG_SUMMARY = logSummary;
})();
