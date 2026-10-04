<?php
/**
 * API геолокации по IP — определение города пользователя для авто-подстановки города вылета.
 * Используется бесплатный ip-api.com (не коммерческий, лимит 45 запросов/мин).
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Cache-Control: private, max-age=3600'); // кэш 1 час на стороне клиента

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (empty($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
    echo json_encode(['success' => false, 'error' => 'invalid_ip', 'city' => null]);
    exit;
}

// Локальные и частные IP — не опрашиваем внешний API, возвращаем город по умолчанию
$isPublic = filter_var(
    $ip,
    FILTER_VALIDATE_IP,
    FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
) !== false;
if (!$isPublic || $ip === '127.0.0.1' || $ip === '::1' || strpos($ip, 'fe80:') === 0) {
    echo json_encode(['success' => true, 'city' => 'Самара', 'countryCode' => 'RU', 'regionName' => null, 'country' => 'Россия']);
    exit;
}

$url = 'http://ip-api.com/json/' . urlencode($ip)
    . '?fields=status,message,city,regionName,countryCode,country'
    . '&lang=ru';

$ctx = stream_context_create([
    'http' => [
        'timeout' => 3,
        'ignore_errors' => true,
    ],
]);

$raw = @file_get_contents($url, false, $ctx);
if ($raw === false || $raw === '') {
    echo json_encode(['success' => false, 'error' => 'geo_unavailable', 'city' => null]);
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
    echo json_encode([
        'success' => false,
        'error' => $data['message'] ?? 'unknown',
        'city' => null,
    ]);
    exit;
}

require_once __DIR__ . '/../../config/departure_defaults.php';

$city = trim((string) ($data['city'] ?? ''));
if ($city !== '' && th_departure_is_blocked_name($city)) {
    $city = th_departure_default_name();
}

echo json_encode([
    'success' => true,
    'city' => $city !== '' ? $city : null,
    'regionName' => $data['regionName'] ?? null,
    'countryCode' => $data['countryCode'] ?? null,
    'country' => $data['country'] ?? null,
], JSON_UNESCAPED_UNICODE);
