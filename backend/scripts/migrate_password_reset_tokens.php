<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

if (!$pdo) {
    echo "DB connection unavailable\n";
    exit(1);
}

try {
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
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_password_reset_token_hash ON password_reset_tokens(token_hash)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_password_reset_expires_at ON password_reset_tokens(expires_at)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_password_reset_used_at ON password_reset_tokens(used_at)");
    }

    echo "password_reset_tokens migration: OK\n";
    exit(0);
} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

