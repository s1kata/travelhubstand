<?php
/**
 * API создания платежа для мобильного приложения
 *
 * Принимает JSON с provider, bookingId, amount, description, returnUrl.
 * Возвращает { paymentUrl } для открытия в WebView.
 * Поддерживает provider: alpha (Альфа-Банк).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'payment' . DIRECTORY_SEPARATOR . 'alfabank_config.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'security_helper.php';

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use Voronkovich\SberbankAcquiring\ClientFactory;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Метод не разрешён']);
    exit;
}

if (security_rate_limit_exceeded('payment_create_alpha', 30, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Слишком много запросов, попробуйте позже']);
    exit;
}

$raw = file_get_contents('php://input');
$input = $raw ? json_decode($raw, true) : null;
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Неверный JSON']);
    exit;
}

$provider = strtolower(trim((string) ($input['provider'] ?? '')));
$bookingId = trim((string) ($input['bookingId'] ?? ''));
$amount = (float) ($input['amount'] ?? 0);
$currency = strtoupper(trim((string) ($input['currency'] ?? 'RUB')));
$description = trim((string) ($input['description'] ?? 'Оплата заказа'));
$returnUrl = trim((string) ($input['returnUrl'] ?? ''));

if ($provider !== 'alpha') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Поддерживается только provider: alpha']);
    exit;
}

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Укажите сумму']);
    exit;
}

$amountKopecks = (int) round($amount * 100);

if (empty($alfaUsername) || empty($alfaPassword)) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Оплата временно недоступна']);
    exit;
}

// Для мобильного: returnUrl = страница редиректа в travelhub:// (банки требуют https)
$bankReturnUrl = ($returnUrl !== '' && (str_starts_with($returnUrl, 'travelhub://') || str_starts_with($returnUrl, 'https://')))
    ? ALFABANK_APP_REDIRECT_URL
    : ALFABANK_CALLBACK_URL;

$orderNumber = 'TH' . time() . substr((string) mt_rand(1000, 9999), 0, 4);
if ($bookingId !== '') {
    $orderNumber = 'TH' . preg_replace('/[^a-zA-Z0-9_-]/', '', substr($bookingId, 0, 20)) . '_' . time();
}

// Сохраняем в БД
ensureAlfabankOrdersTable($pdo);
try {
    $stmt = $pdo->prepare(
        'INSERT INTO alfabank_orders (order_number, amount_kopecks, description, status, created_at) VALUES (?, ?, ?, ?, NOW())'
    );
    $stmt->execute([$orderNumber, $amountKopecks, $description, 'created']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка создания заказа']);
    exit;
}

$client = $alfaTestMode
    ? ClientFactory::alfabankTest(['userName' => $alfaUsername, 'password' => $alfaPassword])
    : ClientFactory::alfabank(['userName' => $alfaUsername, 'password' => $alfaPassword]);

try {
    $result = $client->registerOrder(
        $orderNumber,
        $amountKopecks,
        $bankReturnUrl,
        [
            'failUrl' => ALFABANK_APP_REDIRECT_URL . '?result=fail',
            'description' => mb_substr($description, 0, 250),
        ]
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Ошибка сервиса оплаты']);
    exit;
}

if (empty($result['formUrl'])) {
    $errMsg = $result['errorMessage'] ?? $result['errorCode'] ?? 'Ошибка банка';
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => $errMsg]);
    exit;
}

$alfaOrderId = $result['orderId'] ?? null;
if ($alfaOrderId && $pdo) {
    try {
        $upd = $pdo->prepare('UPDATE alfabank_orders SET alfabank_order_id = ?, status = ? WHERE order_number = ?');
        $upd->execute([$alfaOrderId, 'pending', $orderNumber]);
    } catch (Throwable $e) {}
}

echo json_encode([
    'success' => true,
    'paymentId' => $orderNumber,
    'paymentUrl' => $result['formUrl'],
]);

function ensureAlfabankOrdersTable(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='alfabank_orders'");
        $exists = $stmt && $stmt->fetch();
    } else {
        $stmt = $pdo->query("SHOW TABLES LIKE 'alfabank_orders'");
        $exists = $stmt && $stmt->rowCount() > 0;
    }
    if ($exists) return;

    if ($driver === 'sqlite') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS alfabank_orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_number VARCHAR(64) NOT NULL UNIQUE,
            amount_kopecks INTEGER NOT NULL,
            description TEXT,
            alfabank_order_id VARCHAR(64),
            status VARCHAR(32) DEFAULT 'created',
            user_id INTEGER,
            created_at DATETIME,
            updated_at DATETIME
        )");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS alfabank_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(64) NOT NULL UNIQUE,
            amount_kopecks INT NOT NULL,
            description TEXT,
            alfabank_order_id VARCHAR(64),
            status VARCHAR(32) DEFAULT 'created',
            user_id INT,
            created_at DATETIME,
            updated_at DATETIME
        )");
    }
}
