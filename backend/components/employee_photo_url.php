<?php
declare(strict_types=1);

/**
 * Фото сотрудников: в БД путь вида /img/employees/{город}/{файл}.
 * Href: если файл лежит под DOCUMENT_ROOT/img/employees — прямой URL для nginx; иначе, если PHP видит файл
 * в корне репозитория (img/employees) — /backend/api/employee-photo.php?p=...; иначе прямой URL (как в админке).
 */
if (!function_exists('th_employee_photo_project_root')) {
    function th_employee_photo_project_root(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $env = getenv('TRAVELHUB_PROJECT_ROOT');
        if (is_string($env) && $env !== '' && is_dir($env)) {
            return $cached = rtrim(str_replace('\\', '/', $env), '/');
        }
        // backend/components → два уровня вверх = корень репозитория
        $fromComponents = str_replace('\\', '/', dirname(__DIR__, 2));
        $emp = $fromComponents . '/img/employees';
        if (is_dir(str_replace('/', DIRECTORY_SEPARATOR, $emp))) {
            return $cached = $fromComponents;
        }
        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $dr = rtrim(str_replace('\\', '/', (string) $_SERVER['DOCUMENT_ROOT']), '/');
            $empDr = $dr . '/img/employees';
            if (is_dir(str_replace('/', DIRECTORY_SEPARATOR, $empDr))) {
                return $cached = $dr;
            }
        }
        return $cached = $fromComponents;
    }
}

if (!function_exists('th_employee_photo_employees_rel')) {
    /**
     * Относительный путь под img/employees (напр. samara/photo.png) или null.
     */
    function th_employee_photo_employees_rel(?string $photo): ?string
    {
        $photo = trim((string) ($photo ?? ''));
        if ($photo === '') {
            return null;
        }
        if (preg_match('#\Ahttps?://#i', $photo)) {
            return null;
        }
        if (strpos($photo, '%') !== false) {
            $photo = rawurldecode(str_replace('+', ' ', $photo));
        }
        $path = str_replace('\\', '/', $photo);
        $path = '/' . ltrim($path, '/');
        if (preg_match('#\A/img/employees/#i', $path)) {
            $rel = substr($path, strlen('/img/employees/'));
        } elseif (preg_match('#\Aimg/employees/#i', ltrim($photo, '/'))) {
            $rel = preg_replace('#\Aimg/employees/#i', '', ltrim($photo, '/'));
        } else {
            return null;
        }
        $rel = str_replace('\\', '/', $rel);
        $rel = ltrim($rel, '/');
        if ($rel === '' || strpos($rel, '..') !== false) {
            return null;
        }
        return $rel;
    }
}

if (!function_exists('th_employee_photo_resolve_disk_from_rel')) {
    /**
     * Абсолютный путь к файлу на диске или null.
     */
    function th_employee_photo_resolve_disk_from_rel(string $rel): ?string
    {
        $rel = str_replace('\\', '/', $rel);
        if ($rel === '' || strpos($rel, '..') !== false) {
            return null;
        }
        $root = str_replace('\\', '/', th_employee_photo_project_root());
        $relFs = str_replace('/', DIRECTORY_SEPARATOR, $rel);
        $candidates = [
            $root . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'employees' . DIRECTORY_SEPARATOR . $relFs,
            $root . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'window' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'employees' . DIRECTORY_SEPARATOR . $relFs,
        ];
        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $dr = rtrim(str_replace('\\', '/', (string) $_SERVER['DOCUMENT_ROOT']), '/');
            $candidates[] = str_replace('/', DIRECTORY_SEPARATOR, $dr . '/img/employees/' . $rel);
        }
        foreach ($candidates as $disk) {
            if (is_file($disk)) {
                return $disk;
            }
        }
        return null;
    }
}

if (!function_exists('th_employee_photo_public_endpoint')) {
    /**
     * Базовый URL скрипта отдачи фото.
     */
    function th_employee_photo_public_endpoint(): string
    {
        if (php_sapi_name() === 'cli') {
            return '/backend/api/employee-photo.php';
        }
        $sn = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        if ($sn !== '' && preg_match('#^(.+)/backend/api/#', $sn, $m)) {
            return rtrim($m[1], '/') . '/backend/api/employee-photo.php';
        }
        if ($sn !== '' && preg_match('#^(.+)/backend/#', $sn, $m)) {
            return rtrim($m[1], '/') . '/backend/api/employee-photo.php';
        }
        if ($sn !== '' && preg_match('#^/frontend/window/#', $sn)) {
            return '/backend/api/employee-photo.php';
        }
        if ($sn !== '' && preg_match('#^(.+)/frontend/window/#', $sn, $m)) {
            return rtrim($m[1], '/') . '/backend/api/employee-photo.php';
        }
        return '/backend/api/employee-photo.php';
    }
}

if (!function_exists('th_employee_photo_public_href')) {
    function th_employee_photo_public_href(?string $photo): string
    {
        $photo = trim((string) ($photo ?? ''));
        if ($photo === '') {
            return '';
        }
        if (preg_match('#\Ahttps?://#i', $photo)) {
            return $photo;
        }
        $rel = th_employee_photo_employees_rel($photo);
        if ($rel === null) {
            $path = '/' . ltrim(str_replace('\\', '/', $photo), '/');
            return $path;
        }
        $rel = preg_replace('#/+#', '/', str_replace('\\', '/', $rel));
        $rel = ltrim($rel, '/');
        $staticHref = '/img/employees/' . $rel;

        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $dr = rtrim(str_replace('\\', '/', (string) $_SERVER['DOCUMENT_ROOT']), '/');
            $underDoc = str_replace('/', DIRECTORY_SEPARATOR, $dr . '/img/employees/' . $rel);
            if (is_file($underDoc)) {
                return $staticHref;
            }
        }

        $disk = th_employee_photo_resolve_disk_from_rel($rel);
        if ($disk !== null) {
            return th_employee_photo_public_endpoint() . '?p=' . rawurlencode($rel);
        }

        return $staticHref;
    }
}
