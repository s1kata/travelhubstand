<?php
/**
 * Фоновое обновление кэша акционных туров 2 раза в сутки:
 * — 12:00 по Москве
 * — 00:05 по Москве (12:05 ночи)
 *
 * Свежесть данных: 24 часа. Скрипт вызывается кроном; сам по себе не проверяет время.
 *
 * Запуск:
 *   CLI: php backend/scripts/promo_tours_refresh.php
 *   Cron (часовой пояс Europe/Moscow):
 *     0 12 * * * cd /path/to/project && php backend/scripts/promo_tours_refresh.php
 *     5 0 * * * cd /path/to/project && php backend/scripts/promo_tours_refresh.php
 *   Если сервер в UTC: 0 9 * * * (12:00 МСК = 09:00 UTC) и 5 21 * * * (00:05 МСК = 21:05 UTC).
 *
 * Требует: веб-сервер запущен для HTTP-запросов к прокси.
 * В .env: SITE_URL=https://travelhub63.ru (origin без /frontend) или TOURVISOR_PROXY_URL.
 * Публичный прокси на сайте — /frontend/api/tourvisor-proxy.php (см. tourvisor_proxy_http_base.php).
 */
declare(strict_types=1);

require_once __DIR__ . '/../components/tourvisor_proxy_http_base.php';

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
}

$projectRoot = dirname(__DIR__, 2);

// Загрузка .env
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

$proxyBase = get_tourvisor_proxy_http_base_url();

function promoLog(string $msg, array $ctx = []): void {
    $line = date('Y-m-d H:i:s') . ' [promo-refresh] ' . $msg;
    if (!empty($ctx)) $line .= ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE);
    error_log($line);
    $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents($dir . DIRECTORY_SEPARATOR . 'tourvisor_api.log', $line . "\n", FILE_APPEND | LOCK_EX);
}

// Страны берём из API TourVisor (type=countries, departureId=1) — только то, что публикует API
$countriesUrl = $proxyBase . (strpos($proxyBase, '?') !== false ? '&' : '?') . 'type=countries&departureId=1';
$promoCountries = [];
$ctxCountries = stream_context_create(['http' => ['timeout' => 30, 'ignore_errors' => true]]);
$rawCountries = @file_get_contents($countriesUrl, false, $ctxCountries);
if ($rawCountries !== false) {
    $countriesData = json_decode($rawCountries, true);
    if (!empty($countriesData['success']) && is_array($countriesData['data'])) {
        foreach ($countriesData['data'] as $c) {
            $promoCountries[] = ['id' => (int)($c['id'] ?? 0), 'name' => trim($c['name'] ?? $c['russianName'] ?? '')];
        }
    }
}
if (empty($promoCountries)) {
    $promoCountries = [
        ['id' => 12, 'name' => 'Турция'],
        ['id' => 13, 'name' => 'Египет'],
        ['id' => 14, 'name' => 'ОАЭ'],
        ['id' => 15, 'name' => 'Таиланд'],
        ['id' => 16, 'name' => 'Мальдивы'],
    ];
    promoLog('promo_countries_fallback', ['msg' => 'API countries unavailable, using fallback']);
}

$today = date('Y-m-d');
$dateFrom = date('Y-m-d', strtotime('+1 day'));
$dateTo = date('Y-m-d', strtotime('+60 days'));

$totalOk = 0;
$totalErr = 0;

promoLog('promo_refresh_start', ['countries' => count($promoCountries), 'proxyBase' => $proxyBase]);
if ($isCli) {
    echo "Обновление кэша акционных туров: " . count($promoCountries) . " стран\n";
}

foreach ($promoCountries as $country) {
    $countryId = (int)$country['id'];
    $params = http_build_query([
        'type' => 'search-cached',
        'departureId' => '1',
        'countryId' => (string)$countryId,
        'dateFrom' => $dateFrom,
        'dateTo' => $dateTo,
        'nightsFrom' => '3',
        'nightsTo' => '21',
        'adults' => '2',
        'onlyPromo' => '1',
        'live' => '1',
    ]);
    $url = $proxyBase . (str_contains($proxyBase, '?') ? '&' : '?') . $params;

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 180,
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => true],
    ]);
    $raw = @file_get_contents($url, false, $ctx);

    if ($raw === false) {
        promoLog('promo_refresh_fail', ['countryId' => $countryId, 'country' => $country['name'] ?? '', 'error' => 'HTTP request failed']);
        $totalErr++;
        if ($isCli) echo "  " . ($country['name'] ?? "cnt{$countryId}") . ": ошибка запроса\n";
        sleep(3);
        continue;
    }

    $data = json_decode($raw, true);
    $success = !empty($data['success']);
    $count = is_array($data['data'] ?? null) ? count($data['data']) : 0;

    if ($success) {
        $totalOk++;
        promoLog('promo_refresh_ok', ['countryId' => $countryId, 'country' => $country['name'] ?? '', 'hotels' => $count]);
        if ($isCli) echo "  " . ($country['name'] ?? "cnt{$countryId}") . ": {$count} отелей\n";
    } else {
        $totalErr++;
        $err = $data['error'] ?? 'Unknown error';
        promoLog('promo_refresh_fail', ['countryId' => $countryId, 'country' => $country['name'] ?? '', 'error' => $err]);
        if ($isCli) echo "  " . ($country['name'] ?? "cnt{$countryId}") . ": {$err}\n";
    }

    sleep(2); // пауза между странами
}

promoLog('promo_refresh_done', ['ok' => $totalOk, 'err' => $totalErr]);

// Фид YML для Яндекс.Бизнеса: синк акций в БД + пересборка export/services.yml (тот же публичный URL).
try {
    $cfg = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
    if (is_file($cfg)) {
        require_once $cfg;
    }
    if (!empty($pdo)) {
        require_once __DIR__ . '/../components/yandex_feed_sync.php';
        yandex_feed_ensure_table($pdo);
        if (yandex_feed_legacy_table_sync_enabled()) {
            yandex_feed_sync_from_tourvisor($pdo);
        } else {
            promoLog('yandex_feed_sync_skipped', ['reason' => 'YANDEX_LEGACY_OFFERS_TABLE_SYNC=0']);
        }
        require_once __DIR__ . '/generate_yml.php';
        if (function_exists('generate_services_yml')) {
            generate_services_yml($pdo);
        }
    }
} catch (Throwable $e) {
    promoLog('yandex_feed_after_promo', ['error' => $e->getMessage()]);
}

if ($isCli) {
    echo "Готово. Обновлено: {$totalOk}, ошибок: {$totalErr}. Свежесть кэша — 24 часа.\n";
} else {
    echo json_encode([
        'success' => true,
        'message' => 'Кэш акций обновлён',
        'ok' => $totalOk,
        'errors' => $totalErr,
    ], JSON_UNESCAPED_UNICODE);
}
