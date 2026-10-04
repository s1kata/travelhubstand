<?php
declare(strict_types=1);

/**
 * Фильтры promo-выдачи для YML (Турция/Египет — от 6 ночей; Вьетнам — прямой перелёт).
 */

require_once __DIR__ . '/../config/departure_defaults.php';

/** @var array<string, bool|null> */
$GLOBALS['yandex_yml_tour_direct_cache'] = $GLOBALS['yandex_yml_tour_direct_cache'] ?? [];

function yandex_yml_is_vietnam_country(int $countryId): bool
{
    return in_array($countryId, [16, 18], true);
}

function yandex_yml_is_tr_or_eg_country(int $countryId): bool
{
    return in_array($countryId, [1, 4, 13], true);
}

/**
 * @param array<int, array<string, mixed>> $hotels
 * @return array<int, array<string, mixed>>
 */
function yandex_yml_filter_hotels_tr_eg_min_nights(array $hotels, int $countryId): array
{
    if (!yandex_yml_is_tr_or_eg_country($countryId)) {
        return $hotels;
    }
    $minN = 6;
    $out = [];
    foreach ($hotels as $hotel) {
        if (!is_array($hotel)) {
            continue;
        }
        $tours = $hotel['tours'] ?? [];
        if (!is_array($tours)) {
            continue;
        }
        $kept = [];
        foreach ($tours as $t) {
            if (!is_array($t)) {
                continue;
            }
            $n = (int) ($t['nights'] ?? 0);
            if ($n >= $minN) {
                $kept[] = $t;
            }
        }
        if ($kept === []) {
            continue;
        }
        $hotel['tours'] = array_values($kept);
        if (function_exists('th_promo_speed_hotel_min_price')) {
            $min = th_promo_speed_hotel_min_price($hotel);
            if ($min > 0) {
                $hotel['price'] = $min;
            }
        }
        $out[] = $hotel;
    }

    return $out;
}

function yandex_yml_norm_flight_text(string $s): string
{
    $s = mb_strtolower(trim($s));

    return str_replace('ё', 'е', $s);
}

/** @param array<string, mixed>|null $leg */
function yandex_yml_flight_leg_dep_port_text(?array $leg): string
{
    if ($leg === null || !isset($leg['departure']) || !is_array($leg['departure'])) {
        return '';
    }
    $port = $leg['departure']['port'] ?? null;
    if (!is_array($port)) {
        return '';
    }
    $bits = [];
    foreach (['shortName', 'name', 'id', 'code', 'iata', 'enName', 'enname'] as $k) {
        if (!isset($port[$k]) || (string) $port[$k] === '') {
            continue;
        }
        $x = yandex_yml_norm_flight_text((string) $port[$k]);
        if ($x !== '' && !in_array($x, $bits, true)) {
            $bits[] = $x;
        }
    }

    return implode(' ', $bits);
}

/** @return list<string> */
function yandex_yml_departure_hints(string $cityLabel, int $departureId): array
{
    $hints = [];
    $add = static function (string $x) use (&$hints): void {
        $x = yandex_yml_norm_flight_text($x);
        if ($x !== '' && !in_array($x, $hints, true)) {
            $hints[] = $x;
        }
    };
    $s = yandex_yml_norm_flight_text($cityLabel);
    if ($s !== '') {
        $add($s);
        if (mb_strpos($s, 'самар') !== false) {
            $add('самара');
            $add('курумоч');
            $add('kuf');
        }
        if (mb_strpos($s, 'москв') !== false) {
            $add('москва');
            $add('домодедово');
            $add('внуково');
            $add('шереметьево');
            $add('жуковский');
            $add('dme');
            $add('svo');
            $add('vko');
            $add('zia');
            $add('mow');
        }
    }
    $depNorm = th_departure_normalize_id($departureId);
    $iataMap = [
        1 => ['dme', 'svo', 'vko', 'zia', 'mow', 'москва', 'домодедово', 'шереметьево', 'внуково', 'жуковский'],
        7 => ['kuf', 'самара', 'курумоч'],
    ];
    foreach ($iataMap[$depNorm] ?? [] as $code) {
        $add($code);
    }

    return $hints;
}

function yandex_yml_flight_dep_text_blocked(string $depText): bool
{
    if ($depText === '') {
        return false;
    }
    foreach (['красноярск', 'krasnoyarsk', 'емельяново', 'kja'] as $blocked) {
        if (mb_strpos($depText, $blocked) !== false) {
            return true;
        }
    }

    return false;
}

function yandex_yml_dep_text_matches_hints(string $depText, array $hints): bool
{
    if ($depText === '') {
        return false;
    }
    foreach ($hints as $h) {
        if ($h === '') {
            continue;
        }
        if (strlen($h) === 3 && mb_strpos($depText, $h) !== false) {
            return true;
        }
        if (strlen($h) >= 2 && mb_strpos($depText, $h) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<array<string, mixed>> $flights
 * @return array<string, mixed>|null
 */
function yandex_yml_pick_tourvisor_flight_package(array $flights, string $cityLabel, int $departureId): ?array
{
    if ($flights === []) {
        return null;
    }
    $hints = yandex_yml_departure_hints($cityLabel, $departureId);
    if ($hints !== []) {
        foreach ($flights as $f) {
            if (!is_array($f)) {
                continue;
            }
            $fw = $f['forward'][0] ?? null;
            if (!is_array($fw)) {
                continue;
            }
            $depTxt = yandex_yml_flight_leg_dep_port_text($fw);
            if (yandex_yml_flight_dep_text_blocked($depTxt)) {
                continue;
            }
            if (yandex_yml_dep_text_matches_hints($depTxt, $hints)) {
                return $f;
            }
        }

        return null;
    }
    foreach ($flights as $f) {
        if (!is_array($f)) {
            continue;
        }
        $fw = $f['forward'][0] ?? null;
        if (!is_array($fw)) {
            continue;
        }
        if (yandex_yml_flight_dep_text_blocked(yandex_yml_flight_leg_dep_port_text($fw))) {
            continue;
        }
        if (!empty($f['isDefault'])) {
            return $f;
        }
    }
    foreach ($flights as $f) {
        if (!is_array($f)) {
            continue;
        }
        $fw = $f['forward'][0] ?? null;
        if (!is_array($fw)) {
            continue;
        }
        if (!yandex_yml_flight_dep_text_blocked(yandex_yml_flight_leg_dep_port_text($fw))) {
            return $f;
        }
    }

    return null;
}

/** @param array<string, mixed>|null $pkg */
function yandex_yml_flight_package_is_direct(?array $pkg): bool
{
    if ($pkg === null) {
        return false;
    }
    $fw = $pkg['forward'] ?? null;
    if (!is_array($fw) || count($fw) !== 1) {
        return false;
    }
    $bw = $pkg['backward'] ?? $pkg['back'] ?? null;
    if (is_array($bw) && count($bw) > 1) {
        return false;
    }

    return true;
}

function yandex_yml_tour_has_direct_flight(string $tourId, string $cityLabel, int $departureId, string $proxyBase, int $timeout): ?bool
{
    if ($tourId === '') {
        return false;
    }
    if (array_key_exists($tourId, $GLOBALS['yandex_yml_tour_direct_cache'])) {
        $v = $GLOBALS['yandex_yml_tour_direct_cache'][$tourId];

        return $v === null ? null : (bool) $v;
    }
    $params = [
        'type' => 'tour-flights',
        'tourId' => $tourId,
        'currency' => 'RUB',
    ];
    $url = $proxyBase . (strpos($proxyBase, '?') !== false ? '&' : '?') . http_build_query($params);
    $raw = yandex_feed_http_get($url, min($timeout, 30));
    if ($raw === null) {
        $GLOBALS['yandex_yml_tour_direct_cache'][$tourId] = null;

        return null;
    }
    $decoded = json_decode($raw, true);
    $flights = [];
    if (is_array($decoded)) {
        if (isset($decoded['flights']) && is_array($decoded['flights'])) {
            $flights = $decoded['flights'];
        } elseif (isset($decoded['data']['flights']) && is_array($decoded['data']['flights'])) {
            $flights = $decoded['data']['flights'];
        }
    }
    $pick = yandex_yml_pick_tourvisor_flight_package($flights, $cityLabel, $departureId);
    $isDirect = yandex_yml_flight_package_is_direct($pick);
    $GLOBALS['yandex_yml_tour_direct_cache'][$tourId] = $isDirect;

    return $isDirect;
}

/**
 * @param array<int, array<string, mixed>> $hotels
 * @return array<int, array<string, mixed>>
 */
function yandex_yml_filter_hotels_vietnam_direct(
    array $hotels,
    int $departureId,
    string $cityLabel,
    string $proxyBase,
    int $timeout
): array {
    $out = [];
    foreach ($hotels as $hotel) {
        if (!is_array($hotel)) {
            continue;
        }
        $tours = $hotel['tours'] ?? [];
        if (!is_array($tours)) {
            continue;
        }
        $kept = [];
        foreach ($tours as $t) {
            if (!is_array($t)) {
                continue;
            }
            $tid = (string) ($t['id'] ?? $t['tourId'] ?? '');
            if ($tid === '') {
                continue;
            }
            if (yandex_yml_tour_has_direct_flight($tid, $cityLabel, $departureId, $proxyBase, $timeout) !== true) {
                continue;
            }
            $kept[] = $t;
        }
        if ($kept === []) {
            continue;
        }
        $hotel['tours'] = array_values($kept);
        if (function_exists('th_promo_speed_hotel_min_price')) {
            $min = th_promo_speed_hotel_min_price($hotel);
            if ($min > 0) {
                $hotel['price'] = $min;
            }
        }
        $out[] = $hotel;
    }

    return $out;
}
