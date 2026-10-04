<?php
/**
 * PDO + helpers для отзывов (та же БД, что auth-mobile.php).
 */
declare(strict_types=1);

require_once __DIR__ . '/auth-jwt.php';
require_once __DIR__ . '/review-profanity.php';

function reviews_db_connect(array $config): PDO
{
    $db = $config['db'] ?? [];
    if (empty($db['name']) || empty($db['user'])) {
        throw new RuntimeException('Не заданы db.name или db.user в auth-mobile.config.php');
    }
    $charset = $db['charset'] ?? 'utf8mb4';
    if (!empty($db['unix_socket'])) {
        $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $db['unix_socket'], $db['name'], $charset);
    } else {
        $host = $db['host'] ?? 'localhost';
        $port = (int) ($db['port'] ?? 3306);
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $db['name'], $charset);
    }
    return new PDO($dsn, (string) $db['user'], (string) ($db['pass'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function reviews_json_ok($data = null): void
{
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function reviews_json_error(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function reviews_sanitize_label(string $text, int $maxLen = 255): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if (mb_strlen($text) > $maxLen) {
        $text = mb_substr($text, 0, $maxLen);
    }
    return $text;
}

function reviews_assert_no_profanity(string $text): void
{
    if (review_text_contains_profanity($text)) {
        reviews_json_error('Отзыв содержит недопустимые слова', 400);
    }
}

function reviews_assert_rate_limit(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS cnt FROM reviews WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) AND deleted_at IS NULL'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if ($row && (int) $row['cnt'] >= 10) {
        reviews_json_error('Слишком много отзывов за короткое время. Попробуйте позже.', 429);
    }
}

/**
 * @return array<string, mixed>|null
 */
function reviews_row_to_dto(array $r, ?int $viewerUserId, array $helpfulSet = []): array
{
    $id = (int) $r['id'];
    return [
        'id' => (string) $id,
        'userId' => (string) $r['user_id'],
        'userName' => $r['user_name'],
        'tourId' => $r['tour_id'],
        'hotelId' => $r['hotel_id'],
        'hotelName' => $r['hotel_name'] ?? null,
        'countryName' => $r['country_name'] ?? null,
        'rating' => (int) $r['rating'],
        'text' => $r['text'],
        'helpful' => (int) $r['helpful'],
        'verified' => (bool) $r['verified'],
        'date' => gmdate('c', strtotime((string) $r['created_at'])),
        'isOwn' => $viewerUserId !== null && (int) $r['user_id'] === $viewerUserId,
        'userMarkedHelpful' => isset($helpfulSet[$id]),
    ];
}

/**
 * @return array<string, mixed>|null
 */
function reviews_fetch_by_id(PDO $pdo, int $id, ?int $viewerUserId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT r.id, r.user_id, r.user_name, r.tour_id, r.hotel_id, r.hotel_name, r.country_name,
                r.rating, r.review_text AS text, r.helpful, r.verified, r.created_at, r.updated_at
         FROM reviews r WHERE r.id = ? AND r.deleted_at IS NULL LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    return reviews_row_to_dto($row, $viewerUserId);
}

/**
 * @return array<int, array<string, mixed>>
 */
function reviews_list(PDO $pdo, ?string $tourId, ?string $hotelId, ?int $viewerUserId, ?string $scope = null): array
{
    $sql = 'SELECT r.id, r.user_id, r.user_name, r.tour_id, r.hotel_id, r.hotel_name, r.country_name,
            r.rating, r.review_text AS text, r.helpful, r.verified, r.created_at, r.updated_at
            FROM reviews r
            WHERE r.deleted_at IS NULL';
    $params = [];
    if ($tourId !== null && $tourId !== '' && $hotelId !== null && $hotelId !== '') {
        // Тот же отель / смена tourId у горящих — показываем оба набора
        $sql .= ' AND (r.tour_id = ? OR r.hotel_id = ?)';
        $params[] = $tourId;
        $params[] = $hotelId;
    } elseif ($tourId !== null && $tourId !== '') {
        $sql .= ' AND r.tour_id = ?';
        $params[] = $tourId;
    } elseif ($hotelId !== null && $hotelId !== '') {
        $sql .= ' AND r.hotel_id = ?';
        $params[] = $hotelId;
    } elseif ($scope === 'general') {
        $sql .= ' AND r.tour_id IS NULL';
    }
    $sql .= ' ORDER BY r.created_at DESC LIMIT 200';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $helpfulSet = [];
    if ($viewerUserId !== null && $rows) {
        try {
            $ids = array_map(static fn ($r) => (int) $r['id'], $rows);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $hStmt = $pdo->prepare("SELECT review_id FROM review_helpful WHERE user_id = ? AND review_id IN ({$placeholders})");
            $hStmt->execute(array_merge([$viewerUserId], $ids));
            foreach ($hStmt->fetchAll() as $h) {
                $helpfulSet[(int) $h['review_id']] = true;
            }
        } catch (Throwable $e) {
            error_log('[crm/reviews] review_helpful: ' . $e->getMessage());
        }
    }

    $out = [];
    foreach ($rows as $r) {
        $out[] = reviews_row_to_dto($r, $viewerUserId, $helpfulSet);
    }
    return $out;
}

function reviews_assert_single_per_target(PDO $pdo, int $userId, ?string $tourId, ?string $hotelId, ?int $excludeId = null): void
{
    if ($tourId) {
        $sql = 'SELECT id FROM reviews WHERE user_id = ? AND tour_id = ? AND deleted_at IS NULL';
        $params = [$userId, $tourId];
    } elseif ($hotelId) {
        $sql = 'SELECT id FROM reviews WHERE user_id = ? AND hotel_id = ? AND deleted_at IS NULL';
        $params = [$userId, $hotelId];
    } else {
        return;
    }
    if ($excludeId !== null) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeId;
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetch()) {
        reviews_json_error('Вы уже оставили отзыв', 409);
    }
}

function reviews_sanitize_text(string $text, int $maxLen = 1000): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if ($text === '') {
        reviews_json_error('Текст отзыва обязателен', 400);
    }
    if (mb_strlen($text) < 3) {
        reviews_json_error('Отзыв слишком короткий (минимум 3 символа)', 400);
    }
    if (mb_strlen($text) > $maxLen) {
        reviews_json_error('Отзыв слишком длинный (максимум ' . $maxLen . ' символов)', 400);
    }
    return $text;
}

function reviews_clamp_rating($rating): int
{
    return max(1, min(5, (int) round((float) $rating)));
}

/**
 * Безопасно извлечь viewer id из Bearer (битый токен не должен ронять GET).
 */
/**
 * Удаление отзыва (soft delete). 404 — уже удалён, 403 — чужой.
 */
function reviews_delete_owned(PDO $pdo, int $userId, int $id): void
{
    if ($id <= 0) {
        reviews_json_error('id required', 400);
    }
    $stmt = $pdo->prepare('SELECT user_id FROM reviews WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        reviews_json_error('Review not found', 404);
    }
    if ((int) $row['user_id'] !== $userId) {
        reviews_json_error('Forbidden', 403);
    }
    $upd = $pdo->prepare('UPDATE reviews SET deleted_at = NOW() WHERE id = ?');
    $upd->execute([$id]);
    reviews_json_ok(['id' => (string) $id]);
}

function reviews_optional_viewer_id(array $config): ?int
{
    $authHeader = auth_get_authorization_header();
    if (!preg_match('/^Bearer\s+(\S.+)$/i', $authHeader, $m)) {
        return null;
    }
    $token = trim($m[1]);
    if ($token === '') {
        return null;
    }
    try {
        $secret = (string) ($config['jwt_secret'] ?? '');
        if ($secret === '') {
            return null;
        }
        $claims = auth_jwt_decode($token, $secret, auth_jwt_issuer($config));
        if ($claims && !empty($claims['sub'])) {
            return (int) $claims['sub'];
        }
    } catch (Throwable $e) {
        error_log('[crm/reviews] jwt decode: ' . $e->getMessage());
    }
    return null;
}
