<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

if (!$pdo) {
    echo "DB connection unavailable\n";
    exit(1);
}

try {
    $stmt = $pdo->prepare('
        DELETE FROM password_reset_tokens
        WHERE used_at IS NOT NULL
           OR expires_at < CURRENT_TIMESTAMP
    ');
    $stmt->execute();

    echo "cleanup password_reset_tokens: removed {$stmt->rowCount()} rows\n";
    exit(0);
} catch (Throwable $e) {
    echo "Cleanup failed: " . $e->getMessage() . "\n";
    exit(1);
}

