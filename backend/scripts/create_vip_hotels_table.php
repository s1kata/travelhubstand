<?php
/**
 * Создание таблицы vip_hotels, если её нет.
 * Запуск на сервере: php backend/scripts/create_vip_hotels_table.php
 * После этого при необходимости: php backend/scripts/seed_vip_hotels.php
 */
$projectRoot = dirname(__DIR__, 2);
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/components/vip_hotels_schema.php';

if (!$pdo) {
    echo "Ошибка: нет подключения к БД. Проверьте .env и config.php\n";
    exit(1);
}

try {
    vip_hotels_ensure_table($pdo);
    echo "Таблица vip_hotels создана или уже существует.\n";
    echo "Дальше при пустой таблице: php backend/scripts/seed_vip_hotels.php\n";
} catch (PDOException $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}
