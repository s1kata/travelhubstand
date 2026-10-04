<?php
/**
 * Хелпер безопасности: CSRF, rate limiting, honeypot.
 */
declare(strict_types=1);

/**
 * Генерация CSRF-токена и сохранение в сессии.
 */
function security_csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['_csrf_token']) || empty($_SESSION['_csrf_time']) || (time() - $_SESSION['_csrf_time']) > 3600) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['_csrf_time'] = time();
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Проверка CSRF-токена. Возвращает true если валиден.
 */
function security_csrf_verify(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($token === '' || empty($_SESSION['_csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['_csrf_token'], $token);
}

/**
 * Проверка CSRF по явно переданной строке (JSON API, заголовки).
 */
function security_csrf_verify_token(?string $token): bool {
    if ($token === null || $token === '') {
        return false;
    }
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['_csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['_csrf_token'], $token);
}

/**
 * Rate limit по IP+ключу. Файловый кэш в data/rate_limit/.
 * @return bool true если лимит превышен (блокировать запрос)
 */
function security_rate_limit_exceeded(string $key, int $maxAttempts = 10, int $windowSeconds = 300): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $forwarded = trim($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($forwarded !== '') {
        $ip = trim(explode(',', $forwarded)[0]);
    }
    $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
    $fileKey = substr(md5($ip . $safeKey), 0, 16) . '_' . $safeKey;
    if (defined('TV_PROJECT_ROOT')) {
        $dir = TV_PROJECT_ROOT . '/data/rate_limit';
    } elseif (function_exists('th_project_root')) {
        $dir = th_project_root() . '/data/rate_limit';
    } else {
        $dir = dirname(__DIR__, 2) . '/data/rate_limit';
    }
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . '/' . $fileKey . '.json';
    $now = time();
    $data = ['count' => 0, 'first' => $now];
    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
    }
    if (($now - $data['first']) > $windowSeconds) {
        $data = ['count' => 0, 'first' => $now];
    }
    $data['count']++;
    @file_put_contents($file, json_encode($data), LOCK_EX);
    return $data['count'] > $maxAttempts;
}

/**
 * Проверка honeypot: поле должно быть пустым (боты его заполнят).
 */
function security_honeypot_check(array $input, string $fieldName = 'website'): bool {
    $v = trim((string)($input[$fieldName] ?? ''));
    return $v === '';
}

/**
 * Определяет клиентский IP (с учетом X-Forwarded-For).
 */
function security_client_ip(): string {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $forwarded = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($forwarded !== '') {
        $first = trim((string)explode(',', $forwarded)[0]);
        if ($first !== '') {
            $ip = $first;
        }
    }
    return $ip;
}

/**
 * Базовые security headers.
 */
function security_apply_default_headers(): void {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
}

/**
 * Примитивная проверка на инъекции/XSS-паттерны в query/body.
 */
function security_payload_has_attack_signatures(string $payload): bool {
    if ($payload === '') return false;
    if (strpos($payload, "\0") !== false) return true;
    $patterns = [
        '/(?:union\s+all?\s+select|select\s+.+\s+from|information_schema|sleep\s*\(|benchmark\s*\(|or\s+1\s*=\s*1)/iu',
        '/(?:<script\b|<\/script>|javascript:|onerror\s*=|onload\s*=|<iframe\b)/iu',
        '/(?:\.\.\/|\.\.\\\\|\/etc\/passwd|boot\.ini)/iu',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $payload)) {
            return true;
        }
    }
    return false;
}

/**
 * Унифицированный guard для публичных API.
 * @return array{ok:bool,code?:int,error?:string,reason?:string}
 */
function security_guard_public_api(string $key, string $payload = '', int $maxRpm = 120, int $maxBodyBytes = 131072): array {
    security_apply_default_headers();
    if ($maxBodyBytes > 0 && strlen($payload) > $maxBodyBytes) {
        return ['ok' => false, 'code' => 413, 'error' => 'Payload too large', 'reason' => 'body_limit'];
    }
    if (security_rate_limit_exceeded($key, $maxRpm, 60)) {
        return ['ok' => false, 'code' => 429, 'error' => 'Too many requests', 'reason' => 'rate_limit'];
    }
    $query = http_build_query($_GET ?: []);
    if (security_payload_has_attack_signatures($query) || security_payload_has_attack_signatures($payload)) {
        return ['ok' => false, 'code' => 400, 'error' => 'Bad request', 'reason' => 'suspicious_payload'];
    }
    return ['ok' => true];
}
