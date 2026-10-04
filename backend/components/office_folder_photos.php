<?php
/**
 * Фото офиса из канонической папки репозитория:
 * frontend/window/img/offices/{city}/{slug}/
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/office_photo_folders.php';

/**
 * @param string|null $folderSlug Явный slug подкаталога из offices_catalog.photo_slug
 * @return list<array{image_url: string, filename: string, title: string}>
 */
function get_office_photos_from_folder(string $city, string $officeNameOrSlug, ?string $folderSlug = null): array
{
    $city = strtolower(trim($city));
    if ($folderSlug !== null && $folderSlug !== '') {
        $officeSlug = strtolower(preg_replace('/[^a-z0-9\-]/', '', $folderSlug));
    } else {
        $officeSlug = th_office_photo_folder_slug($city, $officeNameOrSlug);
    }
    if ($officeSlug === '') {
        return [];
    }

    $baseDir = th_office_photo_disk_dir($city, $officeSlug, false);
    if (!is_dir($baseDir)) {
        return [];
    }

    $urlPrefix = th_office_photos_url_prefix($city, $officeSlug);
    $files = @scandir($baseDir);
    if ($files === false) {
        return [];
    }

    $photos = [];
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || $file === '.gitkeep') {
            continue;
        }
        $filePath = $baseDir . DIRECTORY_SEPARATOR . $file;
        if (!is_file($filePath)) {
            continue;
        }
        $ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            continue;
        }
        $photos[] = [
            'image_url' => $urlPrefix . '/' . rawurlencode($file),
            'filename' => $file,
            'title' => pathinfo($file, PATHINFO_FILENAME),
        ];
    }

    usort($photos, static function (array $a, array $b): int {
        $ka = th_office_photo_sort_key((string) $a['filename']);
        $kb = th_office_photo_sort_key((string) $b['filename']);
        $cmp = strcmp($ka, $kb);
        if ($cmp !== 0) {
            return $cmp;
        }

        return strcmp((string) $a['filename'], (string) $b['filename']);
    });

    return $photos;
}
