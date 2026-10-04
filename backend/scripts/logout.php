<?php
// Без вывода до заголовков — избегаем 500 при отправке cookie
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];

$secureLogout = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
// Удаляем cookie сессии (текущий домен, не localhost)
$params = session_get_cookie_params();
if (!empty($params['name'])) {
    setcookie($params['name'], '', [
        'expires' => time() - 3600,
        'path' => $params['path'] ?: '/',
        'domain' => $params['domain'] ?: '',
        'secure' => (bool) ($params['secure'] ?? $secureLogout),
        'httponly' => (bool) ($params['httponly'] ?? true),
        'samesite' => 'Lax',
    ]);
}
// Cookie запоминания — домен пустой = текущий хост (работает на travelhub63.ru и localhost)
setcookie('user_id', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => $secureLogout,
    'httponly' => true,
    'samesite' => 'Lax',
]);
setcookie('user_token', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => $secureLogout,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_destroy();

header('Content-Type: text/html; charset=utf-8');
echo "<script>
localStorage.removeItem('user_logged_in');
localStorage.removeItem('user_name');
localStorage.removeItem('user_email');
localStorage.removeItem('user_role');
window.location.replace('/index.php?nocache=1&_=' + Date.now());
</script>";
exit;
