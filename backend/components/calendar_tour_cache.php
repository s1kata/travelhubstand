<?php
/**
 * Rolling cache for the deals calendar.
 *
 * Isolated from promo_cache (акции): this layer only stores data assembled from
 * search cover into data/calendar_cache/. Promo warm never writes here, and
 * calendar warm/API never reads or writes data/promo_cache_*.
 */
declare(strict_types=1);

require_once __DIR__ . '/promo_speed_cache.php';
require_once __DIR__ . '/deals_calendar.php';

/**
 * Горизонт прогрева календаря: до конца «лестницы» месяцев
 * (TH_DEALS_CAL_MONTHS_AHEAD). TH_CALENDAR_WARM_DAYS, если задан, только
 * поднимает нижнюю планку (не укорачивает лестницу).
 *
 * @return array{
 *   today: DateTimeImmutable,
 *   fromYmd: string,
 *   toYmd: string,
 *   daysAhead: int,
 *   coverFrom: DateTimeImmutable,
 *   coverTo: DateTimeImmutable,
 *   monthsAhead: int
 * }
 */
function th_calendar_warm_horizon(?DateTimeImmutable $today = null): array
{
    $today = $today ?? new DateTimeImmutable('today');
    $ladder = th_deals_calendar_ladder($today);
    $daysAhead = (int) $ladder['daysAhead'];
    $envRaw = getenv('TH_CALENDAR_WARM_DAYS');
    if ($envRaw === false || $envRaw === '') {
        $envRaw = $_ENV['TH_CALENDAR_WARM_DAYS'] ?? '';
    }
    if ($envRaw !== '' && $envRaw !== null) {
        $envDays = max(31, min(186, (int) $envRaw));
        if ($envDays > $daysAhead) {
            $daysAhead = $envDays;
        }
    }
    $daysAhead = max(31, min(186, $daysAhead));
    $to = $today->modify('+' . $daysAhead . ' days');
    if ($to < $ladder['horizon']) {
        $to = $ladder['horizon'];
        $daysAhead = max(1, (int) $today->diff($to)->days);
    }
    $coverFrom = $today->modify('+3 days');
    if ($coverFrom > $to) {
        $coverFrom = $to;
    }

    return [
        'today' => $today,
        'fromYmd' => $today->format('Y-m-d'),
        'toYmd' => $to->format('Y-m-d'),
        'daysAhead' => $daysAhead,
        'coverFrom' => $coverFrom,
        'coverTo' => $to,
        'monthsAhead' => (int) $ladder['monthsAhead'],
    ];
}

function th_calendar_cache_dir(): string
{
    $configured = trim((string) (getenv('TH_CALENDAR_CACHE_DIR') ?: ($_ENV['TH_CALENDAR_CACHE_DIR'] ?? '')));
    $dir = $configured !== ''
        ? $configured
        : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'calendar_cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $dir;
}

function th_calendar_cache_file(int $departureId): string
{
    return th_calendar_cache_dir() . DIRECTORY_SEPARATOR . 'calendar_' . max(1, $departureId) . '.json';
}

function th_calendar_cache_ttl_seconds(): int
{
    $hours = (float) (getenv('TH_CALENDAR_CACHE_TTL_HOURS') ?: ($_ENV['TH_CALENDAR_CACHE_TTL_HOURS'] ?? 30));
    return (int) (max(6, min(72, $hours)) * 3600);
}

/** @return array<string,mixed>|null */
function th_calendar_cache_get(int $departureId, bool $allowStale = true): ?array
{
    $file = th_calendar_cache_file($departureId);
    if (!is_file($file)) {
        return null;
    }
    $age = time() - (int) filemtime($file);
    $maxAge = $allowStale ? th_calendar_cache_ttl_seconds() * 2 : th_calendar_cache_ttl_seconds();
    if ($age > $maxAge) {
        return null;
    }
    $raw = @file_get_contents($file);
    $payload = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    if (!is_array($payload) || !is_array($payload['dates'] ?? null)) {
        return null;
    }

    return $payload;
}

/** @param array<string,mixed> $payload */
function th_calendar_cache_set(int $departureId, array $payload): bool
{
    $file = th_calendar_cache_file($departureId);
    $tmp = $file . '.tmp.' . getmypid();
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json) || @file_put_contents($tmp, $json, LOCK_EX) === false) {
        @unlink($tmp);
        return false;
    }
    @chmod($tmp, 0644);
    if (!@rename($tmp, $file)) {
        @unlink($file);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }
    }

    return true;
}

/** @param array<string,mixed> $hotel */
function th_calendar_cache_slim_hotel(array $hotel, array $matchingTours, int $countryId, string $countryName): array
{
    $price = 0;
    foreach ($matchingTours as $tour) {
        $p = (int) ($tour['totalPrice'] ?? $tour['price'] ?? $tour['priceRub'] ?? 0);
        if ($p > 0 && ($price === 0 || $p < $price)) {
            $price = $p;
        }
    }
    usort($matchingTours, static function (array $a, array $b): int {
        return ((int) ($a['totalPrice'] ?? $a['price'] ?? 0))
            <=> ((int) ($b['totalPrice'] ?? $b['price'] ?? 0));
    });

    $out = [
        'id' => $hotel['id'] ?? 0,
        'name' => (string) ($hotel['name'] ?? ''),
        'category' => $hotel['category'] ?? $hotel['stars'] ?? 0,
        'stars' => $hotel['stars'] ?? $hotel['category'] ?? 0,
        'rating' => $hotel['rating'] ?? null,
        'picturelink' => (string) ($hotel['picturelink'] ?? $hotel['pictureLink'] ?? ''),
        'hotelDescriptionLink' => (string) ($hotel['hotelDescriptionLink'] ?? ''),
        'country' => is_array($hotel['country'] ?? null)
            ? $hotel['country']
            : ['id' => $countryId, 'name' => $countryName],
        'region' => is_array($hotel['region'] ?? null) ? $hotel['region'] : null,
        'tours' => array_slice($matchingTours, 0, 3),
        '_dayMinPrice' => $price,
        '_countryId' => $countryId,
        '_countryName' => $countryName,
    ];

    return $out;
}

/**
 * Adds real tours to date buckets.
 *
 * @param array<string,list<array<string,mixed>>> $dates
 * @param list<array<string,mixed>> $hotels
 */
function th_calendar_cache_add_hotels(
    array &$dates,
    array $hotels,
    int $countryId,
    string $countryName,
    string $fromYmd,
    string $toYmd
): void {
    foreach ($hotels as $hotel) {
        if (!is_array($hotel)) {
            continue;
        }
        /** @var array<string,list<array<string,mixed>>> $byDay */
        $byDay = [];
        foreach ((array) ($hotel['tours'] ?? []) as $tour) {
            if (!is_array($tour)) {
                continue;
            }
            $date = th_promo_tour_start_ymd($tour);
            $price = (int) ($tour['totalPrice'] ?? $tour['price'] ?? $tour['priceRub'] ?? 0);
            if ($date === '' || $price <= 0 || $date < $fromYmd || $date > $toYmd) {
                continue;
            }
            $byDay[$date][] = $tour;
        }
        foreach ($byDay as $date => $matching) {
            $dates[$date][] = th_calendar_cache_slim_hotel($hotel, $matching, $countryId, $countryName);
        }
    }
}

/**
 * Deduplicates and caps each day to keep the cache small and fast.
 *
 * @param array<string,list<array<string,mixed>>> $dates
 * @return array<string,list<array<string,mixed>>>
 */
function th_calendar_cache_finalize_dates(array $dates, string $fromYmd, string $toYmd, int $perDay = 18): array
{
    $out = [];
    foreach ($dates as $date => $hotels) {
        if ($date < $fromYmd || $date > $toYmd || !is_array($hotels)) {
            continue;
        }
        $seen = [];
        $day = [];
        usort($hotels, static function (array $a, array $b): int {
            return ((int) ($a['_dayMinPrice'] ?? 0)) <=> ((int) ($b['_dayMinPrice'] ?? 0));
        });
        foreach ($hotels as $hotel) {
            $key = (string) ($hotel['_countryId'] ?? 0) . ':' . (string) ($hotel['id'] ?? 0);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $day[] = $hotel;
            if (count($day) >= $perDay) {
                break;
            }
        }
        if ($day !== []) {
            $out[$date] = $day;
        }
    }
    ksort($out);

    return $out;
}

/**
 * Popular countries list for calendar scan (excludes promo-hidden destinations).
 *
 * @return list<array{id:int,name:string}>
 */
function th_calendar_popular_countries_scan(?int $onlyCountryId = null): array
{
    $countries = [];
    $popularFile = dirname(__DIR__) . '/config/popular_countries.php';
    if (is_file($popularFile)) {
        $loaded = require $popularFile;
        if (is_array($loaded)) {
            $countries = $loaded;
        }
    }
    if ($countries === []) {
        $countries = [['id' => 4, 'name' => 'Турция']];
    }
    $excluded = [];
    $exFile = dirname(__DIR__) . '/config/promo_excluded_country_ids.php';
    if (is_file($exFile)) {
        $ex = require $exFile;
        if (is_array($ex)) {
            $excluded = array_map('intval', $ex);
        }
    }
    $scan = [];
    foreach ($countries as $c) {
        $cid = (int) ($c['id'] ?? 0);
        $cname = trim((string) ($c['name'] ?? ''));
        if ($cid <= 0 || in_array($cid, $excluded, true)) {
            continue;
        }
        if ($onlyCountryId !== null && $onlyCountryId > 0 && $cid !== $onlyCountryId) {
            continue;
        }
        $scan[] = ['id' => $cid, 'name' => $cname !== '' ? $cname : ('Страна ' . $cid)];
    }
    if ($onlyCountryId !== null && $onlyCountryId > 0 && $scan === []) {
        $scan[] = ['id' => $onlyCountryId, 'name' => 'Страна ' . $onlyCountryId];
    }

    return $scan;
}

/**
 * Read-only bootstrap from promo_cache into calendar structures.
 * Never writes promo_cache_* — only copies into memory / calendar_cache via warm.
 *
 * @return array{
 *   byDate: array<string, array{minPrice:int,countryId:int,countryName:string}>,
 *   hotelsByDate: array<string, list<array<string,mixed>>>,
 *   fromPromo: bool
 * }
 */
/**
 * Promo payload for calendar: свой файл (с фильтром → без фильтра), затем get_best.
 * Не пишет promo_cache. Нужен, чтобы Самара не опустела из‑за жёсткого IATA-фильтра.
 *
 * @return array{results: array, cachedAt?: int, departureId?: int, countryId?: int}|null
 */
function th_calendar_promo_payload_for_departure(int $countryId, int $departureId): ?array
{
    if (!function_exists('th_promo_speed_cache_get')) {
        require_once __DIR__ . '/promo_speed_cache.php';
    }
    if ($countryId <= 0 || $departureId <= 0) {
        return null;
    }
    $hit = th_promo_speed_cache_get($countryId, $departureId, true, $departureId, true);
    if ($hit !== null) {
        return $hit;
    }
    // Файл прогрет под этот вылет — доверяем ему, даже если IATA в названии тура странный.
    $hit = th_promo_speed_cache_get($countryId, $departureId, true, $departureId, false);
    if ($hit !== null) {
        return $hit;
    }
    $hit = th_promo_speed_cache_get_best($countryId, $departureId, true);
    if ($hit !== null) {
        return $hit;
    }
    // Last resort: чужой warm-файл как есть (лучше даты в календаре, чем пусто).
    foreach (th_promo_speed_warm_departures() as $depRow) {
        $fileDep = (int) ($depRow['departureId'] ?? 0);
        if ($fileDep <= 0 || $fileDep === $departureId) {
            continue;
        }
        $hit = th_promo_speed_cache_get($countryId, $fileDep, true, $fileDep, false);
        if ($hit !== null) {
            return $hit;
        }
    }

    return null;
}

function th_calendar_bootstrap_from_promo(
    int $departureId,
    int $countryId,
    string $fromYmd,
    string $toYmd,
    int $nightsFrom = 0,
    int $nightsTo = 0
): array {
    if (!function_exists('th_promo_speed_cache_get')) {
        require_once __DIR__ . '/promo_speed_cache.php';
    }
    if (!function_exists('th_promo_filter_hotels_for_promo_country')) {
        require_once __DIR__ . '/promo_sochi_filter.php';
    }

    /** @var array<string, array{minPrice:int,countryId:int,countryName:string}> $byDate */
    $byDate = [];
    /** @var array<string, list<array<string,mixed>>> $hotelsByDate */
    $hotelsByDate = [];
    $fromPromo = false;
    $only = $countryId > 0 ? $countryId : null;
    foreach (th_calendar_popular_countries_scan($only) as $row) {
        $cid = (int) $row['id'];
        $cname = (string) $row['name'];
        $payload = th_calendar_promo_payload_for_departure($cid, $departureId);
        $hotels = is_array($payload['results'] ?? null) ? $payload['results'] : [];
        if ($hotels === []) {
            continue;
        }
        $fromPromo = true;
        $hotels = th_promo_filter_hotels_for_promo_country($hotels, $cid);
        $hotels = th_promo_filter_hotels_min_nights($hotels, $cid);
        foreach ($hotels as $hotel) {
            if (!is_array($hotel)) {
                continue;
            }
            /** @var array<string, list<array<string,mixed>>> $byDay */
            $byDay = [];
            foreach ((array) ($hotel['tours'] ?? []) as $tour) {
                if (!is_array($tour)) {
                    continue;
                }
                $ymd = th_promo_tour_start_ymd($tour);
                $price = (int) ($tour['totalPrice'] ?? $tour['price'] ?? $tour['priceRub'] ?? 0);
                if ($ymd === '' || $price <= 0 || $ymd < $fromYmd || $ymd > $toYmd) {
                    continue;
                }
                $n = (int) ($tour['nights'] ?? 0);
                if ($nightsFrom > 0 && $nightsTo > 0 && $n > 0 && ($n < $nightsFrom || $n > $nightsTo)) {
                    continue;
                }
                $byDay[$ymd][] = $tour;
                if (!isset($byDate[$ymd]) || $price < $byDate[$ymd]['minPrice']) {
                    $byDate[$ymd] = [
                        'minPrice' => $price,
                        'countryId' => $cid,
                        'countryName' => $cname,
                    ];
                }
            }
            foreach ($byDay as $ymd => $matching) {
                $hotelsByDate[$ymd][] = th_calendar_cache_slim_hotel($hotel, $matching, $cid, $cname);
            }
        }
    }
    ksort($byDate);
    foreach ($hotelsByDate as $ymd => $list) {
        usort($list, static function (array $a, array $b): int {
            return ((int) ($a['_dayMinPrice'] ?? 0)) <=> ((int) ($b['_dayMinPrice'] ?? 0));
        });
        $hotelsByDate[$ymd] = $list;
    }

    return [
        'byDate' => $byDate,
        'hotelsByDate' => $hotelsByDate,
        'fromPromo' => $fromPromo,
    ];
}
