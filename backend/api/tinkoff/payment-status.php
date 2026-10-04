<?php
/**
 * @deprecated Не используется. Статус — public_html/api/payment-status.php (mobile API).
 * GET /api/payment-status/:transactionId
 * Статус платежа по PaymentId (transactionId) из Tinkoff.
 * Сначала проверяем свою БД, при необходимости запрашиваем GetState у Tinkoff.
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'tinkoff_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$transactionId = isset($_GET['transactionId']) ? trim((string) $_GET['transactionId']) : '';
if ($transactionId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'transactionId is required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$terminalKey = tinkoff_get_terminal_key();
$password = tinkoff_get_password();

$status = 'pending';
$amount = null;
$paidAt = null;

if (isset($pdo)) {
    try {
        $stmt = $pdo->prepare('SELECT status, amount_kopecks, paid_at FROM tinkoff_payments WHERE payment_id = ? LIMIT 1');
        $stmt->execute([$transactionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $dbStatus = (string) ($row['status'] ?? '');
            $status = map_tinkoff_status_to_api($dbStatus);
            $amount = (int) ($row['amount_kopecks'] ?? 0);
            $paidAt = !empty($row['paid_at']) ? $row['paid_at'] : null;
            if ($status === 'success' || $status === 'failed') {
                echo json_encode([
                    'success' => true,
                    'status' => $status,
                    'amount' => $amount,
                    'paidAt' => $paidAt ? format_iso_date($paidAt) : null,
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    } catch (Throwable $e) {
        error_log('[Tinkoff Status] DB: ' . $e->getMessage());
    }
}

if ($terminalKey !== '' && $password !== '' && $status === 'pending') {
    $getStateParams = [
        'TerminalKey' => $terminalKey,
        'PaymentId'   => $transactionId,
    ];
    $token = tinkoff_sign_request($getStateParams, $password);
    $getStateParams['Token'] = $token;

    $ch = curl_init('https://securepay.tinkoff.ru/v2/GetState');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($getStateParams),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
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
    $errorCode = (string) ($data['ErrorCode'] ?? '');
    if ($errorCode !== '' && $errorCode !== '0') {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => $data['Message'] ?? 'Payment not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (isset($data['Status'])) {
        $status = map_tinkoff_status_to_api((string) $data['Status']);
        if (isset($pdo)) {
            try {
                ensure_tinkoff_payments_table($pdo);
                $stmt = $pdo->prepare('SELECT id FROM tinkoff_payments WHERE payment_id = ? LIMIT 1');
                $stmt->execute([$transactionId]);
                if ($stmt->fetch()) {
                    $paidAtCol = ($status === 'success') ? date('Y-m-d H:i:s') : null;
                    $pdo->prepare('UPDATE tinkoff_payments SET status = ?, paid_at = COALESCE(?, paid_at) WHERE payment_id = ?')
                        ->execute([$data['Status'], $paidAtCol, $transactionId]);
                }
            } catch (Throwable $e) {
                // ignore
            }
        }
        if ($status === 'success') {
            $paidAt = date('Y-m-d H:i:s');
        }
    }
}

echo json_encode([
    'success' => true,
    'status' => $status,
    'amount' => $amount,
    'paidAt' => $paidAt ? format_iso_date($paidAt) : null,
], JSON_UNESCAPED_UNICODE);

function map_tinkoff_status_to_api(string $tinkoffStatus): string
{
    $t = strtoupper($tinkoffStatus);
    if ($t === 'CONFIRMED') {
        return 'success';
    }
    // Отмена — отдельный статус для мобильного приложения (не failed)
    if ($t === 'CANCELED' || $t === 'CANCELLED') {
        return 'cancelled';
    }
    if (in_array($t, ['REJECTED', 'DEADLINE_EXPIRED', 'REFUNDED', 'AUTH_FAIL', 'REVERSED'], true)) {
        return 'failed';
    }
    return 'pending';
}

function format_iso_date(string $dt): string
{
    $ts = strtotime($dt);
    return $ts !== false ? date('c', $ts) : $dt;
}

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
