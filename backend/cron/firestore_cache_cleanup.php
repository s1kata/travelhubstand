<?php
declare(strict_types=1);

/**
 * Очистка просроченных документов Firestore searchCache / dictionaryCache.
 * Рекомендуется 2× в неделю ночью (см. docs/CRON.md, docs/CACHE_LAYERS.md).
 *
 *   php backend/cron/firestore_cache_cleanup.php
 *   php backend/cron/firestore_cache_cleanup.php --dry-run
 *   php backend/cron/firestore_cache_cleanup.php --collections=searchCache
 *
 * Удаляет документы, у которых expiresAt (ms или sec) < now.
 * Документы без expiresAt: удаляются только с --delete-missing-expiry
 */

$root = dirname(__DIR__, 2);
require_once $root . '/backend/config/config.php';
require_once $root . '/backend/components/api/firestore-helper.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$deleteMissingExpiry = in_array('--delete-missing-expiry', $argv ?? [], true);
$collections = ['searchCache', 'dictionaryCache'];
foreach ($argv ?? [] as $arg) {
    if (str_starts_with((string) $arg, '--collections=')) {
        $collections = array_values(array_filter(array_map('trim', explode(',', substr((string) $arg, 14)))));
    }
}

$projectId = firestore_resolve_project_id();
if ($projectId === null || $projectId === '') {
    fwrite(STDERR, "FIREBASE_PROJECT_ID / service account missing\n");
    exit(1);
}
if (firestoreAccessToken() === null) {
    fwrite(STDERR, "Cannot obtain Firestore access token\n");
    exit(1);
}

$nowMs = (int) round(microtime(true) * 1000);
$stats = [
    'listed' => 0,
    'expired' => 0,
    'deleted' => 0,
    'kept' => 0,
    'failed' => 0,
    'no_expiry' => 0,
];

echo json_encode([
    'projectId' => $projectId,
    'dryRun' => $dryRun,
    'collections' => $collections,
    'nowMs' => $nowMs,
], JSON_UNESCAPED_UNICODE) . PHP_EOL;

foreach ($collections as $collection) {
    $pageToken = null;
    do {
        $page = firestoreListDocuments($projectId, $collection, 100, $pageToken, ['expiresAt']);
        foreach ($page['documents'] as $doc) {
            $stats['listed']++;
            $id = $doc['id'];
            $fields = $doc['fields'];
            $expRaw = isset($fields['expiresAt']['integerValue'])
                ? (int) $fields['expiresAt']['integerValue']
                : 0;

            if ($expRaw <= 0) {
                $stats['no_expiry']++;
                if (!$deleteMissingExpiry) {
                    $stats['kept']++;
                    continue;
                }
                $expired = true;
            } else {
                $expMs = $expRaw > 1e12 ? $expRaw : $expRaw * 1000;
                $expired = $expMs < $nowMs;
            }

            if (!$expired) {
                $stats['kept']++;
                continue;
            }

            $stats['expired']++;
            if ($dryRun) {
                echo "DRY delete {$collection}/{$id}\n";
                $stats['deleted']++;
                continue;
            }

            if (firestoreDeleteDocument($projectId, $collection, $id)) {
                $stats['deleted']++;
                echo "DEL {$collection}/{$id}\n";
            } else {
                $stats['failed']++;
                echo "FAIL delete {$collection}/{$id}\n";
            }
            usleep(50000);
        }
        $pageToken = $page['nextPageToken'];
    } while ($pageToken !== null && $pageToken !== '');
}

echo json_encode(['done' => true, 'stats' => $stats], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
exit($stats['failed'] > 0 ? 1 : 0);
