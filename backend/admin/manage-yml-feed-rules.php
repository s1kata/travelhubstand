<?php
/**
 * @deprecated Перенесено на manage-yandex-feed.php#yml-feed-rules
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? null) !== 'admin') {
    header('Location: ../../frontend/window/login.php');
    exit;
}

header('Location: manage-yandex-feed.php#yml-feed-rules', true, 302);
exit;
