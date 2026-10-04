<?php
/**
 * Seed VIP отелей Турции (только пустая таблица). Полные поля — из vip_hotel_display_defaults.php.
 * Запуск: php backend/scripts/seed_vip_hotels.php
 * Уже есть записи — выход; для обновления контента: vip_hotels_apply_display_defaults.php
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/components/vip_hotels_schema.php';
require_once dirname(__DIR__) . '/components/vip_hotel_display_defaults.php';

if (!$pdo) {
    echo "Ошибка: нет подключения к БД\n";
    exit(1);
}

try {
    vip_hotels_ensure_table($pdo);
} catch (Throwable $e) {
    echo 'ensure_table: ' . $e->getMessage() . "\n";
    exit(1);
}

$count = (int) $pdo->query('SELECT COUNT(*) FROM vip_hotels')->fetchColumn();
if ($count > 0) {
    echo "В таблице vip_hotels уже есть записи ({$count}). Для обновления фото/текстов запустите vip_hotels_apply_display_defaults.php\n";
    exit(0);
}

$defaults = vip_hotel_display_defaults_by_slug();
$stmt = $pdo->prepare('
    INSERT INTO vip_hotels (
        name, slug, city, rating, description, bio, cuisine, meal_plan, location, beach_type,
        distance_to_airport, check_in_time, check_out_time, features, images, detailed_info,
        display_order, is_active
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
');

$order = 0;
foreach ($defaults as $slug => $row) {
    $imagesJson = json_encode($row['images'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $featuresJson = json_encode($row['features'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $detailedJson = json_encode($row['detailed_info'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $stmt->execute([
        (string) ($row['name'] ?? ''),
        (string) ($row['slug'] ?? $slug),
        (string) ($row['city'] ?? ''),
        (string) ($row['rating'] ?? '5*'),
        (string) ($row['description'] ?? ''),
        (string) ($row['bio'] ?? ''),
        (string) ($row['cuisine'] ?? ''),
        (string) ($row['meal_plan'] ?? ''),
        (string) ($row['location'] ?? ''),
        (string) ($row['beach_type'] ?? ''),
        (string) ($row['distance_to_airport'] ?? ''),
        (string) ($row['check_in_time'] ?? ''),
        (string) ($row['check_out_time'] ?? ''),
        $featuresJson,
        $imagesJson,
        $detailedJson,
        $order++,
    ]);
}

echo 'Добавлено отелей: ' . count($defaults) . "\nГотово.\n";
