<?php
declare(strict_types=1);

require_once __DIR__ . '/../components/tourvisor_search_cover.php';

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
}

$projectRoot = dirname(dirname(__DIR__));
$envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
if (!is_file($envPath)) {
    $envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
}
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        $k = trim($k);
        if ($k !== '') {
            putenv($k . '=' . trim($v));
            $_ENV[$k] = trim($v);
        }
    }
}

$ttlHours = (float) (getenv('TOURVISOR_SEARCH_CACHE_TTL_HOURS') ?: ($_ENV['TOURVISOR_SEARCH_CACHE_TTL_HOURS'] ?? 336));
$maxAge = (int) (max(24, min(720, $ttlHours)) * 3600 * 2); // clean older than 2x ttl
$res = th_search_cover_cleanup($maxAge);

$out = [
    'success' => true,
    'cacheDir' => th_search_cover_cache_dir(),
    'maxAgeSec' => $maxAge,
    'removedIndex' => $res['removedIndex'] ?? 0,
    'removedFiles' => $res['removedFiles'] ?? 0,
    'kept' => $res['kept'] ?? 0,
];

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
exit(0);

