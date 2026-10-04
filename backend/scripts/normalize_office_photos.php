<?php
/**
 * Перенос фото офисов в каноническую папку и создание 01-cover.* где нет обложки.
 * Запуск: php backend/scripts/normalize_office_photos.php
 */
declare(strict_types=1);

require __DIR__ . '/../config/offices_catalog.php';

$repo = dirname(__DIR__, 2);
$legacyRoot = $repo . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'offices';

/** @var list<array{city:string, slug:string, label:string}> */
$offices = array_map(static function (array $o): array {
    return [
        'city' => (string) $o['city'],
        'slug' => (string) $o['photo_slug'],
        'label' => (string) $o['name'],
    ];
}, th_offices_raw());

function th_norm_copy_tree(string $from, string $to): int
{
    if (!is_dir($from)) {
        return 0;
    }
    if (!is_dir($to)) {
        mkdir($to, 0755, true);
    }
    $copied = 0;
    foreach (scandir($from) ?: [] as $file) {
        if ($file === '.' || $file === '..' || $file === '.gitkeep') {
            continue;
        }
        $src = $from . DIRECTORY_SEPARATOR . $file;
        $dst = $to . DIRECTORY_SEPARATOR . $file;
        if (is_dir($src)) {
            if ($file === '_old' || str_starts_with($file, '_')) {
                continue;
            }
            $copied += th_norm_copy_tree($src, $dst);
            continue;
        }
        if (!is_file($src)) {
            continue;
        }
        $ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            continue;
        }
        if (!is_file($dst)) {
            copy($src, $dst);
            $copied++;
            echo "copy {$file} -> " . str_replace('\\', '/', $to) . PHP_EOL;
        }
    }

    return $copied;
}

function th_norm_has_cover(string $dir): bool
{
    foreach (scandir($dir) ?: [] as $file) {
        if (!is_file($dir . DIRECTORY_SEPARATOR . $file)) {
            continue;
        }
        $base = strtolower(pathinfo($file, PATHINFO_FILENAME));
        if (preg_match('/^01[-_]?cover$/', $base)) {
            return true;
        }
    }

    return false;
}

function th_norm_pick_cover_source(string $dir): ?string
{
    $photos = [];
    foreach (scandir($dir) ?: [] as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (!is_file($path)) {
            continue;
        }
        $ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            continue;
        }
        $photos[] = $file;
    }
    if ($photos === []) {
        return null;
    }
    usort($photos, static function (string $a, string $b): int {
        return strcmp(th_office_photo_sort_key($a), th_office_photo_sort_key($b));
    });

    return $photos[0];
}

$copiedTotal = 0;
foreach ($offices as $office) {
    $city = $office['city'];
    $slug = $office['slug'];
    $dest = th_office_photo_disk_dir($city, $slug, true);
    $legacy = $legacyRoot . DIRECTORY_SEPARATOR . $city . DIRECTORY_SEPARATOR . $slug;
    $destHasPhotos = th_norm_pick_cover_source($dest) !== null;
    if (!$destHasPhotos) {
        $copiedTotal += th_norm_copy_tree($legacy, $dest);
    }

    if (!th_norm_has_cover($dest)) {
        $source = th_norm_pick_cover_source($dest);
        if ($source !== null) {
            $ext = pathinfo($source, PATHINFO_EXTENSION);
            $coverName = '01-cover.' . $ext;
            $coverPath = $dest . DIRECTORY_SEPARATOR . $coverName;
            if (!is_file($coverPath)) {
                copy($dest . DIRECTORY_SEPARATOR . $source, $coverPath);
                echo "cover {$coverName} <- {$source} ({$office['label']})" . PHP_EOL;
            }
        }
    }
}

echo PHP_EOL . "Done. Copied {$copiedTotal} file(s) from legacy img/offices." . PHP_EOL . PHP_EOL;

foreach (th_offices_catalog() as $o) {
    $ok = ($o['photos_count'] ?? 0) > 0 ? 'OK' : 'MISSING';
    echo str_pad($ok, 8), str_pad($o['slug'], 28), basename((string) $o['cover']), ' (', $o['photos_count'], ')', PHP_EOL;
}
