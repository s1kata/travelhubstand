<?php
/**
 * Удаление устаревших JSON-файлов кэша Tourvisor (data/tourvisor_cache).
 *
 * Запуск по SSH:
 *   cd /path/to/project && php clear_cache.php
 *   php clear_cache.php 7          — удалить файлы старше 7 дней (по умолчанию 10)
 *
 * Папка: переменная TOURVISOR_CACHE_DIR в .env, иначе <корень проекта>/data/tourvisor_cache
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Только CLI: php clear_cache.php [дней]\n");
    exit(1);
}

$projectRoot = __DIR__;

$envCandidates = [
    $projectRoot . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . '.env',
    $projectRoot . DIRECTORY_SEPARATOR . '.env',
];
foreach ($envCandidates as $envPath) {
    if (!is_file($envPath)) {
        continue;
    }
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        if ($k !== '') {
            putenv("$k=$v");
            $_ENV[$k] = $v;
        }
    }
    break;
}

$cacheDir = trim((string)(getenv('TOURVISOR_CACHE_DIR') ?: ($_ENV['TOURVISOR_CACHE_DIR'] ?? '')));
if ($cacheDir === '') {
    $cacheDir = $projectRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'tourvisor_cache';
}

$argv = $GLOBALS['argv'] ?? [];
$daysOld = 5;
if (isset($argv[1]) && is_numeric($argv[1])) {
    $daysOld = max(1, (int)$argv[1]);
}

$ttl = $daysOld * 86400;
$now = time();
$threshold = $now - $ttl;

$deleted = 0;
$deletedSize = 0;
$errors = 0;
$filePattern = '*.json';

if (!is_dir($cacheDir)) {
    fwrite(STDERR, "Папка кэша не найдена: {$cacheDir}\nПроверьте путь и права.\n");
    exit(1);
}

$globPath = $cacheDir . DIRECTORY_SEPARATOR . $filePattern;
$files = glob($globPath) ?: [];

foreach ($files as $path) {
    if (!is_file($path)) {
        continue;
    }
    $mtime = @filemtime($path);
    if ($mtime === false) {
        $errors++;
        continue;
    }
    if ($mtime >= $threshold) {
        continue;
    }
    $size = (int)@filesize($path);
    if (@unlink($path)) {
        $deleted++;
        $deletedSize += $size;
    } else {
        $errors++;
    }
}

echo "Папка: {$cacheDir}\n";
echo "Условие: *.json с датой изменения старше {$daysOld} дн.\n";

if ($deleted === 0) {
    echo "Удалено файлов: 0 (нет подходящих по возрасту или папка пуста).\n";
} else {
    echo "Удалено файлов: {$deleted}\n";
    echo 'Освобождено: ' . round($deletedSize / 1024 / 1024, 2) . " МБ\n";
}

if ($errors > 0) {
    fwrite(STDERR, "Ошибок (mtime/unlink): {$errors}\n");
    exit(1);
}

exit(0);
