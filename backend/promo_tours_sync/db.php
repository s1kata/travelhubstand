<?php
/**
 * PDO для таблицы promo_tours (те же учётные данные, что и у основного сайта).
 */
declare(strict_types=1);

/** @var array<string, mixed> $config */
$config = require __DIR__ . '/config.php';
$db = $config['db'];
$driver = $db['driver'];

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

if ($driver === 'mysql') {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $db['host'],
        $db['port'],
        $db['database'],
        $db['charset']
    );
    $pdo = new PDO($dsn, (string)$db['username'], (string)$db['password'], $options);
    $pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
} elseif ($driver === 'sqlite') {
    $path = (string)$db['sqlite_path'];
    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create SQLite directory: ' . $dir);
        }
    }
    $pdo = new PDO('sqlite:' . $path, null, null, $options);
    $pdo->exec('PRAGMA foreign_keys = ON');
} else {
    throw new RuntimeException(sprintf('Неподдерживаемый DB_DRIVER для promo_tours: %s', $driver));
}

return $pdo;
