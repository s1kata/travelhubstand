<?php
/**
 * Отсев битых/мусорных цен Tourvisor без ручных «полов» по странам.
 * — нормализация полей (в т.ч. {value: N});
 * — totalPrice / priceRub приоритетнее сырого price из search;
 * — конфликт полей, выбросы внутри отеля и всей выдачи.
 */
declare(strict_types=1);

function th_tour_price_normalize_field(mixed $v): int
{
    if ($v === null || $v === '') {
        return 0;
    }
    if (is_array($v)) {
        foreach (['value', 'rub', 'amount', 'total'] as $k) {
            if (array_key_exists($k, $v)) {
                $n = th_tour_price_normalize_field($v[$k]);
                if ($n > 0) {
                    return $n;
                }
            }
        }

        return 0;
    }
    if (is_string($v)) {
        $s = trim(str_replace(["\xc2\xa0", ' '], '', $v));
        $s = str_replace(',', '.', $s);
        if ($s === '' || !preg_match('/^-?\d+(?:\.\d+)?$/', $s)) {
            return 0;
        }
        $v = $s;
    }
    if (!is_numeric($v)) {
        return 0;
    }
    $n = (int) round((float) $v);
    if ($n <= 0 || $n > 50000000) {
        return 0;
    }

    return $n;
}

/**
 * @return array{price:int,source:string,weak:bool,conflict:bool}
 */
function th_tour_price_pick_meta(array $tour): array
{
    $total = th_tour_price_normalize_field($tour['totalPrice'] ?? null);
    $rub = th_tour_price_normalize_field($tour['priceRub'] ?? null);
    $price = th_tour_price_normalize_field($tour['price'] ?? null);
    $cost = th_tour_price_normalize_field($tour['cost'] ?? null);
    $fuel = th_tour_price_normalize_field($tour['fuelCharge'] ?? null);

    $addFuel = static function (int $base) use ($fuel): int {
        if ($base <= 0) {
            return 0;
        }
        if ($fuel <= 0) {
            return $base;
        }
        // Если fuelCharge уже входит в totalPrice — не дублируем (эвристика: fuel < 35% base).
        if ($fuel < (int) round($base * 0.35)) {
            return $base + $fuel;
        }

        return $base;
    };

    if ($total > 0) {
        $package = $addFuel($total);
        $conflict = $price > 0 && ($price < (int) round($package * 0.45) || $price > (int) round($package * 2.2));

        return ['price' => $package, 'source' => 'totalPrice', 'weak' => false, 'conflict' => $conflict];
    }
    if ($rub > 0) {
        $package = $addFuel($rub);
        $conflict = $price > 0 && ($price < (int) round($package * 0.45) || $price > (int) round($package * 2.2));

        return ['price' => $package, 'source' => 'priceRub', 'weak' => false, 'conflict' => $conflict];
    }

    $candidates = [];
    foreach (['price' => $price, 'cost' => $cost] as $src => $val) {
        if ($val > 0) {
            $candidates[$src] = $addFuel($val);
        }
    }
    if ($candidates === []) {
        return ['price' => 0, 'source' => '', 'weak' => true, 'conflict' => false];
    }
    $vals = array_values($candidates);
    sort($vals);
    $min = $vals[0];
    $max = $vals[count($vals) - 1];
    if ($max > $min * 2.5) {
        return ['price' => $max, 'source' => 'max_weak', 'weak' => true, 'conflict' => true];
    }

    $source = array_key_first($candidates);

    return ['price' => $min, 'source' => (string) $source, 'weak' => true, 'conflict' => false];
}

/** Мин. ₽/чел/ночь для пакета с перелётом — от длины поездки, не от страны. */
function th_tour_price_ppna_absurd_floor(int $nights): float
{
    $n = max(0, $nights);
    if ($n >= 10) {
        return 4500.0;
    }
    if ($n >= 7) {
        return 3200.0;
    }
    if ($n >= 4) {
        return 2400.0;
    }

    return 1500.0;
}

function th_tour_price_pick_num(array $tour): int
{
    return th_tour_price_pick_meta($tour)['price'];
}

/** @return array<string, int> */
function th_tour_country_id_from_name_map(): array
{
    return [
        'таиланд' => 2, 'турция' => 4, 'египет' => 1, 'оаэ' => 9, 'вьетнам' => 16,
        'шри-ланка' => 12, 'шри ланка' => 12, 'sri lanka' => 12,
        'абхазия' => 46, 'россия' => 47, 'сочи' => 47, 'армения' => 53, 'грузия' => 54,
        'казахстан' => 78, 'belarus' => 57, 'беларусь' => 57,
    ];
}

function th_tour_country_id_from_name(string $name): int
{
    $key = mb_strtolower(trim($name), 'UTF-8');
    if ($key === '') {
        return 0;
    }

    return (int) (th_tour_country_id_from_name_map()[$key] ?? 0);
}

function th_tour_price_hotel_country_id(array $hotel): int
{
    $countryId = (int) ($hotel['country']['id'] ?? $hotel['_countryId'] ?? 0);
    if ($countryId <= 0 && isset($hotel['country']['name'])) {
        $countryId = th_tour_country_id_from_name((string) $hotel['country']['name']);
    }

    return $countryId;
}

function th_tour_price_percentile(array $sorted, float $p): int
{
    $n = count($sorted);
    if ($n === 0) {
        return 0;
    }
    if ($n === 1) {
        return (int) $sorted[0];
    }
    $idx = (int) floor(($n - 1) * $p);
    $idx = max(0, min($n - 1, $idx));

    return (int) $sorted[$idx];
}

/**
 * @param array<int, mixed> $hotels
 * @return array{
 *   prices: int[],
 *   median: int,
 *   p25: int,
 *   p75: int,
 *   low_fence: int,
 *   per_night_median: float,
 *   per_night_low_fence: float,
 *   adults: int,
 *   package_mode: bool
 * }
 */
function th_tour_price_build_batch_context(array $hotels, ?int $adults = 2, bool $packageMode = true): array
{
    $adults = max(1, min(9, (int) ($adults ?? 2)));
    $prices = [];
    $perNight = [];
    $ppnaList = [];
    $weakCount = 0;
    $pricedCount = 0;
    foreach ($hotels as $hotel) {
        if (!is_array($hotel)) {
            continue;
        }
        foreach ((array) ($hotel['tours'] ?? []) as $tour) {
            if (!is_array($tour)) {
                continue;
            }
            $meta = th_tour_price_pick_meta($tour);
            if ($meta['price'] <= 0) {
                continue;
            }
            $pricedCount++;
            if ($meta['weak']) {
                $weakCount++;
            }
            $prices[] = $meta['price'];
            $nights = max(1, (int) ($tour['nights'] ?? 0));
            $perNight[] = $meta['price'] / $nights;
            $tourAdults = isset($tour['adults']) ? max(1, min(9, (int) $tour['adults'])) : $adults;
            $ppnaList[] = $meta['price'] / ($nights * $tourAdults);
        }
    }
    sort($prices);
    $median = th_tour_price_percentile($prices, 0.5);
    $p25 = th_tour_price_percentile($prices, 0.25);
    $p75 = th_tour_price_percentile($prices, 0.75);
    $iqr = max(0, $p75 - $p25);
    if ($iqr < 1 && $median > 0) {
        $iqr = (int) round($median * 0.12);
    }
    $tukey = $p25 - (int) round($iqr * 1.5);
    $ratio = $median > 25000 ? (int) round($median * 0.38) : ($median > 8000 ? (int) round($median * 0.32) : 0);
    $lowFence = max(0, max($tukey, $ratio));
    $priceSpread = ($median > 0 && count($prices) >= 2)
        ? (max($prices) - min($prices)) / $median
        : 0.0;
    $weakRatio = $pricedCount > 0 ? ($weakCount / $pricedCount) : 0.0;

    sort($perNight);
    $pnMed = count($perNight) ? th_tour_price_percentile($perNight, 0.5) : 0.0;
    $pnP25 = count($perNight) ? th_tour_price_percentile($perNight, 0.25) : 0.0;
    $pnP75 = count($perNight) ? th_tour_price_percentile($perNight, 0.75) : 0.0;
    $pnIqr = max(0.0, $pnP75 - $pnP25);
    if ($pnIqr < 1.0 && $pnMed > 0) {
        $pnIqr = $pnMed * 0.12;
    }
    $pnFence = max(0.0, max($pnP25 - $pnIqr * 1.5, $pnMed > 0 ? $pnMed * 0.38 : 0.0));

    sort($ppnaList);
    $ppnaMed = count($ppnaList) ? (float) th_tour_price_percentile($ppnaList, 0.5) : 0.0;
    $ppnaP25 = count($ppnaList) ? (float) th_tour_price_percentile($ppnaList, 0.25) : 0.0;
    $ppnaP75 = count($ppnaList) ? (float) th_tour_price_percentile($ppnaList, 0.75) : 0.0;
    $ppnaIqr = max(0.0, $ppnaP75 - $ppnaP25);
    if ($ppnaIqr < 1.0 && $ppnaMed > 0) {
        $ppnaIqr = $ppnaMed * 0.12;
    }
    $ppnaFence = max(0.0, max($ppnaP25 - $ppnaIqr * 1.5, $ppnaMed > 0 ? $ppnaMed * 0.38 : 0.0));

    return [
        'prices' => $prices,
        'median' => $median,
        'p25' => $p25,
        'p75' => $p75,
        'low_fence' => $lowFence,
        'per_night_median' => (float) $pnMed,
        'per_night_low_fence' => (float) $pnFence,
        'ppna_median' => $ppnaMed,
        'ppna_low_fence' => $ppnaFence,
        'weak_ratio' => $weakRatio,
        'price_spread' => $priceSpread,
        'adults' => $adults,
        'package_mode' => $packageMode,
    ];
}

function th_tour_price_tour_adults(array $tour, array $ctx): int
{
    if (isset($tour['adults']) && (int) $tour['adults'] > 0) {
        return max(1, min(9, (int) $tour['adults']));
    }

    return max(1, min(9, (int) ($ctx['adults'] ?? 2)));
}

function th_tour_price_ppna(int $price, int $nights, int $adults): float
{
    return $price / (max(1, $nights) * max(1, $adults));
}

/**
 * @param array<string, mixed> $hotel
 * @return array<int, int>
 */
function th_tour_price_hotel_peer_prices(array $hotel, int $adults): array
{
    $out = [];
    foreach ((array) ($hotel['tours'] ?? []) as $tour) {
        if (!is_array($tour)) {
            continue;
        }
        $p = th_tour_price_pick_meta($tour)['price'];
        if ($p > 0) {
            $out[] = $p;
        }
    }
    if ($out === []) {
        $fallback = th_tour_price_pick_num($hotel);
        if ($fallback > 0) {
            $out[] = $fallback;
        }
    }
    sort($out);

    return $out;
}

/**
 * @return list<string>
 */
function th_tour_price_garbage_reasons(array $tour, array $hotel, array $ctx, ?int $hotelPeerMedian = null): array
{
    $reasons = [];
    $meta = th_tour_price_pick_meta($tour);
    $price = $meta['price'];
    if ($price <= 0) {
        return ['no_price'];
    }
    if ($price < 500) {
        $reasons[] = 'absurd_low';
    }

    $nights = max(0, (int) ($tour['nights'] ?? 0));
    $tourAdults = th_tour_price_tour_adults($tour, $ctx);
    $ppna = ($nights >= 1) ? th_tour_price_ppna($price, $nights, $tourAdults) : 0.0;

    if (!empty($ctx['package_mode'])) {
        if ($meta['conflict']) {
            $reasons[] = 'field_conflict';
        }
        /* Пакет с перелётом не может быть ~1k ₽/чел/ночь — битый price без totalPrice. */
        if ($nights >= 3 && $ppna > 0) {
            $absurdFloor = th_tour_price_ppna_absurd_floor($nights);
            if ($ppna < $absurdFloor) {
                $reasons[] = 'ppna_absurd';
            } elseif ($meta['weak'] && $ppna < $absurdFloor * 1.15) {
                $reasons[] = 'weak_ppna';
            }
        }
        $weakRatio = (float) ($ctx['weak_ratio'] ?? 0);
        $spread = (float) ($ctx['price_spread'] ?? 0);
        $sample = count($ctx['prices'] ?? []);
        $batchMedian = (int) ($ctx['median'] ?? 0);
        // Только для подозрительно дешёвых пакетов (15k/54k): не трогать SL/VN ~200k+ с полем price без totalPrice.
        if ($meta['weak'] && $sample >= 3 && $weakRatio >= 0.55 && $spread < 0.22
            && $batchMedian > 0 && $batchMedian < 75000) {
            $reasons[] = 'weak_uniform_cluster';
        }
    }

    $hotelAnchor = max(
        th_tour_price_normalize_field($hotel['minPrice'] ?? null),
        th_tour_price_normalize_field($hotel['price'] ?? null),
        th_tour_price_normalize_field($hotel['minprice'] ?? null)
    );
    if ($hotelAnchor > 0 && $price < (int) round($hotelAnchor * 0.48)) {
        $reasons[] = 'hotel_anchor_low';
    }

    if ($hotelPeerMedian !== null && $hotelPeerMedian > 0 && $price < (int) round($hotelPeerMedian * 0.42)) {
        $reasons[] = 'hotel_peer_outlier';
    }

    $sample = count($ctx['prices'] ?? []);
    if ($sample >= 3 && !empty($ctx['package_mode'])) {
        $fence = (int) ($ctx['low_fence'] ?? 0);
        if ($fence > 0 && $price < $fence) {
            $reasons[] = 'batch_outlier_low';
        }
        if ($nights >= 3 && ($ctx['per_night_low_fence'] ?? 0) > 0) {
            $ppn = $price / max(1, $nights);
            if ($ppn < (float) $ctx['per_night_low_fence']) {
                $reasons[] = 'batch_ppn_outlier';
            }
        }
        if ($nights >= 1 && ($ctx['ppna_low_fence'] ?? 0) > 0 && $ppna > 0 && $ppna < (float) $ctx['ppna_low_fence']) {
            $reasons[] = 'ppna_batch_outlier';
        }
    }

    return $reasons;
}

function th_tour_price_is_garbage_tour(array $tour, array $hotel, array $ctx, ?int $hotelPeerMedian = null): bool
{
    return th_tour_price_garbage_reasons($tour, $hotel, $ctx, $hotelPeerMedian) !== [];
}

/** @deprecated используйте th_tour_price_is_garbage_tour (инверсия) */
function th_tour_price_is_plausible(int $price, ?int $countryId = null, ?int $nights = null, ?int $adults = 2): bool
{
    if ($price <= 0) {
        return false;
    }
    $tour = ['price' => $price, 'nights' => $nights ?? 0, 'adults' => $adults];
    $hotel = ['country' => ['id' => (int) ($countryId ?? 0)]];
    $ctx = th_tour_price_build_batch_context([['tours' => [$tour]]], $adults, true);

    return !th_tour_price_is_garbage_tour($tour, $hotel, $ctx);
}

/**
 * @param array<string, mixed> $hotel
 * @return array<string, mixed>|null
 */
function th_tour_price_filter_hotel(array $hotel, array $ctx): ?array
{
    $adults = max(1, min(9, (int) ($ctx['adults'] ?? 2)));
    $tours = (array) ($hotel['tours'] ?? []);
    if ($tours === []) {
        if (!empty($ctx['package_mode'])) {
            return null;
        }
        $fallback = th_tour_price_pick_num($hotel);
        if ($fallback <= 0 || th_tour_price_is_garbage_tour(['price' => $fallback], $hotel, $ctx)) {
            return null;
        }

        return $hotel;
    }

    $peerPrices = th_tour_price_hotel_peer_prices($hotel, $adults);
    $peerMedian = count($peerPrices) ? (int) th_tour_price_percentile($peerPrices, 0.5) : null;

    $kept = [];
    $min = 0;
    foreach ($tours as $tour) {
        if (!is_array($tour)) {
            continue;
        }
        $peerForTour = null;
        if (count($peerPrices) >= 2) {
            $p = th_tour_price_pick_meta($tour)['price'];
            $others = array_values(array_filter($peerPrices, static fn(int $x) => $x !== $p));
            if ($others !== []) {
                sort($others);
                $peerForTour = th_tour_price_percentile($others, 0.5);
            } else {
                $peerForTour = $peerMedian;
            }
        }
        if (th_tour_price_is_garbage_tour($tour, $hotel, $ctx, $peerForTour)) {
            continue;
        }
        $p = th_tour_price_pick_meta($tour)['price'];
        $kept[] = $tour;
        if ($min === 0 || $p < $min) {
            $min = $p;
        }
    }
    if ($kept === []) {
        return null;
    }
    $hotel['tours'] = array_values($kept);
    if ($min > 0) {
        $hotel['price'] = $min;
        $hotel['minPrice'] = $min;
    }

    return $hotel;
}

/**
 * @param array<int, mixed> $hotels
 * @return array<int, mixed>
 */
function th_tour_price_filter_hotels(array $hotels, ?int $adults = 2): array
{
    $adults = max(1, min(9, (int) ($adults ?? 2)));
    $ctx = th_tour_price_build_batch_context($hotels, $adults, true);
    $out = [];
    foreach ($hotels as $hotel) {
        if (!is_array($hotel)) {
            continue;
        }
        $filtered = th_tour_price_filter_hotel($hotel, $ctx);
        if ($filtered !== null) {
            $out[] = $filtered;
        }
    }

    return $out;
}

function th_tour_price_hotel_min(array $hotel, ?int $adults = 2, ?array $ctx = null): int
{
    $adults = max(1, min(9, (int) ($adults ?? 2)));
    if ($ctx === null) {
        $ctx = th_tour_price_build_batch_context([$hotel], $adults, true);
    }
    $peer = th_tour_price_hotel_peer_prices($hotel, $adults);
    $peerMedian = count($peer) ? th_tour_price_percentile($peer, 0.5) : null;
    $min = 0;
    foreach ((array) ($hotel['tours'] ?? []) as $tour) {
        if (!is_array($tour)) {
            continue;
        }
        if (th_tour_price_is_garbage_tour($tour, $hotel, $ctx, $peerMedian)) {
            continue;
        }
        $p = th_tour_price_pick_meta($tour)['price'];
        if ($min === 0 || $p < $min) {
            $min = $p;
        }
    }
    if ($min > 0) {
        return $min;
    }
    $fallback = th_tour_price_pick_num($hotel);
    if ($fallback > 0 && !th_tour_price_is_garbage_tour(['price' => $fallback], $hotel, $ctx)) {
        return $fallback;
    }

    return 0;
}

/**
 * @param array<string, mixed> $hotel
 * @return array<string, mixed>|null
 */
function th_tour_price_cheapest_tour(array $hotel, ?int $adults = 2, ?array $ctx = null): ?array
{
    $adults = max(1, min(9, (int) ($adults ?? 2)));
    if ($ctx === null) {
        $ctx = th_tour_price_build_batch_context([$hotel], $adults, true);
    }
    $peer = th_tour_price_hotel_peer_prices($hotel, $adults);
    $peerMedian = count($peer) ? th_tour_price_percentile($peer, 0.5) : null;
    $best = null;
    $bestPrice = 0;
    foreach ((array) ($hotel['tours'] ?? []) as $tour) {
        if (!is_array($tour)) {
            continue;
        }
        if (th_tour_price_is_garbage_tour($tour, $hotel, $ctx, $peerMedian)) {
            continue;
        }
        $p = th_tour_price_pick_meta($tour)['price'];
        if ($best === null || $p < $bestPrice) {
            $best = $tour;
            $bestPrice = $p;
        }
    }

    return $best;
}

function th_tour_price_is_hotel_only_departure(?int $departureId): bool
{
    return (int) ($departureId ?? 0) === 99;
}
