<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../components/security_helper.php';

session_start();

/**
 * Логирование для отладки сброса пароля (установка нового пароля). Пишет в error_log, data/forgot_password.log и forgot_password_debug.log
 */
function log_reset_password(string $message, array $context = []): void {
    $line = '[' . date('Y-m-d H:i:s') . '] [RESET] ' . $message;
    if (!empty($context)) {
        $line .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE);
    }
    error_log('[RESET_PASSWORD] ' . $message . (!empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : ''));
    $logDir = defined('TV_PROJECT_ROOT') ? (TV_PROJECT_ROOT . '/data') : (dirname(__DIR__, 2) . '/data');
    if (is_dir($logDir) && is_writable($logDir)) {
        @file_put_contents($logDir . '/forgot_password.log', $line . "\n", FILE_APPEND | LOCK_EX);
    }
    @file_put_contents(__DIR__ . '/forgot_password_debug.log', $line . "\n", FILE_APPEND | LOCK_EX);
}

function ensurePasswordResetColumns(PDO $pdo): void {
    $driver = strtolower((string)($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) ?? 'sqlite'));
    if ($driver === 'mysql') {
        $columns = $pdo->query("SHOW COLUMNS FROM password_reset_tokens")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('token_hash', $columns, true)) {
            $pdo->exec("ALTER TABLE password_reset_tokens ADD COLUMN token_hash CHAR(64) NULL");
        }
        if (!in_array('used_at', $columns, true)) {
            $pdo->exec("ALTER TABLE password_reset_tokens ADD COLUMN used_at DATETIME NULL");
        }
        if (in_array('token', $columns, true)) {
            $pdo->exec("UPDATE password_reset_tokens SET token_hash = SHA2(token, 256) WHERE token_hash IS NULL AND token IS NOT NULL");
        }
    } else {
        $columnsRaw = $pdo->query("PRAGMA table_info(password_reset_tokens)")->fetchAll(PDO::FETCH_ASSOC);
        $columns = array_map(static fn(array $row): string => (string)$row['name'], $columnsRaw);
        if (!in_array('token_hash', $columns, true)) {
            $pdo->exec("ALTER TABLE password_reset_tokens ADD COLUMN token_hash TEXT");
        }
        if (!in_array('used_at', $columns, true)) {
            $pdo->exec("ALTER TABLE password_reset_tokens ADD COLUMN used_at DATETIME NULL");
        }
        if (in_array('token', $columns, true)) {
            $rows = $pdo->query("SELECT id, token FROM password_reset_tokens WHERE token_hash IS NULL AND token IS NOT NULL")->fetchAll();
            if (!empty($rows)) {
                $upd = $pdo->prepare("UPDATE password_reset_tokens SET token_hash = :token_hash WHERE id = :id");
                foreach ($rows as $row) {
                    $upd->execute([
                        ':token_hash' => hash('sha256', (string)$row['token']),
                        ':id' => (int)$row['id'],
                    ]);
                }
            }
        }
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    if (!security_csrf_verify()) {
        header('Location: /frontend/window/reset-password.php?token=' . urlencode((string)($_POST['token'] ?? '')) . '&errors=' . urlencode(json_encode(['token' => 'Сессия истекла. Обновите страницу и попробуйте снова.'])));
        exit;
    }

    $token = trim($_POST['token'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $passwordConfirm = trim($_POST['password_confirm'] ?? '');

    if (empty($token)) {
        $errors['token'] = 'Ссылка для восстановления недействительна.';
    }
    if (strlen($password) < 6) {
        $errors['password'] = 'Пароль должен содержать не менее 6 символов.';
    }
    if ($password !== $passwordConfirm) {
        $errors['password'] = 'Пароли не совпадают.';
    }

    if (empty($errors) && $pdo) {
        try {
            log_reset_password('Начало обработки', ['token_length' => strlen($token)]);
            ensurePasswordResetColumns($pdo);

            $driver = strtolower((string)($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) ?? 'sqlite'));
            $tokenHash = hash('sha256', $token);

            $pdo->beginTransaction();
            if ($driver === 'mysql') {
                $stmt = $pdo->prepare('
                    SELECT id, user_id, expires_at, used_at
                    FROM password_reset_tokens
                    WHERE token_hash = :token_hash
                    LIMIT 1
                    FOR UPDATE
                ');
            } else {
                $stmt = $pdo->prepare('
                    SELECT id, user_id, expires_at, used_at
                    FROM password_reset_tokens
                    WHERE token_hash = :token_hash
                    LIMIT 1
                ');
            }
            $stmt->execute([':token_hash' => $tokenHash]);
            $row = $stmt->fetch();

            if (!$row) {
                $pdo->rollBack();
                log_reset_password('Токен не найден или недействителен', ['token_preview' => substr($token, 0, 8) . '...']);
                $errors['token'] = 'Ссылка для восстановления недействительна или уже использована.';
            } elseif (!empty($row['used_at'])) {
                $pdo->rollBack();
                log_reset_password('Токен уже использован', ['prt_id' => $row['id']]);
                $errors['token'] = 'Эта ссылка уже использована. Запросите новую.';
            } elseif (strtotime((string)$row['expires_at']) < time()) {
                $pdo->rollBack();
                log_reset_password('Токен истёк', ['expires_at' => $row['expires_at']]);
                $errors['token'] = 'Срок действия ссылки истёк. Запросите новую ссылку для восстановления пароля.';
            } else {
                log_reset_password('Обновление пароля', ['user_id' => $row['user_id']]);
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $updateUser = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
                $updateUser->execute([':password' => $hashedPassword, ':id' => (int)$row['user_id']]);

                $markUsed = $pdo->prepare('UPDATE password_reset_tokens SET used_at = CURRENT_TIMESTAMP WHERE id = :id');
                $markUsed->execute([':id' => (int)$row['id']]);

                $invalidateOthers = $pdo->prepare('
                    DELETE FROM password_reset_tokens
                    WHERE user_id = :user_id
                      AND id <> :id
                      AND used_at IS NULL
                ');
                $invalidateOthers->execute([
                    ':user_id' => (int)$row['user_id'],
                    ':id' => (int)$row['id'],
                ]);

                $pdo->commit();
                log_reset_password('Пароль успешно обновлён', ['user_id' => $row['user_id']]);
                header('Location: /frontend/window/reset-password.php?token=' . urlencode($token) . '&success=1');
                exit;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            log_reset_password('PDOException', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
                'token_preview' => substr($token ?? '', 0, 8) . '...',
            ]);
            $errors['token'] = 'Ошибка при сбросе пароля. Попробуйте позже.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            log_reset_password('Throwable', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);
            $errors['token'] = 'Ошибка при сбросе пароля. Попробуйте позже.';
        }
    } elseif (empty($errors) && !$pdo) {
        log_reset_password('PDO is null — база данных недоступна', ['db_driver' => getenv('DB_DRIVER') ?? 'sqlite']);
        $errors['token'] = 'Ошибка базы данных. Попробуйте позже.';
    }
}

if (!empty($errors)) {
    $redirectUrl = '/frontend/window/reset-password.php?token=' . urlencode($_POST['token'] ?? '') . '&errors=' . urlencode(json_encode($errors));
    header('Location: ' . $redirectUrl);
    exit;
}

header('Location: /frontend/window/forgot-password.php');
exit;
?>
