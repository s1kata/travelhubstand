<?php
/**
 * Tourvisor API «Горящие туры» — GET /tours/hots
 * @see https://api.tourvisor.ru/search/docs (раздел tour-hot)
 */
declare(strict_types=1);

/** @return array{dateFrom: string, dateTo: string} */
function th_tourvisor_hots_clamp_date_range(string $dateFrom, string $dateTo): array
{
    $tFrom = strtotime($dateFrom);
    if ($tFrom === false) {
        $dateFrom = date('Y-m-d');
        $tFrom = strtotime($dateFrom);
    }
    $tTo = strtotime($dateTo);
    if ($tTo === false) {
        $dateTo = date('Y-m-d', strtotime('+7 days'));
        $tTo = strtotime($dateTo);
    }
    if ($tFrom !== false && $tTo !== false) {
        if ($tTo < $tFrom) {
            $dateTo = date('Y-m-d', $tFrom + 7 * 86400);
            $tTo = strtotime($dateTo);
        }
        $diff = (int) (($tTo - $tFrom) / 86400);
        if ($diff > 21) {
            $dateTo = date('Y-m-d', $tFrom + 21 * 86400);
        }
    }

    return ['dateFrom' => $dateFrom, 'dateTo' => $dateTo];
}

/**
 * @return array<string, mixed>
 */
function th_tourvisor_hots_build_query(int $departureId, int $countryId, string $dateFrom, string $dateTo, int $limit = 200): array
{
    $dates = th_tourvisor_hots_clamp_date_range($dateFrom, $dateTo);

    return [
        'departureId' => $departureId,
        'countryIds' => [$countryId],
        'dateFrom' => $dates['dateFrom'],
        'dateTo' => $dates['dateTo'],
        'currency' => 'RUB',
        'onlyCharter' => false,
        'limit' => min(200, max(1, $limit)),
    ];
}

/**
 * @return array{success: bool, data?: array, error?: string, query?: array}
 */
function th_tourvisor_hots_fetch(int $departureId, int $countryId, string $dateFrom, string $dateTo, int $limit = 200): array
{
    if (!function_exists('tvRequest')) {
        return ['success' => false, 'error' => 'Tourvisor proxy not loaded', 'data' => []];
    }
    if ($departureId <= 0 || $countryId <= 0) {
        return ['success' => false, 'error' => 'departureId and countryId required', 'data' => []];
    }

    $query = th_tourvisor_hots_build_query($departureId, $countryId, $dateFrom, $dateTo, $limit);
    $res = tvRequest('/tours/hots', $query);
    if (empty($res['success'])) {
        return [
            'success' => false,
            'error' => (string) ($res['error'] ?? 'hots request failed'),
            'data' => [],
            'query' => $query,
        ];
    }

    $rows = $res['data'] ?? null;
    if ($rows === null) {
        return ['success' => true, 'data' => [], 'query' => $query];
    }
    if (is_array($rows) && isset($rows['data']) && is_array($rows['data'])) {
        $rows = $rows['data'];
    }
    if (!is_array($rows)) {
        return ['success' => false, 'error' => 'Invalid hots response', 'data' => [], 'query' => $query];
    }

    return ['success' => true, 'data' => array_values($rows), 'query' => $query];
}

/**
 * Полная стоимость пакета для карточек (как GET /tours/{id}).
 * /tours/hots отдаёт price за 1 взрослого при DBL — умножаем на число взрослых.
 */
function th_tourvisor_hots_package_price(array $row, int $adults = 2): int
{
    $fuel = (int) ($row['fuelCharge'] ?? $row['fuelcharge'] ?? 0);
    $explicit = (int) ($row['totalPrice'] ?? 0);
    if ($explicit > 0) {
        return $explicit + $fuel;
    }
    $price = (int) ($row['price'] ?? 0);
    if ($price <= 0) {
        return 0;
    }
    $adultsN = max(1, min(9, (int) ($row['adults'] ?? $adults)));
    $placement = strtoupper(trim((string) ($row['placement'] ?? 'DBL')));
    if ($adultsN >= 2 && ($placement === 'DBL' || $placement === '')) {
        $price = $price * $adultsN;
    }

    return $price + $fuel;
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function th_tourvisor_hots_row_to_tour(array $row, int $adults = 2): array
{
    $packagePrice = th_tourvisor_hots_package_price($row, $adults);
    $priceOldRaw = (int) ($row['priceOld'] ?? 0);
    $adultsN = max(1, min(9, (int) ($row['adults'] ?? $adults)));
    $priceOld = $priceOldRaw;
    if ($priceOldRaw > 0 && $adultsN >= 2 && (int) ($row['totalPrice'] ?? 0) <= 0) {
        $priceOld = $priceOldRaw * $adultsN;
    }
    $tourId = (string) ($row['tourId'] ?? $row['id'] ?? '');
    $operator = $row['operator'] ?? null;
    $meal = $row['meal'] ?? null;

    return [
        'id' => $tourId,
        'tourId' => $tourId,
        'name' => 'Горящий тур',
        'date' => (string) ($row['date'] ?? ''),
        'nights' => (int) ($row['nights'] ?? 0),
        'flightNights' => 0,
        'price' => $packagePrice,
        'totalPrice' => $packagePrice,
        'priceOld' => $priceOld,
        'oldPrice' => $priceOld > 0 ? $priceOld : null,
        'currency' => (string) ($row['currency'] ?? 'RUB'),
        'placement' => (string) ($row['placement'] ?? 'DBL'),
        'adults' => $adultsN,
        'childs' => 0,
        'meal' => is_array($meal) ? $meal : null,
        'operator' => is_array($operator) ? $operator : null,
        'isCharter' => true,
        'isPromo' => true,
        'isHot' => true,
    ];
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, mixed>>
 */
function th_tourvisor_hots_rows_to_hotels(array $rows, int $expectedCountryId = 0, int $adults = 2): array
{
    $ec = $expectedCountryId > 0 ? (string) $expectedCountryId : '';
    $byHotel = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $hotel = $row['hotel'] ?? null;
        if (!is_array($hotel)) {
            continue;
        }
        $hid = (int) ($hotel['id'] ?? 0);
        if ($hid <= 0) {
            continue;
        }

        $country = $row['country'] ?? $hotel['country'] ?? null;
        if ($ec !== '' && is_array($country) && isset($country['id'])) {
            if ((string) $country['id'] !== $ec) {
                continue;
            }
        }

        $tour = th_tourvisor_hots_row_to_tour($row, $adults);
        $tid = (string) ($tour['id'] ?? '');
        if ($tid === '') {
            continue;
        }

        if (!isset($byHotel[$hid])) {
            $pic = (string) ($hotel['picturelink'] ?? '');
            if ($pic === '' && $hid > 0) {
                $pic = 'https://static.tourvisor.ru/hotel_pics/main400/' . $hid . '.jpg';
            }
            $byHotel[$hid] = [
                'id' => $hid,
                'name' => (string) ($hotel['name'] ?? ''),
                'category' => (int) ($hotel['category'] ?? 0),
                'country' => $country,
                'region' => $hotel['region'] ?? null,
                'subRegion' => $hotel['subRegion'] ?? null,
                'rating' => $hotel['rating'] ?? null,
                'price' => (int) ($tour['price'] ?? 0),
                'currency' => (string) ($row['currency'] ?? 'RUB'),
                'hotelDescription' => '',
                'hotelDescriptionLink' => (string) ($hotel['hotelDescriptionLink'] ?? ''),
                'hasDescription' => !empty($hotel['hotelDescriptionLink']),
                'hasPictures' => $pic !== '',
                'picturelink' => $pic,
                'latitude' => $hotel['latitude'] ?? null,
                'longitude' => $hotel['longitude'] ?? null,
                'tours' => [],
            ];
        }

        $existingIds = [];
        foreach ($byHotel[$hid]['tours'] as $t) {
            if (is_array($t) && isset($t['id'])) {
                $existingIds[(string) $t['id']] = true;
            }
        }
        if (isset($existingIds[$tid])) {
            continue;
        }
        $byHotel[$hid]['tours'][] = $tour;

        $p = (int) ($tour['price'] ?? 0);
        if ($p > 0 && ((int) ($byHotel[$hid]['price'] ?? 0) === 0 || $p < (int) $byHotel[$hid]['price'])) {
            $byHotel[$hid]['price'] = $p;
        }
    }

    return array_values($byHotel);
}

/**
 * @return array{success: bool, data: array<int, array<string, mixed>>, error?: string, query?: array, hotsCount?: int}
 */
function th_tourvisor_hots_fetch_hotels(int $departureId, int $countryId, string $dateFrom, string $dateTo, int $limit = 200, int $adults = 2): array
{
    $res = th_tourvisor_hots_fetch($departureId, $countryId, $dateFrom, $dateTo, $limit);
    if (empty($res['success'])) {
        return [
            'success' => false,
            'data' => [],
            'error' => $res['error'] ?? 'hots failed',
            'query' => $res['query'] ?? null,
        ];
    }
    $rows = is_array($res['data'] ?? null) ? $res['data'] : [];
    $hotels = th_tourvisor_hots_rows_to_hotels($rows, $countryId, max(1, min(9, $adults)));

    return [
        'success' => true,
        'data' => $hotels,
        'hotsCount' => count($rows),
        'query' => $res['query'] ?? null,
    ];
}
