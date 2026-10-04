<?php
/**
 * Создание платежа в Альфа-Банке
 *
 * Получает сумму и описание от пользователя, регистрирует заказ в банке,
 * получает formUrl и перенаправляет пользователя на платёжную страницу.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'payment' . DIRECTORY_SEPARATOR . 'alfabank_config.php';

// Подключаем библиотеку Альфа-Банка
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use Voronkovich\SberbankAcquiring\ClientFactory;
use Voronkovich\SberbankAcquiring\Currency;

session_start();

// Только POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

// Проверка реквизитов
if (empty($alfaUsername) || empty($alfaPassword)) {
    error_log('[Alfabank] Не указаны PAYMENT_ALFA_MERCHANT или PAYMENT_ALFA_SECRET в .env');
    $_SESSION['payment_error'] = 'Оплата временно недоступна. Обратитесь к администратору.';
    header('Location: ' . (ALFABANK_FAIL_URL ?? '/'));
    exit;
}

// Получаем данные из формы
$amountRaw = trim((string) ($_POST['amount'] ?? ''));
$description = trim((string) ($_POST['description'] ?? 'Оплата заказа'));

// Сумма в рублях (в копейках для API — 1 рубль = 100 копеек)
$amountRub = (float) preg_replace('/[^\d.,]/', '', str_replace(',', '.', $amountRaw));
if ($amountRub <= 0) {
    $_SESSION['payment_error'] = 'Укажите корректную сумму оплаты.';
    header('Location: ' . (ALFABANK_FAIL_URL ?? '/'));
    exit;
}

$amountKopecks = (int) round($amountRub * 100);

// Уникальный номер заказа (максимум 30 символов для API)
$orderNumber = 'TH' . time() . substr((string) mt_rand(1000, 9999), 0, 4);

// Сохраняем заказ в БД перед отправкой в банк
ensureAlfabankOrdersTable($pdo);
try {
    $stmt = $pdo->prepare(
        'INSERT INTO alfabank_orders (order_number, amount_kopecks, description, status, user_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())'
    );
    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    $stmt->execute([$orderNumber, $amountKopecks, $description, 'created', $userId]);
} catch (Throwable $e) {
    error_log('[Alfabank] DB insert error: ' . $e->getMessage());
    $_SESSION['payment_error'] = 'Ошибка создания заказа. Попробуйте позже.';
    header('Location: ' . (ALFABANK_FAIL_URL ?? '/'));
    exit;
}

// Создаём клиент Альфа-Банка (тестовый или боевой)
$client = $alfaTestMode
    ? ClientFactory::alfabankTest(['userName' => $alfaUsername, 'password' => $alfaPassword])
    : ClientFactory::alfabank(['userName' => $alfaUsername, 'password' => $alfaPassword]);

// Банк редиректит на callback, мы там проверяем статус и редиректим на success/fail
$returnUrl = ALFABANK_CALLBACK_URL;
$failUrl = ALFABANK_FAIL_URL;

try {
    $result = $client->registerOrder(
        $orderNumber,
        $amountKopecks,
        $returnUrl,
        [
            'failUrl'    => $failUrl,
            'orderBundle' => ['customerDetails' => ['email' => $_SESSION['user_email'] ?? ($_SESSION['email'] ?? '')]],
            'description' => mb_substr($description, 0, 250),
        ]
    );
} catch (Throwable $e) {
    error_log('[Alfabank] registerOrder error: ' . $e->getMessage());
    $_SESSION['payment_error'] = 'Ошибка регистрации платежа: ' . $e->getMessage();
    header('Location: ' . ($failUrl ?? '/'));
    exit;
}

// Проверяем ответ банка
if (empty($result['formUrl'])) {
    $errMsg = $result['errorMessage'] ?? $result['errorCode'] ?? 'Неизвестная ошибка банка';
    error_log('[Alfabank] No formUrl: ' . json_encode($result));
    $_SESSION['payment_error'] = 'Банк не вернул ссылку на оплату. ' . $errMsg;
    header('Location: ' . ($failUrl ?? '/'));
    exit;
}

// Сохраняем ID заказа банка в нашей БД
$alfaOrderId = $result['orderId'] ?? null;
if ($alfaOrderId && $pdo) {
    try {
        $upd = $pdo->prepare('UPDATE alfabank_orders SET alfabank_order_id = ?, status = ? WHERE order_number = ?');
        $upd->execute([$alfaOrderId, 'pending', $orderNumber]);
    } catch (Throwable $e) {
        error_log('[Alfabank] Update alfabank_order_id: ' . $e->getMessage());
    }
}

// Перенаправляем пользователя на страницу оплаты банка
header('Location: ' . $result['formUrl']);
exit;

/**
 * Создаёт таблицу alfabank_orders при отсутствии
 */
function ensureAlfabankOrdersTable(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $tableExists = false;

    if ($driver === 'sqlite') {
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='alfabank_orders'");
        $tableExists = $stmt && $stmt->fetch();
    } else {
        $stmt = $pdo->query("SHOW TABLES LIKE 'alfabank_orders'");
        $tableExists = $stmt && $stmt->rowCount() > 0;
    }

    if ($tableExists) {
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
