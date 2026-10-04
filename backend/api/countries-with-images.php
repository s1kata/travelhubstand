<?php
/**
 * Страны с фото из TourVisor (из кэша поиска/отелей).
 * Результат собирается один раз, сохраняется в файл, далее отдаётся из кэша.
 * GET: возвращает { countries: [{ id, name, slug, images: [...] }], imageProxy: "..." }
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/components/tourvisor_proxy_url.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$dataDir = (function_exists('th_project_root') ? th_project_root() : dirname(__DIR__, 2)) . '/data';
$cacheFile = $dataDir . '/country_images_cache.json';
$forceRebuild = isset($_GET['rebuild']);

if (!$forceRebuild && is_file($cacheFile)) {
    header('X-Country-Images: from-cache');
    readfile($cacheFile);
    exit;
}

function countryImagesLog(string $msg, array $ctx = []): void {
    $line = date('Y-m-d H:i:s') . ' [countries-with-images] ' . $msg;
    if (!empty($ctx)) $line .= ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE);
    error_log($line);
    $dir = (function_exists('th_project_root') ? th_project_root() : dirname(__DIR__, 2)) . '/data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($dir . '/country_images.log', $line . "\n", FILE_APPEND | LOCK_EX);
}

$cacheDir = function_exists('th_tourvisor_cache_dir') ? th_tourvisor_cache_dir() : (dirname(__DIR__, 2) . '/data/tourvisor_cache');

// Маппинг имя страны (нормализованное) -> slug
$nameToSlug = [
    'турция' => 'turkey', 'египет' => 'egypt', 'таиланд' => 'thailand', 'оаэ' => 'uae', 'россия' => 'russia',
    'китай' => 'china', 'вьетнам' => 'vietnam', 'индия' => 'india', 'индонезия' => 'indonesia',
    'шри-ланка' => 'sri-lanka', 'куба' => 'cuba', 'тунис' => 'tunisia', 'черногория' => 'montenegro',
    'мальдивы' => 'maldives', 'оман' => 'oman', 'филиппины' => 'philippines', 'катар' => 'qatar',
    'сейшелы' => 'seychelles', 'маврикий' => 'mauritius', 'танзания' => 'tanzania', 'иордания' => 'jordan',
    'армения' => 'armenia', 'бахрейн' => 'bahrain', 'абхазия' => 'abkhazia', 'венесуэла' => 'venezuela',
];

function norm(string $s): string {
    return mb_strtolower(trim(preg_replace('/\s+/', ' ', $s)), 'UTF-8');
}

// 1. Загрузить страны — из кэша TourVisor или из API (прокси)
$countries = [];
countryImagesLog('cache_dir', ['path' => $cacheDir, 'exists' => is_dir($cacheDir)]);
$cachePatterns = ['countries_onlyCharter_0_api.json', 'countries_onlyCharter_0.json', 'countries_departureId_1_onlyCharter_0_api.json', 'countries_departureId_1_onlyCharter_0.json'];
foreach ($cachePatterns as $f) {
    $countriesFile = $cacheDir . '/' . $f;
    if (is_file($countriesFile)) {
        $data = json_decode(file_get_contents($countriesFile), true);
        $countries = $data['data'] ?? [];
        if (!empty($countries)) {
            countryImagesLog('countries_loaded', ['file' => $f, 'count' => count($countries), 'ids_sample' => array_slice(array_column($countries, 'id'), 0, 5)]);
            break;
        }
    }
}
// Если кэш пуст — запросить из API (прокси)
if (empty($countries)) {
    $projectRoot = function_exists('th_project_root') ? th_project_root() : dirname(__DIR__, 2);
    $envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
    if (file_exists($envPath)) {
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (!str_contains($line, '=')) continue;
            [$k, $v] = array_map('trim', explode('=', $line, 2));
            if ($k !== '') { putenv("$k=$v"); $_ENV[$k] = $v; }
        }
    }
    $siteUrl = function_exists('th_site_base_url') ? th_site_base_url() : rtrim(getenv('SITE_URL') ?: ($_ENV['SITE_URL'] ?? ''), '/');
    if ($siteUrl === '') {
        $siteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    $proxyPath = function_exists('get_tourvisor_proxy_path') ? get_tourvisor_proxy_path() : '/backend/api/tourvisor-proxy.php';
    $proxyUrl = rtrim($siteUrl, '/') . $proxyPath;
    $ctx = stream_context_create(['http' => ['timeout' => 25, 'ignore_errors' => true]]);
    foreach (['type=countries', 'type=countries&departureId=1'] as $query) {
        $url = $proxyUrl . (strpos($proxyUrl, '?') !== false ? '&' : '?') . $query;
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw !== false) {
            $j = json_decode($raw, true);
            if (!empty($j['success']) && is_array($j['data'])) {
                $seen = array_flip(array_column($countries, 'id'));
                foreach ($j['data'] as $c) {
                    $id = (int)($c['id'] ?? 0);
                    if ($id > 0 && !isset($seen[$id])) {
                        $countries[] = $c;
                        $seen[$id] = true;
                    }
                }
                countryImagesLog('countries_from_api', ['count' => count($countries), 'query' => $query]);
                if (!empty($countries)) break;
            }
        }
    }
}
if (empty($countries)) {
    $countries = [
        ['id' => 1, 'name' => 'Египет'], ['id' => 2, 'name' => 'Таиланд'], ['id' => 3, 'name' => 'Индия'],
        ['id' => 4, 'name' => 'Турция'], ['id' => 5, 'name' => 'Тунис'], ['id' => 9, 'name' => 'ОАЭ'],
        ['id' => 8, 'name' => 'Мальдивы'], ['id' => 16, 'name' => 'Вьетнам'], ['id' => 12, 'name' => 'Шри-Ланка'],
        ['id' => 13, 'name' => 'Китай'], ['id' => 10, 'name' => 'Куба'], ['id' => 21, 'name' => 'Черногория'],
        ['id' => 28, 'name' => 'Сейшелы'], ['id' => 27, 'name' => 'Маврикий'], ['id' => 41, 'name' => 'Танзания'],
        ['id' => 29, 'name' => 'Иордания'], ['id' => 53, 'name' => 'Армения'], ['id' => 59, 'name' => 'Бахрейн'],
        ['id' => 46, 'name' => 'Абхазия'], ['id' => 90, 'name' => 'Венесуэла'], ['id' => 47, 'name' => 'Россия'],
    ];
}

// 2. Собрать countryId/countryName -> images из кэша поиска
$countryImages = [];
$files = glob($cacheDir . '/*.json') ?: [];
countryImagesLog('scan_cache', ['files_count' => count($files), 'files' => array_map('basename', $files)]);
$scanned = 0;
$totalResults = 0;
foreach ($files as $f) {
    $base = basename($f);
    if (strpos($base, 'countries_') === 0) continue;
    $raw = @file_get_contents($f);
    if (!$raw) {
        countryImagesLog('file_read_fail', ['file' => $base]);
        continue;
    }
    $j = @json_decode($raw, true);
    if (!is_array($j)) {
        countryImagesLog('json_decode_fail', ['file' => $base]);
        continue;
    }
    $results = $j['results'] ?? $j['data'] ?? [];
    $countBefore = count($countryImages);
    foreach ($results as $r) {
        $cid = isset($r['country']) ? (int)($r['country']['id'] ?? 0) : (int)($r['countryId'] ?? 0);
        $cname = isset($r['country']) ? ($r['country']['name'] ?? '') : '';
        $pic = $r['picturelink'] ?? '';
        if ($pic === '' || !preg_match('#^https?://static\.tourvisor\.ru/#i', $pic)) continue;
        $keyId = $cid > 0 ? (string)$cid : '';
        $keyName = $cname !== '' ? norm($cname) : '';
        foreach (array_filter([$keyId, $keyName]) as $key) {
            if (!isset($countryImages[$key])) $countryImages[$key] = [];
            if (!in_array($pic, $countryImages[$key]) && count($countryImages[$key]) < 3) {
                $countryImages[$key][] = $pic;
            }
        }
        $totalResults++;
    }
    $scanned++;
    if ($scanned <= 3 && !empty($results)) {
        $sample = array_slice($results, 0, 2);
        $sampleIds = [];
        foreach ($sample as $x) {
            $sampleIds[] = isset($x['country']) ? ($x['country']['id'] ?? '?') . ':' . ($x['country']['name'] ?? '') : ($x['countryId'] ?? '?');
        }
        countryImagesLog('file_parsed', ['file' => $base, 'results' => count($results), 'sample' => $sampleIds]);
    }
}
countryImagesLog('images_collected', ['countryImages_keys' => array_keys($countryImages), 'total_keys' => count($countryImages), 'sample' => array_map(function($v) { return count($v); }, array_slice($countryImages, 0, 10, true))]);

$imageProxy = get_tourvisor_image_proxy_base_url();
$out = [];
$withImages = 0;
foreach ($countries as $c) {
    $id = (int)($c['id'] ?? 0);
    $name = (string)($c['name'] ?? $c['russianName'] ?? '');
    $slug = $nameToSlug[norm($name)] ?? '';
    $imgs = $countryImages[(string)$id] ?? $countryImages[norm($name)] ?? [];
    if (!empty($imgs)) $withImages++;
    $imgUrls = array_map(function ($src) use ($imageProxy) {
        if (preg_match('#^https?://static\.tourvisor\.ru/#i', $src)) {
            return $imageProxy . '?url=' . urlencode(str_replace('https:', 'http:', $src));
        }
        return $src;
    }, array_slice($imgs, 0, 3));
    $out[] = ['id' => $id, 'name' => $name, 'slug' => $slug, 'images' => $imgUrls];
}
countryImagesLog('output', ['total_countries' => count($out), 'with_images' => $withImages, 'id_name_mismatch_hint' => 'Search cache may use different country ids than countries API - check countryImages keys vs country ids']);

$json = json_encode(['countries' => $out, 'imageProxy' => $imageProxy], JSON_UNESCAPED_UNICODE);
if (!is_dir($dataDir)) @mkdir($dataDir, 0755, true);
@file_put_contents($cacheFile, $json, LOCK_EX);
echo $json;
