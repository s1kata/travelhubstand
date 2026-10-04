<?php
/**
 * API услуг
 * GET — получение списка услуг из кэша (БД). Кэш обновляется раз в 3–4 часа.
 * POST action=import — ручной импорт (требует admin)
 *
 * Источник данных: Tourvisor API (hotel-group-services)
 * https://api.tourvisor.ru/search/docs#section/Servis-poiska-turov
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

session_start();

// TTL кэша: по умолчанию 4 часа
$cacheHours = (float)(getenv('SERVICES_CACHE_TTL_HOURS') ?: ($_ENV['SERVICES_CACHE_TTL_HOURS'] ?? 4));
$cacheHours = max(1, min(24, $cacheHours));
define('SERVICES_CACHE_TTL', (int)($cacheHours * 3600));

const TOURVISOR_BASE = 'https://api.tourvisor.ru/search/api/v1';

function logServices(string $msg, array $context = []): void {
    $line = date('Y-m-d H:i:s') . ' [services] ' . $msg;
    if (!empty($context)) {
        $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
    }
    error_log($line);
    $file = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'services_api.log';
    $dir = dirname($file);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
}

function jsonResponse(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
}

function getSyncFile(): string {
    $projectRoot = dirname(__DIR__, 2);
    $dataDir = $projectRoot . DIRECTORY_SEPARATOR . 'data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
    return $dataDir . DIRECTORY_SEPARATOR . '.services_sync_at';
}

function isCacheStale(): bool {
    $file = getSyncFile();
    if (!file_exists($file)) return true;
    $ts = (int)trim(file_get_contents($file));
    return (time() - $ts) >= SERVICES_CACHE_TTL;
}

function markCacheUpdated(): void {
    file_put_contents(getSyncFile(), (string)time());
}

/** Запрос к Tourvisor API с JWT-авторизацией (cURL для корректной работы SSL) */
function tourvisorRequest(string $endpoint, string $jwt, ?int $countryId = null, ?array $regionIds = null): array {
    $url = TOURVISOR_BASE . $endpoint;
    $pairs = [];
    if ($countryId !== null) $pairs[] = 'countryId=' . urlencode((string)$countryId);
    if (!empty($regionIds)) {
        foreach ($regionIds as $rid) {
            $pairs[] = 'regionIds=' . urlencode((string)$rid);
        }
    }
    if (!empty($pairs)) $url .= '?' . implode('&', $pairs);

    logServices('Tourvisor request', ['url' => $url, 'endpoint' => $endpoint]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . trim($jwt),
            'Accept: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
    ]);

    $response = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errNo = curl_errno($ch);
    $errMsg = curl_error($ch);
    curl_close($ch);

    if ($errNo !== 0) {
        logServices('Tourvisor FAILED: cURL error', ['url' => $url, 'errno' => $errNo, 'error' => $errMsg]);
        return ['ok' => false, 'error' => 'Tourvisor request failed: ' . $errMsg, 'data' => null];
    }

    $data = is_string($response) ? json_decode($response, true) : null;

    if ($code >= 400) {
        logServices('Tourvisor FAILED: HTTP ' . $code, ['url' => $url, 'body' => substr((string)$response, 0, 500)]);
        return ['ok' => false, 'error' => 'Tourvisor HTTP ' . $code, 'data' => $data];
    }

    $count = is_array($data) ? count($data) : 0;
    logServices('Tourvisor OK: connected', ['url' => $url, 'http' => $code, 'groups' => $count]);
    return ['ok' => true, 'data' => $data];
}

/**
 * Импорт из Tourvisor hotel-group-services
 * Документация: https://api.tourvisor.ru/search/docs
 * Формат ответа: [{ id, name, items: [{ id, name }] }, ...]
 */
function runImportTourvisor(PDO $pdo): array {
    $jwt = getenv('TOURVISOR_JWT_TOKEN') ?: ($_ENV['TOURVISOR_JWT_TOKEN'] ?? '');
    if (empty($jwt)) {
        logServices('Import SKIP: TOURVISOR_JWT_TOKEN not configured');
        return ['ok' => false, 'error' => 'TOURVISOR_JWT_TOKEN not configured in .env'];
    }

    logServices('Import start: Tourvisor hotel-group-services', ['token_preview' => substr($jwt, 0, 20) . '...']);

    $countryId = getenv('TOURVISOR_SERVICES_COUNTRY_ID') ? (int)getenv('TOURVISOR_SERVICES_COUNTRY_ID') : null;
    $regionIds = null;
    $r = getenv('TOURVISOR_SERVICES_REGION_IDS');
    if (!empty($r)) {
        $regionIds = array_map('intval', array_filter(explode(',', $r)));
    }

    $result = tourvisorRequest('/hotel-group-services', $jwt, $countryId, $regionIds);
    if (!$result['ok'] || $result['data'] === null) {
        logServices('Import FAILED', ['error' => $result['error'] ?? 'Tourvisor API error']);
        return ['ok' => false, 'error' => $result['error'] ?? 'Tourvisor API error'];
    }

    $groups = is_array($result['data']) ? $result['data'] : [];
    $items = [];
    $order = 0;
    foreach ($groups as $group) {
        if (!is_array($group)) continue;
        $groupName = trim((string)($group['name'] ?? ''));
        $groupItems = $group['items'] ?? [];
        if (!is_array($groupItems)) continue;
        foreach ($groupItems as $it) {
            if (!is_array($it)) continue;
            $name = trim((string)($it['name'] ?? ''));
            if (empty($name)) continue;
            $itemId = (int)($it['id'] ?? 0);
            $groupId = (int)($group['id'] ?? 0);
            $extId = 'tv_' . $groupId . '_' . $itemId;
            $items[] = [
                'name' => $name,
                'description' => $groupName ?: '',
                'price' => 0,
                'url' => '',
                'available' => true,
                'external_id' => $extId,
                'display_order' => $order++,
            ];
        }
    }

    $saved = saveServicesToDb($pdo, $items);
    logServices('Import done', ['imported' => $saved['imported'], 'total' => $saved['total'], 'errors' => count($saved['errors'] ?? [])]);
    return $saved;
}

/**
 * Импорт из кастомного API (SERVICES_API_URL)
 */
function runImportCustom(PDO $pdo): array {
    $apiUrl = getenv('SERVICES_API_URL') ?: ($_ENV['SERVICES_API_URL'] ?? '');
    if (empty($apiUrl)) {
        return ['ok' => false, 'error' => 'SERVICES_API_URL not configured'];
    }
    $opts = ['http' => ['timeout' => 30, 'ignore_errors' => true]];
    $response = @file_get_contents($apiUrl, false, stream_context_create($opts));
    if ($response === false) {
        return ['ok' => false, 'error' => 'Failed to fetch from external API'];
    }
    $data = json_decode($response, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Invalid JSON from API'];
    }
    $raw = $data['services'] ?? $data['data'] ?? $data['items'] ?? (isset($data[0]) ? $data : []);
    if (!is_array($raw)) {
        return ['ok' => false, 'error' => 'No services array in API response'];
    }
    $items = [];
    foreach ($raw as $idx => $item) {
        if (!is_array($item)) continue;
        $name = trim((string)($item['name'] ?? $item['title'] ?? $item['title_ru'] ?? ''));
        if (empty($name)) continue;
        $items[] = [
            'name' => $name,
            'price' => (float)str_replace([',', ' '], ['.', ''], (string)($item['price'] ?? $item['cost'] ?? 0)),
            'description' => trim((string)($item['description'] ?? $item['desc'] ?? '')),
            'url' => trim((string)($item['url'] ?? $item['link'] ?? '')),
            'available' => isset($item['available']) ? (bool)$item['available'] : true,
            'display_order' => (int)($item['display_order'] ?? $item['order'] ?? $idx),
            'external_id' => $item['id'] ?? $item['external_id'] ?? null,
        ];
    }
    return saveServicesToDb($pdo, $items);
}

function saveServicesToDb(PDO $pdo, array $items): array {
    $imported = 0;
    $errors = [];
    foreach ($items as $it) {
        $name = $it['name'];
        $price = (float)($it['price'] ?? 0);
        $description = trim((string)($it['description'] ?? ''));
        $url = trim((string)($it['url'] ?? ''));
        $available = ($it['available'] ?? true) ? 1 : 0;
        $displayOrder = (int)($it['display_order'] ?? 0);
        $externalId = $it['external_id'] ?? null;

        try {
            if ($externalId !== null && $externalId !== '') {
                $stmt = $pdo->prepare("SELECT id FROM services WHERE name = ? LIMIT 1");
                $stmt->execute([$name]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM services WHERE name = ? LIMIT 1");
                $stmt->execute([$name]);
            }
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $stmt = $pdo->prepare("UPDATE services SET name=?, price=?, description=?, url=?, available=?, display_order=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
                $stmt->execute([$name, $price, $description, $url, $available, $displayOrder, $existing['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO services (name, price, description, url, available, display_order) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $price, $description, $url, $available, $displayOrder]);
            }
            $imported++;
        } catch (PDOException $e) {
            $errors[] = "Item '{$name}': " . $e->getMessage();
        }
    }
    markCacheUpdated();
    return ['ok' => true, 'imported' => $imported, 'total' => count($items), 'errors' => $errors];
}

function runImport(PDO $pdo): array {
    $jwt = getenv('TOURVISOR_JWT_TOKEN') ?: ($_ENV['TOURVISOR_JWT_TOKEN'] ?? '');
    if (!empty($jwt)) {
        return runImportTourvisor($pdo);
    }
    return runImportCustom($pdo);
}

function canRefreshCache(): bool {
    $jwt = getenv('TOURVISOR_JWT_TOKEN') ?: ($_ENV['TOURVISOR_JWT_TOKEN'] ?? '');
    $custom = getenv('SERVICES_API_URL') ?: ($_ENV['SERVICES_API_URL'] ?? '');
    return !empty($jwt) || !empty($custom);
}

if (!$pdo) {
    jsonResponse(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Проверка и создание таблицы services при отсутствии
$driverName = 'unknown';
try {
    $driverName = strtolower((string)($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) ?? ''));
    $isMysql = ($driverName === 'mysql');
    if ($isMysql) {
        $exists = $pdo->query("SHOW TABLES LIKE 'services'")->rowCount() > 0;
    } else {
        $exists = $pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='services'")->fetchColumn() !== false;
    }
    if (!$exists) {
        if ($isMysql) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS services (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                description TEXT,
                url VARCHAR(500) DEFAULT NULL,
                available TINYINT(1) DEFAULT 1,
                display_order INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_services_available (available),
                INDEX idx_services_display_order (display_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS services (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                price DECIMAL(10,2) NOT NULL DEFAULT 0,
                description TEXT,
                url VARCHAR(500),
                available INTEGER DEFAULT 1,
                display_order INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_services_available ON services(available)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_services_display_order ON services(display_order)");
        }
        logServices('Table services created (auto-migration)', ['driver' => $driverName]);
    }
} catch (PDOException $e) {
    logServices('Migration error', ['msg' => $e->getMessage(), 'driver' => $driverName]);
    jsonResponse(['success' => false, 'error' => 'Migration failed: ' . $e->getMessage()]);
    exit;
}

// POST: ручной импорт (требует admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? ($_REQUEST['action'] ?? '');
    
    // Чтение JSON body для application/json
    if (empty($action) && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        $input = file_get_contents('php://input');
        $body = json_decode($input, true);
        $action = $body['action'] ?? '';
    }

    if ($action === 'import') {
        if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? null) !== 'admin') {
            jsonResponse(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }
        $result = runImport($pdo);
        if (!$result['ok']) {
            jsonResponse(['success' => false, 'error' => $result['error'] ?? 'Import failed']);
            exit;
        }
        jsonResponse([
            'success' => true,
            'imported' => $result['imported'],
            'total' => $result['total'],
            'errors' => $result['errors'] ?? [],
        ]);
        exit;
    }
}

// GET: получение из кэша (БД). Если кэш устарел — обновляем и возвращаем из кэша.
if (isCacheStale() && canRefreshCache()) {
    logServices('Cache stale, refreshing from Tourvisor');
    $importResult = runImport($pdo);
    if (!$importResult['ok']) {
        logServices('Cache refresh failed', ['error' => $importResult['error'] ?? 'unknown']);
    }
}

$availableOnly = !isset($_GET['all']) || $_GET['all'] !== '1';

$sql = "SELECT id, name, price, description, url, available, display_order FROM services";
if ($availableOnly) {
    $sql .= " WHERE available = 1";
}
$sql .= " ORDER BY display_order ASC, name ASC";

try {
    $stmt = $pdo->query($sql);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Нормализация типов
    foreach ($services as &$s) {
        $s['price'] = (float)($s['price'] ?? 0);
        $s['available'] = (int)($s['available'] ?? 1);
        $s['display_order'] = (int)($s['display_order'] ?? 0);
    }

    if (isset($_GET['debug'])) {
        logServices('GET response', ['services_count' => count($services)]);
        jsonResponse(['success' => true, 'services' => $services, '_debug' => ['from_cache' => true, 'count' => count($services)]]);
    } else {
        jsonResponse(['success' => true, 'services' => $services]);
    }
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()]);
}
