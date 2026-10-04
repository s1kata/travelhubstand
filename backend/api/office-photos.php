<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? null) !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Доступ запрещен']);
    exit;
}

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка подключения к базе данных']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$officeId = isset($_GET['office_id']) ? intval($_GET['office_id']) : null;

try {
    switch ($method) {
        case 'GET':
            // Получить фото для офиса
            if ($officeId) {
                $stmt = $pdo->prepare("
                    SELECT id, office_id, image_url, title, description, sort_order, created_at
                    FROM office_photos
                    WHERE office_id = ?
                    ORDER BY sort_order ASC, created_at DESC
                ");
                $stmt->execute([$officeId]);
                $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'photos' => $photos]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Не указан ID офиса']);
            }
            break;

        case 'POST':
            // Добавить фото к офису
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input || !isset($input['office_id']) || !isset($input['image_url'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Неверные данные']);
                break;
            }

            $officeId = intval($input['office_id']);
            $imageUrl = trim($input['image_url']);
            $title = trim($input['title'] ?? '');
            $description = trim($input['description'] ?? '');
            $sortOrder = intval($input['sort_order'] ?? 0);

            $stmt = $pdo->prepare("
                INSERT INTO office_photos (office_id, image_url, title, description, sort_order)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$officeId, $imageUrl, $title, $description, $sortOrder]);

            $newId = $pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'message' => 'Фото добавлено в галерею офиса',
                'photo_id' => $newId
            ]);
            break;

        case 'DELETE':
            // Удалить фото из офиса
            $photoId = isset($_GET['photo_id']) ? intval($_GET['photo_id']) : null;

            if (!$photoId) {
                http_response_code(400);
                echo json_encode(['error' => 'Не указан ID фото']);
                break;
            }

            $stmt = $pdo->prepare("DELETE FROM office_photos WHERE id = ?");
            $stmt->execute([$photoId]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Фото удалено из галереи']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Фото не найдено']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Метод не поддерживается']);
            break;
    }
} catch (PDOException $e) {
    error_log('[Office Photos API] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка сервера']);
}
?>