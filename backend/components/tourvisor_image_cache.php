<?php
declare(strict_types=1);

if (!function_exists('th_project_root')) {
    function th_project_root(): string
    {
        return dirname(__DIR__, 2);
    }
}

/**
 * Серверный кэш байтов картинок Tourvisor (data/tourvisor_image_cache).
 * Файлы с истёкшим TTL удаляются только при повторном запросе — без cron папка растёт без ограничений.
 */

if (!function_exists('th_tourvisor_image_cache_dir')) {
    function th_tourvisor_image_cache_dir(): string
    {
        $explicit = trim((string) (getenv('TOURVISOR_IMAGE_CACHE_DIR') ?: ($_ENV['TOURVISOR_IMAGE_CACHE_DIR'] ?? '')));
        if ($explicit !== '') {
            return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $explicit), DIRECTORY_SEPARATOR);
        }

        return th_project_root() . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'tourvisor_image_cache';
    }
}

if (!function_exists('th_tourvisor_image_cache_ttl_seconds')) {
    function th_tourvisor_image_cache_ttl_seconds(): int
    {
        $days = (int) (getenv('TOURVISOR_IMAGE_CACHE_TTL_DAYS') ?: ($_ENV['TOURVISOR_IMAGE_CACHE_TTL_DAYS'] ?? 30));

        return min(max($days, 1), 365) * 86400;
    }
}

if (!function_exists('th_tourvisor_image_cache_max_bytes')) {
    function th_tourvisor_image_cache_max_bytes(): int
    {
        $mb = (int) (getenv('TOURVISOR_IMAGE_CACHE_MAX_MB') ?: ($_ENV['TOURVISOR_IMAGE_CACHE_MAX_MB'] ?? 0));
        if ($mb <= 0) {
            return 0;
        }

        return min(max($mb, 64), 8192) * 1024 * 1024;
    }
}

/**
 * @return list<string>
 */
function th_tourvisor_image_cache_list_files(string $cacheDir): array
{
    if (!is_dir($cacheDir)) {
        return [];
    }

    $files = glob($cacheDir . DIRECTORY_SEPARATOR . '*') ?: [];
    $out = [];
    foreach ($files as $path) {
        if (!is_file($path)) {
            continue;
        }
        $base = basename($path);
        if ($base === '' || str_ends_with($base, '.tmp')) {
            continue;
        }
        $out[] = $path;
    }

    return $out;
}

/**
 * @return array{files:int,bytes:int,oldest:int|null,newest:int|null}
 */
function th_tourvisor_image_cache_stats(?string $cacheDir = null): array
{
    $cacheDir = $cacheDir ?? th_tourvisor_image_cache_dir();
    $files = th_tourvisor_image_cache_list_files($cacheDir);
    $bytes = 0;
    $oldest = null;
    $newest = null;

    foreach ($files as $path) {
        $size = (int) @filesize($path);
        $mtime = @filemtime($path);
        $bytes += $size;
        if ($mtime === false) {
            continue;
        }
        if ($oldest === null || $mtime < $oldest) {
            $oldest = $mtime;
        }
        if ($newest === null || $mtime > $newest) {
            $newest = $mtime;
        }
    }

    return [
        'files' => count($files),
        'bytes' => $bytes,
        'oldest' => $oldest,
        'newest' => $newest,
    ];
}

/**
 * Удалить файлы старше $maxAgeSeconds (0 = TTL из .env).
 *
 * @return array{deleted:int,freed_bytes:int,errors:int}
 */
function th_tourvisor_image_cache_purge_expired(?string $cacheDir = null, int $maxAgeSeconds = 0): array
{
    $cacheDir = $cacheDir ?? th_tourvisor_image_cache_dir();
    $ttl = $maxAgeSeconds > 0 ? $maxAgeSeconds : th_tourvisor_image_cache_ttl_seconds();
    $threshold = time() - $ttl;

    $deleted = 0;
    $freed = 0;
    $errors = 0;

    foreach (th_tourvisor_image_cache_list_files($cacheDir) as $path) {
        $mtime = @filemtime($path);
        if ($mtime === false) {
            $errors++;
            continue;
        }
        if ($mtime >= $threshold) {
            continue;
        }
        $size = (int) @filesize($path);
        if (@unlink($path)) {
            $deleted++;
            $freed += $size;
        } else {
            $errors++;
        }
    }

    return ['deleted' => $deleted, 'freed_bytes' => $freed, 'errors' => $errors];
}

/**
 * Удалить самые старые файлы, пока суммарный размер не станет <= $targetBytes.
 *
 * @return array{deleted:int,freed_bytes:int,errors:int}
 */
function th_tourvisor_image_cache_purge_to_size(?string $cacheDir = null, int $targetBytes = 0): array
{
    $cacheDir = $cacheDir ?? th_tourvisor_image_cache_dir();
    if ($targetBytes <= 0) {
        return ['deleted' => 0, 'freed_bytes' => 0, 'errors' => 0];
    }

    $entries = [];
    foreach (th_tourvisor_image_cache_list_files($cacheDir) as $path) {
        $mtime = @filemtime($path);
        if ($mtime === false) {
            continue;
        }
        $entries[] = [
            'path' => $path,
            'mtime' => $mtime,
            'size' => (int) @filesize($path),
        ];
    }

    $total = array_sum(array_column($entries, 'size'));
    if ($total <= $targetBytes) {
        return ['deleted' => 0, 'freed_bytes' => 0, 'errors' => 0];
    }

    usort($entries, static fn(array $a, array $b): int => $a['mtime'] <=> $b['mtime']);

    $deleted = 0;
    $freed = 0;
    $errors = 0;

    foreach ($entries as $entry) {
        if ($total <= $targetBytes) {
            break;
        }
        if (@unlink($entry['path'])) {
            $deleted++;
            $freed += $entry['size'];
            $total -= $entry['size'];
        } else {
            $errors++;
        }
    }

    return ['deleted' => $deleted, 'freed_bytes' => $freed, 'errors' => $errors];
}

/**
 * Удалить все файлы кэша (разовая полная очистка на тесте).
 *
 * @return array{deleted:int,freed_bytes:int,errors:int}
 */
function th_tourvisor_image_cache_purge_all(?string $cacheDir = null): array
{
    $cacheDir = $cacheDir ?? th_tourvisor_image_cache_dir();
    $deleted = 0;
    $freed = 0;
    $errors = 0;

    foreach (th_tourvisor_image_cache_list_files($cacheDir) as $path) {
        $size = (int) @filesize($path);
        if (@unlink($path)) {
            $deleted++;
            $freed += $size;
        } else {
            $errors++;
        }
    }

    return ['deleted' => $deleted, 'freed_bytes' => $freed, 'errors' => $errors];
}

/**
 * Периодическая подрезка: просроченные + лимит из TOURVISOR_IMAGE_CACHE_MAX_MB.
 * Вызывается из image-proxy (~1% запросов) и из cron.
 *
 * @return array{expired:array<string,int>,trimmed:array<string,int>}
 */
function th_tourvisor_image_cache_maintain(?string $cacheDir = null): array
{
    $cacheDir = $cacheDir ?? th_tourvisor_image_cache_dir();
    if (!is_dir($cacheDir)) {
        return [
            'expired' => ['deleted' => 0, 'freed_bytes' => 0, 'errors' => 0],
            'trimmed' => ['deleted' => 0, 'freed_bytes' => 0, 'errors' => 0],
        ];
    }

    $expired = th_tourvisor_image_cache_purge_expired($cacheDir);
    $maxBytes = th_tourvisor_image_cache_max_bytes();
    $trimmed = ['deleted' => 0, 'freed_bytes' => 0, 'errors' => 0];
    if ($maxBytes > 0) {
        $target = (int) ($maxBytes * 0.85);
        $trimmed = th_tourvisor_image_cache_purge_to_size($cacheDir, $target);
    }

    return ['expired' => $expired, 'trimmed' => $trimmed];
}
