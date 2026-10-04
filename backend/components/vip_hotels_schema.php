<?php
declare(strict_types=1);
/**
 * Таблица vip_hotels и фильтр по городу (EN/RU) для публичного API.
 */
function vip_hotels_ensure_table(PDO $pdo): void
{
    $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

    if ($driver === 'mysql') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS vip_hotels (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL UNIQUE,
        city VARCHAR(100) NOT NULL,
        rating VARCHAR(20) DEFAULT '5*',
        description TEXT,
        bio TEXT,
        cuisine VARCHAR(255),
        meal_plan VARCHAR(255),
        location VARCHAR(255),
        beach_type VARCHAR(255),
        distance_to_airport VARCHAR(100),
        check_in_time VARCHAR(50),
        check_out_time VARCHAR(50),
        features TEXT,
        images TEXT,
        detailed_info TEXT,
        display_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL,
        updated_by INT,
        INDEX idx_active (is_active),
        INDEX idx_city (city),
        INDEX idx_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Tourvisor hotel id для точного поиска туров (hotelIds)
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM vip_hotels LIKE 'tourvisor_hotel_id'")->fetchAll();
            if (!$cols) {
                $pdo->exec('ALTER TABLE vip_hotels ADD COLUMN tourvisor_hotel_id INT NULL DEFAULT NULL AFTER slug');
            }
        } catch (Throwable $e) {
            // ignore
        }

        return;
    }

    $pdo->exec('CREATE TABLE IF NOT EXISTS vip_hotels (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        city TEXT NOT NULL,
        rating TEXT DEFAULT \'5*\',
        description TEXT,
        bio TEXT,
        cuisine TEXT,
        meal_plan TEXT,
        location TEXT,
        beach_type TEXT,
        distance_to_airport TEXT,
        check_in_time TEXT,
        check_out_time TEXT,
        features TEXT,
        images TEXT,
        detailed_info TEXT,
        display_order INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        created_at TEXT,
        updated_at TEXT,
        updated_by INTEGER
    )');
    try {
        $pdo->exec('ALTER TABLE vip_hotels ADD COLUMN tourvisor_hotel_id INTEGER NULL');
    } catch (Throwable $e) {
        // column may already exist
    }
}

/**
 * @return array{0: string, 1: list<string>}
 */
function vip_hotels_city_filter_clause(string $cityParam): array
{
    $cityParam = trim($cityParam);
    if ($cityParam === '') {
        return ['', []];
    }

    $groups = [
        ['Antalya', 'Анталья', 'ANTALYA', 'анталья'],
        ['Belek', 'Белек', 'BELEK', 'белек'],
        ['Kemer', 'Кемер', 'KEMER', 'кемер'],
    ];

    $norm = mb_strtolower($cityParam, 'UTF-8');
    foreach ($groups as $variants) {
        foreach ($variants as $v) {
            if (mb_strtolower(trim($v), 'UTF-8') === $norm) {
                $uniq = [];
                foreach ($variants as $x) {
                    $t = trim($x);
                    if ($t !== '' && !in_array($t, $uniq, true)) {
                        $uniq[] = $t;
                    }
                }
                $ph = implode(',', array_fill(0, count($uniq), '?'));

                return [' AND city IN (' . $ph . ') ', $uniq];
            }
        }
    }

    return [' AND city = ? ', [$cityParam]];
}

/**
 * Ключ курорта для regionIds в Tourvisor (Antalya/Belek/Kemer) из любого написания города в БД.
 */
function vip_hotels_canonical_resort_key(string $city): ?string
{
    $n = mb_strtolower(trim($city), 'UTF-8');
    $map = [
        'Antalya' => ['antalya', 'анталья'],
        'Belek' => ['belek', 'белек'],
        'Kemer' => ['kemer', 'кемер'],
    ];
    foreach ($map as $key => $variants) {
        foreach ($variants as $v) {
            if ($n === mb_strtolower($v, 'UTF-8')) {
                return $key;
            }
        }
    }

    return null;
}
