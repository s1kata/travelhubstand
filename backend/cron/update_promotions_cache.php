<?php
/**
 * Прогрев кэша горящих туров: promo-search live → data/promo_cache_{countryId}_{departureId}.json
 * + tour-flights для топ-N туров (flightsByTourId в том же файле).
 * Питает витрину главной (home_showcase_shelves) — страны из popular_countries.php.
 *
 * Cron (2 раза в сутки, минимум 1× ночью):
 *   0 0,12 * * * cd /path/to/website-main && flock -n data/promo_warm.lock bash backend/cron/warm_promotions_cache.sh >> data/promo_warm.log 2>&1
 *
 * Ручной прогрев по SSH (фон, не рвётся при disconnect):
 *   cd ~/travel63test_ru/public_html && nohup env PHP_BIN=/usr/bin/php7.4 bash backend/cron/warm_promotions_cache.sh >> data/promo_warm.log 2>&1 &
 *   tail -f data/promo_warm.log
 *
 * Дозапуск после обрыва (пропускает уже свежие promo_cache_*):
 *   PROMO_WARM_FORCE=0 nohup env PHP_BIN=/usr/bin/php7.4 bash backend/cron/warm_promotions_cache.sh >> data/promo_warm.log 2>&1 &
 *
 * Быстрый прогрев без рейсов:
 *   PROMO_WARM_SKIP_FLIGHTS=1 PHP_BIN=/usr/bin/php7.4 bash backend/cron/warm_promotions_cache.sh
 *
 * Требует SITE_URL / TOURVISOR_PROXY_URL (см. tourvisor_proxy_http_base.php).
 */
declare(strict_types=1);

@set_time_limit(0);
if (function_exists('ignore_user_abort')) {
    ignore_user_abort(true);
}

require_once __DIR__ . '/../components/tourvisor_proxy_http_base.php';
require_once __DIR__ . '/../components/promo_speed_cache.php';
require_once __DIR__ . '/../components/promo_sochi_filter.php';

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
}

$projectRoot = dirname(dirname(__DIR__));
$envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
if (!is_file($envPath)) {
    $envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
}
if (is_file($envPath)) {
    $envLines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($envLines)) {
        $envLines = array();
    }
    foreach ($envLines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }
        $parts = explode('=', $line, 2);
        $k = isset($parts[0]) ? trim($parts[0]) : '';
        $v = isset($parts[1]) ? trim($parts[1]) : '';
        if ($k !== '') {
            putenv($k . '=' . $v);
            $_ENV[$k] = $v;
        }
    }
}

$popularFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'popular_countries.php';
$popular = is_file($popularFile) ? require $popularFile : [];
if (!is_array($popular) || $popular === []) {
    $popular = [['id' => 4, 'name' => 'Турция']];
}

$proxyBase = get_tourvisor_proxy_http_base_url();
$departures = th_promo_speed_warm_departures();

$index = th_promo_speed_index_get(true);
$ok = 0;
$err = 0;
$skipped = 0;
$seenCountry = [];

th_promo_speed_log('cron_start', [
    'countries' => count($popular),
    'departures' => array_column($departures, 'departureId'),
    'mode' => 'promo-search-hybrid',
    'skip_flights' => th_promo_speed_warm_skip_flights(),
    'flights_max' => th_promo_speed_warm_flights_max(),
]);

foreach ($departures as $depRow) {
    $departureId = (int) (isset($depRow['departureId']) ? $depRow['departureId'] : 0);
    if ($departureId <= 0) {
        continue;
    }
    $depKey = (string) $departureId;
    if (!isset($index[$depKey])) {
        $index[$depKey] = [];
    }

    foreach ($popular as $row) {
        $countryId = (int) (isset($row['id']) ? $row['id'] : 0);
        if ($countryId <= 0) {
            continue;
        }
        $comboKey = $departureId . '_' . $countryId;
        if (isset($seenCountry[$comboKey])) {
            continue;
        }
        $seenCountry[$comboKey] = true;

        $countryName = isset($row['name']) ? (string) $row['name'] : (string) $countryId;
        if (th_promo_speed_warm_combo_is_fresh($countryId, $departureId)) {
            $skipped++;
            if ($isCli) {
                echo $countryName . " (dep {$departureId}): skip (fresh cache)\n";
                @flush();
            }
            th_promo_speed_log('cron_promo_search_skip_fresh', [
                'countryId' => $countryId,
                'departureId' => $departureId,
            ]);
            continue;
        }

        $dates = th_promo_speed_promo_dates($countryId);

        $cronDispatch = static function (array $winParams) use ($proxyBase): array {
            $params = http_build_query($winParams);
            $url = $proxyBase . (strpos($proxyBase, '?') !== false ? '&' : '?') . $params;
            $ctx = stream_context_create([
                'http' => ['timeout' => 300, 'ignore_errors' => true],
                'ssl' => ['verify_peer' => true],
            ]);
            $raw = @file_get_contents($url, false, $ctx);
            if ($raw === false) {
                return ['success' => false, 'data' => []];
            }
            $data = json_decode($raw, true);
            return is_array($data) ? $data : ['success' => false, 'data' => []];
        };

        $res = $cronDispatch([
            'type' => 'promo-search',
            'departureId' => (string) $departureId,
            'countryId' => (string) $countryId,
            'dateFrom' => $dates['dateFrom'],
            'dateTo' => $dates['dateTo'],
            'adults' => '2',
            'live' => '1',
        ]);
        $hotels = (!empty($res['success']) && is_array(isset($res['data']) ? $res['data'] : null)) ? $res['data'] : [];

        if (
            count($hotels) > 0
            && in_array($countryId, th_promo_speed_tr_eg_star_boost_country_ids(), true)
        ) {
            $hotels = th_promo_speed_finalize_merged(
                $hotels,
                $countryId,
                $departureId,
                $dates,
                2,
                null,
                $cronDispatch
            );
            th_promo_speed_log('cron_promo_tr_eg_finalize', [
                'countryId' => $countryId,
                'departureId' => $departureId,
                'hotels' => count($hotels),
            ]);
        }

        if (count($hotels) === 0 && $countryId === th_promo_phuquoc_virtual_country_id()) {
            $arrays = th_promo_speed_fetch_regular_window_arrays(
                $countryId,
                $departureId,
                $dates,
                2,
                null,
                $cronDispatch,
                true,
                true
            );
            if ($arrays !== []) {
                $merged = th_promo_speed_merge_hotels($arrays, $countryId);
                $hotels = th_promo_speed_prepare_live_search_hotels($merged, $countryId, $departureId, $dates);
                th_promo_speed_log('cron_phuquoc_wide_fallback', [
                    'countryId' => $countryId,
                    'departureId' => $departureId,
                    'hotels' => count($hotels),
                ]);
            }
        }

        if (count($hotels) === 0) {
            th_promo_speed_log('cron_promo_search_empty', [
                'countryId' => $countryId,
                'departureId' => $departureId,
                'error' => isset($res['error']) ? $res['error'] : 'empty',
                'source' => isset($res['promoSearchSource']) ? $res['promoSearchSource'] : null,
            ]);
        } else {
            th_promo_speed_log('cron_promo_search_ok', [
                'countryId' => $countryId,
                'departureId' => $departureId,
                'hotels' => count($hotels),
                'source' => isset($res['promoSearchSource']) ? $res['promoSearchSource'] : null,
            ]);
            $tourIds = th_promo_speed_collect_tour_ids_from_hotels($hotels, th_promo_speed_warm_flights_max());
            $flightsByTourId = [];
            if ($tourIds !== []) {
                $flightsByTourId = th_promo_speed_warm_flights_for_tour_ids($tourIds, $cronDispatch);
                th_promo_speed_log('cron_flights_warm', [
                    'countryId' => $countryId,
                    'departureId' => $departureId,
                    'tours' => count($tourIds),
                    'warmed' => count($flightsByTourId),
                ]);
            }
            th_promo_speed_cache_set($countryId, $departureId, $hotels, array_merge($dates, [
                'flightsByTourId' => $flightsByTourId,
            ]));
        }

        $hotelsForTile = th_promo_filter_hotels_min_nights($hotels, $countryId);
        $min = 0;
        foreach ($hotelsForTile as $h) {
            if (!is_array($h)) {
                continue;
            }
            $p = th_promo_speed_hotel_min_price($h);
            if ($p > 0 && ($min === 0 || $p < $min)) {
                $min = $p;
            }
        }
        $entry = ['has' => count($hotelsForTile) > 0];
        if ($min > 0) {
            $entry['minPrice'] = $min;
        }
        $index[$depKey][(string) $countryId] = $entry;

        if (count($hotels) > 0) {
            $ok++;
            if ($isCli) {
                echo $countryName . " (dep {$departureId}): " . count($hotels) . " отелей\n";
                @flush();
            }
        } else {
            $err++;
            if ($isCli) {
                echo $countryName . " (dep {$departureId}): пусто\n";
                @flush();
            }
        }
        th_promo_speed_index_set($index);
        usleep(500000);
    }
}

th_promo_speed_index_set($index);
th_promo_speed_log('cron_done', ['ok' => $ok, 'err' => $err, 'skipped' => $skipped]);

if (!$isCli) {
    echo json_encode(['success' => true, 'ok' => $ok, 'err' => $err, 'skipped' => $skipped], JSON_UNESCAPED_UNICODE);
}
