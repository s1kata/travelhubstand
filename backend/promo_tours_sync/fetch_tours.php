<?php
/**
 * Синхронизация акционных туров в promo_tours:
 * 1) TourVisor через backend/api/tourvisor-proxy.php — те же параметры, что в country_promo_tours / promotions
 *    (type=search-cached, onlyPromo=1, departureId, countryId, даты +7/+21 дня, ночи 7–14, adults=2).
 * 2) Опционально: доп. JSON по PROMO_TOURS_JSON_URL (legacy).
 *
 * Запуск из корня проекта:
 *   php backend/promo_tours_sync/fetch_tours.php
 *
 * .env: SITE_URL или TOURVISOR_PROXY_URL, DB_*, опционально PROMO_TOURS_JSON_URL, PROMO_TOURS_SYNC_*
 */
declare(strict_types=1);

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/tourvisor_promo_sync.php';

promo_tours_ensure_directories($config);

$logFile = $config['log_file'];
$exitCode = 0;
$received = 0;
$inserted = 0;
$skipped = 0;
$errorMessage = '';
$tourvisorOk = 0;
$tourvisorFail = 0;
$tourvisorTotal = 0;

try {
    $pdo = require __DIR__ . '/db.php';
    promo_tours_ensure_schema($pdo, (string)$config['db']['driver']);

    $tv = promo_tours_sync_fetch_via_tourvisor($config);
    $tourvisorTotal = (int)$tv['countries_total'];
    $tourvisorOk = (int)$tv['countries_ok'];
    $tourvisorFail = (int)$tv['countries_failed'];
    $rows = $tv['rows'];

    if ($tourvisorTotal > 0 && $tourvisorFail === $tourvisorTotal && $rows === []) {
        throw new RuntimeException('TourVisor: все запросы стран завершились ошибкой: ' . implode('; ', $tv['errors']));
    }

    $jsonUrl = (string)($config['json_source_url'] ?? '');
    if ($jsonUrl !== '') {
        $raw = promo_tours_http_get($jsonUrl, (int)$config['http_timeout_seconds']);
        if ($raw === null) {
            throw new RuntimeException('Не удалось загрузить доп. JSON по PROMO_TOURS_JSON_URL.');
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('PROMO_TOURS_JSON_URL: ответ не JSON.');
        }
        foreach (promo_tours_extract_promo_rows($decoded) as $r) {
            $rows[] = $r;
        }
    }

    $rows = promo_tours_unique_by_tour_id($rows);
    $received = count($rows);

    $insertSql = promo_tours_build_insert_sql((string)$config['db']['driver']);
    $stmt = $pdo->prepare($insertSql);

    foreach ($rows as $row) {
        $stmt->execute([
            ':tour_id' => $row['tour_id'],
            ':country' => $row['country'],
            ':city' => $row['city'],
            ':onlypromo' => 1,
            ':status' => 'pending',
        ]);
        $n = $stmt->rowCount();
        if ($n > 0) {
            $inserted++;
        } else {
            $skipped++;
        }
    }
} catch (Throwable $e) {
    $exitCode = 2;
    $errorMessage = $e->getMessage();
}

$logLine = sprintf(
    '[%s] получено=%d добавлено=%d пропущено=%d tourvisor_стран=%d/%d',
    date('Y-m-d H:i:s'),
    $received,
    $inserted,
    $skipped,
    $tourvisorOk,
    $tourvisorTotal
);
if ($tourvisorFail > 0 && $errorMessage === '') {
    $logLine .= ' tourvisor_ошибок_стран=' . $tourvisorFail;
}
if ($errorMessage !== '') {
    $logLine .= ' ошибка=' . str_replace(["\r", "\n"], ' ', $errorMessage);
}

promo_tours_append_log($logFile, $logLine);

if ($isCli) {
    echo $logLine . PHP_EOL;
    exit($exitCode);
}

echo $logLine;
exit($exitCode);

// ——— Вспомогательные функции ———————————————————————————————————————————————

function promo_tours_ensure_directories(array $config): void
{
    foreach (['cache_dir', 'log_dir'] as $key) {
        $dir = $config[$key] ?? '';
        if ($dir === '' || is_dir($dir)) {
            continue;
        }
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Не удалось создать каталог: ' . $dir);
        }
    }
}

function promo_tours_append_log(string $path, string $line): void
{
    @file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function promo_tours_ensure_schema(PDO $pdo, string $driver): void
{
    if ($driver === 'mysql') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS promo_tours (
            tour_id VARCHAR(128) NOT NULL,
            country VARCHAR(255) NOT NULL DEFAULT '',
            city VARCHAR(255) NOT NULL DEFAULT '',
            onlypromo TINYINT(1) NOT NULL DEFAULT 1,
            yandex_id VARCHAR(128) NULL DEFAULT NULL,
            added_to_yandex TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (tour_id),
            KEY idx_promo_tours_status (status),
            KEY idx_promo_tours_yandex (added_to_yandex)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        return;
    }

    if ($driver === 'sqlite') {
        $pdo->exec('CREATE TABLE IF NOT EXISTS promo_tours (
            tour_id TEXT NOT NULL PRIMARY KEY,
            country TEXT NOT NULL DEFAULT \'\',
            city TEXT NOT NULL DEFAULT \'\',
            onlypromo INTEGER NOT NULL DEFAULT 1,
            yandex_id TEXT,
            added_to_yandex INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT \'pending\',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_promo_tours_status ON promo_tours(status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_promo_tours_yandex ON promo_tours(added_to_yandex)');
        return;
    }

    throw new RuntimeException('Схема promo_tours не определена для драйвера: ' . $driver);
}

function promo_tours_build_insert_sql(string $driver): string
{
    if ($driver === 'sqlite') {
        return 'INSERT OR IGNORE INTO promo_tours (tour_id, country, city, onlypromo, yandex_id, added_to_yandex, status)
            VALUES (:tour_id, :country, :city, :onlypromo, NULL, 0, :status)';
    }
    return 'INSERT IGNORE INTO promo_tours (tour_id, country, city, onlypromo, yandex_id, added_to_yandex, status)
        VALUES (:tour_id, :country, :city, :onlypromo, NULL, 0, :status)';
}

function promo_tours_is_only_promo(mixed $value): bool
{
    return $value === 1 || $value === '1' || $value === true;
}

/**
 * Проверка флага акции на туре; при отсутствии — на карточке отеля (если передана).
 */
function promo_tours_row_is_promo(array $tour, ?array $hotel = null): bool
{
    if (array_key_exists('onlypromo', $tour) || array_key_exists('onlyPromo', $tour)) {
        $v = $tour['onlypromo'] ?? $tour['onlyPromo'];
        return promo_tours_is_only_promo($v);
    }
    if ($hotel !== null && (array_key_exists('onlypromo', $hotel) || array_key_exists('onlyPromo', $hotel))) {
        $v = $hotel['onlypromo'] ?? $hotel['onlyPromo'];
        return promo_tours_is_only_promo($v);
    }
    return false;
}

function promo_tours_name_from_bucket(mixed $bucket, string ...$keys): string
{
    if (!is_array($bucket)) {
        return '';
    }
    foreach ($keys as $k) {
        if (!empty($bucket[$k]) && is_string($bucket[$k])) {
            return trim($bucket[$k]);
        }
    }
    return '';
}

/**
 * Собирает tour_id, country, city из пары отель + тур (формат TourVisor / смешанные фиды).
 *
 * @return array{tour_id: string, country: string, city: string}|null
 */
function promo_tours_build_record_from_hotel_tour(array $hotel, array $tour): ?array
{
    if (!promo_tours_row_is_promo($tour, $hotel)) {
        return null;
    }
    $tid = $tour['id'] ?? $tour['tour_id'] ?? null;
    if ($tid === null || $tid === '') {
        return null;
    }
    $country = promo_tours_name_from_bucket($tour['country'] ?? null, 'name', 'russianName');
    if ($country === '') {
        $country = promo_tours_name_from_bucket($hotel['country'] ?? null, 'name', 'russianName');
    }
    $city = promo_tours_name_from_bucket($tour['region'] ?? null, 'name', 'russianName');
    if ($city === '') {
        $city = promo_tours_name_from_bucket($hotel['region'] ?? null, 'name', 'russianName');
    }
    if ($city === '' && isset($tour['city']) && is_string($tour['city'])) {
        $city = trim($tour['city']);
    }

    return [
        'tour_id' => (string)$tid,
        'country' => $country,
        'city' => $city,
    ];
}

/**
 * Из «плоского» объекта тура (если JSON — список туров без обёртки отеля).
 *
 * @return array{tour_id: string, country: string, city: string}|null
 */
function promo_tours_build_record_from_flat_tour(array $tour): ?array
{
    if (!promo_tours_row_is_promo($tour, null)) {
        return null;
    }
    $tid = $tour['id'] ?? $tour['tour_id'] ?? null;
    if ($tid === null || $tid === '') {
        return null;
    }
    $country = promo_tours_name_from_bucket($tour['country'] ?? null, 'name', 'russianName');
    $city = promo_tours_name_from_bucket($tour['region'] ?? null, 'name', 'russianName');
    if ($city === '' && isset($tour['city']) && is_string($tour['city'])) {
        $city = trim($tour['city']);
    }

    return [
        'tour_id' => (string)$tid,
        'country' => $country,
        'city' => $city,
    ];
}

/**
 * @return list<array{tour_id: string, country: string, city: string}>
 */
function promo_tours_extract_promo_rows(array $json): array
{
    $out = [];

    if (isset($json['tours']) && is_array($json['tours'])) {
        foreach ($json['tours'] as $tour) {
            if (!is_array($tour)) {
                continue;
            }
            $rec = promo_tours_build_record_from_flat_tour($tour);
            if ($rec !== null) {
                $out[] = $rec;
            }
        }
        return $out;
    }

    $list = $json;
    if (isset($json['data']) && is_array($json['data'])) {
        $list = $json['data'];
    }

    if (promo_tours_array_is_list($list)) {
        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (!empty($item['tours']) && is_array($item['tours'])) {
                foreach ($item['tours'] as $tour) {
                    if (!is_array($tour)) {
                        continue;
                    }
                    $rec = promo_tours_build_record_from_hotel_tour($item, $tour);
                    if ($rec !== null) {
                        $out[] = $rec;
                    }
                }
                continue;
            }
            $rec = promo_tours_build_record_from_flat_tour($item);
            if ($rec !== null) {
                $out[] = $rec;
            }
        }
    }

    return $out;
}

/**
 * Список с последовательными ключами 0..n (совместимо с PHP 7.4).
 */
function promo_tours_array_is_list(array $arr): bool
{
    if (function_exists('array_is_list')) {
        return array_is_list($arr);
    }
    $i = 0;
    foreach ($arr as $k => $_) {
        if ($k !== $i) {
            return false;
        }
        $i++;
    }
    return true;
}

function promo_tours_unique_by_tour_id(array $rows): array
{
    $seen = [];
    $out = [];
    foreach ($rows as $row) {
        $id = $row['tour_id'];
        if (isset($seen[$id])) {
            continue;
        }
        $seen[$id] = true;
        $out[] = $row;
    }
    return $out;
}
