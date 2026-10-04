<?php
/**
 * Seed туров для VIP отелей. Запуск: php backend/scripts/seed_vip_hotel_tours.php
 */
declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
require_once dirname(__DIR__) . '/config/config.php';

if (!$pdo) {
    echo "Ошибка: нет подключения к БД\n";
    exit(1);
}

$migrationPath = $projectRoot . '/database/migrations/add_vip_hotel_tours.sql';
if (file_exists($migrationPath)) {
    $sql = file_get_contents($migrationPath);
    $pdo->exec($sql);
    echo "Таблица vip_hotel_tours создана.\n";
}

$count = $pdo->query("SELECT COUNT(*) FROM vip_hotel_tours")->fetchColumn();
if ($count > 0) {
    echo "В vip_hotel_tours уже есть записи ({$count}). Выход.\n";
    exit(0);
}

$hotels = $pdo->query("SELECT id, slug, city FROM vip_hotels WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
if (empty($hotels)) {
    echo "Нет VIP отелей. Сначала запустите seed_vip_hotels.php\n";
    exit(1);
}

$departures = ['Москва', 'Санкт-Петербург', 'Екатеринбург', 'Казань', 'Краснодар'];
$meals = ['BB', 'HB', 'AI', 'UAI', null];

$stmt = $pdo->prepare("
    INSERT INTO vip_hotel_tours (vip_hotel_id, departure_date, nights, price, meal, departure_city, adults, is_active)
    VALUES (?, ?, ?, ?, ?, ?, 2, 1)
");

$baseDate = strtotime('+14 days');
$inserted = 0;
foreach ($hotels as $h) {
    for ($i = 0; $i < 5; $i++) {
        $date = date('Y-m-d', $baseDate + $i * 7 * 86400);
        $nights = [7, 7, 10, 10, 14][$i % 5];
        $price = 80000 + rand(20000, 150000) + ($nights - 7) * 5000;
        $meal = $meals[$i % count($meals)];
        $dep = $departures[$i % count($departures)];
        $stmt->execute([$h['id'], $date, $nights, $price, $meal, $dep]);
        $inserted++;
    }
}

echo "Добавлено туров: $inserted\n";
echo "Готово.\n";
