<?php
declare(strict_types=1);

/**
 * Mobile passthrough proxy for Tourvisor Search API v1.
 *
 * Важно: приложение не должно слать Authorization — upstream получает только Bearer из TOURVISOR_* на сервере.
 *
 * Usage examples:
 *   /api/tourvisor-mobile/countries?departureId=1
 *   /api/tourvisor-mobile/departures
 *   /api/tourvisor-mobile/tours/search?...
 *
 * This endpoint keeps Tourvisor token on server side and forwards
 * requests to https://api.tourvisor.ru/search/api/v1/*.
 */

require_once __DIR__ . '/../config/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$token = trim((string)(getenv('TOURVISOR_TOKEN') ?: ($_ENV['TOURVISOR_TOKEN'] ?? '')));
if ($token === '') {
    $token = trim((string)(getenv('TOURVISOR_JWT_TOKEN') ?: ($_ENV['TOURVISOR_JWT_TOKEN'] ?? '')));
}
if ($token === '') {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Tourvisor token is not configured'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pathRaw = trim((string)($_GET['path'] ?? ''));
if ($pathRaw === '' && !empty($_SERVER['PATH_INFO'])) {
    $pathRaw = trim((string)$_SERVER['PATH_INFO'], '/');
}
if ($pathRaw === '') {
    $pathRaw = 'countries';
}

$path = '/' . ltrim($pathRaw, '/');

// Allow only API-like safe path characters.
if (!preg_match('#^/[a-zA-Z0-9/_-]+$#', $path)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Invalid path'], JSON_UNESCAPED_UNICODE);
    exit;
}

$baseUrl = rtrim((string)(getenv('TOURVISOR_API_URL') ?: ($_ENV['TOURVISOR_API_URL'] ?? '')), '/');
if ($baseUrl === '') {
    $baseUrl = 'https://api.tourvisor.ru/search/api/v1';
}

$query = $_GET;
unset($query['path']);
$queryString = http_build_query($query);
$upstreamUrl = $baseUrl . $path . ($queryString !== '' ? ('?' . $queryString) : '');

$ch = curl_init($upstreamUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_CONNECTTIMEOUT => 25,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Accept: application/json',
    ],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
]);

$body = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = (string)(curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'application/json; charset=utf-8');
$errno = curl_errno($ch);
$error = curl_error($ch);
curl_close($ch);

if ($errno !== 0) {
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Upstream request failed: ' . $error], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($status <= 0) {
    $status = 502;
}
http_response_code($status);
header('Content-Type: ' . $contentType);
echo is_string($body) ? $body : '';

