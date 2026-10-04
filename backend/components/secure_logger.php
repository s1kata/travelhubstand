<?php
declare(strict_types=1);

if (!function_exists('th_log_is_enabled')) {
    function th_log_is_enabled(): bool
    {
        return filter_var(getenv('TH_LOG_ENABLED') ?: ($_ENV['TH_LOG_ENABLED'] ?? '1'), FILTER_VALIDATE_BOOLEAN);
    }
}

if (!function_exists('th_log_root_dir')) {
    function th_log_root_dir(): string
    {
        if (defined('TV_PROJECT_ROOT')) {
            $root = TV_PROJECT_ROOT;
        } elseif (function_exists('th_project_root')) {
            $root = th_project_root();
        } else {
            $root = dirname(__DIR__, 2);
        }
        $dir = $root . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir;
    }
}

if (!function_exists('th_log_salt')) {
    function th_log_salt(): string
    {
        $salt = (string) (getenv('TH_LOG_SALT') ?: ($_ENV['TH_LOG_SALT'] ?? ''));
        if ($salt !== '') {
            return $salt;
        }

        return 'travelhub-default-log-salt-change-me';
    }
}

if (!function_exists('th_log_hash')) {
    function th_log_hash(string $value): string
    {
        return substr(hash_hmac('sha256', $value, th_log_salt()), 0, 24);
    }
}

if (!function_exists('th_log_mask_phone')) {
    function th_log_mask_phone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value);
        if ($digits === null || $digits === '') {
            return '[phone]';
        }
        $tail = substr($digits, -4);
        return '[phone:*' . $tail . ']';
    }
}

if (!function_exists('th_log_mask_email')) {
    function th_log_mask_email(string $value): string
    {
        $v = trim($value);
        if ($v === '' || strpos($v, '@') === false) {
            return '[email]';
        }
        [$name, $domain] = array_pad(explode('@', $v, 2), 2, '');
        $name = $name !== '' ? mb_substr($name, 0, 1) . '***' : '***';
        $domainMasked = $domain !== '' ? preg_replace('/^(.).+(\..+)$/', '$1***$2', $domain) : '***';
        return '[email:' . $name . '@' . $domainMasked . ']';
    }
}

if (!function_exists('th_log_maybe_mask_string')) {
    function th_log_maybe_mask_string(string $value): string
    {
        $v = trim($value);
        if ($v === '') {
            return '';
        }
        if (preg_match('/^\+?[0-9\-\s()]{7,}$/', $v)) {
            return th_log_mask_phone($v);
        }
        if (strpos($v, '@') !== false && filter_var($v, FILTER_VALIDATE_EMAIL)) {
            return th_log_mask_email($v);
        }
        if (strlen($v) > 240) {
            return mb_substr($v, 0, 240) . '…';
        }
        return $v;
    }
}

if (!function_exists('th_log_is_sensitive_key')) {
    function th_log_is_sensitive_key(string $key): bool
    {
        static $patterns = [
            'password', 'pass', 'pwd', 'token', 'secret', 'authorization', 'auth',
            'cookie', 'session', 'jwt', 'bearer', 'api_key', 'apikey',
            'phone', 'email', 'card', 'pan', 'cvv', 'cvc',
            'passport', 'child', 'message', 'reply', 'raw', 'response',
        ];
        $k = strtolower($key);
        foreach ($patterns as $p) {
            if (strpos($k, $p) !== false) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('th_log_scrub_value')) {
    /**
     * @param mixed $value
     * @return mixed
     */
    function th_log_scrub_value(mixed $value, string $key = '', int $depth = 0): mixed
    {
        if ($depth > 4) {
            return '[depth-limit]';
        }
        if (is_array($value)) {
            $out = [];
            $i = 0;
            foreach ($value as $k => $v) {
                if ($i >= 60) {
                    $out['__truncated__'] = true;
                    break;
                }
                $kk = (string) $k;
                if (th_log_is_sensitive_key($kk)) {
                    if (is_string($v)) {
                        $out[$kk] = '[redacted:' . th_log_hash($v) . ']';
                    } elseif (is_scalar($v) || $v === null) {
                        $out[$kk] = '[redacted]';
                    } else {
                        $out[$kk] = '[redacted-complex]';
                    }
                } else {
                    $out[$kk] = th_log_scrub_value($v, $kk, $depth + 1);
                }
                $i++;
            }
            return $out;
        }
        if (is_string($value)) {
            if ($key !== '' && th_log_is_sensitive_key($key)) {
                return '[redacted:' . th_log_hash($value) . ']';
            }
            return th_log_maybe_mask_string($value);
        }
        if (is_object($value)) {
            return '[object:' . get_class($value) . ']';
        }
        if (is_resource($value)) {
            return '[resource]';
        }
        return $value;
    }
}

if (!function_exists('th_log_ip_fingerprint')) {
    function th_log_ip_fingerprint(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $forwarded = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwarded !== '') {
            $ip = trim(explode(',', $forwarded)[0]);
        }
        if ($ip === '') {
            return '';
        }
        return 'ip_' . th_log_hash($ip);
    }
}

if (!function_exists('th_log_write')) {
    /**
     * @param array<string,mixed> $context
     */
    function th_log_write(string $channel, string $event, array $context = [], string $level = 'info'): void
    {
        if (!th_log_is_enabled()) {
            return;
        }
        try {
            $safeChannel = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $channel) ?: 'app';
            $payload = [
                'ts' => date('c'),
                'level' => $level,
                'event' => $event,
                'ip' => th_log_ip_fingerprint(),
                'ctx' => th_log_scrub_value($context),
            ];
            $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($line) || $line === '') {
                return;
            }
            $file = th_log_root_dir() . DIRECTORY_SEPARATOR . $safeChannel . '.log';
            @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
            @chmod($file, 0600);
            $toErrorLog = filter_var(getenv('TH_LOG_TO_ERRORLOG') ?: ($_ENV['TH_LOG_TO_ERRORLOG'] ?? '0'), FILTER_VALIDATE_BOOLEAN);
            if ($toErrorLog) {
                error_log('[app][' . $safeChannel . '] ' . $line);
            }
        } catch (Throwable $e) {
            // avoid recursive logging failures
        }
    }
}

