<?php
/**
 * JWT HS256 для auth-mobile.php и CRM/оплаты (общий jwt_secret).
 */
declare(strict_types=1);

function auth_jwt_issuer(array $config): string
{
    $iss = trim((string) ($config['jwt_issuer'] ?? 'travelhub-auth'));
    return $iss !== '' ? $iss : 'travelhub-auth';
}

function auth_get_authorization_header(): string
{
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        return (string) $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (strcasecmp((string) $name, 'Authorization') === 0) {
                    return (string) $value;
                }
            }
        }
    }
    return '';
}

function auth_jwt_encode(array $payload, string $secret): string
{
    $header = auth_base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
    $body = auth_base64url_encode(json_encode($payload));
    $sig = auth_base64url_encode(hash_hmac('sha256', "{$header}.{$body}", $secret, true));
    return "{$header}.{$body}.{$sig}";
}

function auth_jwt_decode(string $token, string $secret, ?string $expectedIssuer = null): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }
    [$h, $p, $s] = $parts;
    $expected = auth_base64url_encode(hash_hmac('sha256', "{$h}.{$p}", $secret, true));
    if (!hash_equals($expected, $s)) {
        return null;
    }
    $payload = json_decode(auth_base64url_decode($p), true);
    if (!is_array($payload)) {
        return null;
    }
    if (isset($payload['exp']) && time() >= (int) $payload['exp']) {
        return null;
    }
    if ($expectedIssuer !== null && $expectedIssuer !== '') {
        $iss = isset($payload['iss']) ? (string) $payload['iss'] : '';
        if ($iss === '' || !hash_equals($expectedIssuer, $iss)) {
            return null;
        }
    }
    return $payload;
}

/**
 * @return array{sub: string, ...}
 */
function auth_jwt_require_bearer(array $config): array
{
    $header = auth_get_authorization_header();
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        auth_jwt_json_error('Unauthorized: Bearer token required', 401);
    }
    $token = trim($m[1]);
    $secret = (string) ($config['jwt_secret'] ?? '');
    if ($secret === '') {
        auth_jwt_json_error('JWT secret not configured', 500);
    }
    $claims = auth_jwt_decode($token, $secret, auth_jwt_issuer($config));
    if (!$claims || empty($claims['sub'])) {
        auth_jwt_json_error('Invalid or expired auth token', 401);
    }
    return $claims;
}

function auth_jwt_json_error(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function auth_base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function auth_base64url_decode(string $data): string
{
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return (string) base64_decode(strtr($data, '-_', '+/'));
}
