<?php
/**
 * File-based rate limiting for auth endpoints.
 */
declare(strict_types=1);

function rate_limit_storage_dir(): string
{
    $dir = sys_get_temp_dir() . '/travelhub_rate_limit';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return $dir;
}

function rate_limit_key_hash(string $key): string
{
    return hash('sha256', $key);
}

/**
 * @return array{allowed: bool, retry_after?: int}
 */
function rate_limit_check(string $key, int $maxAttempts, int $windowSeconds): array
{
    $path = rate_limit_storage_dir() . '/' . rate_limit_key_hash($key) . '.json';
    $now = time();
    $data = ['count' => 0, 'reset_at' => $now + $windowSeconds];

    if (is_file($path)) {
        $raw = @file_get_contents($path);
        $decoded = $raw ? json_decode($raw, true) : null;
        if (is_array($decoded) && isset($decoded['count'], $decoded['reset_at'])) {
            if ((int) $decoded['reset_at'] > $now) {
                $data = $decoded;
            }
        }
    }

    if ((int) $data['count'] >= $maxAttempts) {
        return [
            'allowed' => false,
            'retry_after' => max(1, (int) $data['reset_at'] - $now),
        ];
    }

    return ['allowed' => true];
}

function rate_limit_hit(string $key, int $windowSeconds): void
{
    $path = rate_limit_storage_dir() . '/' . rate_limit_key_hash($key) . '.json';
    $now = time();
    $data = ['count' => 0, 'reset_at' => $now + $windowSeconds];

    if (is_file($path)) {
        $raw = @file_get_contents($path);
        $decoded = $raw ? json_decode($raw, true) : null;
        if (is_array($decoded) && isset($decoded['count'], $decoded['reset_at'])) {
            if ((int) $decoded['reset_at'] > $now) {
                $data = $decoded;
            }
        }
    }

    $data['count'] = (int) $data['count'] + 1;
    if ((int) $data['reset_at'] <= $now) {
        $data['reset_at'] = $now + $windowSeconds;
    }

    @file_put_contents($path, json_encode($data), LOCK_EX);
}

function rate_limit_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
        $candidate = trim($parts[0]);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            $ip = $candidate;
        }
    }
    return $ip;
}

function rate_limit_enforce(string $scope, string $identifier, int $maxAttempts, int $windowSeconds): void
{
    $key = $scope . ':' . rate_limit_client_ip() . ':' . strtolower(trim($identifier));
    $check = rate_limit_check($key, $maxAttempts, $windowSeconds);
    if (!$check['allowed']) {
        $retry = (int) ($check['retry_after'] ?? $windowSeconds);
        json_error(
            'Слишком много попыток. Попробуйте через ' . max(1, (int) ceil($retry / 60)) . ' мин.',
            429,
            'RATE_LIMITED'
        );
    }
    rate_limit_hit($key, $windowSeconds);
}
