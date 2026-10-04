<?php
/**
 * Локальные настройки синхронизации акционных туров (БД, пути, URL источника).
 * Не путать с backend/config/config.php — это отдельный модуль под cron и будущий Яндекс API.
 */
declare(strict_types=1);

if (!function_exists('promo_tours_sync_load_env')) {
    /**
     * Минимальная подгрузка .env из корня проекта (два уровня выше этой папки).
     */
    function promo_tours_sync_load_env(): void
    {
        $candidates = [
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env',
            dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env',
        ];
        foreach ($candidates as $envPath) {
            if (!is_file($envPath) || !is_readable($envPath)) {
                continue;
            }
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                continue;
            }
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                if (!str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = array_map('trim', explode('=', $line, 2));
                if ($k === '') {
                    continue;
                }
                if (
                    ($v !== '') &&
                    (($v[0] === '"' && str_ends_with($v, '"')) || ($v[0] === "'" && str_ends_with($v, "'")))
                ) {
                    $v = substr($v, 1, -1);
                }
                if (getenv($k) === false) {
                    putenv("$k=$v");
                    $_ENV[$k] = $v;
                }
            }
            break;
        }
    }
}

promo_tours_sync_load_env();

$root = __DIR__;

$driver = strtolower(trim((string)(getenv('DB_DRIVER') ?: ($_ENV['DB_DRIVER'] ?? 'mysql'))));
if ($driver === '') {
    $driver = 'mysql';
}

$dbConfig = [
    'driver' => $driver,
    'host' => getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'localhost'),
    'port' => getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306'),
    'database' => getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? 'travel_hub'),
    'username' => getenv('DB_USERNAME') ?: ($_ENV['DB_USERNAME'] ?? ''),
    'password' => getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? ''),
    'charset' => getenv('DB_CHARSET') ?: ($_ENV['DB_CHARSET'] ?? 'utf8mb4'),
];

$sqlitePath = trim((string)(getenv('SQLITE_PATH') ?: ($_ENV['SQLITE_PATH'] ?? '')));
$projectRoot = dirname(__DIR__, 2);
if ($sqlitePath !== '' && !preg_match('/^[A-Za-z]:[\\\\\\/]/', $sqlitePath) && ($sqlitePath[0] ?? '') !== '/' && ($sqlitePath[0] ?? '') !== '\\') {
    $sqlitePath = $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sqlitePath);
}
if ($sqlitePath === '') {
    $sqlitePath = $projectRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'user_management.db';
}
$dbConfig['sqlite_path'] = $sqlitePath;

$jsonExtra = trim((string)(getenv('PROMO_TOURS_JSON_URL') ?: ($_ENV['PROMO_TOURS_JSON_URL'] ?? '')));

return [
    'module_root' => $root,
    'cache_dir' => $root . DIRECTORY_SEPARATOR . 'cache',
    'log_dir' => $root . DIRECTORY_SEPARATOR . 'logs',
    'log_file' => $root . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'fetch.log',
    /**
     * Дополнительный источник: полный URL JSON (например legacy searchpromotours.php).
     * Пусто — только TourVisor через прокси (как country_promo_tours / promotions).
     */
    'json_source_url' => $jsonExtra,
    'http_timeout_seconds' => (int)(getenv('PROMO_TOURS_HTTP_TIMEOUT') ?: ($_ENV['PROMO_TOURS_HTTP_TIMEOUT'] ?? 120)),
    'tourvisor_departure_id' => (int)(getenv('PROMO_TOURS_SYNC_DEPARTURE_ID') ?: ($_ENV['PROMO_TOURS_SYNC_DEPARTURE_ID'] ?? 1)),
    'tourvisor_delay_seconds' => (float)(getenv('PROMO_TOURS_SYNC_DELAY_SEC') ?: ($_ENV['PROMO_TOURS_SYNC_DELAY_SEC'] ?? 2)),
    'tourvisor_live' => filter_var(getenv('PROMO_TOURS_SYNC_LIVE') ?: ($_ENV['PROMO_TOURS_SYNC_LIVE'] ?? false), FILTER_VALIDATE_BOOLEAN),
    'db' => $dbConfig,
];
