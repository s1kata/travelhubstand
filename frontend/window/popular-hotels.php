<?php
/**
 * Популярные отели — mobile-first витрина: только отели с турами и ценой.
 */
require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/components/tourvisor_proxy_url.php';
require_once __DIR__ . '/../../backend/config/departure_defaults.php';
require_once __DIR__ . '/../../backend/components/promo_virtual_destinations.php';
session_start();

$popularCountriesPath = __DIR__ . '/../../backend/config/popular_countries.php';
$popularCountries = is_file($popularCountriesPath) ? require $popularCountriesPath : [];
if (!is_array($popularCountries)) {
    $popularCountries = [];
}

$tvApiBase = get_tourvisor_proxy_base_url();
$imgProxyBase = get_tourvisor_image_proxy_base_url();
$defaultCountryId = !empty($popularCountries[0]['id']) ? (int) $popularCountries[0]['id'] : 4;
$departureId = th_departure_default_id();
$departureName = th_departure_default_name();

$countryMeta = [];
foreach ($popularCountries as $c) {
    $cid = (int) ($c['id'] ?? 0);
    if ($cid <= 0) {
        continue;
    }
    $countryMeta[$cid] = [
        'id' => $cid,
        'name' => (string) ($c['name'] ?? ''),
        'tvCountryId' => th_promo_resolve_tv_country_id($cid),
        'regionIds' => th_promo_virtual_region_ids($cid),
    ];
}

$dateFrom = date('Y-m-d', strtotime('+3 days'));
$dateTo = date('Y-m-d', strtotime('+17 days'));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title>Популярные отели — Travel Hub</title>
    <meta name="description" content="Отели с реальными турами и ценами. Удобный выбор с телефона.">
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/frontend/css/pages/popular-hotels.css?v=8">
    <?php include __DIR__ . '/../../backend/components/design_system_head.php'; ?>
</head>
<body class="ph antialiased">
<?php
$current_page = 'popular-hotels';
include __DIR__ . '/../../backend/components/header.php';
?>

<div class="ph-shell">
    <header class="ph-top">
        <div class="ph-top__badge"><i class="fas fa-bolt"></i> Только с турами</div>
        <h1>Популярные отели</h1>
        <p>Выберите страну — откройте отель — сразу туры с ценой. Без лишних шагов.</p>
    </header>

    <div class="ph-sticky">
        <div class="ph-countries" id="ph-countries" role="tablist" aria-label="Страны">
            <?php foreach ($popularCountries as $i => $c): ?>
                <?php
                $cid = (int) ($c['id'] ?? 0);
                $cname = (string) ($c['name'] ?? '');
                if ($cid <= 0 || $cname === '') {
                    continue;
                }
                ?>
                <button type="button"
                        class="ph-chip<?php echo $i === 0 ? ' is-active' : ''; ?>"
                        data-country-id="<?php echo $cid; ?>"
                        role="tab"
                        aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>">
                    <?php echo htmlspecialchars($cname, ENT_QUOTES, 'UTF-8'); ?>
                </button>
            <?php endforeach; ?>
        </div>
        <div class="ph-row2">
            <div class="ph-stars" id="ph-stars" aria-label="Звёзды">
                <button type="button" class="ph-star is-active" data-stars="0">Все</button>
                <button type="button" class="ph-star" data-stars="3">3+</button>
                <button type="button" class="ph-star" data-stars="4">4+</button>
                <button type="button" class="ph-star" data-stars="5">5★</button>
            </div>
            <label class="sr-only" for="ph-sort">Сортировка</label>
            <select id="ph-sort" class="ph-sort" aria-label="Сортировка">
                <option value="price">Дешевле</option>
                <option value="rating">Рейтинг</option>
                <option value="stars">Звёзды</option>
            </select>
        </div>
    </div>

    <div class="ph-meta">
        <span>Вылет: <strong><?php echo htmlspecialchars($departureName, ENT_QUOTES, 'UTF-8'); ?></strong></span>
        <span id="ph-count">—</span>
    </div>

    <div id="ph-skel" class="ph-skel" aria-hidden="true">
        <?php for ($i = 0; $i < 3; $i++): ?>
            <div class="ph-skel__card">
                <div class="ph-skel__media"></div>
                <div class="ph-skel__lines">
                    <div class="ph-skel__line ph-skel__line--short"></div>
                    <div class="ph-skel__line ph-skel__line--mid"></div>
                    <div class="ph-skel__line"></div>
                </div>
            </div>
        <?php endfor; ?>
    </div>
    <p id="ph-skel-hint" class="ph-skel-hint">Загружаем цены отелей…</p>

    <div id="ph-status" class="ph-status hidden"></div>
    <div id="ph-grid" class="ph-grid" aria-live="polite"></div>
    <div id="ph-more" class="ph-more hidden">
        <button type="button" id="ph-more-btn">Показать ещё</button>
    </div>
</div>

<style>.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0}</style>

<script>
(function () {
    var TV_API_BASE = <?php echo json_encode($tvApiBase, JSON_UNESCAPED_UNICODE); ?>;
    var IMG_PROXY = <?php echo json_encode($imgProxyBase, JSON_UNESCAPED_UNICODE); ?>;
    var COUNTRY_META = <?php echo json_encode($countryMeta, JSON_UNESCAPED_UNICODE); ?>;
    var DEFAULT_COUNTRY = <?php echo (int) $defaultCountryId; ?>;
    var DEPARTURE_ID = <?php echo (int) $departureId; ?>;
    var DATE_FROM = <?php echo json_encode($dateFrom); ?>;
    var DATE_TO = <?php echo json_encode($dateTo); ?>;
    var PAGE = 12;
    var PLACEHOLDER = 'data:image/svg+xml,' + encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="400"><rect fill="#d9ecea" width="640" height="400"/><text x="50%" y="50%" fill="#94a3b8" font-family="sans-serif" font-size="20" text-anchor="middle" dy=".3em">Отель</text></svg>'
    );

    var state = {
        countryId: DEFAULT_COUNTRY,
        category: 0,
        sort: 'price',
        hotels: [],
        shown: 0,
        loading: false,
        reqId: 0
    };

    try {
        var qCountry = parseInt(new URLSearchParams(window.location.search).get('country') || '', 10);
        if (qCountry && COUNTRY_META[qCountry]) {
            state.countryId = qCountry;
            document.querySelectorAll('#ph-countries .ph-chip').forEach(function (b) {
                var on = parseInt(b.getAttribute('data-country-id'), 10) === qCountry;
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-selected', on ? 'true' : 'false');
            });
        }
    } catch (e0) {}

    var el = {
        grid: document.getElementById('ph-grid'),
        skel: document.getElementById('ph-skel'),
        skelHint: document.getElementById('ph-skel-hint'),
        status: document.getElementById('ph-status'),
        more: document.getElementById('ph-more'),
        moreBtn: document.getElementById('ph-more-btn'),
        sort: document.getElementById('ph-sort'),
        count: document.getElementById('ph-count')
    };

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function proxyImg(url) {
        if (!url) return PLACEHOLDER;
        var u = String(url).trim();
        if (u.indexOf('//') === 0) u = 'https:' + u;
        if (IMG_PROXY && /static\.tourvisor\.ru/i.test(u)) {
            return IMG_PROXY + '?url=' + encodeURIComponent(u);
        }
        return u;
    }

    function fmtPrice(n) {
        var num = parseInt(String(n), 10) || 0;
        if (!num) return '';
        return num.toLocaleString('ru-RU') + ' ₽';
    }

    function pickPrice() {
        for (var i = 0; i < arguments.length; i++) {
            var n = Number(arguments[i]);
            if (!isNaN(n) && n > 0) return Math.round(n);
        }
        return 0;
    }

    function hotelMinPrice(h) {
        var min = 0;
        var tours = Array.isArray(h.tours) ? h.tours : [];
        tours.forEach(function (t) {
            var p = pickPrice(t && t.totalPrice, t && t.price, t && t.priceRub, t && t.cost);
            if (p > 0 && (min === 0 || p < min)) min = p;
        });
        if (!min) min = pickPrice(h.price, h.priceFrom, h.minPrice, h.minprice);
        return min;
    }

    function hotelPhoto(h) {
        if (h.picturelink) return h.picturelink;
        if (Array.isArray(h.pictures) && h.pictures[0]) return h.pictures[0];
        if (Array.isArray(h.images) && h.images[0]) return h.images[0];
        return '';
    }

    function metaFor(cid) {
        return COUNTRY_META[String(cid)] || COUNTRY_META[cid] || {
            id: cid, tvCountryId: cid, regionIds: [], name: ''
        };
    }

    function tvUrl(type, params) {
        var u;
        try { u = new URL(TV_API_BASE); } catch (e) { u = new URL(TV_API_BASE, window.location.origin); }
        u.searchParams.set('type', type);
        Object.keys(params || {}).forEach(function (k) {
            if (params[k] != null && params[k] !== '') u.searchParams.set(k, String(params[k]));
        });
        return u.toString();
    }

    async function fetchHotels(params) {
        var api = Object.assign({}, params, {
            cacheScope: 'country_page',
            slim: '1',
            live: '1',
            _t: String(Date.now())
        });
        var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timer = ctrl ? setTimeout(function () { try { ctrl.abort(); } catch (e) {} }, 55000) : null;
        try {
            var r = await fetch(tvUrl('search-cached', api), {
                method: 'GET',
                cache: 'no-store',
                signal: ctrl ? ctrl.signal : undefined
            });
            var text = await r.text();
            var j = text ? JSON.parse(text) : { success: false };
            return j;
        } finally {
            if (timer) clearTimeout(timer);
        }
    }

    function groupHotels(raw) {
        var map = {};
        (raw || []).forEach(function (h) {
            if (!h) return;
            var id = parseInt(h.id, 10) || 0;
            if (!id) return;
            var price = hotelMinPrice(h);
            if (!(price > 0)) return;
            if (!map[id]) {
                map[id] = {
                    id: id,
                    name: h.name || 'Отель',
                    category: parseInt(h.category, 10) || 0,
                    rating: parseFloat(h.rating) || 0,
                    countryId: (h.country && h.country.id) ? parseInt(h.country.id, 10) : state.countryId,
                    regionName: (h.region && h.region.name) || (h.country && h.country.name) || h.regionName || '',
                    picturelink: hotelPhoto(h),
                    minPrice: price
                };
            } else if (price > 0 && (map[id].minPrice === 0 || price < map[id].minPrice)) {
                map[id].minPrice = price;
                if (!map[id].picturelink) map[id].picturelink = hotelPhoto(h);
            }
        });
        return Object.keys(map).map(function (k) { return map[k]; });
    }

    function applySort(list) {
        var out = list.slice();
        if (state.category > 0) {
            out = out.filter(function (h) { return (h.category || 0) >= state.category; });
        }
        out.sort(function (a, b) {
            if (state.sort === 'rating') return (b.rating || 0) - (a.rating || 0);
            if (state.sort === 'stars') return (b.category || 0) - (a.category || 0);
            return (a.minPrice || 0) - (b.minPrice || 0);
        });
        return out;
    }

    function detailUrl(h) {
        return '/frontend/window/hotels/tv-hotel-detail.php?id=' + encodeURIComponent(h.id) +
            '&countryId=' + encodeURIComponent(h.countryId || state.countryId);
    }

    function cardHtml(h, idx) {
        var stars = h.category > 0 ? (h.category + '★') : '';
        var rate = h.rating ? Number(h.rating).toFixed(1) : '';
        var price = fmtPrice(h.minPrice);
        var delay = Math.min(idx, 10) * 30;
        return (
            '<a class="ph-card" style="animation-delay:' + delay + 'ms" href="' + detailUrl(h) + '">' +
            '<div class="ph-card__media">' +
            '<img src="' + esc(proxyImg(h.picturelink)) + '" alt="' + esc(h.name) + '" loading="lazy" decoding="async" onerror="this.onerror=null;this.src=\'' + PLACEHOLDER + '\'">' +
            '<div class="ph-card__badges">' +
            (stars ? '<span class="ph-badge">' + esc(stars) + '</span>' : '<span></span>') +
            (rate ? '<span class="ph-badge ph-badge--rate"><i class="fas fa-star"></i> ' + esc(rate) + '</span>' : '') +
            '</div></div>' +
            '<div class="ph-card__body">' +
            (h.regionName ? '<div class="ph-card__place">' + esc(h.regionName) + '</div>' : '') +
            '<h3 class="ph-card__name">' + esc(h.name) + '</h3>' +
            '<div class="ph-card__foot">' +
            '<div class="ph-card__price"><span>' + (price ? 'туры от' : 'цена') + '</span><strong>' + esc(price || 'по запросу') + '</strong></div>' +
            '<span class="ph-card__go">Туры <i class="fas fa-arrow-right"></i></span>' +
            '</div></div></a>'
        );
    }

    function setLoading(on) {
        el.skel.classList.toggle('hidden', !on);
        if (el.skelHint) el.skelHint.classList.toggle('is-hidden', !on);
        if (on) {
            el.status.classList.add('hidden');
            el.grid.innerHTML = '';
            el.more.classList.add('hidden');
            el.count.textContent = 'Загрузка…';
        }
    }

    function showStatus(html, isError) {
        el.status.innerHTML = html;
        el.status.classList.remove('hidden');
        el.status.classList.toggle('ph-status--error', !!isError);
    }

    function render(reset) {
        var list = applySort(state.hotels);
        if (reset) state.shown = 0;
        el.count.textContent = list.length ? (list.length + ' отелей') : '0 отелей';

        if (!list.length) {
            el.grid.innerHTML = '';
            el.more.classList.add('hidden');
            showStatus('<i class="fas fa-hotel"></i><p>Пока нет отелей с турами.<br>Выберите другую страну или звёзды.</p>', false);
            return;
        }
        el.status.classList.add('hidden');
        var next = Math.min(list.length, state.shown + PAGE);
        var slice = list.slice(state.shown, next);
        var html = slice.map(function (h, i) { return cardHtml(h, state.shown + i); }).join('');
        if (state.shown === 0) el.grid.innerHTML = html;
        else el.grid.insertAdjacentHTML('beforeend', html);
        state.shown = next;
        el.more.classList.toggle('hidden', state.shown >= list.length);
        el.moreBtn.disabled = false;
        el.moreBtn.textContent = 'Показать ещё';
    }

    async function load() {
        var reqId = ++state.reqId;
        state.loading = true;
        setLoading(true);

        var meta = metaFor(state.countryId);
        var params = {
            departureId: DEPARTURE_ID,
            countryId: meta.tvCountryId || state.countryId,
            dateFrom: DATE_FROM,
            dateTo: DATE_TO,
            nightsFrom: 6,
            nightsTo: 14,
            adults: 2,
            currency: 'RUB'
        };
        if (state.category > 0) params.hotelCategory = state.category;
        if (meta.regionIds && meta.regionIds.length) {
            params.regionIds = meta.regionIds.join(',');
        }

        try {
            var j = await fetchHotels(params);
            if (reqId !== state.reqId) return;
            var raw = (j && j.success && Array.isArray(j.data)) ? j.data : [];
            var grouped = groupHotels(raw);
            if (!grouped.length) {
                var cat = await fetch(tvUrl('hotels', {
                    countryId: meta.tvCountryId || state.countryId,
                    limit: 48,
                    sort: 'rating',
                    category: state.category > 0 ? state.category : ''
                }), { cache: 'no-store' }).then(function (r) { return r.json(); }).catch(function () { return null; });
                var rows = [];
                if (cat && cat.success) {
                    if (Array.isArray(cat.data)) rows = cat.data;
                    else if (cat.data && Array.isArray(cat.data.hotels)) rows = cat.data.hotels;
                }
                grouped = groupHotels(rows);
            }
            if (reqId !== state.reqId) return;
            state.hotels = grouped;
            setLoading(false);
            render(true);
        } catch (e) {
            if (reqId !== state.reqId) return;
            setLoading(false);
            el.grid.innerHTML = '';
            el.more.classList.add('hidden');
            el.count.textContent = '—';
            var msg = (e && e.name === 'AbortError')
                ? 'Долго отвечаем. Нажмите страну ещё раз.'
                : (e.message || 'Ошибка загрузки');
            showStatus('<i class="fas fa-exclamation-circle"></i><p>' + esc(msg) + '</p>', true);
        } finally {
            if (reqId === state.reqId) state.loading = false;
        }
    }

    document.getElementById('ph-countries').addEventListener('click', function (e) {
        var btn = e.target.closest('.ph-chip');
        if (!btn) return;
        document.querySelectorAll('#ph-countries .ph-chip').forEach(function (b) {
            b.classList.remove('is-active');
            b.setAttribute('aria-selected', 'false');
        });
        btn.classList.add('is-active');
        btn.setAttribute('aria-selected', 'true');
        state.countryId = parseInt(btn.getAttribute('data-country-id'), 10) || DEFAULT_COUNTRY;
        btn.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
        load();
    });

    document.getElementById('ph-stars').addEventListener('click', function (e) {
        var btn = e.target.closest('.ph-star');
        if (!btn) return;
        document.querySelectorAll('.ph-star').forEach(function (b) { b.classList.remove('is-active'); });
        btn.classList.add('is-active');
        state.category = parseInt(btn.getAttribute('data-stars'), 10) || 0;
        load();
    });

    el.sort.addEventListener('change', function () {
        state.sort = el.sort.value || 'price';
        render(true);
        try { window.scrollTo({ top: el.grid.offsetTop - 80, behavior: 'smooth' }); } catch (e) {}
    });

    el.moreBtn.addEventListener('click', function () {
        el.moreBtn.disabled = true;
        el.moreBtn.textContent = '…';
        render(false);
    });

    load();
})();
</script>

<?php include __DIR__ . '/../../backend/components/footer.php'; ?>
</body>
</html>
