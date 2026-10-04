<?php
/**
 * VIP Hotel Min Price API
 * Возвращает минимальную цену тура в VIP-отель из Tourvisor (параметры поиска ближе к обычным турам: 3–21 ночь, без жёсткой категории).
 * Кэш: файловый, TTL = 24 часа.
 *
 * GET /backend/api/vip-hotel-minprice.php
 *   ?slug=lara-barut-collection        — слаг отеля (обязателен)
 *   &departure_id=1                    — ID города вылета (по умолч. 1 = Москва)
 *   &adults=2                          — кол-во взрослых (по умолч. 2)
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

/* ─── Параметры ─── */
$slug        = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim((string)($_GET['slug'] ?? ''))));
$departureId = preg_replace('/[^0-9]/', '', (string)($_GET['departure_id'] ?? '1')) ?: '1';
$adults      = max(1, min(9, (int)($_GET['adults'] ?? 2)));

if (!$slug) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'slug is required']);
    exit;
}

/* ─── Кэш ─── */
$cacheDir  = dirname(__DIR__) . '/cache/vip_prices';
$cacheKey  = $slug . '_dep' . $departureId . '_a' . $adults;
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';
$cacheTTL  = 86400; // 24 часа

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
    header('X-VIP-Cache: HIT');
    echo file_get_contents($cacheFile);
    exit;
}

header('X-VIP-Cache: MISS');

/* ─── БД: получаем имя и город отеля ─── */
try {
    require_once dirname(__DIR__) . '/config/config.php';
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'config error', 'minPrice' => null]);
    exit;
}

if (!isset($pdo) || !$pdo) {
    echo json_encode(['success' => false, 'error' => 'no db', 'minPrice' => null]);
    exit;
}

require_once dirname(__DIR__) . '/components/vip_hotels_schema.php';
try {
    vip_hotels_ensure_table($pdo);
} catch (Throwable $e) {
    error_log('[vip-hotel-minprice] ensure_table: ' . $e->getMessage());
}

try {
    $stmt = $pdo->prepare('SELECT name, city FROM vip_hotels WHERE slug = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$slug]);
    $hotel = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'db error: ' . $e->getMessage(), 'minPrice' => null]);
    exit;
}

if (!$hotel) {
    echo json_encode(['success' => false, 'error' => 'hotel not found', 'minPrice' => null]);
    exit;
}

/* ─── Tourvisor: регион Турции ─── */
$regionMap = ['Antalya' => '20', 'Belek' => '21', 'Kemer' => '22'];
$canonicalCity = vip_hotels_canonical_resort_key((string) $hotel['city']);
$cityKey = $canonicalCity ?? (isset($regionMap[$hotel['city']]) ? (string) $hotel['city'] : null);
$regionId = ($cityKey !== null && isset($regionMap[$cityKey])) ? $regionMap[$cityKey] : '';

require_once dirname(__DIR__) . '/components/tourvisor_proxy_url.php';
$apiBase = get_tourvisor_proxy_base_url('vip-prices');

$today    = new DateTime();
$dateFrom = (clone $today)->modify('+7 days')->format('Y-m-d');
$dateTo   = (clone $today)->modify('+60 days')->format('Y-m-d');

$nameLower = mb_strtolower(trim((string) $hotel['name']), 'UTF-8');

$tvHotelMatches = static function (string $tvName) use ($nameLower, $slug): bool {
    $tv = mb_strtolower($tvName, 'UTF-8');
    if ($nameLower !== '' && str_contains($tv, $nameLower)) {
        return true;
    }
    $words = preg_split('/[\s\-_]+/u', $slug, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $need = 0;
    $hit = 0;
    foreach ($words as $w) {
        $w = mb_strtolower((string) $w, 'UTF-8');
        if (mb_strlen($w) < 3) {
            continue;
        }
        $need++;
        if (str_contains($tv, $w)) {
            $hit++;
        }
    }
    if ($need === 1) {
        return $hit >= 1;
    }
    if ($need >= 2) {
        return $hit >= 2;
    }

    return false;
};

$scanMinPrice = static function (?array $data) use ($tvHotelMatches): array {
    $minPrice = null;
    $matchedHotelName = null;
    if (empty($data['data']) || !is_array($data['data'])) {
        return [$minPrice, $matchedHotelName];
    }
    foreach ($data['data'] as $h) {
        $tvName = (string) ($h['name'] ?? '');
        if (!$tvHotelMatches($tvName)) {
            continue;
        }
        foreach ((array) ($h['tours'] ?? []) as $t) {
            $p = (float) ($t['totalPrice'] ?? $t['price'] ?? $t['priceRub'] ?? $t['cost'] ?? 0);
            if ($p > 0 && ($minPrice === null || $p < $minPrice)) {
                $minPrice = (int) round($p);
                $matchedHotelName = $h['name'] ?? null;
            }
        }
    }

    return [$minPrice, $matchedHotelName];
};

$fetchSearch = static function (string $apiBase, array $qp): ?array {
    $apiUrl = $apiBase . '&' . http_build_query($qp);
    $ctx  = stream_context_create(['http' => ['timeout' => 14, 'ignore_errors' => true]]);
    $body = @file_get_contents($apiUrl, false, $ctx);
    if (!$body || !trim($body)) {
        return null;
    }

    $decoded = @json_decode($body, true);

    return is_array($decoded) ? $decoded : null;
};

$baseQp = [
    'type'        => 'search-cached',
    'departureId' => $departureId,
    'countryId'   => '4',
    'dateFrom'    => $dateFrom,
    'dateTo'      => $dateTo,
    'adults'      => (string) $adults,
    'currency'    => 'RUB',
];

/* Порядок: кэш → live; широкий диапазон ночей как в промо-синхроне; без region — как общий поиск по Турции */
$profiles = [
    ['live' => '0', 'nightsFrom' => '3', 'nightsTo' => '21', 'region' => true,  'hotelCategory' => null],
    ['live' => '1', 'nightsFrom' => '3', 'nightsTo' => '21', 'region' => true,  'hotelCategory' => null],
    ['live' => '0', 'nightsFrom' => '5', 'nightsTo' => '14', 'region' => true,  'hotelCategory' => null],
    ['live' => '0', 'nightsFrom' => '3', 'nightsTo' => '21', 'region' => false, 'hotelCategory' => null],
    ['live' => '1', 'nightsFrom' => '3', 'nightsTo' => '21', 'region' => false, 'hotelCategory' => null],
    ['live' => '0', 'nightsFrom' => '3', 'nightsTo' => '21', 'region' => true,  'hotelCategory' => '5'],
    ['live' => '1', 'nightsFrom' => '3', 'nightsTo' => '21', 'region' => true,  'hotelCategory' => '5'],
];

$minPrice = null;
$matchedHotelName = null;

foreach ($profiles as $prof) {
    $qp = $baseQp + [
        'nightsFrom' => $prof['nightsFrom'],
        'nightsTo'   => $prof['nightsTo'],
        'live'       => $prof['live'],
    ];
    if ($prof['hotelCategory'] !== null) {
        $qp['hotelCategory'] = $prof['hotelCategory'];
    }
    if (!empty($prof['region']) && $regionId !== '') {
        $qp['regionIds'] = $regionId;
    }

    $data = $fetchSearch($apiBase, $qp);
    [$p, $name] = $scanMinPrice($data);
    if ($p !== null) {
        $minPrice = $p;
        $matchedHotelName = $name;
        break;
    }
}

$result = [
    'success'          => true,
    'slug'             => $slug,
    'hotelName'        => $hotel['name'],
    'minPrice'         => $minPrice,
    'matchedHotelName' => $matchedHotelName,
    'currency'         => 'RUB',
    'adults'           => $adults,
    'cachedAt'         => date('c'),
];

if ($minPrice !== null) {
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    @file_put_contents($cacheFile, json_encode($result, JSON_UNESCAPED_UNICODE));
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
