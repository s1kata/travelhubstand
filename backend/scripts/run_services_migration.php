<?php
/**
 * Скрипт миграции: создание таблицы services
 * Запуск: php backend/scripts/run_services_migration.php
 * Или через браузер: /backend/scripts/run_services_migration.php (добавьте проверку авторизации при необходимости)
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

if (!$pdo) {
    die("Ошибка: нет подключения к БД.\n");
}

$driverName = strtolower((string)($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) ?? ''));
$migrationFile = ($driverName === 'mysql')
    ? __DIR__ . '/../../database/migrations/add_services_table_mysql.sql'
    : __DIR__ . '/../../database/migrations/add_services_table_sqlite.sql';

if (!file_exists($migrationFile)) {
    die("Файл миграции не найден: $migrationFile\n");
}

$sql = file_get_contents($migrationFile);
$statements = array_filter(array_map('trim', explode(';', $sql)), fn($s) => !empty($s) && !preg_match('/^--/', $s));

try {
    foreach ($statements as $stmt) {
        $pdo->exec($stmt);
    }
    echo "Миграция services выполнена успешно.\n";
} catch (PDOException $e) {
    die("Ошибка миграции: " . $e->getMessage() . "\n");
}
