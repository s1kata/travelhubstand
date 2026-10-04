<?php
/**
 * Отдача фото сотрудника: GET ?p=город/файл.png (относительно img/employees/).
 * Публичный URL: /backend/api/employee-photo.php?p=...
 */
declare(strict_types=1);

require_once __DIR__ . '/../components/employee_photo_url.php';

$p = isset($_GET['p']) ? (string) $_GET['p'] : '';
$p = rawurldecode(str_replace('+', ' ', $p));
$p = str_replace('\\', '/', $p);
$p = ltrim($p, '/');
if ($p === '' || strpos($p, '..') !== false) {
    http_response_code(404);
    exit;
}
if (preg_match('#[\x00-\x08\x0b\x0c\x0e-\x1f<>|\\\\*?"\x7f]#', $p)) {
    http_response_code(404);
    exit;
}

$disk = th_employee_photo_resolve_disk_from_rel($p);
if ($disk === null || !is_file($disk)) {
    http_response_code(404);
    exit;
}

$root = th_employee_photo_project_root();
$rootReal = realpath($root) ?: $root;
$fileReal = realpath($disk) ?: $disk;
$rootFs = str_replace('\\', '/', $rootReal);
$fileFs = str_replace('\\', '/', $fileReal);
$rp = rtrim($rootFs, '/');
$isWin = (stripos(PHP_OS, 'WIN') === 0);
$underRoot = $isWin ? (stripos($fileFs, $rp) === 0) : (strpos($fileFs, $rp) === 0);
$tail = $underRoot ? substr($fileFs, strlen($rp)) : '';
$okUnderRepo = $underRoot && (preg_match('#^/img/employees/#', $tail) || preg_match('#^/frontend/window/img/employees/#', $tail));
$okUnderDocroot = false;
if (!$okUnderRepo && !empty($_SERVER['DOCUMENT_ROOT'])) {
    $dr = realpath((string) $_SERVER['DOCUMENT_ROOT']) ?: rtrim(str_replace('\\', '/', (string) $_SERVER['DOCUMENT_ROOT']), '/');
    $drFs = str_replace('\\', '/', $dr);
    $ud = $isWin ? (stripos($fileFs, rtrim($drFs, '/')) === 0) : (strpos($fileFs, rtrim($drFs, '/')) === 0);
    if ($ud) {
        $t2 = substr($fileFs, strlen(rtrim($drFs, '/')));
        $okUnderDocroot = (bool) preg_match('#^/img/employees/#', $t2);
    }
}
if (!$okUnderRepo && !$okUnderDocroot) {
    http_response_code(404);
    exit;
}

$ext = strtolower(pathinfo($fileReal, PATHINFO_EXTENSION));
$types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
];
$mime = $types[$ext] ?? 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
readfile($fileReal);
