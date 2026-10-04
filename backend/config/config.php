<?php
declare(strict_types=1);

// Session configuration (default settings)

/**
 * Global configuration and secure database bootstrap.
 *
 * This script loads environment variables from a local .env file (when present),
 * establishes a PDO connection using a SQL driver (MySQL by default) and exposes
 * the `$pdo` instance to the rest of the application.
 *
 * ⚠️ Place your database server outside the public web host or restrict remote
 * access by IP/SSL. Credentials should be stored in the .env file that is never
 * committed to the repository.
 */

// Harden PHP error display in production
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN));
}

if (!APP_DEBUG) {
    ini_set('display_errors', '0');
    error_reporting(0);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// Centralized log disabling: keeps code quiet across the whole project.
$nullLogDevice = (stripos(PHP_OS, 'WIN') === 0) ? 'NUL' : '/dev/null';
ini_set('log_errors', '0');
ini_set('error_log', $nullLogDevice);

// Безопасность сессии: защита от кражи cookie и перехвата (до любого session_start())
if (php_sapi_name() !== 'cli') {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Lax');
    $sessionSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if ($sessionSecure) {
        ini_set('session.cookie_secure', '1');
    }
    if (function_exists('session_set_cookie_params')) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $sessionSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

/**
 * Lightweight .env loader (supports KEY=VALUE, comments with #, and quoted values)
 */
if (!function_exists('load_env_file')) {
    function load_env_file(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            error_log('[ENV] File not found or not readable: ' . $path);
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            error_log('[ENV] File is empty or cannot be read');
            return;
        }

        $loaded = 0;
        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if ($line === '' || substr($line, 0, 1) === '#') {
                continue;
            }

            if (strpos($line, '=') === false) {
                error_log("[ENV] Line " . ($lineNum + 1) . " skipped (no = sign): " . substr($line, 0, 50));
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));

            if ($key === '') {
                continue;
            }

            // Remove surrounding quotes
            if ($value !== '' && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
                $value = substr($value, 1, -1);
            }

            putenv("$key=$value");
            $_ENV[$key] = $value;
            $loaded++;
        }
        
        error_log('[ENV] Loaded ' . $loaded . ' variables from .env file');
    }
}

// Два допустимых расположения .env: backend/.env и корень репозитория (как в .env.example).
// Загружаем оба по порядку; последующий файл перекрывает ключи предыдущего — корень имеет приоритет над backend.
$__th_env_files = [];
$__th_backend_env = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
$__th_root_env = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
if (is_file($__th_backend_env)) {
    $__th_env_files[] = $__th_backend_env;
}
if (is_file($__th_root_env)) {
    $__th_env_files[] = $__th_root_env;
}
foreach ($__th_env_files as $__th_env_path) {
    load_env_file($__th_env_path);
}
// parse_ini_file — запасной разбор тех же файлов (только ключи, которые ещё не в окружении)
if (function_exists('parse_ini_file')) {
    foreach ($__th_env_files as $__th_env_path) {
        $envVars = parse_ini_file($__th_env_path);
        if ($envVars === false) {
            continue;
        }
        foreach ($envVars as $key => $value) {
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}
unset($__th_env_files, $__th_backend_env, $__th_root_env, $__th_env_path, $envVars, $key, $value);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';

/** Опциональный URL бота MAX (MAX_BOT_URL в .env). Константа оставлена для совместимости; публичный UI на неё не опирается. */
if (!defined('TRAVELHUB_MAX_BOT_URL')) {
    define(
        'TRAVELHUB_MAX_BOT_URL',
        trim((string) (getenv('MAX_BOT_URL') ?: ($_ENV['MAX_BOT_URL'] ?? 'https://max.ru/id631219827328_bot')))
    );
}
?>