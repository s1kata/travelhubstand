<?php
/**
 * API для работы с эксклюзивными турами
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

session_start();

$method = $_SERVER['REQUEST_METHOD'];

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

switch ($method) {
    case 'GET':
        // Получение всех активных эксклюзивных туров
        try {
            $stmt = $pdo->query('
                SELECT id, title, subtitle, description, country_slug, image_url, blocks, display_order
                FROM exclusive_tours 
                WHERE is_active = 1 
                ORDER BY display_order ASC, id ASC
            ');
            $tours = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Декодируем JSON поля
            foreach ($tours as &$tour) {
                if ($tour['blocks']) {
                    $tour['blocks'] = json_decode($tour['blocks'], true);
                } else {
                    $tour['blocks'] = [];
                }
            }
            
            echo json_encode(['tours' => $tours], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch tours: ' . $e->getMessage()]);
        }
        break;
        
    case 'POST':
        // Сохранение/обновление эксклюзивного тура (только для админов)
        if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? null) !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['title']) || !isset($data['country_slug'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }
        
        try {
            $title = $data['title'];
            $subtitle = $data['subtitle'] ?? null;
            $description = $data['description'] ?? null;
            $countrySlug = $data['country_slug'];
            $imageUrl = $data['image_url'] ?? null;
            $blocks = isset($data['blocks']) ? json_encode($data['blocks'], JSON_UNESCAPED_UNICODE) : null;
            $displayOrder = $data['display_order'] ?? 0;
            $isActive = $data['is_active'] ?? 1;
            $updatedBy = $_SESSION['user_id'];
            
            if (isset($data['id']) && $data['id']) {
                // Обновление существующего тура
                $stmt = $pdo->prepare('
                    UPDATE exclusive_tours 
                    SET title = ?, subtitle = ?, description = ?, country_slug = ?, 
                        image_url = ?, blocks = ?, display_order = ?, is_active = ?,
                        updated_at = CURRENT_TIMESTAMP, updated_by = ?
                    WHERE id = ?
                ');
                $stmt->execute([$title, $subtitle, $description, $countrySlug, $imageUrl, 
                               $blocks, $displayOrder, $isActive, $updatedBy, $data['id']]);
                $tourId = $data['id'];
            } else {
                // Создание нового тура
                $stmt = $pdo->prepare('
                    INSERT INTO exclusive_tours 
                    (title, subtitle, description, country_slug, image_url, blocks, display_order, is_active, updated_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([$title, $subtitle, $description, $countrySlug, $imageUrl, 
                               $blocks, $displayOrder, $isActive, $updatedBy]);
                $tourId = $pdo->lastInsertId();
            }
            
            echo json_encode(['success' => true, 'id' => $tourId], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save tour: ' . $e->getMessage()]);
        }
        break;
        
    case 'DELETE':
        // Удаление тура (только для админов)
        if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? null) !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing tour ID']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare('DELETE FROM exclusive_tours WHERE id = ?');
            $stmt->execute([$data['id']]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete tour: ' . $e->getMessage()]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

























