<?php
/**
 * Тест записи в лог. Открыть: /backend/scripts/test_log_write.php
 * Проверяет, можно ли создать forgot_password_debug.log
 */
header('Content-Type: text/plain; charset=utf-8');

$logFile = __DIR__ . '/forgot_password_debug.log';
$testLine = '[' . date('Y-m-d H:i:s') . '] ТЕСТ: запись работает, папка доступна для записи' . "\n";

$written = @file_put_contents($logFile, $testLine, FILE_APPEND | LOCK_EX);

if ($written !== false) {
    echo "OK: Файл создан/обновлён.\n";
    echo "Путь: " . $logFile . "\n";
    echo "Размер: " . filesize($logFile) . " байт\n";
    echo "\nТеперь сделайте запрос на сброс пароля и откройте view_forgot_password_log.php";
} else {
    echo "ОШИБКА: Не удалось записать в файл.\n";
    echo "Путь: " . $logFile . "\n";
    echo "Проверьте права на папку backend/scripts/ (должна быть доступна для записи).\n";
    echo "is_writable(dir): " . (is_writable(__DIR__) ? 'да' : 'нет') . "\n";
}
