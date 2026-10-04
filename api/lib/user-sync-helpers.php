<?php
/**
 * Shared helpers for /api/user/* sync endpoints.
 */
declare(strict_types=1);

function user_sync_json_ok(mixed $data): void
{
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function user_sync_json_error(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function user_sync_db_connect(array $config): PDO
{
    if (function_exists('db_connect')) {
        return db_connect($config);
    }

    $db = $config['db'] ?? [];
    if (empty($db['name']) || empty($db['user'])) {
        throw new RuntimeException('Database not configured');
    }

    $charset = $db['charset'] ?? 'utf8mb4';
    if (!empty($db['unix_socket'])) {
        $dsn = sprintf(
            'mysql:unix_socket=%s;dbname=%s;charset=%s',
            $db['unix_socket'],
            $db['name'],
            $charset
        );
    } else {
        $host = $db['host'] ?? 'localhost';
        $port = (int) ($db['port'] ?? 3306);
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $db['name'], $charset);
    }

    return new PDO($dsn, (string) $db['user'], (string) ($db['pass'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

/**
 * @return array<string, mixed>|null
 */
function user_sync_decode_json(?string $raw): ?array
{
    if ($raw === null || $raw === '') {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}
