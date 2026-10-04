<?php
/**
 * API для работы с VIP отелями Турции
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

session_start();

// Функция для генерации slug из названия
function generateSlug($text) {
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    $text = trim($text, '-');
    return $text;
}

$method = $_SERVER['REQUEST_METHOD'];

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

require_once __DIR__ . '/../vip_hotels_schema.php';
require_once __DIR__ . '/../vip_hotel_display_defaults.php';
try {
    vip_hotels_ensure_table($pdo);
} catch (Throwable $e) {
    error_log('[vip-hotels components/api] ensure_table: ' . $e->getMessage());
}

switch ($method) {
    case 'GET':
        $action = $_GET['action'] ?? 'list';
        
        if ($action === 'detail') {
            // Получение деталей конкретного отеля
            $slug = $_GET['slug'] ?? null;
            if (!$slug) {
                http_response_code(400);
                echo json_encode(['error' => 'Hotel slug is required']);
                exit;
            }
            
            try {
                $stmt = $pdo->prepare('
                    SELECT * FROM vip_hotels 
                    WHERE slug = ? AND is_active = 1
                ');
                $stmt->execute([$slug]);
                $hotel = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$hotel) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Hotel not found']);
                    exit;
                }
                
                // Декодируем JSON поля
                if ($hotel['images']) {
                    $hotel['images'] = json_decode($hotel['images'], true) ?: [];
                } else {
                    $hotel['images'] = [];
                }
                
                if ($hotel['features']) {
                    $hotel['features'] = json_decode($hotel['features'], true) ?: [];
                } else {
                    $hotel['features'] = [];
                }
                
                if ($hotel['detailed_info']) {
                    $hotel['detailed_info'] = json_decode($hotel['detailed_info'], true) ?: [];
                } else {
                    $hotel['detailed_info'] = [];
                }

                $hotel = vip_hotels_enrich_hotel_array($hotel);
                
                echo json_encode(['hotel' => $hotel], JSON_UNESCAPED_UNICODE);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to fetch hotel: ' . $e->getMessage()]);
            }
        } else {
            // Получение списка отелей
            $city = isset($_GET['city']) ? trim((string) $_GET['city']) : '';
            
            try {
                $query = 'SELECT id, name, slug, city, rating, description, images, display_order 
                          FROM vip_hotels 
                          WHERE is_active = 1';
                $params = [];
                [$citySql, $cityParams] = vip_hotels_city_filter_clause($city);
                $query .= $citySql;
                $params = array_merge($params, $cityParams);
                
                $query .= ' ORDER BY city ASC, display_order ASC, id ASC';
                
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $hotels = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Декодируем JSON поля
                foreach ($hotels as &$hotel) {
                    if ($hotel['images']) {
                        $hotel['images'] = json_decode($hotel['images'], true) ?: [];
                    } else {
                        $hotel['images'] = [];
                    }
                    $hotel = vip_hotels_enrich_hotel_array($hotel);
                    // Берем первое изображение для превью
                    $hotel['image'] = !empty($hotel['images'][0]) ? $hotel['images'][0] : null;
                }
                unset($hotel);
                
                // Группируем по городам
                $groupedHotels = [];
                foreach ($hotels as $hotel) {
                    $cityName = $hotel['city'];
                    if (!isset($groupedHotels[$cityName])) {
                        $groupedHotels[$cityName] = [];
                    }
                    $groupedHotels[$cityName][] = $hotel;
                }
                
                echo json_encode([
                    'hotels' => $hotels,
                    'grouped' => $groupedHotels
                ], JSON_UNESCAPED_UNICODE);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to fetch hotels: ' . $e->getMessage()]);
            }
        }
        break;
        
    case 'POST':
        // Сохранение/обновление отеля (только для админов)
        if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? null) !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['name']) || !isset($data['city'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }
        
        try {
            $name = $data['name'];
            $slug = $data['slug'] ?? generateSlug($name);
            $city = $data['city'];
            $rating = $data['rating'] ?? '5*';
            $description = $data['description'] ?? null;
            $bio = $data['bio'] ?? null;
            $cuisine = $data['cuisine'] ?? null;
            $mealPlan = $data['meal_plan'] ?? null;
            $images = isset($data['images']) ? json_encode($data['images'], JSON_UNESCAPED_UNICODE) : null;
            $features = isset($data['features']) ? json_encode($data['features'], JSON_UNESCAPED_UNICODE) : null;
            $location = $data['location'] ?? null;
            $beachType = $data['beach_type'] ?? null;
            $distanceToAirport = $data['distance_to_airport'] ?? null;
            $checkInTime = $data['check_in_time'] ?? null;
            $checkOutTime = $data['check_out_time'] ?? null;
            $detailedInfo = isset($data['detailed_info']) ? json_encode($data['detailed_info'], JSON_UNESCAPED_UNICODE) : null;
            $displayOrder = $data['display_order'] ?? 0;
            $isActive = $data['is_active'] ?? 1;
            $updatedBy = $_SESSION['user_id'];
            
            if (isset($data['id']) && $data['id']) {
                // Обновление существующего отеля
                $stmt = $pdo->prepare('
                    UPDATE vip_hotels 
                    SET name = ?, slug = ?, city = ?, rating = ?, description = ?, bio = ?,
                        cuisine = ?, meal_plan = ?, images = ?, features = ?, location = ?,
                        beach_type = ?, distance_to_airport = ?, check_in_time = ?, check_out_time = ?,
                        detailed_info = ?, display_order = ?, is_active = ?,
                        updated_at = CURRENT_TIMESTAMP, updated_by = ?
                    WHERE id = ?
                ');
                $stmt->execute([
                    $name, $slug, $city, $rating, $description, $bio,
                    $cuisine, $mealPlan, $images, $features, $location,
                    $beachType, $distanceToAirport, $checkInTime, $checkOutTime,
                    $detailedInfo, $displayOrder, $isActive, $updatedBy, $data['id']
                ]);
                $hotelId = $data['id'];
            } else {
                // Создание нового отеля
                $stmt = $pdo->prepare('
                    INSERT INTO vip_hotels 
                    (name, slug, city, rating, description, bio, cuisine, meal_plan, images, features,
                     location, beach_type, distance_to_airport, check_in_time, check_out_time,
                     detailed_info, display_order, is_active, updated_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([
                    $name, $slug, $city, $rating, $description, $bio,
                    $cuisine, $mealPlan, $images, $features, $location,
                    $beachType, $distanceToAirport, $checkInTime, $checkOutTime,
                    $detailedInfo, $displayOrder, $isActive, $updatedBy
                ]);
                $hotelId = $pdo->lastInsertId();
            }
            
            echo json_encode(['success' => true, 'id' => $hotelId], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save hotel: ' . $e->getMessage()]);
        }
        break;
        
    case 'DELETE':
        // Удаление отеля (только для админов)
        if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? null) !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit;
        }
        
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Hotel ID is required']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare('DELETE FROM vip_hotels WHERE id = ?');
            $stmt->execute([$id]);
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete hotel: ' . $e->getMessage()]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

