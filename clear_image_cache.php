<?php
/**
 * Очистка серверного кэша картинок Tourvisor (data/tourvisor_image_cache).
 *
 * Просроченные файлы сами по себе не удаляются, пока их снова не запросят —
 * отсюда разрастание папки на проде. Этот скрипт — для разовой чистки и cron.
 *
 * Запуск по SSH:
 *   cd /path/to/project && php clear_image_cache.php
 *   php clear_image_cache.php 14              — удалить старше 14 дней
 *   php clear_image_cache.php --trim-mb=800   — оставить не больше ~800 МБ (самые старые первыми)
 *   php clear_image_cache.php 14 --trim-mb=1024
 *   php clear_image_cache.php --all             — удалить ВСЕ файлы кэша (тест)
 *   php clear_image_cache.php --stats         — только статистика, без удаления
 *
 * Папка: TOURVISOR_IMAGE_CACHE_DIR в .env, иначе data/tourvisor_image_cache
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Только CLI: php clear_image_cache.php [дней] [--all] [--trim-mb=N] [--stats]\n");
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

require_once $projectRoot . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';
require_once $projectRoot . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'tourvisor_image_cache.php';

$argv = $GLOBALS['argv'] ?? [];
$daysOld = 0;
$trimMb = 0;
$statsOnly = false;
$purgeAll = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--stats') {
        $statsOnly = true;
        continue;
    }
    if ($arg === '--all') {
        $purgeAll = true;
        continue;
    }
    if (preg_match('/^--trim-mb=(\d+)$/', $arg, $m)) {
        $trimMb = max(64, (int) $m[1]);
        continue;
    }
    if (is_numeric($arg)) {
        $daysOld = max(1, (int) $arg);
    }
}

$cacheDir = th_tourvisor_image_cache_dir();

if (!is_dir($cacheDir)) {
    fwrite(STDERR, "Папка кэша не найдена: {$cacheDir}\n");
    exit(1);
}

$before = th_tourvisor_image_cache_stats($cacheDir);
echo "Папка: {$cacheDir}\n";
echo 'Сейчас: ' . $before['files'] . ' файлов, '
    . round($before['bytes'] / 1024 / 1024, 2) . " МБ\n";

if ($statsOnly) {
    if ($before['oldest']) {
        echo 'Самый старый: ' . date('Y-m-d H:i', $before['oldest']) . "\n";
    }
    if ($before['newest']) {
        echo 'Самый новый: ' . date('Y-m-d H:i', $before['newest']) . "\n";
    }
    echo 'TTL из .env: ' . (th_tourvisor_image_cache_ttl_seconds() / 86400) . " дн.\n";
    $maxMb = th_tourvisor_image_cache_max_bytes();
    echo 'Лимит MAX_MB: ' . ($maxMb > 0 ? round($maxMb / 1024 / 1024) : 'не задан') . "\n";
    exit(0);
}

if ($purgeAll) {
    $all = th_tourvisor_image_cache_purge_all($cacheDir);
    echo "\nПолная очистка: удалено {$all['deleted']}, "
        . round($all['freed_bytes'] / 1024 / 1024, 2) . " МБ\n";
    $after = th_tourvisor_image_cache_stats($cacheDir);
    echo "После очистки: {$after['files']} файлов, "
        . round($after['bytes'] / 1024 / 1024, 2) . " МБ\n";
    if ($all['errors'] > 0) {
        fwrite(STDERR, "Ошибок: {$all['errors']}\n");
        exit(1);
    }
    exit(0);
}

$expiredDays = $daysOld > 0 ? $daysOld : (int) (th_tourvisor_image_cache_ttl_seconds() / 86400);
$expired = th_tourvisor_image_cache_purge_expired($cacheDir, $expiredDays * 86400);

echo "\nПросроченные (старше {$expiredDays} дн.): удалено {$expired['deleted']}, "
    . round($expired['freed_bytes'] / 1024 / 1024, 2) . " МБ\n";

$trimmed = ['deleted' => 0, 'freed_bytes' => 0, 'errors' => 0];
if ($trimMb > 0) {
    $trimmed = th_tourvisor_image_cache_purge_to_size($cacheDir, $trimMb * 1024 * 1024);
    echo "Подрезка до {$trimMb} МБ: удалено {$trimmed['deleted']}, "
        . round($trimmed['freed_bytes'] / 1024 / 1024, 2) . " МБ\n";
}

$after = th_tourvisor_image_cache_stats($cacheDir);
echo "\nПосле очистки: {$after['files']} файлов, "
    . round($after['bytes'] / 1024 / 1024, 2) . " МБ\n";

$errors = $expired['errors'] + $trimmed['errors'];
if ($errors > 0) {
    fwrite(STDERR, "Ошибок: {$errors}\n");
    exit(1);
}

exit(0);
