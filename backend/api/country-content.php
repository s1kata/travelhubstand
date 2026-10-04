<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/country_content_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();

// Проверка авторизации для POST/PUT запросов
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT']) && (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? null) !== 'admin')) {
    http_response_code(403);
    echo json_encode(['error' => 'Доступ запрещен. Требуется роль администратора.']);
    exit;
}

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка подключения к базе данных']);
    exit;
}

// Создаем таблицу, если её нет
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS country_content (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        country_slug TEXT NOT NULL UNIQUE,
        bio TEXT,
        highlights TEXT,
        useful_info TEXT,
        detailed_info TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_by INTEGER,
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_country_content_slug ON country_content(country_slug)");
} catch (PDOException $e) {
    error_log('[country-content] Table creation failed: ' . $e->getMessage());
}

$method = $_SERVER['REQUEST_METHOD'];
$countrySlug = strtolower(trim((string) ($_GET['slug'] ?? '')));

if ($method === 'GET') {
    // Получение данных для страны
    if (empty($countrySlug)) {
        http_response_code(400);
        echo json_encode(['error' => 'Не указан slug страны']);
        exit;
    }

    try {
        $stmt = $pdo->prepare('SELECT * FROM country_content WHERE country_slug = ?');
        $stmt->execute([$countrySlug]);
        $content = $stmt->fetch();

        if ($content) {
            // Декодируем JSON поля
            $content['highlights'] = $content['highlights'] ? json_decode($content['highlights'], true) : null;
            $content['useful_info'] = $content['useful_info'] ? json_decode($content['useful_info'], true) : null;
            $content['detailed_info'] = $content['detailed_info'] ? json_decode($content['detailed_info'], true) : null;
            echo json_encode($content, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            echo json_encode(['message' => 'Контент для страны не найден'], JSON_UNESCAPED_UNICODE);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка при получении данных: ' . $e->getMessage()]);
    }
} elseif ($method === 'POST' || $method === 'PUT') {
    // Сохранение/обновление данных
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($countrySlug)) {
        $countrySlug = strtolower(trim((string) ($input['country_slug'] ?? '')));
    }

    if (empty($countrySlug)) {
        http_response_code(400);
        echo json_encode(['error' => 'Не указан slug страны']);
        exit;
    }

    try {
        // Подготавливаем данные
        $bio = $input['bio'] ?? null;
        if (is_string($bio)) {
            $bio = country_content_clean_utf8($bio);
        }
        $highlights = isset($input['highlights']) ? json_encode($input['highlights'], JSON_UNESCAPED_UNICODE) : null;
        $usefulInfo = isset($input['useful_info']) ? json_encode($input['useful_info'], JSON_UNESCAPED_UNICODE) : null;
        $detailedInfo = isset($input['detailed_info']) ? json_encode($input['detailed_info'], JSON_UNESCAPED_UNICODE) : null;
        $updatedBy = $_SESSION['user_id'] ?? null;

        // Проверяем, существует ли запись
        $checkStmt = $pdo->prepare('SELECT id FROM country_content WHERE country_slug = ?');
        $checkStmt->execute([$countrySlug]);
        $exists = $checkStmt->fetch();

        if ($exists) {
            // Обновляем существующую запись
            $stmt = $pdo->prepare('
                UPDATE country_content 
                SET bio = ?, highlights = ?, useful_info = ?, detailed_info = ?, 
                    updated_at = CURRENT_TIMESTAMP, updated_by = ?
                WHERE country_slug = ?
            ');
            $stmt->execute([$bio, $highlights, $usefulInfo, $detailedInfo, $updatedBy, $countrySlug]);
        } else {
            // Создаем новую запись
            $stmt = $pdo->prepare('
                INSERT INTO country_content (country_slug, bio, highlights, useful_info, detailed_info, updated_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([$countrySlug, $bio, $highlights, $usefulInfo, $detailedInfo, $updatedBy]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Данные успешно сохранены',
            'country_slug' => $countrySlug
        ], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Ошибка при сохранении данных: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не поддерживается']);
}

























