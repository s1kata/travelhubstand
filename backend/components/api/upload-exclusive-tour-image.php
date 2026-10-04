<?php
/**
 * API для загрузки изображений эксклюзивных туров
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
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$maxSize = 5 * 1024 * 1024; // 5MB

// Проверка типа файла
if (!in_array($file['type'], $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP']);
    exit;
}

// Проверка размера файла
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => 'File size exceeds 5MB limit']);
    exit;
}

// Создаем директорию для загрузки, если её нет
$uploadDir = __DIR__ . '/../../frontend/window/img/exclusive-tours/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Генерируем уникальное имя файла
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$fileName = 'tour_' . time() . '_' . uniqid() . '.' . $extension;
$filePath = $uploadDir . $fileName;

// Перемещаем загруженный файл
if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file']);
    exit;
}

// Возвращаем путь относительно корня сайта
$relativePath = '/frontend/window/img/exclusive-tours/' . $fileName;

echo json_encode([
    'success' => true,
    'path' => $relativePath,
    'url' => $relativePath
], JSON_UNESCAPED_UNICODE);

























