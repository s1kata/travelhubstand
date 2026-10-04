<?php
/**
 * API для отдачи оптимизированного hero-изображения (WebP).
 * Сначала отдаёт готовые home-hero-*.webp, иначе генерирует из PNG в кэш.
 * Использование: /backend/api/hero-image.php?w=960
 */
$heroDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'window' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'hero';
$allowedWidths = [640, 960, 1280, 1920];
$maxWidth = min(1920, max(320, (int)($_GET['w'] ?? 960)));
$serveWidth = 1920;
foreach ($allowedWidths as $w) {
    if ($maxWidth <= $w) {
        $serveWidth = $w;
        break;
    }
}

$staticWebp = $heroDir . DIRECTORY_SEPARATOR . 'home-hero-' . $serveWidth . '.webp';
if (is_file($staticWebp)) {
    header('Content-Type: image/webp');
    header('Cache-Control: public, max-age=31536000, immutable');
    header('Content-Length: ' . filesize($staticWebp));
    readfile($staticWebp);
    exit;
}

$heroPath = null;
foreach (['e978c0767c0fe7bc778596c86b2b54f3 1.png', 'e978c0767c0fe7bc778596c86b2b54f3%201.png', 'e978c0767c0fe7bc778596c86b2b54f3%201.png'] as $f) {
    $p = $heroDir . DIRECTORY_SEPARATOR . str_replace('%20', ' ', $f);
    if (file_exists($p)) { $heroPath = $p; break; }
    if (file_exists($heroDir . DIRECTORY_SEPARATOR . $f)) { $heroPath = $heroDir . DIRECTORY_SEPARATOR . $f; break; }
}

$cacheDir = dirname(__DIR__, 2) . '/data/cache';
$cacheFile = $cacheDir . '/hero-' . $serveWidth . '.webp';

if (!$heroPath || !file_exists($heroPath)) {
    header('HTTP/1.1 404 Not Found');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Hero image not found';
    exit;
}

if (file_exists($cacheFile) && filemtime($cacheFile) >= filemtime($heroPath)) {
    header('Content-Type: image/webp');
    header('Cache-Control: public, max-age=31536000, immutable');
    header('Content-Length: ' . filesize($cacheFile));
    readfile($cacheFile);
    exit;
}

if (!function_exists('imagewebp') || !function_exists('imagecreatefrompng')) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    readfile($heroPath);
    exit;
}

$img = @imagecreatefrompng($heroPath);
if (!$img) {
    header('HTTP/1.1 500 Internal Server Error');
    exit;
}

$w = imagesx($img);
$h = imagesy($img);
if ($w > $serveWidth) {
    $ratio = $serveWidth / $w;
    $newW = $serveWidth;
    $newH = (int)($h * $ratio);
    $resized = imagecreatetruecolor($newW, $newH);
    if ($resized) {
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transp = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefill($resized, 0, 0, $transp);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($img);
        $img = $resized;
    }
}

if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
if (is_dir($cacheDir) && is_writable($cacheDir)) {
    imagewebp($img, $cacheFile, 80);
}

header('Content-Type: image/webp');
header('Cache-Control: public, max-age=31536000, immutable');
imagewebp($img, null, 80);
imagedestroy($img);
exit;
