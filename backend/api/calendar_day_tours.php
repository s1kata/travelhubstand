<?php
/**
 * Туры на выбранную дату для календаря выгодных дат.
 *
 * Приоритет: calendar_cache → read-only bootstrap из promo_cache (не пишет акции).
 *
 * GET: departureId, date=Y-m-d, countryId?, nightsFrom?, nightsTo?, limit?
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/components/calendar_tour_cache.php';
require_once dirname(__DIR__) . '/components/security_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
security_apply_default_headers();

$guard = security_guard_public_api('calendar_day_tours', '', 90, 4096);
if (empty($guard['ok'])) {
    http_response_code((int) ($guard['code'] ?? 400));
    echo json_encode(['success' => false, 'error' => (string) ($guard['error'] ?? 'Bad request')], JSON_UNESCAPED_UNICODE);
    exit;
}

$departureId = isset($_GET['departureId']) ? (int) $_GET['departureId'] : 7;
$countryId = isset($_GET['countryId']) ? (int) $_GET['countryId'] : 0;
$date = trim((string) ($_GET['date'] ?? ''));
$nightsFrom = isset($_GET['nightsFrom']) ? (int) $_GET['nightsFrom'] : 0;
$nightsTo = isset($_GET['nightsTo']) ? (int) $_GET['nightsTo'] : 0;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 12;
$limit = max(1, min(24, $limit));

if ($departureId <= 0) {
    $departureId = 7;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'date=Y-m-d required'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $day = new DateTimeImmutable($date);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid date'], JSON_UNESCAPED_UNICODE);
    exit;
}
$today = new DateTimeImmutable('today');
if ($day < $today) {
    echo json_encode(['success' => true, 'data' => [], 'fromCache' => true, 'source' => 'none'], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * @param list<array<string,mixed>> $hotels
 * @return list<array<string,mixed>>
 */
$finalizeDayHotels = static function (array $hotels, string $date, int $countryId, int $nightsFrom, int $nightsTo, int $limit): array {
    $out = [];
    foreach ($hotels as $hotel) {
        if (!is_array($hotel)) {
            continue;
        }
        $hotelCountryId = (int) ($hotel['_countryId'] ?? $hotel['country']['id'] ?? 0);
        if ($countryId > 0 && $hotelCountryId !== $countryId) {
            continue;
        }
        $matching = [];
        $bestPrice = 0;
        foreach ((array) ($hotel['tours'] ?? []) as $tour) {
            if (!is_array($tour) || th_promo_tour_start_ymd($tour) !== $date) {
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
            $matching[] = $tour;
            if ($bestPrice === 0 || $price < $bestPrice) {
                $bestPrice = $price;
            }
        }
        if ($matching === []) {
            continue;
        }
        usort($matching, static function (array $a, array $b): int {
            return ((int) ($a['totalPrice'] ?? $a['price'] ?? 0))
                <=> ((int) ($b['totalPrice'] ?? $b['price'] ?? 0));
        });
        $hotel['tours'] = array_slice($matching, 0, 3);
        $hotel['_dayMinPrice'] = $bestPrice;
        $out[] = $hotel;
    }
    usort($out, static function (array $a, array $b): int {
        return ((int) ($a['_dayMinPrice'] ?? 0)) <=> ((int) ($b['_dayMinPrice'] ?? 0));
    });

    return array_slice($out, 0, $limit);
};

$source = 'none';
$updatedAt = 0;
$calendarOut = [];

$calendarPayload = th_calendar_cache_get($departureId, true);
if ($calendarPayload !== null) {
    $updatedAt = (int) ($calendarPayload['generatedAt'] ?? 0);
    $calendarOut = $finalizeDayHotels(
        (array) ($calendarPayload['dates'][$date] ?? []),
        $date,
        $countryId,
        $nightsFrom,
        $nightsTo,
        $limit
    );
    if ($calendarOut !== []) {
        $source = 'calendar_cache';
    }
}

if ($calendarOut === []) {
    $boot = th_calendar_bootstrap_from_promo(
        $departureId,
        $countryId,
        $date,
        $date,
        $nightsFrom,
        $nightsTo
    );
    $dayHotels = (array) ($boot['hotelsByDate'][$date] ?? []);
    $calendarOut = $finalizeDayHotels($dayHotels, $date, $countryId, $nightsFrom, $nightsTo, $limit);
    if ($calendarOut !== []) {
        $source = 'promo_bootstrap';
        $updatedAt = time();
    }
}

if ($calendarOut === []) {
    header('Cache-Control: no-store, max-age=0');
} else {
    header('Cache-Control: public, max-age=60');
}

echo json_encode([
    'success' => true,
    'departureId' => $departureId,
    'countryId' => $countryId,
    'mode' => $countryId > 0 ? 'country' : 'all',
    'date' => $date,
    'fromCache' => $calendarOut !== [],
    'source' => $source,
    'emptyReason' => $calendarOut === [] ? ($source === 'none' ? 'no_calendar_cache' : 'no_tours') : '',
    'updatedAt' => $updatedAt,
    'data' => $calendarOut,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
