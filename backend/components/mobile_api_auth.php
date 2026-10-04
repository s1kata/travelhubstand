<?php
/**
 * Мост сайта → mobile payment API (/api/create-payment, /api/payment-status).
 * Вход на сайте — PHP-сессия; JWT только на сервере (тот же secret, что auth-mobile).
 */
declare(strict_types=1);

function mobile_api_base_url(): string
{
    $url = trim((string) (getenv('API_URL') ?: getenv('APP_URL') ?: ($_ENV['API_URL'] ?? $_ENV['APP_URL'] ?? '')));
    if ($url !== '') {
        return rtrim($url, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return ($https ? 'https' : 'http') . '://' . $host;
}

function mobile_api_jwt_secret(): string
{
    return trim((string) (getenv('MOBILE_JWT_SECRET') ?: getenv('JWT_SECRET') ?: ($_ENV['MOBILE_JWT_SECRET'] ?? $_ENV['JWT_SECRET'] ?? '')));
}

/** Онлайн-оплата на сайте: Т-Касса + JWT-мост. Без этого кнопку оплаты не показываем. */
function th_online_payment_enabled(): bool
{
    $key = trim((string) (getenv('TINKOFF_TERMINAL_KEY') ?: ($_ENV['TINKOFF_TERMINAL_KEY'] ?? '')));
    $pass = trim((string) (getenv('TINKOFF_PASSWORD') ?: ($_ENV['TINKOFF_PASSWORD'] ?? '')));
    return $key !== '' && $pass !== '' && mobile_api_jwt_secret() !== '';
}

function mobile_api_jwt_issuer(): string
{
    $iss = trim((string) (getenv('MOBILE_JWT_ISSUER') ?: ($_ENV['MOBILE_JWT_ISSUER'] ?? 'travelhub-auth')));
    return $iss !== '' ? $iss : 'travelhub-auth';
}

function mobile_api_base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function mobile_api_mint_access_token(int $userId, int $ttlSeconds = 900): ?string
{
    $secret = mobile_api_jwt_secret();
    if ($secret === '' || $userId <= 0) {
        return null;
    }
    $now = time();
    $header = mobile_api_base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
    $payload = mobile_api_base64url_encode(json_encode([
        'iss' => mobile_api_jwt_issuer(),
        'sub' => (string) $userId,
        'iat' => $now,
        'exp' => $now + max(60, $ttlSeconds),
    ]));
    $sig = mobile_api_base64url_encode(hash_hmac('sha256', "{$header}.{$payload}", $secret, true));
    return "{$header}.{$payload}.{$sig}";
}

/**
 * @param array<string, mixed>|null $body
 * @return array{httpCode: int, data: array<string, mixed>|null, raw: string}
 */
function mobile_api_request(string $method, string $path, ?array $body, string $bearerToken): array
{
    $url = mobile_api_base_url() . $path;
    $ch = curl_init($url);
    if ($ch === false) {
        return ['httpCode' => 0, 'data' => null, 'raw' => ''];
    }
    $headers = [
        'Accept: application/json',
        'Authorization: Bearer ' . $bearerToken,
    ];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => $headers,
    ];
    $method = strtoupper($method);
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $headers[] = 'Content-Type: application/json';
        $opts[CURLOPT_HTTPHEADER] = $headers;
        $opts[CURLOPT_POSTFIELDS] = json_encode($body ?? [], JSON_UNESCAPED_UNICODE);
    } else {
        $opts[CURLOPT_HTTPHEADER] = $headers;
        $opts[CURLOPT_CUSTOMREQUEST] = $method;
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    return [
        'httpCode' => $httpCode,
        'data' => is_array($data) ? $data : null,
        'raw' => is_string($raw) ? $raw : '',
    ];
}

function mobile_api_site_user_id(): int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
}

function mobile_api_site_require_user(): int
{
    $userId = mobile_api_site_user_id();
    if ($userId <= 0) {
        return 0;
    }
    return $userId;
}

/**
 * @param array<string, mixed> $paymentBody amount, orderId, description?, returnUrl?, failReturnUrl?
 * @return array{ok: bool, httpCode: int, data: array<string, mixed>}
 */
function mobile_api_proxy_create_payment(int $userId, array $paymentBody): array
{
    $token = mobile_api_mint_access_token($userId);
    if ($token === null) {
        return [
            'ok' => false,
            'httpCode' => 500,
            'data' => ['success' => false, 'error' => 'Payment bridge is not configured (MOBILE_JWT_SECRET)'],
        ];
    }
    $body = array_merge($paymentBody, [
        'userId' => (string) $userId,
        'currency' => 'RUB',
    ]);
    $res = mobile_api_request('POST', '/api/create-payment', $body, $token);
    return [
        'ok' => $res['httpCode'] >= 200 && $res['httpCode'] < 300 && is_array($res['data']) && !empty($res['data']['success']),
        'httpCode' => $res['httpCode'] ?: 502,
        'data' => is_array($res['data']) ? $res['data'] : ['success' => false, 'error' => 'Invalid response from payment API'],
    ];
}

/**
 * @return array{ok: bool, httpCode: int, data: array<string, mixed>}
 */
function mobile_api_proxy_payment_status(int $userId, string $transactionId): array
{
    $token = mobile_api_mint_access_token($userId);
    if ($token === null) {
        return [
            'ok' => false,
            'httpCode' => 500,
            'data' => ['success' => false, 'error' => 'Payment bridge is not configured (MOBILE_JWT_SECRET)'],
        ];
    }
    $path = '/api/payment-status/' . rawurlencode($transactionId);
    $res = mobile_api_request('GET', $path, null, $token);
    return [
        'ok' => $res['httpCode'] >= 200 && $res['httpCode'] < 300 && is_array($res['data']) && !empty($res['data']['success']),
        'httpCode' => $res['httpCode'] ?: 502,
        'data' => is_array($res['data']) ? $res['data'] : ['success' => false, 'error' => 'Invalid response from payment API'],
    ];
}
