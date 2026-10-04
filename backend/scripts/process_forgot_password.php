<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../components/security_helper.php';
require_once __DIR__ . '/../components/mail_helper.php';

session_start();

/**
 * Логирование для отладки сброса пароля. Пишет в error_log, data/forgot_password.log и backend/scripts/forgot_password_debug.log
 */
function log_forgot_password(string $message, array $context = []): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if (!empty($context)) {
        $line .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE);
    }
    error_log('[FORGOT_PASSWORD] ' . $message . (!empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : ''));
    $logDir = defined('TV_PROJECT_ROOT') ? (TV_PROJECT_ROOT . '/data') : (dirname(__DIR__, 2) . '/data');
    if (is_dir($logDir) && is_writable($logDir)) {
        @file_put_contents($logDir . '/forgot_password.log', $line . "\n", FILE_APPEND | LOCK_EX);
    }
    // Fallback: всегда пишем рядом со скриптом (гарантированно доступно)
    $debugLog = __DIR__ . '/forgot_password_debug.log';
    @file_put_contents($debugLog, $line . "\n", FILE_APPEND | LOCK_EX);
}

function ensurePasswordResetTable(PDO $pdo): void {
    $driver = strtolower((string)($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) ?? 'sqlite'));

    if ($driver === 'mysql') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            requested_ip VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_token_hash (token_hash),
            KEY idx_user_id (user_id),
            KEY idx_expires_at (expires_at),
            KEY idx_used_at (used_at),
            CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $columns = $pdo->query("SHOW COLUMNS FROM password_reset_tokens")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('token_hash', $columns, true)) {
            $pdo->exec("ALTER TABLE password_reset_tokens ADD COLUMN token_hash CHAR(64) NULL");
        }
        if (!in_array('used_at', $columns, true)) {
            $pdo->exec("ALTER TABLE password_reset_tokens ADD COLUMN used_at DATETIME NULL");
        }
        if (!in_array('requested_ip', $columns, true)) {
            $pdo->exec("ALTER TABLE password_reset_tokens ADD COLUMN requested_ip VARCHAR(45) NULL");
        }
        if (!in_array('user_agent', $columns, true)) {
            $pdo->exec("ALTER TABLE password_reset_tokens ADD COLUMN user_agent VARCHAR(255) NULL");
        }
        if (in_array('token', $columns, true)) {
            $pdo->exec("UPDATE password_reset_tokens SET token_hash = SHA2(token, 256) WHERE token_hash IS NULL AND token IS NOT NULL");
        }
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token_hash TEXT NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            requested_ip TEXT NULL,
            user_agent TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");

        $columnsRaw = $pdo->query("PRAGMA table_info(password_reset_tokens)")->fetchAll(PDO::FETCH_ASSOC);
        $columns = array_map(static fn(array $row): string => (string)$row['name'], $columnsRaw);
        if (!in_array('token_hash', $columns, true)) {
            $pdo->exec("ALTER TABLE password_reset_tokens ADD COLUMN token_hash TEXT");
        }
        if (!in_array('used_at', $columns, true)) {
            $pdo->exec("ALTER TABLE password_reset_tokens ADD COLUMN used_at DATETIME NULL");
        }
        if (!in_array('requested_ip', $columns, true)) {
            $pdo->exec("ALTER TABLE password_reset_tokens ADD COLUMN requested_ip TEXT NULL");
        }
        if (!in_array('user_agent', $columns, true)) {
            $pdo->exec("ALTER TABLE password_reset_tokens ADD COLUMN user_agent TEXT NULL");
        }
        if (in_array('token', $columns, true)) {
            // Для SQLite мигрируем старые plaintext-токены в hash через PHP.
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
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_password_reset_token_hash ON password_reset_tokens(token_hash)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_password_reset_expires_at ON password_reset_tokens(expires_at)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_password_reset_used_at ON password_reset_tokens(used_at)");
    }
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && security_rate_limit_exceeded('forgot_password', 3, 1800)) {
    header('Location: /frontend/window/forgot-password.php?success=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    if (!security_csrf_verify()) {
        header('Location: /frontend/window/forgot-password.php?errors=' . urlencode(json_encode(['general' => 'Сессия истекла. Обновите страницу и попробуйте снова.'])));
        exit;
    }

    $email = trim((string)($_POST['email'] ?? ''));

    if ($email === '') {
        $errors['email'] = 'Пожалуйста, введите email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Пожалуйста, введите корректный email.';
    }

    if (empty($errors)) {
        try {
            if (!$pdo) {
                throw new RuntimeException('DB connection unavailable');
            }

            ensurePasswordResetTable($pdo);

            $stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            // Всегда один и тот же UX-ответ, чтобы не раскрывать наличие email
            if ($user) {
                $rawToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $rawToken);
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $cleanupStmt = $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id AND used_at IS NULL');
                $cleanupStmt->execute([':user_id' => (int)$user['id']]);

                $insertStmt = $pdo->prepare('
                    INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, requested_ip, user_agent)
                    VALUES (:user_id, :token_hash, :expires_at, :requested_ip, :user_agent)
                ');
                $insertStmt->execute([
                    ':user_id' => (int)$user['id'],
                    ':token_hash' => $tokenHash,
                    ':expires_at' => $expiresAt,
                    ':requested_ip' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
                    ':user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
                ]);

                $siteUrl = rtrim((string)(getenv('SITE_URL') ?: ('https://' . ($_SERVER['HTTP_HOST'] ?? 'travelhub63.ru'))), '/');
                $resetLink = $siteUrl . '/frontend/window/reset-password.php?token=' . urlencode($rawToken);
                $userName = trim((string)($user['name'] ?? '')) !== '' ? (string)$user['name'] : 'Пользователь';

                $subject = 'Восстановление пароля - Travel Hub';
                $message = "Здравствуйте, {$userName}!\n\n";
                $message .= "Вы запросили восстановление пароля на сайте Travel Hub.\n\n";
                $message .= "Для установки нового пароля перейдите по ссылке (действует 1 час):\n";
                $message .= $resetLink . "\n\n";
                $message .= "Если вы не запрашивали восстановление пароля, просто проигнорируйте это письмо.\n\n";
                $message .= "С уважением,\nTravel Hub";

                $isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true)
                    || (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost:') === 0);
                $useDevLink = $isLocalhost && defined('APP_DEBUG') && APP_DEBUG;

                if ($useDevLink) {
                    header('Location: /frontend/window/forgot-password.php?success=1&dev_link=' . urlencode($resetLink));
                    exit;
                }

                $sent = mail_send((string)$user['email'], $subject, $message);
                if (!$sent) {
                    log_forgot_password('Ошибка отправки письма', ['email' => $email]);
                }
            }

            header('Location: /frontend/window/forgot-password.php?success=1');
            exit;
        } catch (Throwable $e) {
            log_forgot_password('Ошибка request reset', ['message' => $e->getMessage()]);
            $errors['general'] = defined('APP_DEBUG') && APP_DEBUG
                ? 'Ошибка: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
                : 'Временная ошибка сервера.';
        }
    }
}

if (!empty($errors)) {
    header('Location: /frontend/window/forgot-password.php?errors=' . urlencode(json_encode($errors)) . '&email=' . urlencode($email));
    exit;
}

header('Location: /frontend/window/forgot-password.php');
exit;
