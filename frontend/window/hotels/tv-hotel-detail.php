<?php
/**
 * Hotel hub mobile-first: коротко об отеле + сразу карточки туров (25 + ещё), без формы поиска.
 */
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/components/tourvisor_proxy_url.php';
require_once __DIR__ . '/../../../backend/config/departure_defaults.php';
session_start();

$hotelId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$countryIdHint = isset($_GET['countryId']) ? (int) $_GET['countryId'] : 0;
$hotel = null;
$error = null;
$tvApiBase = get_tourvisor_proxy_base_url();
$imgProxyBase = get_tourvisor_image_proxy_base_url();
$departureId = th_departure_id_from_request();
$departureName = th_departure_default_name();
if ($departureId === 99) {
    $departureName = 'Без перелёта';
} elseif (!empty($_GET['departureName'])) {
    $dn = trim((string) $_GET['departureName']);
    if ($dn !== '' && !th_departure_is_blocked_name($dn)) {
        $departureName = $dn;
    }
}

function thh_proxy_image(?string $url, string $imgProxyBase): string
{
    $u = trim((string) $url);
    if ($u === '') {
        return '';
    }
    if (strpos($u, '//') === 0) {
        $u = 'https:' . $u;
    }
    if ($imgProxyBase !== '' && preg_match('#^hotel_pics/#i', $u)) {
        $u = 'https://static.tourvisor.ru/' . ltrim($u, '/');
    }
    if ($imgProxyBase !== '' && stripos($u, 'static.tourvisor.ru') !== false) {
        return rtrim($imgProxyBase, '/') . '?url=' . rawurlencode($u);
    }
    return $u;
}

/** @return array<string, mixed>|null */
function thh_fetch_hotel(string $tvApiBase, int $hotelId): ?array
{
    if ($hotelId <= 0 || $tvApiBase === '') {
        return null;
    }
    $sep = strpos($tvApiBase, '?') !== false ? '&' : '?';
    $url = $tvApiBase . $sep . 'type=hotel&hotelId=' . $hotelId;
    $ch = curl_init($url);
    if ($ch === false) {
        return null;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 400 || !is_string($body) || trim($body) === '') {
        return null;
    }
    $json = json_decode($body, true);
    if (!is_array($json) || empty($json['success']) || !is_array($json['data'] ?? null)) {
        return null;
    }
    return $json['data'];
}

function thh_text(?string $html): string
{
    return trim(strip_tags((string) $html));
}

function thh_clip(string $text, int $limit): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    if (mb_strlen($text) <= $limit) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $limit - 1)) . '…';
}

if ($hotelId <= 0) {
    $error = 'Не передан id отеля.';
} else {
    $hotel = thh_fetch_hotel($tvApiBase, $hotelId);
    if (!$hotel) {
        $error = 'Не удалось загрузить отель. Попробуйте позже.';
    }
}

$imagesAll = [];
if (is_array($hotel)) {
    if (!empty($hotel['images']) && is_array($hotel['images'])) {
        foreach ($hotel['images'] as $img) {
            $raw = '';
            if (is_string($img)) {
                $raw = trim($img);
            } elseif (is_array($img)) {
                $raw = trim((string) ($img['url'] ?? $img['src'] ?? $img['link'] ?? $img['picturelink'] ?? $img['pictureLink'] ?? $img['picture'] ?? ''));
            }
            if ($raw !== '') {
                $imagesAll[] = thh_proxy_image($raw, $imgProxyBase);
            }
        }
    }
    if ($imagesAll === [] && !empty($hotel['pictures']) && is_array($hotel['pictures'])) {
        foreach ($hotel['pictures'] as $img) {
            $raw = '';
            if (is_string($img)) {
                $raw = trim($img);
            } elseif (is_array($img)) {
                $raw = trim((string) ($img['url'] ?? $img['src'] ?? $img['link'] ?? $img['picturelink'] ?? $img['pictureLink'] ?? ''));
            }
            if ($raw !== '') {
                $imagesAll[] = thh_proxy_image($raw, $imgProxyBase);
            }
        }
    }
    if ($imagesAll === [] && !empty($hotel['picturelink'])) {
        $pic = thh_proxy_image((string) $hotel['picturelink'], $imgProxyBase);
        if ($pic !== '') {
            $imagesAll[] = $pic;
        }
    }
    if ($imagesAll === [] && $hotelId > 0) {
        $fallbackPic = thh_proxy_image('hotel_pics/main400/' . $hotelId . '.jpg', $imgProxyBase);
        if ($fallbackPic === '') {
            $fallbackPic = thh_proxy_image('https://static.tourvisor.ru/hotel_pics/main400/' . $hotelId . '.jpg', $imgProxyBase);
        }
        if ($fallbackPic !== '') {
            $imagesAll[] = $fallbackPic;
        }
    }
}
$imagesLb = array_slice($imagesAll, 0, 24);
$mosaic = array_slice($imagesLb, 0, 3);
$totalPhotos = count($imagesAll);

$common = is_array($hotel['common'] ?? null) ? $hotel['common'] : [];
$infra = is_array($hotel['infrastructure'] ?? null) ? $hotel['infrastructure'] : [];
$meals = is_array($hotel['meals'] ?? null) ? $hotel['meals'] : [];
$services = is_array($hotel['services'] ?? null) ? $hotel['services'] : [];
$country = is_array($hotel['country'] ?? null) ? $hotel['country'] : [];
$region = is_array($hotel['region'] ?? null) ? $hotel['region'] : [];

$hotelName = (string) ($hotel['name'] ?? 'Отель');
$hotelCountryId = (int) ($country['id'] ?? 0);
if ($hotelCountryId <= 0) {
    $hotelCountryId = $countryIdHint > 0 ? $countryIdHint : 4;
}
$category = (int) ($hotel['category'] ?? 0);
$rating = isset($hotel['rating']) ? (float) $hotel['rating'] : 0.0;
$placeLabel = trim(implode(', ', array_filter([
    (string) ($region['name'] ?? ''),
    (string) ($country['name'] ?? ''),
])));
$description = thh_text((string) ($common['description'] ?? ''));
$lead = thh_clip($description, 180);

$aboutSections = [];
if ($description !== '') {
    $aboutSections['Описание'] = thh_clip($description, 700);
}
$beach = thh_text((string) ($infra['beach'] ?? ''));
if ($beach !== '') {
    $aboutSections['Пляж'] = thh_clip($beach, 320);
}
$territory = thh_text((string) ($infra['territory'] ?? ''));
if ($territory !== '') {
    $aboutSections['Территория'] = thh_clip($territory, 320);
}
$mealBits = trim(implode("\n", array_filter([
    thh_text((string) ($meals['list'] ?? '')),
    thh_clip(thh_text((string) ($meals['description'] ?? '')), 280),
])));
if ($mealBits !== '') {
    $aboutSections['Питание'] = $mealBits;
}
$svcFree = thh_text((string) ($services['free'] ?? ''));
if ($svcFree !== '') {
    $aboutSections['Услуги'] = thh_clip($svcFree, 280);
}

/* Tourvisor live: date span ≤14 days. Cover ~1 year ahead. */
$windows = [];
$winStart = strtotime('+2 days');
$winEndLimit = strtotime('+365 days');
while ($winStart < $winEndLimit) {
    $from = date('Y-m-d', $winStart);
    $toTs = min(strtotime('+13 days', $winStart), $winEndLimit);
    $to = date('Y-m-d', $toTs);
    $windows[] = [$from, $to];
    $winStart = strtotime('+14 days', $winStart);
}
/* Night bands: span ≤10 (API). Cover 1–28 nights. */
$nightBands = [
    [1, 7],
    [5, 14],
    [8, 17],
    [14, 21],
    [18, 28],
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title><?php echo htmlspecialchars($hotelName, ENT_QUOTES, 'UTF-8'); ?> — туры | Travel Hub</title>
    <meta name="description" content="<?php echo htmlspecialchars(thh_clip($hotelName . '. Туры с ценами.', 140), ENT_QUOTES, 'UTF-8'); ?>">
    <?php if (!empty($mosaic[0])): ?>
        <link rel="preload" as="image" href="<?php echo htmlspecialchars($mosaic[0], ENT_QUOTES, 'UTF-8'); ?>" fetchpriority="high">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/frontend/css/pages/tv-hotel-hub.css?v=13">
    <?php include __DIR__ . '/../../../backend/components/design_system_head.php'; ?>
</head>
<body class="thh antialiased">
<?php
$current_page = 'popular-hotels';
include __DIR__ . '/../../../backend/components/header.php';
?>

<main class="thh-wrap">
    <a class="thh-back" href="/frontend/window/popular-hotels.php"><i class="fas fa-arrow-left"></i> К отелям</a>

    <?php if ($error): ?>
        <div class="thh-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php else: ?>
        <?php $mosaicClass = 'thh-mosaic' . (count($mosaic) <= 1 ? ' thh-mosaic--one' : ''); ?>
        <section class="<?php echo $mosaicClass; ?>" aria-label="Фото отеля">
            <?php if ($mosaic === []): ?>
                <div class="thh-mosaic__cell thh-mosaic__main thh-mosaic__empty">Нет фото</div>
            <?php else: ?>
                <?php foreach ($mosaic as $i => $src): ?>
                    <button type="button"
                            class="thh-mosaic__cell<?php echo $i === 0 ? ' thh-mosaic__main' : ''; ?>"
                            data-lb-index="<?php echo (int) $i; ?>">
                        <img src="<?php echo htmlspecialchars($src, ENT_QUOTES, 'UTF-8'); ?>"
                             alt="<?php echo htmlspecialchars($hotelName, ENT_QUOTES, 'UTF-8'); ?>"
                             loading="<?php echo $i === 0 ? 'eager' : 'lazy'; ?>"
                             <?php echo $i === 0 ? 'fetchpriority="high"' : ''; ?>>
                        <?php if ($i === count($mosaic) - 1 && $totalPhotos > count($mosaic)): ?>
                            <span class="thh-mosaic__more" data-open-gallery="1">Фото · <?php echo (int) min(24, $totalPhotos); ?></span>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="thh-title-block">
            <div>
                <div class="thh-kicker">
                    <?php if ($category > 0): ?><span class="thh-pill"><?php echo (int) $category; ?>★</span><?php endif; ?>
                    <?php if ($rating > 0): ?>
                        <span class="thh-pill thh-pill--star"><i class="fas fa-star"></i> <?php echo htmlspecialchars(number_format($rating, 1, '.', ''), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    <?php if ($placeLabel !== ''): ?>
                        <span class="thh-pill"><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($placeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>
                <h1 class="thh-title"><?php echo htmlspecialchars($hotelName, ENT_QUOTES, 'UTF-8'); ?></h1>
                <?php if ($lead !== ''): ?>
                    <p class="thh-lead"><?php echo htmlspecialchars($lead, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <p class="thh-dep-line">Вылет из <?php echo htmlspecialchars($departureName, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </section>

        <section id="thh-tours" class="thh-offers" aria-label="Туры">
            <div class="thh-offers__head">
                <div>
                    <h2>Туры в этот отель</h2>
                    <p class="thh-offers__sub">Все доступные предложения · вылет <?php echo htmlspecialchars($departureName, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <select id="thh-sort" class="thh-sort" aria-label="Сортировка">
                    <option value="price-asc">Дешевле</option>
                    <option value="price-desc">Дороже</option>
                    <option value="date">По дате</option>
                    <option value="nights">По ночам</option>
                </select>
            </div>

            <div id="thh-loading" class="thh-loading"><i class="fas fa-spinner fa-spin"></i> Загружаем туры…</div>
            <div id="thh-error" class="thh-error hidden"></div>
            <div id="thh-empty" class="thh-empty hidden">Сейчас нет туров в этот отель. Загляните позже или выберите другой отель.</div>
            <div id="thh-list" class="thh-tour-grid th-tour-grid" aria-live="polite"></div>
            <div id="thh-more" class="thh-more hidden">
                <button type="button" id="thh-more-btn">Показать ещё</button>
            </div>
        </section>

        <?php if ($aboutSections !== []): ?>
        <section class="thh-about" aria-label="Об отеле">
            <h2>Об отеле</h2>
            <?php $first = true; foreach ($aboutSections as $title => $body): ?>
                <details<?php echo $first ? '' : ''; ?>>
                    <summary><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></summary>
                    <div class="thh-about__body"><?php echo htmlspecialchars($body, ENT_QUOTES, 'UTF-8'); ?></div>
                </details>
            <?php $first = false; endforeach; ?>
        </section>
        <?php endif; ?>

        <div class="thh-sticky">
            <div class="thh-sticky__price">
                Туры
                <strong id="thh-sticky-from">—</strong>
            </div>
            <a href="#thh-tours">К предложениям</a>
        </div>
    <?php endif; ?>
</main>

<?php if (!$error && $imagesLb !== []): ?>
<div class="thh-lb" id="thh-lb" role="dialog" aria-modal="true" aria-label="Галерея">
    <div class="thh-lb__inner">
        <button type="button" class="thh-lb__close" id="thh-lb-close" aria-label="Закрыть">×</button>
        <button type="button" class="thh-lb__nav thh-lb__nav--prev" id="thh-lb-prev" aria-label="Назад">‹</button>
        <img id="thh-lb-img" src="" alt="">
        <button type="button" class="thh-lb__nav thh-lb__nav--next" id="thh-lb-next" aria-label="Далее">›</button>
        <div class="thh-lb__count" id="thh-lb-count"></div>
    </div>
</div>
<?php endif; ?>

<script src="/frontend/js/tour-link-utils.js?v=1"></script>
<?php
$_th_card = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'th-tour-card.js';
$_th_card_v = is_file($_th_card) ? (string) filemtime($_th_card) : '1';
$_th_fpick = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'tourvisor-flight-pick.js';
$_th_fpick_v = is_file($_th_fpick) ? (string) filemtime($_th_fpick) : '1';
?>
<script src="/frontend/js/th-tour-card.js?v=<?php echo htmlspecialchars($_th_card_v, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="/frontend/js/tourvisor-flight-pick.js?v=<?php echo htmlspecialchars($_th_fpick_v, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
(function () {
    if (<?php echo $error ? 'true' : 'false'; ?>) return;

    var CFG = {
        apiBase: <?php echo json_encode($tvApiBase, JSON_UNESCAPED_UNICODE); ?>,
        imageProxy: <?php echo json_encode($imgProxyBase, JSON_UNESCAPED_UNICODE); ?>,
        hotelId: <?php echo (int) $hotelId; ?>,
        hotelName: <?php echo json_encode($hotelName, JSON_UNESCAPED_UNICODE); ?>,
        countryId: <?php echo (int) $hotelCountryId; ?>,
        countryName: <?php echo json_encode((string) ($country['name'] ?? ''), JSON_UNESCAPED_UNICODE); ?>,
        regionName: <?php echo json_encode((string) ($region['name'] ?? ''), JSON_UNESCAPED_UNICODE); ?>,
        category: <?php echo (int) $category; ?>,
        rating: <?php echo json_encode(number_format($rating, 1, '.', ''), JSON_UNESCAPED_UNICODE); ?>,
        departureId: <?php echo (int) $departureId; ?>,
        departureName: <?php echo json_encode($departureName, JSON_UNESCAPED_UNICODE); ?>,
        images: <?php echo json_encode(array_values($imagesLb), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        windows: <?php echo json_encode($windows); ?>,
        nightBands: <?php echo json_encode($nightBands); ?>,
        pageSize: 20,
        adults: 2
    };

    var state = {
        pool: [],
        tours: [],
        shown: 0,
        loading: false,
        minPrice: 0,
        adults: CFG.adults,
        flightsGen: 0
    };

    var el = {
        list: document.getElementById('thh-list'),
        loading: document.getElementById('thh-loading'),
        empty: document.getElementById('thh-empty'),
        error: document.getElementById('thh-error'),
        sort: document.getElementById('thh-sort'),
        more: document.getElementById('thh-more'),
        moreBtn: document.getElementById('thh-more-btn'),
        stickyFrom: document.getElementById('thh-sticky-from')
    };

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    function fmtPrice(n) {
        var num = parseInt(String(n), 10) || 0;
        if (!num) return '—';
        return num.toLocaleString('ru-RU') + ' ₽';
    }

    function ymdAdd(startYmd, nights) {
        var p = String(startYmd).split('-');
        if (p.length !== 3) return '';
        var d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
        d.setDate(d.getDate() + (parseInt(nights, 10) || 0));
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + m + '-' + day;
    }

    function tourStartYmd(t) {
        var d = t && (t.date || t.flyDate || t.checkIn || '');
        if (!d) return '';
        d = String(d);
        if (/^\d{4}-\d{2}-\d{2}/.test(d)) return d.slice(0, 10);
        var m = d.match(/(\d{2})\.(\d{2})\.(\d{4})/);
        if (m) return m[3] + '-' + m[2] + '-' + m[1];
        return '';
    }

    function tvUrl(type, params) {
        var u;
        try { u = new URL(CFG.apiBase); } catch (e) { u = new URL(CFG.apiBase, window.location.origin); }
        u.searchParams.set('type', type);
        Object.keys(params || {}).forEach(function (k) {
            if (params[k] != null && params[k] !== '') u.searchParams.set(k, String(params[k]));
        });
        return u.toString();
    }

    async function tvFetch(type, params) {
        var forceLive = !!(params && params._forceLive);
        var cacheOnly = !!(params && params._cacheOnly);
        var api = Object.assign({}, params || {});
        delete api._forceLive;
        delete api._cacheOnly;
        if (type === 'search-cached') {
            api.cacheScope = 'country_page';
            api.slim = '1';
            if (forceLive) api.live = '1';
            if (cacheOnly) api.cacheOnly = '1';
            api._t = String(Date.now());
        }
        var r = await fetch(tvUrl(type, api), { method: 'GET', cache: 'no-store' });
        var text = await r.text();
        try { return text ? JSON.parse(text) : { success: false }; } catch (e) { return { success: false }; }
    }

    function flattenTours(rawHotels, adultsUsed) {
        var out = [];
        var wantId = CFG.hotelId;
        var adults = adultsUsed != null ? adultsUsed : state.adults;
        (rawHotels || []).forEach(function (h) {
            if (!h) return;
            var hid = parseInt(h.id, 10) || 0;
            if (wantId && hid && hid !== wantId) return;
            var tours = Array.isArray(h.tours) ? h.tours : [];
            tours.forEach(function (t) {
                if (!t) return;
                var price = parseInt(t.totalPrice || t.price || t.priceRub || t.cost, 10) || 0;
                if (price <= 0) return;
                var nights = parseInt(t.nights, 10) || 0;
                var start = tourStartYmd(t);
                var meal = (t.meal && (t.meal.russianName || t.meal.name)) || '';
                var key = String(t.id || '') + '|' + start + '|' + nights + '|' + adults + '|' + price;
                out.push({
                    hotel: h,
                    tour: t,
                    price: price,
                    nights: nights,
                    start: start,
                    meal: meal,
                    adults: adults,
                    key: key
                });
            });
        });
        return out;
    }

    function dedupe(list) {
        var seen = {};
        var out = [];
        list.forEach(function (it) {
            if (seen[it.key]) return;
            seen[it.key] = true;
            out.push(it);
        });
        return out;
    }

    function sortTours(list) {
        var mode = (el.sort && el.sort.value) || 'price-asc';
        var arr = list.slice();
        arr.sort(function (a, b) {
            if (mode === 'price-desc') return b.price - a.price;
            if (mode === 'date') return String(a.start || '').localeCompare(String(b.start || ''));
            if (mode === 'nights') return (a.nights || 0) - (b.nights || 0) || a.price - b.price;
            return a.price - b.price;
        });
        return arr;
    }

    function buildJobs(adults) {
        var windows = CFG.windows || [];
        var bands = CFG.nightBands || [[1, 7], [5, 14], [8, 17], [14, 21], [18, 28]];
        var jobs = [];
        windows.forEach(function (w) {
            bands.forEach(function (b) {
                jobs.push({
                    dateFrom: w[0],
                    dateTo: w[1],
                    nightsFrom: b[0],
                    nightsTo: b[1],
                    adults: adults
                });
            });
        });
        return jobs;
    }

    function tourDetailUrl(item) {
        var h = item.hotel || {};
        var t = item.tour || {};
        var link = h.hotelDescriptionLink || h.hoteldescriptionlink || h.link || '';
        if (window.TourLinkUtils && TourLinkUtils.sanitizeTourLink) {
            link = TourLinkUtils.sanitizeTourLink(link) || '';
        }
        var meal = (t.meal && (t.meal.russianName || t.meal.name)) || '';
        var start = tourStartYmd(t);
        var nights = parseInt(t.nights, 10) || 0;
        var op = (t.operator && (t.operator.russianName || t.operator.name)) || t.operatorName || '';
        var params = new URLSearchParams({
            tour_link: link,
            country: CFG.countryName || (h.country && h.country.name) || '',
            hotel_name: CFG.hotelName || h.name || '',
            price: String(item.price || ''),
            nights: String(nights || ''),
            meal: meal,
            room_category: String(t.roomType || 'Стандарт'),
            region: CFG.regionName || (h.region && h.region.name) || '',
            departure_city: CFG.departureName,
            image: (CFG.images && CFG.images[0]) || h.picturelink || '',
            rating: CFG.rating || String(h.rating || ''),
            category: String(CFG.category || h.category || ''),
            date_from: start,
            date_to: start && nights ? ymdAdd(start, nights) : '',
            tour_id: String(t.id || ''),
            adults: String(item.adults || state.adults || CFG.adults),
            hotel_id: String(CFG.hotelId),
            departure_id: String(CFG.departureId),
            return_url: window.location.pathname + window.location.search
        });
        if (op) params.set('tour_operator', op);
        return '/frontend/window/tour-detail.php?' + params.toString();
    }

    function mapImg(src) {
        if (!src) return '';
        var u = String(src).trim();
        if (u.indexOf('//') === 0) u = 'https:' + u;
        if (CFG.imageProxy && /static\.tourvisor\.ru/i.test(u)) {
            return CFG.imageProxy + '?url=' + encodeURIComponent(u);
        }
        return u;
    }

    function fmtTourDate(ymd) {
        if (!ymd || !/^\d{4}-\d{2}-\d{2}$/.test(ymd)) return ymd || '';
        var p = ymd.split('-');
        var d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
        if (isNaN(d.getTime())) return ymd;
        try {
            return d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' });
        } catch (e) {
            return ymd;
        }
    }

    function nightsLabel(n) {
        var num = parseInt(n, 10) || 0;
        if (!num) return '';
        var mod = num % 100;
        var n1 = num % 10;
        var word = (mod >= 11 && mod <= 14) ? 'ночей' : (n1 === 1 ? 'ночь' : (n1 >= 2 && n1 <= 4 ? 'ночи' : 'ночей'));
        return num + ' ' + word;
    }

    /** Список туров в отель — компактные офферы, не карточки отелей. */
    function cardHtml(item) {
        var t = item.tour || {};
        var start = tourStartYmd(t) || item.start || '';
        var nights = parseInt(t.nights, 10) || item.nights || 0;
        var detailUrl = tourDetailUrl(item);
        var meal = (t.meal && (t.meal.russianName || t.meal.name)) || item.meal || '';
        var room = String(t.roomType || t.room || t.roomName || '').trim();
        var op = '';
        if (t.operator && typeof t.operator === 'object') {
            op = t.operator.russianName || t.operator.name || '';
        } else if (typeof t.operator === 'string') {
            op = t.operator;
        }
        if (!op) op = t.operatorName || '';
        var tourId = t.id != null ? String(t.id) : '';
        var adults = item.adults || state.adults || CFG.adults || 2;
        var dateEnd = start && nights ? ymdAdd(start, nights) : '';
        var dateLine = fmtTourDate(start);
        if (dateEnd) dateLine += (dateLine ? ' — ' : '') + fmtTourDate(dateEnd);

        return (
            '<a class="thh-tour" href="' + esc(detailUrl) + '"' +
            (tourId ? ' data-th-tour-id="' + esc(tourId) + '"' : '') + '>' +
            '<div class="thh-tour__main">' +
            '<span class="thh-tour__date">' + esc(dateLine || start || 'Дата уточняется') + '</span>' +
            '<div class="thh-tour__chips">' +
            (nights ? '<span class="thh-tour__chip">' + esc(nightsLabel(nights)) + '</span>' : '') +
            (meal ? '<span class="thh-tour__chip">' + esc(meal) + '</span>' : '') +
            (room ? '<span class="thh-tour__chip thh-tour__chip--room">' + esc(room) + '</span>' : '') +
            (op ? '<span class="thh-tour__chip thh-tour__chip--op">' + esc(op) + '</span>' : '') +
            '</div>' +
            '<span class="thh-tour__flight" data-thh-flight aria-hidden="true"></span>' +
            '</div>' +
            '<div class="thh-tour__price-side">' +
            '<div class="thh-tour__price"><span>за ' + esc(String(adults)) + ' взр.</span><strong>' + esc(fmtPrice(item.price)) + '</strong></div>' +
            '<span class="thh-tour__go">К туру</span></div></a>'
        );
    }

    function patchThhFlights() {
        if (!el.list || typeof window.thFlightsCacheGet !== 'function') return;
        var cards = el.list.querySelectorAll('.thh-tour[data-th-tour-id]');
        cards.forEach(function (card) {
            var tid = card.getAttribute('data-th-tour-id');
            var slot = card.querySelector('[data-thh-flight]');
            if (!tid || !slot) return;
            var meta = window.thFlightsCacheGet(tid, CFG.departureName);
            if (!meta) return;
            var bits = [];
            if (meta.direct) bits.push('Прямой');
            else if (meta.companies || meta.airline) bits.push('С пересадкой');
            var line = meta.forwardLine || meta.subline || meta.summary || '';
            if (meta.airline && meta.time) line = meta.airline + ' · ' + meta.time;
            else if (meta.airline) line = meta.airline;
            if (line) bits.push(String(line).slice(0, 48));
            if (!bits.length) return;
            slot.innerHTML = '<i class="fas fa-plane" aria-hidden="true"></i> ' + esc(bits.join(' · '));
            slot.removeAttribute('aria-hidden');
        });
    }

    function hydrateFlights(items) {
        if (!window.thLoadTourFlightsForHotels || !items || !items.length) return;
        state.flightsGen += 1;
        var gen = state.flightsGen;
        window.__thFlightsLoadGen = gen;
        var hotels = items.map(function (it) {
            return Object.assign({}, it.hotel || {}, { _tour: it.tour, tours: [it.tour] });
        });
        thLoadTourFlightsForHotels(hotels, {
            apiBase: CFG.apiBase,
            departureCity: CFG.departureName,
            departureId: CFG.departureId,
            maxTours: items.length,
            maxConcurrent: 4,
            waveSize: 12,
            patchEvery: 2,
            loadGen: gen,
            patchContainer: null,
            onDone: patchThhFlights,
            getTourId: function (h) {
                var t = (h && h._tour) || (h && h.tours && h.tours[0]) || {};
                return t.id != null ? String(t.id) : '';
            }
        }).then(function () {
            patchThhFlights();
        }).catch(function () {});
        /* промежуточные патчи: thLoad не вызывает наш onDone до конца — дублируем таймером редко */
        var ticks = 0;
        var timer = setInterval(function () {
            ticks += 1;
            if (gen !== state.flightsGen || ticks > 40) {
                clearInterval(timer);
                return;
            }
            patchThhFlights();
        }, 700);
    }

    function render(reset) {
        if (reset) state.shown = 0;
        state.tours = sortTours(state.pool);
        var sorted = state.tours;
        var min = 0;
        sorted.forEach(function (it) {
            if (it.price > 0 && (min === 0 || it.price < min)) min = it.price;
        });
        state.minPrice = min;
        if (el.stickyFrom) el.stickyFrom.textContent = min ? ('от ' + fmtPrice(min)) : '—';

        var headSub = document.querySelector('.thh-offers__sub');
        if (headSub) {
            headSub.textContent = sorted.length
                ? (sorted.length + ' предложений · вылет ' + CFG.departureName)
                : ('Все доступные предложения · вылет ' + CFG.departureName);
        }

        if (!sorted.length) {
            el.list.innerHTML = '';
            el.empty.classList.remove('hidden');
            el.more.classList.add('hidden');
            return;
        }
        el.empty.classList.add('hidden');
        var next = Math.min(sorted.length, state.shown + CFG.pageSize);
        var slice = sorted.slice(state.shown, next);
        var html = slice.map(cardHtml).join('');
        if (state.shown === 0) el.list.innerHTML = html;
        else el.list.insertAdjacentHTML('beforeend', html);
        state.shown = next;
        el.more.classList.toggle('hidden', state.shown >= sorted.length);
        if (reset || slice.length) {
            hydrateFlights(sorted.slice(0, state.shown));
        }
    }

    async function loadMatrix() {
        state.loading = true;
        el.loading.classList.remove('hidden');
        el.error.classList.add('hidden');
        el.empty.classList.add('hidden');
        el.list.innerHTML = '';
        state.pool = [];
        state.shown = 0;
        el.more.classList.add('hidden');

        state.adults = CFG.adults;
        var jobs = buildJobs(CFG.adults);

        async function fetchJob(job, fetchOpts) {
            return tvFetch('search-cached', Object.assign({
                departureId: CFG.departureId,
                countryId: CFG.countryId,
                dateFrom: job.dateFrom,
                dateTo: job.dateTo,
                nightsFrom: job.nightsFrom,
                nightsTo: job.nightsTo,
                adults: job.adults,
                currency: 'RUB',
                hotelIds: String(CFG.hotelId)
            }, fetchOpts || {}));
        }

        try {
            var collected = [];
            var needLive = [];
            var BATCH = 12;
            for (var i = 0; i < jobs.length; i += BATCH) {
                var chunk = jobs.slice(i, i + BATCH);
                var results = await Promise.all(chunk.map(function (j) {
                    return fetchJob(j, { _cacheOnly: true });
                }));
                results.forEach(function (j, idx) {
                    var got = 0;
                    if (j && j.success && Array.isArray(j.data)) {
                        var flat = flattenTours(j.data, chunk[idx].adults);
                        got = flat.length;
                        collected = collected.concat(flat);
                    }
                    if (got === 0) needLive.push(chunk[idx]);
                });
                collected = dedupe(collected);
                state.pool = collected;
                if (collected.length) {
                    el.loading.classList.add('hidden');
                    render(true);
                }
            }

            /* Добираем пустые окна live — без лимита «хватит 20». */
            for (var li = 0; li < needLive.length; li++) {
                var liveJ = await fetchJob(needLive[li], { _forceLive: true });
                if (liveJ && liveJ.success && Array.isArray(liveJ.data)) {
                    collected = dedupe(collected.concat(flattenTours(liveJ.data, needLive[li].adults)));
                    state.pool = collected;
                    render(true);
                }
                await new Promise(function (r) { setTimeout(r, 350); });
            }

            state.pool = dedupe(collected);
            render(true);
            if (!state.tours.length) el.empty.classList.remove('hidden');
        } catch (e) {
            el.error.textContent = e.message || 'Не удалось загрузить туры';
            el.error.classList.remove('hidden');
        } finally {
            state.loading = false;
            el.loading.classList.add('hidden');
        }
    }

    el.sort.addEventListener('change', function () { render(true); });
    el.moreBtn.addEventListener('click', function () { render(false); });

    /* Lightbox */
    var lb = document.getElementById('thh-lb');
    var lbImg = document.getElementById('thh-lb-img');
    var lbCount = document.getElementById('thh-lb-count');
    var lbIdx = 0;
    function openLb(i) {
        if (!lb || !CFG.images.length) return;
        lbIdx = Math.max(0, Math.min(CFG.images.length - 1, i || 0));
        lbImg.src = CFG.images[lbIdx];
        lbCount.textContent = (lbIdx + 1) + ' / ' + CFG.images.length;
        lb.classList.add('is-open');
    }
    function closeLb() { if (lb) lb.classList.remove('is-open'); }
    function stepLb(d) {
        if (!CFG.images.length) return;
        lbIdx = (lbIdx + d + CFG.images.length) % CFG.images.length;
        lbImg.src = CFG.images[lbIdx];
        lbCount.textContent = (lbIdx + 1) + ' / ' + CFG.images.length;
    }
    document.querySelectorAll('[data-lb-index]').forEach(function (node) {
        node.addEventListener('click', function (e) {
            e.preventDefault();
            if (e.target && e.target.getAttribute && e.target.getAttribute('data-open-gallery')) openLb(0);
            else openLb(parseInt(node.getAttribute('data-lb-index'), 10) || 0);
        });
    });
    var lbClose = document.getElementById('thh-lb-close');
    var lbPrev = document.getElementById('thh-lb-prev');
    var lbNext = document.getElementById('thh-lb-next');
    if (lbClose) lbClose.addEventListener('click', closeLb);
    if (lbPrev) lbPrev.addEventListener('click', function () { stepLb(-1); });
    if (lbNext) lbNext.addEventListener('click', function () { stepLb(1); });
    if (lb) lb.addEventListener('click', function (e) { if (e.target === lb) closeLb(); });

    loadMatrix();
})();
</script>

<?php include __DIR__ . '/../../../backend/components/footer.php'; ?>
</body>
</html>
