<?php
/**
 * @deprecated Не используется. Оплата — через public_html/api/create-payment (mobile API).
 * POST /api/create-payment
 * Создание платежа Tinkoff T-Kassa для мобильного приложения.
 * Ожидает: JSON body (amount, orderId, description?, currency?, userId, returnUrl?, failReturnUrl?),
 * Authorization: Bearer <Firebase ID token>.
 * returnUrl/failReturnUrl: только https для Tinkoff; travelhub:// игнорируется (используются /payment-success|fail).
 *
 * Диагностика 401: PAYMENT_TOKEN_VERIFY_DEBUG=1 → website/data/payment-token-verify.log (JWT не пишется).
 * Проверка токена: RS256 + x509 Google (не tokeninfo URL — длинный ID token давал HTTP 400 invalid_token).
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'tinkoff_helper.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'security_helper.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'firebase_id_token_verify.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (security_rate_limit_exceeded('payment_create_tinkoff', 30, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many requests'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON body'], JSON_UNESCAPED_UNICODE);
    exit;
}

$amount = isset($input['amount']) ? (float) $input['amount'] : null;
$orderId = isset($input['orderId']) ? trim((string) $input['orderId']) : '';
$description = isset($input['description']) ? trim((string) $input['description']) : 'Оплата заказа';
$currency = isset($input['currency']) ? strtoupper(trim((string) $input['currency'])) : 'RUB';
$userId = isset($input['userId']) ? trim((string) $input['userId']) : '';

$errors = [];
if ($amount === null || $amount < 0.01) {
    $errors[] = 'amount is required and must be positive';
}
if ($orderId === '') {
    $errors[] = 'orderId is required';
}
if ($userId === '') {
    $errors[] = 'userId is required';
}
if ($currency !== 'RUB') {
    $errors[] = 'Only RUB is supported';
}
if ($errors !== []) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => implode('; ', $errors)], JSON_UNESCAPED_UNICODE);
    exit;
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($authHeader === '' && function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
}
if (!preg_match('/^\s*Bearer\s+(.+)\s*$/i', $authHeader, $m)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid Authorization Bearer token'], JSON_UNESCAPED_UNICODE);
    exit;
}
$bearerToken = trim($m[1]);
if ($bearerToken === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Empty Bearer token'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!verify_firebase_token_uid($bearerToken, $userId)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid or expired token'], JSON_UNESCAPED_UNICODE);
    exit;
}

$terminalKey = tinkoff_get_terminal_key();
$password = tinkoff_get_password();
if ($terminalKey === '' || $password === '') {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Payment is not configured'], JSON_UNESCAPED_UNICODE);
    exit;
}

$baseUrl = tinkoff_get_base_url();
$amountKopecks = (int) round($amount * 100);
if ($amountKopecks < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Amount too small'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Tinkoff SuccessURL/FailURL — только https. travelhub:// из приложения → дефолтные страницы сайта.
$defaultSuccessUrl = $baseUrl . '/payment-success?orderId=' . rawurlencode($orderId);
$defaultFailUrl = $baseUrl . '/payment-fail?orderId=' . rawurlencode($orderId);

$clientReturnUrl = isset($input['returnUrl']) ? trim((string) $input['returnUrl']) : '';
$clientFailReturnUrl = isset($input['failReturnUrl']) ? trim((string) $input['failReturnUrl']) : '';

$resolveTinkoffReturnUrl = static function (string $url, string $orderId, string $fallback): string {
    if ($url === '') {
        return $fallback;
    }
    if (preg_match('#^travelhub://#i', $url)) {
        return $fallback;
    }
    if (!preg_match('#^https://#i', $url)) {
        return $fallback;
    }
    return str_replace(
        ['{orderId}', '{bookingId}'],
        [rawurlencode($orderId), rawurlencode($orderId)],
        $url
    );
};

$successUrl = $resolveTinkoffReturnUrl($clientReturnUrl, $orderId, $defaultSuccessUrl);
$failUrl = $resolveTinkoffReturnUrl($clientFailReturnUrl, $orderId, $defaultFailUrl);
$notificationUrl = $baseUrl . '/api/payment-webhook';

$initParams = [
    'TerminalKey' => $terminalKey,
    'Amount'      => (string) $amountKopecks,
    'OrderId'     => $orderId,
    'Description'  => mb_substr($description, 0, 140),
    'SuccessURL'  => $successUrl,
    'FailURL'     => $failUrl,
    'NotificationURL' => $notificationUrl,
];
$token = tinkoff_sign_request($initParams, $password);
$initParams['Token'] = $token;

$ch = curl_init('https://securepay.tinkoff.ru/v2/Init');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($initParams),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
]);
$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'Payment service unavailable'], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($response, true);
if (!is_array($data)) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'Invalid response from payment service'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!empty($data['ErrorCode']) && (string) $data['ErrorCode'] !== '0') {
    $message = $data['Message'] ?? $data['Details'] ?? 'Unknown error';
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

$paymentUrl = $data['PaymentURL'] ?? '';
$paymentId = $data['PaymentId'] ?? null;
if ($paymentUrl === '' || $paymentId === null) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'Invalid response from payment service'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($pdo)) {
    try {
        ensure_tinkoff_payments_table($pdo);
        $stmt = $pdo->prepare(
            'INSERT INTO tinkoff_payments (order_id, payment_id, amount_kopecks, currency, user_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$orderId, (string) $paymentId, $amountKopecks, $currency, $userId, 'CREATED', date('Y-m-d H:i:s')]);
    } catch (Throwable $e) {}
}

echo json_encode([
    'success' => true,
    'paymentUrl' => $paymentUrl,
    'transactionId' => (string) $paymentId,
], JSON_UNESCAPED_UNICODE);

function ensure_tinkoff_payments_table(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='tinkoff_payments'")->fetch();
    } else {
        $exists = $pdo->query("SHOW TABLES LIKE 'tinkoff_payments'")->rowCount() > 0;
    }
    if ($exists) {
        return;
    }
    if ($driver === 'sqlite') {
        $pdo->exec("CREATE TABLE tinkoff_payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id VARCHAR(64) NOT NULL,
            payment_id VARCHAR(32) NOT NULL,
            amount_kopecks INT NOT NULL,
            currency VARCHAR(8) DEFAULT 'RUB',
            user_id VARCHAR(128) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'CREATED',
            paid_at DATETIME NULL,
            created_at DATETIME NOT NULL
        )");
        $pdo->exec('CREATE INDEX idx_tinkoff_payments_payment_id ON tinkoff_payments(payment_id)');
        $pdo->exec('CREATE INDEX idx_tinkoff_payments_order_id ON tinkoff_payments(order_id)');
    } else {
        $pdo->exec("CREATE TABLE tinkoff_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id VARCHAR(64) NOT NULL,
            payment_id VARCHAR(32) NOT NULL,
            amount_kopecks INT NOT NULL,
            currency VARCHAR(8) DEFAULT 'RUB',
            user_id VARCHAR(128) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'CREATED',
            paid_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_payment_id (payment_id),
            INDEX idx_order_id (order_id)
        )");
    }
}
