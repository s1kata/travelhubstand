<?php
declare(strict_types=1);

/**
 * Подключение PDO и вспомогательные пути проекта.
 * Вызывается из config.php после загрузки .env.
 *
 * @var PDO|null $pdo
 */
$pdo = null;

if (!function_exists('th_project_root')) {
    function th_project_root(): string
    {
        if (defined('TH_PROJECT_ROOT')) {
            return (string) TH_PROJECT_ROOT;
        }
        $root = dirname(__DIR__, 2);
        define('TH_PROJECT_ROOT', $root);

        return $root;
    }
}

if (!function_exists('th_tourvisor_cache_dir')) {
    function th_tourvisor_cache_dir(): string
    {
        $explicit = trim((string) (getenv('TOURVISOR_CACHE_DIR') ?: ($_ENV['TOURVISOR_CACHE_DIR'] ?? '')));
        if ($explicit !== '') {
            return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $explicit), DIRECTORY_SEPARATOR);
        }

        return th_project_root() . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'tourvisor_cache';
    }
}

if (!function_exists('th_runtime_host')) {
    function th_runtime_host(): string
    {
        return strtolower(preg_replace('/:\d+$/', '', trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'))));
    }
}

/** Продакшен travelhub63.ru — явная проверка; иначе совпадение хоста с SITE_URL. */
if (!function_exists('th_is_production_site')) {
    function th_is_production_site(): bool
    {
        $host = th_runtime_host();
        if (in_array($host, ['travelhub63.ru', 'www.travelhub63.ru'], true)) {
            return true;
        }
        $siteUrl = rtrim((string) (getenv('SITE_URL') ?: ($_ENV['SITE_URL'] ?? '')), '/');
        if ($siteUrl === '') {
            return false;
        }
        $parsed = parse_url($siteUrl);
        $siteHost = isset($parsed['host']) ? strtolower((string) $parsed['host']) : '';

        return $siteHost !== '' && $siteHost === $host;
    }
}

if (!function_exists('th_site_base_url')) {
    function th_site_base_url(): string
    {
        $siteUrl = rtrim((string) (getenv('SITE_URL') ?: ($_ENV['SITE_URL'] ?? '')), '/');
        if ($siteUrl !== '') {
            if (preg_match('#/frontend/?$#i', $siteUrl)) {
                $siteUrl = rtrim((string) preg_replace('#/frontend/?$#i', '', $siteUrl), '/');
            }

            return $siteUrl;
        }

        if (php_sapi_name() !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
                $first = strtolower(trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
                if ($first === 'https') {
                    $proto = 'https';
                }
            }

            return $proto . '://' . $_SERVER['HTTP_HOST'];
        }

        return 'https://travelhub63.ru';
    }
}

if (!function_exists('th_db_is_available')) {
    function th_db_is_available(): bool
    {
        global $pdo;

        return isset($pdo) && $pdo instanceof PDO;
    }
}

if (!function_exists('th_json_db_unavailable_exit')) {
    /**
     * @param array<string, mixed> $extra
     */
    function th_json_db_unavailable_exit(array $extra = []): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        http_response_code(200);
        echo json_encode(
            array_merge(['success' => false, 'error' => 'Database unavailable'], $extra),
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }
}

$dbDriver = strtolower(trim((string) (getenv('DB_DRIVER') ?: ($_ENV['DB_DRIVER'] ?? 'sqlite'))));
if ($dbDriver === '') {
    $dbDriver = 'sqlite';
}

$remoteDbConfigPath = __DIR__ . DIRECTORY_SEPARATOR . 'db_remote.php';
$useRemoteDb = false;
$remoteConfig = null;

if (is_file($remoteDbConfigPath)) {
    $remoteConfig = require $remoteDbConfigPath;
    if (isset($remoteConfig['host']) &&
        $remoteConfig['host'] !== 'aws.connect.psdb.cloud' &&
        isset($remoteConfig['database']) &&
        $remoteConfig['database'] !== 'your_database_name' &&
        isset($remoteConfig['username']) &&
        $remoteConfig['username'] !== 'your_username') {
        $useRemoteDb = true;
        error_log('[DB] Using remote database configuration from db_remote.php');
    }
}

try {
    if ($dbDriver === 'mysql') {
        if ($useRemoteDb && $remoteConfig) {
            $host = $remoteConfig['host'];
            $port = $remoteConfig['port'] ?? '3306';
            $database = $remoteConfig['database'];
            $username = $remoteConfig['username'];
            $password = $remoteConfig['password'];
            $charset = $remoteConfig['charset'] ?? 'utf8mb4';
            $useSsl = $remoteConfig['ssl'] ?? false;
        } else {
            $host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'localhost');
            $port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306');
            $database = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? 'travel_hub');
            $username = getenv('DB_USERNAME') ?: ($_ENV['DB_USERNAME'] ?? 'travel_user');
            $password = getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? '');
            $charset = getenv('DB_CHARSET') ?: ($_ENV['DB_CHARSET'] ?? 'utf8mb4');
            $useSsl = false;
        }

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $database, $charset);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if ($useSsl) {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            $options[PDO::MYSQL_ATTR_SSL_CA] = '';
            if (defined('PDO::MYSQL_ATTR_SSL_CA')) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = '';
            }
        }

        $sslCa = getenv('DB_SSL_CA') ?: null;
        if (!empty($sslCa) && defined('PDO::MYSQL_ATTR_SSL_CA') && !$useSsl) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
        }

        try {
            $pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            error_log('[DB MySQL] Connection failed: ' . $e->getMessage());
            error_log('[DB MySQL] Falling back to SQLite. Set DB_DRIVER=sqlite in .env to hide this.');
            $dbDriver = 'sqlite';
            $pdo = null;
        }
    }

    if ($dbDriver === 'sqlite' && $pdo === null) {
        $projectRoot = th_project_root();
        $sqlitePath = trim((string) (getenv('SQLITE_PATH') ?: ($_ENV['SQLITE_PATH'] ?? '')));

        $candidates = [];
        if ($sqlitePath !== '') {
            if ($sqlitePath !== '' && substr($sqlitePath, 0, strlen(DIRECTORY_SEPARATOR)) !== DIRECTORY_SEPARATOR && !preg_match('/^[A-Za-z]:[\\\\\\/]/', $sqlitePath)) {
                $sqlitePath = $projectRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sqlitePath);
            }
            $candidates[] = $sqlitePath;
        }
        $dataDir = $projectRoot . DIRECTORY_SEPARATOR . 'data';
        $candidates[] = $dataDir . DIRECTORY_SEPARATOR . 'user_management.db';
        $candidates[] = __DIR__ . DIRECTORY_SEPARATOR . 'user_management.db';
        $candidates[] = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'user_management.db';

        $lastException = null;
        foreach ($candidates as $path) {
            try {
                $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
                $dbDir = dirname($path);
                if (!is_dir($dbDir)) {
                    @mkdir($dbDir, 0755, true);
                }
                if (!is_dir($dbDir)) {
                    continue;
                }
                $dsn = 'sqlite:' . $path;
                $pdo = new PDO($dsn);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                if ($path !== $candidates[0] && count($candidates) > 1) {
                    error_log('[DB] SQLite connected using fallback path: ' . $path);
                }
                break;
            } catch (Throwable $e) {
                $lastException = $e;
                error_log('[DB] SQLite failed for ' . $path . ': ' . $e->getMessage());
                $pdo = null;
            }
        }
        if ($pdo === null && $lastException !== null) {
            throw $lastException;
        }

        try {
            if ($pdo instanceof PDO) {
                $tablesCheck = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
                if ($tablesCheck->fetchColumn() === false) {
                    $pdo->exec('PRAGMA foreign_keys = ON');

                    $schemaPath = $projectRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema_sqlite.sql';
                    if (is_file($schemaPath)) {
                        $schema = file_get_contents($schemaPath);
                        $statements = array_filter(
                            array_map('trim', explode(';', $schema)),
                            static function ($stmt) {
                                return trim($stmt) !== '';
                            }
                        );
                        foreach ($statements as $statement) {
                            $statement = trim($statement);
                            if ($statement !== '') {
                                $pdo->exec($statement);
                            }
                        }
                    } else {
                        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        name TEXT NOT NULL,
                        email TEXT NOT NULL UNIQUE,
                        password TEXT NOT NULL,
                        phone TEXT,
                        city TEXT,
                        age INTEGER,
                        gender TEXT,
                        passport_series TEXT,
                        passport_number TEXT,
                        passport_issued_by TEXT,
                        passport_issue_date DATE,
                        passport_expiry_date DATE,
                        role TEXT DEFAULT 'user',
                        reg_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                        last_login DATETIME,
                        status TEXT DEFAULT 'active',
                        source TEXT DEFAULT 'website' CHECK(source IN ('website', 'app'))
                    )");
                    }
                } else {
                    $pdo->exec('PRAGMA foreign_keys = ON');
                }
            }
        } catch (PDOException $e) {
            error_log('[DB] Table initialization failed: ' . $e->getMessage());
        }
    }

    if ($pdo === null && $dbDriver !== 'mysql' && $dbDriver !== 'sqlite') {
        throw new RuntimeException(sprintf('Unsupported database driver "%s".', $dbDriver));
    }

    if ($pdo instanceof PDO && $dbDriver === 'mysql') {
        $pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
    }
} catch (Throwable $e) {
    error_log('[DB] Connection failed: ' . $e->getMessage());
    $pdo = null;
}
