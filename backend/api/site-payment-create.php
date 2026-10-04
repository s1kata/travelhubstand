<?php
/**
 * Создание платежа для сайта: PHP-сессия → mobile API /api/create-payment.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/components/security_helper.php';
require_once dirname(__DIR__) . '/components/mobile_api_auth.php';

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (security_rate_limit_exceeded('site_payment_create', 20, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Слишком много запросов'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = mobile_api_site_require_user();
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Войдите в аккаунт'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$input = json_decode($raw, true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!security_csrf_verify_token(isset($input['_csrf_token']) ? (string) $input['_csrf_token'] : null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRF'], JSON_UNESCAPED_UNICODE);
    exit;
}

$amount = isset($input['amount']) ? (float) $input['amount'] : 0.0;
$orderId = isset($input['orderId']) ? trim((string) $input['orderId']) : '';
$description = isset($input['description']) ? trim((string) $input['description']) : 'Оплата заказа';
$returnUrl = isset($input['returnUrl']) ? trim((string) $input['returnUrl']) : '';
$failReturnUrl = isset($input['failReturnUrl']) ? trim((string) $input['failReturnUrl']) : '';

if ($amount < 0.01 || $orderId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'amount and orderId are required'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (preg_match('/^[A-Za-z0-9_-]+$/', $orderId) !== 1 || mb_strlen($orderId) > 190) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid orderId'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = [
    'amount' => $amount,
    'orderId' => $orderId,
    'description' => mb_substr($description, 0, 140),
];
if ($returnUrl !== '') {
    $payload['returnUrl'] = $returnUrl;
}
if ($failReturnUrl !== '') {
    $payload['failReturnUrl'] = $failReturnUrl;
}

$result = mobile_api_proxy_create_payment($userId, $payload);
http_response_code($result['httpCode'] >= 400 ? $result['httpCode'] : 200);
echo json_encode($result['data'], JSON_UNESCAPED_UNICODE);
