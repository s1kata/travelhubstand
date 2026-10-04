<?php
/**
 * Обновляет тексты, JSON-поля и URL фото для VIP-отелей по slug (уже существующие строки в vip_hotels).
 * Запуск на проде: /usr/bin/php8.1 backend/scripts/vip_hotels_apply_display_defaults.php
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

$defaults = vip_hotel_display_defaults_by_slug();
$driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
$nowExpr = $driver === 'mysql' ? 'CURRENT_TIMESTAMP' : null;
$updatedAt = date('Y-m-d H:i:s');

$sql = 'UPDATE vip_hotels SET
    name = ?, city = ?, rating = ?, description = ?, bio = ?, cuisine = ?, meal_plan = ?,
    location = ?, beach_type = ?, distance_to_airport = ?, check_in_time = ?, check_out_time = ?,
    features = ?, images = ?, detailed_info = ?';

if ($driver === 'mysql') {
    $sql .= ', updated_at = ' . $nowExpr;
} else {
    $sql .= ', updated_at = ?';
}
$sql .= ' WHERE slug = ?';

$stmt = $pdo->prepare($sql);

$updated = 0;
$missing = [];

foreach ($defaults as $slug => $row) {
    $check = $pdo->prepare('SELECT id FROM vip_hotels WHERE slug = ? LIMIT 1');
    $check->execute([$slug]);
    if (!$check->fetchColumn()) {
        $missing[] = $slug;
        continue;
    }

    $imagesJson = json_encode($row['images'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $featuresJson = json_encode($row['features'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $detailedJson = json_encode($row['detailed_info'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $params = [
        (string) ($row['name'] ?? ''),
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
    ];
    if ($driver !== 'mysql') {
        $params[] = $updatedAt;
    }
    $params[] = $slug;

    $stmt->execute($params);
    $updated += $stmt->rowCount();
}

echo "Обновлено строк (rowCount суммарно): {$updated}\n";
if ($missing !== []) {
    echo 'Нет в БД (пропущены): ' . implode(', ', $missing) . "\n";
}
echo "Готово.\n";
