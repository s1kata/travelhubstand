<?php
/**
 * Точка входа для TourVisor API.
 * Подключает реализацию из components/api. При любой ошибке возвращаем JSON, а не 500.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    if (!is_file(__DIR__ . '/../components/api/tourvisor-proxy.php')) {
        echo json_encode(['success' => false, 'error' => 'Proxy script not found', 'data' => null]);
        exit;
    }
    require_once __DIR__ . '/../components/api/tourvisor-proxy.php';
} catch (Throwable $e) {
    error_log('[tourvisor-proxy] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'data' => null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
