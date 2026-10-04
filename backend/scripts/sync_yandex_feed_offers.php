<?php
/**
 * Синхронизация таблицы yandex_feed_offers с Tourvisor (акции, onlyPromo) и пересборка export/services.yml.
 *
 * CLI:
 *   php backend/scripts/sync_yandex_feed_offers.php
 *
 * Крон (например 12:00 МСК вместе с обновлением акций):
 *   0 12 * * * cd /path/to/project && php backend/scripts/sync_yandex_feed_offers.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../components/yandex_feed_sync.php';

if (!$pdo) {
    fwrite(STDERR, "No database connection.\n");
    exit(1);
}

yandex_feed_ensure_table($pdo);

if (!yandex_feed_legacy_table_sync_enabled()) {
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "Skip: YANDEX_LEGACY_OFFERS_TABLE_SYNC=0 — синк yandex_feed_offers отключён. Пересборка services YML из текущей таблицы.\n");
    }
    $res = ['inserted' => 0, 'errors' => [], 'legacy_sync_disabled' => true];
} else {
    $res = yandex_feed_sync_from_tourvisor($pdo);
}

if (php_sapi_name() === 'cli') {
    echo 'Sync: upserts attempted ' . (int) ($res['inserted'] ?? 0) . "\n";
    if (!empty($res['errors'])) {
        foreach ($res['errors'] as $e) {
            echo '  ' . $e . "\n";
        }
    }
}

require_once __DIR__ . '/generate_yml.php';
$gen = generate_services_yml($pdo);
if (!$gen['ok']) {
    fwrite(STDERR, 'YML: ' . ($gen['error'] ?? 'failed') . "\n");
    exit(1);
}

if (php_sapi_name() === 'cli') {
    $p = (int) ($gen['promo'] ?? 0);
    $s = (int) ($gen['services'] ?? 0);
    echo 'YML: ' . ($gen['file'] ?? '') . " (promo {$p}, services {$s})\n";
}

exit(0);
