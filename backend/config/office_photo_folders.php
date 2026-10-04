<?php
/**
 * Единая привязка офис → папка с фото на диске.
 * Ключ: slug из offices_catalog.php или db_name + city для админки.
 */
declare(strict_types=1);

/**
 * Явные slug подкаталогов (когда имя офиса в БД не совпадает с именем папки).
 *
 * @return array<string, string> "{city}|{db_name}" => folder slug
 */
function th_office_photo_folder_overrides(): array
{
    return [
        'samara|Fun&Sun' => 'fun-sun',
        'samara|Fun&Sun (ТЦ «Гудок»)' => 'fun-sun-gudok',
        'samara|Anex Tour (Московское шоссе, 81Б)' => 'anex-tour-moskovskoe-81b',
        'samara|Anex Tour (ТЦ «Апельсин»)' => 'anex-apelsin',
        'samara|Coral Travel' => 'coral-travel',
        'moscow|Coral Elite Service' => 'coral-elite-service',
        'moscow|Anex Tour' => 'anex-tour',
    ];
}

/** Канонический корень фото офисов в репозитории. */
function th_office_photos_repo_root(): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'frontend'
        . DIRECTORY_SEPARATOR . 'window' . DIRECTORY_SEPARATOR . 'img'
        . DIRECTORY_SEPARATOR . 'offices';
}

/** Публичный URL-префикс канонической папки офиса. */
function th_office_photos_url_prefix(string $city, string $folderSlug): string
{
    return '/frontend/window/img/offices/' . rawurlencode($city) . '/' . rawurlencode($folderSlug);
}

/**
 * Slug папки с фото по городу и имени офиса в БД (админка).
 */
function th_office_photo_folder_slug(string $city, string $officeName): string
{
    $key = strtolower(trim($city)) . '|' . $officeName;
    $overrides = th_office_photo_folder_overrides();
    if (isset($overrides[$key])) {
        return $overrides[$key];
    }

    return strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $officeName));
}

/**
 * Абсолютный путь к папке фото офиса (создаёт при необходимости).
 */
function th_office_photo_disk_dir(string $city, string $folderSlug, bool $mkdir = false): string
{
    $slug = strtolower(preg_replace('/[^a-z0-9\-]/', '', $folderSlug));
    $dir = th_office_photos_repo_root() . DIRECTORY_SEPARATOR . $city . DIRECTORY_SEPARATOR . $slug;
    if ($mkdir && !is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $dir;
}

/**
 * Приоритет обложки: 01-cover.* → cover.* → *-01.* → coral-01-* → остальное по имени.
 */
function th_office_photo_sort_key(string $filename): string
{
    $base = strtolower(pathinfo($filename, PATHINFO_FILENAME));
    if (preg_match('/^01[-_]?cover$/', $base)) {
        return '0-' . $filename;
    }
    if (preg_match('/^cover$/', $base)) {
        return '1-' . $filename;
    }
    if (preg_match('/(?:^|[-_])01$/', $base) || preg_match('/^coral-01/', $base)) {
        return '2-' . $filename;
    }
    if (preg_match('/[-_]1$/', $base)) {
        return '3-' . $filename;
    }

    return '9-' . $filename;
}
