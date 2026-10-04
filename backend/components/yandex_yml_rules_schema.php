<?php
declare(strict_types=1);
/**
 * Правила гибкой генерации YML (Яндекс.Бизнес): город вылета + страна + лимит туров.
 */

function yandex_yml_rules_ensure_table(PDO $pdo): void
{
    $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    if ($driver === 'mysql') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS yandex_yml_feed_rules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            source_departure_id INT NOT NULL,
            source_city VARCHAR(255) NOT NULL,
            target_country_id INT NOT NULL,
            target_country VARCHAR(255) NOT NULL,
            tour_limit INT NOT NULL DEFAULT 20,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_yml_rules_enabled (enabled),
            KEY idx_yml_rules_sort (sort_order, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS yandex_yml_feed_rules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            source_departure_id INTEGER NOT NULL,
            source_city TEXT NOT NULL,
            target_country_id INTEGER NOT NULL,
            target_country TEXT NOT NULL,
            tour_limit INTEGER NOT NULL DEFAULT 20,
            enabled INTEGER NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )");
    }
    yandex_yml_rules_ensure_hotel_stars_column($pdo);
}

/**
 * Опциональная фильтрация отелей по звёздности в фиде по правилам (3 / 4 / 5).
 */
function yandex_yml_rules_ensure_hotel_stars_column(PDO $pdo): void
{
    try {
        $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        if ($driver === 'mysql') {
            $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
            if ($dbName === '') {
                return;
            }
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $stmt->execute([$dbName, 'yandex_yml_feed_rules', 'hotel_stars_filter']);
            if ((int) $stmt->fetchColumn() === 0) {
                $pdo->exec('ALTER TABLE yandex_yml_feed_rules ADD COLUMN hotel_stars_filter TINYINT UNSIGNED NULL DEFAULT NULL COMMENT \'3|4|5 или NULL — без фильтра\'');
            }
        } else {
            $cols = $pdo->query('PRAGMA table_info(yandex_yml_feed_rules)');
            if ($cols === false) {
                return;
            }
            $have = [];
            while ($row = $cols->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['name'])) {
                    $have[$row['name']] = true;
                }
            }
            if (!isset($have['hotel_stars_filter'])) {
                $pdo->exec('ALTER TABLE yandex_yml_feed_rules ADD COLUMN hotel_stars_filter INTEGER NULL');
            }
        }
    } catch (Throwable $e) {
        error_log('[yandex_yml_rules_ensure_hotel_stars_column] ' . $e->getMessage());
    }
}

/**
 * Политика синка/выгрузки YML: если в таблице есть хотя бы одна строка правил — ориентируемся только на enabled-правила.
 * Если таблица пустая (ещё не настраивали админку) — legacy: весь список из country_promo_tourvisor_map.
 *
 * @return array{legacy_map: bool, country_ids: list<int>, limit_by_country: array<int, int>}
 */
function yandex_yml_feed_sync_policy(PDO $pdo): array
{
    yandex_yml_rules_ensure_table($pdo);
    try {
        $n = (int) $pdo->query('SELECT COUNT(*) FROM yandex_yml_feed_rules')->fetchColumn();
    } catch (Throwable $e) {
        error_log('[yandex_yml_feed_sync_policy] count: ' . $e->getMessage());

        return ['legacy_map' => true, 'country_ids' => [], 'limit_by_country' => []];
    }
    if ($n <= 0) {
        return ['legacy_map' => true, 'country_ids' => [], 'limit_by_country' => []];
    }

    $countryIds = [];
    $limitBy = [];
    try {
        $stmt = $pdo->query('SELECT target_country_id, tour_limit FROM yandex_yml_feed_rules WHERE enabled = 1 AND target_country_id > 0 ORDER BY sort_order ASC, id ASC');
        if ($stmt === false) {
            return ['legacy_map' => false, 'country_ids' => [], 'limit_by_country' => []];
        }
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cid = (int) ($row['target_country_id'] ?? 0);
            if ($cid <= 0) {
                continue;
            }
            $lim = (int) ($row['tour_limit'] ?? 20);
            $lim = max(1, min(500, $lim));
            if (!in_array($cid, $countryIds, true)) {
                $countryIds[] = $cid;
            }
            $limitBy[$cid] = max($limitBy[$cid] ?? 0, $lim);
        }
    } catch (Throwable $e) {
        error_log('[yandex_yml_feed_sync_policy] select: ' . $e->getMessage());

        return ['legacy_map' => false, 'country_ids' => [], 'limit_by_country' => []];
    }

    return ['legacy_map' => false, 'country_ids' => $countryIds, 'limit_by_country' => $limitBy];
}
