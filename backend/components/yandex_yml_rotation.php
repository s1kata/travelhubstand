<?php
declare(strict_types=1);
/**
 * Ротация YML для Яндекс.Бизнес: пакет стран → batch × N туров → полная замена /feed.yml.
 *
 * Приоритет отбора на страну:
 * 1) обычный search-cached (min price, sanity)
 * 2) promo_cache / promo-search (добор)
 * 3) замена страной из pool (если 0 туров)
 */

require_once __DIR__ . '/yandex_yml_rotation_schema.php';
require_once __DIR__ . '/yandex_yml_rules_runner.php';
require_once __DIR__ . '/yandex_feed_sync.php';
require_once __DIR__ . '/th_tour_price.php';

function yandex_feed_rotation_log(string $line): void
{
    yandex_yml_rules_log_line('[rotation] ' . $line);
}

/** @return list<string> */
function yandex_feed_rotation_excluded_tour_ids(PDO $pdo, int $excludeBatches): array
{
    $excludeBatches = max(0, min(12, $excludeBatches));
    if ($excludeBatches <= 0) {
        return [];
    }
    $state = yandex_feed_rotation_get_state($pdo);
    $currentBatch = (int) ($state['batch_index'] ?? 0);
    $minBatch = max(0, $currentBatch - $excludeBatches);
    try {
        $stmt = $pdo->prepare('SELECT DISTINCT tour_id FROM yandex_feed_rotation_history WHERE batch_index >= ? AND batch_index < ?');
        $stmt->execute([$minBatch, $currentBatch]);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tid = trim((string) ($row['tour_id'] ?? ''));
            if ($tid !== '') {
                $out[] = $tid;
            }
        }

        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @param array<string, string> $extra
 * @param array<string, mixed>|null $settings
 * @return array{success: bool, data: array<int, array<string, mixed>>}
 */
function yandex_feed_rotation_proxy_search(
    string $proxyBase,
    int $timeout,
    array $extra,
    ?array $settings = null
): array {
    $nf = max(1, min(28, (int) ($settings['nights_from'] ?? 6)));
    $nt = max($nf, min(28, (int) ($settings['nights_to'] ?? 14)));
    if (($nt - $nf) > 10) {
        $nt = $nf + 10; // лимит Tourvisor
    }
    $params = array_merge([
        'type' => 'search-cached',
        'adults' => '2',
        'currency' => 'RUB',
        'cacheScope' => 'country_page',
        'slim' => '1',
        'nightsFrom' => (string) $nf,
        'nightsTo' => (string) $nt,
    ], $extra);
    $flightMode = strtolower(trim((string) ($settings['flight_mode'] ?? 'any')));
    if ($flightMode === 'direct' && empty($params['onlyDirect'])) {
        $params['onlyDirect'] = '1';
    }
    $dispatch = yandex_yml_rules_promo_dispatch($proxyBase, $timeout);
    $decoded = $dispatch($params);
    if (!is_array($decoded)) {
        return ['success' => false, 'data' => []];
    }
    $data = $decoded['data'] ?? [];
    if (!is_array($data)) {
        $data = [];
    }

    return ['success' => !empty($decoded['success']), 'data' => $data];
}

/**
 * @return array<int, array<string, mixed>>
 */
function yandex_feed_rotation_fetch_regular_hotels(
    int $countryId,
    int $depId,
    string $cityLabel,
    string $proxyBase,
    int $timeout,
    string $dateFrom,
    string $dateTo,
    bool $forceLive,
    ?array $settings = null
): array {
    $depNorm = th_departure_normalize_id($depId);
    $base = [
        'departureId' => (string) $depNorm,
        'countryId' => (string) $countryId,
        'dateFrom' => $dateFrom,
        'dateTo' => $dateTo,
    ];
    $resp = yandex_feed_rotation_proxy_search($proxyBase, $timeout, array_merge($base, ['cacheOnly' => '1']), $settings);
    $hotels = is_array($resp['data']) ? $resp['data'] : [];
    if ($hotels === [] && $forceLive) {
        $resp = yandex_feed_rotation_proxy_search($proxyBase, $timeout, array_merge($base, ['live' => '1']), $settings);
        $hotels = is_array($resp['data']) ? $resp['data'] : [];
    }
    if ($hotels === []) {
        return [];
    }

    return yandex_yml_rules_apply_promo_hotel_filters($hotels, $countryId, $depId, $cityLabel, $proxyBase, $timeout);
}

/**
 * @param array<int, array<string, mixed>> $hotels
 * @param list<string> $excludeTourIds
 * @return list<array<string, mixed>>
 */
function yandex_feed_rotation_collect_plausible_offers(
    array $hotels,
    int $countryId,
    string $countryName,
    string $dateFrom,
    string $dateTo,
    string $siteBase,
    string $imageProxyBase,
    int $targetCount,
    ?string $departureCity,
    int $departureId,
    array $excludeTourIds
): array {
    $targetCount = max(1, min(50, $targetCount));
    $exclude = array_fill_keys($excludeTourIds, true);
    $candidates = [];

    foreach ($hotels as $h) {
        if (!is_array($h)) {
            continue;
        }
        $tours = $h['tours'] ?? [];
        if (!is_array($tours) || $tours === []) {
            continue;
        }
        $cname = $countryName;
        if (!empty($h['country']) && is_array($h['country'])) {
            $cn = trim((string) ($h['country']['name'] ?? $h['country']['russianName'] ?? ''));
            if ($cn !== '') {
                $cname = $cn;
            }
        }

        $bestRow = null;
        $bestPrice = PHP_FLOAT_MAX;
        foreach ($tours as $tour) {
            if (!is_array($tour)) {
                continue;
            }
            $tid = (string) ($tour['id'] ?? $tour['tour_id'] ?? '');
            if ($tid !== '' && isset($exclude[$tid])) {
                continue;
            }
            $nights = (int) ($tour['nights'] ?? 0);
            $price = th_tour_price_pick_num($tour);
            if ($price <= 0 || !th_tour_price_is_plausible($price, $countryId, $nights > 0 ? $nights : null)) {
                continue;
            }
            $hCopy = $h;
            $tCopy = $tour;
            $hCopy['price'] = $price;
            if (!isset($tCopy['totalPrice']) || (int) $tCopy['totalPrice'] <= 0) {
                $tCopy['price'] = $price;
            }
            $row = yandex_feed_row_from_hotel_tour(
                $hCopy,
                $tCopy,
                $cname,
                $dateFrom,
                $dateTo,
                $siteBase,
                $imageProxyBase,
                $departureCity,
                false
            );
            if ($row === null || !yandex_yml_rules_row_passes_xml_gate($row, $siteBase)) {
                continue;
            }
            $p = (float) $row['price'];
            if ($p < $bestPrice) {
                $bestPrice = $p;
                $bestRow = $row;
            }
        }
        if ($bestRow === null) {
            continue;
        }
        $bestRow['country_id'] = $countryId;
        $bestRow['country_name'] = $cname;
        $candidates[] = $bestRow;
    }

    usort($candidates, static function (array $a, array $b): int {
        return ((float) $a['price']) <=> ((float) $b['price']);
    });

    $out = [];
    $seenHotel = [];
    foreach ($candidates as $row) {
        if (count($out) >= $targetCount) {
            break;
        }
        $hid = (int) ($row['hotel_id'] ?? 0);
        if ($hid > 0) {
            if (isset($seenHotel[$hid])) {
                continue;
            }
            $seenHotel[$hid] = true;
        }
        $row['source_departure_id'] = th_departure_normalize_id($departureId);
        $row['rule_stars_filter'] = 0;
        $row['rule_id'] = 0;
        $out[] = $row;
    }

    return $out;
}

/**
 * @param list<string> $excludeTourIds
 * @return array{rows: list<array<string, mixed>>, source: string, count: int}
 */
function yandex_feed_rotation_collect_for_country(
    PDO $pdo,
    int $countryId,
    string $countryName,
    array $settings,
    string $proxyBase,
    int $timeout,
    string $siteBase,
    string $imageProxyBase,
    array $excludeTourIds,
    bool $allowLive
): array {
    $depId = (int) ($settings['source_departure_id'] ?? 7);
    $cityLabel = trim((string) ($settings['source_city'] ?? 'Самара'));
    $target = max(1, min(50, (int) ($settings['tours_per_country'] ?? 5)));
    $datesFrom = date('Y-m-d', strtotime('+2 days'));
    $datesTo = date('Y-m-d', strtotime('+60 days'));

    $regular = yandex_feed_rotation_fetch_regular_hotels(
        $countryId,
        $depId,
        $cityLabel,
        $proxyBase,
        $timeout,
        $datesFrom,
        $datesTo,
        $allowLive,
        $settings
    );
    $rows = yandex_feed_rotation_collect_plausible_offers(
        $regular,
        $countryId,
        $countryName,
        $datesFrom,
        $datesTo,
        $siteBase,
        $imageProxyBase,
        $target,
        $cityLabel,
        $depId,
        $excludeTourIds
    );
    $source = 'search';

    if (count($rows) < $target) {
        $promoHit = yandex_yml_rules_fetch_promo_hotels_for_rule($countryId, $depId, $proxyBase, $timeout);
        if ($promoHit !== null) {
            $promoHotels = yandex_yml_rules_apply_promo_hotel_filters(
                $promoHit['hotels'],
                $countryId,
                $depId,
                $cityLabel,
                $proxyBase,
                $timeout
            );
            $promoRows = yandex_feed_rotation_collect_plausible_offers(
                $promoHotels,
                $countryId,
                $countryName,
                $promoHit['dateFrom'] ?: $datesFrom,
                $promoHit['dateTo'] ?: $datesTo,
                $siteBase,
                $imageProxyBase,
                $target,
                $cityLabel,
                $depId,
                $excludeTourIds
            );
            $seen = [];
            foreach ($rows as $r) {
                $seen[(string) ($r['tour_id'] ?? '')] = true;
            }
            foreach ($promoRows as $pr) {
                if (count($rows) >= $target) {
                    break;
                }
                $tid = (string) ($pr['tour_id'] ?? '');
                if ($tid !== '' && isset($seen[$tid])) {
                    continue;
                }
                $rows[] = $pr;
                if ($tid !== '') {
                    $seen[$tid] = true;
                }
            }
            if ($promoRows !== []) {
                $source = count($regular) > 0 ? 'search+promo' : 'promo';
            }
        }
    }

    usort($rows, static function (array $a, array $b): int {
        return ((float) $a['price']) <=> ((float) $b['price']);
    });
    if (count($rows) > $target) {
        $rows = array_slice($rows, 0, $target);
    }

    return ['rows' => $rows, 'source' => $source, 'count' => count($rows)];
}

/**
 * @param list<array<string, mixed>> $pool
 * @return list<array<string, mixed>>
 */
function yandex_feed_rotation_pick_planned_batch(array $pool, int $batchIndex, int $batchSize): array
{
    $pool = array_values($pool);
    $n = count($pool);
    if ($n === 0 || $batchSize <= 0) {
        return [];
    }
    $start = ($batchIndex * $batchSize) % $n;
    $out = [];
    for ($i = 0; $i < $batchSize && $i < $n; $i++) {
        $out[] = $pool[($start + $i) % $n];
    }

    return $out;
}

/**
 * @param list<array<string, mixed>> $pool
 * @return list<array<string, mixed>>
 */
function yandex_feed_rotation_replacement_queue(array $pool, int $afterIndex, int $limit): array
{
    $pool = array_values($pool);
    $n = count($pool);
    if ($n === 0 || $limit <= 0) {
        return [];
    }
    $out = [];
    for ($j = 1; $j <= $n && count($out) < $limit; $j++) {
        $out[] = $pool[($afterIndex + $j) % $n];
    }

    return $out;
}

function yandex_feed_rotation_days_since(?string $dt): ?int
{
    if ($dt === null || trim($dt) === '') {
        return null;
    }
    $ts = strtotime($dt);
    if ($ts === false) {
        return null;
    }

    return (int) floor((time() - $ts) / 86400);
}

function yandex_feed_rotation_save_state(
    PDO $pdo,
    int $batchIndex,
    int $offersCount,
    array $planned,
    array $actual,
    string $log
): void {
    $plannedJson = json_encode($planned, JSON_UNESCAPED_UNICODE);
    $actualJson = json_encode($actual, JSON_UNESCAPED_UNICODE);
    $stmt = $pdo->prepare('UPDATE yandex_feed_rotation_state SET batch_index = ?, last_rotated_at = ?, last_offers_count = ?, planned_countries_json = ?, actual_countries_json = ?, last_log = ?, updated_at = ? WHERE id = 1');
    $now = date('Y-m-d H:i:s');
    $stmt->execute([$batchIndex, $now, $offersCount, $plannedJson, $actualJson, $log, $now]);
}

function yandex_feed_rotation_record_history(PDO $pdo, int $batchIndex, array $rows): void
{
    if ($rows === []) {
        return;
    }
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare('INSERT INTO yandex_feed_rotation_history (batch_index, country_id, tour_id, rotated_at) VALUES (?,?,?,?)');
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $tid = trim((string) ($row['tour_id'] ?? ''));
        $cid = (int) ($row['country_id'] ?? 0);
        if ($tid === '') {
            continue;
        }
        try {
            $stmt->execute([$batchIndex, $cid, $tid, $now]);
        } catch (Throwable $e) {
            error_log('[yandex_feed_rotation_record_history] ' . $e->getMessage());
        }
    }
    try {
        $pdo->exec('DELETE FROM yandex_feed_rotation_history WHERE rotated_at < DATE_SUB(NOW(), INTERVAL 120 DAY)');
    } catch (Throwable $e) {
        try {
            $pdo->exec("DELETE FROM yandex_feed_rotation_history WHERE rotated_at < datetime('now', '-120 days')");
        } catch (Throwable $e2) {
            // ignore
        }
    }
}

/** @return list<int> country ids for warm (planned batch + spare) */
function yandex_feed_rotation_country_ids_for_warm(PDO $pdo): array
{
    if (!yandex_feed_rotation_is_active($pdo)) {
        return [];
    }
    $settings = yandex_feed_rotation_get_settings($pdo);
    $pool = yandex_feed_rotation_get_pool($pdo, true);
    if ($pool === []) {
        return [];
    }
    $batchSize = max(1, (int) ($settings['countries_per_batch'] ?? 3));
    $state = yandex_feed_rotation_get_state($pdo);
    $batchIndex = (int) ($state['batch_index'] ?? 0);
    $planned = yandex_feed_rotation_pick_planned_batch($pool, $batchIndex, $batchSize);
    $startIdx = ($batchIndex * $batchSize) % max(1, count($pool));
    $spares = yandex_feed_rotation_replacement_queue($pool, $startIdx + $batchSize - 1, (int) ($settings['max_country_replacements'] ?? 3));
    $ids = [];
    foreach (array_merge($planned, $spares) as $c) {
        $cid = (int) ($c['country_id'] ?? 0);
        if ($cid > 0) {
            $ids[$cid] = $cid;
        }
    }

    return array_values($ids);
}

/**
 * @return array{
 *   ok: bool,
 *   rotated?: bool,
 *   skipped?: bool,
 *   offers_written?: int,
 *   message?: string,
 *   errors?: list<string>,
 *   lock_busy?: bool,
 *   stale_kept?: bool
 * }
 */
function yandex_yml_rotation_run(PDO $pdo, bool $blockingLock = true, bool $force = false): array
{
    if (!yandex_feed_rotation_env_enabled()) {
        return ['ok' => false, 'skipped' => true, 'message' => 'YML_FEED_ROTATION_ENABLED=0'];
    }

    yandex_feed_rotation_ensure_tables($pdo);
    $settings = yandex_feed_rotation_get_settings($pdo);
    if (empty($settings['enabled'])) {
        return ['ok' => false, 'skipped' => true, 'message' => 'rotation disabled in admin'];
    }

    if (!empty($settings['frozen']) && !$force) {
        yandex_feed_rotation_log('SKIP frozen=1');

        return ['ok' => true, 'skipped' => true, 'message' => 'rotation frozen'];
    }

    $pool = yandex_feed_rotation_get_pool($pdo, true);
    if ($pool === []) {
        return ['ok' => false, 'message' => 'country pool empty'];
    }

    $state = yandex_feed_rotation_get_state($pdo);
    $intervalDays = max(1, (int) ($settings['rotation_interval_days'] ?? 7));
    $daysSince = yandex_feed_rotation_days_since(isset($state['last_rotated_at']) ? (string) $state['last_rotated_at'] : null);

    if (!$force && $daysSince !== null && $daysSince < $intervalDays) {
        yandex_feed_rotation_log('SKIP interval days_since=' . $daysSince . ' need=' . $intervalDays);

        return [
            'ok' => true,
            'skipped' => true,
            'message' => 'next rotation in ' . ($intervalDays - $daysSince) . ' days',
            'offers_written' => (int) ($state['last_offers_count'] ?? 0),
        ];
    }

    $fh = yandex_yml_rules_acquire_lock($blockingLock);
    if ($fh === false) {
        return ['ok' => false, 'lock_busy' => true, 'message' => 'lock busy'];
    }

    $errors = [];
    try {
        $siteBase = rtrim((string) (getenv('SITE_URL') ?: ($_ENV['SITE_URL'] ?? 'https://travelhub63.ru')), '/');
        if (preg_match('#/frontend/?$#i', $siteBase)) {
            $siteBase = (string) preg_replace('#/frontend/?$#i', '', $siteBase);
            $siteBase = rtrim($siteBase, '/');
        }
        $imageProxyBase = $siteBase . '/backend/api/tourvisor-image-proxy.php';
        $proxyBase = get_tourvisor_proxy_http_base_url();
        $timeout = (int) (getenv('YML_RULES_HTTP_TIMEOUT') ?: ($_ENV['YML_RULES_HTTP_TIMEOUT'] ?? 120));
        $timeout = max(30, min(300, $timeout));

        $batchSize = max(1, (int) ($settings['countries_per_batch'] ?? 3));
        $maxReplace = max(0, (int) ($settings['max_country_replacements'] ?? 3));
        $minPublish = max(1, (int) ($settings['min_offers_publish'] ?? 8));
        $excludeBatches = max(0, (int) ($settings['history_exclude_batches'] ?? 3));
        $batchIndex = (int) ($state['batch_index'] ?? 0);

        $planned = yandex_feed_rotation_pick_planned_batch($pool, $batchIndex, $batchSize);
        $poolCount = count($pool);
        $startIdx = ($batchIndex * $batchSize) % max(1, $poolCount);
        $excludeTourIds = yandex_feed_rotation_excluded_tour_ids($pdo, $excludeBatches);
        $allowLive = filter_var(getenv('YML_ROTATION_SEARCH_LIVE') ?: ($_ENV['YML_ROTATION_SEARCH_LIVE'] ?? '1'), FILTER_VALIDATE_BOOLEAN);
        $cities = yandex_feed_rotation_enabled_cities($settings);

        $plannedMeta = [];
        foreach ($planned as $pc) {
            $plannedMeta[] = [
                'country_id' => (int) ($pc['country_id'] ?? 0),
                'country_name' => (string) ($pc['country_name'] ?? ''),
            ];
        }

        $allRows = [];
        $actualMeta = [];
        $cityFeedBits = [];
        $replacementsUsedTotal = 0;
        $anyCityPublished = false;

        foreach ($cities as $city) {
            $citySettings = $settings;
            $citySettings['source_departure_id'] = (int) $city['departure_id'];
            $citySettings['source_city'] = (string) $city['label'];
            $slug = (string) $city['slug'];
            $depId = (int) $city['departure_id'];

            $replacementQueue = yandex_feed_rotation_replacement_queue($pool, $startIdx + count($planned) - 1, $maxReplace + $batchSize);
            $queue = $planned;
            $queueIdx = 0;
            $replacementsUsed = 0;
            $cityRows = [];

            while ($queueIdx < count($queue)) {
                $country = $queue[$queueIdx];
                $queueIdx++;
                $countryId = (int) ($country['country_id'] ?? 0);
                $countryName = trim((string) ($country['country_name'] ?? ''));
                if ($countryId <= 0) {
                    continue;
                }

                $result = yandex_feed_rotation_collect_for_country(
                    $pdo,
                    $countryId,
                    $countryName,
                    $citySettings,
                    $proxyBase,
                    $timeout,
                    $siteBase,
                    $imageProxyBase,
                    $excludeTourIds,
                    $allowLive
                );
                $rows = $result['rows'];
                $meta = [
                    'city' => $slug,
                    'country_id' => $countryId,
                    'country_name' => $countryName,
                    'source' => $result['source'],
                    'offers' => $result['count'],
                    'replaced' => false,
                ];

                if ($rows === [] && $replacementsUsed < $maxReplace && $replacementQueue !== []) {
                    $next = array_shift($replacementQueue);
                    if (is_array($next)) {
                        $replacementsUsed++;
                        $queue[] = $next;
                        $meta['replaced'] = true;
                        $meta['replacement_for'] = $countryId;
                        yandex_feed_rotation_log("REPLACE city={$slug} country={$countryId} -> next=" . (int) ($next['country_id'] ?? 0));
                    }
                }

                $actualMeta[] = $meta;
                foreach ($rows as $r) {
                    $cityRows[] = $r;
                    $allRows[] = $r;
                }
                yandex_feed_rotation_log("city={$slug} country={$countryId} {$countryName} source={$result['source']} offers={$result['count']}");
            }

            $replacementsUsedTotal += $replacementsUsed;
            $mergedCity = yandex_yml_rules_merge_dedupe($cityRows);
            $cityOfferCount = count($mergedCity);
            $minCity = yandex_yml_rules_publish_min_offers_for_slug($slug);

            if ($cityOfferCount < max($minPublish, $minCity)) {
                yandex_feed_rotation_log("city={$slug} stale_kept offers={$cityOfferCount} min=" . max($minPublish, $minCity));
                $cityFeedBits[] = $city['label'] . ': мало туров (' . $cityOfferCount . ')';
                continue;
            }

            $depSnap = yandex_yml_rules_feed_snapshot_path_for_departure($depId);
            $genDep = yandex_yml_rules_write_combined_yml_to($depSnap, $mergedCity, $siteBase, 0, []);
            if (!empty($genDep['ok']) && empty($genDep['stale_kept'])) {
                $slugPub = yandex_yml_rules_publish_slug_snapshot_from_departure(
                    $depId,
                    $slug,
                    $depSnap,
                    (int) ($genDep['offers'] ?? $cityOfferCount)
                );
                $anyCityPublished = true;
                $cityFeedBits[] = $city['label'] . ': ' . (int) ($genDep['offers'] ?? $cityOfferCount)
                    . (!empty($slugPub['stale_kept_slug']) ? ' (городской фид не обновлён)' : '');
                yandex_feed_rotation_log("city={$slug} published offers=" . (int) ($genDep['offers'] ?? $cityOfferCount));
            } else {
                $cityFeedBits[] = $city['label'] . ': не обновлён';
                yandex_feed_rotation_log("city={$slug} write_fail");
            }
        }

        $merged = yandex_yml_rules_merge_dedupe($allRows);
        $offerCount = count($merged);
        yandex_feed_rotation_log('batch_index=' . $batchIndex . ' candidate_offers=' . $offerCount . ' min=' . $minPublish);

        if (!$anyCityPublished || $offerCount < $minPublish) {
            $logMsg = 'FAIL offers=' . $offerCount . '<min=' . $minPublish . ' stale_kept batch=' . $batchIndex
                . ' cities=[' . implode('; ', $cityFeedBits) . ']';
            yandex_feed_rotation_log($logMsg);
            $stmt = $pdo->prepare('UPDATE yandex_feed_rotation_state SET last_log = ?, updated_at = ? WHERE id = 1');
            $stmt->execute([$logMsg, date('Y-m-d H:i:s')]);

            return [
                'ok' => false,
                'stale_kept' => true,
                'offers_written' => (int) ($state['last_offers_count'] ?? 0),
                'message' => $logMsg,
                'errors' => ['Not enough offers for publish'],
            ];
        }

        $gen = yandex_yml_rules_write_combined_yml($merged, $siteBase, 0);
        if (!empty($gen['stale_kept'])) {
            return [
                'ok' => false,
                'stale_kept' => true,
                'offers_written' => (int) ($state['last_offers_count'] ?? 0),
                'message' => 'publish gate stale_kept',
                'errors' => $errors,
            ];
        }
        if (empty($gen['ok'])) {
            return [
                'ok' => false,
                'message' => $gen['error'] ?? 'write failed',
                'errors' => [$gen['error'] ?? 'write failed'],
            ];
        }

        $newBatchIndex = $batchIndex + 1;
        $logMsg = 'OK batch=' . $batchIndex . ' offers=' . $offerCount
            . ' replacements=' . $replacementsUsedTotal
            . ' | ' . implode('; ', $cityFeedBits);
        yandex_feed_rotation_save_state($pdo, $newBatchIndex, $offerCount, $plannedMeta, $actualMeta, $logMsg);
        yandex_feed_rotation_record_history($pdo, $batchIndex, $merged);
        yandex_feed_rotation_log($logMsg);

        return [
            'ok' => true,
            'rotated' => true,
            'offers_written' => (int) ($gen['offers'] ?? $offerCount),
            'message' => $logMsg,
            'city_feeds' => $cityFeedBits,
            'errors' => $errors,
        ];
    } catch (Throwable $e) {
        yandex_feed_rotation_log('EXCEPTION ' . $e->getMessage());

        return ['ok' => false, 'message' => $e->getMessage(), 'errors' => [$e->getMessage()]];
    } finally {
        yandex_yml_rules_release_lock($fh);
    }
}
