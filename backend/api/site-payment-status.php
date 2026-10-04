<?php
/**
 * Статус платежа для сайта: PHP-сессия → mobile API /api/payment-status/:id.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/components/security_helper.php';
require_once dirname(__DIR__) . '/components/mobile_api_auth.php';

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = mobile_api_site_require_user();
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Войдите в аккаунт'], JSON_UNESCAPED_UNICODE);
    exit;
}

$transactionId = isset($_GET['transactionId']) ? trim((string) $_GET['transactionId']) : '';
if ($transactionId === '' || preg_match('/^\d{1,32}$/', $transactionId) !== 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid transactionId'], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = mobile_api_proxy_payment_status($userId, $transactionId);
http_response_code($result['httpCode'] >= 400 ? $result['httpCode'] : 200);
echo json_encode($result['data'], JSON_UNESCAPED_UNICODE);
