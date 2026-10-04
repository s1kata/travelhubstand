<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function fail(int $code, string $msg, array $extra = []): void {
    http_response_code($code);
    echo json_encode(array_merge(['success' => false, 'error' => $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function cfg(array $cfg, string $key, string $env): string {
    $v = $cfg[$key] ?? null;
    if (is_string($v) && trim($v) !== '') return trim($v);
    $e = getenv($env);
    return is_string($e) ? trim($e) : '';
}

function tinkoffSign(array $params, string $password): string {
    $data = $params;
    unset($data['Token']);
    $data['Password'] = $password;
    ksort($data);

    $concat = '';
    foreach ($data as $value) {
        if (is_scalar($value) || $value === null) $concat .= (string)$value;
    }
    return hash('sha256', $concat);
}

function mapStatus(string $s): string {
    $s = strtoupper(trim($s));
    if ($s === 'CONFIRMED') return 'success';
    if ($s === 'CANCELED' || $s === 'CANCELLED') return 'cancelled';
    if (in_array($s, ['REJECTED','DEADLINE_EXPIRED','REFUNDED','AUTH_FAIL','REVERSED'], true)) return 'failed';
    return 'pending';
}

function resolveTransactionId(): string {
    $fromQuery = isset($_GET['transactionId']) ? trim((string)$_GET['transactionId']) : '';
    if ($fromQuery !== '') return $fromQuery;

    // fallback для /api/payment-status/<id>, если rewrite не сработал
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $path = (string)parse_url($uri, PHP_URL_PATH);
    $last = trim((string)basename($path));
    return $last;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail(405, 'Method not allowed');
}

$configPath = __DIR__ . '/auth-mobile.config.php';
if (!is_file($configPath)) {
    fail(500, 'auth-mobile.config.php not found');
}
/** @var array<string,mixed> $CONFIG */
$CONFIG = require $configPath;

$jwtLib = __DIR__ . '/lib/auth-jwt.php';
if (!is_file($jwtLib)) {
    fail(500, 'auth-jwt.php not found');
}
require_once $jwtLib;

$claims = auth_jwt_require_bearer($CONFIG);
$authUserId = (string)($claims['sub'] ?? '');
if ($authUserId === '') {
    fail(401, 'Invalid auth token payload');
}

$transactionId = resolveTransactionId();
if ($transactionId === '') {
    fail(400, 'transactionId is required');
}
if (preg_match('/^\d{1,32}$/', $transactionId) !== 1) {
    fail(400, 'Invalid transactionId format');
}

$terminalKey = cfg($CONFIG, 'tinkoff_terminal_key', 'TINKOFF_TERMINAL_KEY');
$password = cfg($CONFIG, 'tinkoff_password', 'TINKOFF_PASSWORD');
if ($terminalKey === '' || $password === '') {
    fail(500, 'Tinkoff is not configured');
}

$payload = [
    'TerminalKey' => $terminalKey,
    'PaymentId' => $transactionId,
];
$payload['Token'] = tinkoffSign($payload, $password);

$ch = curl_init('https://securepay.tinkoff.ru/v2/GetState');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 5,
]);

$response = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    fail(502, 'Payment service unavailable', ['details' => $curlErr ?: 'curl_error']);
}
if ($httpCode >= 500) {
    fail(502, 'Payment provider temporarily unavailable');
}

$data = json_decode((string)$response, true);
if (!is_array($data)) {
    fail(502, 'Invalid response from payment service');
}

// Лог для диагностики (можно убрать после стабилизации)
error_log('[payment-status] GetState raw=' . json_encode($data, JSON_UNESCAPED_UNICODE));

$errorCode = (string)($data['ErrorCode'] ?? '');
if ($errorCode !== '' && $errorCode !== '0') {
    // Отдаем детали, чтобы не терять причину "Неверные параметры"
    fail(502, (string)($data['Message'] ?? 'Payment state request failed'), [
        'errorCode' => $errorCode,
        'providerStatus' => (string)($data['Status'] ?? ''),
        'providerDetails' => (string)($data['Details'] ?? ''),
    ]);
}

// Проверка владельца платежа, если create-payment передает CustomerKey
$customerKey = isset($data['CustomerKey']) ? trim((string)$data['CustomerKey']) : '';
if ($customerKey !== '' && $customerKey !== $authUserId) {
    fail(403, 'Forbidden: payment does not belong to current user');
}

$providerStatus = (string)($data['Status'] ?? 'pending');
$status = mapStatus($providerStatus);

// Важный fallback: если банк дал Success=true, не держим вечный pending
$successFlag = (bool)($data['Success'] ?? false);
if ($status === 'pending' && $successFlag) {
    $status = 'success';
}

$amountKopecks = isset($data['Amount']) && is_numeric($data['Amount']) ? (int)$data['Amount'] : null;
$amount = $amountKopecks !== null ? round($amountKopecks / 100, 2) : null;

echo json_encode([
    'success' => true,
    'status' => $status,                 // pending|success|failed|cancelled
    'providerStatus' => $providerStatus, // raw статус Tinkoff
    'amount' => $amount,                 // рубли
    'amountKopecks' => $amountKopecks,   // копейки
    'paidAt' => null,
], JSON_UNESCAPED_UNICODE);