<?php
/**
 * @deprecated Не используется. Webhook — public_html/api/payment-webhook.php (mobile API).
 * POST /api/payment-webhook
 * Уведомления от Tinkoff T-Kassa (NotificationURL).
 * Обязательна проверка подписи Token. Ответ: HTTP 200, тело "OK".
 *
 * После CONFIRMED: MySQL tinkoff_payments + при необходимости tour_bookings;
 * Firestore bookings/{OrderId} (REST, firestore-helper.php, те же FIREBASE_* что и кэш туров).
 * Альтернатива с Composer: kreait/firebase-php — не обязательна при уже настроенном JSON сервисного аккаунта.
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'tinkoff_helper.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'firestore-helper.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'ERROR';
    exit;
}

$rawInput = file_get_contents('php://input');
$body = json_decode($rawInput, true);
if (!is_array($body)) {
    http_response_code(400);
    echo 'ERROR';
    exit;
}

$password = tinkoff_get_password();
if ($password === '') {
    error_log('[Tinkoff Webhook] TINKOFF_PASSWORD not set');
    http_response_code(500);
    echo 'ERROR';
    exit;
}

if (!tinkoff_verify_notification_token($body, $password)) {
    error_log('[Tinkoff Webhook] Invalid token from notification');
    http_response_code(403);
    echo 'ERROR';
    exit;
}

$orderId = isset($body['OrderId']) ? (string) $body['OrderId'] : '';
$success = isset($body['Success']) && (strtolower((string) $body['Success']) === 'true' || $body['Success'] === true);
$status = isset($body['Status']) ? (string) $body['Status'] : '';
$paymentId = isset($body['PaymentId']) ? (string) $body['PaymentId'] : '';
$amount = isset($body['Amount']) ? (int) $body['Amount'] : 0;

$isPaid = $success && ($status === 'CONFIRMED');

if (isset($pdo)) {
    try {
        ensure_tinkoff_payments_table($pdo);
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare('SELECT id, status FROM tinkoff_payments WHERE payment_id = ? LIMIT 1');
        $stmt->execute([$paymentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $updateStatus = $isPaid ? 'CONFIRMED' : $status;
            $paidAt = $isPaid ? $now : null;
            $stmt = $pdo->prepare('UPDATE tinkoff_payments SET status = ?, amount_kopecks = COALESCE(NULLIF(?, 0), amount_kopecks), paid_at = COALESCE(?, paid_at) WHERE payment_id = ?');
            $stmt->execute([$updateStatus, $amount, $paidAt, $paymentId]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO tinkoff_payments (order_id, payment_id, amount_kopecks, currency, user_id, status, paid_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $orderId,
                $paymentId,
                $amount ?: 0,
                'RUB',
                (string) ($body['CustomerKey'] ?? ''),
                $isPaid ? 'CONFIRMED' : $status,
                $isPaid ? $now : null,
                $now,
            ]);
        }

        if ($isPaid && $orderId !== '') {
            mark_order_paid($pdo, $orderId, $now);
        }
    } catch (Throwable $e) {
        error_log('[Tinkoff Webhook] DB error: ' . $e->getMessage());
    }
}

if ($isPaid && $orderId !== '' && $paymentId !== '') {
    firestore_booking_mark_paid($orderId, $paymentId);
}

http_response_code(200);
echo 'OK';

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

/**
 * Обновляет заказ в БД: payment_status = paid, paid_at = now.
 * Поддерживается tour_bookings (если есть колонки payment_status, paid_at) или таблица с order_id.
 */
function mark_order_paid(PDO $pdo, string $orderId, string $paidAt): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if (!ctype_digit($orderId)) {
        return;
    }
    $id = (int) $orderId;
    try {
        if ($driver === 'sqlite') {
            $cols = $pdo->query("PRAGMA table_info(tour_bookings)")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $cols = $pdo->query("SHOW COLUMNS FROM tour_bookings")->fetchAll(PDO::FETCH_ASSOC);
        }
        $hasPaymentStatus = false;
        $hasPaidAt = false;
        foreach ($cols as $c) {
            $name = $c['name'] ?? $c['Field'] ?? '';
            if ($name === 'payment_status') $hasPaymentStatus = true;
            if ($name === 'paid_at') $hasPaidAt = true;
        }
        if (!$hasPaymentStatus && !$hasPaidAt) {
            if ($driver === 'sqlite') {
                $pdo->exec('ALTER TABLE tour_bookings ADD COLUMN payment_status VARCHAR(32) DEFAULT NULL');
                $pdo->exec('ALTER TABLE tour_bookings ADD COLUMN paid_at DATETIME DEFAULT NULL');
            } else {
                $pdo->exec('ALTER TABLE tour_bookings ADD COLUMN payment_status VARCHAR(32) DEFAULT NULL');
                $pdo->exec('ALTER TABLE tour_bookings ADD COLUMN paid_at DATETIME DEFAULT NULL');
            }
            $hasPaymentStatus = true;
            $hasPaidAt = true;
        }
        if ($hasPaymentStatus && $hasPaidAt) {
            $pdo->prepare('UPDATE tour_bookings SET payment_status = ?, paid_at = ? WHERE id = ?')->execute(['paid', $paidAt, $id]);
        }
    } catch (Throwable $e) {
        error_log('[Tinkoff Webhook] mark_order_paid: ' . $e->getMessage());
    }
}
