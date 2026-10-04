<?php
declare(strict_types=1);

/**
 * Единый город вылета для сайта (Самара).
 */
function th_departure_default_id(): int
{
    $v = getenv('PROMO_DEFAULT_DEPARTURE_ID')
        ?: ($_ENV['PROMO_DEFAULT_DEPARTURE_ID'] ?? null)
        ?: getenv('YML_FEED_DEPARTURE_ID')
        ?: ($_ENV['YML_FEED_DEPARTURE_ID'] ?? null);
    $id = (int) ($v ?: 7);
    return th_departure_normalize_id($id > 0 ? $id : 7);
}

function th_departure_default_name(): string
{
    $n = trim((string) (
        getenv('PROMO_DEFAULT_DEPARTURE_NAME')
        ?: ($_ENV['PROMO_DEFAULT_DEPARTURE_NAME'] ?? '')
        ?: 'Самара'
    ));
    return $n !== '' ? $n : 'Самара';
}

/** Города вылета, которые не показываем и не подставляем автоматически. */
function th_departure_blocked_names_normalized(): array
{
    return ['красноярск', 'krasnoyarsk'];
}

function th_departure_is_blocked_name(string $name): bool
{
    $n = mb_strtolower(trim($name));
    if ($n === '') {
        return false;
    }
    return in_array($n, th_departure_blocked_names_normalized(), true);
}

/** Убирает заблокированные города из справочника Tourvisor (type=departures). */
function th_departure_filter_list(array $list): array
{
    return array_values(array_filter($list, static function ($item): bool {
        if (!is_array($item)) {
            return false;
        }
        $name = (string) ($item['name'] ?? $item['russianName'] ?? '');
        return !th_departure_is_blocked_name($name);
    }));
}

/** ID 12/28 — устаревшие дефолты (12 → Красноярск в выдаче, 28 → Оренбург в справочнике). */
function th_departure_normalize_id(int $id): int
{
    if ($id === 12) {
        return th_departure_default_id();
    }
    if ($id === 28) {
        return 1;
    }

    return $id > 0 ? $id : th_departure_default_id();
}

/** ID города вылета из запроса (departureId / departure) или дефолт Самара. */
function th_departure_id_from_request(): int
{
    $name = trim((string) ($_GET['departureName'] ?? ''));
    if ($name !== '' && th_departure_is_blocked_name($name)) {
        return th_departure_default_id();
    }
    $id = (int) ($_GET['departureId'] ?? $_GET['departure'] ?? 0);

    return th_departure_normalize_id($id);
}

/** IATA-коды вылета в названии тура (XXX-YYY) для конкретного departureId Tourvisor. */
function th_departure_allowed_route_from_codes(int $departureId): ?array
{
    $map = [
        7 => ['KUF'],
        1 => ['DME', 'SVO', 'VKO', 'ZIA', 'MOW'],
    ];

    return $map[$departureId] ?? null;
}

/** Tourvisor иногда отдаёт туры из Красноярска даже при departureId Самара — отсекаем по названию. */
function th_departure_tour_name_is_blocked(string $name): bool
{
    if ($name === '') {
        return false;
    }
    $nameLower = mb_strtolower($name);
    if (preg_match('/\bKJA\b/i', $name)) {
        return true;
    }
    if (preg_match('/\bKrasnoyarsk\b/i', $name)) {
        return true;
    }
    if (preg_match('/краснояр/u', $nameLower)) {
        return true;
    }
    if (preg_match('/\bемельяново\b/ui', $nameLower)) {
        return true;
    }

    return false;
}

function th_departure_tour_matches_departure(int $departureId, array $tour): bool
{
    $name = (string) ($tour['name'] ?? '');
    if (th_departure_tour_name_is_blocked($name)) {
        return false;
    }
    /* Самара (7): дальние пакеты часто с MOW-* в name при корректном departureId=7 — не режем по IATA */
    if ($departureId === 7) {
        return true;
    }
    if ($name !== '' && preg_match_all('/(?:^|[^A-Z])([A-Z]{3})-([A-Z]{3})(?:[^A-Z]|$)/', strtoupper($name), $matches, PREG_SET_ORDER)) {
        $allowed = th_departure_allowed_route_from_codes($departureId);
        foreach ($matches as $m) {
            $from = $m[1];
            if ($from === 'KJA') {
                return false;
            }
            if ($allowed !== null && !in_array($from, $allowed, true)) {
                return false;
            }
        }
    }

    return true;
}

/**
 * Оставляет только туры, соответствующие городу вылета; пересчитывает price отеля.
 *
 * @param array<int, array<string, mixed>> $hotels
 * @return array<int, array<string, mixed>>
 */
function th_departure_filter_hotels_for_departure(array $hotels, int $departureId): array
{
    if ($departureId <= 0 || $hotels === []) {
        return $hotels;
    }

    $out = [];
    foreach ($hotels as $hotel) {
        if (!is_array($hotel)) {
            continue;
        }
        $kept = [];
        foreach (($hotel['tours'] ?? []) as $t) {
            if (is_array($t) && th_departure_tour_matches_departure($departureId, $t)) {
                $kept[] = $t;
            }
        }
        if ($kept === []) {
            continue;
        }
        $hotel['tours'] = $kept;
        $min = 0;
        foreach ($kept as $t) {
            $p = (int) ($t['totalPrice'] ?? $t['price'] ?? 0);
            if ($p > 0 && ($min === 0 || $p < $min)) {
                $min = $p;
            }
        }
        if ($min > 0) {
            $hotel['price'] = $min;
        }
        $out[] = $hotel;
    }

    return $out;
}
