<?php
/**
 * @deprecated Совместимость: отдача через /backend/api/employee-photo.php
 */
declare(strict_types=1);

$p = isset($_GET['p']) ? trim((string) $_GET['p']) : '';
if ($p === '') {
    header('Location: /frontend/window/offices/samara-offices.php', true, 301);
    exit;
}

require __DIR__ . '/../../backend/api/employee-photo.php';
