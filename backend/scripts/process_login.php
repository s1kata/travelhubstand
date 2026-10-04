<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../components/security_helper.php';

session_start();

// Rate limit: 5 попыток за 15 минут с одного IP
if (security_rate_limit_exceeded('login', 5, 900)) {
    header('Location: /frontend/window/login.php?errors=' . urlencode(json_encode(['login' => 'Слишком много попыток. Попробуйте через 15 минут.'])));
    exit;
}

if (!defined('REMEMBER_TOKEN_SALT')) {
    define('REMEMBER_TOKEN_SALT', (getenv('AUTH_REMEMBER_SALT') ?: 'travelhub-remember-token'));
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!security_csrf_verify()) {
        header('Location: /frontend/window/login.php?errors=' . urlencode(json_encode(['login' => 'Сессия истекла. Обновите страницу и войдите снова.'])));
        exit;
    }
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '') {
        $errors['email'] = 'Пожалуйста, введите email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Пожалуйста, введите корректный email.';
    }

    if ($password === '') {
        $errors['password'] = 'Пожалуйста, введите пароль.';
    }

    if (empty($errors)) {
        try {
            if (!$pdo) {
                $errors['database'] = 'База данных недоступна.';
            } else {
                $user = null;
                try {
                    $stmt = $pdo->prepare('SELECT id, name, email, password, role, status, COALESCE(is_manager,0) AS is_manager FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
                    $stmt->execute([':email' => $email]);
                    $user = $stmt->fetch();
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'is_manager') !== false || strpos($e->getMessage(), '42S22') !== false || stripos($e->getMessage(), 'Unknown column') !== false) {
                        $stmt = $pdo->prepare('SELECT id, name, email, password, role, status FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
                        $stmt->execute([':email' => $email]);
                        $user = $stmt->fetch();
                        if ($user) $user['is_manager'] = 0;
                    } else {
                        throw $e;
                    }
                }

                if (!$user) {
                    $errors['account_not_found'] = true;
                } elseif ($user['status'] !== 'active') {
                    $errors['login'] = 'Учетная запись заблокирована. Свяжитесь с менеджером.';
                } elseif (!password_verify($password, $user['password'])) {
                    $errors['login'] = 'Неверный email или пароль.';
                } else {
                    $normalizedRole = strtolower(trim((string) ($user['role'] ?? '')));

                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int) $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $normalizedRole;
                    $_SESSION['user_is_manager'] = !empty($user['is_manager']);
                    $_SESSION['logged_in'] = true;

                    if (!empty($_POST['remember'])) {
                        $cookiePayload = [
                            'expires' => time() + (30 * 24 * 60 * 60),
                            'path' => '/',
                            'domain' => $_SERVER['HTTP_HOST'] ?? '',
                            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                            'httponly' => true,
                            'samesite' => 'Lax',
                        ];

                        setcookie('user_id', (string) $user['id'], $cookiePayload);
                        setcookie('user_token', hash('sha256', $user['email'] . $user['password'] . REMEMBER_TOKEN_SALT), $cookiePayload);
                    }

                    try {
                        // Используем CURRENT_TIMESTAMP для совместимости с SQLite и MySQL
                        $dbDriver = strtolower((getenv('DB_DRIVER') ?: 'sqlite'));
                        $timestampExpr = ($dbDriver === 'mysql') ? 'NOW()' : "datetime('now')";
                        $pdo->prepare("UPDATE users SET last_login = $timestampExpr WHERE id = :id")->execute([':id' => $user['id']]);
                    } catch (PDOException $updateException) {}

                    if ($normalizedRole === 'admin') {
                        $redirectPage = '/frontend/window/dashboard.php';
                    } else {
                        $redirectPage = '/index.php';
                    }
                    if (!empty($_POST['redirect'])) {
                        $r = trim((string) $_POST['redirect']);
                        if ($r !== '' && substr($r, 0, 1) === '/' && strpos($r, '//') === false) {
                            $redirectPage = $r;
                        }
                    }
                
                    $safeName = json_encode((string)$user['name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                    $safeEmail = json_encode((string)$user['email'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                    $safeRole = json_encode($normalizedRole, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                    echo "<script>localStorage.setItem('user_logged_in','true');localStorage.setItem('user_name',$safeName);localStorage.setItem('user_email',$safeEmail);localStorage.setItem('user_role',$safeRole);console.log('Redirect to:', " . json_encode($redirectPage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ");window.location.href=" . json_encode($redirectPage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ";</script>";
                    exit;
                }
            }
        } catch (PDOException $e) {
            $errors['database'] = 'Временная ошибка сервера. Попробуйте позже.';
        }
    }
}

if (!empty($errors)) {
    header('Location: /frontend/window/login.php?errors=' . urlencode(json_encode($errors)));
    exit;
}
?>