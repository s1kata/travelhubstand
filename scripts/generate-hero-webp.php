<?php
$heroDir = dirname(__DIR__) . '/frontend/window/img/hero';
$candidates = [
    'e978c0767c0fe7bc778596c86b2b54f3 1.png',
    'e978c0767c0fe7bc778596c86b2b54f3%201.png',
];
$heroPath = null;
foreach ($candidates as $f) {
    $p = $heroDir . DIRECTORY_SEPARATOR . $f;
    if (is_file($p)) {
        $heroPath = $p;
        break;
    }
}
if (!$heroPath) {
    fwrite(STDERR, "Hero PNG not found\n");
    exit(1);
}
if (!function_exists('imagewebp')) {
    fwrite(STDERR, "imagewebp not available\n");
    exit(1);
}
$img = @imagecreatefrompng($heroPath);
if (!$img) {
    fwrite(STDERR, "Failed to read PNG\n");
    exit(1);
}
$w = imagesx($img);
$h = imagesy($img);
echo "Source: {$w}x{$h}\n";
foreach ([640, 960, 1280, 1920] as $maxW) {
    $targetW = min($maxW, $w);
    $targetH = (int) round($h * ($targetW / $w));
    $resized = imagecreatetruecolor($targetW, $targetH);
    imagealphablending($resized, false);
    imagesavealpha($resized, true);
    imagecopyresampled($resized, $img, 0, 0, 0, 0, $targetW, $targetH, $w, $h);
    $out = $heroDir . '/home-hero-' . $maxW . '.webp';
    imagewebp($resized, $out, 78);
    imagedestroy($resized);
    echo basename($out) . ' ' . filesize($out) . " bytes\n";
}
imagedestroy($img);
