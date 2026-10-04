<?php
/**
 * Обработка результата оплаты от Альфа-Банка
 *
 * Банк отправляет пользователя сюда после оплаты (returnUrl) с параметрами в URL.
 * Также банк может отправлять асинхронные уведомления (deposit callback) — их обрабатываем отдельно.
 *
 * Здесь обрабатываем только редирект пользователя после оплаты.
 * Проверяем статус через API и обновляем БД, затем перенаправляем на страницу успеха/ошибки.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'payment' . DIRECTORY_SEPARATOR . 'alfabank_config.php';

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use Voronkovich\SberbankAcquiring\ClientFactory;
use Voronkovich\SberbankAcquiring\OrderStatus;

// Реквизиты должны быть настроены
if (empty($alfaUsername) || empty($alfaPassword)) {
    error_log('[Alfabank callback] Missing credentials');
    header('Location: ' . (ALFABANK_FAIL_URL ?? '/'));
    exit;
}

// Банк передаёт orderId в URL при редиректе (это ID заказа в системе банка)
// Документация: https://alfa.rbsuat.com/sandbox/ru/includes/scripts/redirect_after_payment.html
$orderId = trim((string) ($_GET['orderId'] ?? $_POST['orderId'] ?? ''));
$mdOrder = trim((string) ($_GET['mdOrder'] ?? $_POST['mdOrder'] ?? ''));

// Если передали orderId — это ID заказа банка, по нему можно получить статус
// mdOrder — это наш order_number

$client = $alfaTestMode
    ? ClientFactory::alfabankTest(['userName' => $alfaUsername, 'password' => $alfaPassword])
    : ClientFactory::alfabank(['userName' => $alfaUsername, 'password' => $alfaPassword]);

$orderNumber = $mdOrder;

// Если есть только orderId банка — ищем наш order_number в БД
if ($orderNumber === '' && $orderId !== '' && isset($pdo)) {
    try {
        $stmt = $pdo->prepare('SELECT order_number FROM alfabank_orders WHERE alfabank_order_id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $orderNumber = $row['order_number'] ?? '';
    } catch (Throwable $e) {
        error_log('[Alfabank callback] Lookup order_number: ' . $e->getMessage());
    }
}

// Пробуем получить статус: по нашему orderNumber или по orderId банка
$statusResult = null;
try {
    if ($orderNumber !== '') {
        $statusResult = $client->getOrderStatusByOwnId($orderNumber);
    } elseif ($orderId !== '') {
        $statusResult = $client->getOrderStatus($orderId);
    }
} catch (Throwable $e) {
    error_log('[Alfabank callback] getOrderStatus error: ' . $e->getMessage());
}

// Обновляем статус в БД
if ($statusResult && isset($pdo)) {
    $orderStatus = (int) ($statusResult['orderStatus'] ?? 0);

    // Определяем наш статус
    if (OrderStatus::isDeposited($orderStatus)) {
        $newStatus = 'paid';
    } elseif (OrderStatus::isDeclined($orderStatus)) {
        $newStatus = 'declined';
    } elseif (OrderStatus::isReversed($orderStatus)) {
        $newStatus = 'reversed';
    } elseif (OrderStatus::isRefunded($orderStatus)) {
        $newStatus = 'refunded';
    } else {
        $newStatus = 'pending'; // Ожидание, в процессе
    }

    try {
        $oid = $statusResult['orderId'] ?? null;
        $updateOrderNumber = $orderNumber ?: null;

        if ($updateOrderNumber !== null) {
            $upd = $pdo->prepare('UPDATE alfabank_orders SET status = ?, alfabank_order_id = COALESCE(?, alfabank_order_id), updated_at = NOW() WHERE order_number = ?');
            $upd->execute([$newStatus, $oid, $updateOrderNumber]);
        } elseif ($oid !== null) {
            $upd = $pdo->prepare('UPDATE alfabank_orders SET status = ?, updated_at = NOW() WHERE alfabank_order_id = ?');
            $upd->execute([$newStatus, $oid]);
        }
    } catch (Throwable $e) {
        error_log('[Alfabank callback] Update status: ' . $e->getMessage());
    }

    // Редирект в зависимости от результата
    if (OrderStatus::isDeposited($orderStatus)) {
        session_start();
        $_SESSION['payment_success_message'] = 'Оплата прошла успешно!';
        $_SESSION['payment_order_number'] = $orderNumber;
        header('Location: ' . ALFABANK_SUCCESS_URL);
        exit;
    }
}

// Ошибка или отклонение — на страницу неудачи
session_start();
$_SESSION['payment_error'] = $_SESSION['payment_error'] ?? 'Оплата не завершена или была отклонена.';
header('Location: ' . ALFABANK_FAIL_URL);
exit;
