#!/usr/bin/env php
<?php
/**
 * Сброс file-cache поиска для стран (main + promo namespace + legacy).
 * Usage: php backend/scripts/clear_search_cache_countries.php 12 16
 */
declare(strict_types=1);

define('TH_TOURVISOR_PROXY_EMBED', true);

$root = dirname(__DIR__, 2);
require_once $root . '/backend/components/api/tourvisor-proxy.php';

$ids = array_map('intval', array_slice($argv, 1));
if ($ids === []) {
    $ids = [12, 16, 16104];
}

$dir = tvCacheDir();
if (!is_dir($dir)) {
    fwrite(STDERR, "Cache dir not found: {$dir}\n");
    exit(1);
}

$removed = 0;
foreach (glob($dir . DIRECTORY_SEPARATOR . 'search_*.json') ?: [] as $file) {
    $base = basename($file);
    foreach ($ids as $cid) {
        if (preg_match('/_cnt' . preg_quote((string) $cid, '/') . '_/', $base)) {
            if (@unlink($file)) {
                $removed++;
                echo "removed {$base}\n";
            }
            break;
        }
    }
}

echo "Done. Removed {$removed} files for countries: " . implode(', ', $ids) . "\n";
