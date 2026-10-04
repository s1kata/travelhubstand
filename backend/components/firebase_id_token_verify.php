<?php
/**
 * Проверка Firebase ID token (RS256, Google x509). Общий код для оплаты и CRM API.
 */
declare(strict_types=1);

function payment_token_verify_debug(): bool
{
    $v = getenv('PAYMENT_TOKEN_VERIFY_DEBUG');
    if ($v === false || $v === '') {
        $v = $_ENV['PAYMENT_TOKEN_VERIFY_DEBUG'] ?? '';
    }
    $v = strtolower(trim((string) $v));

    return $v === '1' || $v === 'true' || $v === 'yes';
}

/** Пишет в data/payment-token-verify.log только при PAYMENT_TOKEN_VERIFY_DEBUG=1 (JWT не логируем). */
function payment_token_verify_log(string $line): void
{
    if (!payment_token_verify_debug()) {
        return;
    }
    $root = dirname(__DIR__, 2);
    $dir = $root . DIRECTORY_SEPARATOR . 'data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $path = $dir . DIRECTORY_SEPARATOR . 'payment-token-verify.log';
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '-');
    @file_put_contents($path, date('c') . ' ip=' . $ip . ' ' . $line . "\n", FILE_APPEND | LOCK_EX);
}

function firebase_jwt_b64url_decode(string $data): string
{
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    $raw = base64_decode(strtr($data, '-_', '+/'), true);

    return $raw === false ? '' : $raw;
}

/**
 * @return array<string, string> kid => PEM
 */
function firebase_jwt_load_google_x509_certs(): array
{
    $ttl = 3600;
    $root = dirname(__DIR__, 2);
    $dir = $root . DIRECTORY_SEPARATOR . 'data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $cacheFile = $dir . DIRECTORY_SEPARATOR . 'securetoken_google_x509.json';
    if (is_file($cacheFile) && (time() - (int) @filemtime($cacheFile)) < $ttl) {
        $cached = json_decode((string) @file_get_contents($cacheFile), true);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }
    }

    $url = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false || $httpCode !== 200) {
        return [];
    }
    $decoded = json_decode($response, true);
    if (!is_array($decoded) || $decoded === []) {
        return [];
    }
    @file_put_contents($cacheFile, json_encode($decoded, JSON_UNESCAPED_UNICODE));

    return $decoded;
}

/**
 * Валидирует подпись и стандартные claims Firebase ID token; возвращает payload или null.
 *
 * @return array<string, mixed>|null
 */
function firebase_id_token_parse_and_verify(string $jwt): ?array
{
    $projectId = trim((string) (getenv('FIREBASE_PROJECT_ID') ?: ($_ENV['FIREBASE_PROJECT_ID'] ?? '')));
    if ($projectId === '') {
        payment_token_verify_log('verify_fail empty_env project_id');

        return null;
    }

    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        payment_token_verify_log('verify_fail jwt_not_three_segments');

        return null;
    }
    [$h64, $p64, $s64] = $parts;
    if ($h64 === '' || $p64 === '' || $s64 === '') {
        payment_token_verify_log('verify_fail jwt_empty_segment');

        return null;
    }

    $headerRaw = firebase_jwt_b64url_decode($h64);
    $payloadRaw = firebase_jwt_b64url_decode($p64);
    $sigBin = firebase_jwt_b64url_decode($s64);
    if ($headerRaw === '' || $payloadRaw === '' || $sigBin === '') {
        payment_token_verify_log('verify_fail jwt_b64_decode');

        return null;
    }

    $header = json_decode($headerRaw, true);
    if (!is_array($header)) {
        payment_token_verify_log('verify_fail jwt_header_json');

        return null;
    }
    $alg = (string) ($header['alg'] ?? '');
    $kid = (string) ($header['kid'] ?? '');
    if ($alg !== 'RS256' || $kid === '') {
        payment_token_verify_log('verify_fail jwt_header_alg_kid alg=' . $alg . ' kid_empty=' . ($kid === '' ? '1' : '0'));

        return null;
    }

    $certs = firebase_jwt_load_google_x509_certs();
    if (!isset($certs[$kid])) {
        payment_token_verify_log('verify_fail jwt_no_cert_for_kid kid=' . $kid . ' certs=' . count($certs));

        return null;
    }

    $pem = (string) $certs[$kid];
    $pkey = openssl_pkey_get_public($pem);
    if ($pkey === false) {
        payment_token_verify_log('verify_fail openssl_pubkey');

        return null;
    }
    $data = $h64 . '.' . $p64;
    $ok = openssl_verify($data, $sigBin, $pkey, OPENSSL_ALGO_SHA256);
    if (PHP_VERSION_ID < 80000) {
        openssl_free_key($pkey);
    }
    if ($ok !== 1) {
        payment_token_verify_log('verify_fail openssl_verify rc=' . (string) $ok);

        return null;
    }

    $payload = json_decode($payloadRaw, true);
    if (!is_array($payload)) {
        payment_token_verify_log('verify_fail jwt_payload_json');

        return null;
    }

    $aud = (string) ($payload['aud'] ?? '');
    $iss = (string) ($payload['iss'] ?? '');
    $sub = (string) ($payload['sub'] ?? '');
    $exp = (int) ($payload['exp'] ?? 0);
    $leeway = 120;
    $issExpected = 'https://securetoken.google.com/' . $projectId;
    if ($aud !== $projectId) {
        payment_token_verify_log(
            'verify_fail aud_mismatch env_project_id=' . $projectId . ' token_aud=' . $aud
        );

        return null;
    }
    if ($iss !== $issExpected) {
        payment_token_verify_log(
            'verify_fail iss_mismatch expected=' . $issExpected . ' token_iss=' . $iss
        );

        return null;
    }
    if ($sub === '') {
        payment_token_verify_log('verify_fail sub_empty');

        return null;
    }
    if ($exp < time() - $leeway) {
        payment_token_verify_log('verify_fail exp_past exp=' . $exp . ' now=' . time());

        return null;
    }

    return $payload;
}

function verify_firebase_token_uid(string $jwt, string $expectedUid): bool
{
    if ($expectedUid === '') {
        payment_token_verify_log('verify_fail expected_uid_empty');

        return false;
    }
    $payload = firebase_id_token_parse_and_verify($jwt);
    if ($payload === null) {
        return false;
    }
    $sub = (string) ($payload['sub'] ?? '');
    if ($sub !== $expectedUid) {
        payment_token_verify_log(
            'verify_fail uid_mismatch body_userId=' . $expectedUid . ' token_sub=' . $sub
        );

        return false;
    }

    return true;
}
