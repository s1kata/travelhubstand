<?php
/**
 * Страница оплаты для мобильного приложения TravelHub
 *
 * Принимает GET: bookingId, amount, currency, description, returnUrl.
 * Создаёт заказ в Альфа-Банке и редиректит на страницу банка.
 * URL: /payment (через .htaccess) или /frontend/window/payment.php
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'payment' . DIRECTORY_SEPARATOR . 'alfabank_config.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use Voronkovich\SberbankAcquiring\ClientFactory;

$bookingId = trim((string) ($_GET['bookingId'] ?? ''));
$amount = (float) ($_GET['amount'] ?? 0);
$currency = strtoupper(trim((string) ($_GET['currency'] ?? 'RUB')));
$description = trim((string) ($_GET['description'] ?? 'Оплата заказа'));
$returnUrl = trim((string) ($_GET['returnUrl'] ?? ''));

// Валидация
if ($amount <= 0) {
    header('Location: ' . ALFABANK_FAIL_URL . '?error=amount');
    exit;
}

if (empty($alfaUsername) || empty($alfaPassword)) {
    error_log('[Payment] Не указаны реквизиты Альфа-Банка в .env');
    header('Location: ' . (ALFABANK_FAIL_URL ?? '/') . '?error=config');
    exit;
}

$amountKopecks = (int) round($amount * 100);
$orderNumber = 'TH' . ($bookingId !== '' ? preg_replace('/[^a-zA-Z0-9_-]/', '', substr($bookingId, 0, 20)) . '_' : '') . time();

// returnUrl для банка — страница с кнопкой «Вернуться в приложение», bookingId в query
$bankReturnUrl = ALFABANK_APP_REDIRECT_URL . ($bookingId !== '' ? '?bookingId=' . rawurlencode($bookingId) : '');
$bankFailUrl = ALFABANK_APP_REDIRECT_URL . '?result=fail' . ($bookingId !== '' ? '&bookingId=' . rawurlencode($bookingId) : '');

ensureAlfabankOrdersTable($pdo);
try {
    $stmt = $pdo->prepare(
        'INSERT INTO alfabank_orders (order_number, amount_kopecks, description, status, created_at) VALUES (?, ?, ?, ?, NOW())'
    );
    $stmt->execute([$orderNumber, $amountKopecks, $description, 'created']);
} catch (Throwable $e) {
    error_log('[Payment] DB error: ' . $e->getMessage());
    header('Location: ' . (ALFABANK_FAIL_URL ?? '/') . '?error=db');
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
            'failUrl' => $bankFailUrl,
            'description' => mb_substr($description, 0, 250),
        ]
    );
} catch (Throwable $e) {
    error_log('[Payment] registerOrder: ' . $e->getMessage());
    header('Location: ' . (ALFABANK_FAIL_URL ?? '/') . '?error=bank');
    exit;
}

if (empty($result['formUrl'])) {
    $errMsg = $result['errorMessage'] ?? $result['errorCode'] ?? 'Ошибка банка';
    error_log('[Payment] No formUrl: ' . $errMsg);
    header('Location: ' . (ALFABANK_FAIL_URL ?? '/') . '?error=form');
    exit;
}

$alfaOrderId = $result['orderId'] ?? null;
if ($alfaOrderId && $pdo) {
    try {
        $upd = $pdo->prepare('UPDATE alfabank_orders SET alfabank_order_id = ?, status = ? WHERE order_number = ?');
        $upd->execute([$alfaOrderId, 'pending', $orderNumber]);
    } catch (Throwable $e) {
        error_log('[Payment] Update: ' . $e->getMessage());
    }
}

header('Location: ' . $result['formUrl']);
exit;

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
    if ($exists) {
        return;
    }

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
