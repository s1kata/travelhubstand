<?php
/**
 * Регионы (города/курорты) страны с фото из TourVisor.
 * Фото — снимки отелей в этом регионе (реальные фото из инвентаря туров).
 * GET countryId=4 → { regions: [{ id, name, images: [...] }], imageProxy }
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/components/tourvisor_proxy_url.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=1800');

$countryId = isset($_GET['countryId']) ? (int)$_GET['countryId'] : 0;
if ($countryId <= 0) {
    echo json_encode(['success' => false, 'error' => 'countryId required', 'regions' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$cacheDir = function_exists('th_tourvisor_cache_dir') ? th_tourvisor_cache_dir() : (dirname(__DIR__, 2) . '/data/tourvisor_cache');
$regionsByKey = []; // regionId => [name, images[]]

$files = glob($cacheDir . '/*.json') ?: [];
foreach ($files as $f) {
    $base = basename($f);
    if (strpos($base, 'countries_') === 0) continue;
    $raw = @file_get_contents($f);
    if (!$raw) continue;
    $j = @json_decode($raw, true);
    if (!is_array($j)) continue;

    $results = $j['results'] ?? $j['data'] ?? [];
    foreach ($results as $r) {
        $cid = isset($r['country']) ? (int)($r['country']['id'] ?? 0) : (int)($r['countryId'] ?? 0);
        if ($cid !== $countryId) continue;

        $rid = isset($r['region']) ? (int)($r['region']['id'] ?? 0) : (int)($r['regionId'] ?? 0);
        $rname = isset($r['region']) ? ($r['region']['name'] ?? '') : '';
        $pic = $r['picturelink'] ?? '';

        if ($rid <= 0 || $rname === '') continue;
        if ($pic === '' || !preg_match('#^https?://static\.tourvisor\.ru/#i', $pic)) continue;

        $key = (string)$rid;
        if (!isset($regionsByKey[$key])) {
            $regionsByKey[$key] = ['id' => $rid, 'name' => $rname, 'images' => []];
        }
        if (!in_array($pic, $regionsByKey[$key]['images']) && count($regionsByKey[$key]['images']) < 2) {
            $regionsByKey[$key]['images'][] = $pic;
        }
    }
}

$imageProxy = get_tourvisor_image_proxy_base_url();
$out = [];
foreach ($regionsByKey as $r) {
    $imgUrls = array_map(function ($src) use ($imageProxy) {
        if (preg_match('#^https?://static\.tourvisor\.ru/#i', $src)) {
            return $imageProxy . '?url=' . urlencode(str_replace('https:', 'http:', $src));
        }
        return $src;
    }, array_slice($r['images'], 0, 2));
    $out[] = ['id' => $r['id'], 'name' => $r['name'], 'images' => $imgUrls];
}

usort($out, function ($a, $b) { return strcmp($a['name'], $b['name']); });

echo json_encode([
    'success' => true,
    'regions' => $out,
    'imageProxy' => $imageProxy,
], JSON_UNESCAPED_UNICODE);
