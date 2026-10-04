<?php
/**
 * Блок «Туры из {страна}» — обычные туры на ближайший месяц для страниц стран.
 * Требует: $countrySlug, $countryData['name']; опционально $country_promo_listing_url (из country_page_main_sections).
 * Данные: search-cached без onlyPromo, dateFrom=сегодня, dateTo=+30 дней; фильтр по категории отеля.
 */
if (empty($countrySlug)) {
    return;
}
require_once __DIR__ . '/tourvisor_proxy_url.php';
require_once dirname(__DIR__) . '/config/departure_defaults.php';
$countryMatchConfig = require __DIR__ . '/country_match_config.php';
$countryNameMap = $countryMatchConfig['names'] ?? [];
$countryAliasMap = $countryMatchConfig['aliases'] ?? [];
$countryFallbackIdMap = $countryMatchConfig['fallback_ids'] ?? [];
$countryNameResolved = $countryData['name'] ?? ($countryNameMap[$countrySlug] ?? $countrySlug);
$countryFallbackId = isset($countryFallbackIdMap[$countrySlug]) ? (int) $countryFallbackIdMap[$countrySlug] : 0;
$tvBase = get_tourvisor_proxy_base_url();
$tvImageProxy = get_tourvisor_image_proxy_base_url();

$countryPromoDepartureId = th_departure_default_id();
$countryPromoDepartureName = th_departure_default_name();

$country_promo_listing_url = $country_promo_listing_url ?? '/frontend/window/promotions.php';
?>
<section class="py-8 md:py-12 bg-white border-b border-slate-100" id="country-promo-tours">
    <div class="container mx-auto px-6">
        <div class="max-w-7xl mx-auto">
            <div class="rounded-2xl overflow-hidden shadow-sm border border-sky-100">
            <div class="bg-white px-4 py-6 sm:px-6 sm:py-8">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
                <div>
                    <h2 class="heading-font text-2xl md:text-3xl font-bold text-slate-900">Туры из <?php echo htmlspecialchars($countryNameResolved); ?></h2>
                    <p class="text-slate-600 mt-2 text-sm md:text-base">Актуальные предложения на ближайший месяц. Цены за число взрослых из фильтра на главной.</p>
                </div>
                <button type="button"
                        id="country-promo-pick-btn"
                        class="th-promo-pick-tour-btn shrink-0"
                        data-promo-listing-url="<?php echo htmlspecialchars($country_promo_listing_url, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-fire-alt" aria-hidden="true"></i>
                    Подобрать тур
                </button>
            </div>

            <div id="country-promo-stars-wrap" class="mb-6 hidden">
                <div id="country-promo-filters-row">
                    <span class="th-filter-stars-label">Выберите категорию отеля</span>
                    <div class="country-star-filters__buttons" role="group" aria-label="Категория отеля">
                        <button type="button" class="promo-star-btn country-star-btn country-star-btn--all is-active" data-country-star="" aria-pressed="true">Все</button>
                        <button type="button" class="promo-star-btn country-star-btn country-star-btn--3" data-country-star="3" aria-pressed="false">3★</button>
                        <button type="button" class="promo-star-btn country-star-btn country-star-btn--4" data-country-star="4" aria-pressed="false">4★</button>
                        <button type="button" class="promo-star-btn country-star-btn country-star-btn--5" data-country-star="5" aria-pressed="false">5★</button>
                    </div>
                </div>
            </div>

            <div id="country-promo-loading" class="text-center py-12">
                <i class="fas fa-spinner fa-spin text-4xl text-sky-500 mb-4"></i>
                <p class="text-slate-600">Загрузка туров...</p>
            </div>
            <div id="country-promo-results" class="th-tour-grid hidden"></div>
            <div id="country-promo-empty" class="hidden text-center py-12">
                <p class="text-slate-600">Туры по этому направлению временно недоступны. Попробуйте позже или оставьте заявку.</p>
                <a href="<?php echo htmlspecialchars($country_promo_listing_url, ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex items-center gap-2 mt-4 text-sky-600 font-medium hover:text-sky-700">
                    Горящие туры <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            </div><!-- white inner -->
            </div><!-- rounded card -->
        </div>
    </div>
</section>

<?php
$_th_fp_cp = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'tourvisor-flight-pick.js';
$_th_fp_cp_v = is_file($_th_fp_cp) ? (string) filemtime($_th_fp_cp) : '1';
?>
<script src="/frontend/js/tourvisor-flight-pick.js?v=<?php echo htmlspecialchars($_th_fp_cp_v, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
(function() {
    window.__countryFlightsByTourId = window.__countryFlightsByTourId || {};
    var TV_BASE = <?php echo json_encode($tvBase); ?>;
    var TV_IMAGE = <?php echo json_encode($tvImageProxy); ?>;
    if (typeof location !== 'undefined' && location.protocol === 'https:' && typeof TV_BASE === 'string' && TV_BASE.indexOf('http://') === 0) {
        TV_BASE = 'https:' + TV_BASE.substring(5);
    }
    if (typeof location !== 'undefined' && location.protocol === 'https:' && typeof TV_IMAGE === 'string' && TV_IMAGE.indexOf('http://') === 0) {
        TV_IMAGE = 'https:' + TV_IMAGE.substring(5);
    }
    var COUNTRY_NAME = <?php echo json_encode($countryNameResolved); ?>;
    var COUNTRY_SLUG = <?php echo json_encode($countrySlug); ?>;
    var COUNTRY_ALIASES = <?php echo json_encode($countryAliasMap); ?>;
    var COUNTRY_ID_FALLBACK = <?php echo (int) $countryFallbackId; ?>;
    var PROMO_DEPARTURE_ID = <?php echo (int) $countryPromoDepartureId; ?>;
    var PROMO_DEPARTURE_NAME = <?php echo json_encode($countryPromoDepartureName, JSON_UNESCAPED_UNICODE); ?>;
    try {
        var __lsPid = localStorage.getItem('th_departure_id');
        var __lsPnm = localStorage.getItem('th_departure_name');
        if (__lsPid) {
            var __pParsed = parseInt(String(__lsPid), 10);
            if (!isNaN(__pParsed) && __pParsed > 0) PROMO_DEPARTURE_ID = __pParsed;
        }
        if (__lsPnm) PROMO_DEPARTURE_NAME = String(__lsPnm);
    } catch (__eLsPromo) {}

    function countryPromoAdultsCount() {
        try {
            var raw = localStorage.getItem('tv_tourists');
            if (raw) {
                var j = JSON.parse(raw);
                if (j && typeof j.adults === 'number' && j.adults >= 1 && j.adults <= 9) return j.adults;
            }
        } catch (e) {}
        return 2;
    }
    function countryPromoPriceSuffix(adults) {
        var n = (adults >= 1 && adults <= 9) ? adults : 2;
        return 'за ' + n + ' взрослых';
    }
    function countryPromoPhotoUrls(h) {
        var raw = [];
        if (window.THTourCard && typeof window.THTourCard.collectHotelPhotoRawUrls === 'function') {
            raw = window.THTourCard.collectHotelPhotoRawUrls(h);
        } else if (h) {
            raw.push((h.picturelink || h.pictureLink || '').toString());
            var pics = h.pictures;
            if (pics && Array.isArray(pics)) {
                pics.forEach(function (p) {
                    if (typeof p === 'string') raw.push(p);
                    else if (p && typeof p === 'object') raw.push((p.src || p.url || p.link || p.picturelink || p.pictureLink || '').toString());
                });
            }
            var hid = parseInt(String(h.id || ''), 10);
            if (!raw.filter(function (x) { return x && String(x).trim(); }).length && hid > 0) {
                raw.push('hotel_pics/main400/' + hid + '.jpg');
            }
        }
        var urls = [];
        var seen = {};
        raw.forEach(function (u) {
            if (!u || typeof u !== 'string') return;
            u = u.trim();
            if (!u || seen[u]) return;
            seen[u] = true;
            urls.push(u);
        });
        var max = 4;
        var i = 0;
        while (urls.length > 0 && urls.length < max && i < max) {
            urls.push(urls[urls.length - 1]);
            i++;
        }
        if (urls.length === 0) urls.push('https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400');
        return urls.slice(0, max);
    }

    function formatLocalYMD(d) {
        if (!d || !(d instanceof Date) || isNaN(d.getTime())) return '';
        var y = d.getFullYear();
        var m = String(d.getMonth() + 1);
        if (m.length === 1) m = '0' + m;
        var day = String(d.getDate());
        if (day.length === 1) day = '0' + day;
        return y + '-' + m + '-' + day;
    }

    window.__countryPromoStar = '4-5';
    window.__countryPromoAllHotels = [];

    function imgUrl(src) {
        if (!src) return 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400';
        if (window.THTourCard && typeof window.THTourCard.mapTourvisorImageUrl === 'function') {
            var mapped = window.THTourCard.mapTourvisorImageUrl(src, TV_IMAGE);
            return mapped || 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400';
        }
        var s = String(src).trim();
        if (/^\/\//.test(s)) {
            s = (typeof location !== 'undefined' && location.protocol === 'https:' ? 'https:' : 'http:') + s;
        }
        if (/^https?:\/\/static\.tourvisor\.ru\//i.test(s)) return TV_IMAGE + '?url=' + encodeURIComponent(s.replace(/^https:/i, 'http:'));
        if (/^static\.tourvisor\.ru\//i.test(s)) return TV_IMAGE + '?url=' + encodeURIComponent('http://' + s);
        if (/^\/hotel_pics\//i.test(s) || /^hotel_pics\//i.test(s)) return TV_IMAGE + '?path=' + encodeURIComponent(s.replace(/^\/+/, ''));
        return s;
    }
    function countryPromoCardPrimaryImage(h) {
        var fallback = 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400';
        var urls = [];
        var seen = {};
        function add(u) {
            if (!u || typeof u !== 'string') return;
            u = u.trim();
            if (!u || seen[u]) return;
            seen[u] = true;
            urls.push(u);
        }
        if (h) {
            add((h.picturelink || h.pictureLink || '').toString());
            var pics = h.pictures;
            if (pics && Array.isArray(pics)) {
                pics.forEach(function (p) {
                    if (typeof p === 'string') add(p);
                    else if (p && typeof p === 'object') add((p.src || p.url || p.link || p.picturelink || p.pictureLink || '').toString());
                });
            }
        }
        for (var i = 0; i < urls.length; i++) {
            var u = imgUrl(urls[i]);
            if (u && u.indexOf('unsplash.com') === -1) return u;
        }
        return urls.length ? imgUrl(urls[0]) : fallback;
    }
    function formatPrice(n) {
        var num = Number(n);
        if (isNaN(num)) return '0 ₽';
        return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 }).format(num);
    }
    function countryPromoPickFirstPriceNum() {
        var args = Array.prototype.slice.call(arguments);
        for (var i = 0; i < args.length; i++) {
            var v = args[i];
            if (v == null || v === '') continue;
            var n = Number(v);
            if (!isNaN(n) && n > 0) return n;
        }
        return 0;
    }
    function countryPromoHotelListPrice(h) {
        if (!h) return 0;
        var tour = (h.tours && h.tours[0]) ? h.tours[0] : {};
        if (h.tours && h.tours[0]) {
            var n = countryPromoPickFirstPriceNum(
                tour.totalPrice, tour.price, tour.priceRub, tour.cost
            );
            if (n > 0) return Math.round(n);
            n = countryPromoPickFirstPriceNum(h.price);
            return n > 0 ? Math.round(n) : 0;
        }
        return Math.round(countryPromoPickFirstPriceNum(h.price, h.minPrice, h.minprice));
    }

    function normalizeCountryToken(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/ё/g, 'е')
            .replace(/[^a-zа-я0-9]+/g, ' ')
            .trim();
    }
    function getTokenVariants(token) {
        var base = normalizeCountryToken(token);
        if (!base) return [];
        var compact = base.replace(/\s+/g, '');
        return compact && compact !== base ? [base, compact] : [base];
    }
    function getCountryTokens() {
        var tokens = [];
        if (COUNTRY_NAME) tokens = tokens.concat(getTokenVariants(COUNTRY_NAME));
        if (COUNTRY_SLUG) tokens = tokens.concat(getTokenVariants(COUNTRY_SLUG));
        var aliases = (COUNTRY_ALIASES && COUNTRY_SLUG && Array.isArray(COUNTRY_ALIASES[COUNTRY_SLUG])) ? COUNTRY_ALIASES[COUNTRY_SLUG] : [];
        aliases.forEach(function(a) { tokens = tokens.concat(getTokenVariants(a)); });
        return Array.from(new Set(tokens.filter(Boolean)));
    }
    var COUNTRY_RESOLVER_KEY = '__th_country_id_resolver_state';
    var COUNTRY_RESOLVER_MAP = (window[COUNTRY_RESOLVER_KEY] = window[COUNTRY_RESOLVER_KEY] || {});
    var COUNTRY_RESOLVER_TOKEN = normalizeCountryToken(COUNTRY_SLUG || COUNTRY_NAME || '');
    function toCountryId(value) {
        var num = Number(value);
        return Number.isFinite(num) && num > 0 ? num : null;
    }
    function getCountryResolverState() {
        if (!COUNTRY_RESOLVER_MAP[COUNTRY_RESOLVER_TOKEN]) {
            COUNTRY_RESOLVER_MAP[COUNTRY_RESOLVER_TOKEN] = {
                apiId: null,
                fallbackId: toCountryId(COUNTRY_ID_FALLBACK),
                source: null,
                resolvePromise: null
            };
        } else if (!COUNTRY_RESOLVER_MAP[COUNTRY_RESOLVER_TOKEN].fallbackId) {
            COUNTRY_RESOLVER_MAP[COUNTRY_RESOLVER_TOKEN].fallbackId = toCountryId(COUNTRY_ID_FALLBACK);
        }
        return COUNTRY_RESOLVER_MAP[COUNTRY_RESOLVER_TOKEN];
    }
    function setResolvedCountryId(id, source) {
        var state = getCountryResolverState();
        var normalizedId = toCountryId(id);
        if (!normalizedId) return null;
        if (source === 'api') {
            state.apiId = normalizedId;
            state.source = 'api';
            return normalizedId;
        }
        if (!state.apiId) {
            state.fallbackId = normalizedId;
            state.source = 'fallback';
            return normalizedId;
        }
        return state.apiId;
    }
    function resolveCountryIdShared(fetchCountriesFn) {
        var state = getCountryResolverState();
        if (state.apiId) return Promise.resolve(state.apiId);
        if (state.resolvePromise) return state.resolvePromise;
        state.resolvePromise = Promise.resolve()
            .then(function() { return fetchCountriesFn(); })
            .then(function(resp) {
                var countries = resp && Array.isArray(resp.data) ? resp.data : [];
                var apiId = resolveCountryIdFromApi(countries);
                if (apiId) return setResolvedCountryId(apiId, 'api');
                return setResolvedCountryId(state.fallbackId, 'fallback');
            })
            .catch(function() {
                return setResolvedCountryId(state.fallbackId, 'fallback');
            })
            .finally(function() {
                state.resolvePromise = null;
            });
        return state.resolvePromise;
    }
    function resolveCountryIdFromApi(countries) {
        if (!Array.isArray(countries) || countries.length === 0) return null;
        var tokens = getCountryTokens();
        for (var i = 0; i < countries.length; i++) {
            var c = countries[i] || {};
            var values = []
                .concat(getTokenVariants(c.name))
                .concat(getTokenVariants(c.russianName))
                .concat(getTokenVariants(c.englishName))
                .concat(getTokenVariants(c.slug))
                .filter(Boolean);
            var matched = tokens.some(function(t) {
                return values.some(function(v) {
                    return v === t || v.indexOf(t) !== -1 || t.indexOf(v) !== -1;
                });
            });
            if (matched && c.id != null && c.id !== '') return Number(c.id);
        }
        return null;
    }
    function safeFetchJson(url) {
        return fetch(url, { cache: 'no-store' })
            .then(function(r) { return r.text().then(function(t) { return { ok: r.ok, text: t }; }); })
            .then(function(o) {
                var t = (o.text || '').trim();
                if (!o.ok || !t) return null;
                try { return JSON.parse(t); } catch (e) { return null; }
            })
            .catch(function() { return null; });
    }

    var baseSep = TV_BASE.indexOf('?') >= 0 ? '&' : '?';
    var promoDateFromStr = '';
    var promoDateToStr = '';

    function strClip(s, max) {
        s = String(s == null ? '' : s);
        if (s.length <= max) return s;
        return s.slice(0, max);
    }

    function tourStartYmdFromSearch(tour) {
        if (!tour) return '';
        var raw = String(tour.date || tour.startDate || tour.departureDate || tour.flydate || tour.flyDate || '').trim();
        var m = raw.match(/^(\d{4}-\d{2}-\d{2})/);
        return m ? m[1] : '';
    }
    function tourReturnYmdFromStart(startYmd, nightsNum) {
        if (!startYmd || !nightsNum) return '';
        var p = startYmd.split('-');
        if (p.length !== 3) return '';
        var d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10), 12, 0, 0);
        if (isNaN(d.getTime())) return '';
        d.setDate(d.getDate() + nightsNum);
        var mo = String(d.getMonth() + 1), da = String(d.getDate());
        if (mo.length === 1) mo = '0' + mo;
        if (da.length === 1) da = '0' + da;
        return d.getFullYear() + '-' + mo + '-' + da;
    }

    function fetchRegularSearchCached(countryId, df, dt, forceLive) {
        var q = [
            'type=search-cached',
            'cacheScope=country_page',
            'departureId=' + encodeURIComponent(String(PROMO_DEPARTURE_ID)),
            'countryId=' + encodeURIComponent(countryId),
            'countryName=' + encodeURIComponent(COUNTRY_NAME || ''),
            'dateFrom=' + encodeURIComponent(df),
            'dateTo=' + encodeURIComponent(dt),
            'nightsFrom=5',
            'nightsTo=14',
            'adults=' + encodeURIComponent(String(countryPromoAdultsCount()))
        ];
        if (forceLive) q.push('live=1');
        var url = TV_BASE + baseSep + q.join('&');
        return fetch(url, { cache: 'no-store' })
            .then(function(r) { return r.text().then(function(t) { return { ok: r.ok, text: t }; }); })
            .then(function(o) {
                if (!o.ok || !(o.text || '').trim()) return { success: false, data: [] };
                try { return JSON.parse(o.text); } catch (e) { return { success: false, data: [] }; }
            })
            .catch(function() { return { success: false, data: [] }; });
    }

    function extractHotelList(j) {
        if (!j || typeof j !== 'object') return [];
        if (Array.isArray(j.data)) return j.data;
        if (Array.isArray(j.tours)) return j.tours;
        return [];
    }

    function getHotelStarCategory(h) {
        if (!h) return null;
        var raw = h.category;
        if (raw == null || raw === '') raw = h.hotelCategory != null ? h.hotelCategory : h.stars;
        if (raw == null || raw === '') return null;
        var s = String(raw).trim();
        var n = parseInt(s, 10);
        if (!isNaN(n) && n >= 1 && n <= 5) return n;
        var m = s.match(/([1-5])\s*(?:\*|зв|\u2605|★|stars?)?/i);
        if (m) { n = parseInt(m[1], 10); if (!isNaN(n) && n >= 1 && n <= 5) return n; }
        return null;
    }

    window.__countryPromoStarFilter = '';

    function countryPromoToursHaveAnyStarRating(list) {
        if (!list || !list.length) return false;
        for (var i = 0; i < list.length; i++) {
            if (getHotelStarCategory(list[i]) != null) return true;
        }
        return false;
    }

    function getFilteredCountryPromoHotels() {
        var list = (window.__countryPromoAllHotels || []).slice();
        var starRaw = (typeof window.__countryPromoStarFilter === 'string') ? window.__countryPromoStarFilter.trim() : '';
        if (starRaw === '') return list;
        var want = parseInt(starRaw, 10);
        if (isNaN(want)) return list;
        if (!countryPromoToursHaveAnyStarRating(list)) return list;
        return list.filter(function (h) {
            return getHotelStarCategory(h) === want;
        });
    }

    var __countryPromoStarButtonsBound = false;
    function syncCountryPromoStarButtons() {
        var row = document.getElementById('country-promo-filters-row');
        if (!row) return;
        var cur = (typeof window.__countryPromoStarFilter === 'string') ? window.__countryPromoStarFilter.trim() : '';
        row.querySelectorAll('[data-country-star]').forEach(function (btn) {
            var v = btn.getAttribute('data-country-star') || '';
            var on = (v === cur);
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }
    function initCountryPromoStarButtons() {
        if (__countryPromoStarButtonsBound) return;
        var row = document.getElementById('country-promo-filters-row');
        if (!row) return;
        __countryPromoStarButtonsBound = true;
        row.querySelectorAll('[data-country-star]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                window.__countryPromoStarFilter = btn.getAttribute('data-country-star') || '';
                syncCountryPromoStarButtons();
                renderCountryPromoResults();
            });
        });
        syncCountryPromoStarButtons();
    }

    function renderCountryPromoResults() {
        var results = document.getElementById('country-promo-results');
        var empty = document.getElementById('country-promo-empty');
        if (!results) return;
        var filtered = getFilteredCountryPromoHotels();
        if (filtered.length === 0) {
            results.classList.add('hidden');
            results.innerHTML = '';
            if (empty) {
                empty.querySelector('p').textContent = (window.__countryPromoAllHotels && window.__countryPromoAllHotels.length)
                    ? 'По выбранной категории отеля туров нет. Попробуйте другой фильтр.'
                    : 'Туры по этому направлению временно недоступны. Попробуйте позже или оставьте заявку.';
                empty.classList.remove('hidden');
            }
            return;
        }
        if (empty) empty.classList.add('hidden');
        var tourDetailBase = '/frontend/window';
        var roomCategoryDefault = 'Стандарт';
        var fallbackImg = 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=600&q=80';
        var priceFormatter = new Intl.NumberFormat('ru-RU');
        function fmtPriceSimple(n) { return n > 0 ? priceFormatter.format(n) + '\u00a0\u20bd' : 'Уточняйте'; }
        try {
            results.innerHTML = filtered.map(function(h) {
                var adultsN = countryPromoAdultsCount();
                var img = countryPromoCardPrimaryImage(h);
                var region = (h.region && h.region.name) ? h.region.name : '';
                var country = (h.country && h.country.name) ? h.country.name : '';
                var geoStr = (country + (region ? ', ' + region : '')).replace(/</g, '&lt;');
                var tour = (h.tours && h.tours[0]) ? h.tours[0] : {};
                var meal = (tour.meal && (tour.meal.russianName || tour.meal.name)) ? (tour.meal.russianName || tour.meal.name) : '';
                var nights = tour.nights ? parseInt(String(tour.nights), 10) : 0;
                var nightsLbl = nights ? (nights === 1 ? '1\u00a0ночь' : nights < 5 ? nights + '\u00a0ночи' : nights + '\u00a0ночей') : '';
                var datesMeta = (nightsLbl ? nightsLbl + ', ' : '') + adultsN + '\u00a0взр.';
                var catNum = getHotelStarCategory(h) || 0;
                var starsHtml = catNum > 0 ? '\u2605'.repeat(Math.min(catNum, 5)) : '';
                var priceNum = countryPromoHotelListPrice(h);
                var link = h.hotelDescriptionLink || h.hoteldescriptionlink || h.link || '';
                var desc = strClip((h.description || h.hotelDescription || '').toString().trim().replace(/\s+/g, ' '), 900);
                var roomCategory = strClip((tour.roomType || h.roomCategory || roomCategoryDefault).toString().trim() || roomCategoryDefault, 120);
                var tourId = (tour.id != null && tour.id !== '') ? String(tour.id) : '';
                var startY = tourStartYmdFromSearch(tour);
                var retY = (startY && nights) ? tourReturnYmdFromStart(startY, nights) : '';
                var hotelNameSafe = strClip((h.name || '').toString(), 200);
                var tourDetailParams = {
                    tour_link: strClip(link, 2000), country: strClip(country, 120), hotel_name: hotelNameSafe,
                    price: priceNum > 0 ? String(priceNum) : '',
                    nights: String(tour.nights || ''), meal: strClip(meal, 80), region: strClip(region, 120),
                    departure_city: PROMO_DEPARTURE_NAME, date_from: startY || '', date_to: retY || '',
                    image: strClip(img, 2000), description: desc, rating: String(h.rating || ''),
                    category: strClip(String(h.category || ''), 80),
                    room_category: roomCategory, tour_id: tourId, adults: String(adultsN)
                };
                if (h.id) tourDetailParams.hotel_id = String(h.id);
                if (PROMO_DEPARTURE_ID) tourDetailParams.departure_id = String(PROMO_DEPARTURE_ID);
            try {
                tourDetailParams.return_url = (window.TourSessionManager && window.TourSessionManager.buildReturnUrl)
                    ? window.TourSessionManager.buildReturnUrl()
                    : strClip(window.location.pathname + window.location.search, 500);
            } catch (e) {}
                var tourDetailUrl = tourDetailBase + '/tour-detail.php?' + new URLSearchParams(tourDetailParams).toString();
                if (tourDetailUrl.length > 8000) {
                    tourDetailParams.description = strClip(tourDetailParams.description, 400);
                    tourDetailUrl = tourDetailBase + '/tour-detail.php?' + new URLSearchParams(tourDetailParams).toString();
                }
                var nameEsc = hotelNameSafe.replace(/</g, '&lt;').replace(/"/g, '&quot;');
                var imgEsc = img.replace(/"/g, '&quot;');
                var fbEsc = fallbackImg.replace(/"/g, '&quot;');
                if (window.THTourCard && typeof window.THTourCard.render === 'function') {
                    var flightMetaCp = (window.__countryFlightsByTourId && tourId) ? window.__countryFlightsByTourId[tourId] : null;
                    return window.THTourCard.render(h, {
                        tour: tour, countryCard: true, skipPromoPatch: true, detailUrl: tourDetailUrl,
                        getImageUrl: imgUrl, imageProxy: TV_IMAGE,
                        adults: adultsN, dateFrom: startY, dateTo: retY, price: priceNum, meal: meal,
                        departureCity: PROMO_DEPARTURE_NAME, departureId: PROMO_DEPARTURE_ID,
                        carousel: true, image: img,
                        flightMeta: flightMetaCp
                    });
                }
                var flightHtmlCp = (window.THTourCard && typeof window.THTourCard.buildFlightBlockHtml === 'function')
                    ? window.THTourCard.buildFlightBlockHtml(PROMO_DEPARTURE_NAME, tourId, {
                        flightMeta: (window.__countryFlightsByTourId && tourId) ? window.__countryFlightsByTourId[tourId] : null
                    })
                    : '<span class="th-tour-card__dep-city">Вылет: ' + PROMO_DEPARTURE_NAME.replace(/</g, '&lt;') + '</span>';
                var tourIdAttrCp = tourId ? ' data-th-tour-id="' + tourId.replace(/"/g, '&quot;') + '"' : '';
                var hotelIdAttrCp = h.id ? ' data-th-hotel-id="' + String(h.id).replace(/"/g, '&quot;') + '"' : '';
                return '<article class="th-tour-card th-tour-card--country" data-promo-patched="skip"' + hotelIdAttrCp + tourIdAttrCp +
                    ' data-th-departure-city="' + PROMO_DEPARTURE_NAME.replace(/"/g, '&quot;') + '">' +
                    '<a href="' + tourDetailUrl.replace(/"/g, '&quot;') + '" class="th-tour-card__link th-tour-card__link--main">' +
                    '<div class="th-tour-card__media">' +
                    '<img src="' + imgEsc + '" alt="' + nameEsc + '" class="th-tour-card__img" loading="eager" decoding="async" onerror="this.onerror=null;this.src=\'' + fbEsc + '\'">' +
                    '</div>' +
                    '<div class="th-tour-card__body">' +
                    (geoStr ? '<p class="th-tour-card__geo">' + geoStr + '</p>' : '') +
                    '<div class="th-tour-card__name-row">' +
                    '<h3 class="th-tour-card__name">' + nameEsc + '</h3>' +
                    (starsHtml ? '<span class="th-tour-card__stars">' + starsHtml + '</span>' : '') +
                    '</div>' +
                    (meal ? '<span class="th-tour-card__meal-badge">' + meal.replace(/</g, '&lt;') + '</span>' : '') +
                    flightHtmlCp +
                    '<div class="th-tour-card__price-block">' +
                    '<span class="th-tour-card__price-label">' + countryPromoPriceSuffix(adultsN) + '</span>' +
                    '<span class="th-tour-card__price">' + fmtPriceSimple(priceNum) + '</span>' +
                    '<span class="th-tour-card__dates">' + datesMeta + '</span>' +
                    '</div>' +
                    '</div></a>' +
                    '<div class="th-tour-card__actions">' +
                    '<a href="' + tourDetailUrl.replace(/"/g, '&quot;') + '" class="th-tour-card__btn th-tour-card__btn--secondary">' + (window.THTourCard && window.THTourCard.DETAIL_BTN_LABEL ? window.THTourCard.DETAIL_BTN_LABEL : 'Просмотреть параметры тура и забронировать') + '</a>' +
                    '</div></article>';
            }).join('');
            results.classList.remove('hidden');
            window.__countryFlightsByTourId = window.__countryFlightsByTourId || {};
            if (typeof thLoadTourFlightsForHotels === 'function' && TV_BASE) {
                thLoadTourFlightsForHotels(filtered, {
                    apiBase: TV_BASE.replace(/\/$/, ''),
                    departureCity: PROMO_DEPARTURE_NAME,
                    maxTours: filtered.length,
                    maxConcurrent: 6,
                    getTourId: function (h) {
                        var t = (h.tours && h.tours[0]) ? h.tours[0] : {};
                        return (t.id != null && t.id !== '') ? String(t.id) : '';
                    },
                    patchContainer: results
                });
            } else if (window.THTourCard && typeof window.THTourCard.patchFlightsInContainer === 'function') {
                window.THTourCard.patchFlightsInContainer(results);
            }
            if (window.THTourCard && typeof window.THTourCard.ensureCarouselsInContainer === 'function') {
                window.THTourCard.ensureCarouselsInContainer(results);
            } else if (window.THTourCard && typeof window.THTourCard.kickImagesInContainer === 'function') {
                window.THTourCard.kickImagesInContainer(results);
            }
        } catch (renderErr) {
            console.error('[country-tours] Ошибка отрисовки:', renderErr);
            if (empty) { empty.querySelector('p').textContent = 'Не удалось отобразить туры. Попробуйте обновить страницу.'; empty.classList.remove('hidden'); }
        }
    }

    function syncPromoDepartureFromStorage(detail) {
        try {
            var idRaw = (detail && detail.id != null) ? String(detail.id) : localStorage.getItem('th_departure_id');
            var nmRaw = (detail && detail.name) ? String(detail.name) : localStorage.getItem('th_departure_name');
            if (idRaw) {
                var pid = parseInt(String(idRaw), 10);
                if (!isNaN(pid) && pid > 0) PROMO_DEPARTURE_ID = pid;
            }
            if (nmRaw) PROMO_DEPARTURE_NAME = String(nmRaw);
        } catch (eSync) {}
    }

    function loadCountryPromoBlock() {
        syncPromoDepartureFromStorage(null);
        var loading = document.getElementById('country-promo-loading');
        var results = document.getElementById('country-promo-results');
        var empty = document.getElementById('country-promo-empty');
        var starsWrap = document.getElementById('country-promo-stars-wrap');
        if (loading) {
            loading.classList.remove('hidden');
            loading.querySelector('p').textContent = 'Загрузка туров...';
        }
        if (results) {
            results.classList.add('hidden');
            results.innerHTML = '';
        }
        if (empty) empty.classList.add('hidden');
        if (starsWrap) starsWrap.classList.add('hidden');

        resolveCountryIdShared(function() {
                return safeFetchJson(TV_BASE + baseSep + 'type=countries');
            })
            .then(function(resolvedCountryId) {
                resolvedCountryId = Number(resolvedCountryId) || null;
                if (!resolvedCountryId) throw new Error('Country id not resolved');
                var dFrom = new Date();
                var dTo = new Date(); dTo.setDate(dTo.getDate() + 30);
                var df1 = formatLocalYMD(dFrom);
                var dt1 = formatLocalYMD(dTo);
                return fetchRegularSearchCached(resolvedCountryId, df1, dt1, false).then(function(j) {
                    var data = extractHotelList(j);
                    if (data.length > 0 && j && j.success !== false) {
                        promoDateFromStr = df1;
                        promoDateToStr = dt1;
                        return j;
                    }
                    /* Фоллбэк: +60 дней, живой поиск */
                    var dTo2 = new Date(); dTo2.setDate(dTo2.getDate() + 60);
                    var dt2 = formatLocalYMD(dTo2);
                    return fetchRegularSearchCached(resolvedCountryId, df1, dt2, true).then(function(j2) {
                        promoDateFromStr = df1;
                        promoDateToStr = dt2;
                        return j2;
                    });
                });
            })
            .then(function(j) {
                var loadingEl = document.getElementById('country-promo-loading');
                var resultsEl = document.getElementById('country-promo-results');
                var emptyEl = document.getElementById('country-promo-empty');
                var starsWrapEl = document.getElementById('country-promo-stars-wrap');
                if (loadingEl) loadingEl.classList.add('hidden');

                if (!resultsEl) {
                    console.error('[country-promo] Нет #country-promo-results в DOM');
                    if (emptyEl) { emptyEl.querySelector('p').textContent = 'Не удалось показать блок. Обновите страницу.'; emptyEl.classList.remove('hidden'); }
                    return;
                }

                var data = extractHotelList(j);

                if (data.length === 0) {
                    if (emptyEl) emptyEl.classList.remove('hidden');
                    return;
                }

                data.sort(function (a, b) {
                    return countryPromoHotelListPrice(a) - countryPromoHotelListPrice(b);
                });
                window.__countryPromoAllHotels = data;
                window.__countryPromoStarFilter = '';
                if (starsWrapEl) starsWrapEl.classList.remove('hidden');
                initCountryPromoStarButtons();
                syncCountryPromoStarButtons();
                renderCountryPromoResults();
            })
            .catch(function(err) {
                console.error('[country-promo] Загрузка:', err);
                var loadingEl = document.getElementById('country-promo-loading');
                var emptyEl = document.getElementById('country-promo-empty');
                if (loadingEl) loadingEl.classList.add('hidden');
                if (emptyEl) { emptyEl.querySelector('p').textContent = 'Не удалось загрузить туры. Попробуйте позже.'; emptyEl.classList.remove('hidden'); }
            });
    }

    loadCountryPromoBlock();
    window.addEventListener('th-departure-saved', function (ev) {
        syncPromoDepartureFromStorage(ev && ev.detail ? ev.detail : null);
        loadCountryPromoBlock();
    });

    (function bindCountryPromoPickBtn() {
        var btn = document.getElementById('country-promo-pick-btn');
        if (!btn || btn.__thBound) return;
        btn.__thBound = true;
        btn.addEventListener('click', function () {
            var base = btn.getAttribute('data-promo-listing-url') || '/frontend/window/promotions.php';
            var depId = PROMO_DEPARTURE_ID;
            var depName = PROMO_DEPARTURE_NAME;
            try {
                var lid = localStorage.getItem('th_departure_id');
                var lnm = localStorage.getItem('th_departure_name');
                if (lid && lnm) {
                    depId = parseInt(String(lid), 10) || depId;
                    depName = String(lnm);
                }
            } catch (eLs) {}
            if (depId !== 1) { depId = 7; depName = 'Самара'; }
            try {
                var u = new URL(base, window.location.origin);
                u.searchParams.set('departureId', String(depId));
                u.searchParams.set('departureName', depName);
                window.location.href = u.pathname + u.search + u.hash;
            } catch (eUrl) {
                window.location.href = base;
            }
        });
    })();
})();
</script>
