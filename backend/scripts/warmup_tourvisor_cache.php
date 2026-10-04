<?php
/**
 * Прогрев кэша Tourvisor при первом запуске сервера.
 * Вызовите один раз после старта (в браузере или по крону): GET /backend/scripts/warmup_tourvisor_cache.php
 * Запрашивает справочники (вылеты, страны, питание) — данные попадают в кэш на 24 часа,
 * форма поиска сразу показывает актуальные списки из кэша.
 * Документация API: https://api.tourvisor.ru/search/docs
 */
declare(strict_types=1);

$isCli = (php_sapi_name() === 'cli');
if ($isCli) {
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $_SERVER['SCRIPT_NAME'] = '/backend/scripts/warmup_tourvisor_cache.php';
    $_SERVER['HTTPS'] = '';
}

$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base = rtrim($proto . '://' . $host, '/');
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$pathPrefix = (strpos($scriptName, '/backend/') !== false)
    ? rtrim(substr($scriptName, 0, strpos($scriptName, '/backend/')), '/')
    : '';
$apiUrl = $base . $pathPrefix . '/backend/api/tourvisor-proxy.php';

$endpoints = [
    'departures' => $apiUrl . '?type=departures',
    'countries'   => $apiUrl . '?type=countries',
    'meals'       => $apiUrl . '?type=meals',
];

$results = [];
foreach ($endpoints as $name => $url) {
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 30,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    $ok = ($raw !== false);
    $decoded = $ok ? json_decode($raw, true) : null;
    $count = is_array($decoded['data'] ?? null) ? count($decoded['data']) : 0;
    $results[$name] = ['ok' => $ok && !empty($decoded['success']), 'count' => $count, 'error' => $decoded['error'] ?? null];
}

$allOk = array_reduce($results, fn($carry, $r) => $carry && $r['ok'], true);

if ($isCli) {
    foreach ($results as $name => $r) {
        echo $name . ': ' . ($r['ok'] ? 'OK ('.$r['count'].' items)' : 'FAIL ' . ($r['error'] ?? 'unknown')) . "\n";
    }
    exit($allOk ? 0 : 1);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => $allOk,
    'message' => $allOk ? 'Кэш Tourvisor прогрет. Справочники загружены.' : 'Часть запросов не удалась.',
    'results' => $results,
], JSON_UNESCAPED_UNICODE);
