<?php
/**
 * Админка офисов: создание таблиц под MySQL/SQLite, сид списка офисов,
 * пути на диск для загрузки фото офисов и сотрудников (без привязки только к DOCUMENT_ROOT).
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/office_photo_folders.php';

function th_office_admin_project_root(): string
{
    return dirname(__DIR__, 2);
}

function th_office_admin_is_mysql(PDO $pdo): bool
{
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
}

/**
 * Каталог загрузки фото офиса на диск (как на фронте: frontend/window/img/offices/...).
 */
function th_office_admin_office_photos_upload_dir(string $city, string $officeName): string
{
    $slug = th_office_admin_office_disk_slug($city, $officeName);
    return th_office_photo_disk_dir($city, $slug, true);
}

/** Публичный URL-префикс папки офиса (без завершающего слэша). */
function th_office_admin_office_photos_url_prefix(string $city, string $officeName): string
{
    $slug = th_office_admin_office_disk_slug($city, $officeName);
    return th_office_photos_url_prefix($city, $slug);
}

/** Slug папки офиса (как на фронте в get_office_photos_from_folder). */
function th_office_admin_office_slug(string $officeName): string
{
    return strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $officeName));
}

/**
 * Slug подкаталога с фото на диске (город + имя офиса; переопределения для дублей бренда).
 */
function th_office_admin_office_disk_slug(string $city, string $officeName): string
{
    return th_office_photo_folder_slug($city, $officeName);
}

/**
 * Каталог с фото офиса на диске (только каноническая раскладка репозитория).
 *
 * @return list<string>
 */
function th_office_admin_office_photo_disk_dirs(string $city, string $officeName): array
{
    $slug = th_office_admin_office_disk_slug($city, $officeName);
    return [th_office_photo_disk_dir($city, $slug, false)];
}

/** Удаляет файлы изображений в папках офиса (не трогает .gitkeep). Возвращает число удалённых файлов. */
function th_office_admin_delete_office_disk_photos(string $city, string $officeName): int
{
    $deleted = 0;
    foreach (th_office_admin_office_photo_disk_dirs($city, $officeName) as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        $files = @scandir($dir);
        if ($files === false) {
            continue;
        }
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === '.gitkeep') {
                continue;
            }
            $fp = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_file($fp) && @unlink($fp)) {
                $deleted++;
            }
        }
    }
    return $deleted;
}

function th_office_admin_employees_upload_dir(string $city): string
{
    $root = th_office_admin_project_root();
    $dir = $root . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'employees' . DIRECTORY_SEPARATOR . $city;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

/**
 * Разрешить веб-путь (/frontend/... или /img/...) в абсолютный путь в репозитории.
 */
function th_office_admin_web_path_to_disk(string $webPath): ?string
{
    $path = '/' . ltrim(str_replace('\\', '/', $webPath), '/');
    $root = str_replace('\\', '/', th_office_admin_project_root());
    if (strpos($path, '/frontend/') === 0 || strpos($path, '/img/') === 0) {
        return $root . $path;
    }
    return null;
}

/** Найти файл на диске по URL-пути из админки (репозиторий или DOCUMENT_ROOT). */
function th_office_admin_resolve_disk_file(string $webPath): ?string
{
    $webPath = str_replace('\\', '/', $webPath);
    $candidates = [];
    $viaRoot = th_office_admin_web_path_to_disk($webPath);
    if ($viaRoot !== null) {
        $candidates[] = str_replace('\\', '/', $viaRoot);
    }
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $dr = rtrim(str_replace('\\', '/', (string) $_SERVER['DOCUMENT_ROOT']), '/');
        $candidates[] = $dr . '/' . ltrim($webPath, '/');
    }
    foreach ($candidates as $p) {
        if ($p !== '' && is_file($p)) {
            return $p;
        }
    }
    return null;
}

/**
 * Сканирование уже загруженных фото офисов с диска (две раскладки: репозиторий и legacy /img/offices).
 *
 * @return list<array{path: string, filename: string, city: string, office_slug: string, full_path: string}>
 */
function th_office_admin_scan_office_photos_on_disk(): array
{
    $root = str_replace('\\', '/', th_office_admin_project_root());
    $bases = [
        [$root . '/frontend/window/img/offices', '/frontend/window/img/offices'],
    ];
    $photos = [];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    foreach ($bases as [$diskBase, $urlBase]) {
        if (!is_dir($diskBase)) {
            continue;
        }
        $cities = @scandir($diskBase);
        if ($cities === false) {
            continue;
        }
        foreach ($cities as $city) {
            if ($city === '.' || $city === '..' || $city === '.gitkeep') {
                continue;
            }
            $cityDir = $diskBase . '/' . $city;
            if (!is_dir($cityDir)) {
                continue;
            }
            $officeDirs = @scandir($cityDir);
            if ($officeDirs === false) {
                continue;
            }
            foreach ($officeDirs as $officeDir) {
                if ($officeDir === '.' || $officeDir === '..' || $officeDir === '.gitkeep') {
                    continue;
                }
                $officePath = $cityDir . '/' . $officeDir;
                if (!is_dir($officePath)) {
                    continue;
                }
                $files = @scandir($officePath);
                if ($files === false) {
                    continue;
                }
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..' || $file === '.gitkeep') {
                        continue;
                    }
                    $filePath = $officePath . '/' . $file;
                    if (!is_file($filePath)) {
                        continue;
                    }
                    $ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedExtensions, true)) {
                        continue;
                    }
                    $relativePath = $urlBase . '/' . $city . '/' . $officeDir . '/' . $file;
                    $photos[] = [
                        'path' => $relativePath,
                        'filename' => $file,
                        'city' => $city,
                        'office_slug' => $officeDir,
                        'full_path' => $filePath,
                    ];
                }
            }
        }
    }

    return $photos;
}

/**
 * Обновляет поля офисов по актуальному сиду (совпадение city + name).
 * Нужно, чтобы админка «фото офисов» и страницы офисов не расходились с кодом после смены телефона/графика.
 */
function th_office_admin_sync_seed_office_fields(PDO $pdo, array $seed): void
{
    $upd = $pdo->prepare('UPDATE offices SET address = ?, phone = ?, email = ?, working_hours = ?, description = ? WHERE city = ? AND name = ?');
    foreach ($seed as $row) {
        [$name, $city, $address, $phone, $email, $hours, $desc] = $row;
        try {
            $upd->execute([$address, $phone, $email, $hours, $desc, $city, $name]);
        } catch (PDOException $e) {
            error_log('[offices] sync seed fields: ' . $e->getMessage());
        }
    }
}

/**
 * Переносит сотрудников/фото с дубля «Amex tour» на канонический офис Anex на Московском шоссе и удаляет дубль.
 */
function th_office_admin_merge_samara_amex_into_anex_msk(PDO $pdo): void
{
    $canonName = 'Anex Tour (Московское шоссе, 81Б)';
    $city = 'samara';

    $qCanon = $pdo->prepare('SELECT id FROM offices WHERE city = ? AND name = ? LIMIT 1');
    $qCanon->execute([$city, $canonName]);
    $canonId = $qCanon->fetchColumn();

    $qAmex = $pdo->prepare("SELECT id FROM offices WHERE city = ? AND (name = 'Amex tour' OR name = 'Amex Tour') LIMIT 1");
    $qAmex->execute([$city]);
    $amexId = $qAmex->fetchColumn();
    if (!$amexId) {
        return;
    }
    $amexId = (int) $amexId;

    if ($canonId && (int) $canonId !== $amexId) {
        $keepId = (int) $canonId;
        try {
            $pdo->prepare('UPDATE office_employees SET office_id = ? WHERE office_id = ?')->execute([$keepId, $amexId]);
        } catch (PDOException $e) {
            error_log('[offices] merge employees: ' . $e->getMessage());
        }
        try {
            $pdo->prepare('UPDATE office_photos SET office_id = ? WHERE office_id = ?')->execute([$keepId, $amexId]);
        } catch (PDOException $e) {
            error_log('[offices] merge office_photos: ' . $e->getMessage());
        }
        try {
            $pdo->prepare('DELETE FROM offices WHERE id = ?')->execute([$amexId]);
        } catch (PDOException $e) {
            error_log('[offices] delete amex duplicate: ' . $e->getMessage());
        }
        return;
    }

    if (!$canonId) {
        $addr = 'г. Самара, Московское шоссе, 81Б';
        $phone = '+7 (846) 255-25-63';
        $email = 'hello@travelhub63.ru';
        $hours = 'пн–сб: 10:00–20:00, вс: 10:00–16:00';
        $desc = 'Офис Anex Tour на Московском шоссе.';
        try {
            $pdo->prepare('UPDATE offices SET name = ?, address = ?, phone = ?, email = ?, working_hours = ?, description = ? WHERE id = ?')
                ->execute([$canonName, $addr, $phone, $email, $hours, $desc, $amexId]);
        } catch (PDOException $e) {
            error_log('[offices] rename amex to anex msk: ' . $e->getMessage());
        }
    }
}

/** Удаляет закрытый офис на Парковой из списка offices (если остался в старой БД). */
function th_office_admin_remove_samara_parkovaya_office(PDO $pdo): void
{
    try {
        $pdo->exec("DELETE FROM offices WHERE city = 'samara' AND name LIKE '%Парков%'");
    } catch (PDOException $e) {
        error_log('[offices] remove parkovaya: ' . $e->getMessage());
    }
}

/**
 * ТЦ «Гудок»: бывший Anex Tour → Fun&Sun с новым телефоном.
 * Переименовывает запись в БД, чтобы не потерять сотрудников/фото.
 */
function th_office_admin_rename_samara_gudok_to_funsun(PDO $pdo): void
{
    $newName = 'Fun&Sun (ТЦ «Гудок»)';
    $phone = '+7 (846) 255-01-15';
    $address = 'г. Самара, ТЦ «Гудок», ул. Красноармейская, 131 (цокольный этаж, напротив входа в гипермаркет «Лента»)';
    $desc = 'Офис Fun&Sun в ТЦ «Гудок». Специализация на пляжном отдыхе и семейных турах.';

    try {
        // Уже переименован — только синхронизируем телефон/адрес.
        $chk = $pdo->prepare("SELECT id FROM offices WHERE city = 'samara' AND name = ? LIMIT 1");
        $chk->execute([$newName]);
        $newId = $chk->fetchColumn();
        if ($newId) {
            $pdo->prepare('UPDATE offices SET address = ?, phone = ?, description = ? WHERE id = ?')
                ->execute([$address, $phone, $desc, (int) $newId]);
        }

        // Старое имя Anex Tour в Самаре (Гудок переименован в Fun&Sun).
        $old = $pdo->prepare("SELECT id FROM offices WHERE city = 'samara' AND name = 'Anex Tour' LIMIT 1");
        $old->execute();
        $oldId = $old->fetchColumn();
        if (!$oldId) {
            return;
        }

        if ($newId && (int) $newId !== (int) $oldId) {
            // Дубль: переносим сотрудников/фото на новую запись и удаляем старую.
            try {
                $pdo->prepare('UPDATE office_employees SET office_id = ? WHERE office_id = ?')
                    ->execute([(int) $newId, (int) $oldId]);
            } catch (PDOException $e) {
                error_log('[offices] gudok merge employees: ' . $e->getMessage());
            }
            try {
                $pdo->prepare('UPDATE office_photos SET office_id = ? WHERE office_id = ?')
                    ->execute([(int) $newId, (int) $oldId]);
            } catch (PDOException $e) {
                error_log('[offices] gudok merge photos: ' . $e->getMessage());
            }
            $pdo->prepare('DELETE FROM offices WHERE id = ?')->execute([(int) $oldId]);
            return;
        }

        $pdo->prepare('UPDATE offices SET name = ?, address = ?, phone = ?, description = ? WHERE id = ?')
            ->execute([$newName, $address, $phone, $desc, (int) $oldId]);
    } catch (PDOException $e) {
        error_log('[offices] rename gudok to funsun: ' . $e->getMessage());
    }
}

/** Оставляет на сайте только актуальные офисы: Самара — 5, Москва — 2. */
function th_office_admin_purge_non_public_offices(PDO $pdo): void
{
    $keep = [
        'samara' => [
            'Fun&Sun',
            'Fun&Sun (ТЦ «Гудок»)',
            'Anex Tour (Московское шоссе, 81Б)',
            'Anex Tour (ТЦ «Апельсин»)',
            'Coral Travel',
        ],
        'moscow' => [
            'Anex Tour',
            'Coral Elite Service',
        ],
    ];

    foreach ($keep as $city => $names) {
        $placeholders = implode(',', array_fill(0, count($names), '?'));
        try {
            $params = array_merge([$city], $names);
            $pdo->prepare("DELETE FROM offices WHERE city = ? AND name NOT IN ($placeholders)")
                ->execute($params);
        } catch (PDOException $e) {
            error_log('[offices] purge non-public ' . $city . ': ' . $e->getMessage());
        }
    }
}

/**
 * Создаёт таблицы и при необходимости заполняет offices (если таблица пуста или нет ключевых офисов).
 */
function th_office_admin_bootstrap(PDO $pdo): void
{
    $mysql = th_office_admin_is_mysql($pdo);

    if ($mysql) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `offices` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `city` VARCHAR(64) NOT NULL,
            `address` TEXT NULL,
            `phone` VARCHAR(128) NULL,
            `email` VARCHAR(255) NULL,
            `working_hours` VARCHAR(255) NULL,
            `description` TEXT NULL,
            `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_offices_city` (`city`),
            KEY `idx_offices_city_name` (`city`, `name`(128))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `office_employees` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `office_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `position` VARCHAR(255) NULL,
            `phone` VARCHAR(64) NULL,
            `email` VARCHAR(255) NULL,
            `photo` VARCHAR(512) NULL,
            `info` TEXT NULL,
            `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_office_employees_office` (`office_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `office_photos` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `office_id` INT UNSIGNED NOT NULL,
            `image_url` VARCHAR(512) NOT NULL,
            `title` VARCHAR(255) NULL,
            `description` TEXT NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_office_photos_office` (`office_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS offices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            city TEXT NOT NULL,
            address TEXT,
            phone TEXT,
            email TEXT,
            working_hours TEXT,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS office_employees (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            office_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            position TEXT,
            phone TEXT,
            email TEXT,
            photo TEXT,
            info TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS office_photos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            office_id INTEGER NOT NULL,
            image_url TEXT NOT NULL,
            title TEXT,
            description TEXT,
            sort_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    $seed = [
        ['Fun&Sun', 'samara', 'г. Самара, Молл «Парк Хаус», Московское шоссе, 81Б, 2 этаж, рядом с МФЦ', '+7 (846) 254-16-56', 'hello@travelhub63.ru', 'пн–сб: 10:00–20:00, вс: 10:00–16:00', 'Специализация на пляжном отдыхе и семейных турах.'],
        ['Fun&Sun (ТЦ «Гудок»)', 'samara', 'г. Самара, ТЦ «Гудок», ул. Красноармейская, 131 (цокольный этаж, напротив входа в гипермаркет «Лента»)', '+7 (846) 255-01-15', 'hello@travelhub63.ru', 'Пн-Пт: 9:00 - 20:00, Сб-Вс: 10:00 - 18:00', 'Офис Fun&Sun в ТЦ «Гудок». Специализация на пляжном отдыхе и семейных турах.'],
        ['Anex Tour (Московское шоссе, 81Б)', 'samara', 'г. Самара, Московское шоссе, 81Б', '+7 (846) 255-25-63', 'hello@travelhub63.ru', 'пн–сб: 10:00–20:00, вс: 10:00–16:00', 'Офис Anex Tour на Московском шоссе.'],
        ['Anex Tour (ТЦ «Апельсин»)', 'samara', 'г. Самара, ТЦ «Апельсин», ул. Ново-Садовая, 305А (1 этаж, офис 105)', '+7 (846) 955-01-70', 'hello@travelhub63.ru', 'ежедневно 10:00–19:00', 'Офис Anex Tour в ТЦ «Апельсин».'],
        ['Coral Travel', 'samara', 'г. Самара, ТЦ «Эль Рио», Московское шоссе, 205', '+7 (846) 250-03-06', 'hello@travelhub63.ru', 'пн–сб 10:00–20:00, вс 11:00–20:00', 'Международный туроператор Coral Travel в Самаре.'],
        ['Anex Tour', 'moscow', 'г. Москва, Первомайская ул., 42, этаж 1', '+7 (499) 322-02-89', 'moscow@travelhub63.ru', 'Пн-Пт: 9:00 - 21:00, Сб-Вс: 10:00 - 18:00', 'Офис Anex Tour в Москве.'],
        ['Coral Elite Service', 'moscow', 'г. Москва, Первомайская ул., 42, этаж 1', '+7 (495) 660-36-66', 'moscow@travelhub63.ru', 'ежедневно 10:00–20:00', 'Элитный сервис Coral Elite Service в Москве.'],
    ];

    $ins = $pdo->prepare('INSERT INTO offices (name, city, address, phone, email, working_hours, description) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $chk = $pdo->prepare('SELECT id FROM offices WHERE city = ? AND name = ? LIMIT 1');

    foreach ($seed as $row) {
        [$name, $city, $address, $phone, $email, $hours, $desc] = $row;
        $chk->execute([$city, $name]);
        if (!$chk->fetchColumn()) {
            $ins->execute([$name, $city, $address, $phone, $email, $hours, $desc]);
        }
    }

    th_office_admin_sync_seed_office_fields($pdo, $seed);
    th_office_admin_merge_samara_amex_into_anex_msk($pdo);
    th_office_admin_remove_samara_parkovaya_office($pdo);
    th_office_admin_rename_samara_gudok_to_funsun($pdo);
    th_office_admin_purge_non_public_offices($pdo);
}
