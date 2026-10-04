<?php
/**
 * Одноразовый скрипт: создаёт пользователя-админа для входа в аккаунт и админ-панель.
 * Запуск из корня проекта: php backend/scripts/create_admin_user.php
 * После первого входа рекомендуется сменить пароль в профиле или удалить этот файл.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$admin_email = 'gememix76142@gmail.com';
$admin_password = '8909qpzm';
$admin_name = 'Admin';

if (!$pdo) {
    fwrite(STDERR, "Ошибка: нет подключения к БД.\n");
    exit(1);
}

$hash = password_hash($admin_password, PASSWORD_DEFAULT);
if ($hash === false) {
    fwrite(STDERR, "Ошибка: не удалось создать хэш пароля.\n");
    exit(1);
}

try {
    $stmt = $pdo->prepare('SELECT id, role FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$admin_email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $pdo->prepare('UPDATE users SET password = ?, role = ?, name = ? WHERE id = ?')
            ->execute([$hash, 'admin', $admin_name, $existing['id']]);
        echo "Пользователь с email {$admin_email} обновлён: роль admin, пароль установлен.\n";
    } else {
        $ins = $pdo->prepare('INSERT INTO users (name, email, password, role, status, source) VALUES (?, ?, ?, ?, ?, ?)');
        $ins->execute([$admin_name, $admin_email, $hash, 'admin', 'active', 'website']);
        echo "Создан пользователь-админ: {$admin_email}, имя: {$admin_name}.\n";
    }
    echo "Вход: email {$admin_email}, пароль {$admin_password}. После входа будет доступна админ-панель.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Ошибка: " . $e->getMessage() . "\n");
    exit(1);
}
