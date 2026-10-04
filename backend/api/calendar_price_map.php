<?php
/**
 * Карта цен по датам для календаря выгодных дат (без live Tourvisor).
 *
 * Приоритет: calendar_cache → read-only bootstrap из promo_cache (не пишет акции).
 * Акции (promo_cache_*) календарь не перезаписывает.
 *
 * GET: departureId, countryId? (0 = все), nightsFrom?, nightsTo?
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/components/deals_calendar.php';
require_once dirname(__DIR__) . '/components/calendar_tour_cache.php';
require_once dirname(__DIR__) . '/components/security_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
security_apply_default_headers();

$guard = security_guard_public_api('calendar_price_map', '', 120, 4096);
if (empty($guard['ok'])) {
    http_response_code((int) ($guard['code'] ?? 400));
    echo json_encode(['success' => false, 'error' => (string) ($guard['error'] ?? 'Bad request')], JSON_UNESCAPED_UNICODE);
    exit;
}

$departureId = isset($_GET['departureId']) ? (int) $_GET['departureId'] : 0;
$countryId = isset($_GET['countryId']) ? (int) $_GET['countryId'] : 0;
$nightsFrom = isset($_GET['nightsFrom']) ? (int) $_GET['nightsFrom'] : 0;
$nightsTo = isset($_GET['nightsTo']) ? (int) $_GET['nightsTo'] : 0;
if ($departureId <= 0) {
    $departureId = 7;
}

$ladder = th_deals_calendar_ladder();
$todayYmd = $ladder['today']->format('Y-m-d');
$horizonYmdLimit = $ladder['horizonYmd'];

/** @var array<string, array{minPrice:int,countryId:int,countryName:string}> $calendarByDate */
$calendarByDate = [];
$source = 'none';
$updatedAt = time();
$cacheFilledDays = 0;
$totalDays = (int) $ladder['daysAhead'] + 1;

$calendarPayload = th_calendar_cache_get($departureId, true);
if ($calendarPayload !== null) {
    $cacheFilledDays = (int) ($calendarPayload['filledDays'] ?? 0);
    $totalDays = (int) ($calendarPayload['totalDays'] ?? $totalDays);
    $updatedAt = (int) ($calendarPayload['generatedAt'] ?? time());
    foreach ((array) ($calendarPayload['dates'] ?? []) as $date => $hotels) {
        if (!is_string($date) || !is_array($hotels)) {
            continue;
        }
        if ($date < $todayYmd || $date > $horizonYmdLimit) {
            continue;
        }
        foreach ($hotels as $hotel) {
            if (!is_array($hotel)) {
                continue;
            }
            $hotelCountryId = (int) ($hotel['_countryId'] ?? $hotel['country']['id'] ?? 0);
            if ($countryId > 0 && $hotelCountryId !== $countryId) {
                continue;
            }
            $hotelCountryName = (string) ($hotel['_countryName'] ?? $hotel['country']['name'] ?? '');
            foreach ((array) ($hotel['tours'] ?? []) as $tour) {
                if (!is_array($tour)) {
                    continue;
                }
                $nights = (int) ($tour['nights'] ?? 0);
                if ($nightsFrom > 0 && $nightsTo > 0 && $nights > 0 && ($nights < $nightsFrom || $nights > $nightsTo)) {
                    continue;
                }
                $price = (int) ($tour['totalPrice'] ?? $tour['price'] ?? $tour['priceRub'] ?? 0);
                if ($price <= 0) {
                    continue;
                }
                if (!isset($calendarByDate[$date]) || $price < $calendarByDate[$date]['minPrice']) {
                    $calendarByDate[$date] = [
                        'minPrice' => $price,
                        'countryId' => $hotelCountryId,
                        'countryName' => $hotelCountryName,
                    ];
                }
            }
        }
    }
    if ($calendarByDate !== []) {
        $source = 'calendar_cache';
    }
}

if ($calendarByDate === []) {
    $boot = th_calendar_bootstrap_from_promo(
        $departureId,
        $countryId,
        $todayYmd,
        $horizonYmdLimit,
        $nightsFrom,
        $nightsTo
    );
    $calendarByDate = $boot['byDate'];
    if ($calendarByDate !== []) {
        $source = 'promo_bootstrap';
    }
}

ksort($calendarByDate);
$calendarDates = th_deals_calendar_mark_tiers($calendarByDate);
$horizonYmd = $ladder['horizonYmd'];
if ($calendarPayload !== null) {
    $cacheHorizon = (string) ($calendarPayload['horizonTo'] ?? '');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $cacheHorizon) && $cacheHorizon > $horizonYmd) {
        $horizonYmd = $cacheHorizon;
    }
}

$emptyReason = '';
if ($calendarDates === []) {
    $emptyReason = $source === 'none' ? 'no_calendar_cache' : 'no_dates_in_range';
}

// Пустой ответ нельзя кэшировать — иначе Самара «залипает» пустой после прогрева.
if ($calendarDates === []) {
    header('Cache-Control: no-store, max-age=0');
} else {
    header('Cache-Control: public, max-age=120');
}

echo json_encode([
    'success' => true,
    'departureId' => $departureId,
    'countryId' => $countryId,
    'mode' => $countryId > 0 ? 'country' : 'all',
    'nightsFrom' => $nightsFrom,
    'nightsTo' => $nightsTo,
    'updatedAt' => $updatedAt,
    'fromCache' => $calendarDates !== [],
    'source' => $source,
    'emptyReason' => $emptyReason,
    'coverage' => [
        'filledDays' => count($calendarDates),
        'totalDays' => $totalDays,
        'cacheFilledDays' => $cacheFilledDays,
    ],
    'ladder' => [
        'monthsAhead' => $ladder['monthsAhead'],
        'horizon' => $horizonYmd,
        'viewMaxYm' => substr($horizonYmd, 0, 7),
    ],
    'dates' => $calendarDates,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
