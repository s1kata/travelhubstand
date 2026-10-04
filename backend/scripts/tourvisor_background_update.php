<?php
/**
 * Фоновое обновление кэша туров Tourvisor каждые 24 часа.
 * Поиск показывает только из кэша; этот скрипт наполняет кэш.
 *
 * Запуск:
 *   CLI: php backend/scripts/tourvisor_background_update.php
 *   При SSL timeout — через локальный прокси (сервер должен быть запущен):
 *     php backend/scripts/tourvisor_background_update.php --use-proxy
 *     или TOURVISOR_USE_PROXY=1 php ...
 *   Cron: 0 3 * * * cd /path/to/project && php backend/scripts/tourvisor_background_update.php
 */
declare(strict_types=1);

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    // HTTP: для внешнего cron (опционально)
    header('Content-Type: application/json; charset=utf-8');
}

$projectRoot = dirname(__DIR__, 2);
$dataDir = $projectRoot . DIRECTORY_SEPARATOR . 'data';
$lastUpdateFile = $dataDir . DIRECTORY_SEPARATOR . 'tourvisor_background_last_update.txt';
$updateIntervalSec = 24 * 3600; // 24 часа

// Загрузка .env без подключения к БД (чтобы избежать ошибки DB connection в CLI)
$envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
if (!file_exists($envPath)) $envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        if ($k !== '') {
            putenv("$k=$v");
            $_ENV[$k] = $v;
        }
    }
}

// Проверка: прошло ли 24 часа
$lastUpdate = 0;
if (file_exists($lastUpdateFile)) {
    $lastUpdate = (int)trim(file_get_contents($lastUpdateFile));
}
if ((time() - $lastUpdate) < $updateIntervalSec) {
    $msg = 'Кэш обновлён недавно, пропуск (следующее обновление через ' . round(($updateIntervalSec - (time() - $lastUpdate)) / 3600, 1) . ' ч)';
    if ($isCli) {
        echo $msg . "\n";
    } else {
        echo json_encode(['success' => true, 'message' => $msg, 'skipped' => true], JSON_UNESCAPED_UNICODE);
    }
    exit(0);
}

// Инициализация Tourvisor
$argv = $GLOBALS['argv'] ?? $_SERVER['argv'] ?? [];
$useProxy = in_array('--use-proxy', $argv, true) || filter_var(getenv('TOURVISOR_USE_PROXY') ?: ($_ENV['TOURVISOR_USE_PROXY'] ?? ''), FILTER_VALIDATE_BOOLEAN);
$proxyBase = rtrim(getenv('TOURVISOR_PROXY_URL') ?: ($_ENV['TOURVISOR_PROXY_URL'] ?? 'http://localhost:8888'), '/');
$GLOBALS['proxy_base'] = $proxyBase;
$GLOBALS['tv_base'] = rtrim(getenv('TOURVISOR_API_URL') ?: ($_ENV['TOURVISOR_API_URL'] ?? ''), '/') ?: 'https://api.tourvisor.ru/search/api/v1';

function bgLog(string $msg, array $ctx = []): void {
    $line = date('Y-m-d H:i:s') . ' [tourvisor-bg] ' . $msg;
    if (!empty($ctx)) $line .= ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE);
    error_log($line);
    $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($dir . DIRECTORY_SEPARATOR . 'tourvisor_api.log', $line . "\n", FILE_APPEND | LOCK_EX);
}

function bgCacheDir(): string {
    $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'tourvisor_cache';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return $dir;
}

function bgRequest(string $endpoint, array $params = [], bool $useProxy = false): array {
    if ($useProxy) {
        return bgRequestViaProxy($endpoint, $params);
    }
    $jwt = trim((string)(getenv('TOURVISOR_TOKEN') ?: ($_ENV['TOURVISOR_TOKEN'] ?? '')));
    if ($jwt === '') $jwt = trim((string)(getenv('TOURVISOR_JWT_TOKEN') ?: ($_ENV['TOURVISOR_JWT_TOKEN'] ?? '')));
    if (empty($jwt)) return ['success' => false, 'error' => 'TOURVISOR_TOKEN not configured'];

    $base = $GLOBALS['tv_base'] ?? 'https://api.tourvisor.ru/search/api/v1';
    $url = $base . $endpoint;
    if (!empty($params)) {
        $pairs = [];
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $vv) $pairs[] = urlencode($k) . '=' . urlencode((string)$vv);
            } elseif ($v !== '' && $v !== null) {
                $pairs[] = urlencode($k) . '=' . urlencode((string)$v);
            }
        }
        $url .= '?' . implode('&', $pairs);
    }

    $retries = 3;
    $err = '';
    $response = null;
    $code = 0;
    while ($retries > 0) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . trim($jwt), 'Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
        ]);
        $response = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if (!$err) break;
        $retryable = (stripos($err, 'timeout') !== false || stripos($err, 'SSL') !== false);
        if (!$retryable || $retries <= 1) break;
        sleep(5);
        $retries--;
    }

    if ($err) return ['success' => false, 'error' => $err];
    $data = is_string($response) ? json_decode($response, true) : null;
    if ($code >= 400) {
        return ['success' => false, 'error' => $data['error']['reason'] ?? $data['error']['message'] ?? 'HTTP ' . $code, 'data' => $data];
    }
    return ['success' => true, 'data' => $data];
}

/** Вызов через локальный прокси — обходит SSL timeout в CLI */
function bgRequestViaProxy(string $endpoint, array $params): array {
    $proxyBase = $GLOBALS['proxy_base'] ?? 'http://localhost:8888';
    $url = $proxyBase . '/backend/api/tourvisor-proxy.php?';
    if (str_contains($endpoint, '/status')) {
        $searchId = (int)preg_replace('/.*\/(\d+)\/status/', '$1', $endpoint);
        $url .= 'type=status&searchId=' . $searchId;
    } elseif (preg_match('#/search/(\d+)(?:\?|$)#', $endpoint, $m)) {
        $url .= 'type=results&searchId=' . (int)$m[1] . '&limit=' . (int)($params['limit'] ?? 50);
    } else {
        $url .= 'type=search&' . http_build_query($params);
    }
    $ctx = stream_context_create(['http' => ['timeout' => 90, 'ignore_errors' => true]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) return ['success' => false, 'error' => 'Proxy request failed'];
    $data = json_decode($raw, true);
    if (str_contains($endpoint, '/status')) return ['success' => !empty($data['success']), 'data' => $data['data'] ?? $data];
    if (preg_match('#/search/\d+(?:\?|$)#', $endpoint)) return ['success' => !empty($data['success']), 'data' => $data['data'] ?? []];
    // search
    if (!empty($data['searchId'])) return ['success' => true, 'data' => $data];
    if (!empty($data['data']['searchId'])) return ['success' => true, 'data' => $data['data']];
    return ['success' => false, 'error' => $data['error'] ?? 'Proxy error', 'data' => $data];
}

function bgMergeAllToursCache(array $newResults): void {
    $dir = bgCacheDir();
    $file = $dir . DIRECTORY_SEPARATOR . 'all_tours.json';
    $existing = [];
    if (file_exists($file)) {
        $d = json_decode(file_get_contents($file), true);
        $existing = (is_array($d) && isset($d['results'])) ? $d['results'] : [];
    }
    $ids = array_flip(array_map(fn($h) => $h['id'] ?? 0, $existing));
    foreach ($newResults as $h) {
        $id = $h['id'] ?? null;
        if ($id && !isset($ids[$id])) {
            $existing[] = $h;
            $ids[$id] = true;
        }
    }
    file_put_contents($file, json_encode(['results' => $existing, 'cachedAt' => time()], JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// Читаем страны и города вылета из кэша
$cacheDir = bgCacheDir();
$countries = [];
$departures = [];

foreach (['countries_onlyCharter_0_api.json', 'countries_onlyCharter_0.json', 'countries_departureId_2_onlyCharter_0.json'] as $f) {
    $path = $cacheDir . DIRECTORY_SEPARATOR . $f;
    if (file_exists($path)) {
        $d = json_decode(file_get_contents($path), true);
        if (!empty($d['success']) && is_array($d['data'])) {
            $countries = $d['data'];
            break;
        }
    }
}

foreach (['departures__api.json', 'departures_.json'] as $f) {
    $path = $cacheDir . DIRECTORY_SEPARATOR . $f;
    if (file_exists($path)) {
        $d = json_decode(file_get_contents($path), true);
        if (!empty($d['success']) && is_array($d['data'])) {
            $departures = $d['data'];
            break;
        }
    }
}

if (empty($countries) || empty($departures)) {
    bgLog('countries_or_departures_empty', ['countries' => count($countries), 'departures' => count($departures)]);
    if ($isCli) echo "Справочники пусты. Запустите warmup_tourvisor_cache.php.\n";
    else echo json_encode(['success' => false, 'error' => 'Справочники пусты'], JSON_UNESCAPED_UNICODE);
    exit(1);
}

$topDepartures = array_slice($departures, 0, 3);
$today = date('Y-m-d');
$dateTo = date('Y-m-d', strtotime('+14 days'));

bgLog('background_update_start', ['countries' => count($countries), 'departures' => count($topDepartures), 'useProxy' => $useProxy]);
if ($isCli) {
    echo "Обновление кэша: " . count($countries) . " стран × " . count($topDepartures) . " городов вылета";
    if ($useProxy) echo " (через локальный прокси: {$proxyBase})";
    echo "\n";
}

$totalMerged = 0;
foreach ($countries as $country) {
    $countryId = (int)($country['id'] ?? 0);
    if (!$countryId) continue;
    foreach ($topDepartures as $dep) {
        $departureId = (int)($dep['id'] ?? 0);
        if (!$departureId) continue;

        $params = [
            'departureId' => $departureId,
            'countryId' => $countryId,
            'dateFrom' => $today,
            'dateTo' => $dateTo,
            'nightsFrom' => 5,
            'nightsTo' => 9,
            'adults' => 2,
            'currency' => 'RUB',
            'onlyCharter' => 'false',
        ];

        $rSearch = bgRequest('/tours/search', $params, $useProxy);
        if (!$rSearch['success'] || empty($rSearch['data']['searchId'])) {
            bgLog('search_fail', ['countryId' => $countryId, 'departureId' => $departureId, 'error' => $rSearch['error'] ?? 'no searchId']);
            sleep(2);
            continue;
        }
        $searchId = (int)($rSearch['data']['searchId'] ?? $rSearch['data']['data']['searchId'] ?? 0);

        // Ждём завершения поиска (макс 2 мин)
        $attempts = 0;
        $maxAttempts = 40;
        while ($attempts < $maxAttempts) {
            sleep(3);
            $rStatus = bgRequest("/tours/search/{$searchId}/status", [], $useProxy);
            $progress = (int)($rStatus['data']['progress'] ?? 0);
            $status = $rStatus['data']['status'] ?? '';
            if ($status === 'complete' || $progress >= 99) break;
            $attempts++;
        }

        $rResults = bgRequest("/tours/search/{$searchId}", ['limit' => 50], $useProxy);
        if ($rResults['success'] && is_array($rResults['data']) && !empty($rResults['data'])) {
            $cnt = count($rResults['data']);
            bgMergeAllToursCache($rResults['data']);
            $totalMerged += $cnt;
            bgLog('merged', ['countryId' => $countryId, 'departureId' => $departureId, 'tours' => $cnt]);
            if ($isCli) echo "  dep{$departureId} cnt{$countryId}: +{$cnt} туров\n";
        }

        sleep(3); // rate limit
    }
}

file_put_contents($lastUpdateFile, (string)time(), LOCK_EX);
bgLog('background_update_done', ['totalMerged' => $totalMerged]);

if ($isCli) {
    echo "Готово. Обновлено туров: +{$totalMerged}. Кэш будет обновлён через 24 ч.\n";
} else {
    echo json_encode([
        'success' => true,
        'message' => 'Кэш обновлён',
        'totalMerged' => $totalMerged,
    ], JSON_UNESCAPED_UNICODE);
}
