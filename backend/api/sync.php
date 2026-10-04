<?php
declare(strict_types=1);

/**
 * Protected mobile sync API.
 * Requires Authorization: Bearer <SYNC_API_TOKEN>.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../components/security_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (security_rate_limit_exceeded('mobile_sync', 120, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Too many requests']);
    exit;
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($authHeader === '' && function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
}
if (!preg_match('/^\s*Bearer\s+(.+)\s*$/i', $authHeader, $m)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$providedToken = trim((string)$m[1]);
$syncToken = trim((string)(getenv('SYNC_API_TOKEN') ?: ($_ENV['SYNC_API_TOKEN'] ?? '')));
if ($syncToken === '' || $providedToken === '' || !hash_equals($syncToken, $providedToken)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!th_db_is_available()) {
    th_json_db_unavailable_exit();
}

$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';
$rawInput = file_get_contents('php://input');
$input = $rawInput ? json_decode($rawInput, true) : [];
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
    exit;
}

try {
    switch ($action) {
        case 'sync_bookings':
            syncBookings($pdo, $input);
            break;
        case 'sync_users':
            syncUsers($pdo, $input);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

/**
 * Синхронизация бронирований
 */
function syncBookings(PDO $pdo, array $data): void {
    if (!isset($data['bookings']) || !is_array($data['bookings'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ожидается массив бронирований']);
        return;
    }
    
    $synced = 0;
    $errors = [];
    
    foreach ($data['bookings'] as $booking) {
        try {
            // Проверяем существование пользователя
            $userId = findOrCreateUser($pdo, $booking);
            
            if (!$userId) {
                $errors[] = 'User not found for booking';
                continue;
            }
            
            // Проверяем, существует ли уже такое бронирование
            $stmt = $pdo->prepare("SELECT id FROM bookings WHERE id = ?");
            $stmt->execute([$booking['id']]);
            $exists = $stmt->fetch();
            
            if ($exists) {
                // Обновляем существующее бронирование
                $stmt = $pdo->prepare("
                    UPDATE bookings SET
                        user_id = ?,
                        tour_title = ?,
                        hotel_name = ?,
                        destination = ?,
                        stars = ?,
                        nights = ?,
                        price = ?,
                        currency = ?,
                        meals = ?,
                        departure_date = ?,
                        return_date = ?,
                        status = ?,
                        notes = ?,
                        source = 'app'
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $userId,
                    $booking['tourTitle'] ?? '',
                    $booking['hotelName'] ?? '',
                    $booking['destination'] ?? '',
                    $booking['stars'] ?? null,
                    $booking['nights'] ?? null,
                    $booking['totalPrice'] ?? 0,
                    $booking['currency'] ?? 'RUB',
                    $booking['meals'] ?? null,
                    $booking['checkIn'] ?? null,
                    $booking['checkOut'] ?? null,
                    $booking['status'] ?? 'pending',
                    $booking['notes'] ?? null,
                    $booking['id']
                ]);
            } else {
                // Создаем новое бронирование
                $stmt = $pdo->prepare("
                    INSERT INTO bookings (
                        id, user_id, tour_title, hotel_name, destination,
                        stars, nights, price, currency, meals,
                        departure_date, return_date, status, booking_date, notes, source
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?, 'app')
                ");
                
                $stmt->execute([
                    $booking['id'],
                    $userId,
                    $booking['tourTitle'] ?? '',
                    $booking['hotelName'] ?? '',
                    $booking['destination'] ?? '',
                    $booking['stars'] ?? null,
                    $booking['nights'] ?? null,
                    $booking['totalPrice'] ?? 0,
                    $booking['currency'] ?? 'RUB',
                    $booking['meals'] ?? null,
                    $booking['checkIn'] ?? null,
                    $booking['checkOut'] ?? null,
                    $booking['status'] ?? 'pending',
                    $booking['notes'] ?? null
                ]);
            }
            
            $synced++;
        } catch (Throwable $e) {
            $errors[] = 'Failed to sync booking';
        }
    }
    
    echo json_encode([
        'success' => true,
        'synced' => $synced,
        'total' => count($data['bookings']),
        'errors' => $errors
    ]);
}

/**
 * Синхронизация пользователей
 */
function syncUsers(PDO $pdo, array $data): void {
    if (!isset($data['users']) || !is_array($data['users'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ожидается массив пользователей']);
        return;
    }
    
    $synced = 0;
    $errors = [];
    
    foreach ($data['users'] as $user) {
        try {
            $email = trim((string)($user['email'] ?? ''));
            $phone = trim((string)($user['phone'] ?? ''));
            if ($email === '' && $phone === '') {
                $errors[] = 'Skipped user without email/phone';
                continue;
            }
            $passwordRaw = trim((string)($user['password'] ?? ''));
            if ($passwordRaw === '') {
                $errors[] = 'Skipped user without password';
                continue;
            }
            $pwdInfo = password_get_info($passwordRaw);
            $passwordToStore = ($pwdInfo['algo'] !== null && $pwdInfo['algo'] !== 0)
                ? $passwordRaw
                : password_hash($passwordRaw, PASSWORD_DEFAULT);

            // Проверяем, существует ли пользователь по email или phone
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
            $stmt->execute([$email, $phone]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Обновляем существующего пользователя
                $stmt = $pdo->prepare("
                    UPDATE users SET
                        name = ?,
                        email = ?,
                        phone = ?,
                        password = ?,
                        last_login = CURRENT_TIMESTAMP,
                        source = 'app'
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    trim((string)($user['name'] ?? '')),
                    $email,
                    $phone,
                    $passwordToStore,
                    $existing['id']
                ]);
            } else {
                // Создаем нового пользователя
                $stmt = $pdo->prepare("
                    INSERT INTO users (
                        name, email, password, phone, role, reg_date, status, source
                    ) VALUES (?, ?, ?, ?, 'user', CURRENT_TIMESTAMP, 'active', 'app')
                ");
                
                $stmt->execute([
                    trim((string)($user['name'] ?? '')),
                    $email,
                    $passwordToStore,
                    $phone
                ]);
            }
            
            $synced++;
        } catch (Throwable $e) {
            $errors[] = 'Failed to sync user';
        }
    }
    
    echo json_encode([
        'success' => true,
        'synced' => $synced,
        'total' => count($data['users']),
        'errors' => $errors
    ]);
}

/**
 * Поиск или создание пользователя
 */
function findOrCreateUser(PDO $pdo, array $booking): ?int {
    // Пытаемся найти пользователя по userId из приложения
    if (isset($booking['userId'])) {
        // Ищем по phone или email, если userId не совпадает с id в базе сайта
        // Для этого нужно будет хранить связь между userId приложения и id сайта
        // Пока просто ищем по phone
    }
    
    // Если есть phone в бронировании, ищем по нему
    if (isset($booking['userPhone'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->execute([$booking['userPhone']]);
        $user = $stmt->fetch();
        if ($user) {
            return (int)$user['id'];
        }
    }
    
    // Если пользователь не найден, возвращаем null
    // В реальном сценарии можно создать пользователя или вернуть ошибку
    return null;
}

