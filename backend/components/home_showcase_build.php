<?php
/**
 * Сборка витрины «Горящие туры» для главной: карточки отелей/туров из promo_cache.
 * Не страны. При пустом кэше опционально добирает live promo-search (лимит стран).
 */
declare(strict_types=1);

require_once __DIR__ . '/promo_speed_cache.php';
require_once __DIR__ . '/promo_sochi_filter.php';

/**
 * @param array<string, mixed> $hotel
 */
function th_home_showcase_hotel_image(array $hotel): string
{
    $pic = trim((string) ($hotel['picturelink'] ?? $hotel['pictureLink'] ?? ''));
    if ($pic !== '') {
        return $pic;
    }
    $hid = (int) ($hotel['id'] ?? 0);
    if ($hid > 0) {
        return 'https://static.tourvisor.ru/hotel_pics/main400/' . $hid . '.jpg';
    }

    return '';
}

/**
 * @param array<string, mixed> $hotel
 * @param array<string, mixed> $meta
 * @return array<string, mixed>|null
 */
function th_home_showcase_pick_tour(array $hotel, array $meta, int $departureId, string $departureName): ?array
{
    $tours = is_array($hotel['tours'] ?? null) ? $hotel['tours'] : [];
    $best = null;
    $bestPrice = 0;
    $today = date('Y-m-d');
    foreach ($tours as $t) {
        if (!is_array($t)) {
            continue;
        }
        $ymd = th_promo_tour_start_ymd($t);
        if ($ymd !== '' && $ymd < $today) {
            continue;
        }
        $p = (int) ($t['totalPrice'] ?? $t['price'] ?? $t['priceRub'] ?? 0);
        if ($p <= 0) {
            continue;
        }
        if ($best === null || $p < $bestPrice) {
            $best = $t;
            $bestPrice = $p;
        }
    }
    if ($best === null) {
        /* Нет актуальных вылетов — не подставляем «голую» цену отеля без даты */
        return null;
    }

    $hotelName = trim((string) ($hotel['name'] ?? ''));
    if ($hotelName === '') {
        return null;
    }

    $countryName = (string) ($meta['name'] ?? '');
    if (is_array($hotel['country'] ?? null)) {
        $fromHotel = trim((string) ($hotel['country']['name'] ?? ''));
        if ($fromHotel !== '' && $countryName === '') {
            $countryName = $fromHotel;
        }
    }
    if ((int) ($meta['countryId'] ?? 0) === 47) {
        $countryName = 'Сочи';
    }

    $region = '';
    if (is_array($hotel['region'] ?? null)) {
        $region = trim((string) ($hotel['region']['name'] ?? ''));
    }

    $nights = (int) ($best['nights'] ?? 0);
    $meal = '';
    if (is_array($best['meal'] ?? null)) {
        $meal = trim((string) ($best['meal']['russianName'] ?? $best['meal']['name'] ?? ''));
    }
    $dateFrom = th_promo_tour_start_ymd($best);
    $today = date('Y-m-d');
    /* Просроченные вылеты не показываем на витрине «Горящие» */
    if ($dateFrom !== '' && $dateFrom < $today) {
        return null;
    }
    $dateTo = '';
    if ($dateFrom !== '' && $nights > 0) {
        try {
            $dateTo = (new DateTimeImmutable($dateFrom))->modify('+' . $nights . ' days')->format('Y-m-d');
        } catch (Throwable $e) {
            $dateTo = '';
        }
    }

    $tourId = trim((string) ($best['id'] ?? $best['tourId'] ?? ''));
    $link = trim((string) ($hotel['hotelDescriptionLink'] ?? $hotel['hoteldescriptionlink'] ?? $hotel['link'] ?? ''));
    $image = th_home_showcase_hotel_image($hotel);
    if ($image === '') {
        $image = (string) ($meta['image'] ?? '');
    }

    $params = [
        'country' => $countryName,
        'hotel_name' => $hotelName,
        'price' => (string) $bestPrice,
        'nights' => (string) $nights,
        'meal' => $meal,
        'region' => $region,
        'departure_city' => $departureName,
        'departure_id' => (string) $departureId,
        'image' => $image,
        'adults' => '2',
        'rating' => (string) ($hotel['rating'] ?? ''),
        'category' => (string) ($hotel['category'] ?? ''),
        'from_promo' => '1',
    ];
    if ($tourId !== '') {
        $params['tour_id'] = $tourId;
    }
    if ($link !== '') {
        $params['tour_link'] = $link;
    }
    if ($dateFrom !== '') {
        $params['date_from'] = $dateFrom;
    }
    if ($dateTo !== '') {
        $params['date_to'] = $dateTo;
    }
    if (!empty($hotel['id'])) {
        $params['hotel_id'] = (string) $hotel['id'];
    }

    $hotelPayload = [
        'id' => (int) ($hotel['id'] ?? 0),
        'name' => $hotelName,
        'category' => (int) ($hotel['category'] ?? 0),
        'rating' => $hotel['rating'] ?? null,
        'region' => $region !== '' ? ['name' => $region] : (is_array($hotel['region'] ?? null) ? $hotel['region'] : []),
        'country' => [
            'id' => (int) ($meta['countryId'] ?? 0),
            'name' => $countryName,
        ],
        'picturelink' => $image,
        'pictures' => is_array($hotel['pictures'] ?? null) ? $hotel['pictures'] : [],
        'hotelDescriptionLink' => $link,
        'link' => $link,
        'tours' => [$best],
    ];

    return [
        'hotelId' => (int) ($hotel['id'] ?? 0),
        'hotelName' => $hotelName,
        'countryId' => (int) ($meta['countryId'] ?? 0),
        'countryName' => $countryName,
        'slug' => (string) ($meta['slug'] ?? ''),
        'region' => $region,
        'price' => $bestPrice,
        'nights' => $nights,
        'meal' => $meal,
        'dateFrom' => $dateFrom,
        'dateTo' => $dateTo,
        'image' => $image,
        'stars' => (int) ($hotel['category'] ?? 0),
        'rating' => $hotel['rating'] ?? null,
        'tourId' => $tourId,
        'href' => '/frontend/window/tour-detail.php?' . http_build_query($params),
        'promoHref' => '/frontend/window/promotions.php?departureId=' . $departureId
            . '&departureName=' . rawurlencode($departureName)
            . '&countryId=' . (int) ($meta['countryId'] ?? 0)
            . '&countryName=' . rawurlencode($countryName),
        'hotel' => $hotelPayload,
        'tour' => $best,
    ];
}

/**
 * @param list<array<string,mixed>> $hotels
 * @return list<array<string,mixed>>
 */
function th_home_showcase_sort_hotels(array $hotels): array
{
    usort($hotels, static function (array $a, array $b): int {
        $pa = th_promo_speed_hotel_min_price($a);
        $pb = th_promo_speed_hotel_min_price($b);
        if ($pa > 0 && $pb > 0 && $pa !== $pb) {
            return $pa <=> $pb;
        }
        if ($pa > 0 && $pb === 0) {
            return -1;
        }
        if ($pb > 0 && $pa === 0) {
            return 1;
        }

        return 0;
    });

    return $hotels;
}

/**
 * @return list<array<string,mixed>>
 */
function th_home_showcase_fetch_live_hotels(int $countryId, int $departureId): array
{
    static $proxyBase = null;
    if ($proxyBase === null) {
        require_once __DIR__ . '/tourvisor_proxy_http_base.php';
        $proxyBase = rtrim(get_tourvisor_proxy_http_base_url(), '/');
    }
    if ($proxyBase === '') {
        return [];
    }
    $dates = th_promo_speed_promo_dates($countryId);
    $params = http_build_query([
        'type' => 'promo-search',
        'departureId' => (string) $departureId,
        'countryId' => (string) $countryId,
        'dateFrom' => $dates['dateFrom'],
        'dateTo' => $dates['dateTo'],
        'adults' => '2',
    ]);
    $url = $proxyBase . (strpos($proxyBase, '?') !== false ? '&' : '?') . $params;
    $ctx = stream_context_create([
        'http' => ['timeout' => 18, 'ignore_errors' => true],
        'ssl' => ['verify_peer' => true],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['success']) || !is_array($data['data'] ?? null)) {
        return [];
    }

    return $data['data'];
}

/**
 * @param array<string,mixed> $meta
 * @param list<array<string,mixed>> $hotels
 * @param array<string,bool> $seenHotels
 * @param list<array<string,mixed>> $tours
 */
function th_home_showcase_append_from_hotels(
    array $meta,
    array $hotels,
    int $departureId,
    string $departureName,
    array &$seenHotels,
    array &$tours,
    int $perCountry = 6
): void {
    $countryId = (int) ($meta['countryId'] ?? 0);
    $countryName = (string) ($meta['name'] ?? '');
    $hotels = th_promo_filter_hotels_for_promo_country($hotels, $countryId);
    $hotels = th_promo_filter_hotels_future_tours($hotels, date('Y-m-d'));
    $hotels = th_promo_filter_hotels_min_nights($hotels, $countryId);
    /* Тот же whitelist, что search/live (FunSun…Ambotis / TR+EG короткий). */
    $hotels = th_promo_filter_hotels_by_allowed_operators($hotels, $countryId, $countryName, [
        'departureId' => $departureId,
    ]);
    $hotels = th_home_showcase_sort_hotels($hotels);
    $taken = 0;
    foreach ($hotels as $hotel) {
        if (!is_array($hotel) || $taken >= $perCountry) {
            break;
        }
        $hid = (int) ($hotel['id'] ?? 0);
        $key = $hid > 0 ? ('h' . $hid) : ('n' . md5((string) ($hotel['name'] ?? '')));
        if (isset($seenHotels[$key])) {
            continue;
        }
        $card = th_home_showcase_pick_tour($hotel, $meta, $departureId, $departureName);
        if ($card === null) {
            continue;
        }
        $seenHotels[$key] = true;
        $tours[] = $card;
        $taken++;
    }
}

/**
 * @param list<array<string,mixed>> $tours
 * @param list<string> $slugs
 * @return list<array<string,mixed>>
 */
function th_home_showcase_pick_mood_items(array $tours, array $slugs, string $pattern, int $limit): array
{
    $bySlug = [];
    foreach ($slugs as $slug) {
        $bySlug[$slug] = [];
    }
    foreach ($tours as $tour) {
        $slug = (string) ($tour['slug'] ?? '');
        if ($slug === '' || !isset($bySlug[$slug])) {
            continue;
        }
        $bySlug[$slug][] = $tour;
    }

    $picked = [];
    $seen = [];
    $add = static function (array $tour) use (&$picked, &$seen, $limit): bool {
        if (count($picked) >= $limit) {
            return false;
        }
        $key = ((int) ($tour['hotelId'] ?? 0)) . '|' . (string) ($tour['hotelName'] ?? '');
        if (isset($seen[$key])) {
            return true;
        }
        $seen[$key] = true;
        $picked[] = $tour;

        return count($picked) < $limit;
    };

    if ($pattern === 'cheap') {
        $pool = [];
        foreach ($slugs as $slug) {
            foreach ($bySlug[$slug] as $tour) {
                $pool[] = $tour;
            }
        }
        usort($pool, static function (array $a, array $b): int {
            return ((int) ($a['price'] ?? 0)) <=> ((int) ($b['price'] ?? 0));
        });
        foreach ($pool as $tour) {
            if (!$add($tour)) {
                break;
            }
        }

        return $picked;
    }

    if ($pattern === 'spread') {
        foreach ($slugs as $slug) {
            $list = $bySlug[$slug];
            $n = count($list);
            if ($n === 0) {
                continue;
            }
            $start = (int) floor($n / 3);
            for ($i = $start; $i < $n; $i++) {
                if (!$add($list[$i])) {
                    return $picked;
                }
            }
        }
        foreach ($slugs as $slug) {
            foreach ($bySlug[$slug] as $tour) {
                if (!$add($tour)) {
                    return $picked;
                }
            }
        }

        return $picked;
    }

    $idx = [];
    foreach ($slugs as $slug) {
        $idx[$slug] = 0;
    }
    $progress = true;
    while ($progress && count($picked) < $limit) {
        $progress = false;
        foreach ($slugs as $slug) {
            $list = $bySlug[$slug];
            $i = $idx[$slug];
            if ($i >= count($list)) {
                continue;
            }
            $progress = true;
            $idx[$slug] = $i + 1;
            if (!$add($list[$i])) {
                return $picked;
            }
        }
    }

    return $picked;
}

/**
 * @return array{
 *   success: bool,
 *   departureId: int,
 *   departureName: string,
 *   updatedAt: int,
 *   mode: string,
 *   source: string,
 *   moods: array<string, array{title: string, items: list<array<string,mixed>>}>,
 *   hot: list<array<string,mixed>>,
 *   tours: list<array<string,mixed>>
 * }
 */
function th_home_showcase_build(int $departureId, bool $allowLive = true): array
{
    if ($departureId <= 0) {
        $departureId = 7;
    }
    $departureName = ($departureId === 1) ? 'Москва' : (($departureId === 7) ? 'Самара' : 'Ваш город');
    $cardsFile = dirname(__DIR__) . '/config/site_popular_destinations_cards.php';
    $cards = is_file($cardsFile) ? require $cardsFile : [];
    if (!is_array($cards)) {
        $cards = [];
    }

    $tours = [];
    $seenHotels = [];
    $gotFromCacheCountries = [];
    $flightsByTourId = [];

    foreach ($cards as $row) {
        if (!is_array($row)) {
            continue;
        }
        $countryId = (int) ($row['countryId'] ?? 0);
        if ($countryId <= 0) {
            continue;
        }
        $payload = th_promo_speed_cache_get($countryId, $departureId, true, $departureId);
        if ($payload === null) {
            $payload = th_promo_speed_cache_get_best($countryId, $departureId, true);
        }
        if (is_array($payload)) {
            $cachedFlights = th_promo_speed_flights_from_cache_payload($payload);
            foreach ($cachedFlights as $tid => $flightJson) {
                if ($tid !== '' && is_array($flightJson)) {
                    $flightsByTourId[(string) $tid] = $flightJson;
                }
            }
        }
        $hotels = is_array($payload['results'] ?? null) ? $payload['results'] : [];
        if ($hotels === []) {
            continue;
        }
        $before = count($tours);
        th_home_showcase_append_from_hotels($row, $hotels, $departureId, $departureName, $seenHotels, $tours, 6);
        if (count($tours) > $before) {
            $gotFromCacheCountries[$countryId] = true;
        }
    }

    $source = $tours !== [] ? 'promo_cache' : 'empty';

    // Если кэш пустой/бедный — добрать туры live по топ-странам (не показывать страны вместо туров).
    if ($allowLive && count($tours) < 8) {
        $liveBudget = 4;
        $liveDone = 0;
        foreach ($cards as $row) {
            if (!is_array($row) || $liveDone >= $liveBudget || count($tours) >= 16) {
                break;
            }
            $countryId = (int) ($row['countryId'] ?? 0);
            if ($countryId <= 0 || isset($gotFromCacheCountries[$countryId])) {
                continue;
            }
            $hotels = th_home_showcase_fetch_live_hotels($countryId, $departureId);
            $liveDone++;
            if ($hotels === []) {
                continue;
            }
            th_home_showcase_append_from_hotels($row, $hotels, $departureId, $departureName, $seenHotels, $tours, 6);
            $source = $tours !== [] ? 'promo_cache+live' : $source;
        }
        if ($tours !== [] && $source === 'empty') {
            $source = 'live';
        }
    }

    usort($tours, static function (array $a, array $b): int {
        return ((int) ($a['price'] ?? 0)) <=> ((int) ($b['price'] ?? 0));
    });

    $moodRules = [
        'beach' => [
            'title' => 'На пляж',
            'slugs' => ['turkey', 'egypt', 'thailand', 'uae', 'vietnam', 'phuquoc', 'maldives', 'tunisia', 'abkhazia', 'russia', 'srilanka'],
            'pattern' => 'round',
            'limit' => 24,
        ],
        'mountains' => [
            'title' => 'В горы',
            'slugs' => ['russia', 'abkhazia', 'armenia', 'turkey', 'egypt'],
            'pattern' => 'spread',
            'limit' => 20,
        ],
        'family' => [
            'title' => 'С детьми',
            'slugs' => ['turkey', 'egypt', 'thailand', 'uae', 'abkhazia', 'russia', 'vietnam', 'tunisia', 'maldives'],
            'pattern' => 'round',
            'limit' => 24,
        ],
        'budget' => [
            'title' => 'Недорого',
            'slugs' => ['abkhazia', 'russia', 'egypt', 'turkey', 'vietnam', 'srilanka', 'tunisia', 'armenia'],
            'pattern' => 'cheap',
            'limit' => 24,
        ],
    ];

    $moods = [];
    foreach ($moodRules as $moodKey => $rule) {
        $slugs = is_array($rule['slugs'] ?? null) ? $rule['slugs'] : [];
        $limit = (int) ($rule['limit'] ?? 20);
        $pattern = (string) ($rule['pattern'] ?? 'round');
        $picked = th_home_showcase_pick_mood_items($tours, $slugs, $pattern, $limit);
        if ($picked === []) {
            $picked = array_slice($tours, 0, min(12, $limit));
        }
        $moods[$moodKey] = [
            'title' => (string) ($rule['title'] ?? $moodKey),
            'items' => array_values($picked),
        ];
    }

    return [
        'success' => true,
        'departureId' => $departureId,
        'departureName' => $departureName,
        'updatedAt' => time(),
        'mode' => 'tours',
        'source' => $source,
        'flightsByTourId' => $flightsByTourId,
        'moods' => $moods,
        'hot' => array_values(array_slice($tours, 0, 24)),
        'tours' => array_values($tours),
    ];
}
