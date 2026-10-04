<?php
/**
 * Просмотр лога сброса пароля в браузере.
 * Открыть: /backend/scripts/view_forgot_password_log.php
 * Для доступа нужен APP_DEBUG=1 в .env или ?key= (пустой key отключает, можно добавить свой пароль).
 */
require_once __DIR__ . '/../config/config.php';

$allow = defined('APP_DEBUG') && APP_DEBUG;
if (!$allow && isset($_GET['key']) && $_GET['key'] === 'forgot_debug') {
    $allow = true;
}

if (!$allow) {
    header('HTTP/1.0 403 Forbidden');
    echo 'Доступ запрещён. Включите APP_DEBUG=1 в .env или добавьте ?key=forgot_debug';
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

$logFile = __DIR__ . '/forgot_password_debug.log';
if (!is_file($logFile)) {
    echo "Файл лога не найден: forgot_password_debug.log\n";
    echo "Путь: " . $logFile . "\n\n";
    echo "1. Проверьте запись: откройте /backend/scripts/test_log_write.php\n";
    echo "   Если файл создастся — папка доступна для записи.\n\n";
    echo "2. Сделайте запрос на сброс пароля (форма «Забыли пароль»),\n";
    echo "   затем обновите эту страницу.\n\n";
    echo "3. Если форма ведёт на другой URL — проверьте action в forgot-password.php.";
    exit;
}

$content = file_get_contents($logFile);
if ($content === false) {
    echo "Не удалось прочитать файл лога.";
    exit;
}

echo "=== Лог сброса пароля (последние 100 строк) ===\n\n";
$lines = explode("\n", trim($content));
$lines = array_slice($lines, -100);
echo implode("\n", $lines);
