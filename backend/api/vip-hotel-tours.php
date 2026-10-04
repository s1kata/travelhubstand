<?php
/**
 * API туров для VIP отелей (из базы).
 * Параметры: city, hotel_slug, date_from, date_to, nights_from, nights_to, departure_city
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed', 'data' => []]);
    exit;
}

$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';

if ($action === 'departures') {
    try {
        $rows = $pdo->query("SELECT DISTINCT departure_city FROM vip_hotel_tours WHERE is_active = 1 AND departure_city != '' ORDER BY departure_city")->fetchAll(PDO::FETCH_COLUMN);
        $cities = array_values(array_filter($rows));
        if (empty($cities)) {
            $cities = ['Москва', 'Санкт-Петербург', 'Екатеринбург', 'Казань', 'Краснодар'];
        }
        $cities = array_map(fn($c) => ['id' => $c, 'name' => $c], $cities);
        echo json_encode(['success' => true, 'data' => $cities], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        echo json_encode(['success' => true, 'data' => [['id' => 'Москва', 'name' => 'Москва'], ['id' => 'Санкт-Петербург', 'name' => 'Санкт-Петербург']]], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$city = isset($_GET['city']) ? trim((string)$_GET['city']) : null;
$hotelSlug = isset($_GET['hotel_slug']) ? trim((string)$_GET['hotel_slug']) : null;
$dateFrom = isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : null;
$dateTo = isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : null;
$nightsFrom = isset($_GET['nights_from']) ? (int)$_GET['nights_from'] : null;
$nightsTo = isset($_GET['nights_to']) ? (int)$_GET['nights_to'] : null;
$departureCity = isset($_GET['departure_city']) ? trim((string)$_GET['departure_city']) : null;

try {
    $sql = "
        SELECT t.id, t.vip_hotel_id, t.departure_date, t.nights, t.price, t.currency, t.meal, t.departure_city, t.booking_link,
               h.name AS hotel_name, h.slug AS hotel_slug, h.city AS hotel_city, h.rating, h.images
        FROM vip_hotel_tours t
        INNER JOIN vip_hotels h ON h.id = t.vip_hotel_id AND h.is_active = 1
        WHERE t.is_active = 1
    ";
    $params = [];

    if ($city) {
        $sql .= " AND h.city = ?";
        $params[] = $city;
    }
    if ($hotelSlug) {
        $sql .= " AND h.slug = ?";
        $params[] = $hotelSlug;
    }
    if ($dateFrom) {
        $sql .= " AND t.departure_date >= ?";
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $sql .= " AND t.departure_date <= ?";
        $params[] = $dateTo;
    }
    if ($nightsFrom !== null && $nightsFrom > 0) {
        $sql .= " AND t.nights >= ?";
        $params[] = $nightsFrom;
    }
    if ($nightsTo !== null && $nightsTo > 0) {
        $sql .= " AND t.nights <= ?";
        $params[] = $nightsTo;
    }
    if ($departureCity) {
        $sql .= " AND t.departure_city = ?";
        $params[] = $departureCity;
    }

    $sql .= " ORDER BY t.departure_date ASC, t.price ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $grouped = [];
    foreach ($rows as $row) {
        $hid = (int)$row['vip_hotel_id'];
        if (!isset($grouped[$hid])) {
            $images = $row['images'] ? (json_decode($row['images'], true) ?: []) : [];
            $grouped[$hid] = [
                'id' => $row['vip_hotel_id'],
                'name' => $row['hotel_name'],
                'slug' => $row['hotel_slug'],
                'city' => $row['hotel_city'],
                'rating' => $row['rating'],
                'image' => $images[0] ?? null,
                'tours' => [],
            ];
        }
        $grouped[$hid]['tours'][] = [
            'id' => (int)$row['id'],
            'departure_date' => $row['departure_date'],
            'nights' => (int)$row['nights'],
            'price' => (float)$row['price'],
            'currency' => $row['currency'] ?? 'RUB',
            'meal' => $row['meal'],
            'departure_city' => $row['departure_city'],
            'booking_link' => $row['booking_link'],
        ];
    }

    $data = array_values($grouped);
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('[vip-hotel-tours] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error', 'data' => []], JSON_UNESCAPED_UNICODE);
}
