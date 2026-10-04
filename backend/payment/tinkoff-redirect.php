<?php
/**
 * @deprecated Не используется. Сайт вызывает POST /api/create-payment (mobile API).
 * Оплата с сайта через Tinkoff T-Kassa.
 * GET: orderId, amount, description — создаём платёж и редиректим на страницу банка.
 * URL: /payment-tinkoff?orderId=123&amount=1500&description=Бронирование
 */
declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'tinkoff_helper.php';

$orderId = trim((string) ($_GET['orderId'] ?? ''));
$amount = isset($_GET['amount']) ? (float) $_GET['amount'] : 0;
$description = trim((string) ($_GET['description'] ?? 'Оплата заказа'));

if ($orderId === '' || $amount < 0.01) {
    header('Location: /payment-fail?error=params');
    exit;
}

$terminalKey = tinkoff_get_terminal_key();
$password = tinkoff_get_password();
if ($terminalKey === '' || $password === '') {
    error_log('[Tinkoff Site] TINKOFF_TERMINAL_KEY or TINKOFF_PASSWORD not set');
    header('Location: /payment-fail?error=config');
    exit;
}

session_start();
$userId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : 'site-guest';

$baseUrl = tinkoff_get_base_url();
$amountKopecks = (int) round($amount * 100);
$successUrl = $baseUrl . '/payment-success';
$failUrl = $baseUrl . '/payment-fail';
$notificationUrl = $baseUrl . '/api/payment-webhook';

$initParams = [
    'TerminalKey' => $terminalKey,
    'Amount' => (string) $amountKopecks,
    'OrderId' => $orderId,
    'Description' => mb_substr($description, 0, 140),
    'SuccessURL' => $successUrl,
    'FailURL' => $failUrl,
    'NotificationURL' => $notificationUrl,
];
$initParams['Token'] = tinkoff_sign_request($initParams, $password);

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
curl_close($ch);

if ($response === false) {
    error_log('[Tinkoff Site] cURL error');
    header('Location: /payment-fail?error=network');
    exit;
}

$data = json_decode($response, true);
if (!is_array($data)) {
    header('Location: /payment-fail?error=response');
    exit;
}

if (!empty($data['ErrorCode']) && (string) $data['ErrorCode'] !== '0') {
    error_log('[Tinkoff Site] Init error: ' . ($data['Message'] ?? ''));
    header('Location: /payment-fail?error=bank');
    exit;
}

$paymentUrl = $data['PaymentURL'] ?? '';
$paymentId = $data['PaymentId'] ?? null;
if ($paymentUrl === '') {
    header('Location: /payment-fail?error=form');
    exit;
}

if (isset($pdo)) {
    try {
        ensure_tinkoff_payments_table($pdo);
        $stmt = $pdo->prepare(
            'INSERT INTO tinkoff_payments (order_id, payment_id, amount_kopecks, currency, user_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$orderId, (string) $paymentId, $amountKopecks, 'RUB', $userId, 'CREATED', date('Y-m-d H:i:s')]);
    } catch (Throwable $e) {
        error_log('[Tinkoff Site] DB: ' . $e->getMessage());
    }
}

header('Location: ' . $paymentUrl);
exit;

function ensure_tinkoff_payments_table(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='tinkoff_payments'")->fetch();
    } else {
        $exists = $pdo->query("SHOW TABLES LIKE 'tinkoff_payments'")->rowCount() > 0;
    }
    if ($exists) return;
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
