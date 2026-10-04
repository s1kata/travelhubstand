<?php
declare(strict_types=1);
/**
 * Таблица yandex_feed_offers + синхронизация акционных туров из Tourvisor (тот же прокси, что promotions).
 */
require_once __DIR__ . '/yandex_feed_schema.php';
require_once __DIR__ . '/tourvisor_proxy_http_base.php';

/** Файл-сигнал остановки полного синка из админки (manage-yandex-feed). */
function yandex_feed_sync_stop_flag_path(): string
{
    $root = dirname(__DIR__, 2);

    return $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'yandex_feed_sync_stop.flag';
}

function yandex_feed_sync_request_stop(): void
{
    $path = yandex_feed_sync_stop_flag_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    file_put_contents($path, date('c'), LOCK_EX);
}

function yandex_feed_sync_clear_stop_flag(): void
{
    $path = yandex_feed_sync_stop_flag_path();
    if (is_file($path)) {
        @unlink($path);
    }
}

function yandex_feed_sync_stop_requested(): bool
{
    return is_file(yandex_feed_sync_stop_flag_path());
}

/**
 * Таблица yandex_feed_offers: полный синк из Tourvisor, кнопка в админке, подпитка из акционного поиска (см. tourvisor-proxy).
 * В .env: YANDEX_LEGACY_OFFERS_TABLE_SYNC=0 — отключить; для Яндекс.Бизнеса используйте /feed.yml по правилам (yandex_yml_feed_rules, yml_feed_rules_cron.php).
 */
function yandex_feed_legacy_table_sync_enabled(): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $raw = getenv('YANDEX_LEGACY_OFFERS_TABLE_SYNC');
    if ($raw === false || trim((string) $raw) === '') {
        $raw = $_ENV['YANDEX_LEGACY_OFFERS_TABLE_SYNC'] ?? '1';
    }
    $cached = filter_var(trim((string) $raw), FILTER_VALIDATE_BOOLEAN);

    return $cached;
}

/**
 * Публичный URL картинки отеля для YML: короткий ?path= к tourvisor-image-proxy (как на фронте),
 * чтобы не превращать hotel_pics/... в битый URL на своём домене и не упираться в VARCHAR(2000).
 */
function yandex_feed_abs_picture_url(string $picturelink, string $siteBase, string $imageProxyBase): string
{
    $src = trim($picturelink);
    if ($src === '') {
        return rtrim($siteBase, '/') . '/frontend/favicon.svg';
    }

    $proxy = rtrim($imageProxyBase, '/');
    $tourvisorPathOk = static function (string $path): bool {
        $path = ltrim($path, '/');

        return $path !== '' && strpos($path, '..') === false && (bool) preg_match('#^[a-zA-Z0-9_./-]+$#', $path);
    };
    $viaPath = static function (string $path) use ($proxy, $tourvisorPathOk): ?string {
        if (!$tourvisorPathOk($path)) {
            return null;
        }

        return $proxy . '?path=' . rawurlencode(ltrim($path, '/'));
    };

    // Относительный путь Tourvisor (часто приходит без домена)
    if (preg_match('#^/?hotel_pics/#i', $src)) {
        $path = ltrim($src, '/');
        $short = $viaPath($path);
        if ($short !== null) {
            return $short;
        }
    }

    if (preg_match('#^https?://static\.tourvisor\.ru/(.+)$#i', $src, $m)) {
        $short = $viaPath($m[1]);
        if ($short !== null) {
            return $short;
        }
        $httpUrl = preg_replace('#^https:#i', 'http:', $src);

        return $proxy . '?url=' . rawurlencode($httpUrl);
    }
    if (str_starts_with($src, '//') && preg_match('#^//static\.tourvisor\.ru/(.+)$#i', $src, $m)) {
        $short = $viaPath($m[1]);
        if ($short !== null) {
            return $short;
        }

        return $proxy . '?url=' . rawurlencode('http:' . $src);
    }
    if (preg_match('#^static\.tourvisor\.ru/(.+)$#i', $src, $m)) {
        $short = $viaPath($m[1]);
        if ($short !== null) {
            return $short;
        }

        return $proxy . '?url=' . rawurlencode('http://' . $src);
    }
    if (str_starts_with($src, '//')) {
        return $proxy . '?url=' . rawurlencode('http:' . $src);
    }
    if (preg_match('#^https?://#i', $src)) {
        return $src;
    }

    return rtrim($siteBase, '/') . '/' . ltrim($src, '/');
}

/** Есть ли у тура реальное фото (не заглушка favicon). */
function yandex_feed_is_valid_tour_picture(string $pictureUrl): bool
{
    $u = trim($pictureUrl);
    if ($u === '') {
        return false;
    }

    return stripos($u, 'favicon.svg') === false;
}

/**
 * Первый непустой URL/путь к фото отеля или тура (Tourvisor / кэш поиска).
 * Без угадывания hotel_pics/main400 — только данные из ответа API.
 */
function yandex_feed_hotel_or_tour_picture_raw(array $h, array $tour): string
{
    $pickString = static function ($v): string {
        if (!is_string($v)) {
            return '';
        }
        $t = trim($v);

        return $t !== '' ? $t : '';
    };

    foreach (['picturelink', 'pictureLink', 'mainpicture', 'mainPicture', 'picture', 'photo', 'image', 'img'] as $k) {
        if (!array_key_exists($k, $h)) {
            continue;
        }
        $v = $h[$k];
        if (is_string($v)) {
            $s = $pickString($v);
            if ($s !== '') {
                return $s;
            }
        }
        if (is_array($v) && ($k === 'mainPicture' || $k === 'mainpicture')) {
            foreach (['src', 'url', 'link', 'picturelink', 'pictureLink'] as $ik) {
                $s = $pickString((string) ($v[$ik] ?? ''));
                if ($s !== '') {
                    return $s;
                }
            }
        }
    }

    if (!empty($h['pictures']) && is_array($h['pictures'])) {
        foreach ($h['pictures'] as $p) {
            if (is_string($p)) {
                $s = $pickString($p);
                if ($s !== '') {
                    return $s;
                }
            }
            if (is_array($p)) {
                foreach (['src', 'url', 'link', 'picturelink', 'pictureLink', 'picture'] as $pk) {
                    $s = $pickString((string) ($p[$pk] ?? ''));
                    if ($s !== '') {
                        return $s;
                    }
                }
            }
        }
    }

    foreach (['picturelink', 'pictureLink', 'picture', 'image', 'photo'] as $k) {
        $s = $pickString((string) ($tour[$k] ?? ''));
        if ($s !== '') {
            return $s;
        }
    }
    if (!empty($tour['pictures']) && is_array($tour['pictures'])) {
        foreach ($tour['pictures'] as $p) {
            if (is_string($p)) {
                $s = $pickString($p);
                if ($s !== '') {
                    return $s;
                }
            }
            if (is_array($p)) {
                foreach (['src', 'url', 'link', 'picturelink', 'pictureLink'] as $pk) {
                    $s = $pickString((string) ($p[$pk] ?? ''));
                    if ($s !== '') {
                        return $s;
                    }
                }
            }
        }
    }

    return '';
}

function yandex_feed_format_price_rub(float $n): string
{
    return number_format($n, 0, '', ' ') . ' ₽';
}

function yandex_feed_departure_city_for_offer_url(): string
{
    $n = trim((string) (getenv('YML_FEED_DEPARTURE_NAME') ?: ($_ENV['YML_FEED_DEPARTURE_NAME'] ?? 'Самара')));

    return $n !== '' ? $n : 'Самара';
}

/**
 * URL карточки тура для YML: без description/image/tour_link; при необходимости укорачивает query.
 *
 * @param array<string, string> $params
 */
function yandex_feed_trim_offer_url(string $siteBase, array $params, int $maxLen = 1900): string
{
    unset($params['description'], $params['image'], $params['tour_link']);

    $path = rtrim($siteBase, '/') . '/frontend/window/tour-detail.php?';
    $build = static function (array $p) use ($path): string {
        return $path . http_build_query($p, '', '&', PHP_QUERY_RFC3986);
    };

    $url = $build($params);
    if (strlen($url) <= $maxLen) {
        return $url;
    }

    foreach (['region', 'meal', 'hotel_name', 'room_category', 'rating', 'category', 'nights'] as $key) {
        if (!array_key_exists($key, $params)) {
            continue;
        }
        unset($params[$key]);
        $url = $build($params);
        if (strlen($url) <= $maxLen) {
            return $url;
        }
    }

    $minimal = [];
    foreach (['tour_id', 'hotel_id', 'from_promo', 'departure_city', 'departure_id', 'country', 'price'] as $k) {
        if (isset($params[$k]) && (string) $params[$k] !== '') {
            $minimal[$k] = $params[$k];
        }
    }
    $url = $build($minimal);
    if (strlen($url) > $maxLen) {
        error_log('[yandex_feed] offer_url exceeds limit tour_id=' . ($minimal['tour_id'] ?? '?') . ' len=' . strlen($url));
    }

    return $url;
}

function yandex_feed_extract_hotel_stars(array $h): ?int
{
    foreach (['category', 'hotelCategory', 'stars'] as $k) {
        if (!array_key_exists($k, $h) || $h[$k] === '' || $h[$k] === null) {
            continue;
        }
        $v = $h[$k];
        if (is_numeric($v)) {
            $n = (int) $v;

            return ($n >= 1 && $n <= 5) ? $n : null;
        }
        if (is_string($v) && preg_match('/([1-5])/', $v, $m)) {
            return (int) $m[1];
        }
    }

    return null;
}

/**
 * @return array{min: ?int, max: ?int, limit: int, require_stars: bool, sort_price: bool, russia_sochi_cap: bool}
 */
function yandex_feed_export_rule_for_country(int $countryId, string $countryNameNorm): array
{
    $default = [
        'min' => null,
        'max' => null,
        'limit' => 1000,
        'require_stars' => false,
        'sort_price' => true,
        'russia_sochi_cap' => false,
    ];
    $byId = [
        12 => ['min' => 5, 'max' => 5, 'limit' => 10, 'require_stars' => true, 'sort_price' => true, 'russia_sochi_cap' => false],
        1 => ['min' => 4, 'max' => 5, 'limit' => 10, 'require_stars' => true, 'sort_price' => true, 'russia_sochi_cap' => false],
        18 => ['min' => 3, 'max' => null, 'limit' => 10, 'require_stars' => true, 'sort_price' => true, 'russia_sochi_cap' => false],
        9 => ['min' => 3, 'max' => null, 'limit' => 5, 'require_stars' => true, 'sort_price' => true, 'russia_sochi_cap' => false],
        14 => ['min' => 4, 'max' => null, 'limit' => 5, 'require_stars' => true, 'sort_price' => true, 'russia_sochi_cap' => false],
        31 => ['min' => null, 'max' => null, 'limit' => 5, 'require_stars' => false, 'sort_price' => true, 'russia_sochi_cap' => false],
        30 => ['min' => null, 'max' => null, 'limit' => 5, 'require_stars' => false, 'sort_price' => true, 'russia_sochi_cap' => false],
        16 => ['min' => null, 'max' => null, 'limit' => 1000, 'require_stars' => false, 'sort_price' => true, 'russia_sochi_cap' => true],
    ];
    if (isset($byId[$countryId])) {
        return $byId[$countryId];
    }
    if (yandex_feed_is_georgia_or_uzbekistan_by_name($countryNameNorm)) {
        return ['min' => null, 'max' => null, 'limit' => 5, 'require_stars' => false, 'sort_price' => true, 'russia_sochi_cap' => false];
    }

    return $default;
}

function yandex_feed_row_passes_star_rule(?int $stars, array $rule): bool
{
    if (!$rule['require_stars']) {
        return true;
    }
    if ($stars === null) {
        return false;
    }
    if ($rule['min'] !== null && $stars < $rule['min']) {
        return false;
    }
    if ($rule['max'] !== null && $stars > $rule['max']) {
        return false;
    }

    return true;
}

function yandex_feed_region_is_sochi(string $region): bool
{
    return $region !== '' && (bool) preg_match('/сочи/ui', $region);
}

/** Лимит max 5 для Грузии / Узбекистана по названию (Tourvisor id в карте могут отсутствовать). */
function yandex_feed_is_georgia_or_uzbekistan_by_name(string $countryNameNorm): bool
{
    static $needles = [
        'грузия', 'грузии', 'грузию',
        'georgia', 'tbilisi', 'тбилиси',
        'узбекистан', 'узбекистана', 'узбекистану',
        'uzbekistan', 'uzbek',
    ];
    foreach ($needles as $n) {
        if (str_contains($countryNameNorm, $n)) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function yandex_feed_dedupe_rows_by_tour_id(array $rows): array
{
    $best = [];
    foreach ($rows as $row) {
        $tid = (string) ($row['tour_id'] ?? '');
        if ($tid === '') {
            continue;
        }
        if (!isset($best[$tid]) || (float) $row['price'] < (float) $best[$tid]['price']) {
            $best[$tid] = $row;
        }
    }

    return array_values($best);
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function yandex_feed_apply_export_limits(array $rows, int $countryId, string $countryName, int $maxPerCountry, ?array $ruleOverride = null): array
{
    $nameNorm = mb_strtolower($countryName);
    $rule = $ruleOverride ?? yandex_feed_export_rule_for_country($countryId, $nameNorm);
    $cap = min($maxPerCountry, max(1, (int) ($rule['limit'] ?? 1000)));

    if ($rule['sort_price']) {
        usort($rows, static function (array $a, array $b): int {
            return ((float) $a['price']) <=> ((float) $b['price']);
        });
    }

    if (!empty($rule['russia_sochi_cap']) && $countryId === 16) {
        $sochi = [];
        foreach ($rows as $r) {
            $reg = (string) ($r['_region'] ?? '');
            if (yandex_feed_region_is_sochi($reg)) {
                $sochi[] = $r;
            }
        }
        $rows = array_slice($sochi, 0, 5);
    } else {
        $rows = array_slice($rows, 0, $cap);
    }

    foreach ($rows as &$r) {
        unset($r['_region'], $r['_stars']);
    }
    unset($r);

    return $rows;
}

/**
 * @return array{title: string, description: string, price: float, picture: string, tour_id: string}|null
 */
function yandex_feed_row_from_hotel_tour(
    array $h,
    array $tour,
    string $countryNameFromApi,
    string $dateFrom,
    string $dateTo,
    string $siteBase,
    string $imageProxyBase,
    ?string $departureCityForUrl = null,
    bool $linkAsPromoTour = true
): ?array {
    $tid = $tour['id'] ?? $tour['tour_id'] ?? null;
    if ($tid === null || $tid === '') {
        return null;
    }
    $tid = (string) $tid;

    $price = (float) ($h['price'] ?? $tour['price'] ?? 0);
    if ($price <= 0) {
        return null;
    }

    $hotelName = trim((string) ($h['name'] ?? ''));
    $nights = $tour['nights'] ?? '';
    $nightsStr = $nights !== '' && $nights !== null ? (string) $nights : '';
    $title = $hotelName !== ''
        ? ($hotelName . ($nightsStr !== '' ? ' — ' . $nightsStr . ' ноч.' : ''))
        : (($tour['name'] ?? '') !== '' ? (string) $tour['name'] : 'Тур ' . $countryNameFromApi);

    $descRaw = trim((string) ($h['description'] ?? $h['hotelDescription'] ?? ''));
    if ($descRaw === '') {
        $descRaw = trim((string) ($tour['name'] ?? ''));
    }
    $description = $descRaw !== '' ? mb_substr($descRaw, 0, 1500) : mb_substr($title, 0, 500);

    $picRaw = yandex_feed_hotel_or_tour_picture_raw($h, $tour);
    if ($picRaw === '') {
        return null;
    }
    $pictureUrl = yandex_feed_abs_picture_url($picRaw, $siteBase, $imageProxyBase);

    $region = '';
    if (!empty($h['region']) && is_array($h['region'])) {
        $region = trim((string) ($h['region']['name'] ?? $h['region']['russianName'] ?? ''));
    }
    $meal = '';
    if (!empty($tour['meal']) && is_array($tour['meal'])) {
        $meal = trim((string) ($tour['meal']['russianName'] ?? $tour['meal']['name'] ?? ''));
    }
    // Без date_from/date_to: даты подтягиваются по tour_id в tour-detail.php, а стабильный URL
    // уменьшает дубли карточек в Яндекс.Бизнесе при каждом пересинке фида.
    // description/image/tour_link — только в YML-полях оффера, не в query (лимит url ≤2000).
    $params = [
        'country' => $countryNameFromApi,
        'hotel_name' => $hotelName,
        'price' => yandex_feed_format_price_rub($price),
        'nights' => $nightsStr,
        'meal' => $meal,
        'region' => $region,
        'departure_city' => ($departureCityForUrl !== null && trim($departureCityForUrl) !== '')
            ? trim($departureCityForUrl)
            : yandex_feed_departure_city_for_offer_url(),
        'rating' => (string) ($h['rating'] ?? ''),
        'category' => (string) ($h['category'] ?? ''),
        'room_category' => trim((string) ($tour['roomType'] ?? $h['roomCategory'] ?? 'Стандарт')) ?: 'Стандарт',
        'tour_id' => $tid,
        'from_promo' => $linkAsPromoTour ? '1' : '0',
    ];
    $hotelId = 0;
    if (!empty($h['id'])) {
        $params['hotel_id'] = (string) $h['id'];
        $hotelId = (int) $h['id'];
    }

    $offerUrl = yandex_feed_trim_offer_url($siteBase, $params);

    return [
        'title' => mb_substr($title, 0, 500),
        'description' => $description,
        'price' => $price,
        'picture' => $pictureUrl,
        'tour_id' => $tid,
        'offer_url' => $offerUrl,
        'hotel_id' => $hotelId,
    ];
}

/**
 * @param bool $strictFilterCountry если true — отбрасывать отели, у которых country.id в ответе API не совпадает с запрошенным $countryId (фид по правилам админки).
 * @return list<array<string, mixed>>
 */
function yandex_feed_parse_search_response(
    array $decoded,
    int $countryId,
    string $countryName,
    string $dateFrom,
    string $dateTo,
    string $siteBase,
    string $imageProxyBase,
    int $maxPerCountry,
    ?array $exportRuleOverride = null,
    ?string $departureCityForUrl = null,
    bool $linkAsPromoTour = true,
    bool $strictFilterCountry = false
): array {
    $out = [];
    if (empty($decoded['success']) || !is_array($decoded['data'] ?? null)) {
        return $out;
    }

    $nameNorm = mb_strtolower($countryName);
    $rule = $exportRuleOverride ?? yandex_feed_export_rule_for_country($countryId, $nameNorm);
    $rawTourSlots = 0;
    foreach ($decoded['data'] as $h0) {
        if (!is_array($h0)) {
            continue;
        }
        $t0 = $h0['tours'] ?? [];
        if (!is_array($t0)) {
            continue;
        }
        foreach ($t0 as $tour0) {
            if (is_array($tour0)) {
                $rawTourSlots++;
            }
        }
    }

    foreach ($decoded['data'] as $h) {
        if (!is_array($h)) {
            continue;
        }
        $tours = $h['tours'] ?? [];
        if (!is_array($tours)) {
            continue;
        }
        $stars = yandex_feed_extract_hotel_stars($h);
        if (!yandex_feed_row_passes_star_rule($stars, $rule)) {
            continue;
        }
        $region = '';
        if (!empty($h['region']) && is_array($h['region'])) {
            $region = trim((string) ($h['region']['name'] ?? $h['region']['russianName'] ?? ''));
        }
        $fromHotel = 0;
        if (!empty($h['country']) && is_array($h['country'])) {
            $fromHotel = (int) ($h['country']['id'] ?? 0);
        }
        if ($fromHotel <= 0) {
            $fromHotel = (int) ($h['countryId'] ?? 0);
        }
        if ($strictFilterCountry && $countryId > 0) {
            if ($fromHotel <= 0 || $fromHotel !== $countryId) {
                continue;
            }
        }
        foreach ($tours as $tour) {
            if (!is_array($tour)) {
                continue;
            }
            $cname = $countryName;
            if (!empty($h['country']) && is_array($h['country'])) {
                $cn = trim((string) ($h['country']['name'] ?? $h['country']['russianName'] ?? ''));
                if ($cn !== '') {
                    $cname = $cn;
                }
            }
            $row = yandex_feed_row_from_hotel_tour($h, $tour, $cname, $dateFrom, $dateTo, $siteBase, $imageProxyBase, $departureCityForUrl, $linkAsPromoTour);
            if ($row === null) {
                continue;
            }
            $row['country_id'] = $countryId > 0 ? $countryId : $fromHotel;
            if ($row['country_id'] <= 0) {
                $row['country_id'] = 0;
            }
            $row['country_name'] = $cname;
            $row['_region'] = $region;
            $row['_stars'] = $stars;
            $out[] = $row;
        }
    }

    $beforeDedupe = count($out);
    $out = yandex_feed_dedupe_rows_by_tour_id($out);
    $afterDedupe = count($out);
    $out = yandex_feed_apply_export_limits($out, $countryId, $countryName, $maxPerCountry, $exportRuleOverride);
    $afterLimit = count($out);

    error_log(sprintf(
        '[yandex_feed] countryId=%d tours_before_filter=%d rows_after_parse=%d after_dedupe=%d after_export_rules=%d (maxPerCountry=%d)',
        $countryId,
        $rawTourSlots,
        $beforeDedupe,
        $afterDedupe,
        $afterLimit,
        $maxPerCountry
    ));

    return $out;
}

/**
 * Удаляет все строки из yandex_feed_offers (нет активных правил / сброс).
 */
function yandex_feed_delete_all_offers(PDO $pdo): int
{
    yandex_feed_ensure_table($pdo);
    try {
        $n = $pdo->exec('DELETE FROM yandex_feed_offers');

        return $n === false ? 0 : (int) $n;
    } catch (PDOException $e) {
        error_log('[yandex_feed] delete_all_offers: ' . $e->getMessage());

        return 0;
    }
}

/**
 * Удаляет офферы стран, которых нет в списке разрешённых (после смены правил в админке).
 *
 * @param list<int> $allowedCountryIds
 */
function yandex_feed_delete_offers_not_in_country_ids(PDO $pdo, array $allowedCountryIds): int
{
    $allowedCountryIds = array_values(array_unique(array_filter(array_map(static fn ($x): int => (int) $x, $allowedCountryIds), static fn (int $x): bool => $x > 0)));
    if ($allowedCountryIds === []) {
        return yandex_feed_delete_all_offers($pdo);
    }
    yandex_feed_ensure_table($pdo);
    try {
        $placeholders = implode(',', array_fill(0, count($allowedCountryIds), '?'));
        $stmt = $pdo->prepare('DELETE FROM yandex_feed_offers WHERE country_id NOT IN (' . $placeholders . ')');
        $stmt->execute($allowedCountryIds);

        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log('[yandex_feed] delete_offers_not_in_country_ids: ' . $e->getMessage());

        return 0;
    }
}

/**
 * UPSERT строк в yandex_feed_offers (cron и хук акционного поиска).
 *
 * @param list<array<string, mixed>> $rows
 * @return array{inserted: int, errors: list<string>}
 */
function yandex_feed_upsert_rows(PDO $pdo, array $rows): array
{
    $inserted = 0;
    $errors = [];
    if ($rows === []) {
        return ['inserted' => 0, 'errors' => $errors];
    }

    yandex_feed_ensure_table($pdo);

    $suppressed = yandex_feed_suppressed_tour_id_set($pdo);

    $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    $isMysql = ($driver === 'mysql');

    $sqlMysqlUpsert = 'INSERT INTO yandex_feed_offers (tourvisor_tour_id, country_id, country_name, title, description, picture_url, price, offer_url, enabled, synced_at)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
           ON DUPLICATE KEY UPDATE country_id=VALUES(country_id), country_name=VALUES(country_name), title=VALUES(title), description=VALUES(description),
           picture_url=VALUES(picture_url), price=VALUES(price), offer_url=VALUES(offer_url), synced_at=NOW()';

    foreach ($rows as $row) {
        $tid = (string) ($row['tour_id'] ?? '');
        if ($tid !== '' && isset($suppressed[$tid])) {
            continue;
        }
        try {
            if ($isMysql) {
                $stmt = $pdo->prepare($sqlMysqlUpsert);
                $stmt->execute([
                    $row['tour_id'],
                    $row['country_id'],
                    $row['country_name'],
                    $row['title'],
                    $row['description'],
                    $row['picture'],
                    $row['price'],
                    $row['offer_url'],
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO yandex_feed_offers (tourvisor_tour_id, country_id, country_name, title, description, picture_url, price, offer_url, enabled, synced_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, datetime(\'now\'))
                         ON CONFLICT(tourvisor_tour_id) DO UPDATE SET
                           country_id=excluded.country_id,
                           country_name=excluded.country_name,
                           title=excluded.title,
                           description=excluded.description,
                           picture_url=excluded.picture_url,
                           price=excluded.price,
                           offer_url=excluded.offer_url,
                           synced_at=excluded.synced_at'
                );
                $stmt->execute([
                    $row['tour_id'],
                    $row['country_id'],
                    $row['country_name'],
                    $row['title'],
                    $row['description'],
                    $row['picture'],
                    $row['price'],
                    $row['offer_url'],
                ]);
            }
            $inserted++;
        } catch (PDOException $e) {
            $errors[] = 'tour ' . ($row['tour_id'] ?? '') . ': ' . $e->getMessage();
        }
    }

    return ['inserted' => $inserted, 'errors' => $errors];
}

/**
 * Удаляет все офферы страны из фида (полный синк: страна не дала строк после фильтров).
 */
function yandex_feed_delete_offers_for_country(PDO $pdo, int $countryId): int
{
    if ($countryId <= 0) {
        return 0;
    }
    yandex_feed_ensure_table($pdo);
    try {
        $stmt = $pdo->prepare('DELETE FROM yandex_feed_offers WHERE country_id = ?');
        $stmt->execute([$countryId]);

        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log('[yandex_feed] delete_offers_country ' . $countryId . ': ' . $e->getMessage());

        return 0;
    }
}

/**
 * Удаляет офферы страны, которых нет в актуальном наборе tour_id (устаревшие после синка).
 *
 * @param list<string> $keepTourIds
 */
function yandex_feed_prune_offers_for_country_except_tour_ids(PDO $pdo, int $countryId, array $keepTourIds): int
{
    if ($countryId <= 0) {
        return 0;
    }
    $keepTourIds = array_values(array_unique(array_filter(array_map('strval', $keepTourIds), static fn ($x) => $x !== '')));
    if ($keepTourIds === []) {
        return yandex_feed_delete_offers_for_country($pdo, $countryId);
    }
    yandex_feed_ensure_table($pdo);
    $placeholders = implode(',', array_fill(0, count($keepTourIds), '?'));
    $sql = 'DELETE FROM yandex_feed_offers WHERE country_id = ? AND tourvisor_tour_id NOT IN (' . $placeholders . ')';
    $params = array_merge([$countryId], $keepTourIds);
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log('[yandex_feed] prune_offers_country ' . $countryId . ': ' . $e->getMessage());

        return 0;
    }
}

/**
 * Удаляет строки yandex_feed_offers, которые не попадают в YML (пустые обязательные поля, цена ≤ 0).
 *
 * @return int число удалённых строк
 */
function yandex_feed_delete_invalid_offers(PDO $pdo): int
{
    yandex_feed_ensure_table($pdo);
    $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    if ($driver === 'mysql') {
        $sql = "DELETE FROM yandex_feed_offers WHERE
            TRIM(IFNULL(title, '')) = '' OR
            TRIM(IFNULL(offer_url, '')) = '' OR
            TRIM(IFNULL(tourvisor_tour_id, '')) = '' OR
            price IS NULL OR price <= 0";
    } else {
        $sql = "DELETE FROM yandex_feed_offers WHERE
            TRIM(COALESCE(title, '')) = '' OR
            TRIM(COALESCE(offer_url, '')) = '' OR
            TRIM(COALESCE(tourvisor_tour_id, '')) = '' OR
            price IS NULL OR price <= 0";
    }
    try {
        return $pdo->exec($sql) ?: 0;
    } catch (PDOException $e) {
        error_log('[yandex_feed] delete_invalid_offers: ' . $e->getMessage());

        return 0;
    }
}

/**
 * Удаляет из БД офферы, отключённые модерацией (enabled = 0).
 *
 * @return int число удалённых строк
 */
function yandex_feed_delete_disabled_offers(PDO $pdo): int
{
    yandex_feed_ensure_table($pdo);
    yandex_feed_ensure_suppression_table($pdo);
    $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    $isMysql = ($driver === 'mysql');
    try {
        if ($isMysql) {
            $pdo->exec(
                'INSERT IGNORE INTO yandex_feed_suppressed_tour_ids (tourvisor_tour_id)
                 SELECT tourvisor_tour_id FROM yandex_feed_offers WHERE enabled = 0'
            );
        } else {
            $pdo->exec(
                'INSERT OR IGNORE INTO yandex_feed_suppressed_tour_ids (tourvisor_tour_id)
                 SELECT tourvisor_tour_id FROM yandex_feed_offers WHERE enabled = 0'
            );
        }
        $stmt = $pdo->exec('DELETE FROM yandex_feed_offers WHERE enabled = 0');

        return (int) $stmt;
    } catch (PDOException $e) {
        error_log('[yandex_feed] delete_disabled_offers: ' . $e->getMessage());

        return 0;
    }
}

/**
 * Множество tourvisor_tour_id, которые не нужно снова добавлять из синка/поиска.
 *
 * @return array<string, true>
 */
function yandex_feed_suppressed_tour_id_set(PDO $pdo): array
{
    yandex_feed_ensure_suppression_table($pdo);
    try {
        $stmt = $pdo->query('SELECT tourvisor_tour_id FROM yandex_feed_suppressed_tour_ids');
        if ($stmt === false) {
            return [];
        }
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $out = [];
        foreach ($ids as $id) {
            $out[(string) $id] = true;
        }

        return $out;
    } catch (PDOException $e) {
        error_log('[yandex_feed] suppressed_tour_id_set: ' . $e->getMessage());

        return [];
    }
}

/**
 * Гибрид: подпитка фида из ответа акционного поиска (onlyPromo), вызывается из tourvisor-proxy.
 * Возвращает метрики для логов и опционально JSON-отладки в ответе прокси.
 *
 * @return array{
 *   inserted: int,
 *   ingest_enabled: bool,
 *   hotels_in_json: int,
 *   parsed_offer_rows: int,
 *   max_hook: int,
 *   upsert_stmt_ok: int,
 *   pdo_errors: list<string>,
 *   stop_reason: ?string
 * }
 */
function yandex_feed_ingest_search_response_trace(PDO $pdo, array $decoded, int $countryId, string $countryNameFallback, string $dateFrom, string $dateTo): array
{
    $ingestEnabled = filter_var(getenv('YML_FEED_INGEST_FROM_SEARCH') ?: ($_ENV['YML_FEED_INGEST_FROM_SEARCH'] ?? '1'), FILTER_VALIDATE_BOOLEAN);
    $hotelsInJson = is_array($decoded['data'] ?? null) ? count($decoded['data']) : 0;

    $base = [
        'inserted' => 0,
        'ingest_enabled' => $ingestEnabled,
        'hotels_in_json' => $hotelsInJson,
        'parsed_offer_rows' => 0,
        'max_hook' => 0,
        'upsert_stmt_ok' => 0,
        'pdo_errors' => [],
        'stop_reason' => null,
    ];

    if (!$ingestEnabled) {
        $base['stop_reason'] = 'YML_FEED_INGEST_FROM_SEARCH_off';

        return $base;
    }

    if (!yandex_feed_legacy_table_sync_enabled()) {
        $base['stop_reason'] = 'YANDEX_LEGACY_OFFERS_TABLE_SYNC_off';

        return $base;
    }

    $siteBase = rtrim((string) (getenv('SITE_URL') ?: ($_ENV['SITE_URL'] ?? 'https://travelhub63.ru')), '/');
    if (preg_match('#/frontend/?$#i', $siteBase)) {
        $siteBase = (string) preg_replace('#/frontend/?$#i', '', $siteBase);
        $siteBase = rtrim($siteBase, '/');
    }

    $imageProxyBase = $siteBase . '/backend/api/tourvisor-image-proxy.php';
    $maxHook = (int) (getenv('YML_FEED_HOOK_MAX_OFFERS') ?: ($_ENV['YML_FEED_HOOK_MAX_OFFERS'] ?? 20));
    $maxHook = max(1, min(50, $maxHook));
    $base['max_hook'] = $maxHook;

    $countryName = $countryNameFallback !== '' ? $countryNameFallback : ($countryId > 0 ? ('Страна ' . $countryId) : 'Акции');

    $rows = yandex_feed_parse_search_response($decoded, $countryId, $countryName, $dateFrom, $dateTo, $siteBase, $imageProxyBase, $maxHook);
    $base['parsed_offer_rows'] = count($rows);

    if ($rows === []) {
        $base['stop_reason'] = $hotelsInJson > 0 ? 'parse_yielded_zero_rows' : 'empty_data_array';

        return $base;
    }

    $res = yandex_feed_upsert_rows($pdo, $rows);
    $base['upsert_stmt_ok'] = $res['inserted'];
    $base['pdo_errors'] = $res['errors'];
    $base['inserted'] = $res['inserted'];
    if ($res['errors'] !== []) {
        $base['stop_reason'] = 'pdo_errors';
    }

    return $base;
}

/**
 * Гибрид: подпитка фида из ответа акционного поиска (onlyPromo), вызывается из tourvisor-proxy.
 */
function yandex_feed_ingest_search_response(PDO $pdo, array $decoded, int $countryId, string $countryNameFallback, string $dateFrom, string $dateTo): int
{
    $t = yandex_feed_ingest_search_response_trace($pdo, $decoded, $countryId, $countryNameFallback, $dateFrom, $dateTo);

    return $t['inserted'];
}

function yandex_feed_http_get(string $url, int $timeout): ?string
{
    $ctx = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\nUser-Agent: TravelHubYandexFeed/1.0\r\n",
        ],
        'ssl' => ['verify_peer' => true],
    ]);
    $raw = @file_get_contents($url, false, $ctx);

    return ($raw === false || $raw === '') ? null : $raw;
}

/**
 * Имена стран из Tourvisor (type=countries) — те же названия, что в поиске.
 *
 * @return array<int, string> countryId => название
 */
function yandex_feed_load_country_names(string $proxyBase, int $departureId, int $timeout = 30): array
{
    $q = http_build_query(['type' => 'countries', 'departureId' => (string) $departureId]);
    $url = $proxyBase . (str_contains($proxyBase, '?') ? '&' : '?') . $q;
    $raw = yandex_feed_http_get($url, $timeout);
    if ($raw === null) {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || empty($decoded['success']) || !is_array($decoded['data'] ?? null)) {
        return [];
    }
    $out = [];
    foreach ($decoded['data'] as $c) {
        if (!is_array($c)) {
            continue;
        }
        $id = (int) ($c['id'] ?? 0);
        $name = trim((string) ($c['russianName'] ?? $c['name'] ?? ''));
        if ($id > 0 && $name !== '') {
            $out[$id] = $name;
        }
    }

    return $out;
}

/**
 * Лимит стран для кнопки в админке (короткий запрос; полный список — CLI/крон).
 * YML_FEED_ADMIN_MAX_COUNTRIES: пусто = 15; 0 или all = без лимита.
 */
function yandex_feed_web_country_limit(): ?int
{
    $raw = getenv('YML_FEED_ADMIN_MAX_COUNTRIES');
    if ($raw === false || trim((string) $raw) === '') {
        return 15;
    }
    $t = trim((string) $raw);
    if ($t === '0' || strcasecmp($t, 'all') === 0) {
        return null;
    }
    $n = (int) $t;

    return $n > 0 ? $n : null;
}

/**
 * Синхронизация: опрос прокси по странам, UPSERT в yandex_feed_offers.
 *
 * @param array{country_limit?: int|null, honor_stop_file?: bool} $options country_limit — обрезать список стран (для веб-кнопки); honor_stop_file — реагировать на кнопку «Остановить» в админке
 * @return array{inserted: int, updated: int, errors: list<string>, countries_processed: int, countries_total_list: int, countries_capped: bool, stopped_by_user?: bool}
 */
function yandex_feed_sync_from_tourvisor(PDO $pdo, array $options = []): array
{
    yandex_feed_ensure_table($pdo);

    if (!yandex_feed_legacy_table_sync_enabled()) {
        return [
            'inserted' => 0,
            'updated' => 0,
            'errors' => [],
            'countries_processed' => 0,
            'countries_total_list' => 0,
            'countries_capped' => false,
            'stopped_by_user' => false,
            'legacy_sync_disabled' => true,
        ];
    }

    $honorStop = !empty($options['honor_stop_file']);
    if ($honorStop) {
        yandex_feed_sync_clear_stop_flag();
    }

    $siteBase = rtrim((string) (getenv('SITE_URL') ?: ($_ENV['SITE_URL'] ?? 'https://travelhub63.ru')), '/');
    if (preg_match('#/frontend/?$#i', $siteBase)) {
        $siteBase = (string) preg_replace('#/frontend/?$#i', '', $siteBase);
        $siteBase = rtrim($siteBase, '/');
    }

    $imageProxyBase = $siteBase . '/backend/api/tourvisor-image-proxy.php';

    require_once __DIR__ . DIRECTORY_SEPARATOR . 'yandex_yml_rules_schema.php';
    $policy = yandex_yml_feed_sync_policy($pdo);
    $limitByCountry = $policy['limit_by_country'];
    $strictByCountry = !$policy['legacy_map'];

    $map = require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'country_promo_tourvisor_map.php';
    if ($policy['legacy_map']) {
        $countryIds = $map['unique_country_ids'] ?? [];
        $rawIds = trim((string) (getenv('PROMO_TOURS_SYNC_COUNTRY_IDS') ?: ($_ENV['PROMO_TOURS_SYNC_COUNTRY_IDS'] ?? '')));
        if ($rawIds !== '') {
            $countryIds = array_values(array_unique(array_filter(array_map('intval', explode(',', $rawIds)), static fn ($x) => $x > 0)));
        }
    } else {
        $countryIds = $policy['country_ids'];
        if ($countryIds === []) {
            yandex_feed_delete_all_offers($pdo);

            return [
                'inserted' => 0,
                'updated' => 0,
                'errors' => [],
                'countries_processed' => 0,
                'countries_total_list' => 0,
                'countries_capped' => false,
                'stopped_by_user' => false,
            ];
        }
    }

    $countriesTotalList = count($countryIds);
    $countryLimit = $options['country_limit'] ?? null;
    $countriesCapped = false;
    if ($countryLimit !== null && $countryLimit > 0 && count($countryIds) > $countryLimit) {
        $countryIds = array_slice($countryIds, 0, $countryLimit);
        $countriesCapped = true;
    }

    $departureId = (int) (getenv('YML_FEED_DEPARTURE_ID') ?: ($_ENV['YML_FEED_DEPARTURE_ID'] ?? 1));
    $maxPerCountry = (int) (getenv('YML_FEED_MAX_PER_COUNTRY') ?: ($_ENV['YML_FEED_MAX_PER_COUNTRY'] ?? 18));
    $maxPerCountry = max(1, min(100, $maxPerCountry));
    $timeout = (int) (getenv('YML_FEED_HTTP_TIMEOUT') ?: ($_ENV['YML_FEED_HTTP_TIMEOUT'] ?? 180));
    $timeout = max(30, min(600, $timeout));
    $delaySec = (float) (getenv('YML_FEED_COUNTRY_DELAY_SEC') ?: ($_ENV['YML_FEED_COUNTRY_DELAY_SEC'] ?? 4));
    $delaySec = max(0.5, min(30.0, $delaySec));

    /** Живой запрос к API Tourvisor (live=1) — тяжелее; 0 = опора на кэш прокси, мягче для API */
    $syncLive = filter_var(getenv('YML_FEED_SYNC_LIVE') ?: ($_ENV['YML_FEED_SYNC_LIVE'] ?? '1'), FILTER_VALIDATE_BOOLEAN);

    $datesFrom = date('Y-m-d', strtotime('+1 day'));
    $datesTo = date('Y-m-d', strtotime('+60 days'));

    $proxyBase = get_tourvisor_proxy_http_base_url();

    $idToName = yandex_feed_load_country_names($proxyBase, $departureId, min(60, $timeout));
    foreach (($map['slug_to_id'] ?? []) as $slug => $cid) {
        $cid = (int) $cid;
        if (!isset($idToName[$cid])) {
            $idToName[$cid] = mb_convert_case(str_replace(['-', '_'], ' ', (string) $slug), MB_CASE_TITLE, 'UTF-8');
        }
    }

    $inserted = 0;
    $errors = [];
    $countriesProcessed = 0;
    $stoppedByUser = false;

    foreach ($countryIds as $idx => $countryId) {
        if ($honorStop && yandex_feed_sync_stop_requested()) {
            yandex_feed_sync_clear_stop_flag();
            $errors[] = 'Синхронизация остановлена по кнопке «Остановить синхронизацию».';
            $stoppedByUser = true;
            break;
        }

        $countryId = (int) $countryId;
        if ($countryId <= 0) {
            continue;
        }
        $countryName = $idToName[$countryId] ?? ('Страна ' . $countryId);

        $perCap = $limitByCountry !== [] && array_key_exists($countryId, $limitByCountry)
            ? max(1, min(500, (int) $limitByCountry[$countryId]))
            : $maxPerCountry;

        $params = [
            'type' => 'search-cached',
            'departureId' => (string) $departureId,
            'countryId' => (string) $countryId,
            'dateFrom' => $datesFrom,
            'dateTo' => $datesTo,
            'nightsFrom' => '3',
            'nightsTo' => '21',
            'adults' => '2',
            'onlyPromo' => '1',
        ];
        if ($syncLive) {
            $params['live'] = '1';
        }
        $q = http_build_query($params);
        $url = $proxyBase . (str_contains($proxyBase, '?') ? '&' : '?') . $q;

        $raw = yandex_feed_http_get($url, $timeout);
        if ($raw === null) {
            $errors[] = "countryId={$countryId}: нет ответа";
            if ($idx < count($countryIds) - 1 && $delaySec > 0) {
                usleep((int) round($delaySec * 1_000_000));
            }
            continue;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || empty($decoded['success'])) {
            $errors[] = 'countryId=' . $countryId . ': ' . (string) ($decoded['error'] ?? 'success=false');
            if ($idx < count($countryIds) - 1 && $delaySec > 0) {
                usleep((int) round($delaySec * 1_000_000));
            }
            continue;
        }

        $rows = yandex_feed_parse_search_response(
            $decoded,
            $countryId,
            $countryName,
            $datesFrom,
            $datesTo,
            $siteBase,
            $imageProxyBase,
            $perCap,
            null,
            null,
            true,
            $strictByCountry
        );

        if ($rows === []) {
            yandex_feed_delete_offers_for_country($pdo, $countryId);
        } else {
            $batch = yandex_feed_upsert_rows($pdo, $rows);
            $inserted += $batch['inserted'];
            $errors = array_merge($errors, $batch['errors']);
            $keepIds = [];
            foreach ($rows as $r) {
                if (!empty($r['tour_id'])) {
                    $keepIds[] = (string) $r['tour_id'];
                }
            }
            yandex_feed_prune_offers_for_country_except_tour_ids($pdo, $countryId, $keepIds);
        }
        $countriesProcessed++;

        if ($idx < count($countryIds) - 1 && $delaySec > 0) {
            usleep((int) round($delaySec * 1_000_000));
        }
    }

    if (!$policy['legacy_map'] && $countryIds !== []) {
        yandex_feed_delete_offers_not_in_country_ids($pdo, $countryIds);
    }

    return [
        'inserted' => $inserted,
        'updated' => 0,
        'errors' => $errors,
        'countries_processed' => $countriesProcessed,
        'countries_total_list' => $countriesTotalList,
        'countries_capped' => $countriesCapped,
        'stopped_by_user' => $stoppedByUser,
    ];
}
