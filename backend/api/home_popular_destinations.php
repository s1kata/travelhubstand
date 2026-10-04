<?php
/**
 * 5 популярных направлений с сайта для города вылета (Tourvisor: страны, доступные из departureId).
 * Кеш файла: 21 суток; после истечения — живой запрос к прокси (live=1), затем снова кеш.
 * При ошибке живого запроса отдаётся предыдущий файл кеша (если был), иначе статический fallback.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');

const HOME_POPULAR_TTL_SEC = 21 * 86400;

$departureId = isset($_GET['departureId']) ? (int) $_GET['departureId'] : 0;
if ($departureId <= 0) {
    echo json_encode(['success' => false, 'error' => 'departureId required', 'items' => [], 'fromCache' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$fallback = require dirname(__DIR__) . '/config/home_popular_destinations_fallback.php';
$imageMap = require dirname(__DIR__) . '/config/home_popular_destinations_images.php';
$defaultImg = (string) ($imageMap['_default'] ?? '/frontend/window/img/hero/e978c0767c0fe7bc778596c86b2b54f3%201.png');
unset($imageMap['_default']);

$HOME_POPULAR_CARD_ORDER = [
    ['slug' => 'turkey', 'name' => 'Турция'],
    ['slug' => 'russia', 'name' => 'Сочи'],
    ['slug' => 'abkhazia', 'name' => 'Абхазия'],
    ['slug' => 'egypt', 'name' => 'Египет'],
    ['slug' => 'vietnam', 'name' => 'Вьетнам'],
];

$promo = require dirname(__DIR__) . '/config/country_promo_tourvisor_map.php';
$idToSlug = [];
foreach ($promo['slug_to_id'] as $slug => $cid) {
    if (!isset($idToSlug[$cid])) {
        $idToSlug[$cid] = $slug;
    }
}

function home_popular_public_base(): string
{
    $u = rtrim((string) (getenv('SITE_URL') ?: ($_ENV['SITE_URL'] ?? '')), '/');
    if ($u !== '') {
        return $u;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

    return $https . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function home_popular_proxy_get(string $query): ?array
{
    $base = home_popular_public_base() . '/backend/api/tourvisor-proxy.php';
    $sep = str_contains($base, '?') ? '&' : '?';
    $url = $base . $sep . $query;
    $ch = curl_init($url);
    if ($ch === false) {
        return null;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 55,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    if (!is_string($raw) || $raw === '') {
        return null;
    }
    $j = json_decode($raw, true);

    return is_array($j) ? $j : null;
}

function home_popular_cache_path(int $depId): string
{
    $root = dirname(__DIR__, 2);
    $dir = $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'home_popular_destinations';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $dir . DIRECTORY_SEPARATOR . 'dep_' . $depId . '.json';
}

function home_popular_read_cache(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }
    $j = json_decode($raw, true);
    if (!is_array($j) || !isset($j['saved_at'], $j['items']) || !is_array($j['items'])) {
        return null;
    }

    return $j;
}

function home_popular_merge_country_lists(array $primary, array $secondary): array
{
    $seen = [];
    $out = [];
    foreach ([$primary, $secondary] as $list) {
        foreach ($list as $c) {
            if (!is_array($c)) {
                continue;
            }
            $id = (int) ($c['id'] ?? 0);
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $c;
        }
    }

    return $out;
}

/**
 * Подбор slug по названию из Tourvisor (ids у разных вылетов/версий API могут не совпадать с slug_to_id).
 */
function home_popular_infer_slug_from_country_row(array $c): ?string
{
    $raw = trim((string) ($c['name'] ?? '') . ' ' . ($c['russianName'] ?? ''));
    $n = function_exists('mb_strtolower') ? mb_strtolower($raw, 'UTF-8') : strtolower($raw);
    if ($n === '') {
        return null;
    }
    if (str_contains($n, 'вьетнам')) {
        return 'vietnam';
    }
    if (str_contains($n, 'турци')) {
        return 'turkey';
    }
    if (str_contains($n, 'египет')) {
        return 'egypt';
    }
    if (str_contains($n, 'абхаз')) {
        return 'abkhazia';
    }
    if (str_contains($n, 'росси') || str_contains($n, 'сочи')) {
        return 'russia';
    }

    return null;
}

function home_popular_build_items(array $merged, array $idToSlug, array $imageMap, string $defaultImg, array $fallback): array
{
    $items = [];
    $haveSlugs = [];
    foreach ($merged as $c) {
        $id = (int) ($c['id'] ?? 0);
        $slug = home_popular_infer_slug_from_country_row($c) ?? ($idToSlug[$id] ?? null);
        if ($slug === null) {
            continue;
        }
        if (isset($haveSlugs[$slug])) {
            continue;
        }
        $haveSlugs[$slug] = true;
        $img = $imageMap[$slug] ?? $defaultImg;
        $items[] = [
            'countryId' => $id,
            'name' => (string) ($c['name'] ?? ''),
            'slug' => $slug,
            'href' => '/frontend/window/countries/' . $slug . '.php',
            'image' => $img,
        ];
        if (count($items) >= 5) {
            return $items;
        }
    }
    $have = array_flip(array_column($items, 'slug'));
    foreach ($fallback as $row) {
        if (count($items) >= 5) {
            break;
        }
        $slug = (string) ($row['slug'] ?? '');
        if ($slug === '' || isset($have[$slug])) {
            continue;
        }
        $items[] = $row;
        $have[$slug] = true;
    }

    return $items;
}

/** Фиксированный порядок и подписи карточек на главной (Турция, Сочи, Абхазия, Египет, Вьетнам). */
function home_popular_apply_card_order(array $builtItems, array $orderDefs, array $imageMap, string $defaultImg, array $fallback): array
{
    $bySlug = [];
    foreach ($builtItems as $it) {
        if (!is_array($it) || empty($it['slug'])) {
            continue;
        }
        $bySlug[(string) $it['slug']] = $it;
    }
    $fbBySlug = [];
    foreach ($fallback as $row) {
        if (!is_array($row) || empty($row['slug'])) {
            continue;
        }
        $fbBySlug[(string) $row['slug']] = $row;
    }
    $out = [];
    foreach ($orderDefs as $def) {
        $slug = (string) ($def['slug'] ?? '');
        if ($slug === '') {
            continue;
        }
        $src = $bySlug[$slug] ?? $fbBySlug[$slug] ?? null;
        if (!is_array($src)) {
            continue;
        }
        $imgOverride = isset($def['image']) ? trim((string) $def['image']) : '';
        $img = $imgOverride !== '' ? $imgOverride : (string) ($src['image'] ?? '');
        if ($img === '') {
            $img = (string) ($imageMap[$slug] ?? $defaultImg);
        }
        $out[] = [
            'countryId' => (int) ($src['countryId'] ?? 0),
            'name' => (string) ($def['name'] ?? $src['name'] ?? ''),
            'slug' => $slug,
            'href' => (string) ($src['href'] ?? ('/frontend/window/countries/' . $slug . '.php')),
            'image' => $img,
        ];
        if (count($out) >= 5) {
            break;
        }
    }

    return $out !== [] ? $out : $builtItems;
}

function home_popular_write_cache(string $path, int $departureId, array $items): void
{
    $payload = json_encode([
        'saved_at' => time(),
        'departure_id' => $departureId,
        'items' => $items,
    ], JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return;
    }
    @file_put_contents($path, $payload, LOCK_EX);
}

$cacheFile = home_popular_cache_path($departureId);
$cached = home_popular_read_cache($cacheFile);
$now = time();

if ($cached !== null && ($now - (int) $cached['saved_at']) < HOME_POPULAR_TTL_SEC) {
    header('X-Home-Popular: file-cache');
    echo json_encode([
        'success' => true,
        'departureId' => $departureId,
        'items' => $cached['items'],
        'fromCache' => true,
        'savedAt' => (int) $cached['saved_at'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$q1 = 'type=countries&departureId=' . $departureId . '&live=1';
$q2 = 'type=countries&departureId=' . $departureId . '&onlyCharter=1&live=1';
$j1 = home_popular_proxy_get($q1);
$j2 = home_popular_proxy_get($q2);
$list1 = (!empty($j1['success']) && is_array($j1['data'] ?? null)) ? $j1['data'] : [];
$list2 = (!empty($j2['success']) && is_array($j2['data'] ?? null)) ? $j2['data'] : [];
$merged = home_popular_merge_country_lists($list1, $list2);
$liveOk = (is_array($j1) && !empty($j1['success'])) || (is_array($j2) && !empty($j2['success']));

$items = home_popular_build_items($merged, $idToSlug, $imageMap, $defaultImg, $fallback);
$items = home_popular_apply_card_order($items, $HOME_POPULAR_CARD_ORDER, $imageMap, $defaultImg, $fallback);

if ($liveOk && $items !== []) {
    home_popular_write_cache($cacheFile, $departureId, $items);
    header('X-Home-Popular: live');
    echo json_encode([
        'success' => true,
        'departureId' => $departureId,
        'items' => $items,
        'fromCache' => false,
        'savedAt' => time(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($cached !== null && $items !== []) {
    header('X-Home-Popular: stale-file-cache');
    echo json_encode([
        'success' => true,
        'departureId' => $departureId,
        'items' => $cached['items'],
        'fromCache' => true,
        'savedAt' => (int) $cached['saved_at'],
        'warning' => 'live_unavailable',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

header('X-Home-Popular: fallback');
echo json_encode([
    'success' => true,
    'departureId' => $departureId,
    'items' => $fallback,
    'fromCache' => false,
    'savedAt' => null,
    'warning' => 'fallback',
], JSON_UNESCAPED_UNICODE);
