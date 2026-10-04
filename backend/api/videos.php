<?php
/**
 * API для получения видеоинструкций
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

try {
    // Получаем активные видео, которые еще не истекли
    $query = "
        SELECT * FROM videos 
        WHERE is_active = 1 
        AND (expires_at IS NULL OR expires_at > datetime('now'))
        ORDER BY is_main DESC, sort_order ASC, created_at DESC
    ";
    
    $stmt = $pdo->query($query);
    $videos = $stmt->fetchAll();
    
    // Форматируем данные
    foreach ($videos as &$video) {
        // Извлекаем ID из URL RuTube
        $videoUrl = $video['video_url'];
        $videoId = '';
        if (preg_match('/rutube\.ru\/video\/([^\/\?]+)/', $videoUrl, $matches)) {
            $videoId = $matches[1];
        }
        $video['video_id'] = $videoId;
        $video['embed_url'] = $videoId ? "https://rutube.ru/play/embed/{$videoId}" : '';
    }
    
    echo json_encode(['success' => true, 'videos' => $videos]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}








