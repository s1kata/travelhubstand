<?php
/**
 * Прокси Tourvisor. СВЕЖЕСТЬ > СКОРОСТЬ (как в TravelHubNew).
 * — Справочники: из файлов tourvisor_cache (до 7 дней), при ошибке API — устаревший до 30 дней.
 * — Поиск: type=search — старт live, сразу searchId (клиент поллит status/results).
 *   type=search-cached без live=1 — exact → cover → live; live=1 — принудительный bypass кэша.
 *   Persist кэша/cover/Firestore — после ответа клиенту (fastcgi_finish_request).
 *   Poll: early ~55% / soft ~16с; results?persist=0 — early paint без записи кэша.
 * https://api.tourvisor.ru/search/docs
 *
 * Подключено во все «свои» поисковики через единый URL (backend/components/tourvisor_proxy_url.php):
 * — frontend/index.php (главная)
 * — backend/components/country_tour_search.php (все страницы стран: turkey, egypt, thailand, …)
 * Остальные страницы (hotel-detail, offices, country.php) используют виджет Tourvisor (init.js) → запросы идут напрямую в api.tourvisor.ru.
 * tour-calendar.php — свой календарь выгодных дат (promo_cache API), без виджета.
 */
declare(strict_types=1);

if (!defined('TH_TOURVISOR_PROXY_EMBED')) {
    define('TH_TOURVISOR_PROXY_EMBED', false);
}

try {
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../config/departure_defaults.php';
    require_once __DIR__ . '/../security_helper.php';
    require_once __DIR__ . '/../secure_logger.php';
} catch (Throwable $e) {
    error_log('[tourvisor-proxy] bootstrap: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (TH_TOURVISOR_PROXY_EMBED) {
        throw $e;
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
    }
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'data' => null,
        'error_detail' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!TH_TOURVISOR_PROXY_EMBED) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

function tv_firestore_helper_load(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;
    require_once __DIR__ . '/firestore-helper.php';
}

function tv_init_firestore_project_id(): void
{
    if (!empty($GLOBALS['tv_firestore_project_id'])) {
        tv_firestore_helper_load();
        return;
    }
    $pid = getenv('FIREBASE_PROJECT_ID') ?: ($_ENV['FIREBASE_PROJECT_ID'] ?? null);
    if ($pid !== null && $pid !== '') {
        $GLOBALS['tv_firestore_project_id'] = $pid;
        tv_firestore_helper_load();
        return;
    }
    if (is_file(__DIR__ . '/firestore-helper.php')) {
        tv_firestore_helper_load();
        if (function_exists('firestoreLoadCredentials')) {
            $creds = firestoreLoadCredentials();
            if (is_array($creds) && !empty($creds['project_id'])) {
                $GLOBALS['tv_firestore_project_id'] = $creds['project_id'];
                return;
            }
        }
    }
    error_log('[tourvisor] Firestore off: set FIREBASE_PROJECT_ID in .env or add config/firebase-service-account.json');
}

function tv_promo_country_cache_load(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;
    require_once __DIR__ . '/../promo_country_cache.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS' && !TH_TOURVISOR_PROXY_EMBED) {
    http_response_code(204);
    exit;
}

$GLOBALS['tv_base'] = rtrim(getenv('TOURVISOR_API_URL') ?: ($_ENV['TOURVISOR_API_URL'] ?? ''), '/') ?: 'https://api.tourvisor.ru/search/api/v1';
$GLOBALS['tv_cache_ttl'] = (int)(min(720, max(1, (float)(getenv('TOURVISOR_CACHE_TTL_HOURS') ?: ($_ENV['TOURVISOR_CACHE_TTL_HOURS'] ?? 168)))) * 3600);
/** Использовать статические fallback'и при ошибках API. false = только реальные данные API. */
$GLOBALS['tv_use_fallbacks'] = filter_var(getenv('TOURVISOR_USE_FALLBACKS') ?: ($_ENV['TOURVISOR_USE_FALLBACKS'] ?? '0'), FILTER_VALIDATE_BOOLEAN);
/** СВЕЖЕСТЬ > СКОРОСТЬ: TTL 14 дней (как в TravelHubNew). При промахе — живой поиск, пользователь ждёт. */
$GLOBALS['tv_search_cache_ttl'] = (int)(min(720, max(24, (float)(getenv('TOURVISOR_SEARCH_CACHE_TTL_HOURS') ?: ($_ENV['TOURVISOR_SEARCH_CACHE_TTL_HOURS'] ?? 336)))) * 3600);
/** Общий кэш всех туров: TTL 14 дней */
$GLOBALS['tv_all_tours_cache_ttl'] = (int)(min(720, max(24, (float)(getenv('TOURVISOR_ALL_TOURS_CACHE_TTL_HOURS') ?: ($_ENV['TOURVISOR_ALL_TOURS_CACHE_TTL_HOURS'] ?? 336)))) * 3600);
/** TTL кэша акционных туров (только промо): 24 часа по умолчанию. Обновление фоновое 2× в сутки (12:00 и 00:05 МСК). */
$GLOBALS['tv_promo_cache_ttl'] = (int)(min(48, max(12, (float)(getenv('TOURVISOR_PROMO_CACHE_TTL_HOURS') ?: ($_ENV['TOURVISOR_PROMO_CACHE_TTL_HOURS'] ?? 24)))) * 3600);
/** TTL кэша справочников (города вылета, страны, питание): 30 дней — редко обновляются. */
$GLOBALS['tv_dictionary_cache_ttl'] = (int)(min(2160, max(168, (float)(getenv('TOURVISOR_DICTIONARY_CACHE_TTL_HOURS') ?: ($_ENV['TOURVISOR_DICTIONARY_CACHE_TTL_HOURS'] ?? 720)))) * 3600);
/** TTL кэша блока туров на страницах стран (search-cached + cacheScope=country_page): 24 ч. */
$GLOBALS['tv_country_page_cache_ttl'] = (int)(min(168, max(1, (float)(getenv('TOURVISOR_COUNTRY_PAGE_CACHE_TTL_HOURS') ?: ($_ENV['TOURVISOR_COUNTRY_PAGE_CACHE_TTL_HOURS'] ?? 24)))) * 3600);

/**
 * TTL файлового кэша поиска: акции / страницы стран / общий поиск.
 */
function tvSearchCacheTtlSeconds(bool $onlyPromo): int {
    $scope = trim((string)($_GET['cacheScope'] ?? ''));
    if ($onlyPromo) {
        return (int)($GLOBALS['tv_promo_cache_ttl'] ?? 86400);
    }
    if ($scope === 'country_page') {
        return (int)($GLOBALS['tv_country_page_cache_ttl'] ?? 86400);
    }
    return (int)($GLOBALS['tv_search_cache_ttl'] ?? (14 * 24 * 3600));
}

require_once __DIR__ . '/../tourvisor_search_cover.php';
$GLOBALS['tv_search_cover_enabled'] = filter_var(
    getenv('TH_SEARCH_COVER_ENABLED') ?: ($_ENV['TH_SEARCH_COVER_ENABLED'] ?? '1'),
    FILTER_VALIDATE_BOOLEAN
);

// Корень проекта (TOURVISOR_CACHE_DIR в .env — полный путь к папке кэша)
$GLOBALS['tv_project_root'] = function_exists('th_project_root') ? th_project_root() : dirname(__DIR__, 3);
if (!defined('TV_PROJECT_ROOT')) {
    define('TV_PROJECT_ROOT', $GLOBALS['tv_project_root']);
}

$GLOBALS['tv_firestore_project_id'] = null;
$GLOBALS['tv_firestore_used'] = null;
$GLOBALS['tv_cache_layer'] = null;
$explicitCacheDir = getenv('TOURVISOR_CACHE_DIR') ?: ($_ENV['TOURVISOR_CACHE_DIR'] ?? '');
if ($explicitCacheDir !== '') {
    $GLOBALS['tv_cache_dir_override'] = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $explicitCacheDir), DIRECTORY_SEPARATOR);
}
/** Если в запросе передан live=1 или bypassCache=1 — не читать из кэша, всегда запрос в API Tourvisor (результат по-прежнему сохраняется в кэш). */
$GLOBALS['tv_bypass_cache'] = (isset($_GET['live']) && $_GET['live'] === '1') || (isset($_GET['bypassCache']) && $_GET['bypassCache'] === '1');

function tvLog(string $msg, array $ctx = []): void {
    try {
        th_log_write('tourvisor_api', $msg, $ctx, 'info');
    } catch (Throwable $e) {
        error_log('[tourvisor] tvLog failed: ' . $e->getMessage());
    }
}

/** JSON-поле _ymlIngest в ответе: GET ymlDebug=1 и YML_CLIENT_DEBUG=1 в .env (для консоли браузера на проде). */
function tvYmlClientDebugEnabled(): bool {
    if (!isset($_GET['ymlDebug']) || $_GET['ymlDebug'] !== '1') {
        return false;
    }

    return filter_var(getenv('YML_CLIENT_DEBUG') ?: ($_ENV['YML_CLIENT_DEBUG'] ?? '0'), FILTER_VALIDATE_BOOLEAN);
}

/**
 * Если в ответе поиска нет picturelink — подставляем типовой путь Tourvisor (как в YML-фиде).
 *
 * @param array<int, mixed> $hotels
 * @return array<int, mixed>
 */
function tv_enrich_hotel_pictures(array $hotels): array
{
    foreach ($hotels as $idx => $hotel) {
        if (!is_array($hotel)) {
            continue;
        }
        $pic = trim((string) ($hotel['picturelink'] ?? $hotel['pictureLink'] ?? ''));
        if ($pic !== '') {
            continue;
        }
        if (!empty($hotel['pictures']) && is_array($hotel['pictures'])) {
            foreach ($hotel['pictures'] as $p) {
                if (is_string($p) && trim($p) !== '') {
                    $pic = trim($p);
                    break;
                }
                if (is_array($p)) {
                    foreach (['src', 'url', 'link', 'picturelink', 'pictureLink'] as $k) {
                        $s = trim((string) ($p[$k] ?? ''));
                        if ($s !== '') {
                            $pic = $s;
                            break 2;
                        }
                    }
                }
            }
        }
        if ($pic === '') {
            $hid = (int) ($hotel['id'] ?? $hotel['hotelId'] ?? 0);
            if ($hid > 0) {
                $pic = 'hotel_pics/main400/' . $hid . '.jpg';
            }
        }
        if ($pic !== '') {
            $hotels[$idx]['picturelink'] = $pic;
        }
    }

    return $hotels;
}

/**
 * Урезанная выдача для списка карточек (SpaceWeb / PHP-only):
 * без description и лишних туров — меньше JSON и быстрее parse/paint.
 *
 * @param array<int, mixed> $hotels
 * @return array<int, mixed>
 */
function tv_slim_hotels_for_list(array $hotels): array
{
    $out = [];
    foreach ($hotels as $hotel) {
        if (!is_array($hotel)) {
            continue;
        }
        $pics = [];
        $seen = [];
        $addPic = static function ($u) use (&$pics, &$seen): void {
            $u = trim((string) $u);
            if ($u === '' || isset($seen[$u]) || count($pics) >= 6) {
                return;
            }
            $seen[$u] = true;
            $pics[] = $u;
        };
        $addPic($hotel['picturelink'] ?? $hotel['pictureLink'] ?? '');
        if (!empty($hotel['pictures']) && is_array($hotel['pictures'])) {
            foreach ($hotel['pictures'] as $p) {
                if (is_string($p)) {
                    $addPic($p);
                } elseif (is_array($p)) {
                    foreach (['src', 'url', 'link', 'picturelink', 'pictureLink'] as $k) {
                        if (!empty($p[$k])) {
                            $addPic($p[$k]);
                            break;
                        }
                    }
                }
            }
        }
        $hid = (int) ($hotel['id'] ?? $hotel['hotelId'] ?? 0);
        if ($pics === [] && $hid > 0) {
            $addPic('hotel_pics/main400/' . $hid . '.jpg');
        }

        $tourSlim = null;
        if (!empty($hotel['tours']) && is_array($hotel['tours'])) {
            $t0 = $hotel['tours'][0] ?? null;
            if (is_array($t0)) {
                $meal = $t0['meal'] ?? null;
                if (is_array($meal)) {
                    $meal = array_filter([
                        'name' => $meal['name'] ?? null,
                        'russianName' => $meal['russianName'] ?? null,
                        'code' => $meal['code'] ?? null,
                    ], static fn($v) => $v !== null && $v !== '');
                }
                $tourSlim = array_filter([
                    'id' => $t0['id'] ?? $t0['tourId'] ?? null,
                    'price' => $t0['price'] ?? null,
                    'totalPrice' => $t0['totalPrice'] ?? null,
                    'priceRub' => $t0['priceRub'] ?? null,
                    'cost' => $t0['cost'] ?? null,
                    'fuelCharge' => $t0['fuelCharge'] ?? null,
                    'nights' => $t0['nights'] ?? null,
                    'date' => $t0['date'] ?? $t0['flydate'] ?? null,
                    'meal' => $meal ?: null,
                    'roomType' => $t0['roomType'] ?? null,
                    'flydate' => $t0['flydate'] ?? null,
                    'datefrom' => $t0['datefrom'] ?? $t0['dateFrom'] ?? null,
                    'dateto' => $t0['dateto'] ?? $t0['dateTo'] ?? null,
                    'adults' => $t0['adults'] ?? null,
                    'childs' => $t0['childs'] ?? null,
                    'operator' => $t0['operator'] ?? ($t0['operatorName'] ?? null),
                    'operatorName' => $t0['operatorName'] ?? null,
                ], static fn($v) => $v !== null && $v !== '');
            }
        }

        $country = $hotel['country'] ?? null;
        if (is_array($country)) {
            $country = array_filter([
                'id' => $country['id'] ?? null,
                'name' => $country['name'] ?? null,
            ], static fn($v) => $v !== null && $v !== '');
        }
        $region = $hotel['region'] ?? null;
        if (is_array($region)) {
            $region = array_filter([
                'id' => $region['id'] ?? null,
                'name' => $region['name'] ?? null,
            ], static fn($v) => $v !== null && $v !== '');
        }

        $row = [
            'id' => $hotel['id'] ?? $hotel['hotelId'] ?? null,
            'name' => $hotel['name'] ?? '',
            'category' => $hotel['category'] ?? null,
            'rating' => $hotel['rating'] ?? null,
            'country' => $country ?: null,
            'region' => $region ?: null,
            'picturelink' => $pics[0] ?? '',
            'pictures' => $pics,
            'hotelDescriptionLink' => $hotel['hotelDescriptionLink'] ?? $hotel['hoteldescriptionlink'] ?? null,
            'link' => $hotel['link'] ?? null,
            'tours' => $tourSlim ? [$tourSlim] : [],
        ];
        $out[] = array_filter($row, static function ($v) {
            if ($v === null || $v === '') {
                return false;
            }
            if (is_array($v) && $v === []) {
                return false;
            }
            return true;
        });
    }

    return $out;
}

function tvCacheDir(): string {
    if (!empty($GLOBALS['tv_cache_dir_override'])) {
        $dir = $GLOBALS['tv_cache_dir_override'];
    } elseif (function_exists('th_tourvisor_cache_dir')) {
        $dir = th_tourvisor_cache_dir();
    } else {
        $dir = ($GLOBALS['tv_project_root'] ?? dirname(__DIR__, 3)) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'tourvisor_cache';
    }
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

function tvCacheKey(string $type, array $params): string {
    ksort($params);
    $s = $type . '_' . http_build_query($params, '', '_');
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $s);
}

/** ID документа в Firestore dictionaryCache для справочников: без параметров — type (departures/countries), с параметрами — type_key=val_key=val */
function tvRefFirestoreDocId(string $type, array $reqParams, string $cacheKey): string {
    if (empty($reqParams)) {
        return $type;
    }
    ksort($reqParams);
    $pairs = [];
    foreach ($reqParams as $k => $v) {
        if ($v === true) {
            $pairs[] = $k . '=true';
        } elseif ($v === false) {
            $pairs[] = $k . '=false';
        } else {
            $pairs[] = $k . '=' . $v;
        }
    }
    return $type . '_' . implode('_', $pairs);
}

function tvCacheGet(string $key, ?int $ttlOverride = null): ?array {
    $file = tvCacheDir() . DIRECTORY_SEPARATOR . $key . '.json';
    if (!file_exists($file)) return null;
    $ttl = $ttlOverride ?? (int)($GLOBALS['tv_cache_ttl'] ?? 86400);
    $age = time() - filemtime($file);
    if ($age >= $ttl) return null;
    $raw = file_get_contents($file);
    $d = json_decode($raw, true);
    return is_array($d) ? $d : null;
}

/** Кэш старше TTL, но не старше $maxAge — для stale-while-error (tour-flights). */
function tvCacheGetStale(string $key, int $maxAge = 604800): ?array {
    $file = tvCacheDir() . DIRECTORY_SEPARATOR . $key . '.json';
    if (!file_exists($file)) {
        return null;
    }
    $age = time() - filemtime($file);
    if ($age >= $maxAge) {
        return null;
    }
    $raw = @file_get_contents($file);
    $d = is_string($raw) ? json_decode($raw, true) : null;

    return is_array($d) ? $d : null;
}

/** Берёт данные из любого файла кэша для типа (departures_*, countries_*, meals_*), если есть и не старше maxAge. Заполнение поисковика из отложенного кэша. */
function tvCacheGetAnyForType(string $type, int $maxAge = 86400): ?array {
    $dir = tvCacheDir();
    $prefix = preg_replace('/[^a-zA-Z0-9_-]/', '_', $type) . '_';
    $pattern = $dir . DIRECTORY_SEPARATOR . $prefix . '*.json';
    $files = glob($pattern);
    if (!$files || !is_array($files)) {
        tvLog('cache_any_glob_empty', ['type' => $type, 'dir' => $dir, 'pattern' => $prefix . '*.json']);
        return null;
    }
    $best = null;
    $bestMtime = 0;
    $now = time();
    foreach ($files as $file) {
        $mtime = @filemtime($file);
        if ($mtime === false) continue;
        if ($now - $mtime >= $maxAge) continue;
        if ($mtime <= $bestMtime) continue;
        $raw = @file_get_contents($file);
        if ($raw === false) continue;
        $d = json_decode($raw, true);
        if (is_array($d) && isset($d['data'])) {
            $best = $d;
            $bestMtime = $mtime;
        }
    }
    if ($best === null) {
        tvLog('cache_any_no_valid', ['type' => $type, 'dir' => $dir, 'files_count' => count($files)]);
    }
    return $best;
}

function tvCacheSet(string $key, array $data): void {
    $file = tvCacheDir() . DIRECTORY_SEPARATOR . $key . '.json';
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/**
 * Возрасты детей для Tourvisor: из строки "7,10" или массива.
 * Не использовать array_filter без callback — он удаляет 0 (младенец / возраст по умолчанию в UI).
 *
 * @return list<int>
 */
function tvParseChildAges(mixed $childs): array
{
    if ($childs === null || $childs === '') {
        return [];
    }
    if (is_array($childs)) {
        $out = [];
        foreach ($childs as $a) {
            if ($a === null || $a === '') {
                continue;
            }
            if (is_numeric($a)) {
                $n = (int) $a;
                if ($n >= 0 && $n <= 17) {
                    $out[] = $n;
                }
            }
        }

        return $out;
    }
    $s = trim((string) $childs);
    if ($s === '') {
        return [];
    }
    $out = [];
    foreach (explode(',', $s) as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        if (preg_match('/^-?\d+$/', $part)) {
            $n = (int) $part;
            if ($n >= 0 && $n <= 17) {
                $out[] = $n;
            }
        }
    }

    return $out;
}

/**
 * Короткий кэш для «живых» данных (цена тура, перелёты) — снижает burst-нагрузку, но почти не влияет на свежесть.
 * Уважает live=1 / bypassCache=1 (не читает кэш, но записывает результат).
 */
function tvCachedShort(string $type, array $params, int $ttlSeconds, callable $fetchFn, bool $staleOnError = false): array {
    $cacheKey = tvCacheKey($type, $params);
    if (!$GLOBALS['tv_bypass_cache']) {
        $cached = tvCacheGet($cacheKey, $ttlSeconds);
        if (is_array($cached)) {
            $GLOBALS['tv_cache_hit'] = true;
            return $cached;
        }
    }
    $GLOBALS['tv_cache_hit'] = false;
    $res = $fetchFn();
    if (is_array($res) && !empty($res['success'])) {
        tvCacheSet($cacheKey, $res);
        return $res;
    }
    if (is_array($res) && $type === 'tour-flights') {
        if (!empty($res['operatorNoFlights'])) {
            tvCacheSet($cacheKey, $res);

            return $res;
        }
        $errLow = strtolower(trim((string) ($res['error'] ?? '')));
        if ($errLow !== '' && (str_contains($errLow, 'not found') || str_contains($errLow, 'не найден'))) {
            $gone = array_merge($res, ['success' => false, 'tourGone' => true]);
            tvCacheSet($cacheKey, $gone);

            return $gone;
        }
    }
    if ($staleOnError) {
        $stale = tvCacheGetStale($cacheKey, max($ttlSeconds * 4, 86400));
        if (is_array($stale) && !empty($stale['success'])) {
            $stale['fromStaleCache'] = true;
            $GLOBALS['tv_cache_hit'] = true;
            return $stale;
        }
    }

    return $res;
}

/** Порядок полей как в TravelHubNew (worker/cacheKey + tourSearchCache) для совместимости ключа с Firestore searchCache */
define('TV_SEARCH_PARAM_KEYS', [
    'adults', 'arrivalId', 'childs', 'countryId', 'currency', 'dateFrom', 'dateTo',
    'departureId', 'hotelCategory', 'hotelIds', 'hotelRating', 'hotelServices', 'meal', 'nightsFrom', 'nightsTo',
    'onlyCharter', 'onlyDirect', 'regionIds', 'subregionIds',
]);

/** Стабильный хэш как в TravelHubNew stableHash() — один и тот же ключ для одинаковых параметров */
function tvStableHash(string $s): string {
    $h = 0;
    $len = strlen($s);
    for ($i = 0; $i < $len; $i++) {
        $h = (($h << 5) - $h + ord($s[$i])) & 0xFFFFFFFF;
        if ($h & 0x80000000) {
            $h -= 0x100000000;
        }
    }
    return 's' . base_convert((string)abs($h), 10, 36);
}

/** Namespace search-кэша: main (главная/страны) vs promo (onlyPromo=1). Не смешивать записи. */
function tvSearchCacheNamespace(?bool $onlyPromo = null): string
{
    if ($onlyPromo === null) {
        $onlyPromo = isset($_GET['onlyPromo']) && $_GET['onlyPromo'] === '1';
    }

    return $onlyPromo ? 'promo' : 'main';
}

/**
 * Ключ кэша поиска: search_{namespace}_dep{N}_cnt{N}_{hash}.
 * Legacy без namespace: search_dep{N}_cnt{N}_{hash} (только чтение main при миграции).
 */
function tvSearchParamsKey(array $params, int $limit = 25, ?string $namespace = null): string {
    $params = array_merge(['currency' => 'RUB', 'onlyCharter' => false], $params);
    if ($namespace === null) {
        $onlyPromo = !empty($params['onlyPromo']) && (string) $params['onlyPromo'] === '1';
        $namespace = $onlyPromo ? 'promo' : 'main';
    }
    $p = [];
    foreach (TV_SEARCH_PARAM_KEYS as $k) {
        if (!array_key_exists($k, $params)) continue;
        $v = $params[$k];
        if ($v === null || $v === '') continue;
        if ($k === 'regionIds' && is_string($v)) {
            $v = array_values(array_map('intval', array_filter(explode(',', $v))));
            sort($v);
        }
        if ($k === 'hotelIds' && is_string($v)) {
            $v = array_values(array_map('intval', array_filter(explode(',', $v))));
            sort($v);
        }
        if ($k === 'childs') {
            $v = tvParseChildAges(is_string($v) ? $v : (is_array($v) ? $v : (string) $v));
            sort($v);
        }
        if ($k === 'hotelServices' && is_string($v)) {
            $v = array_values(array_map('intval', array_filter(explode(',', $v))));
            sort($v);
        }
        if (is_array($v)) {
            $p[$k] = implode(',', $v);
        } else {
            $p[$k] = (string)$v;
        }
    }
    $parts = [];
    foreach (TV_SEARCH_PARAM_KEYS as $k) {
        if (!isset($p[$k])) continue;
        $parts[] = $k . '=' . $p[$k];
    }
    $parts[] = 'limit=' . $limit;
    $canonical = implode('|', $parts);
    $dep = (int)($params['departureId'] ?? 0);
    $cnt = (int)($params['countryId'] ?? 0);
    $ns = preg_replace('/[^a-z0-9_-]/', '', (string) $namespace) ?: 'main';

    return 'search_' . $ns . '_dep' . $dep . '_cnt' . $cnt . '_' . tvStableHash($canonical);
}

/** Legacy-ключ (до namespace) — fallback чтения main, без записи. */
function tvSearchParamsKeyLegacy(array $params, int $limit = 25): string
{
    $params = array_merge(['currency' => 'RUB', 'onlyCharter' => false], $params);
    $p = [];
    foreach (TV_SEARCH_PARAM_KEYS as $k) {
        if (!array_key_exists($k, $params)) {
            continue;
        }
        $v = $params[$k];
        if ($v === null || $v === '') {
            continue;
        }
        if ($k === 'regionIds' && is_string($v)) {
            $v = array_values(array_map('intval', array_filter(explode(',', $v))));
            sort($v);
        }
        if ($k === 'hotelIds' && is_string($v)) {
            $v = array_values(array_map('intval', array_filter(explode(',', $v))));
            sort($v);
        }
        if ($k === 'childs') {
            $v = tvParseChildAges(is_string($v) ? $v : (is_array($v) ? $v : (string) $v));
            sort($v);
        }
        if ($k === 'hotelServices' && is_string($v)) {
            $v = array_values(array_map('intval', array_filter(explode(',', $v))));
            sort($v);
        }
        if (is_array($v)) {
            $p[$k] = implode(',', $v);
        } else {
            $p[$k] = (string) $v;
        }
    }
    $parts = [];
    foreach (TV_SEARCH_PARAM_KEYS as $k) {
        if (!isset($p[$k])) {
            continue;
        }
        $parts[] = $k . '=' . $p[$k];
    }
    $parts[] = 'limit=' . $limit;
    $canonical = implode('|', $parts);
    $dep = (int) ($params['departureId'] ?? 0);
    $cnt = (int) ($params['countryId'] ?? 0);

    return 'search_dep' . $dep . '_cnt' . $cnt . '_' . tvStableHash($canonical);
}

/**
 * Чтение search-кэша: сначала namespaced, для main — fallback на legacy-ключ.
 *
 * @return array<int, mixed>|null
 */
function tvGetSearchResultsCacheForScope(array $sp, string $namespace, int $ttl): ?array
{
    $ck = tvSearchParamsKey($sp, 25, $namespace);
    $cached = tvGetSearchResultsCache($ck, $ttl);
    if (is_array($cached) && $cached !== []) {
        return $cached;
    }
    if ($namespace === 'main') {
        $legacyCk = tvSearchParamsKeyLegacy($sp);
        if ($legacyCk !== $ck) {
            $legacy = tvGetSearchResultsCache($legacyCk, $ttl);
            if (is_array($legacy) && $legacy !== []) {
                return $legacy;
            }
        }
    }

    return null;
}

function tvSearchParamsPath(int $searchId): string {
    return tvCacheDir() . DIRECTORY_SEPARATOR . 'search_params_' . $searchId . '.json';
}

function tvSaveSearchParams(int $searchId, array $params): void {
    file_put_contents(tvSearchParamsPath($searchId), json_encode($params, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function tvLoadSearchParams(int $searchId): ?array {
    $f = tvSearchParamsPath($searchId);
    if (!file_exists($f)) return null;
    $d = json_decode(file_get_contents($f), true);
    return is_array($d) ? $d : null;
}

function tvSaveSearchResultsCache(string $key, array $results): void {
    $file = tvCacheDir() . DIRECTORY_SEPARATOR . $key . '.json';
    $data = ['results' => $results, 'cachedAt' => time()];
    @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/**
 * L1 Firestore searchCache — быстрый remote read (SpaceWeb / multi-node).
 *
 * @return list<mixed>|null
 */
function tvGetSearchResultsCacheFirestore(string $key): ?array {
    $projectId = $GLOBALS['tv_firestore_project_id'] ?? null;
    if ($projectId === null || $projectId === '') {
        return null;
    }
    tv_firestore_helper_load();
    if (!function_exists('firestoreGet')) {
        return null;
    }
    $fs = @firestoreGet((string) $projectId, 'searchCache', $key);
    if ($fs === null || !isset($fs['data']) || !is_array($fs['data'])) {
        return null;
    }
    $data = $fs['data'];
    // Документ может быть { results: [...] } или сразу списком отелей
    if (isset($data['results']) && is_array($data['results'])) {
        return $data['results'];
    }
    if ($data === []) {
        return null;
    }
    // list of hotels
    if (isset($data[0]) || array_values($data) === $data) {
        return $data;
    }

    return null;
}

/**
 * Трёхуровневое чтение кэша поиска:
 *   L1 — Firestore searchCache (TH_CACHE_FIRESTORE_FIRST=1)
 *   L2 — локальный файл data/tourvisor_cache
 *   L3 — зарезервирован под Go sidecar (на VPS); здесь только метка при будущем чтении
 *
 * @return list<mixed>|null
 */
function tvGetSearchResultsCache(string $key, int $maxAge = 0): ?array {
    $fsFirst = filter_var(
        getenv('TH_CACHE_FIRESTORE_FIRST') ?: ($_ENV['TH_CACHE_FIRESTORE_FIRST'] ?? '1'),
        FILTER_VALIDATE_BOOLEAN
    );

    if ($fsFirst) {
        $fromFs = tvGetSearchResultsCacheFirestore($key);
        if (is_array($fromFs) && $fromFs !== []) {
            $GLOBALS['tv_cache_layer'] = 'L1-firestore';
            $GLOBALS['tv_firestore_used'] = 'hit';
            $GLOBALS['tv_cache_hit'] = true;
            // Прогрев L2 на диске (быстрый локальный fallback при следующем запросе)
            @tvSaveSearchResultsCache($key, $fromFs);
            tvLog('search_cache_hit', [
                'cache_key' => $key,
                'layer' => 'L1-firestore',
                'hotels_count' => count($fromFs),
            ]);

            return $fromFs;
        }
    }

    $file = tvCacheDir() . DIRECTORY_SEPARATOR . $key . '.json';
    if (is_file($file)) {
        $useFile = true;
        if ($maxAge > 0) {
            $age = time() - (int) filemtime($file);
            if ($age >= $maxAge) {
                $useFile = false;
            }
        }
        if ($useFile) {
            $d = json_decode((string) file_get_contents($file), true);
            if (is_array($d) && isset($d['results']) && is_array($d['results']) && $d['results'] !== []) {
                $GLOBALS['tv_cache_layer'] = 'L2-file';
                $GLOBALS['tv_cache_hit'] = true;
                tvLog('search_cache_hit', [
                    'cache_key' => $key,
                    'layer' => 'L2-file',
                    'hotels_count' => count($d['results']),
                ]);

                return $d['results'];
            }
        }
    }

    // Если L1 был выключен — всё же попробуем Firestore после файла
    if (!$fsFirst) {
        $fromFs = tvGetSearchResultsCacheFirestore($key);
        if (is_array($fromFs) && $fromFs !== []) {
            $GLOBALS['tv_cache_layer'] = 'L1-firestore';
            $GLOBALS['tv_firestore_used'] = 'hit';
            $GLOBALS['tv_cache_hit'] = true;
            @tvSaveSearchResultsCache($key, $fromFs);

            return $fromFs;
        }
    }

    return null;
}

const TV_ALL_TOURS_CACHE_KEY = 'all_tours';

/** Читает all_tours из кэша. $maxAge = 0 значит не проверять возраст (всегда брать из кэша). */
function tvGetAllToursCache(int $maxAge = 0): ?array {
    $file = tvCacheDir() . DIRECTORY_SEPARATOR . TV_ALL_TOURS_CACHE_KEY . '.json';
    if (!file_exists($file)) return null;
    $size = @filesize($file);
    if ($size === false || $size < 50) return null;
    if ($maxAge > 0) {
        $age = time() - filemtime($file);
        if ($age >= $maxAge && $size < 100) return null; // просрочен и почти пустой — не использовать
    }
    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') return null;
    $d = json_decode($raw, true);
    return is_array($d) && isset($d['results']) ? $d['results'] : null;
}

function tvMergeAllToursCache(array $newResults): void {
    $file = tvCacheDir() . DIRECTORY_SEPARATOR . TV_ALL_TOURS_CACHE_KEY . '.json';
    $existing = [];
    if (file_exists($file)) {
        $d = json_decode(file_get_contents($file), true);
        $existing = (is_array($d) && isset($d['results'])) ? $d['results'] : [];
    }
    $existingIds = array_flip(array_map(function ($h) { return $h['id'] ?? 0; }, $existing));
    foreach ($newResults as $hotel) {
        $id = $hotel['id'] ?? null;
        if ($id !== null && $id !== 0 && !isset($existingIds[$id])) {
            $existing[] = $hotel;
            $existingIds[$id] = true;
        }
    }
    $data = ['results' => $existing, 'cachedAt' => time()];
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/** Ослабленная копия параметров: только страна, без дат, широкий диапазон ночей — чтобы при пустом точном поиске показать хоть что-то из all_tours */
function tvRelaxSearchParams(array $params): array {
    $relaxed = $params;
    $relaxed['dateFrom'] = '';
    $relaxed['dateTo'] = '';
    $relaxed['nightsFrom'] = 1;
    $relaxed['nightsTo'] = 21;
    return $relaxed;
}

/**
 * Диапазон ночей для Tourvisor API (лимит span ≤ 10).
 * При nightsFrom ≥ 10 TV часто отдаёт только битые/проданные позиции (Шри-Ланка и др.) —
 * расширяем вниз на 2 ночи, чтобы подтянуть живую выдачу (8–14 вместо 10–14).
 *
 * @return array{0:int,1:int,2:bool} [nightsFrom, nightsTo, expanded]
 */
function tvNightsForTourvisorApi(int $nightsFrom, int $nightsTo): array
{
    $from = max(1, min(28, $nightsFrom));
    $to = max(1, min(28, $nightsTo));
    if ($to < $from) {
        $to = $from;
    }
    if ($to - $from > 10) {
        $to = $from + 10;
    }
    $expanded = false;
    if ($from >= 10) {
        $relaxedFrom = max(7, $from - 2);
        if ($relaxedFrom < $from) {
            $from = $relaxedFrom;
            $expanded = true;
        }
    }
    if ($to - $from > 10) {
        $to = $from + 10;
    }

    return [$from, $to, $expanded];
}

/** Фильтрация туров из общего кэша по параметрам поиска (как filterToursByParamsFromCache в TravelHubNew) */
function tvFilterToursByParams(array $tours, array $params): array {
    $countryId = (int)($params['countryId'] ?? 0);
    $countryName = isset($params['countryName']) ? trim((string)$params['countryName']) : '';
    $dateFrom = $params['dateFrom'] ?? '';
    $dateTo = $params['dateTo'] ?? '';
    $nightsFrom = (int)($params['nightsFrom'] ?? 6);
    $nightsTo = (int)($params['nightsTo'] ?? 9);
    $adults = (int)($params['adults'] ?? 2);
    $meal = isset($params['meal']) ? (int)$params['meal'] : 0;
    $hotelCategory = isset($params['hotelCategory']) ? (int)$params['hotelCategory'] : 0;
    $regionIds = [];
    if (!empty($params['regionIds'])) {
        $regionIds = is_array($params['regionIds']) ? $params['regionIds'] : array_map('intval', array_filter(explode(',', (string)$params['regionIds'])));
    }
    $childs = tvParseChildAges($params['childs'] ?? null);
    $tsFrom = $dateFrom ? strtotime($dateFrom) : 0;
    $tsTo = $dateTo ? strtotime($dateTo) : PHP_INT_MAX;

    $filtered = [];
    foreach ($tours as $hotel) {
        $cId = (int)($hotel['country']['id'] ?? $hotel['countryId'] ?? 0);
        $cName = isset($hotel['country']['name']) ? trim((string)$hotel['country']['name']) : '';
        $countryMatch = true;
        if ($countryId || $countryName !== '') {
            $countryMatch = ($countryId && $cId === $countryId)
                || ($countryName !== '' && $cName !== '' && stripos($cName, $countryName) !== false);
        }
        if (!$countryMatch) continue;
        if (!empty($regionIds)) {
            $rId = (int)($hotel['region']['id'] ?? $hotel['regionId'] ?? 0);
            if (!in_array($rId, $regionIds, true)) continue;
        }
        $htours = $hotel['tours'] ?? [];
        $matchingTours = [];
        foreach ($htours as $t) {
            $tDate = isset($t['date']) ? strtotime($t['date']) : 0;
            if ($tDate && $tsFrom && $tsTo < PHP_INT_MAX && ($tDate < $tsFrom || $tDate > $tsTo)) continue;
            $n = (int)($t['nights'] ?? 0);
            if ($n > 0 && $nightsTo >= $nightsFrom && ($n < $nightsFrom || $n > $nightsTo)) continue;
            $a = isset($t['adults']) ? (int)$t['adults'] : null;
            if ($a !== null && $a !== $adults) continue;
            $ch = isset($t['childs']) ? (int)$t['childs'] : null;
            if (!empty($childs)) {
                if ($ch !== null && $ch !== count($childs)) continue;
            } elseif ($ch !== null && $ch > 0) continue;
            if ($meal && isset($t['meal']['id']) && (int)$t['meal']['id'] < $meal) continue;
            if ($hotelCategory && (int)($hotel['category'] ?? 0) < $hotelCategory) continue;
            $matchingTours[] = $t;
        }
        if (!empty($matchingTours)) {
            $filtered[] = array_merge($hotel, ['tours' => $matchingTours]);
        }
    }
    return $filtered;
}

function tvBuildQuery(array $params): string {
    $pairs = [];
    foreach ($params as $k => $v) {
        if (is_array($v)) {
            foreach ($v as $vv) {
                if ($vv !== '' && $vv !== null) {
                    $pairs[] = urlencode($k) . '=' . urlencode((string)$vv);
                }
            }
        } elseif ($v === true || $v === false) {
            $pairs[] = urlencode($k) . '=' . ($v ? 'true' : 'false');
        } elseif ($v !== '' && $v !== null) {
            $pairs[] = urlencode($k) . '=' . urlencode((string)$v);
        }
    }
    return implode('&', $pairs);
}

function tv_dictionary_use_fallback(): bool
{
    if (!empty($GLOBALS['tv_use_fallbacks'])) {
        return true;
    }

    return getTvToken() === '';
}

function getTvToken(): string {
    $src = $GLOBALS['tv_source'] ?? 'default';
    $tokenKey = '';
    if ($src === 'main') {
        $tokenKey = 'TOURVISOR_TOKEN_MAIN';
    } elseif ($src === 'promo') {
        $tokenKey = 'TOURVISOR_TOKEN_PROMO';
    } elseif ($src === 'countries') {
        $tokenKey = 'TOURVISOR_TOKEN_COUNTRIES';
    }
    if ($tokenKey !== '') {
        $jwt = trim((string)(getenv($tokenKey) ?: ($_ENV[$tokenKey] ?? '')));
        if ($jwt !== '') return $jwt;
    }
    $jwt = trim((string)(getenv('TOURVISOR_TOKEN') ?: ($_ENV['TOURVISOR_TOKEN'] ?? '')));
    if ($jwt === '') {
        $jwt = trim((string)(getenv('TOURVISOR_JWT_TOKEN') ?: ($_ENV['TOURVISOR_JWT_TOKEN'] ?? '')));
    }
    return $jwt;
}

/**
 * Лимит исходящих запросов к Tourvisor (~30/min по доке → держим запас).
 * @see https://api.tourvisor.ru/search/docs
 */
function tvOutboundRpmLimit(): int
{
    $n = (int) (getenv('TH_TV_OUTBOUND_RPM') ?: ($_ENV['TH_TV_OUTBOUND_RPM'] ?? 25));

    return max(5, min(30, $n));
}

/**
 * Сколько continue разрешено (каждый continue = отдельный search в суточном лимите TV).
 * По умолчанию 0 — не жжём квоту.
 */
function tvContinueMax(): int
{
    $n = (int) (getenv('TH_TV_CONTINUE_MAX') ?: ($_ENV['TH_TV_CONTINUE_MAX'] ?? 0));

    return max(0, min(2, $n));
}

/**
 * Глобальный throttle исходящих вызовов TV (файловый sliding window).
 */
function tvOutboundThrottleWait(): void
{
    $rpm = tvOutboundRpmLimit();
    $dir = tvCacheDir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . DIRECTORY_SEPARATOR . 'tv_outbound_rate.json';
    $lock = $dir . DIRECTORY_SEPARATOR . 'tv_outbound_rate.lock';
    $h = @fopen($lock, 'c+');
    if ($h === false) {
        usleep(200000);
        return;
    }
    try {
        if (!@flock($h, LOCK_EX)) {
            usleep(200000);
            return;
        }
        $now = microtime(true);
        $window = 60.0;
        $times = [];
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($decoded) && isset($decoded['t']) && is_array($decoded['t'])) {
                foreach ($decoded['t'] as $t) {
                    $tf = (float) $t;
                    if (($now - $tf) < $window) {
                        $times[] = $tf;
                    }
                }
            }
        }
        if (count($times) >= $rpm) {
            $oldest = min($times);
            $wait = ($oldest + $window) - $now;
            if ($wait > 0) {
                // Не блокируем PHP дольше 8с за раз — иначе таймауты веб-запроса
                $sleepUs = (int) min(8.0, $wait) * 1000000;
                usleep(max(100000, $sleepUs));
                $now = microtime(true);
                $times = array_values(array_filter($times, static fn ($t) => ($now - (float) $t) < $window));
            }
        }
        $times[] = microtime(true);
        @file_put_contents($file, json_encode(['t' => array_slice($times, -$rpm)], JSON_UNESCAPED_UNICODE), LOCK_EX);
    } finally {
        @flock($h, LOCK_UN);
        @fclose($h);
    }
}

/**
 * Polling статуса поиска по доке: пауза 3с, затем каждые 2–3с, soft timeout.
 * earlyProgress: можно забрать частичную выдачу раньше 100% (меньше TTFB).
 *
 * @param array{earlyProgress?:int} $opts
 * @return array{ok:bool,error?:string,polls:int,softTimeout?:bool,earlyReady?:bool,progress?:int}
 */
function tvPollSearchUntilReady(int $searchId, int $maxSeconds = 24, array $opts = []): array
{
    $started = time();
    $polls = 0;
    $earlyProgress = isset($opts['earlyProgress']) ? (int) $opts['earlyProgress'] : 0;
    if ($earlyProgress < 0) {
        $earlyProgress = 0;
    }
    if ($earlyProgress > 95) {
        $earlyProgress = 95;
    }
    // Дока: результатов обычно нет раньше 3–5с
    sleep(3);
    $lastProgress = 0;
    while ((time() - $started) < $maxSeconds) {
        $polls++;
        $st = tvRequest("/tours/search/{$searchId}/status", ['operatorStatus' => true]);
        if (!$st['success']) {
            $err = (string) ($st['error'] ?? '');
            if (stripos($err, '429') !== false || stripos($err, 'rate') !== false) {
                sleep(5);
                continue;
            }
            return ['ok' => false, 'error' => $err !== '' ? $err : 'status failed', 'polls' => $polls];
        }
        $status = isset($st['data']['status']) ? strtolower((string) $st['data']['status']) : '';
        $progress = (int) ($st['data']['progress'] ?? 0);
        $lastProgress = $progress;
        if ($status === 'completed' || $progress >= 100) {
            return ['ok' => true, 'polls' => $polls, 'progress' => $progress];
        }
        if ($status === 'error') {
            return ['ok' => false, 'error' => 'Search error', 'polls' => $polls, 'progress' => $progress];
        }
        // Достаточно операторов ответили — можно забирать накопленные results
        if ($earlyProgress > 0 && $progress >= $earlyProgress) {
            return [
                'ok' => true,
                'polls' => $polls,
                'progress' => $progress,
                'earlyReady' => true,
            ];
        }
        // Дока: опрашивать статус примерно каждые 2с
        sleep(2);
    }
    // Soft timeout — всё равно пробуем забрать накопленные results
    return [
        'ok' => true,
        'polls' => $polls,
        'softTimeout' => true,
        'progress' => $lastProgress,
    ];
}

/**
 * Отложенная запись кэша после ответа клиенту (не держит TTFB).
 *
 * @param array<string,mixed> $job
 */
function tvQueuePersistAfterResponse(array $job): void
{
    if (empty($job['ck']) || empty($job['liveData']) || !is_array($job['liveData'])) {
        return;
    }
    $GLOBALS['tv_persist_after'] = $job;
}

/**
 * Выполнить отложенный persist (вызывать после flush ответа).
 */
function tvRunPersistAfterResponse(): void
{
    $job = $GLOBALS['tv_persist_after'] ?? null;
    $GLOBALS['tv_persist_after'] = null;
    if (!is_array($job) || empty($job['ck']) || empty($job['liveData']) || !is_array($job['liveData'])) {
        return;
    }
    $ck = (string) $job['ck'];
    $liveData = $job['liveData'];
    $sp = is_array($job['sp'] ?? null) ? $job['sp'] : [];
    $ttlSearch = (int) ($job['ttlSearch'] ?? tvSearchCacheTtlSeconds(!empty($job['onlyPromo'])));
    $onlyPromo = !empty($job['onlyPromo']);
    $dataToReturn = is_array($job['dataToReturn'] ?? null) ? $job['dataToReturn'] : $liveData;
    $sid = (int) ($job['searchId'] ?? 0);

    try {
        tvSaveSearchResultsCache($ck, $liveData);
        tvMergeAllToursCache($liveData);
        if (
            !empty($GLOBALS['tv_search_cover_enabled'])
            && !$onlyPromo
            && empty($sp['regionIds'])
            && empty($sp['meal'])
            && empty($sp['hotelCategory'])
        ) {
            th_search_cover_upsert(
                array_merge($sp, [
                    'currency' => 'RUB',
                    'departureId' => (int) ($sp['departureId'] ?: th_departure_default_id()),
                ]),
                $liveData,
                $ttlSearch
            );
        }
        if ($onlyPromo && !empty($dataToReturn)) {
            tv_promo_country_cache_load();
            $promoCacheCountryId = (int) ($job['promoCacheCountryId'] ?? 0);
            $skipCountryWidePromoCache = !empty($job['skipCountryWidePromoCache']);
            if ($promoCacheCountryId > 0 && !$skipCountryWidePromoCache) {
                th_promo_cache_set($promoCacheCountryId, $dataToReturn, [
                    'departureId' => (int) ($sp['departureId'] ?: th_departure_default_id()),
                    'dateFrom' => (string) ($sp['dateFrom'] ?? ''),
                    'dateTo' => (string) ($sp['dateTo'] ?? ''),
                ]);
            }
        }
        $projectId = $GLOBALS['tv_firestore_project_id'] ?? null;
        if ($projectId !== null && $projectId !== '') {
            tv_firestore_helper_load();
            @firestoreSet($projectId, 'searchCache', $ck, $liveData, time() + $ttlSearch);
        }
        tvLog('search_persist_deferred', [
            'searchId' => $sid,
            'cache_key' => $ck,
            'cache_namespace' => (string) ($job['cacheNamespace'] ?? ''),
            'tours_count' => count($liveData),
        ]);
    } catch (Throwable $e) {
        tvLog('search_persist_deferred_fail', [
            'searchId' => $sid,
            'error' => $e->getMessage(),
        ]);
    }
}

/**
 * Опциональные continue (сжигают суточный search-лимит TV). По умолчанию 0.
 */
function tvMaybeContinueSearch(int $searchId, int $hotelsSoFar = 0): int
{
    $max = tvContinueMax();
    if ($max <= 0) {
        return 0;
    }
    // Не продолжаем, если уже достаточно выдачи
    if ($hotelsSoFar >= 40) {
        return 0;
    }
    $done = 0;
    for ($i = 0; $i < $max; $i++) {
        sleep(3);
        $res = tvRequest("/tours/search/{$searchId}/continue", []);
        if (!$res['success']) {
            tvLog('search_continue_skip', [
                'searchId' => $searchId,
                'attempt' => $i + 1,
                'error' => $res['error'] ?? 'fail',
            ]);
            break;
        }
        $done++;
        tvLog('search_continue', ['searchId' => $searchId, 'attempt' => $i + 1]);
        // После continue снова короткий poll
        tvPollSearchUntilReady($searchId, 12);
    }

    return $done;
}

function tvRequest(string $endpoint, array $params = []): array {
    $jwt = getTvToken();
    if (empty($jwt)) {
        return ['success' => false, 'error' => 'TOURVISOR_TOKEN or TOURVISOR_JWT_TOKEN not configured'];
    }
    $base = $GLOBALS['tv_base'] ?? 'https://api.tourvisor.ru/search/api/v1';
    $url = $base . $endpoint;
    if (!empty($params)) {
        $url .= '?' . tvBuildQuery($params);
    }
    $isSearch = strpos($endpoint, '/tours/search') !== false;
    $isTourFlights = (bool) preg_match('#/tours/[^/]+/flights$#', $endpoint);
    // На localhost возможны SSL connection timeout из-за сети/фаервола; увеличенные таймауты снижают число сбоев
    $connectTimeout = $isSearch ? 35 : ($isTourFlights ? 30 : 25);
    $totalTimeout = $isSearch ? 60 : ($isTourFlights ? 75 : 45);
    $retries = $isSearch ? 3 : ($isTourFlights ? 2 : 2);
    $response = null;
    $errNo = 0;
    $errMsg = '';
    $code = 0;
    while ($retries >= 0) {
        tvOutboundThrottleWait();
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $totalTimeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . trim($jwt),
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_FORBID_REUSE => true,
        ]);
        $response = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errNo = curl_errno($ch);
        $errMsg = curl_error($ch);
        curl_close($ch);
        if ($errNo === 0 && $code !== 429) {
            break;
        }
        // 429 / сетевые сбои — backoff по доке (не долбить API)
        if ($code === 429 && $retries > 0) {
            $backoff = (4 - $retries) * 3; // 3, 6, 9 сек
            tvLog('retry_429', ['endpoint' => $endpoint, 'backoff_sec' => $backoff, 'attempts_left' => $retries]);
            sleep(max(3, $backoff));
            $retries--;
            continue;
        }
        $retryable = in_array($errNo, [28 /* CURLE_OPERATION_TIMEDOUT */, 56 /* CURLE_RECV_ERROR */, 35, 52, 7], true)
            || stripos($errMsg, 'reset') !== false || stripos($errMsg, 'timed out') !== false;
        if ($retries <= 0 || !$retryable) {
            break;
        }
        tvLog('retry', ['endpoint' => $endpoint, 'attempts_left' => $retries, 'error' => $errMsg]);
        usleep($isSearch ? 3000000 : 500000); // 3 сек для поиска, 0.5 сек для остальных
        $retries--;
    }
    if ($errNo !== 0) {
        return ['success' => false, 'error' => 'Request failed: ' . $errMsg];
    }
    $data = is_string($response) ? json_decode($response, true) : null;
    if ($code >= 400) {
        $errMsg = 'HTTP ' . $code;
        if (is_array($data)) {
            $errMsg = $data['error']['reason'] ?? $data['error']['message'] ?? $data['message'] ?? (is_array($data['error'] ?? null) ? json_encode($data['error']) : ($data['error'] ?? $errMsg));
        } elseif (is_string($response) && trim($response) !== '' && strlen($response) < 200) {
            $errMsg = trim($response);
        }
        tvLog('api_error', ['code' => $code, 'endpoint' => $endpoint, 'response' => $data, 'raw' => substr((string)$response, 0, 500)]);
        return ['success' => false, 'error' => is_string($errMsg) ? $errMsg : json_encode($errMsg), 'data' => $data, 'http' => $code];
    }
    if ($data === null && $response !== '' && $response !== '[]') {
        return ['success' => false, 'error' => 'Invalid JSON response', 'raw' => substr((string)$response, 0, 200)];
    }
    return ['success' => true, 'data' => $data];
}

function tvCountriesFallbackPath(): string {
    return ($GLOBALS['tv_project_root'] ?? dirname(__DIR__, 3)) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'tourvisor_countries_fallback.json';
}

function tvLoadCountriesFallback(): ?array {
    $f = tvCountriesFallbackPath();
    if (!file_exists($f)) return null;
    $raw = file_get_contents($f);
    $d = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($d) || empty($d)) return null;
    // Поддержка формата {data: [...]} или просто [...]
    if (isset($d['data']) && is_array($d['data'])) return $d['data'];
    return array_values($d) === $d ? $d : null; // массив со числовыми ключами
}

function tvRegionsSupplementPath(): string {
    return ($GLOBALS['tv_project_root'] ?? dirname(__DIR__, 3)) . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'tourvisor_regions_supplement.php';
}

function tvLoadRegionsSupplement(?int $countryId): array {
    if (!$countryId) return [];
    $f = tvRegionsSupplementPath();
    if (!file_exists($f)) return [];
    $all = require $f;
    if (!is_array($all)) return [];
    $list = $all[$countryId] ?? [];
    return is_array($list) ? array_values($list) : [];
}

function tvFetchRegionsResolved(array $params, ?int $countryId): array {
    $res = tvRequest('/regions', $params);
    if ($res['success'] && is_array($res['data']) && count($res['data']) > 0) {
        if ($countryId) {
            $filtered = array_values(array_filter($res['data'], static function ($row) use ($countryId) {
                $rowCountry = (int) ($row['countryId'] ?? 0);
                /* Некоторые ответы без countryId — оставляем; чужие страны отсекаем */
                return $rowCountry === 0 || $rowCountry === $countryId;
            }));
            /* Если API вернул чужой справочник (как Egypt при countryId=16) — не кэшируем его */
            $foreignOnly = $filtered === [] && count($res['data']) > 0;
            if (!$foreignOnly && count($filtered) > 0) {
                return ['success' => true, 'data' => $filtered];
            }
        } else {
            return $res;
        }
    }
    if ($countryId) {
        $all = tvRequest('/regions', []);
        if ($all['success'] && is_array($all['data'])) {
            $filtered = array_values(array_filter($all['data'], static function ($row) use ($countryId) {
                return (int)($row['countryId'] ?? 0) === $countryId;
            }));
            if (count($filtered) > 0) {
                return ['success' => true, 'data' => $filtered];
            }
        }
        $supp = tvLoadRegionsSupplement($countryId);
        if (count($supp) > 0) {
            return ['success' => true, 'data' => $supp];
        }
    }
    if (!$res['success'] && $params && !empty($GLOBALS['tv_use_fallbacks'])) {
        return ['success' => true, 'data' => []];
    }
    return $res;
}

function tvCached(string $type, array $reqParams, callable $fetch): array {
    tv_init_firestore_project_id();
    $key = tvCacheKey($type, $reqParams);
    if (!$GLOBALS['tv_use_fallbacks'] && in_array($type, ['departures', 'countries', 'meals', 'dates', 'regions'], true)) {
        $key .= '_api';
    }
    $ttl = in_array($type, ['departures', 'countries', 'meals', 'hotel'], true)
        ? (int)($GLOBALS['tv_dictionary_cache_ttl'] ?? (720 * 3600))
        : (int)($GLOBALS['tv_cache_ttl'] ?? 86400);
    $ttlSec = $ttl;
    tvLog('request', ['type' => $type, 'params' => $reqParams, 'cache_key' => $key, 'ttl_h' => (int)($ttl / 3600)]);

    // Обход кэша: всегда запрос в API (результат сохраняем в кэш)
    if (!empty($GLOBALS['tv_bypass_cache'])) {
        tvLog('cache_bypass', ['type' => $type, 'params' => $reqParams]);
        $GLOBALS['tv_cache_hit'] = false;
        $r = $fetch();
        $cnt = is_array($r['data'] ?? null) ? count($r['data']) : 0;
        tvLog('api_response', ['type' => $type, 'success' => !empty($r['success']), 'items' => $cnt, 'error' => $r['error'] ?? null]);
        if ($r['success'] && isset($r['data'])) {
            tvCacheSet($key, $r);
            tvLog('cache_saved', ['type' => $type, 'cache_key' => $key, 'items' => $cnt]);
            if (in_array($type, ['departures', 'countries', 'meals', 'regions', 'dates'], true)) {
                $projectId = $GLOBALS['tv_firestore_project_id'] ?? null;
                if ($projectId !== null && $projectId !== '') {
                    $expiresAt = time() + $ttlSec;
                    $fsDocId = tvRefFirestoreDocId($type, $reqParams, $key);
                    if (@firestoreSet($projectId, 'dictionaryCache', $fsDocId, $r['data'], $expiresAt)) {
                        tvLog('cache_saved_firestore', ['type' => $type, 'doc_id' => $fsDocId, 'items' => $cnt]);
                        $GLOBALS['tv_firestore_used'] = 'miss';
                    }
                }
            }
            return $r;
        }
        if (!empty($r['error']) && in_array($type, ['departures', 'countries', 'meals', 'regions'], true)) {
            $stale = tvCacheGetAnyForType($type, 30 * 86400);
            if ($stale !== null) {
                $cnt = is_array($stale['data'] ?? null) ? count($stale['data']) : 0;
                tvLog('cache_stale_used', ['type' => $type, 'items' => $cnt, 'api_error' => $r['error']]);
                $GLOBALS['tv_cache_hit'] = true;
                return $stale;
            }
        }
        return $r;
    }

    // Города вылета, страны, питание, курорты, даты: сначала Firestore (dictionaryCache), затем файл, затем API; после API — сохраняем в файл и Firestore
    $projectId = $GLOBALS['tv_firestore_project_id'] ?? null;
    if ($projectId !== null && $projectId !== '' && in_array($type, ['departures', 'countries', 'meals', 'regions', 'dates'], true)) {
        $fsDocId = tvRefFirestoreDocId($type, $reqParams, $key);
        $fs = firestoreGet($projectId, 'dictionaryCache', $fsDocId);
        if ($fs !== null && isset($fs['data']) && is_array($fs['data'])) {
            $cnt = count($fs['data']);
            tvLog('cache_hit', ['type' => $type, 'source' => 'firestore', 'params' => $reqParams, 'items' => $cnt]);
            $GLOBALS['tv_cache_hit'] = true;
            $GLOBALS['tv_firestore_used'] = 'hit';
            $GLOBALS['tv_cache_layer'] = 'L1-firestore';
            $payload = ['success' => true, 'data' => $fs['data']];
            @tvCacheSet($key, $payload);
            return $payload;
        }
    }

    $cached = tvCacheGet($key, $ttl);
    if ($cached === null && in_array($type, ['departures', 'countries', 'meals', 'regions'], true)) {
        $cached = tvCacheGetAnyForType($type, (int)($GLOBALS['tv_dictionary_cache_ttl'] ?? (720 * 3600)));
    }
    if ($cached !== null) {
        $cnt = is_array($cached['data'] ?? null) ? count($cached['data']) : 0;
        tvLog('cache_hit', ['type' => $type, 'source' => 'file', 'params' => $reqParams, 'items' => $cnt]);
        $GLOBALS['tv_cache_hit'] = true;
        $GLOBALS['tv_cache_layer'] = 'L2-file';
        return $cached;
    }

    tvLog('cache_miss', ['type' => $type, 'params' => $reqParams]);
    $GLOBALS['tv_cache_hit'] = false;
    $r = $fetch();
    $cnt = is_array($r['data'] ?? null) ? count($r['data']) : 0;
    tvLog('api_response', ['type' => $type, 'success' => !empty($r['success']), 'items' => $cnt, 'error' => $r['error'] ?? null]);
    if ($r['success'] && isset($r['data'])) {
        tvCacheSet($key, $r);
        tvLog('cache_saved', ['type' => $type, 'cache_key' => $key, 'items' => $cnt]);
        // Все справочники — сохраняем в Firestore (dictionaryCache) после ответа API
        $projectId = $GLOBALS['tv_firestore_project_id'] ?? null;
        if ($projectId !== null && $projectId !== '' && in_array($type, ['departures', 'countries', 'meals', 'regions', 'dates'], true)) {
            $expiresAt = time() + $ttlSec;
            $fsDocId = tvRefFirestoreDocId($type, $reqParams, $key);
            if (@firestoreSet($projectId, 'dictionaryCache', $fsDocId, $r['data'], $expiresAt)) {
                tvLog('cache_saved_firestore', ['type' => $type, 'doc_id' => $fsDocId, 'items' => $cnt]);
                $GLOBALS['tv_firestore_used'] = 'miss';
            }
        }
        return $r;
    }
    // При ошибке API (таймаут, SSL и т.д.) отдаём устаревший кэш для справочников, если есть
    if (!empty($r['error']) && in_array($type, ['departures', 'countries', 'meals', 'regions'], true)) {
        $stale = tvCacheGetAnyForType($type, 30 * 86400);
        if ($stale !== null) {
            $cnt = is_array($stale['data'] ?? null) ? count($stale['data']) : 0;
            tvLog('cache_stale_used', ['type' => $type, 'items' => $cnt, 'api_error' => $r['error']]);
            $GLOBALS['tv_cache_hit'] = true;
            return $stale;
        }
    }
    return $r;
}

function tourvisor_proxy_dispatch_get(array $params = []): array
{
    $savedGet = $_GET;
    if ($params !== []) {
        $_GET = array_merge($savedGet, $params);
    }
    try {
        return tourvisor_proxy_dispatch();
    } finally {
        $_GET = $savedGet;
    }
}

function tourvisor_proxy_dispatch(): array
{
    $GLOBALS['tv_req_started_at'] = microtime(true);
    tv_init_firestore_project_id();
    // Nested dispatch_get() меняет $_GET — bypass нужно пересчитывать на каждый вызов
    // (иначе live=1 во вложенном search-cached не сбрасывает promo_cache родительской страны).
    $GLOBALS['tv_bypass_cache'] = (isset($_GET['live']) && $_GET['live'] === '1')
        || (isset($_GET['bypassCache']) && $_GET['bypassCache'] === '1');
    $type = $_GET['type'] ?? '';
    $GLOBALS['tv_source'] = trim((string) ($_GET['source'] ?? '')) ?: 'default';
    $departureId = isset($_GET['departureId']) ? (int) $_GET['departureId'] : null;
    if (!$departureId && isset($_GET['departure'])) {
        $departureId = (int) $_GET['departure'];
    }

    $searchCacheOnly = isset($_GET['cacheOnly']) && $_GET['cacheOnly'] === '1';
    $dictReadTypes = ['countries', 'dates', 'meals', 'regions', 'hotel-services', 'promo-cache-index', 'tour-hots'];
    $isCacheProbe = $type === 'search-cached' && $searchCacheOnly;
    $isDictRead = in_array($type, $dictReadTypes, true);
    $isLightRead = $isCacheProbe || $isDictRead;

    if (!$isLightRead && in_array($type, ['search', 'search-cached'], true) && security_rate_limit_exceeded('tourvisor_search', 150, 60)) {
        $ratePayload = ['success' => false, 'error' => 'Слишком много запросов. Подождите минуту.'];
        if (TH_TOURVISOR_PROXY_EMBED) {
            return $ratePayload;
        }
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        http_response_code(429);
        echo json_encode($ratePayload, JSON_UNESCAPED_UNICODE);
        exit;
    }
    $guardPayload = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string)file_get_contents('php://input') : '';
    $guardKey = $isLightRead ? 'tourvisor_proxy_read' : 'tourvisor_proxy_api';
    $guardRpm = $isLightRead ? 600 : 300;
    $guard = security_guard_public_api($guardKey, $guardPayload, $guardRpm, 65536);
    if (empty($guard['ok'])) {
        if (TH_TOURVISOR_PROXY_EMBED) {
            return ['success' => false, 'error' => (string)($guard['error'] ?? 'Bad request'), 'data' => []];
        }
        header('Content-Type: application/json; charset=utf-8');
        http_response_code((int)($guard['code'] ?? 400));
        echo json_encode(['success' => false, 'error' => (string)($guard['error'] ?? 'Bad request')], JSON_UNESCAPED_UNICODE);
        tvLog('request_blocked', ['reason' => $guard['reason'] ?? 'unknown', 'type' => $type]);
        exit;
    }

    $r = ['success' => false, 'error' => 'Server error', 'data' => null];
    try {
        $logKeys = ['type', 'departureId', 'countryId', 'regionIds', 'arrivalId', 'searchId', 'dateFrom', 'dateTo', 'nightsFrom', 'nightsTo', 'adults', 'childs', 'meal', 'regionId', 'hotelCategory'];
        tvLog('incoming', ['type' => $type, 'GET' => array_intersect_key($_GET, array_flip($logKeys))]);
        $countryId = isset($_GET['countryId']) ? (int) $_GET['countryId'] : null;
        $regionId = isset($_GET['regionId']) ? (int) $_GET['regionId'] : null;
        $arrivalId = isset($_GET['arrivalId']) ? (int) $_GET['arrivalId'] : null;
        $onlyCharter = isset($_GET['onlyCharter']) && $_GET['onlyCharter'] === '1';
        $onlyDirect = isset($_GET['onlyDirect']) && $_GET['onlyDirect'] === '1';

        $r = ['success' => false, 'error' => 'Unknown type'];
        $GLOBALS['tv_cache_hit'] = null;
        if ($GLOBALS['tv_firestore_project_id'] === null || $GLOBALS['tv_firestore_project_id'] === '') {
            $GLOBALS['tv_firestore_used'] = 'off';
        }

        switch ($type) {
    case 'promo-cache-index':
        require_once __DIR__ . '/../promo_speed_cache.php';
        $indexData = th_promo_speed_index_get(true);
        th_promo_speed_log('promo_cache_index_read', ['departures' => count($indexData)]);
        $r = [
            'success' => true,
            'data' => $indexData,
            'fromCache' => true,
        ];
        break;
    case 'tour-hots':
        require_once __DIR__ . '/../tourvisor_hots.php';
        $hotsCntId = (int) ($_GET['countryId'] ?? 0);
        $hotsDepId = th_departure_id_from_request();
        $hotsDates = th_promo_speed_promo_dates($hotsCntId > 0 ? $hotsCntId : 4);
        if (trim((string) ($_GET['dateFrom'] ?? '')) !== '') {
            $hotsDates['dateFrom'] = trim((string) $_GET['dateFrom']);
        }
        if (trim((string) ($_GET['dateTo'] ?? '')) !== '') {
            $hotsDates['dateTo'] = trim((string) $_GET['dateTo']);
        }
        if ($hotsCntId <= 0) {
            $r = ['success' => false, 'error' => 'countryId required', 'data' => []];
            break;
        }
        $hotsLimit = min(200, max(1, (int) ($_GET['limit'] ?? 200)));
        $hotsAdults = max(1, min(9, (int) ($_GET['adults'] ?? 2)));
        $hotsRes = th_tourvisor_hots_fetch_hotels(
            $hotsDepId,
            $hotsCntId,
            $hotsDates['dateFrom'],
            $hotsDates['dateTo'],
            $hotsLimit,
            $hotsAdults
        );
        header('X-Tourvisor-Promo-Source: tour_hots_api');
        $r = [
            'success' => !empty($hotsRes['success']),
            'data' => $hotsRes['data'] ?? [],
            'fromCache' => false,
            'promoSearchSource' => 'tour_hots_api',
            'hotsCount' => $hotsRes['hotsCount'] ?? 0,
        ];
        if (!empty($hotsRes['error'])) {
            $r['error'] = $hotsRes['error'];
        }
        break;
    case 'promo-search':
        require_once __DIR__ . '/../promo_speed_cache.php';
        require_once __DIR__ . '/../promo_sochi_filter.php';
        require_once __DIR__ . '/../tourvisor_hots.php';
        $promoCntId = (int) ($_GET['countryId'] ?? 0);
        $promoDepId = th_departure_id_from_request();
        $promoBypassFile = (isset($_GET['live']) && $_GET['live'] === '1')
            || (isset($_GET['bypassCache']) && $_GET['bypassCache'] === '1');
        $promoAdults = max(1, min(9, (int) ($_GET['adults'] ?? 2)));
        $promoDates = th_promo_speed_promo_dates($promoCntId);
        $promoDateFrom = trim((string) ($_GET['dateFrom'] ?? ''));
        $promoDateTo = trim((string) ($_GET['dateTo'] ?? ''));
        if ($promoDateFrom !== '') {
            $promoDates['dateFrom'] = $promoDateFrom;
        }
        if ($promoDateTo !== '') {
            $promoDates['dateTo'] = $promoDateTo;
        }
        th_promo_speed_log('promo_search_start', [
            'countryId' => $promoCntId,
            'departureId' => $promoDepId,
            'bypass_file' => $promoBypassFile,
        ]);
        if ($promoCntId <= 0) {
            $r = ['success' => false, 'error' => 'countryId required', 'data' => []];
            break;
        }
        $promoCacheOnly = isset($_GET['cacheOnly']) && $_GET['cacheOnly'] === '1';
        $promoChilds = (isset($_GET['childs']) && trim((string) $_GET['childs']) !== '')
            ? trim((string) $_GET['childs'])
            : null;
        $promoDispatch = static fn(array $params): array => tourvisor_proxy_dispatch_get($params);
        $hybrid = th_promo_speed_promo_search_hybrid(
            $promoCntId,
            $promoDepId,
            $promoDates,
            $promoAdults,
            $promoChilds,
            $promoDispatch,
            $promoBypassFile,
            $promoCacheOnly
        );
        $promoLiveHotels = $hybrid['hotels'];
        $promoSource = (string) ($hybrid['source'] ?? 'promo_search_live');
        $fromCache = !empty($hybrid['fromCache']);
        $promoHotsFallbackCountries = [1, 4, 13];
        if (
            $promoLiveHotels !== []
            || $promoCacheOnly
            || $promoBypassFile
            || !in_array($promoCntId, $promoHotsFallbackCountries, true)
        ) {
            if ($promoLiveHotels !== []) {
                th_promo_speed_index_update_country($promoDepId, $promoCntId, $promoLiveHotels);
            }
            header('X-Tourvisor-Search-Mode: ' . ($fromCache ? 'cache' : 'live'));
            header('X-Tourvisor-Promo-Source: ' . $promoSource);
            $GLOBALS['tv_cache_hit'] = $fromCache;
            $r = [
                'success' => true,
                'data' => $promoLiveHotels,
                'fromCache' => $fromCache,
                'promoSearchSource' => $promoSource,
                'flightsByTourId' => is_array($hybrid['flightsByTourId'] ?? null) ? $hybrid['flightsByTourId'] : [],
            ];
            if ($promoLiveHotels === []) {
                $r['empty'] = true;
            }
            break;
        }
        /* Турция/Египет: если hybrid+live пуст — fallback /tours/hots (как раньше). */
        $promoBuildFileResponse = static function (array $promoFile, bool $stale) use (
            $promoCntId,
            $promoDepId,
            $promoDates,
            $promoAdults,
            $promoChilds,
            $promoDispatch
        ): array {
            $beforeBoost = th_promo_filter_hotels_min_nights(
                th_promo_filter_hotels_for_promo_country(
                    is_array($promoFile['results'] ?? null) ? $promoFile['results'] : [],
                    $promoCntId
                ),
                $promoCntId
            );
            $hotelsFromFile = th_promo_speed_hotels_from_cache_payload(
                $promoFile,
                $promoCntId,
                $promoDepId,
                $promoDates,
                $promoDispatch,
                $promoAdults,
                $promoChilds
            );
            if (
                !in_array($promoCntId, th_promo_speed_nearest_fallback_country_ids(), true)
                && th_promo_speed_tr_eg_needs_star_boost($beforeBoost, $promoCntId)
                && count($hotelsFromFile) > count($beforeBoost)
            ) {
                th_promo_speed_log('promo_search_file_star_boost', [
                    'countryId' => $promoCntId,
                    'departureId' => $promoDepId,
                    'before' => count($beforeBoost),
                    'after' => count($hotelsFromFile),
                    'five_star' => th_promo_speed_count_hotels_in_star_range($hotelsFromFile, 5, 5),
                ]);
            }
            th_promo_speed_log($stale ? 'promo_search_file_stale' : 'promo_search_file_hit', [
                'countryId' => $promoCntId,
                'departureId' => $promoDepId,
                'fileDepartureId' => (int) ($promoFile['departureId'] ?? 0),
                'hotels' => count($hotelsFromFile),
                'stale' => $stale,
            ]);
            header('X-Tourvisor-Search-Mode: cache');
            header('X-Tourvisor-Promo-Source: ' . ($stale ? 'promo_speed_file_stale' : 'promo_speed_file'));
            $GLOBALS['tv_cache_hit'] = true;

            return [
                'success' => count($hotelsFromFile) > 0,
                'data' => $hotelsFromFile,
                'fromCache' => true,
                'promoSearchSource' => $stale ? 'promo_speed_file_stale' : 'promo_speed_file',
                'flightsByTourId' => th_promo_speed_flights_from_cache_payload($promoFile),
            ];
        };
        if (!$promoBypassFile) {
            $promoFile = th_promo_speed_cache_get_best($promoCntId, $promoDepId, false);
            if ($promoFile !== null) {
                $r = $promoBuildFileResponse($promoFile, false);
                break;
            }
            // Stale файл лучше медленного /tours/hots — и для cacheOnly, и для обычного запроса.
            $promoStaleEarly = th_promo_speed_cache_get_best($promoCntId, $promoDepId, true);
            if ($promoStaleEarly !== null) {
                $r = $promoBuildFileResponse($promoStaleEarly, true);
                break;
            }
            if ($promoCacheOnly) {
                th_promo_speed_log('promo_search_cache_only_miss', [
                    'countryId' => $promoCntId,
                    'departureId' => $promoDepId,
                ]);
                header('X-Tourvisor-Search-Mode: cache');
                header('X-Tourvisor-Promo-Source: promo_cache_miss');
                $GLOBALS['tv_cache_hit'] = true;
                $r = [
                    'success' => true,
                    'data' => [],
                    'fromCache' => true,
                    'promoSearchSource' => 'promo_cache_miss',
                ];
                break;
            }
        }
        $hotsRes = th_tourvisor_hots_fetch_hotels(
            $promoDepId,
            $promoCntId,
            $promoDates['dateFrom'],
            $promoDates['dateTo'],
            200,
            $promoAdults
        );
        $promoArrays = [];
        if (!empty($hotsRes['success']) && is_array($hotsRes['data'] ?? null) && $hotsRes['data'] !== []) {
            $promoArrays[] = $hotsRes['data'];
            th_promo_speed_log('promo_search_hots_ok', [
                'countryId' => $promoCntId,
                'departureId' => $promoDepId,
                'hots_rows' => $hotsRes['hotsCount'] ?? 0,
                'hotels' => count($hotsRes['data']),
            ]);
        } else {
            th_promo_speed_log('promo_search_hots_miss', [
                'countryId' => $promoCntId,
                'departureId' => $promoDepId,
                'error' => $hotsRes['error'] ?? 'empty',
                'hots_rows' => $hotsRes['hotsCount'] ?? 0,
            ]);
        }
        $promoMerged = th_promo_speed_merge_hotels($promoArrays, $promoCntId);
        $promoMerged = th_promo_speed_finalize_merged(
            $promoMerged,
            $promoCntId,
            $promoDepId,
            $promoDates,
            $promoAdults,
            $promoChilds,
            $promoDispatch
        );
        $promoForResponse = th_promo_filter_hotels_min_nights($promoMerged, $promoCntId);
        if ($promoForResponse !== []) {
            th_promo_speed_cache_set($promoCntId, $promoDepId, $promoMerged, $promoDates);
            th_promo_speed_index_update_country($promoDepId, $promoCntId, $promoForResponse);
        }
        th_promo_speed_log('promo_search_live_merged', [
            'countryId' => $promoCntId,
            'departureId' => $promoDepId,
            'hotels' => count($promoForResponse),
            'cached_hotels' => count($promoMerged),
            'cached_written' => $promoForResponse !== [],
        ]);
        if ($promoForResponse === [] && !$promoBypassFile) {
            $promoStale = th_promo_speed_cache_get_best($promoCntId, $promoDepId, true);
            if ($promoStale !== null) {
                $r = $promoBuildFileResponse($promoStale, true);
                break;
            }
        }
        header('X-Tourvisor-Search-Mode: live');
        header('X-Tourvisor-Promo-Source: tour_hots_api');
        $GLOBALS['tv_cache_hit'] = false;
        $r = [
            'success' => count($promoForResponse) > 0,
            'data' => $promoForResponse,
            'fromCache' => false,
            'promoSearchSource' => 'tour_hots_api',
        ];
        if (!empty($hotsRes['error']) && count($promoForResponse) === 0) {
            $r['error'] = $hotsRes['error'];
            $r['success'] = false;
        }
        break;
    case 'departures':
        $params = isset($_GET['departureCountryId']) ? ['departureCountryId' => (int)$_GET['departureCountryId']] : [];
        $r = tvCached('departures', $params, function() use ($params) {
            $r = tvRequest('/departures', $params);
            if ((!$r['success'] || empty($r['data'])) && tv_dictionary_use_fallback()) {
                return ['success' => true, 'data' => [
                    ['id' => 7, 'name' => 'Самара'],
                    ['id' => 1, 'name' => 'Москва'],
                    ['id' => 2, 'name' => 'Санкт-Петербург'],
                    ['id' => 3, 'name' => 'Екатеринбург'],
                    ['id' => 4, 'name' => 'Уфа'],
                ]];
            }
            return $r;
        });
        if (!empty($r['data']) && is_array($r['data'])) {
            $r['data'] = th_departure_filter_list($r['data']);
        }
        break;
    case 'countries':
        // Без departureId кэш/API часто отдают урезанный список (~4 страны).
        // Всегда привязываем к городу вылета (дефолт сайта).
        if (!$departureId) {
            $departureId = function_exists('th_departure_default_id') ? th_departure_default_id() : 7;
        }
        $params = ['onlyCharter' => (bool)$onlyCharter, 'departureId' => $departureId];
        $r = tvCached('countries', $params, function() use ($params) {
            $res = tvRequest('/countries', $params);
            if ((!$res['success'] || empty($res['data'])) && tv_dictionary_use_fallback()) {
                tvLog('countries_fallback', ['error' => $res['error'] ?? 'unknown']);
                $fallback = tvLoadCountriesFallback();
                return ['success' => true, 'data' => $fallback ?? [['id' => 12, 'name' => 'Турция'], ['id' => 13, 'name' => 'Египет'], ['id' => 14, 'name' => 'ОАЭ'], ['id' => 15, 'name' => 'Таиланд'], ['id' => 16, 'name' => 'Мальдивы']]];
            }
            $data = $res['data'] ?? null;
            if ((!is_array($data) || empty($data)) && tv_dictionary_use_fallback()) {
                tvLog('countries_invalid', ['data_type' => gettype($data)]);
                $fallback = tvLoadCountriesFallback();
                return ['success' => true, 'data' => $fallback ?? [['id' => 12, 'name' => 'Турция'], ['id' => 13, 'name' => 'Египет'], ['id' => 14, 'name' => 'ОАЭ'], ['id' => 15, 'name' => 'Таиланд'], ['id' => 16, 'name' => 'Мальдивы']]];
            }
            return $res;
        });
        break;
    case 'arrivals':
        if (!$departureId) {
            $r = ['success' => false, 'error' => 'departureId required'];
        } else {
            $params = ['departureId' => $departureId, 'onlyCharter' => (bool)$onlyCharter];
            $r = tvCached('arrivals', $params, function() use ($params) { return tvRequest('/arrivals', $params); });
        }
        break;
    case 'regions':
        $params = [];
        if ($countryId) $params['countryId'] = $countryId;
        if ($arrivalId) $params['arrivalId'] = $arrivalId;
        $r = tvCached('regions', $params, function() use ($params, $countryId) {
            return tvFetchRegionsResolved($params, $countryId);
        });
        break;
    case 'meals':
        $r = tvCached('meals', [], function() {
            $res = tvRequest('/meals');
            if ((!$res['success'] || empty($res['data'])) && tv_dictionary_use_fallback()) {
                return ['success' => true, 'data' => [
                    ['id' => 1, 'russianName' => 'Без питания'],
                    ['id' => 2, 'russianName' => 'Завтраки'],
                    ['id' => 3, 'russianName' => 'Полупансион'],
                    ['id' => 4, 'russianName' => 'Полный пансион'],
                    ['id' => 5, 'russianName' => 'Все включено'],
                    ['id' => 6, 'russianName' => 'Ультра все включено'],
                ]];
            }
            return $res;
        });
        break;
    case 'hotel-services':
        $hsParams = [];
        if ($countryId) $hsParams['countryId'] = $countryId;
        $rids = $_GET['regionIds'] ?? '';
        if ($rids !== '') $hsParams['regionIds'] = array_map('intval', array_filter(explode(',', $rids)));
        $r = tvCached('hotel-services', $hsParams, function() use ($hsParams) { return tvRequest('/hotel-group-services', $hsParams); });
        break;
    case 'hotel-types':
        if (!$countryId) {
            $r = ['success' => false, 'error' => 'countryId required'];
        } else {
            $r = tvCached('hotel-types', ['countryId' => $countryId], function() use ($countryId) { return tvRequest('/hotel-types', ['countryId' => $countryId]); });
        }
        break;
    case 'currencies':
        $r = tvCached('currencies', [], function() { return tvRequest('/currencies'); });
        break;
    case 'dates':
        if (!$departureId || !$countryId) {
            $r = ['success' => false, 'error' => 'departureId and countryId required'];
        } else {
            // onlyDirect на /tours/dates Tourvisor отклоняет (schema: invalid path) — не слать.
            // Для подсветки прямых дат клиент дергает search-cached&onlyDirect и/или tour-flights.
            $params = ['departureId' => $departureId, 'countryId' => $countryId, 'onlyCharter' => (bool)$onlyCharter];
            if ($arrivalId) $params['arrivalId'] = $arrivalId;
            if ($onlyDirect) {
                $r = [
                    'success' => false,
                    'error' => 'onlyDirect is not supported on /tours/dates',
                    'data' => [],
                    'directViaDatesUnsupported' => true,
                ];
            } else {
                $r = tvCached('dates', $params, function() use ($params) {
                    $res = tvRequest('/tours/dates', $params);
                    if (!$res['success'] && $GLOBALS['tv_use_fallbacks']) {
                        return ['success' => true, 'data' => [date('Y-m-d', strtotime('+7 days'))]];
                    }
                    return $res;
                });
            }
        }
        break;
    case 'tour-flights':
        $tourId = isset($_GET['tourId']) ? trim((string)$_GET['tourId']) : '';
        $currency = isset($_GET['currency']) ? trim((string)$_GET['currency']) : 'RUB';
        if ($tourId === '') {
            $r = ['success' => false, 'error' => 'tourId required', 'data' => null];
        } else {
            $ttl = (int)(getenv('TOURVISOR_TOUR_LIVE_CACHE_TTL_SECONDS') ?: ($_ENV['TOURVISOR_TOUR_LIVE_CACHE_TTL_SECONDS'] ?? 300));
            $ttl = min(max($ttl, 60), 900);
            $r = tvCachedShort('tour-flights', ['tourId' => $tourId, 'currency' => $currency], $ttl, function () use ($tourId, $currency) {
                $res = tvRequest('/tours/' . $tourId . '/flights', ['currency' => $currency]);
                if ($res['success'] && isset($res['data'])) {
                    $flights = $res['data']['flights'] ?? [];
                    $err = $res['data']['error'] ?? null;
                    /* success + пустые flights + error.code=3 — ТО не ответил (часто Paks/SL).
                       Кладём как failure, иначе клиент вечно «Загружаем перелёт…». */
                    if ((!is_array($flights) || $flights === []) && $err !== null && $err !== '') {
                        $reason = 'no flights';
                        if (is_array($err)) {
                            $reason = trim((string) ($err['reason'] ?? $err['message'] ?? $err['code'] ?? $reason));
                        } else {
                            $reason = trim((string) $err);
                        }
                        if ($reason === '') {
                            $reason = 'no flights';
                        }

                        return [
                            'success' => false,
                            'error' => $reason,
                            'flights' => [],
                            'operatorNoFlights' => true,
                            'data' => $res['data'],
                        ];
                    }

                    return [
                        'success' => true,
                        'flights' => is_array($flights) ? $flights : [],
                        'info' => $res['data']['info'] ?? null,
                        'error' => $err,
                        'data' => $res['data'],
                    ];
                }
                return ['success' => false, 'error' => $res['error'] ?? 'Ошибка запроса авиарейсов', 'data' => null];
            }, true);
        }
        break;
    case 'tour':
        $tourId = isset($_GET['tourId']) ? trim((string)$_GET['tourId']) : '';
        $currency = isset($_GET['currency']) ? trim((string)$_GET['currency']) : 'RUB';
        if ($tourId === '') {
            $r = ['success' => false, 'error' => 'tourId required', 'data' => null];
        } else {
            $ttl = (int)(getenv('TOURVISOR_TOUR_LIVE_CACHE_TTL_SECONDS') ?: ($_ENV['TOURVISOR_TOUR_LIVE_CACHE_TTL_SECONDS'] ?? 60));
            $ttl = min(max($ttl, 0), 600);
            $r = tvCachedShort('tour', ['tourId' => $tourId, 'currency' => $currency], $ttl, function() use ($tourId, $currency) {
                $res = tvRequest('/tours/' . $tourId, ['currency' => $currency]);
                if ($res['success'] && isset($res['data'])) {
                    return ['success' => true, 'data' => $res['data']];
                }
                return ['success' => false, 'error' => $res['error'] ?? 'Ошибка запроса тура', 'data' => null];
            });
        }
        break;
    case 'hotels':
        // Каталог отелей Tourvisor: GET /hotels (без цен). Для витрины «Популярные отели».
        require_once dirname(__DIR__) . '/promo_virtual_destinations.php';
        $hotelsCountryRaw = isset($_GET['countryId']) ? (int) $_GET['countryId'] : 0;
        if ($hotelsCountryRaw <= 0) {
            $r = ['success' => false, 'error' => 'countryId required', 'data' => null];
            break;
        }
        $hotelsCountryId = th_promo_resolve_tv_country_id($hotelsCountryRaw);
        $hotelsRegionId = isset($_GET['regionId']) && $_GET['regionId'] !== '' ? (int) $_GET['regionId'] : 0;
        if ($hotelsRegionId <= 0) {
            $virtRegions = th_promo_virtual_region_ids($hotelsCountryRaw);
            if (!empty($virtRegions[0])) {
                $hotelsRegionId = (int) $virtRegions[0];
            }
        }
        $hotelsCategory = isset($_GET['category']) && $_GET['category'] !== '' ? (int) $_GET['category'] : 0;
        $hotelsRating = isset($_GET['rating']) && $_GET['rating'] !== '' ? (float) $_GET['rating'] : 0.0;
        $hotelsPage = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $hotelsLimit = isset($_GET['limit']) ? (int) $_GET['limit'] : 24;
        if ($hotelsLimit < 1 || $hotelsLimit > 100) {
            $hotelsLimit = 24;
        }
        $hotelsEnrich = !empty($_GET['enrich']) && (string) $_GET['enrich'] !== '0';
        $hotelsParams = [
            'countryId' => $hotelsCountryId,
            'page' => $hotelsPage,
            'limit' => $hotelsLimit,
        ];
        if ($hotelsRegionId > 0) {
            $hotelsParams['regionId'] = $hotelsRegionId;
        }
        if ($hotelsCategory > 0) {
            $hotelsParams['category'] = $hotelsCategory;
        }
        if ($hotelsRating > 0) {
            $hotelsParams['rating'] = $hotelsRating;
        }
        $r = tvCached('hotels_catalog', $hotelsParams, function () use ($hotelsParams) {
            return tvRequest('/hotels', $hotelsParams);
        });
        if (!empty($r['success']) && is_array($r['data'] ?? null)) {
            $list = $r['data'];
            if (isset($list['data']) && is_array($list['data'])) {
                $list = $list['data'];
            }
            if (!is_array($list)) {
                $list = [];
            }
            $isList = $list === [] || array_keys($list) === range(0, count($list) - 1);
            if (!$isList) {
                $list = [];
            }
            $compact = [];
            foreach ($list as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $hid = (int) ($row['id'] ?? 0);
                if ($hid <= 0) {
                    continue;
                }
                $country = is_array($row['country'] ?? null) ? $row['country'] : [];
                $region = is_array($row['region'] ?? null) ? $row['region'] : [];
                $compact[] = [
                    'id' => $hid,
                    'name' => (string) ($row['name'] ?? ''),
                    'category' => (int) ($row['category'] ?? 0),
                    'rating' => (float) ($row['rating'] ?? 0),
                    'countryId' => (int) ($country['id'] ?? $hotelsCountryId),
                    'countryName' => (string) ($country['name'] ?? ''),
                    'regionId' => (int) ($region['id'] ?? 0),
                    'regionName' => (string) ($region['name'] ?? ''),
                    'picturelink' => (string) ($row['picturelink'] ?? ''),
                    'promoCountryId' => $hotelsCountryRaw,
                ];
            }
            if ($hotelsEnrich && $compact !== []) {
                $enrichN = min(12, count($compact));
                for ($ei = 0; $ei < $enrichN; $ei++) {
                    $eid = (int) $compact[$ei]['id'];
                    $det = tvCached('hotel', ['hotelId' => $eid], function () use ($eid) {
                        $res = tvRequest('/hotels/' . $eid, []);
                        if (!$res['success']) {
                            return $res;
                        }
                        $data = $res['data'];
                        if (is_array($data) && isset($data[0])) {
                            $data = $data[0];
                        }
                        return ['success' => true, 'data' => $data];
                    });
                    if (empty($det['success']) || !is_array($det['data'] ?? null)) {
                        continue;
                    }
                    $dh = $det['data'];
                    $imgs = is_array($dh['images'] ?? null) ? $dh['images'] : [];
                    $pic = '';
                    if (!empty($dh['picturelink']) && is_string($dh['picturelink'])) {
                        $pic = trim($dh['picturelink']);
                    } elseif (!empty($imgs[0]) && is_string($imgs[0])) {
                        $pic = trim($imgs[0]);
                    }
                    if ($pic !== '') {
                        if (strpos($pic, '//') === 0) {
                            $pic = 'https:' . $pic;
                        }
                        $compact[$ei]['picturelink'] = $pic;
                    }
                    if (isset($dh['rating']) && (float) $dh['rating'] > 0) {
                        $compact[$ei]['rating'] = (float) $dh['rating'];
                    }
                    $desc = trim(strip_tags((string) ($dh['common']['description'] ?? '')));
                    if ($desc !== '') {
                        $compact[$ei]['descriptionSnippet'] = mb_substr($desc, 0, 160);
                    }
                    usleep(40000);
                }
            }
            // Сортировка на нашей стороне (API не всегда сортирует): rating|stars|name
            $sort = strtolower(trim((string) ($_GET['sort'] ?? 'rating')));
            if ($sort === 'stars' || $sort === 'category') {
                usort($compact, static function ($a, $b) {
                    $c = ((int) ($b['category'] ?? 0)) <=> ((int) ($a['category'] ?? 0));
                    return $c !== 0 ? $c : (((float) ($b['rating'] ?? 0)) <=> ((float) ($a['rating'] ?? 0)));
                });
            } elseif ($sort === 'name') {
                usort($compact, static function ($a, $b) {
                    return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
                });
            } else {
                // rating default
                usort($compact, static function ($a, $b) {
                    $c = ((float) ($b['rating'] ?? 0)) <=> ((float) ($a['rating'] ?? 0));
                    return $c !== 0 ? $c : (((int) ($b['category'] ?? 0)) <=> ((int) ($a['category'] ?? 0)));
                });
            }
            $r = [
                'success' => true,
                'data' => [
                    'hotels' => $compact,
                    'page' => $hotelsPage,
                    'limit' => $hotelsLimit,
                    'countryId' => $hotelsCountryId,
                    'promoCountryId' => $hotelsCountryRaw,
                    'regionId' => $hotelsRegionId > 0 ? $hotelsRegionId : null,
                    'enriched' => $hotelsEnrich,
                    'sort' => $sort,
                ],
            ];
        }
        break;
    case 'hotel':
        $hotelId = isset($_GET['hotelId']) ? (int)$_GET['hotelId'] : 0;
        if ($hotelId <= 0) {
            $r = ['success' => false, 'error' => 'hotelId required', 'data' => null];
        } else {
            $ttlHotel = (int)($GLOBALS['tv_dictionary_cache_ttl'] ?? (720 * 3600));
            $r = tvCached('hotel', ['hotelId' => $hotelId], function() use ($hotelId) {
                $res = tvRequest('/hotels/' . $hotelId, []);
                if (!$res['success']) return $res;
                $data = $res['data'];
                if (is_array($data) && isset($data[0])) $data = $data[0];
                return ['success' => true, 'data' => $data];
            });
        }
        break;
    case 'search':
        $dateFrom = $_GET['dateFrom'] ?? date('Y-m-d');
        $dateTo = $_GET['dateTo'] ?? date('Y-m-d', strtotime('+14 days'));
        // TourVisor: dateTo - dateFrom ограничен (часто 14 дней). Ограничиваем 14 днями
        $tFrom = strtotime($dateFrom);
        $tTo = strtotime($dateTo);
        if ($tFrom !== false && $tTo !== false) {
            $diff = (int)(($tTo - $tFrom) / 86400);
            if ($diff < 0) $dateTo = $dateFrom;
            elseif ($diff > 14) $dateTo = date('Y-m-d', $tFrom + 14 * 86400);
        }
        $searchParams = [
            'departureId' => (int)($_GET['departureId'] ?? th_departure_default_id()),
            'countryId' => (int)($_GET['countryId'] ?? 12),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'nightsFrom' => (int)($_GET['nightsFrom'] ?? 6),
            'nightsTo' => (int)($_GET['nightsTo'] ?? 9),
            'adults' => (int)($_GET['adults'] ?? 2),
            'currency' => $_GET['currency'] ?? 'RUB',
            'onlyCharter' => !empty($_GET['onlyCharter']) && $_GET['onlyCharter'] !== '0',
            'onlyDirect' => !empty($_GET['onlyDirect']) && $_GET['onlyDirect'] !== '0',
        ];
        if (empty($searchParams['onlyDirect'])) {
            unset($searchParams['onlyDirect']);
        }
        $childs = $_GET['childs'] ?? '';
        if ($childs !== '') {
            $ages = tvParseChildAges($childs);
            if ($ages !== []) {
                $searchParams['childs'] = $ages;
            }
        }
        if (!empty($_GET['meal'])) $searchParams['meal'] = (int)$_GET['meal'];
        if (!empty($_GET['hotelCategory'])) $searchParams['hotelCategory'] = (int)$_GET['hotelCategory'];
        if (!empty($_GET['arrivalId'])) $searchParams['arrivalId'] = (int)$_GET['arrivalId'];
        $regionIds = $_GET['regionIds'] ?? '';
        if ($regionIds !== '') {
            $searchParams['regionIds'] = array_map('intval', array_filter(explode(',', $regionIds)));
        }
        $hotelIds = $_GET['hotelIds'] ?? '';
        if ($hotelIds !== '') {
            $searchParams['hotelIds'] = array_values(array_filter(array_map('intval', explode(',', (string) $hotelIds))));
        }
        $hotelServices = $_GET['hotelServices'] ?? '';
        if ($hotelServices !== '') {
            $searchParams['hotelServices'] = array_map('intval', array_filter(explode(',', $hotelServices)));
        }
        [$apiNFrom, $apiNTo, $nightsExpanded] = tvNightsForTourvisorApi(
            (int) $searchParams['nightsFrom'],
            (int) $searchParams['nightsTo']
        );
        if ($nightsExpanded) {
            tvLog('search_nights_expanded', [
                'user' => [(int) $searchParams['nightsFrom'], (int) $searchParams['nightsTo']],
                'api' => [$apiNFrom, $apiNTo],
            ]);
            header('X-Tourvisor-Nights-Expanded: 1');
        }
        $searchParams['nightsFrom'] = $apiNFrom;
        $searchParams['nightsTo'] = $apiNTo;
        tvLog('search_start', ['params' => $searchParams, 'GET' => array_intersect_key($_GET, array_flip(['departureId', 'countryId', 'dateFrom', 'dateTo', 'nightsFrom', 'nightsTo', 'adults', 'childs', 'meal', 'regionIds', 'hotelIds', 'hotelCategory', 'hotelServices']))]);
        $r = tvRequest('/tours/search', $searchParams);
        if ($r['success'] && is_array($r['data']) && isset($r['data']['error'])) {
            $err = $r['data']['error'];
            $r = ['success' => false, 'error' => is_array($err) ? ($err['reason'] ?? json_encode($err)) : (string)$err];
        }
        if ($r['success'] && isset($r['data']['searchId'])) {
            $sid = (int)$r['data']['searchId'];
            $paramsToSave = $searchParams;
            $promoTileSave = (int) ($_GET['promoTileId'] ?? 0);
            if ($promoTileSave > 0) {
                $paramsToSave['promoTileId'] = $promoTileSave;
            }
            tvSaveSearchParams($sid, $paramsToSave);
            tvLog('search_success', ['searchId' => $sid, 'params' => $paramsToSave]);
            header('X-Tourvisor-Type: search');
            header('X-Tourvisor-Success: yes');
            header('X-Tourvisor-Token: ' . (strlen(trim((string)(getenv('TOURVISOR_TOKEN') ?: ($_ENV['TOURVISOR_TOKEN'] ?? '')))) > 10 ? 'ok' : 'missing'));
            header('X-Tourvisor-Cache: n/a');
            $searchPayload = ['success' => true, 'searchId' => $sid];
            if (TH_TOURVISOR_PROXY_EMBED) {
                return $searchPayload;
            }
            echo json_encode($searchPayload, JSON_UNESCAPED_UNICODE);
            exit;
        }
        tvLog('search_fail', ['error' => $r['error'] ?? $r, 'params' => $searchParams, 'api_response' => $r['data'] ?? null]);
        break;
    case 'status':
        $searchId = (int)($_GET['searchId'] ?? 0);
        if (!$searchId) {
            $r = ['success' => false, 'error' => 'searchId required'];
            tvLog('status_error', ['reason' => 'searchId required']);
        } else {
            $opStatus = isset($_GET['operatorStatus']) ? filter_var($_GET['operatorStatus'], FILTER_VALIDATE_BOOLEAN) : false;
            $r = tvRequest("/tours/search/{$searchId}/status", ['operatorStatus' => $opStatus]);
            tvLog('status', ['searchId' => $searchId, 'success' => !empty($r['success']), 'progress' => $r['data']['progress'] ?? null, 'status' => $r['data']['status'] ?? null, 'minPrice' => $r['data']['minPrice'] ?? null]);
        }
        break;
    case 'results':
        $searchId = (int)($_GET['searchId'] ?? 0);
        // API поддерживает только limit (Default: 25), offset нет. Максимум не указан в доке — запрашиваем до 1000.
        $limit = min(max((int)($_GET['limit'] ?? 25), 1), 1000);
        // persist=0 — частичная выдача для early paint, не затираем кэш неполными данными
        $persistResults = !(isset($_GET['persist']) && $_GET['persist'] === '0');
        if (!$searchId) {
            $r = ['success' => false, 'error' => 'searchId required'];
            tvLog('results_error', ['reason' => 'searchId required']);
        } else {
            $r = tvRequest("/tours/search/{$searchId}", ['limit' => $limit]);
            $cnt = is_array($r['data'] ?? null) ? count($r['data']) : 0;
            if ($persistResults && $r['success'] && is_array($r['data']) && $cnt > 0) {
                $params = tvLoadSearchParams($searchId);
                if ($params !== null) {
                    $ck = tvSearchParamsKey($params, 25, 'main');
                    // Тяжёлый persist — после ответа клиенту
                    tvQueuePersistAfterResponse([
                        'ck' => $ck,
                        'liveData' => $r['data'],
                        'sp' => $params,
                        'ttlSearch' => (int) ($GLOBALS['tv_search_cache_ttl'] ?? 172800),
                        'onlyPromo' => false,
                        'searchId' => $searchId,
                    ]);
                    tvLog('results_cached_queued', ['searchId' => $searchId, 'cache_key' => $ck, 'tours_count' => $cnt]);
                }
            }
            tvLog('results', [
                'searchId' => $searchId,
                'limit' => $limit,
                'persist' => $persistResults,
                'success' => !empty($r['success']),
                'tours_count' => $cnt,
            ]);
        }
        break;
    case 'search':
    case 'search-cached':
        $sp = [
            'departureId' => (int) ($_GET['departureId'] ?? $_GET['departure'] ?? 0),
            'countryId' => (int)($_GET['countryId'] ?? 0),
            'countryName' => isset($_GET['countryName']) ? trim((string)$_GET['countryName']) : '',
            'dateFrom' => trim((string)($_GET['dateFrom'] ?? '')),
            'dateTo' => trim((string)($_GET['dateTo'] ?? '')),
            'nightsFrom' => (int)($_GET['nightsFrom'] ?? 6),
            'nightsTo' => (int)($_GET['nightsTo'] ?? 9),
            'adults' => (int)($_GET['adults'] ?? 2),
        ];
        if (isset($_GET['childs'])) {
            $cRaw = trim((string) $_GET['childs']);
            if ($cRaw !== '') {
                $sp['childs'] = $cRaw;
            }
        }
        if (!empty($_GET['meal'])) $sp['meal'] = (int)$_GET['meal'];
        if (!empty($_GET['hotelCategory'])) $sp['hotelCategory'] = (int)$_GET['hotelCategory'];
        if (!empty($_GET['arrivalId'])) $sp['arrivalId'] = (int)$_GET['arrivalId'];
        $regionIds = $_GET['regionIds'] ?? '';
        if ($regionIds !== '') $sp['regionIds'] = $regionIds;
        $hotelIdsRaw = trim((string) ($_GET['hotelIds'] ?? ''));
        if ($hotelIdsRaw !== '') $sp['hotelIds'] = $hotelIdsRaw;
        if (!empty($_GET['hotelServices'])) $sp['hotelServices'] = $_GET['hotelServices'];
        $sp['onlyCharter'] = !empty($_GET['onlyCharter']) && $_GET['onlyCharter'] !== '0';
        if (!empty($_GET['onlyDirect']) && $_GET['onlyDirect'] !== '0') {
            $sp['onlyDirect'] = true;
        }
        header('X-Tourvisor-Dates: ' . $sp['dateFrom'] . ',' . $sp['dateTo']);
        $onlyPromo = isset($_GET['onlyPromo']) && $_GET['onlyPromo'] === '1';
        $internalRequest = isset($_GET['internal']) && $_GET['internal'] === '1';
        $cacheNamespace = tvSearchCacheNamespace($onlyPromo);
        header('X-Tourvisor-Cache-Namespace: ' . $cacheNamespace);
        $ck = tvSearchParamsKey($sp, 25, $cacheNamespace);
        $ttlSearch = tvSearchCacheTtlSeconds($onlyPromo);
        $projectId = $GLOBALS['tv_firestore_project_id'] ?? null;
        if (trim((string)($_GET['cacheScope'] ?? '')) === 'country_page') {
            header('X-Tourvisor-Cache-TTL: ' . (string) $ttlSearch);
        }

        // Акционные туры (onlyPromo=1): promo_cache_{countryId}, затем search-кэш; live=1 / bypass — только API.
        if ($onlyPromo && $sp['dateFrom'] !== '' && $sp['dateTo'] !== '') {
            require_once __DIR__ . '/../promo_sochi_filter.php';
            $promoCntId = (int) ($sp['countryId'] ?? 0);
            $promoDepId = (int) ($sp['departureId'] ?: th_departure_default_id());
            tvLog('promo_search_start', [
                'countryId' => $promoCntId,
                'departureId' => $promoDepId,
                'dateFrom' => $sp['dateFrom'],
                'dateTo' => $sp['dateTo'],
                'bypass_cache' => !empty($GLOBALS['tv_bypass_cache']),
            ]);
            // regionIds (Фукуок и др.) — не отдаём country-wide promo_cache без фильтра курорта
            $promoRegionIdsRaw = trim((string) ($_GET['regionIds'] ?? ''));
            $promoHasRegions = $promoRegionIdsRaw !== '';
            if (!$GLOBALS['tv_bypass_cache'] && !$promoHasRegions) {
            tv_promo_country_cache_load();
            if ($promoCntId > 0) {
                $promoFile = th_promo_cache_get($promoCntId, $promoDepId);
                if ($promoFile !== null && is_array($promoFile['results'])) {
                    $dataToReturn = th_promo_filter_hotels_for_promo_country($promoFile['results'], $promoCntId);
                    $promoTourIds = [];
                    foreach ($dataToReturn as $hotel) {
                        foreach (($hotel['tours'] ?? []) as $t) {
                            if (!empty($t['id'])) {
                                $promoTourIds[] = $t['id'];
                            }
                        }
                    }
                    $GLOBALS['tv_cache_hit'] = true;
                    header('X-Tourvisor-Search-Mode: cache');
                    header('X-Tourvisor-Cache-Read: promo_country');
                    header('X-Tourvisor-Promo-Source: promo_country_file');
                    tvLog('promo_search_source', [
                        'source' => 'promo_country_file',
                        'countryId' => $promoCntId,
                        'departureId' => $promoDepId,
                        'hotels' => count($dataToReturn),
                    ]);
                    $r = [
                        'success' => true,
                        'data' => $dataToReturn,
                        'fromCache' => true,
                        'promoSearchSource' => 'promo_country_file',
                    ];
                    if ($promoTourIds !== []) {
                        $r['promoTourIds'] = array_values(array_unique($promoTourIds));
                    }
                    break;
                }
            }
            $cached = tvGetSearchResultsCacheForScope($sp, 'promo', $ttlSearch);
            if (is_array($cached) && !empty($cached)) {
                $dataToReturn = [];
                $promoTourIds = [];
                foreach ($cached as $hotel) {
                    $tours = $hotel['tours'] ?? [];
                    $promoTours = array_filter($tours, static function ($t) {
                        if (!empty($t['isPromo'])) return true;
                        $name = (string)($t['name'] ?? '');
                        return $name !== '' && preg_match('/promo|промо|скидка/i', $name);
                    });
                    if (!empty($promoTours)) {
                        $hotel['tours'] = array_values($promoTours);
                        $dataToReturn[] = $hotel;
                        foreach ($promoTours as $t) {
                            if (!empty($t['id'])) $promoTourIds[] = $t['id'];
                        }
                    }
                }
                $dataToReturn = th_promo_filter_hotels_for_promo_country($dataToReturn, $promoCntId);
                $GLOBALS['tv_cache_hit'] = true;
                header('X-Tourvisor-Search-Mode: cache');
                header('X-Tourvisor-Cache-Read: promo');
                header('X-Tourvisor-Promo-Source: search_cache');
                tvLog('promo_search_source', [
                    'source' => 'search_cache',
                    'cache_key' => $ck,
                    'countryId' => $promoCntId,
                    'promo_hotels' => count($dataToReturn),
                ]);
                $r = [
                    'success' => true,
                    'data' => $dataToReturn,
                    'fromCache' => true,
                    'promoSearchSource' => 'search_cache',
                ];
                if (!empty($promoTourIds)) $r['promoTourIds'] = array_values(array_unique($promoTourIds));
                break;
            }
            } else {
                tvLog('promo_search_cache_skipped', [
                    'reason' => 'live_or_bypass',
                    'countryId' => $promoCntId,
                ]);
            }
        }

        // Полная выдача из файлового кэша (страницы стран: блок «Туры из …», search-cached без onlyPromo). При live=1 / bypassCache — пропуск.
        if ($type === 'search-cached' && !$onlyPromo && !$GLOBALS['tv_bypass_cache'] && $sp['dateFrom'] !== '' && $sp['dateTo'] !== '') {
            $cachedFull = tvGetSearchResultsCacheForScope($sp, 'main', $ttlSearch);
            if (is_array($cachedFull) && $cachedFull !== []) {
                $GLOBALS['tv_cache_hit'] = true;
                header('X-Tourvisor-Search-Mode: cache');
                header('X-Tourvisor-Cache-Read: exact');
                tvLog('search_cache_hit', ['cache_key' => $ck, 'hotels_count' => count($cachedFull), 'cache_kind' => 'search_cached_full']);
                $r = ['success' => true, 'data' => $cachedFull, 'fromCache' => true];
                break;
            }

            // Cover-cache: тот же состав туристов, nights ⊇ запрос, dates ⊆ cover → filter
            if (!empty($GLOBALS['tv_search_cover_enabled'])) {
                $coverHit = th_search_cover_find(array_merge($sp, ['currency' => 'RUB']), $ttlSearch);
                if (is_array($coverHit) && !empty($coverHit['hotels'])) {
                    $coverFiltered = tvFilterToursByParams($coverHit['hotels'], $sp);
                    if ($coverFiltered !== []) {
                        $GLOBALS['tv_cache_hit'] = true;
                        $GLOBALS['tv_cache_layer'] = 'L2-cover';
                        $coverRead = (string) ($coverHit['read'] ?? 'cover');
                        header('X-Tourvisor-Search-Mode: cache');
                        header('X-Tourvisor-Cache-Read: ' . $coverRead);
                        $cMeta = $coverHit['meta'] ?? [];
                        if (!empty($cMeta['from']) && !empty($cMeta['to'])) {
                            header('X-Tourvisor-Cache-Cover: ' . $cMeta['from'] . '..' . $cMeta['to']);
                        }
                        if (!empty($coverHit['identity'])) {
                            header('X-Tourvisor-Cache-Identity: ' . rawurlencode((string) $coverHit['identity']));
                        }
                        tvLog('search_cache_hit', [
                            'cache_key' => $ck,
                            'cache_kind' => 'search_cover',
                            'cover_read' => $coverRead,
                            'identity' => $coverHit['identity'] ?? '',
                            'hotels_count' => count($coverFiltered),
                        ]);
                        $r = ['success' => true, 'data' => $coverFiltered, 'fromCache' => true, 'fromCover' => true];
                        break;
                    }
                }
            }
        }

        $searchCacheOnly = isset($_GET['cacheOnly']) && $_GET['cacheOnly'] === '1';
        if ($type === 'search-cached' && $searchCacheOnly) {
            header('X-Tourvisor-Search-Mode: cache');
            header('X-Tourvisor-Cache-Read: none');
            $GLOBALS['tv_cache_hit'] = true;
            $r = ['success' => false, 'error' => 'Cache miss', 'data' => [], 'fromCache' => false];
            break;
        }

        header('X-Tourvisor-Search-Mode: live');
        header('X-Tourvisor-Cache-Read: none');
        $GLOBALS['tv_cache_layer'] = 'live';
        if ($onlyPromo) {
            header('X-Tourvisor-Promo-Source: live_api');
            tvLog('promo_search_live', [
                'countryId' => (int) ($sp['countryId'] ?? 0),
                'departureId' => (int) ($sp['departureId'] ?? 0),
                'reason' => !empty($GLOBALS['tv_bypass_cache']) ? 'bypass_cache' : 'cache_miss',
            ]);
        }
        tvLog('search_api_request', ['cache' => 'none', 'params' => array_intersect_key($sp, array_flip(['departureId', 'countryId', 'dateFrom', 'dateTo']))]);
        $GLOBALS['tv_cache_hit'] = false;
        [$nightsFrom, $nightsTo, $nightsExpanded] = tvNightsForTourvisorApi(
            (int) ($sp['nightsFrom'] ?? 6),
            (int) ($sp['nightsTo'] ?? 9)
        );
        if ($nightsExpanded) {
            tvLog('search_nights_expanded', [
                'user' => [(int) ($sp['nightsFrom'] ?? 0), (int) ($sp['nightsTo'] ?? 0)],
                'api' => [$nightsFrom, $nightsTo],
                'cache_key' => $ck,
            ]);
            header('X-Tourvisor-Nights-Expanded: 1');
        }
        $searchParamsApi = [
            'departureId' => $sp['departureId'] ?: th_departure_default_id(),
            'countryId' => $sp['countryId'] ?: 12,
            'dateFrom' => $sp['dateFrom'] !== '' ? $sp['dateFrom'] : date('Y-m-d', strtotime('+7 days')),
            'dateTo' => $sp['dateTo'] !== '' ? $sp['dateTo'] : date('Y-m-d', strtotime('+30 days')),
            'nightsFrom' => $nightsFrom,
            'nightsTo' => $nightsTo,
            'adults' => $sp['adults'],
            'currency' => 'RUB',
            'onlyCharter' => !empty($sp['onlyCharter']),
        ];
        if (!empty($sp['onlyDirect'])) {
            $searchParamsApi['onlyDirect'] = true;
        }
        $agesForApi = tvParseChildAges($sp['childs'] ?? null);
        if ($agesForApi !== []) {
            $searchParamsApi['childs'] = $agesForApi;
        }
        if (!empty($sp['meal'])) $searchParamsApi['meal'] = $sp['meal'];
        if (!empty($sp['hotelCategory'])) $searchParamsApi['hotelCategory'] = $sp['hotelCategory'];
        if (!empty($sp['regionIds'])) {
            $searchParamsApi['regionIds'] = is_array($sp['regionIds']) ? $sp['regionIds'] : array_map('intval', array_filter(explode(',', (string)$sp['regionIds'])));
        }
        $promoTileIdReq = (int) ($_GET['promoTileId'] ?? 0);
        if (!empty($sp['hotelIds'])) {
            $searchParamsApi['hotelIds'] = is_array($sp['hotelIds'])
                ? array_values(array_filter(array_map('intval', $sp['hotelIds'])))
                : array_values(array_filter(array_map('intval', explode(',', (string) $sp['hotelIds']))));
        }
        if (!empty($_GET['hotelServices'])) {
            $hs = $_GET['hotelServices'];
            $searchParamsApi['hotelServices'] = is_array($hs) ? array_map('intval', $hs) : array_map('intval', array_filter(explode(',', (string)$hs)));
        } elseif (!empty($sp['hotelServices'])) {
            $hs = $sp['hotelServices'];
            $searchParamsApi['hotelServices'] = is_array($hs) ? array_map('intval', $hs) : array_map('intval', array_filter(explode(',', (string)$hs)));
        }
        $dateFrom = $searchParamsApi['dateFrom'];
        $dateTo = $searchParamsApi['dateTo'];
        $tFrom = strtotime($dateFrom);
        $tTo = strtotime($dateTo);
        if ($tFrom !== false && $tTo !== false) {
            $diff = (int)(($tTo - $tFrom) / 86400);
            if ($diff > 14) $searchParamsApi['dateTo'] = date('Y-m-d', $tFrom + 14 * 86400);
        }
        $resSearch = tvRequest('/tours/search', $searchParamsApi); // запрос сразу в API, без проверки кэша
        if (!$resSearch['success'] || !isset($resSearch['data']['searchId'])) {
            tvLog('search_api_fail', ['error' => $resSearch['error'] ?? 'no searchId']);
            $r = ['success' => false, 'error' => $resSearch['error'] ?? 'Cache miss', 'fromCache' => false];
            break;
        }
        $sid = (int)$resSearch['data']['searchId'];
        $paramsToSave = $searchParamsApi;
        if ($promoTileIdReq > 0) {
            $paramsToSave['promoTileId'] = $promoTileIdReq;
        }
        tvSaveSearchParams($sid, $paramsToSave);
        // Раньше отдаём накопленное: early ~55% или soft 16с (вместо жёстких 24с до 100%)
        $earlyPct = (int) (getenv('TH_TV_EARLY_PROGRESS') ?: ($_ENV['TH_TV_EARLY_PROGRESS'] ?? 55));
        $pollSec = (int) (getenv('TH_TV_POLL_MAX_SEC') ?: ($_ENV['TH_TV_POLL_MAX_SEC'] ?? 16));
        $pollSec = max(10, min(28, $pollSec));
        $poll = tvPollSearchUntilReady($sid, $pollSec, ['earlyProgress' => max(0, min(90, $earlyPct))]);
        if (empty($poll['ok'])) {
            tvLog('search_api_error', ['searchId' => $sid, 'error' => $poll['error'] ?? 'poll']);
            $r = ['success' => false, 'error' => $poll['error'] ?? 'Search error', 'fromCache' => false];
            break;
        }
        // Для списка достаточно 250 отелей; полный 1000 только по явному resultLimit
        $resultLimit = isset($_GET['resultLimit']) ? (int) $_GET['resultLimit'] : 250;
        $resultLimit = max(50, min(1000, $resultLimit));
        $resResults = tvRequest("/tours/search/{$sid}", ['limit' => $resultLimit]);
        $liveData = (isset($resResults['data']) && is_array($resResults['data'])) ? $resResults['data'] : [];
        // Early-ready часто отдаёт пустой список при уже известном minPrice — дожимаем до 100%
        if ($liveData === [] && !empty($poll['earlyReady'])) {
            tvLog('search_early_empty_wait', ['searchId' => $sid, 'progress' => $poll['progress'] ?? null]);
            $poll2 = tvPollSearchUntilReady($sid, max(12, $pollSec), ['earlyProgress' => 0]);
            if (!empty($poll2['ok'])) {
                $poll = array_merge($poll, $poll2);
                unset($poll['earlyReady']);
            }
            $resResults = tvRequest("/tours/search/{$sid}", ['limit' => $resultLimit]);
            $liveData = (isset($resResults['data']) && is_array($resResults['data'])) ? $resResults['data'] : [];
        }
        // continue: мало выдачи ИЛИ пусто при разрешённом continue (жжёт суточный search-лимит TV)
        if (tvContinueMax() > 0 && (empty($liveData) || count($liveData) < 25)) {
            tvMaybeContinueSearch($sid, count($liveData));
            $resResults = tvRequest("/tours/search/{$sid}", ['limit' => $resultLimit]);
            $liveData = (isset($resResults['data']) && is_array($resResults['data'])) ? $resResults['data'] : [];
        }
        if (!empty($liveData)) {
            // Логируем, есть ли туры со скидкой в сыром ответе API: по полю isPromo и по названию (promo/промо/скидка)
            $totalTours = 0;
            $promoByFlag = 0;
            $promoByNameCount = 0;
            $promoExamples = [];
            $nonPromoExamples = [];
            foreach ($liveData as $hotel) {
                $tours = $hotel['tours'] ?? [];
                foreach ($tours as $t) {
                    $totalTours++;
                    $name = (string)($t['name'] ?? '');
                    $isPromo = !empty($t['isPromo']);
                    if ($isPromo) {
                        $promoByFlag++;
                    }
                    $nameHasPromo = $name !== '' && preg_match('/promo|промо|скидка/i', $name);
                    if ($nameHasPromo) {
                        $promoByNameCount++;
                        if (count($promoExamples) < 5) {
                            $promoExamples[] = ['id' => $t['id'] ?? null, 'name' => mb_substr($name, 0, 80), 'isPromo' => $isPromo];
                        }
                    }
                    if (!$isPromo && $name !== '' && count($nonPromoExamples) < 5) {
                        $nonPromoExamples[] = mb_substr($name, 0, 60);
                    }
                }
            }
            tvLog('promo_scan', [
                'hotels_count' => count($liveData),
                'total_tours' => $totalTours,
                'tours_with_isPromo_true' => $promoByFlag,
                'tours_with_promo_in_name' => $promoByNameCount,
                'promo_examples' => $promoExamples,
                'non_promo_examples' => $nonPromoExamples,
            ]);

            // Фильтр «только со скидкой» для страницы акций: API отдаёт isPromo у тура (документация https://api.tourvisor.ru/search/docs), иначе — по названию (promo/промо/скидка)
            $dataToReturn = $liveData;
            $promoTourIds = [];
            if ($onlyPromo) {
                $dataToReturn = [];
                foreach ($liveData as $hotel) {
                    $tours = $hotel['tours'] ?? [];
                    $promoTours = array_filter($tours, static function ($t) {
                        if (!empty($t['isPromo'])) {
                            return true;
                        }
                        $name = (string)($t['name'] ?? '');
                        return $name !== '' && preg_match('/promo|промо|скидка/i', $name);
                    });
                    $countryIdPromo = (int)($sp['countryId'] ?? 0);
                    $promoSearchSingle = isset($_GET['promoSearchSingle']) && $_GET['promoSearchSingle'] === '1';
                    if (!$promoSearchSingle && in_array($countryIdPromo, [1, 4, 13], true)) {
                        $promoTours = array_values(array_filter($promoTours, static function ($t) {
                            $n = (int)($t['nights'] ?? 0);
                            return $n === 0 || $n >= 6;
                        }));
                    }
                    if (!empty($promoTours)) {
                        $hotel['tours'] = array_values($promoTours);
                        $dataToReturn[] = $hotel;
                        foreach ($promoTours as $t) {
                            if (!empty($t['id'])) {
                                $promoTourIds[] = $t['id'];
                            }
                        }
                    }
                }
                tvLog('promo_filter', ['total_hotels' => count($liveData), 'promo_hotels' => count($dataToReturn), 'promo_tour_ids_count' => count($promoTourIds)]);
            }
    // Не сохраняем пустую выдачу в кэш (иначе Самара→VN с minPrice навсегда «пустая»)
            // internal=1 — вложенный вызов из promo-pipeline, не пишем в search-кэш.
            $saveToCache = $sp['dateFrom'] !== '' && $sp['dateTo'] !== '' && $liveData !== [] && !$internalRequest;
            if ($saveToCache) {
                $promoCacheCountryId = (int) ($_GET['promoTileId'] ?? 0);
                if ($promoCacheCountryId <= 0) {
                    $promoCacheCountryId = (int) ($sp['countryId'] ?? 0);
                }
                $skipCountryWidePromoCache = trim((string) ($_GET['regionIds'] ?? '')) !== ''
                    && (int) ($_GET['promoTileId'] ?? 0) <= 0;
                tvQueuePersistAfterResponse([
                    'ck' => $ck,
                    'liveData' => $liveData,
                    'dataToReturn' => $dataToReturn,
                    'sp' => $sp,
                    'ttlSearch' => $ttlSearch,
                    'onlyPromo' => $onlyPromo,
                    'cacheNamespace' => $cacheNamespace,
                    'searchId' => $sid,
                    'promoCacheCountryId' => $promoCacheCountryId,
                    'skipCountryWidePromoCache' => $skipCountryWidePromoCache,
                ]);
                tvLog('search_api_ok', [
                    'searchId' => $sid,
                    'tours_count' => count($liveData),
                    'saved_to_cache_and_firestore' => 'deferred',
                    'earlyReady' => !empty($poll['earlyReady']),
                    'softTimeout' => !empty($poll['softTimeout']),
                    'progress' => $poll['progress'] ?? null,
                    'dateFrom' => $sp['dateFrom'],
                    'dateTo' => $sp['dateTo'],
                ]);
            } else {
                tvLog('search_api_ok', ['searchId' => $sid, 'tours_count' => count($liveData), 'saved_to_cache' => false, 'reason' => 'missing_dates']);
            }
            $r = [
                'success' => true,
                'data' => $dataToReturn,
                'searchId' => $sid,
                'fromCache' => false,
                'partial' => !empty($poll['earlyReady']) || !empty($poll['softTimeout']),
                'progress' => $poll['progress'] ?? null,
                'promoSearchSource' => $onlyPromo ? 'live_api' : null,
            ];
            if (!empty($nightsExpanded)) {
                $r['nightsExpanded'] = true;
                $r['nightsRequestedFrom'] = (int) ($sp['nightsFrom'] ?? 0);
                $r['nightsRequestedTo'] = (int) ($sp['nightsTo'] ?? 0);
            }
            if ($onlyPromo) {
                tvLog('promo_search_live_ok', [
                    'searchId' => $sid,
                    'countryId' => (int) ($sp['countryId'] ?? 0),
                    'promo_hotels' => count($dataToReturn),
                    'saved_to_cache' => $saveToCache ? 'deferred' : false,
                ]);
            }
            if ($onlyPromo && !empty($promoTourIds)) {
                $r['promoTourIds'] = array_values(array_unique($promoTourIds));
            }
        } else {
            $r = ['success' => false, 'error' => 'No results', 'fromCache' => false];
        }
        break;
    case 'tours':
        $sp = [
            'departureId' => (int)($_GET['departureId'] ?? th_departure_default_id()),
            'countryId' => (int)($_GET['countryId'] ?? 0),
            'dateFrom' => trim((string)($_GET['dateFrom'] ?? date('Y-m-d', strtotime('+7 days')))),
            'dateTo' => trim((string)($_GET['dateTo'] ?? date('Y-m-d', strtotime('+21 days')))),
            'nightsFrom' => (int)($_GET['nightsFrom'] ?? 6),
            'nightsTo' => (int)($_GET['nightsTo'] ?? 9),
            'adults' => (int)($_GET['adults'] ?? 2),
            'page' => max(1, (int)($_GET['page'] ?? 1)),
            'perPage' => max(1, min(100, (int)($_GET['perPage'] ?? ($_GET['per_page'] ?? 25)))),
        ];
        $isAction = isset($_GET['isAction']) ? in_array(strtolower((string)$_GET['isAction']), ['1', 'true', 'yes'], true) : false;
        $regionIdRaw = $_GET['regionId'] ?? ($_GET['regionIds'] ?? '');
        if (isset($_GET['childs'])) {
            $cRawTours = trim((string) $_GET['childs']);
            if ($cRawTours !== '') {
                $sp['childs'] = $cRawTours;
            }
        }
        if (!empty($_GET['meal'])) $sp['meal'] = (int)$_GET['meal'];
        if (!empty($_GET['hotelCategory'])) $sp['hotelCategory'] = (int)$_GET['hotelCategory'];
        if (!empty($_GET['hotelServices'])) $sp['hotelServices'] = $_GET['hotelServices'];
        if ($regionIdRaw !== '') $sp['regionIds'] = (string)$regionIdRaw;

        if ($sp['countryId'] <= 0) {
            $r = ['success' => false, 'error' => 'countryId required', 'tours' => [], 'data' => []];
            break;
        }

        $nightsFrom = max(1, min(28, (int)$sp['nightsFrom']));
        $nightsTo = max(1, min(28, (int)$sp['nightsTo']));
        if ($nightsTo - $nightsFrom > 10) {
            $nightsTo = $nightsFrom + 10;
        }

        $searchParamsApi = [
            'departureId' => $sp['departureId'] ?: th_departure_default_id(),
            'countryId' => $sp['countryId'],
            'dateFrom' => $sp['dateFrom'],
            'dateTo' => $sp['dateTo'],
            'nightsFrom' => $nightsFrom,
            'nightsTo' => $nightsTo,
            'adults' => max(1, (int)$sp['adults']),
            'currency' => 'RUB',
            'onlyCharter' => !empty($sp['onlyCharter']),
        ];
        if (!empty($sp['onlyDirect'])) {
            $searchParamsApi['onlyDirect'] = true;
        }
        $agesToursApi = tvParseChildAges($sp['childs'] ?? null);
        if ($agesToursApi !== []) {
            $searchParamsApi['childs'] = $agesToursApi;
        }
        if (!empty($sp['meal'])) $searchParamsApi['meal'] = (int)$sp['meal'];
        if (!empty($sp['hotelCategory'])) $searchParamsApi['hotelCategory'] = (int)$sp['hotelCategory'];
        if (!empty($sp['regionIds'])) {
            $searchParamsApi['regionIds'] = is_array($sp['regionIds']) ? $sp['regionIds'] : array_map('intval', array_filter(explode(',', (string)$sp['regionIds'])));
        }
        if (!empty($sp['hotelServices'])) {
            $hs = $sp['hotelServices'];
            $searchParamsApi['hotelServices'] = is_array($hs) ? array_map('intval', $hs) : array_map('intval', array_filter(explode(',', (string)$hs)));
        }

        tvLog('tours_live_start', [
            'countryId' => $sp['countryId'],
            'isAction' => $isAction,
            'regionIds' => $searchParamsApi['regionIds'] ?? [],
            'dateFrom' => $searchParamsApi['dateFrom'],
            'dateTo' => $searchParamsApi['dateTo'],
        ]);

        $resSearch = tvRequest('/tours/search', $searchParamsApi);
        if (!$resSearch['success'] || !isset($resSearch['data']['searchId'])) {
            $r = ['success' => false, 'error' => $resSearch['error'] ?? 'Search start failed', 'tours' => [], 'data' => []];
            break;
        }

        $sid = (int)$resSearch['data']['searchId'];
        $poll = tvPollSearchUntilReady($sid, 20);
        if (empty($poll['ok'])) {
            $r = ['success' => false, 'error' => $poll['error'] ?? 'Search error', 'tours' => [], 'data' => []];
            break;
        }

        $resResults = tvRequest("/tours/search/{$sid}", ['limit' => 1000]);
        $liveData = (isset($resResults['data']) && is_array($resResults['data'])) ? $resResults['data'] : [];
        if (!is_array($liveData)) $liveData = [];
        if ($liveData !== [] && tvContinueMax() > 0 && count($liveData) < 25) {
            tvMaybeContinueSearch($sid, count($liveData));
            $resResults = tvRequest("/tours/search/{$sid}", ['limit' => 1000]);
            $liveData = (isset($resResults['data']) && is_array($resResults['data'])) ? $resResults['data'] : [];
            if (!is_array($liveData)) $liveData = [];
        }

        $filtered = $liveData;
        if ($isAction) {
            $actionOnly = [];
            foreach ($filtered as $hotel) {
                $tours = $hotel['tours'] ?? [];
                $promoTours = array_filter($tours, static function ($t) {
                    if (!empty($t['isPromo'])) return true;
                    $name = (string)($t['name'] ?? '');
                    return $name !== '' && preg_match('/promo|промо|скидка|акци/i', $name);
                });
                if (!empty($promoTours)) {
                    $hotel['tours'] = array_values($promoTours);
                    $actionOnly[] = $hotel;
                }
            }
            $filtered = $actionOnly;
        }

        $total = count($filtered);
        $offset = ($sp['page'] - 1) * $sp['perPage'];
        $paged = array_slice($filtered, $offset, $sp['perPage']);
        $hasMore = ($offset + $sp['perPage']) < $total;

        tvLog('tours_live_done', [
            'countryId' => $sp['countryId'],
            'isAction' => $isAction,
            'total' => $total,
            'returned' => count($paged),
            'page' => $sp['page'],
            'perPage' => $sp['perPage'],
        ]);

        $r = [
            'success' => true,
            'tours' => $paged,
            'data' => $paged,
            'total' => $total,
            'page' => $sp['page'],
            'hasMore' => $hasMore,
            'isAction' => $isAction,
            'fromCache' => false,
            'searchId' => $sid,
        ];
        break;
default:
        $r = ['success' => false, 'error' => 'Unknown type. Use: departures, countries, regions, arrivals, meals, hotel-services, hotel-types, hotel, dates, tour, tour-flights, tours, search, status, results'];
}

    // Гибрид YML: подпитка yandex_feed_offers из ответов акционного поиска (onlyPromo=1); выключается YANDEX_LEGACY_OFFERS_TABLE_SYNC=0
    $ingestEnvOn = filter_var(getenv('YML_FEED_INGEST_FROM_SEARCH') ?: ($_ENV['YML_FEED_INGEST_FROM_SEARCH'] ?? '1'), FILTER_VALIDATE_BOOLEAN);
    $onlyPromoReq = isset($_GET['onlyPromo']) && $_GET['onlyPromo'] === '1';
    $isSearchType = in_array($type, ['search', 'search-cached'], true);
    $legacyOffersTableOn = true;
    if ($isSearchType && $onlyPromoReq && $ingestEnvOn) {
        require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'yandex_feed_sync.php';
        $legacyOffersTableOn = yandex_feed_legacy_table_sync_enabled();
    }
    $wantsIngest = $isSearchType && $onlyPromoReq && $ingestEnvOn && $legacyOffersTableOn;
    $ymlIngestClient = null;

    if ($onlyPromoReq && $isSearchType) {
        tvLog('yandex_feed_ingest_gate', [
            'ingest_env_on' => $ingestEnvOn,
            'legacy_offers_table_on' => $legacyOffersTableOn,
            'response_success' => !empty($r['success']),
            'data_count' => is_array($r['data'] ?? null) ? count($r['data']) : 0,
            'countryId' => (int) ($_GET['countryId'] ?? 0),
        ]);
    }

    if ($wantsIngest) {
        if (empty($r['success']) || !is_array($r['data'] ?? null) || count($r['data']) === 0) {
            tvLog('yandex_feed_ingest_skip', [
                'reason' => 'empty_or_failed_search_response',
                'success' => $r['success'] ?? null,
                'data_count' => is_array($r['data'] ?? null) ? count($r['data']) : 0,
            ]);
            if (tvYmlClientDebugEnabled()) {
                $ymlIngestClient = [
                    'step' => 'skip_no_data',
                    'response_success' => !empty($r['success']),
                    'data_count' => is_array($r['data'] ?? null) ? count($r['data']) : 0,
                ];
            }
        } else {
            try {
                if (!isset($pdo) || !($pdo instanceof PDO)) {
                    tvLog('yandex_feed_ingest_skip', ['reason' => 'pdo_unavailable']);
                    if (tvYmlClientDebugEnabled()) {
                        $ymlIngestClient = ['step' => 'skip_no_pdo', 'pdo_available' => false];
                    }
                } else {
                    require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'yandex_feed_sync.php';
                    $cid = (int) ($_GET['countryId'] ?? 0);
                    $cname = trim((string) ($_GET['countryName'] ?? ''));
                    $df = trim((string) ($_GET['dateFrom'] ?? ''));
                    $dt = trim((string) ($_GET['dateTo'] ?? ''));
                    if ($df === '' || $dt === '') {
                        $df = date('Y-m-d', strtotime('+7 days'));
                        $dt = date('Y-m-d', strtotime('+21 days'));
                    }
                    $decoded = ['success' => true, 'data' => $r['data']];
                    $trace = yandex_feed_ingest_search_response_trace($pdo, $decoded, $cid, $cname, $df, $dt);
                    tvLog('yandex_feed_ingest_trace', [
                        'inserted' => $trace['inserted'],
                        'hotels_in_json' => $trace['hotels_in_json'],
                        'parsed_offer_rows' => $trace['parsed_offer_rows'],
                        'upsert_stmt_ok' => $trace['upsert_stmt_ok'],
                        'max_hook' => $trace['max_hook'],
                        'stop_reason' => $trace['stop_reason'],
                        'pdo_errors_n' => count($trace['pdo_errors']),
                    ]);
                    if ($trace['pdo_errors'] !== []) {
                        tvLog('yandex_feed_ingest_pdo_errors', ['sample' => array_slice($trace['pdo_errors'], 0, 5)]);
                    }
                    if ($trace['inserted'] > 0) {
                        tvLog('yandex_feed_ingest', ['rows' => $trace['inserted'], 'countryId' => $cid]);
                    }
                    if (tvYmlClientDebugEnabled()) {
                        $ymlIngestClient = array_merge(
                            ['step' => 'completed', 'countryId' => $cid, 'dateFrom' => $df, 'dateTo' => $dt],
                            $trace
                        );
                    }
                }
            } catch (Throwable $e) {
                tvLog('yandex_feed_ingest_fail', ['error' => $e->getMessage()]);
                if (tvYmlClientDebugEnabled()) {
                    $ymlIngestClient = ['step' => 'exception', 'message' => $e->getMessage()];
                }
            }
        }
    }

    if (tvYmlClientDebugEnabled() && $onlyPromoReq && $isSearchType) {
        if (!$ingestEnvOn) {
            $r['_ymlIngest'] = ['step' => 'skipped_env', 'ingest_enabled' => false];
        } elseif ($ymlIngestClient !== null) {
            $r['_ymlIngest'] = $ymlIngestClient;
        }
    }

    } catch (Throwable $e) {
        error_log('[tourvisor-proxy] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        $r = [
            'success' => false,
            'error' => 'Server error',
            'data' => null,
            'error_detail' => $e->getMessage(),
        ];
    }

    return $r;
}

/** Типы ответов, где применяются operator + price фильтры на emit. */
function tourvisor_proxy_emit_filter_types(): array
{
    return ['search', 'search-cached', 'results', 'promo-search', 'tour-hots', 'tours'];
}

/**
 * Профиль emit-фильтров: main (главная/страны) vs promo (акции).
 * Promo-only правила (VN regions, SL 55k floor) — в promo_speed_cache / promo_sochi_filter, не здесь.
 */
function tourvisor_proxy_emit_filter_profile(string $type): string
{
    if ($type === 'promo-search') {
        return 'promo';
    }
    if ($type === 'search-cached' && isset($_GET['onlyPromo']) && $_GET['onlyPromo'] === '1') {
        return 'promo';
    }

    return 'main';
}

/**
 * @return array{countryId:int,countryName:string,departureId:int,regionIds:string,promoTileId:int,adults:int}
 */
function tourvisor_proxy_emit_filter_context(string $type): array
{
    $ofCountryId = (int) ($_GET['countryId'] ?? 0);
    $ofCountryName = trim((string) ($_GET['countryName'] ?? ''));
    $ofDepartureId = (int) ($_GET['departureId'] ?? 0);
    $ofRegionIds = trim((string) ($_GET['regionIds'] ?? ''));
    $ofPromoTileId = (int) ($_GET['promoTileId'] ?? 0);
    $ofAdults = max(1, min(9, (int) ($_GET['adults'] ?? 0)));

    if ($type === 'results') {
        $ofSid = (int) ($_GET['searchId'] ?? 0);
        if ($ofSid > 0) {
            $ofParams = tvLoadSearchParams($ofSid);
            if (is_array($ofParams)) {
                $ofCountryId = (int) ($ofParams['countryId'] ?? $ofCountryId);
                $ofDepartureId = (int) ($ofParams['departureId'] ?? $ofDepartureId);
                if ($ofRegionIds === '' && !empty($ofParams['regionIds'])) {
                    $ofRegionIds = is_array($ofParams['regionIds'])
                        ? implode(',', array_map('strval', $ofParams['regionIds']))
                        : trim((string) $ofParams['regionIds']);
                }
                if ($ofPromoTileId <= 0 && !empty($ofParams['promoTileId'])) {
                    $ofPromoTileId = (int) $ofParams['promoTileId'];
                }
                if ($ofAdults <= 0 && !empty($ofParams['adults'])) {
                    $ofAdults = max(1, min(9, (int) $ofParams['adults']));
                }
            }
        }
    }
    if ($ofAdults <= 0) {
        $ofAdults = 2;
    }

    return [
        'countryId' => $ofCountryId,
        'countryName' => $ofCountryName,
        'departureId' => $ofDepartureId,
        'regionIds' => $ofRegionIds,
        'promoTileId' => $ofPromoTileId,
        'adults' => $ofAdults,
    ];
}

/** Operator filter — одинаково для main и promo. */
function tourvisor_proxy_apply_operator_filters(array $r, array $ctx): array
{
    require_once __DIR__ . '/../operator_filter.php';
    $ofOpts = [
        'allowSpaceTravel' => th_operator_allows_space_travel([
            'departureId' => $ctx['departureId'],
            'countryId' => $ctx['countryId'],
            'regionIds' => $ctx['regionIds'],
            'promoTileId' => $ctx['promoTileId'],
        ]),
    ];
    if (is_array($r['data'] ?? null) && $r['data'] !== []) {
        $r['data'] = th_operator_filter_hotels($r['data'], $ctx['countryId'], $ctx['countryName'], $ofOpts);
    }
    if (is_array($r['tours'] ?? null) && $r['tours'] !== []) {
        $r['tours'] = th_operator_filter_hotels($r['tours'], $ctx['countryId'], $ctx['countryName'], $ofOpts);
    }

    return $r;
}

/** Price filter — одинаково для main и promo (отсев битых 15k/54k пакетов). */
function tourvisor_proxy_apply_price_filters(array $r, array $ctx): array
{
    require_once __DIR__ . '/../th_tour_price.php';
    if (th_tour_price_is_hotel_only_departure($ctx['departureId'] > 0 ? $ctx['departureId'] : null)) {
        return $r;
    }
    if (is_array($r['data'] ?? null) && $r['data'] !== []) {
        $r['data'] = th_tour_price_filter_hotels($r['data'], $ctx['adults']);
    }
    if (is_array($r['tours'] ?? null) && $r['tours'] !== []) {
        $r['tours'] = th_tour_price_filter_hotels($r['tours'], $ctx['adults']);
    }

    return $r;
}

/**
 * Emit-фильтры ответа (не трогают файл search-кэша — там полная выдача API).
 *
 * @param array<string, mixed> $r
 * @return array<string, mixed>
 */
function tourvisor_proxy_emit_count_items(array $r): int
{
    if (is_array($r['data'] ?? null)) {
        return count($r['data']);
    }
    if (is_array($r['results'] ?? null)) {
        return count($r['results']);
    }

    return 0;
}

function tourvisor_proxy_apply_emit_filters(array $r, string $type): array
{
    if (empty($r['success'])) {
        return $r;
    }
    if (!in_array($type, tourvisor_proxy_emit_filter_types(), true)) {
        return $r;
    }
    $ctx = tourvisor_proxy_emit_filter_context($type);
    $rawCount = tourvisor_proxy_emit_count_items($r);
    $GLOBALS['tv_emit_items_raw'] = $rawCount;

    $r = tourvisor_proxy_apply_operator_filters($r, $ctx);
    $afterOperator = tourvisor_proxy_emit_count_items($r);
    $GLOBALS['tv_emit_items_after_operator'] = $afterOperator;
    $afterOperatorData = is_array($r['data'] ?? null) ? $r['data'] : null;
    $afterOperatorTours = is_array($r['tours'] ?? null) ? $r['tours'] : null;

    $r = tourvisor_proxy_apply_price_filters($r, $ctx);
    $afterPrice = tourvisor_proxy_emit_count_items($r);
    $GLOBALS['tv_emit_items_after_price'] = $afterPrice;
    $GLOBALS['tv_emit_price_filter_relaxed'] = false;

    // Price filter не должен обнулять живую выдачу (SL/VN: только price без totalPrice).
    if ($rawCount >= 3 && $afterOperator >= 3 && $afterPrice === 0
        && tourvisor_proxy_emit_filter_profile($type) === 'main') {
        if ($afterOperatorData !== null) {
            $r['data'] = $afterOperatorData;
        }
        if ($afterOperatorTours !== null) {
            $r['tours'] = $afterOperatorTours;
        }
        $GLOBALS['tv_emit_price_filter_relaxed'] = true;
        if (function_exists('tvLog')) {
            tvLog('price_filter_zeroed_all', [
                'type' => $type,
                'raw' => $rawCount,
                'after_operator' => $afterOperator,
                'countryId' => $ctx['countryId'] ?? null,
            ]);
        }
    }

    return $r;
}

function tourvisor_proxy_emit(array $r): void
{
    $type = $_GET['type'] ?? '';
    $filterProfile = tourvisor_proxy_emit_filter_profile($type);
    header('X-Tourvisor-Filter-Profile: ' . $filterProfile);

    // ─── Фильтр туроператоров + отсев битых цен ───
    // Единственная точка emit: operator + th_tour_price для main и promo.
    // Promo-only (VN/SL floor, min nights) — в promo_speed_cache до ответа.
    // Кэш поиска, all_tours и YML сохраняют полную выдачу API (фильтруется только ответ клиенту).
    $r = tourvisor_proxy_apply_emit_filters($r, $type);

    $jwt = function_exists('getTvToken') ? getTvToken() : '';
    $itemsCount = is_array($r['data'] ?? null) ? count($r['data']) : (is_array($r['results'] ?? null) ? count($r['results']) : 0);
    // Слой ответа: go выставляет сам; PHP — прямой fallback (nginx / Go upstream).
    $readerHint = strtolower(trim((string) ($_SERVER['HTTP_X_CACHE_READER_HINT'] ?? '')));
    if ($readerHint === '' || !preg_match('/^[a-z0-9_-]{1,40}$/', $readerHint)) {
        $readerHint = 'php';
    }
    header('X-Cache-Reader: ' . $readerHint);
    $cacheLayer = (string) ($GLOBALS['tv_cache_layer'] ?? '');
    if ($cacheLayer === '' && !empty($GLOBALS['tv_cache_hit'])) {
        $cacheLayer = 'L2-file';
    }
    if ($cacheLayer === '' && isset($GLOBALS['tv_cache_hit']) && $GLOBALS['tv_cache_hit'] === false) {
        $cacheLayer = 'live';
    }
    if ($cacheLayer !== '') {
        header('X-Tourvisor-Cache-Layer: ' . $cacheLayer);
    }
    header('X-Tourvisor-Type: ' . ($type ?: 'none'));
    header('X-Tourvisor-Source: ' . ($GLOBALS['tv_source'] ?? 'default'));
    header('X-Tourvisor-Success: ' . (!empty($r['success']) ? 'yes' : 'no'));
    header('X-Tourvisor-Token: ' . (strlen($jwt) > 10 ? 'ok' : 'missing'));
    header('X-Tourvisor-Cache: ' . ($GLOBALS['tv_cache_hit'] === true ? 'hit' : ($GLOBALS['tv_cache_hit'] === false ? 'miss' : 'n/a')));
    header('X-Tourvisor-Firestore: ' . ($GLOBALS['tv_firestore_used'] ?? 'off'));
    header('X-Tourvisor-Items: ' . (string) $itemsCount);
    if (isset($GLOBALS['tv_emit_items_raw'])) {
        header('X-Tourvisor-Items-Raw: ' . (string) (int) $GLOBALS['tv_emit_items_raw']);
    }
    if (isset($GLOBALS['tv_emit_items_after_operator'])) {
        header('X-Tourvisor-Items-After-Operator: ' . (string) (int) $GLOBALS['tv_emit_items_after_operator']);
    }
    if (isset($GLOBALS['tv_emit_items_after_price'])) {
        header('X-Tourvisor-Items-After-Price: ' . (string) (int) $GLOBALS['tv_emit_items_after_price']);
    }
    if (!empty($GLOBALS['tv_emit_price_filter_relaxed'])) {
        header('X-Tourvisor-Price-Filter-Relaxed: yes');
    }
    header('X-Tourvisor-Cache-Saved: ' . ($GLOBALS['tv_cache_hit'] === false && !empty($r['success']) && isset($r['data']) ? 'yes' : 'no'));
    header('X-Tourvisor-Fallback: ' . ($GLOBALS['tv_use_fallbacks'] ? 'on' : 'off'));
    if (!empty($r['success']) && is_array($r['data'] ?? null)) {
        $first = $r['data'][0] ?? null;
        $isHotelList = is_array($first) && (isset($first['id']) || isset($first['tours']) || isset($first['name']));
        if ($isHotelList) {
            $wantFull = isset($_GET['full']) && $_GET['full'] === '1';
            $wantSlim = !$wantFull && (
                (isset($_GET['slim']) && $_GET['slim'] === '1')
                || (isset($_GET['cacheOnly']) && $_GET['cacheOnly'] === '1')
                || in_array($type, ['search-cached', 'search', 'results'], true)
            );
            if ($wantSlim && function_exists('tv_slim_hotels_for_list')) {
                $r['data'] = tv_slim_hotels_for_list($r['data']);
                $r['slim'] = true;
                header('X-Tourvisor-Slim: 1');
            } else {
                $r['data'] = tv_enrich_hotel_pictures($r['data']);
                header('X-Tourvisor-Slim: 0');
            }
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 0;
            $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
            if ($limit > 0 && is_array($r['data'])) {
                $r['totalCount'] = count($r['data']);
                $r['data'] = array_slice($r['data'], $offset, $limit);
                $r['offset'] = $offset;
                $r['limit'] = $limit;
                header('X-Tourvisor-Items: ' . (string) count($r['data']));
                header('X-Tourvisor-Total: ' . (string) $r['totalCount']);
            }
        }
    }
    $clientDebugEnabled = filter_var(getenv('TH_CLIENT_DEBUG_ENABLED') ?: ($_ENV['TH_CLIENT_DEBUG_ENABLED'] ?? '0'), FILTER_VALIDATE_BOOLEAN);
    $clientDebugHeader = trim((string)($_SERVER['HTTP_X_TH_DEBUG'] ?? ''));
    $clientDebugKey = trim((string)(getenv('TH_CLIENT_DEBUG_KEY') ?: ($_ENV['TH_CLIENT_DEBUG_KEY'] ?? '')));
    $clientDebugReqKey = trim((string)($_SERVER['HTTP_X_TH_DEBUG_KEY'] ?? ''));
    $canClientDebug = $clientDebugEnabled
        && $clientDebugHeader === '1'
        && ($clientDebugKey === '' || hash_equals($clientDebugKey, $clientDebugReqKey));
    if ($canClientDebug) {
        $elapsedMs = 0;
        if (!empty($GLOBALS['tv_req_started_at'])) {
            $elapsedMs = (int)round((microtime(true) - (float)$GLOBALS['tv_req_started_at']) * 1000);
        }
        $r['_trace'] = [
            'type' => (string)($type ?: 'none'),
            'success' => !empty($r['success']),
            'cacheReader' => (string)($readerHint ?? 'php'),
            'cacheLayer' => (string)($GLOBALS['tv_cache_layer'] ?? ''),
            'items' => $itemsCount,
            'elapsedMs' => $elapsedMs,
            'fromCache' => $GLOBALS['tv_cache_hit'] === true,
            'firestore' => (string)($GLOBALS['tv_firestore_used'] ?? 'off'),
        ];
    }
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    if (!empty($GLOBALS['tv_persist_after']) && is_array($GLOBALS['tv_persist_after'])) {
        // Отдаём тело клиенту, затем пишем кэш/cover/Firestore — не держим TTFB
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            @litespeed_finish_request();
        } else {
            if (function_exists('ob_get_level')) {
                while (ob_get_level() > 0) {
                    @ob_end_flush();
                }
            }
            @flush();
        }
        tvRunPersistAfterResponse();
    }
}

if (!TH_TOURVISOR_PROXY_EMBED) {
    tourvisor_proxy_emit(tourvisor_proxy_dispatch());
}
