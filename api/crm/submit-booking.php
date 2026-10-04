<?php
/**
 * POST /api/crm/submit-booking (или /api/crm/submit-booking.php)
 * Создание обращения в U-ON. Авторизация: JWT из auth-mobile.php (не Firebase).
 *
 * Тело: { "idempotencyKey": "...", "payload": { ... CrmBookingQueuePayload } }
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$configPath = dirname(__DIR__) . '/auth-mobile.config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Конфиг auth-mobile.config.php не найден']);
    exit;
}

/** @var array<string, mixed> $CONFIG */
$CONFIG = require $configPath;

require_once dirname(__DIR__) . '/lib/auth-jwt.php';
require_once dirname(__DIR__) . '/lib/crm-booking-body.php';
require_once dirname(__DIR__) . '/lib/uon-client.php';

$claims = auth_jwt_require_bearer($CONFIG);
$userId = (string) ($claims['sub'] ?? '');
if ($userId === '') {
    auth_jwt_json_error('Invalid token subject', 401);
}

$raw = file_get_contents('php://input') ?: '';
$body = json_decode($raw, true);
if (!is_array($body)) {
    auth_jwt_json_error('Некорректный JSON', 400);
}

$idempotencyKey = isset($body['idempotencyKey']) ? trim((string) $body['idempotencyKey']) : '';
$payload = $body['payload'] ?? null;
if ($idempotencyKey === '' || !is_array($payload)) {
    auth_jwt_json_error('Required: idempotencyKey, payload', 400);
}

// userId из JWT — источник истины (клиентский payload.userId мог разъехаться)
$payloadUserId = trim((string) ($payload['userId'] ?? ''));
if ($payloadUserId !== '' && $payloadUserId !== $userId) {
    error_log('[crm/submit-booking] userId mismatch payload=' . $payloadUserId . ' jwt=' . $userId . ' — using JWT');
}
$payload['userId'] = $userId;

try {
    $requestBody = crm_build_lead_create_body(array_merge($payload, ['idempotencyKey' => $idempotencyKey]));
} catch (InvalidArgumentException $e) {
    auth_jwt_json_error($e->getMessage(), 400);
}

$response = uon_request('lead/create.json', $CONFIG, [
    'method' => 'POST',
    'body' => json_encode($requestBody, JSON_UNESCAPED_UNICODE),
]);

if (!$response['success'] || !is_array($response['data'] ?? null)) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'error' => $response['error'] ?? 'CRM request failed',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = $response['data'];
$id = $data['id'] ?? $data['id_system'] ?? $data['r_id'] ?? null;
if ($id === null && isset($data['message']) && is_array($data['message'])) {
    $id = $data['message']['id'] ?? $data['message']['id_system'] ?? null;
}
// U-ON иногда отдаёт result=0 при ошибке на HTTP 200
if (isset($data['result']) && (int) $data['result'] === 0 && $id === null) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'error' => (string) ($data['message'] ?? $data['error'] ?? 'U-ON lead/create rejected'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'data' => [
        'id' => $id !== null ? (string) $id : null,
        'requestId' => $id !== null ? (string) $id : null,
        'bookingNumber' => isset($data['id_internal'])
            ? (string) $data['id_internal']
            : ($id !== null ? (string) $id : null),
    ],
], JSON_UNESCAPED_UNICODE);
