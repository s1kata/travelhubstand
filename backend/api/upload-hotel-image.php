<?php
/**
 * API для загрузки изображений VIP отелей
 */

require_once __DIR__ . '/../config/config.php';

session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? null) !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Проверяем, что файл был загружен
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['image'];
$maxSize = 5 * 1024 * 1024; // 5MB

// Проверка размера файла
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => 'File size exceeds 5MB limit']);
    exit;
}

// Проверка реального MIME-типа
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$detectedMime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
$allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
if (!isset($allowedMimes[$detectedMime])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP']);
    exit;
}
$extension = $allowedMimes[$detectedMime];

// Город — только из белого списка
$city = isset($_POST['city']) ? strtolower(trim($_POST['city'])) : 'antalya';
$validCities = ['antalya', 'belek', 'kemer'];
if (!in_array($city, $validCities, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid city. Allowed: antalya, belek, kemer']);
    exit;
}

// Создаем директорию для загрузки, если её нет
$uploadDir = __DIR__ . '/../../frontend/window/img/hotels/' . $city . '/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$fileName = 'hotel_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
$filePath = $uploadDir . $fileName;

// Перемещаем загруженный файл
if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file']);
    exit;
}

// Возвращаем путь относительно корня сайта
$relativePath = '/frontend/window/img/hotels/' . $city . '/' . $fileName;

echo json_encode([
    'success' => true,
    'path' => $relativePath,
    'url' => $relativePath
], JSON_UNESCAPED_UNICODE);























