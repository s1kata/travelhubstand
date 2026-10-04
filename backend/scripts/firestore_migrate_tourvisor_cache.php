<?php
declare(strict_types=1);

/**
 * Одноразовая (или повторная) миграция локального data/tourvisor_cache → Firestore.
 *
 *   php backend/scripts/firestore_migrate_tourvisor_cache.php
 *   php backend/scripts/firestore_migrate_tourvisor_cache.php --dry-run
 *   php backend/scripts/firestore_migrate_tourvisor_cache.php --only=search
 *   php backend/scripts/firestore_migrate_tourvisor_cache.php --only=dictionaries
 *   php backend/scripts/firestore_migrate_tourvisor_cache.php --purge-local   # удалить файл после успешной заливки
 *   php backend/scripts/firestore_migrate_tourvisor_cache.php --delay-ms=150
 *
 * Запускайте НА сервере хостинга (где лежат файлы кэша), с настроенным FIREBASE_*.
 */

$root = dirname(__DIR__, 2);
require_once $root . '/backend/config/config.php';
require_once $root . '/backend/components/api/firestore-helper.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$purgeLocal = in_array('--purge-local', $argv ?? [], true);
$only = 'all';
$delayMs = 100;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with((string) $arg, '--only=')) {
        $only = strtolower(substr((string) $arg, 7));
    }
    if (str_starts_with((string) $arg, '--delay-ms=')) {
        $delayMs = max(0, (int) substr((string) $arg, 11));
    }
}

$projectId = firestore_resolve_project_id();
if ($projectId === null || $projectId === '') {
    fwrite(STDERR, "FIREBASE_PROJECT_ID / service account missing\n");
    exit(1);
}
if (firestoreAccessToken() === null) {
    fwrite(STDERR, "Cannot obtain Firestore access token (check firebase-service-account.json)\n");
    exit(1);
}

$cacheDir = function_exists('th_tourvisor_cache_dir')
    ? th_tourvisor_cache_dir()
    : $root . '/data/tourvisor_cache';

if (!is_dir($cacheDir)) {
    fwrite(STDERR, "Cache dir not found: {$cacheDir}\n");
    exit(1);
}

$maxBytes = firestore_max_payload_bytes();
$searchTtl = (int) (min(720, max(24, (float) (getenv('TOURVISOR_SEARCH_CACHE_TTL_HOURS')
    ?: ($_ENV['TOURVISOR_SEARCH_CACHE_TTL_HOURS'] ?? 336)))) * 3600);
$dictTtl = (int) (min(2160, max(168, (float) (getenv('TOURVISOR_DICTIONARY_CACHE_TTL_HOURS')
    ?: ($_ENV['TOURVISOR_DICTIONARY_CACHE_TTL_HOURS'] ?? 720)))) * 3600);
$countryPageTtl = (int) (min(168, max(1, (float) (getenv('TOURVISOR_COUNTRY_PAGE_CACHE_TTL_HOURS')
    ?: ($_ENV['TOURVISOR_COUNTRY_PAGE_CACHE_TTL_HOURS'] ?? 24)))) * 3600);

$stats = [
    'scanned' => 0,
    'uploaded_search' => 0,
    'uploaded_dict' => 0,
    'skipped_size' => 0,
    'skipped_empty' => 0,
    'skipped_type' => 0,
    'failed' => 0,
    'purged' => 0,
];

/**
 * @return array{collection: string, docId: string, data: array, ttl: int}|null
 */
function th_fs_migrate_classify(string $basename, array $decoded, int $searchTtl, int $dictTtl, int $countryPageTtl): ?array
{
    $docId = preg_replace('/\.json$/i', '', $basename);
    $docId = preg_replace('#[^a-zA-Z0-9_\-=]#', '_', (string) $docId);

    // Поиск: search_dep7_cnt4_....json → { results: [...] }
    if (str_starts_with($docId, 'search_')) {
        $results = null;
        if (isset($decoded['results']) && is_array($decoded['results'])) {
            $results = $decoded['results'];
        } elseif (isset($decoded['data']) && is_array($decoded['data']) && (isset($decoded['data'][0]) || $decoded['data'] === [])) {
            $results = $decoded['data'];
        }
        if ($results === null) {
            return null;
        }
        $ttl = (strpos($docId, 'country') !== false) ? $countryPageTtl : $searchTtl;

        return [
            'collection' => 'searchCache',
            'docId' => $docId,
            'data' => $results,
            'ttl' => $ttl,
        ];
    }

    // all_tours — часто огромный; всё равно пробуем (size check снаружи)
    if ($docId === 'all_tours') {
        $results = isset($decoded['results']) && is_array($decoded['results']) ? $decoded['results'] : null;
        if ($results === null) {
            return null;
        }

        return [
            'collection' => 'searchCache',
            'docId' => 'all_tours',
            'data' => $results,
            'ttl' => $searchTtl,
        ];
    }

    // Справочники: файл от tvCacheSet → { success, data: [...] }
    $dictPrefixes = ['departures', 'countries', 'meals', 'regions', 'dates', 'hotel'];
    foreach ($dictPrefixes as $prefix) {
        if ($docId === $prefix || str_starts_with($docId, $prefix . '_')) {
            $data = null;
            if (isset($decoded['data']) && is_array($decoded['data'])) {
                $data = $decoded['data'];
            } elseif (isset($decoded[0]) || (is_array($decoded) && $decoded !== [] && !isset($decoded['success']))) {
                // сырой список
                $data = $decoded;
            }
            if ($data === null) {
                return null;
            }
            // Firestore doc id для пустых params = type; для файлов с суффиксом оставляем имя файла
            $fsDocId = $docId;
            if (str_ends_with($docId, '_api')) {
                $fsDocId = preg_replace('/_api$/', '', $docId) ?: $docId;
            }

            return [
                'collection' => 'dictionaryCache',
                'docId' => $fsDocId,
                'data' => $data,
                'ttl' => $dictTtl,
            ];
        }
    }

    return null;
}

$files = glob($cacheDir . DIRECTORY_SEPARATOR . '*.json') ?: [];
sort($files);

echo json_encode([
    'projectId' => $projectId,
    'cacheDir' => $cacheDir,
    'files' => count($files),
    'dryRun' => $dryRun,
    'only' => $only,
    'maxBytes' => $maxBytes,
], JSON_UNESCAPED_UNICODE) . PHP_EOL;

foreach ($files as $path) {
    $stats['scanned']++;
    $base = basename($path);
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        $stats['skipped_empty']++;
        continue;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        $stats['skipped_empty']++;
        continue;
    }

    $classified = th_fs_migrate_classify($base, $decoded, $searchTtl, $dictTtl, $countryPageTtl);
    if ($classified === null) {
        $stats['skipped_type']++;
        echo "SKIP type {$base}\n";
        continue;
    }

    if ($only === 'search' && $classified['collection'] !== 'searchCache') {
        $stats['skipped_type']++;
        continue;
    }
    if ($only === 'dictionaries' && $classified['collection'] !== 'dictionaryCache') {
        $stats['skipped_type']++;
        continue;
    }

    $payloadJson = json_encode($classified['data'], JSON_UNESCAPED_UNICODE);
    if ($payloadJson === false) {
        $stats['failed']++;
        echo "FAIL encode {$base}\n";
        continue;
    }
    $bytes = strlen($payloadJson);
    if ($bytes > $maxBytes) {
        $stats['skipped_size']++;
        echo "SKIP size {$base} ({$bytes} bytes > {$maxBytes})\n";
        continue;
    }

    $mtime = @filemtime($path) ?: time();
    $expiresAt = $mtime + (int) $classified['ttl'];
    if ($expiresAt < time() + 3600) {
        // Просроченным даём ещё TTL от «сейчас», чтобы после миграции сразу не вычистило
        $expiresAt = time() + (int) $classified['ttl'];
    }

    if ($dryRun) {
        echo "DRY {$classified['collection']}/{$classified['docId']} ({$bytes} b, ttl_until={$expiresAt})\n";
        if ($classified['collection'] === 'searchCache') {
            $stats['uploaded_search']++;
        } else {
            $stats['uploaded_dict']++;
        }
        continue;
    }

    $ok = firestoreSet($projectId, $classified['collection'], $classified['docId'], $classified['data'], $expiresAt);
    if (!$ok) {
        $stats['failed']++;
        echo "FAIL upload {$classified['collection']}/{$classified['docId']}\n";
        continue;
    }

    if ($classified['collection'] === 'searchCache') {
        $stats['uploaded_search']++;
    } else {
        $stats['uploaded_dict']++;
    }
    echo "OK {$classified['collection']}/{$classified['docId']} ({$bytes} b)\n";

    if ($purgeLocal) {
        if (@unlink($path)) {
            $stats['purged']++;
        }
    }

    if ($delayMs > 0) {
        usleep($delayMs * 1000);
    }
}

echo json_encode(['done' => true, 'stats' => $stats], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($stats['failed'] > 0 ? 1 : 0);
