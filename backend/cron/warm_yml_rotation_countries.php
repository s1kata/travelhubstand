<?php
declare(strict_types=1);
/**
 * Прогрев promo + search для стран текущего batch ротации YML (Самара и/или Москва).
 *
 * Cron (понедельник 00:10, перед yml_feed_rules_cron):
 *   10 0 * * 1 cd /path/to/travelhub-v2 && PHP_BIN=/usr/bin/php8.1 bash backend/cron/warm_yml_rotation_countries.sh
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../components/yandex_feed_sync.php';
require_once __DIR__ . '/../components/yandex_yml_rotation.php';
require_once __DIR__ . '/../components/promo_speed_cache.php';
require_once __DIR__ . '/../components/tourvisor_proxy_http_base.php';

if (!$pdo) {
    fwrite(STDERR, "No database connection.\n");
    exit(1);
}

if (!yandex_feed_rotation_env_enabled() || !yandex_feed_rotation_is_active($pdo)) {
    echo date('c') . " rotation warm skip (disabled)\n";
    exit(0);
}

$countryIds = yandex_feed_rotation_country_ids_for_warm($pdo);
if ($countryIds === []) {
    echo date('c') . " rotation warm skip (no countries)\n";
    exit(0);
}

$settings = yandex_feed_rotation_get_settings($pdo);
$cities = yandex_feed_rotation_enabled_cities($settings);
$proxyBase = get_tourvisor_proxy_http_base_url();
$sep = strpos($proxyBase, '?') !== false ? '&' : '?';

echo date('c') . ' rotation warm start countries=' . implode(',', $countryIds) . "\n";

$ok = 0;
$fail = 0;

foreach ($cities as $city) {
    $depNorm = th_departure_normalize_id((int) $city['departure_id']);
    foreach ($countryIds as $cid) {
        $cid = (int) $cid;
        if ($cid <= 0) {
            continue;
        }

        $promoDates = th_promo_speed_promo_dates($cid);
        $promoUrl = $proxyBase . $sep . http_build_query([
            'type' => 'promo-search',
            'departureId' => (string) $depNorm,
            'countryId' => (string) $cid,
            'dateFrom' => $promoDates['dateFrom'],
            'dateTo' => $promoDates['dateTo'],
            'adults' => '2',
            'live' => '1',
        ]);
        $raw = yandex_feed_http_get($promoUrl, 90);
        if ($raw !== null) {
            $ok++;
        } else {
            $fail++;
        }

        $searchUrl = $proxyBase . $sep . http_build_query([
            'type' => 'search-cached',
            'departureId' => (string) $depNorm,
            'countryId' => (string) $cid,
            'dateFrom' => date('Y-m-d', strtotime('+2 days')),
            'dateTo' => date('Y-m-d', strtotime('+16 days')),
            'nightsFrom' => '6',
            'nightsTo' => '14',
            'adults' => '2',
            'currency' => 'RUB',
            'cacheScope' => 'country_page',
            'slim' => '1',
            'live' => '1',
        ]);
        $raw2 = yandex_feed_http_get($searchUrl, 90);
        if ($raw2 !== null) {
            $ok++;
        } else {
            $fail++;
        }

        usleep(400000);
    }
}

echo date('c') . " rotation warm done ok={$ok} fail={$fail}\n";
exit($fail > 0 && $ok === 0 ? 1 : 0);
