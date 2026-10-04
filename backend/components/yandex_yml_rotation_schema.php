<?php
declare(strict_types=1);
/**
 * Схема ротации YML: пакет стран, настройки, state, история tour_id.
 */

function yandex_feed_rotation_ensure_tables(PDO $pdo): void
{
    $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    if ($driver === 'mysql') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS yandex_feed_rotation_settings (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            frozen TINYINT(1) NOT NULL DEFAULT 0,
            source_departure_id INT NOT NULL DEFAULT 7,
            source_city VARCHAR(255) NOT NULL DEFAULT 'Самара',
            publish_samara TINYINT(1) NOT NULL DEFAULT 1,
            publish_moscow TINYINT(1) NOT NULL DEFAULT 1,
            countries_per_batch INT NOT NULL DEFAULT 3,
            tours_per_country INT NOT NULL DEFAULT 5,
            rotation_interval_days INT NOT NULL DEFAULT 7,
            max_country_replacements INT NOT NULL DEFAULT 3,
            min_offers_publish INT NOT NULL DEFAULT 8,
            history_exclude_batches INT NOT NULL DEFAULT 3,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS yandex_feed_country_pool (
            id INT AUTO_INCREMENT PRIMARY KEY,
            country_id INT NOT NULL,
            country_name VARCHAR(255) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_yf_pool_country (country_id),
            KEY idx_yf_pool_sort (sort_order, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS yandex_feed_rotation_state (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
            batch_index INT NOT NULL DEFAULT 0,
            last_rotated_at DATETIME NULL,
            last_offers_count INT NOT NULL DEFAULT 0,
            planned_countries_json TEXT NULL,
            actual_countries_json TEXT NULL,
            last_log TEXT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS yandex_feed_rotation_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            batch_index INT NOT NULL,
            country_id INT NOT NULL,
            tour_id VARCHAR(64) NOT NULL,
            rotated_at DATETIME NOT NULL,
            KEY idx_yf_rot_hist_batch (batch_index),
            KEY idx_yf_rot_hist_tour (tour_id),
            KEY idx_yf_rot_hist_at (rotated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS yandex_feed_country_pool (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            country_id INTEGER NOT NULL UNIQUE,
            country_name TEXT NOT NULL,
            sort_order INTEGER NOT NULL DEFAULT 0,
            enabled INTEGER NOT NULL DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS yandex_feed_rotation_settings (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            enabled INTEGER NOT NULL DEFAULT 0,
            frozen INTEGER NOT NULL DEFAULT 0,
            source_departure_id INTEGER NOT NULL DEFAULT 7,
            source_city TEXT NOT NULL DEFAULT 'Самара',
            publish_samara INTEGER NOT NULL DEFAULT 1,
            publish_moscow INTEGER NOT NULL DEFAULT 1,
            countries_per_batch INTEGER NOT NULL DEFAULT 3,
            tours_per_country INTEGER NOT NULL DEFAULT 5,
            rotation_interval_days INTEGER NOT NULL DEFAULT 7,
            max_country_replacements INTEGER NOT NULL DEFAULT 3,
            min_offers_publish INTEGER NOT NULL DEFAULT 8,
            history_exclude_batches INTEGER NOT NULL DEFAULT 3,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS yandex_feed_rotation_state (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            batch_index INTEGER NOT NULL DEFAULT 0,
            last_rotated_at TEXT NULL,
            last_offers_count INTEGER NOT NULL DEFAULT 0,
            planned_countries_json TEXT NULL,
            actual_countries_json TEXT NULL,
            last_log TEXT NULL,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS yandex_feed_rotation_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            batch_index INTEGER NOT NULL,
            country_id INTEGER NOT NULL,
            tour_id TEXT NOT NULL,
            rotated_at TEXT NOT NULL
        )");
    }

    yandex_feed_rotation_migrate_city_columns($pdo);
    yandex_feed_rotation_ensure_defaults($pdo);
}

function yandex_feed_rotation_migrate_city_columns(PDO $pdo): void
{
    $cols = ['publish_samara', 'publish_moscow'];
    foreach ($cols as $col) {
        try {
            $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
            if ($driver === 'mysql') {
                $pdo->exec("ALTER TABLE yandex_feed_rotation_settings ADD COLUMN {$col} TINYINT(1) NOT NULL DEFAULT 1");
            } else {
                $pdo->exec("ALTER TABLE yandex_feed_rotation_settings ADD COLUMN {$col} INTEGER NOT NULL DEFAULT 1");
            }
        } catch (Throwable $e) {
            // column already exists
        }
    }
    yandex_feed_rotation_migrate_search_param_columns($pdo);
}

/** Ночи / тип перелёта для поиска туров в фиде. */
function yandex_feed_rotation_migrate_search_param_columns(PDO $pdo): void
{
    $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    $defs = $driver === 'mysql'
        ? [
            'nights_from' => "INT NOT NULL DEFAULT 6",
            'nights_to' => "INT NOT NULL DEFAULT 14",
            'flight_mode' => "VARCHAR(16) NOT NULL DEFAULT 'any'",
        ]
        : [
            'nights_from' => "INTEGER NOT NULL DEFAULT 6",
            'nights_to' => "INTEGER NOT NULL DEFAULT 14",
            'flight_mode' => "TEXT NOT NULL DEFAULT 'any'",
        ];
    foreach ($defs as $col => $sqlType) {
        try {
            $pdo->exec("ALTER TABLE yandex_feed_rotation_settings ADD COLUMN {$col} {$sqlType}");
        } catch (Throwable $e) {
            // already exists
        }
    }
}

function yandex_feed_rotation_ensure_defaults(PDO $pdo): void
{
    try {
        $n = (int) $pdo->query('SELECT COUNT(*) FROM yandex_feed_rotation_settings')->fetchColumn();
        if ($n <= 0) {
            $stmt = $pdo->prepare('INSERT INTO yandex_feed_rotation_settings (id, enabled, frozen, source_departure_id, source_city, publish_samara, publish_moscow) VALUES (1, 0, 0, 7, ?, 1, 1)');
            $stmt->execute(['Самара']);
        }
        $ns = (int) $pdo->query('SELECT COUNT(*) FROM yandex_feed_rotation_state')->fetchColumn();
        if ($ns <= 0) {
            $pdo->exec('INSERT INTO yandex_feed_rotation_state (id, batch_index, last_offers_count) VALUES (1, 0, 0)');
        }
    } catch (Throwable $e) {
        error_log('[yandex_feed_rotation_ensure_defaults] ' . $e->getMessage());
    }
}

/** @return array<string, mixed> */
function yandex_feed_rotation_get_settings(PDO $pdo): array
{
    yandex_feed_rotation_ensure_tables($pdo);
    $stmt = $pdo->query('SELECT * FROM yandex_feed_rotation_settings WHERE id = 1 LIMIT 1');
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

    return is_array($row) ? $row : [];
}

/** @return array<string, mixed> */
function yandex_feed_rotation_get_state(PDO $pdo): array
{
    yandex_feed_rotation_ensure_tables($pdo);
    $stmt = $pdo->query('SELECT * FROM yandex_feed_rotation_state WHERE id = 1 LIMIT 1');
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

    return is_array($row) ? $row : [];
}

/** @return list<array<string, mixed>> */
function yandex_feed_rotation_get_pool(PDO $pdo, bool $enabledOnly = true): array
{
    yandex_feed_rotation_ensure_tables($pdo);
    $sql = 'SELECT * FROM yandex_feed_country_pool';
    if ($enabledOnly) {
        $sql .= ' WHERE enabled = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    $stmt = $pdo->query($sql);

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

/**
 * Города вылета для публичных фидов (без технических ID в UI).
 *
 * @return array<string, array{slug: string, label: string, departure_id: int}>
 */
function yandex_feed_rotation_city_catalog(): array
{
    return [
        'samara' => ['slug' => 'samara', 'label' => 'Самара', 'departure_id' => 7],
        'moscow' => ['slug' => 'moscow', 'label' => 'Москва', 'departure_id' => 1],
    ];
}

/**
 * @param array<string, mixed> $settings
 * @return list<array{slug: string, label: string, departure_id: int}>
 */
function yandex_feed_rotation_enabled_cities(array $settings): array
{
    $catalog = yandex_feed_rotation_city_catalog();
    $out = [];
    if (!isset($settings['publish_samara']) || !empty($settings['publish_samara'])) {
        $out[] = $catalog['samara'];
    }
    if (!isset($settings['publish_moscow']) || !empty($settings['publish_moscow'])) {
        $out[] = $catalog['moscow'];
    }
    if ($out === []) {
        $out[] = $catalog['samara'];
    }

    return $out;
}

/** @return list<array{id: int, name: string}> */
function yandex_feed_rotation_default_pool_seed(): array
{
    // Те же направления, что на странице акций / warm promo
    $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'popular_countries.php';
    if (is_file($file)) {
        $rows = require $file;
        if (is_array($rows) && $rows !== []) {
            $out = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $id = (int) ($row['id'] ?? 0);
                $name = trim((string) ($row['name'] ?? ''));
                if ($id > 0 && $name !== '') {
                    $out[] = ['id' => $id, 'name' => $name];
                }
            }
            if ($out !== []) {
                return $out;
            }
        }
    }

    return [
        ['id' => 4, 'name' => 'Турция'],
        ['id' => 1, 'name' => 'Египет'],
        ['id' => 2, 'name' => 'Таиланд'],
        ['id' => 9, 'name' => 'ОАЭ'],
    ];
}

function yandex_feed_rotation_seed_default_pool(PDO $pdo): int
{
    yandex_feed_rotation_ensure_tables($pdo);
    $added = 0;
    $sort = 0;
    foreach (yandex_feed_rotation_default_pool_seed() as $c) {
        $sort += 10;
        $cid = (int) $c['id'];
        $name = (string) $c['name'];
        if ($cid <= 0 || $name === '') {
            continue;
        }
        try {
            $stmt = $pdo->prepare('INSERT INTO yandex_feed_country_pool (country_id, country_name, sort_order, enabled) VALUES (?,?,?,1)
                ON DUPLICATE KEY UPDATE country_name = VALUES(country_name), sort_order = VALUES(sort_order), enabled = 1');
            if ($stmt === false) {
                continue;
            }
            $stmt->execute([$cid, $name, $sort]);
            $added++;
        } catch (Throwable $e) {
            try {
                $chk = $pdo->prepare('SELECT id FROM yandex_feed_country_pool WHERE country_id = ? LIMIT 1');
                $chk->execute([$cid]);
                if ($chk->fetchColumn()) {
                    $upd = $pdo->prepare('UPDATE yandex_feed_country_pool SET country_name=?, sort_order=?, enabled=1 WHERE country_id=?');
                    $upd->execute([$name, $sort, $cid]);
                } else {
                    $ins = $pdo->prepare('INSERT INTO yandex_feed_country_pool (country_id, country_name, sort_order, enabled) VALUES (?,?,?,1)');
                    $ins->execute([$cid, $name, $sort]);
                }
                $added++;
            } catch (Throwable $e2) {
                error_log('[yandex_feed_rotation_seed] ' . $e2->getMessage());
            }
        }
    }

    return $added;
}

/**
 * Env: пусто/не задано = разрешено (управление из админки).
 * Явный 0/false/off = жёстко выключить на сервере.
 */
function yandex_feed_rotation_env_enabled(): bool
{
    $raw = getenv('YML_FEED_ROTATION_ENABLED');
    if ($raw === false || $raw === null || $raw === '') {
        $raw = $_ENV['YML_FEED_ROTATION_ENABLED'] ?? '';
    }
    if ($raw === '' || $raw === null) {
        return true;
    }

    return filter_var((string) $raw, FILTER_VALIDATE_BOOLEAN);
}

function yandex_feed_rotation_is_active(PDO $pdo): bool
{
    if (!yandex_feed_rotation_env_enabled()) {
        return false;
    }
    $settings = yandex_feed_rotation_get_settings($pdo);

    return !empty($settings['enabled']);
}
