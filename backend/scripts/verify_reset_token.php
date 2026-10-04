<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
if ($token === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'valid' => false, 'message' => 'Токен не передан'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$pdo) {
    http_response_code(503);
    echo json_encode(['success' => false, 'valid' => false, 'message' => 'База данных недоступна'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare('
        SELECT id
        FROM password_reset_tokens
        WHERE token_hash = :token_hash
          AND used_at IS NULL
          AND expires_at > CURRENT_TIMESTAMP
        LIMIT 1
    ');
    $stmt->execute([':token_hash' => $tokenHash]);
    $row = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'valid' => $row !== false,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'valid' => false,
        'message' => 'Ошибка проверки токена',
    ], JSON_UNESCAPED_UNICODE);
}

