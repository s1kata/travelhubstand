<?php
/**
 * Миграция: добавить колонку is_manager в таблицу users.
 * Запустить на сервере (travelhub63.ru):
 *   1) Через браузер: https://travelhub63.ru/backend/scripts/add_is_manager_column.php
 *   2) По SSH: php backend/scripts/add_is_manager_column.php
 *
 * Локально скрипт подключается к локальной БД; для продакшена — выполните на сервере.
 */
require_once __DIR__ . '/../config/config.php';

$isWeb = (php_sapi_name() !== 'cli');
function out($msg, $isWeb) {
    echo $isWeb ? '<p>' . htmlspecialchars($msg) . '</p>' : $msg . "\n";
}

$drv = $pdo ? strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) : 'sqlite';

if ($isWeb) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Миграция is_manager</title></head><body><h1>Миграция БД: is_manager</h1>';
}

if ($pdo) {
    try {
        if ($drv === 'mysql') {
            $cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_manager'")->fetchAll();
            if (empty($cols)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN is_manager TINYINT(1) DEFAULT 0 NOT NULL");
                out("MySQL: добавлена колонка is_manager", $isWeb);
            } else {
                out("MySQL: колонка is_manager уже существует", $isWeb);
            }
        } else {
            $info = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
            $has = false;
            foreach ($info as $col) {
                if ($col['name'] === 'is_manager') { $has = true; break; }
            }
            if (!$has) {
                $pdo->exec("ALTER TABLE users ADD COLUMN is_manager INTEGER DEFAULT 0");
                out("SQLite: добавлена колонка is_manager", $isWeb);
            } else {
                out("SQLite: колонка is_manager уже существует", $isWeb);
            }
        }
    } catch (PDOException $e) {
        out("Ошибка: " . $e->getMessage(), $isWeb);
        if ($isWeb) echo '</body></html>';
        exit(1);
    }
} else {
    out("Нет подключения к БД. Проверьте .env (DB_HOST, DB_NAME, DB_USER, DB_PASS).", $isWeb);
    if ($isWeb) echo '</body></html>';
    exit(1);
}

out("OK — миграция завершена.", $isWeb);
if ($isWeb) echo '<p><a href="/">← На главную</a></p></body></html>';
