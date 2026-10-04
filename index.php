<?php
// Универсальное перенаправление на frontend/index.php
// Работает как для корня домена, так и для подкаталога (/website).
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/'));
$basePath = str_replace('\\', '/', dirname($scriptName));
$basePath = rtrim($basePath, '/.');
if ($basePath === '' || $basePath === '/') {
    $target = '/frontend/index.php';
} else {
    $target = $basePath . '/frontend/index.php';
}
header('Location: ' . $target);
exit;
