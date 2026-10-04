<?php
/**
 * Next-patch helpers: Tourvisor server calls, Expo push, image/price helpers.
 */
declare(strict_types=1);

function np_maybe_cors(array $config): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = $config['allowed_origins'] ?? [];
    if (!is_array($allowed)) {
        $allowed = [];
    }
    if ($origin !== '' && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Cron-Token, X-Health-Token');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    }
}

/**
 * @return array{ok:bool,status:int,body:?string,json:?array}
 */
function np_http_json(string $method, string $url, ?array $headers = null, ?string $body = null, int $timeout = 25): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'status' => 0, 'body' => null, 'json' => null];
    }
    $hdrs = $headers ?? [];
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $hdrs,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        error_log('[next-patch] curl error: ' . $err);
        return ['ok' => false, 'status' => $status, 'body' => null, 'json' => null];
    }
    $json = json_decode((string) $raw, true);
    return [
        'ok' => $status >= 200 && $status < 300,
        'status' => $status,
        'body' => (string) $raw,
        'json' => is_array($json) ? $json : null,
    ];
}

function np_tourvisor_base(array $config): string
{
    $base = trim((string) ($config['tourvisor_api_base'] ?? 'https://api.tourvisor.ru/search/api/v1'));
    return rtrim($base, '/');
}

function np_tourvisor_token(array $config): string
{
    return trim((string) ($config['tourvisor_token'] ?? getenv('TOURVISOR_TOKEN') ?: ''));
}

/**
 * Build query string; arrays become repeated keys (Tourvisor style).
 *
 * @param array<string, mixed> $query
 */
function np_query_encode(array $query): string
{
    $parts = [];
    foreach ($query as $k => $v) {
        if ($v === null || $v === '') {
            continue;
        }
        if (is_bool($v)) {
            $parts[] = rawurlencode((string) $k) . '=' . ($v ? 'true' : 'false');
            continue;
        }
        if (is_array($v)) {
            foreach ($v as $item) {
                if ($item === null || $item === '') {
                    continue;
                }
                $parts[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $item);
            }
            continue;
        }
        $parts[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
    }
    return implode('&', $parts);
}

/**
 * Unwrap Tourvisor JSON list/object payloads.
 *
 * @param mixed $json
 * @return array<string, mixed>|list<mixed>|null
 */
function np_unwrap_tourvisor_payload($json)
{
    if (!is_array($json)) {
        return null;
    }
    if (isset($json['data']) && is_array($json['data'])) {
        return $json['data'];
    }
    return $json;
}

/**
 * GET Tourvisor path relative to /search/api/v1, e.g. "/tours/hots?...".
 *
 * @return array<string, mixed>|list<mixed>|null
 */
function np_tourvisor_get(array $config, string $pathAndQuery)
{
    $meta = np_tourvisor_get_meta($config, $pathAndQuery);
    if (!$meta['ok']) {
        return null;
    }
    /** @var mixed $json */
    $json = $meta['json'];
    return is_array($json) ? $json : null;
}

/**
 * GET Tourvisor with metadata (status + response headers for pagination).
 *
 * @return array{ok:bool,status:int,json:mixed,headers:array<string,string>}
 */
function np_tourvisor_get_meta(array $config, string $pathAndQuery): array
{
    $token = np_tourvisor_token($config);
    if ($token === '') {
        return ['ok' => false, 'status' => 0, 'json' => null, 'headers' => []];
    }
    $url = np_tourvisor_base($config) . '/' . ltrim($pathAndQuery, '/');
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'status' => 0, 'json' => null, 'headers' => []];
    }
    $respHeaders = [];
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ],
        CURLOPT_HEADERFUNCTION => static function ($ch, $header) use (&$respHeaders) {
            $len = strlen($header);
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $respHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return $len;
        },
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) {
        return ['ok' => false, 'status' => $status, 'json' => null, 'headers' => $respHeaders];
    }
    $json = json_decode((string) $raw, true);
    return [
        'ok' => $status >= 200 && $status < 300,
        'status' => $status,
        'json' => $json,
        'headers' => $respHeaders,
    ];
}

/**
 * @param list<int> $ids
 * @return array<int, string>
 */
function np_hotel_images_from_db(PDO $pdo, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if ($ids === []) {
        return [];
    }
    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT hotel_id, picture_url FROM hotel_image_cache WHERE hotel_id IN ($placeholders)");
        $stmt->execute($ids);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $map[(int) $row['hotel_id']] = (string) $row['picture_url'];
        }
        return $map;
    } catch (Throwable $e) {
        error_log('[next-patch] hotel_image_cache read failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Attach picturelink/images from cache onto hotel rows.
 *
 * @param list<array<string, mixed>> $hotels
 * @return list<array<string, mixed>>
 */
function np_enrich_hotels_with_images(PDO $pdo, array $hotels, array $config, bool $fetchMissingDetails = false, int $enrichLimit = 12): array
{
    try {
        $ids = [];
        foreach ($hotels as $h) {
            if (is_array($h) && isset($h['id'])) {
                $ids[] = (int) $h['id'];
            }
        }
        $cached = np_hotel_images_from_db($pdo, $ids);
        $enriched = 0;

        foreach ($hotels as $i => $h) {
            if (!is_array($h) || empty($h['id'])) {
                continue;
            }
            $hid = (int) $h['id'];
            $inline = np_extract_hotel_image($h);
            if ($inline) {
                $hotels[$i]['picturelink'] = $inline;
                if (empty($hotels[$i]['images']) || !is_array($hotels[$i]['images'])) {
                    $hotels[$i]['images'] = [$inline];
                }
                np_upsert_hotel_image($pdo, $hid, $inline);
                continue;
            }
            if (isset($cached[$hid])) {
                $hotels[$i]['picturelink'] = $cached[$hid];
                $hotels[$i]['images'] = [$cached[$hid]];
                continue;
            }
            if ($fetchMissingDetails && $enriched < $enrichLimit) {
                $details = np_tourvisor_get($config, '/hotels/' . $hid);
                $enriched++;
                if (is_array($details)) {
                    $hotel = isset($details['data']) && is_array($details['data']) ? $details['data'] : $details;
                    if (is_array($hotel)) {
                        // Merge catalog row with descriptions module fields (photos + text).
                        if (!empty($hotel['common']) && is_array($hotel['common'])) {
                            $hotels[$i]['common'] = array_merge(
                                is_array($hotels[$i]['common'] ?? null) ? $hotels[$i]['common'] : [],
                                $hotel['common']
                            );
                            $desc = trim(strip_tags((string) ($hotel['common']['description'] ?? '')));
                            if ($desc !== '') {
                                $hotels[$i]['descriptionSnippet'] = mb_substr($desc, 0, 180);
                            }
                        }
                        if (!empty($hotel['images']) && is_array($hotel['images'])) {
                            $hotels[$i]['images'] = $hotel['images'];
                        }
                        if (isset($hotel['rating']) && (float) $hotel['rating'] > 0) {
                            $hotels[$i]['rating'] = (float) $hotel['rating'];
                        }
                        $url = np_extract_hotel_image($hotel);
                        if ($url) {
                            $hotels[$i]['picturelink'] = $url;
                            if (empty($hotels[$i]['images']) || !is_array($hotels[$i]['images'])) {
                                $hotels[$i]['images'] = [$url];
                            }
                            np_upsert_hotel_image($pdo, $hid, $url);
                        }
                    }
                }
                usleep(60000);
            }
        }
        return $hotels;
    } catch (Throwable $e) {
        error_log('[next-patch] enrich hotels images failed: ' . $e->getMessage());
        return $hotels;
    }
}

/**
 * Extract first hotel image URL from mixed Tourvisor payloads.
 *
 * @param array<string, mixed> $hotel
 */
function np_extract_hotel_image(array $hotel): ?string
{
    $keys = ['picturelink', 'picture', 'image', 'photo', 'mainImage', 'thumb', 'thumbnail', 'pictureUrl', 'site'];
    foreach ($keys as $k) {
        if (!empty($hotel[$k]) && is_string($hotel[$k])) {
            $u = trim($hotel[$k]);
            if (strpos($u, '//') === 0) {
                $u = 'https:' . $u;
            }
            if (strpos($u, 'http://') === 0 || strpos($u, 'https://') === 0) {
                return $u;
            }
        }
    }
    if (!empty($hotel['images']) && is_array($hotel['images'])) {
        foreach ($hotel['images'] as $item) {
            if (is_string($item) && $item !== '') {
                $u = strpos($item, '//') === 0 ? 'https:' . $item : $item;
                if (strpos($u, 'http') === 0) {
                    return $u;
                }
            }
            if (is_array($item)) {
                foreach (['url', 'link', 'src', 'picture', 'image'] as $ik) {
                    if (!empty($item[$ik]) && is_string($item[$ik])) {
                        $u = trim($item[$ik]);
                        if (strpos($u, '//') === 0) {
                            $u = 'https:' . $u;
                        }
                        if (strpos($u, 'http') === 0) {
                            return $u;
                        }
                    }
                }
            }
        }
    }
    if (!empty($hotel['common']) && is_array($hotel['common'])) {
        return np_extract_hotel_image($hotel['common']);
    }
    return null;
}

function np_upsert_hotel_image(PDO $pdo, int $hotelId, string $url): void
{
    if ($hotelId <= 0 || $url === '') {
        return;
    }
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO hotel_image_cache (hotel_id, picture_url, source, updated_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE picture_url = VALUES(picture_url), source = VALUES(source), updated_at = NOW()'
        );
        $stmt->execute([$hotelId, $url, 'tourvisor']);
    } catch (Throwable $e) {
        error_log('[next-patch] hotel_image_cache write failed: ' . $e->getMessage());
    }
}

/**
 * Send Expo push notifications.
 *
 * @param list<string> $tokens
 * @param array<string, mixed> $data
 * @return array{ok:bool,sent:int}
 */
function np_expo_push(array $tokens, string $title, string $body, array $data = []): array
{
    $tokens = array_values(array_unique(array_filter(array_map('strval', $tokens))));
    if ($tokens === []) {
        return ['ok' => true, 'sent' => 0];
    }
    $messages = [];
    foreach ($tokens as $token) {
        if (strpos($token, 'ExponentPushToken[') !== 0 && strpos($token, 'ExpoPushToken[') !== 0) {
            continue;
        }
        $messages[] = [
            'to' => $token,
            'sound' => 'default',
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'priority' => 'high',
            'channelId' => 'favorites',
        ];
    }
    if ($messages === []) {
        return ['ok' => false, 'sent' => 0];
    }

    $sent = 0;
    foreach (array_chunk($messages, 90) as $chunk) {
        $res = np_http_json(
            'POST',
            'https://exp.host/--/api/v2/push/send',
            [
                'Content-Type: application/json',
                'Accept: application/json',
                'Accept-Encoding: gzip, deflate',
            ],
            json_encode($chunk, JSON_UNESCAPED_UNICODE)
        );
        if ($res['ok']) {
            $sent += count($chunk);
        } else {
            error_log('[next-patch] expo push failed status=' . $res['status']);
        }
    }
    return ['ok' => $sent > 0, 'sent' => $sent];
}

/**
 * @param list<array<string, mixed>> $favorites
 * @param list<array<string, mixed>> $recent
 * @param list<array<string, mixed>> $hot
 * @return list<array<string, mixed>>
 */
function np_build_recommendations(array $favorites, array $recent, array $hot, int $limit = 8): array
{
    $out = [];
    $seen = [];

    foreach ($favorites as $fav) {
        if (count($out) >= $limit) {
            break;
        }
        $payload = is_array($fav['payload'] ?? null) ? $fav['payload'] : [];
        $tourId = (string) ($fav['item_id'] ?? $payload['id'] ?? '');
        $hotel = is_array($payload['hotel'] ?? null) ? $payload['hotel'] : [];
        $title = (string) ($hotel['name'] ?? $payload['name'] ?? 'Тур');
        $key = $tourId !== '' ? 't:' . $tourId : 'h:' . ($hotel['id'] ?? $title);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $country = is_array($hotel['country'] ?? null) ? $hotel['country'] : [];
        $region = is_array($hotel['region'] ?? null) ? $hotel['region'] : [];
        $image = np_extract_hotel_image($hotel);
        if (!$image && !empty($payload['picture']) && is_string($payload['picture'])) {
            $image = $payload['picture'];
        }
        $out[] = [
            'key' => 'fav_' . $key,
            'title' => $title,
            'subtitle' => trim(implode(' · ', array_filter([
                (string) ($country['name'] ?? ''),
                (string) ($region['name'] ?? ''),
            ]))),
            'price' => (float) ($payload['price'] ?? 0),
            'currency' => (string) ($payload['currency'] ?? 'RUB'),
            'image' => $image,
            'stars' => (int) ($hotel['stars'] ?? $hotel['category'] ?? 0),
            'tourId' => $tourId !== '' ? $tourId : null,
            'countryId' => isset($country['id']) ? (int) $country['id'] : null,
            'countryName' => isset($country['name']) ? (string) $country['name'] : null,
            'source' => 'favorite',
        ];
    }

    foreach ($hot as $idx => $h) {
        if (count($out) >= $limit) {
            break;
        }
        if (!is_array($h)) {
            continue;
        }
        $hotel = is_array($h['hotel'] ?? null) ? $h['hotel'] : [];
        $country = is_array($h['country'] ?? null) ? $h['country'] : (is_array($hotel['country'] ?? null) ? $hotel['country'] : []);
        $title = (string) ($hotel['name'] ?? 'Отель');
        $dedupe = 'c:' . ($country['id'] ?? '') . ':' . $title;
        if (isset($seen[$dedupe])) {
            continue;
        }
        $seen[$dedupe] = true;
        $region = is_array($hotel['region'] ?? null) ? $hotel['region'] : [];
        $out[] = [
            'key' => 'hot_' . ($hotel['id'] ?? $idx) . '_' . ($h['date'] ?? $idx),
            'title' => $title,
            'subtitle' => trim(implode(' · ', array_filter([
                (string) ($country['name'] ?? ''),
                (string) ($region['name'] ?? ''),
            ]))),
            'price' => (float) ($h['price'] ?? 0),
            'currency' => (string) ($h['currency'] ?? 'RUB'),
            'image' => np_extract_hotel_image($hotel),
            'stars' => (int) ($hotel['category'] ?? 0),
            'tourId' => null,
            'countryId' => isset($country['id']) ? (int) $country['id'] : null,
            'countryName' => isset($country['name']) ? (string) $country['name'] : null,
            'source' => 'hot',
        ];
    }

    return $out;
}

function np_site_base(array $config): string
{
    $base = trim((string) ($config['site_url'] ?? $config['app_url'] ?? $config['api_url'] ?? ''));
    if ($base === '') {
        $base = 'https://travelhub63.ru';
    }

    return rtrim($base, '/');
}

/**
 * Popular countries for home hot-deals when countryIds not provided.
 *
 * @return list<int>
 */
function np_default_hot_country_ids(): array
{
    // Турция, Египет, ОАЭ, Таиланд, Россия (Сочи и др. через country 47/2 часто в кэше акций)
    return [4, 1, 9, 8, 2];
}

/**
 * Map promo/search hotels → TourHot rows expected by the mobile app.
 *
 * @param list<array<string, mixed>> $hotels
 * @return list<array<string, mixed>>
 */
function np_promo_hotels_to_tour_hots(array $hotels, int $departureId, string $currency = 'RUB'): array
{
    $departure = [
        'id' => $departureId,
        'name' => $departureId === 1 ? 'Москва' : '',
        'nameGenitive' => $departureId === 1 ? 'Москвы' : '',
    ];
    $out = [];

    foreach ($hotels as $hotel) {
        if (!is_array($hotel)) {
            continue;
        }
        $hid = isset($hotel['id']) ? (int) $hotel['id'] : 0;
        if ($hid <= 0) {
            continue;
        }

        $country = is_array($hotel['country'] ?? null) ? $hotel['country'] : null;
        $region = is_array($hotel['region'] ?? null) ? $hotel['region'] : null;
        $subRegion = is_array($hotel['subRegion'] ?? null) ? $hotel['subRegion'] : null;
        $pic = isset($hotel['picturelink']) ? trim((string) $hotel['picturelink']) : '';
        if ($pic === '') {
            $pic = 'https://static.tourvisor.ru/hotel_pics/main400/' . $hid . '.jpg';
        }

        $hotelPayload = [
            'id' => $hid,
            'name' => (string) ($hotel['name'] ?? ''),
            'category' => (int) ($hotel['category'] ?? 0),
            'rating' => (float) ($hotel['rating'] ?? 0),
            'country' => $country,
            'region' => $region,
            'subRegion' => $subRegion,
            'type' => (int) ($hotel['type'] ?? 0),
            'latitude' => (float) ($hotel['latitude'] ?? 0),
            'longitude' => (float) ($hotel['longitude'] ?? 0),
            'picturelink' => $pic,
            'hotelDescriptionLink' => (string) ($hotel['hotelDescriptionLink'] ?? ''),
        ];

        $tours = is_array($hotel['tours'] ?? null) ? $hotel['tours'] : [];
        if ($tours === []) {
            $price = (int) ($hotel['price'] ?? 0);
            if ($price <= 0) {
                continue;
            }
            $out[] = [
                'country' => $country,
                'departure' => $departure,
                'hotel' => $hotelPayload,
                'meal' => null,
                'operator' => null,
                'currency' => (string) ($hotel['currency'] ?? $currency),
                'date' => '',
                'nights' => 0,
                'price' => $price,
                'priceOld' => 0,
            ];
            continue;
        }

        foreach ($tours as $tour) {
            if (!is_array($tour)) {
                continue;
            }
            $price = (int) ($tour['price'] ?? 0);
            if ($price <= 0) {
                $price = (int) ($hotel['price'] ?? 0);
            }
            if ($price <= 0) {
                continue;
            }
            $priceOld = (int) ($tour['priceOld'] ?? $tour['oldPrice'] ?? 0);
            $out[] = [
                'country' => $country,
                'departure' => $departure,
                'hotel' => $hotelPayload,
                'meal' => is_array($tour['meal'] ?? null) ? $tour['meal'] : null,
                'operator' => is_array($tour['operator'] ?? null) ? $tour['operator'] : null,
                'currency' => (string) ($tour['currency'] ?? $hotel['currency'] ?? $currency),
                'date' => (string) ($tour['date'] ?? ''),
                'nights' => (int) ($tour['nights'] ?? 0),
                'price' => $price,
                'priceOld' => $priceOld,
                'tourId' => (string) ($tour['id'] ?? $tour['tourId'] ?? ''),
            ];
        }
    }

    usort($out, static function ($a, $b) {
        return ((int) ($a['price'] ?? 0)) <=> ((int) ($b['price'] ?? 0));
    });

    return $out;
}

/**
 * Site promo-search cache (акции) — used when Tourvisor /tours/hots returns 403.
 *
 * @param list<int> $countryIds
 * @return list<array<string, mixed>>
 */
function np_fetch_promo_hotels_for_hots(
    array $config,
    int $departureId,
    array $countryIds,
    int $perCountry = 30
): array {
    $base = np_site_base($config);
    $all = [];
    $seenHotel = [];

    foreach ($countryIds as $cid) {
        $cid = (int) $cid;
        if ($cid <= 0) {
            continue;
        }
        $qs = http_build_query([
            'type' => 'promo-search',
            'countryId' => $cid,
            'departureId' => $departureId,
            'limit' => max(5, min(80, $perCountry)),
            'cacheOnly' => 1,
            'adults' => 2,
        ]);
        $url = $base . '/backend/api/tourvisor-proxy.php?' . $qs;
        $res = np_http_json('GET', $url, ['Accept: application/json'], null, 18);
        if (!$res['ok'] || !is_array($res['json'])) {
            continue;
        }
        $payload = $res['json'];
        $rows = [];
        if (!empty($payload['success']) && isset($payload['data']) && is_array($payload['data'])) {
            $rows = $payload['data'];
        } elseif (isset($payload['data']) && is_array($payload['data'])) {
            $rows = $payload['data'];
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $hid = (int) ($row['id'] ?? 0);
            if ($hid > 0) {
                if (isset($seenHotel[$hid])) {
                    continue;
                }
                $seenHotel[$hid] = true;
            }
            $all[] = $row;
        }
    }

    return $all;
}

/**
 * Live Tourvisor search snapshot (no onlyPromo param in API — take cheapest tours).
 *
 * @return list<array<string, mixed>>
 */
function np_tourvisor_search_hotels_snapshot(
    array $config,
    int $departureId,
    int $countryId,
    string $currency = 'RUB',
    int $limit = 40,
    int $waitSeconds = 12
): array {
    $dateFrom = date('Y-m-d', strtotime('+2 days'));
    $dateTo = date('Y-m-d', strtotime('+16 days'));
    $query = [
        'departureId' => $departureId,
        'countryId' => $countryId,
        'dateFrom' => $dateFrom,
        'dateTo' => $dateTo,
        'nightsFrom' => 6,
        'nightsTo' => 14,
        'adults' => 2,
        'currency' => $currency,
        'onlyCharter' => false,
    ];
    $start = np_tourvisor_get_meta($config, '/tours/search?' . np_query_encode($query));
    if (!$start['ok']) {
        return [];
    }
    $payload = np_unwrap_tourvisor_payload($start['json']);
    $searchId = is_array($payload) ? (int) ($payload['searchId'] ?? $payload['id'] ?? 0) : 0;
    if ($searchId <= 0) {
        return [];
    }

    $deadline = time() + max(4, min(25, $waitSeconds));
    while (time() < $deadline) {
        $stMeta = np_tourvisor_get_meta($config, '/tours/search/' . $searchId . '/status');
        if ($stMeta['ok']) {
            $st = np_unwrap_tourvisor_payload($stMeta['json']);
            $progress = is_array($st) ? (int) ($st['progress'] ?? 0) : 0;
            $status = is_array($st) ? strtolower((string) ($st['status'] ?? '')) : '';
            if ($progress >= 100 || in_array($status, ['done', 'finished', 'complete', 'completed'], true)) {
                break;
            }
        }
        usleep(900000);
    }

    $resMeta = np_tourvisor_get_meta(
        $config,
        '/tours/search/' . $searchId . '?' . np_query_encode(['limit' => $limit])
    );
    if (!$resMeta['ok']) {
        return [];
    }
    $rows = np_unwrap_tourvisor_payload($resMeta['json']);
    if (!is_array($rows)) {
        return [];
    }
    $isList = $rows === [] || array_keys($rows) === range(0, count($rows) - 1);

    return $isList ? $rows : [];
}

/**
 * Fallback hot tours when /tours/hots is forbidden or empty.
 *
 * @param list<int> $countryIds
 * @return list<array<string, mixed>>
 */
function np_hot_tours_fallback(
    array $config,
    int $departureId,
    array $countryIds,
    string $currency,
    int $limit
): array {
    $ids = array_values(array_filter(array_map('intval', $countryIds)));
    if ($ids === []) {
        $ids = np_default_hot_country_ids();
    }
    $per = max(10, (int) ceil($limit / max(1, min(3, count($ids)))) + 5);
    $hotels = np_fetch_promo_hotels_for_hots($config, $departureId, $ids, $per);
    if ($hotels === []) {
        // Live search for first 1–2 countries if promo cache empty
        foreach (array_slice($ids, 0, 2) as $cid) {
            $chunk = np_tourvisor_search_hotels_snapshot($config, $departureId, (int) $cid, $currency, min(40, $limit), 10);
            foreach ($chunk as $row) {
                if (is_array($row)) {
                    $hotels[] = $row;
                }
            }
            if (count($hotels) >= $limit) {
                break;
            }
        }
    }

    $hots = np_promo_hotels_to_tour_hots($hotels, $departureId, $currency);

    return array_slice($hots, 0, max(1, $limit));
}
