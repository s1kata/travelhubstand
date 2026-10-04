<?php
declare(strict_types=1);
/**
 * DDL для yandex_feed_offers — без зависимостей от Tourvisor (админка, generate_yml).
 */

function yandex_feed_ensure_table(PDO $pdo): void
{
    $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    if ($driver === 'mysql') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS yandex_feed_offers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tourvisor_tour_id VARCHAR(64) NOT NULL,
            country_id INT NOT NULL,
            country_name VARCHAR(255) NOT NULL,
            title VARCHAR(500) NOT NULL,
            description TEXT,
            picture_url VARCHAR(2000) NOT NULL,
            price DECIMAL(12,2) NOT NULL,
            offer_url VARCHAR(2000) NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            synced_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_yandex_tour (tourvisor_tour_id),
            KEY idx_yandex_country (country_id),
            KEY idx_yandex_enabled (enabled)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS yandex_feed_offers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tourvisor_tour_id TEXT NOT NULL UNIQUE,
            country_id INTEGER NOT NULL,
            country_name TEXT NOT NULL,
            title TEXT NOT NULL,
            description TEXT,
            picture_url TEXT NOT NULL,
            price REAL NOT NULL,
            offer_url TEXT NOT NULL,
            enabled INTEGER NOT NULL DEFAULT 1,
            synced_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");
        try {
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_yandex_country ON yandex_feed_offers(country_id)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_yandex_enabled ON yandex_feed_offers(enabled)');
        } catch (PDOException $e) {
            // Старый SQLite: индексы опциональны
        }
    }

    yandex_feed_ensure_suppression_table($pdo);
}

/**
 * Туры, удалённые из yandex_feed_offers кнопкой «выключенные из БД» — не подставлять снова из Tourvisor.
 */
function yandex_feed_ensure_suppression_table(PDO $pdo): void
{
    $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    if ($driver === 'mysql') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS yandex_feed_suppressed_tour_ids (
            tourvisor_tour_id VARCHAR(64) NOT NULL PRIMARY KEY,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS yandex_feed_suppressed_tour_ids (
            tourvisor_tour_id TEXT NOT NULL PRIMARY KEY,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");
    }
}
