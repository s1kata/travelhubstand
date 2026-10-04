<?php
/**
 * Builds a rolling calendar cache from search-cover + read-only promo seed.
 *
 * Never writes data/promo_cache_* (акции остаются отдельным пайплайном).
 * Promo только копируется в data/calendar_cache/ как bootstrap ближних дат.
 * Run after warm_home_search_cache.sh. No live Tourvisor.
 */
declare(strict_types=1);

require_once __DIR__ . '/../components/tourvisor_proxy_http_base.php';
require_once __DIR__ . '/../components/tourvisor_search_cover.php';
require_once __DIR__ . '/../components/calendar_tour_cache.php';
require_once __DIR__ . '/../components/promo_sochi_filter.php';

$projectRoot = dirname(__DIR__, 2);
foreach ([$projectRoot . '/backend/.env', $projectRoot . '/.env'] as $envPath) {
    if (!is_file($envPath)) {
        continue;
    }
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        if ($key !== '') {
            putenv($key . '=' . trim($value));
            $_ENV[$key] = trim($value);
        }
    }
}

$countries = require __DIR__ . '/../config/popular_countries.php';
if (!is_array($countries) || $countries === []) {
    $countries = [['id' => 4, 'name' => 'Турция']];
}
/** Самара + Москва — те же вылеты, что у home search cover. */
$departureIds = [7, 1];
$proxyBase = get_tourvisor_proxy_http_base_url();
$horizon = th_calendar_warm_horizon();
$fromYmd = $horizon['fromYmd'];
$toYmd = $horizon['toYmd'];
$daysAhead = $horizon['daysAhead'];
$coverFrom = $horizon['coverFrom'];
$coverTo = $horizon['coverTo'];
$wideNights = th_search_cover_wide_nights();
$ttlHours = (float) (getenv('TOURVISOR_COUNTRY_PAGE_CACHE_TTL_HOURS')
    ?: ($_ENV['TOURVISOR_COUNTRY_PAGE_CACHE_TTL_HOURS'] ?? 24));
$coverTtlSeconds = (int) (min(168, max(1, $ttlHours)) * 3600);
/** Allow slightly stale cover when assembling calendar (2× country TTL). */
$coverReadTtl = $coverTtlSeconds * 2;

/** @return array<string,mixed> */
function th_calendar_warm_fetch_json(string $url): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return ['success' => false, 'data' => [], '_transportError' => 'curl_init'];
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!is_string($raw) || $raw === '' || $status >= 500) {
        return [
            'success' => false,
            'data' => [],
            '_transportError' => $error !== '' ? $error : ('HTTP ' . $status),
        ];
    }
    $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

    return is_array($decoded) ? $decoded : ['success' => false, 'data' => []];
}

/** @return list<array{from:string,to:string}> */
function th_calendar_warm_windows(DateTimeImmutable $from, DateTimeImmutable $to): array
{
    $windows = [];
    $cursor = $from;
    while ($cursor <= $to) {
        $end = $cursor->modify('+13 days');
        if ($end > $to) {
            $end = $to;
        }
        $windows[] = ['from' => $cursor->format('Y-m-d'), 'to' => $end->format('Y-m-d')];
        $cursor = $end->modify('+1 day');
    }

    return $windows;
}

/**
 * Pull hotels from local search-cover blob (all dates in file).
 *
 * @param array<string,mixed> $params
 * @return list<array<string,mixed>>
 */
function th_calendar_warm_cover_hotels(array $params, int $ttlSeconds): array
{
    $hit = th_search_cover_find($params, $ttlSeconds);
    if ($hit === null) {
        // Partial cover: load matching identity even if dates do not fully ⊇ query.
        $identity = th_search_cover_identity($params);
        $entry = th_search_cover_get_entry($identity);
        if ($entry === null) {
            return [];
        }
        $file = (string) ($entry['file'] ?? '');
        if ($file === '') {
            $file = th_search_cover_file_for_identity($identity);
        } elseif ($file[0] !== '/' && strpos($file, ':') === false) {
            $file = th_search_cover_cache_dir() . DIRECTORY_SEPARATOR . basename($file);
        }
        $blob = th_search_cover_load_blob($file);
        $hotels = is_array($blob['hotels'] ?? null) ? $blob['hotels'] : [];

        return is_array($hotels) ? $hotels : [];
    }
    $hotels = $hit['hotels'] ?? [];

    return is_array($hotels) ? $hotels : [];
}

$summary = [];
foreach ($departureIds as $departureId) {
    $departureId = (int) $departureId;
    if ($departureId <= 0) {
        continue;
    }

    /** @var array<string,list<array<string,mixed>>> $dateBuckets */
    $dateBuckets = [];
    /** @var array<string,list<array<string,mixed>>> $previousDates */
    $previousDates = [];
    $previous = th_calendar_cache_get($departureId, true);
    $previousAge = isset($previous['generatedAt']) ? time() - (int) $previous['generatedAt'] : PHP_INT_MAX;
    if ($previousAge <= 72 * 3600) {
        foreach ((array) ($previous['dates'] ?? []) as $date => $hotels) {
            if (is_string($date) && is_array($hotels) && $date >= $fromYmd && $date <= $toYmd) {
                $previousDates[$date] = $hotels;
            }
        }
    }

    $coverHits = 0;
    $coverMisses = 0;
    $transportErrors = 0;
    $coverDirectHits = 0;
    $promoCountries = 0;
    foreach ($countries as $country) {
        $countryId = (int) ($country['id'] ?? 0);
        $countryName = trim((string) ($country['name'] ?? ''));
        if ($countryId <= 0) {
            continue;
        }

        // Bootstrap ближних дат из promo → только в calendar_cache (promo файлы не трогаем).
        $promo = th_calendar_promo_payload_for_departure($countryId, $departureId);
        $promoHotels = is_array($promo['results'] ?? null) ? $promo['results'] : [];
        if ($promoHotels !== []) {
            $promoCountries++;
            $promoHotels = th_promo_filter_hotels_for_promo_country($promoHotels, $countryId);
            $promoHotels = th_promo_filter_hotels_min_nights($promoHotels, $countryId);
            th_calendar_cache_add_hotels(
                $dateBuckets,
                $promoHotels,
                $countryId,
                $countryName,
                $fromYmd,
                $toYmd
            );
        }

        $coverParams = [
            'departureId' => $departureId,
            'countryId' => $countryId,
            'adults' => 2,
            'childs' => '',
            'nightsFrom' => $wideNights['from'],
            'nightsTo' => $wideNights['to'],
            'currency' => 'RUB',
            'dateFrom' => $coverFrom->format('Y-m-d'),
            'dateTo' => $coverTo->format('Y-m-d'),
        ];
        $directHotels = th_calendar_warm_cover_hotels($coverParams, $coverReadTtl);
        if ($directHotels !== []) {
            $coverDirectHits++;
            $coverHits++;
            th_calendar_cache_add_hotels(
                $dateBuckets,
                $directHotels,
                $countryId,
                $countryName,
                $fromYmd,
                $toYmd
            );
            continue;
        }

        foreach (th_calendar_warm_windows($coverFrom, $coverTo) as $window) {
            $params = [
                'type' => 'search-cached',
                'departureId' => (string) $departureId,
                'countryId' => (string) $countryId,
                'dateFrom' => $window['from'],
                'dateTo' => $window['to'],
                'nightsFrom' => (string) $wideNights['from'],
                'nightsTo' => (string) $wideNights['to'],
                'adults' => '2',
                'currency' => 'RUB',
                'cacheScope' => 'country_page',
                'cacheOnly' => '1',
                'slim' => '1',
            ];
            $url = $proxyBase . (strpos($proxyBase, '?') !== false ? '&' : '?') . http_build_query($params);
            $response = th_calendar_warm_fetch_json($url);
            if (!empty($response['_transportError'])) {
                $transportErrors++;
                $coverMisses++;
                if ($transportErrors >= 3) {
                    break 2;
                }
                continue;
            }
            $hotels = !empty($response['success']) && is_array($response['data'] ?? null)
                ? $response['data']
                : [];
            if ($hotels === []) {
                $coverMisses++;
                continue;
            }
            $coverHits++;
            th_calendar_cache_add_hotels(
                $dateBuckets,
                $hotels,
                $countryId,
                $countryName,
                $window['from'],
                $window['to']
            );
        }
    }

    $dates = th_calendar_cache_finalize_dates($dateBuckets, $fromYmd, $toYmd, 18);
    // Stale-while-revalidate only for dates that could not be rebuilt this run.
    foreach ($previousDates as $date => $hotels) {
        if (!isset($dates[$date])) {
            $dates[$date] = $hotels;
        }
    }
    ksort($dates);
    $totalDays = $daysAhead + 1;
    $payload = [
        'version' => 4,
        'source' => 'cover_plus_promo_seed',
        'generatedAt' => time(),
        'departureId' => $departureId,
        'horizonFrom' => $fromYmd,
        'horizonTo' => $toYmd,
        'daysAhead' => $daysAhead,
        'monthsAhead' => $horizon['monthsAhead'],
        'filledDays' => count($dates),
        'totalDays' => $totalDays,
        'dates' => $dates,
    ];
    // Never replace/refresh a usable cache after a transport outage.
    $buildHealthy = $transportErrors < 3 || $coverHits > 0 || $promoCountries > 0 || $dates !== [];
    $saved = $dates !== [] && $buildHealthy && th_calendar_cache_set($departureId, $payload);
    $summary[] = [
        'departureId' => $departureId,
        'saved' => $saved,
        'filledDays' => count($dates),
        'totalDays' => $totalDays,
        'horizonTo' => $toYmd,
        'promoCountries' => $promoCountries,
        'coverDirectHits' => $coverDirectHits,
        'coverHits' => $coverHits,
        'coverMisses' => $coverMisses,
        'transportErrors' => $transportErrors,
    ];
}

$result = [
    'success' => !in_array(false, array_column($summary, 'saved'), true),
    'source' => 'cover_plus_promo_seed',
    'writesPromoCache' => false,
    'horizonFrom' => $fromYmd,
    'horizonTo' => $toYmd,
    'daysAhead' => $daysAhead,
    'monthsAhead' => $horizon['monthsAhead'],
    'departures' => $summary,
];
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
