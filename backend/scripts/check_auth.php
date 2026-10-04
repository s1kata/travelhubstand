<?php
require_once __DIR__ . '/../config/config.php';

session_start();

if (!defined('REMEMBER_TOKEN_SALT')) {
    define('REMEMBER_TOKEN_SALT', getenv('AUTH_REMEMBER_SALT') ?: 'travelhub-remember-token');
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if (isset($_COOKIE['user_id'], $_COOKIE['user_token']) && $pdo) {
        $userId = (int) $_COOKIE['user_id'];
        $token = $_COOKIE['user_token'];

        try {
            $user = null;
            try {
                $stmt = $pdo->prepare('SELECT id, name, email, password, role, status, COALESCE(is_manager,0) AS is_manager FROM users WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $userId]);
                $user = $stmt->fetch();
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'is_manager') !== false || strpos($e->getMessage(), '42S22') !== false || stripos($e->getMessage(), 'Unknown column') !== false) {
                    $stmt = $pdo->prepare('SELECT id, name, email, password, role, status FROM users WHERE id = :id LIMIT 1');
                    $stmt->execute([':id' => $userId]);
                    $user = $stmt->fetch();
                    if ($user) $user['is_manager'] = 0;
                } else {
                    throw $e;
                }
            }

            if ($user && $user['status'] === 'active') {
                $expectedToken = hash('sha256', $user['email'] . $user['password'] . REMEMBER_TOKEN_SALT);
                if (hash_equals($expectedToken, $token)) {
                    $normalizedRole = strtolower(trim((string) ($user['role'] ?? '')));

                    $_SESSION['user_id'] = (int) $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $normalizedRole;
                    $_SESSION['user_is_manager'] = !empty($user['is_manager']);
                    $_SESSION['logged_in'] = true;
                }
            }
        } catch (PDOException $e) {
            error_log('[auth] remember me failed: ' . $e->getMessage());
        }
    }
}

header('Content-Type: application/json');

echo json_encode([
    'authenticated' => isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true,
    'user' => isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true ? [
        'name' => $_SESSION['user_name'] ?? null,
        'email' => $_SESSION['user_email'] ?? null,
        'role' => $_SESSION['user_role'] ?? null,
        'is_manager' => !empty($_SESSION['user_is_manager']),
    ] : null,
]);
?>