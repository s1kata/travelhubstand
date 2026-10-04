<?php
/**
 * Загрузка акционных туров тем же способом, что фронт в country_promo_tours / promotions:
 * GET tourvisor-proxy.php?type=search-cached&onlyPromo=1&departureId&countryId&dateFrom&dateTo&nightsFrom=3&nightsTo=21&adults=2
 * Разбор j.data[]: отель h, туры h.tours[], страна h.country.name, курорт h.region.name, id тура tour.id.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'tourvisor_proxy_http_base.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'th_feature_flags.php';

function promo_tours_http_get(string $url, int $timeoutSeconds): ?string
{
    $ctx = stream_context_create([
        'http' => [
            'timeout' => $timeoutSeconds,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\nUser-Agent: Travelhub63PromoSync/1.0\r\n",
        ],
        'ssl' => ['verify_peer' => true],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false || $raw === '') {
        return null;
    }
    return $raw;
}

/**
 * Окно дат как в JS: +1 и +60 дней от «сегодня» (локальное время сервера; см. promotions-page.js).
 *
 * @return array{from: string, to: string}
 */
function promo_tours_tourvisor_date_window(): array
{
    return [
        'from' => date('Y-m-d', strtotime('+1 day')),
        'to' => date('Y-m-d', strtotime('+60 days')),
    ];
}

/**
 * Список countryId: из country_promo_tourvisor_map или PROMO_TOURS_SYNC_COUNTRY_IDS=12,1,2
 *
 * @return list<int>
 */
function promo_tours_sync_country_ids_to_query(): array
{
    $raw = trim((string)(getenv('PROMO_TOURS_SYNC_COUNTRY_IDS') ?: ($_ENV['PROMO_TOURS_SYNC_COUNTRY_IDS'] ?? '')));
    if ($raw !== '') {
        $parts = array_filter(array_map('trim', explode(',', $raw)), static fn ($x) => $x !== '');
        $ids = [];
        foreach ($parts as $p) {
            $ids[] = (int)$p;
        }
        return array_values(array_unique(array_filter($ids, static fn ($id) => $id > 0)));
    }
    $map = require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'country_promo_tourvisor_map.php';
    return array_map('intval', $map['unique_country_ids']);
}

function promo_tours_tourvisor_is_promo_flag_truthy(mixed $v): bool
{
    return $v === 1 || $v === '1' || $v === true;
}

/** Только если в JSON явно onlyPromo=1 (или на отеле). Иначе «акций нет». */
function promo_tours_row_is_strict_only_promo(array $tour, ?array $hotel = null): bool
{
    if (array_key_exists('onlypromo', $tour) || array_key_exists('onlyPromo', $tour)) {
        return promo_tours_tourvisor_is_promo_flag_truthy($tour['onlypromo'] ?? $tour['onlyPromo']);
    }
    if ($hotel !== null && (array_key_exists('onlypromo', $hotel) || array_key_exists('onlyPromo', $hotel))) {
        return promo_tours_tourvisor_is_promo_flag_truthy($hotel['onlypromo'] ?? $hotel['onlyPromo']);
    }

    return false;
}

function promo_tours_is_vietnam_country_id(int $countryId): bool
{
    return $countryId === 16 || $countryId === 18;
}

/**
 * @return int|false unix ts for sort
 */
function promo_tours_vietnam_fallback_tour_departure_ts(array $tour): int|false
{
    foreach (['date', 'startDate', 'departureDate', 'flydate', 'flyDate'] as $k) {
        if (empty($tour[$k]) || !is_scalar($tour[$k])) {
            continue;
        }
        $s = trim((string) $tour[$k]);
        if ($s === '') {
            continue;
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $s, $m)) {
            $t = strtotime($m[1] . ' 12:00:00');

            return $t !== false ? $t : false;
        }
    }

    return false;
}

/**
 * @return array{tour_id: string, country: string, city: string}|null
 */
function promo_tours_build_record_any_tour(array $hotel, array $tour): ?array
{
    $tid = $tour['id'] ?? $tour['tour_id'] ?? null;
    if ($tid === null || $tid === '') {
        return null;
    }
    $country = promo_tours_tourvisor_place_name($hotel['country'] ?? null, 'name', 'russianName');
    if ($country === '') {
        $country = promo_tours_tourvisor_place_name($tour['country'] ?? null, 'name', 'russianName');
    }
    $city = promo_tours_tourvisor_place_name($hotel['region'] ?? null, 'name', 'russianName');
    if ($city === '') {
        $city = promo_tours_tourvisor_place_name($tour['region'] ?? null, 'name', 'russianName');
    }
    if ($city === '' && isset($tour['city']) && is_string($tour['city'])) {
        $city = trim($tour['city']);
    }

    return [
        'tour_id' => (string) $tid,
        'country' => $country,
        'city' => $city,
    ];
}

/**
 * @return list<array{tour_id: string, country: string, city: string}>
 */
function promo_tours_parse_strict_only_promo_rows(array $decoded): array
{
    $out = [];
    if (empty($decoded['success']) || !is_array($decoded['data'] ?? null)) {
        return $out;
    }
    foreach ($decoded['data'] as $h) {
        if (!is_array($h)) {
            continue;
        }
        $tours = $h['tours'] ?? [];
        if (!is_array($tours)) {
            continue;
        }
        foreach ($tours as $tour) {
            if (!is_array($tour)) {
                continue;
            }
            if (!promo_tours_row_is_strict_only_promo($tour, $h)) {
                continue;
            }
            $rec = promo_tours_build_record_any_tour($h, $tour);
            if ($rec !== null) {
                $out[] = $rec;
            }
        }
    }

    return $out;
}

/**
 * @return list<array{tour_id: string, country: string, city: string}>
 */
function promo_tours_vietnam_nearest_rows_from_search(array $decoded, int $maxRows = 40): array
{
    $tmp = [];
    if (empty($decoded['success']) || !is_array($decoded['data'] ?? null)) {
        return [];
    }
    foreach ($decoded['data'] as $h) {
        if (!is_array($h)) {
            continue;
        }
        $tours = $h['tours'] ?? [];
        if (!is_array($tours)) {
            continue;
        }
        foreach ($tours as $tour) {
            if (!is_array($tour)) {
                continue;
            }
            $rec = promo_tours_build_record_any_tour($h, $tour);
            if ($rec === null) {
                continue;
            }
            $ts = promo_tours_vietnam_fallback_tour_departure_ts($tour);
            $tmp[] = ['row' => $rec, 'ts' => $ts === false ? PHP_INT_MAX : $ts];
        }
    }
    usort($tmp, static function (array $a, array $b): int {
        return ($a['ts'] ?? PHP_INT_MAX) <=> ($b['ts'] ?? PHP_INT_MAX);
    });
    $out = [];
    $seen = [];
    foreach ($tmp as $item) {
        $tid = $item['row']['tour_id'] ?? '';
        if ($tid === '' || isset($seen[$tid])) {
            continue;
        }
        $seen[$tid] = true;
        $out[] = $item['row'];
        if (count($out) >= $maxRows) {
            break;
        }
    }

    return $out;
}

/**
 * Запрос search-cached без onlyPromo + ближайшие вылеты (для Вьетнама при пустом/битом onlyPromo).
 *
 * @param array<string, mixed> $params базовые GET-параметры (с onlyPromo — будет снят)
 *
 * @return list<array{tour_id: string, country: string, city: string}>
 */
function promo_tours_vietnam_try_rows_without_only_promo(string $proxyBase, array $params, int $timeout): array
{
    if (!function_exists('th_feature_promo_vietnam_nearest_fallback') || !th_feature_promo_vietnam_nearest_fallback()) {
        return [];
    }
    try {
        $p = $params;
        if (isset($p['onlyPromo'])) {
            unset($p['onlyPromo']);
        }
        $q2 = http_build_query($p);
        $url2 = $proxyBase . (str_contains($proxyBase, '?') ? '&' : '?') . $q2;
        $raw2 = promo_tours_http_get($url2, $timeout);
        if ($raw2 === null || $raw2 === '') {
            return [];
        }
        $decoded2 = json_decode($raw2, true);
        if (!is_array($decoded2) || empty($decoded2['success'])) {
            return [];
        }

        return promo_tours_vietnam_nearest_rows_from_search($decoded2, 40);
    } catch (Throwable $e) {
        error_log('[promo_tours_vietnam_try_rows_without_only_promo] ' . $e->getMessage());

        return [];
    }
}

function promo_tours_tourvisor_tour_counts_as_promo(array $tour, ?array $hotel = null): bool
{
    if (array_key_exists('onlypromo', $tour) || array_key_exists('onlyPromo', $tour)) {
        return promo_tours_tourvisor_is_promo_flag_truthy($tour['onlypromo'] ?? $tour['onlyPromo']);
    }
    if ($hotel !== null && (array_key_exists('onlypromo', $hotel) || array_key_exists('onlyPromo', $hotel))) {
        return promo_tours_tourvisor_is_promo_flag_truthy($hotel['onlypromo'] ?? $hotel['onlyPromo']);
    }
    return true;
}

function promo_tours_tourvisor_place_name(mixed $bucket, string ...$keys): string
{
    if (!is_array($bucket)) {
        return '';
    }
    foreach ($keys as $k) {
        if (!empty($bucket[$k]) && is_string($bucket[$k])) {
            return trim($bucket[$k]);
        }
    }
    return '';
}

/**
 * @return array{tour_id: string, country: string, city: string}|null
 */
function promo_tours_build_record_tourvisor_hotel_tour(array $hotel, array $tour): ?array
{
    if (!promo_tours_tourvisor_tour_counts_as_promo($tour, $hotel)) {
        return null;
    }
    $tid = $tour['id'] ?? $tour['tour_id'] ?? null;
    if ($tid === null || $tid === '') {
        return null;
    }
    $country = promo_tours_tourvisor_place_name($hotel['country'] ?? null, 'name', 'russianName');
    if ($country === '') {
        $country = promo_tours_tourvisor_place_name($tour['country'] ?? null, 'name', 'russianName');
    }
    $city = promo_tours_tourvisor_place_name($hotel['region'] ?? null, 'name', 'russianName');
    if ($city === '') {
        $city = promo_tours_tourvisor_place_name($tour['region'] ?? null, 'name', 'russianName');
    }
    if ($city === '' && isset($tour['city']) && is_string($tour['city'])) {
        $city = trim($tour['city']);
    }

    return [
        'tour_id' => (string)$tid,
        'country' => $country,
        'city' => $city,
    ];
}

/**
 * @return list<array{tour_id: string, country: string, city: string}>
 */
function promo_tours_parse_tourvisor_search_response(array $decoded): array
{
    $out = [];
    if (empty($decoded['success']) || !is_array($decoded['data'] ?? null)) {
        return $out;
    }
    foreach ($decoded['data'] as $h) {
        if (!is_array($h)) {
            continue;
        }
        $tours = $h['tours'] ?? [];
        if (!is_array($tours)) {
            continue;
        }
        foreach ($tours as $tour) {
            if (!is_array($tour)) {
                continue;
            }
            $rec = promo_tours_build_record_tourvisor_hotel_tour($h, $tour);
            if ($rec !== null) {
                $out[] = $rec;
            }
        }
    }
    return $out;
}

/**
 * @param array<string, mixed> $config из promo_tours_sync/config.php
 * @return array{
 *   rows: list<array{tour_id: string, country: string, city: string}>,
 *   countries_total: int,
 *   countries_ok: int,
 *   countries_failed: int,
 *   errors: list<string>
 * }
 */
function promo_tours_sync_fetch_via_tourvisor(array $config): array
{
    $proxyBase = get_tourvisor_proxy_http_base_url();
    $countryIds = promo_tours_sync_country_ids_to_query();
    if ($countryIds === []) {
        return [
            'rows' => [],
            'countries_total' => 0,
            'countries_ok' => 0,
            'countries_failed' => 0,
            'errors' => ['Нет countryId для опроса (проверьте country_promo_tourvisor_map или PROMO_TOURS_SYNC_COUNTRY_IDS).'],
        ];
    }

    $dates = promo_tours_tourvisor_date_window();
    $departureId = (int)($config['tourvisor_departure_id'] ?? 1);
    $timeout = (int)($config['http_timeout_seconds'] ?? 120);
    $delaySec = (float)($config['tourvisor_delay_seconds'] ?? 2.0);
    $live = !empty($config['tourvisor_live']);

    $allRows = [];
    $ok = 0;
    $failed = 0;
    $errors = [];

    foreach ($countryIds as $idx => $countryId) {
        $params = [
            'type' => 'search-cached',
            'departureId' => (string)$departureId,
            'countryId' => (string)$countryId,
            'dateFrom' => $dates['from'],
            'dateTo' => $dates['to'],
            'nightsFrom' => '3',
            'nightsTo' => '21',
            'adults' => '2',
            'onlyPromo' => '1',
        ];
        if ($live) {
            $params['live'] = '1';
        }
        $q = http_build_query($params);
        $url = $proxyBase . (str_contains($proxyBase, '?') ? '&' : '?') . $q;

        $raw = promo_tours_http_get($url, $timeout);
        $rowsThis = [];
        $isVn = promo_tours_is_vietnam_country_id($countryId);
        $vnFlag = function_exists('th_feature_promo_vietnam_nearest_fallback') && th_feature_promo_vietnam_nearest_fallback();

        if ($raw === null) {
            $failed++;
            $errors[] = "countryId={$countryId}: нет ответа HTTP";
            if ($isVn && $vnFlag) {
                $rowsThis = promo_tours_vietnam_try_rows_without_only_promo($proxyBase, $params, $timeout);
            }
        } else {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                $failed++;
                $errors[] = "countryId={$countryId}: невалидный JSON";
                if ($isVn && $vnFlag) {
                    $rowsThis = promo_tours_vietnam_try_rows_without_only_promo($proxyBase, $params, $timeout);
                }
            } elseif (empty($decoded['success'])) {
                $failed++;
                $err = isset($decoded['error']) ? (string) $decoded['error'] : 'success=false';
                $errors[] = "countryId={$countryId}: {$err}";
                if ($isVn && $vnFlag) {
                    $rowsThis = promo_tours_vietnam_try_rows_without_only_promo($proxyBase, $params, $timeout);
                }
            } else {
                $ok++;
                try {
                    if ($vnFlag && $isVn) {
                        $strict = promo_tours_parse_strict_only_promo_rows($decoded);
                        if ($strict === []) {
                            $rowsThis = promo_tours_vietnam_try_rows_without_only_promo($proxyBase, $params, $timeout);
                            if ($rowsThis === []) {
                                $rowsThis = promo_tours_parse_tourvisor_search_response($decoded);
                            }
                        } else {
                            $rowsThis = promo_tours_parse_tourvisor_search_response($decoded);
                        }
                    } else {
                        $rowsThis = promo_tours_parse_tourvisor_search_response($decoded);
                    }
                } catch (Throwable $e) {
                    $errors[] = 'countryId=' . $countryId . ': fallback ' . $e->getMessage();
                    $rowsThis = is_array($decoded ?? null) ? promo_tours_parse_tourvisor_search_response($decoded) : [];
                }
            }
        }
        foreach ($rowsThis as $row) {
            $allRows[] = $row;
        }

        if ($idx < count($countryIds) - 1 && $delaySec > 0) {
            usleep((int)round($delaySec * 1_000_000));
        }
    }

    return [
        'rows' => $allRows,
        'countries_total' => count($countryIds),
        'countries_ok' => $ok,
        'countries_failed' => $failed,
        'errors' => $errors,
    ];
}
