<?php
declare(strict_types=1);

/**
 * CLI: пересборка YML по правилам yandex_yml_feed_rules: главный снимок data/yandex_rules_feed_snapshot.yml и data/yandex_feed_dep_{id}.yml по вылетам (плюс data/yandex_feed_{slug}.yml при YML_FEED_DEPARTURE_ALIASES).
 *
 *   php rebuild_feed.php
 *   php backend/scripts/rebuild_feed.php
 */
$projectRoot = __DIR__;
require_once $projectRoot . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
require_once $projectRoot . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'yandex_yml_rules_runner.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    fwrite(STDERR, "No database connection\n");
    exit(1);
}

$res = yandex_yml_rules_run($pdo, true);
if (!empty($res['lock_busy'])) {
    fwrite(STDERR, "SKIP lock_busy\n");
    exit(2);
}
if (!empty($res['stale_kept'])) {
    fwrite(STDOUT, 'OK stale_kept=1 rules_total=' . (int) ($res['rules_total'] ?? 0)
        . ' rules_ok=' . (int) ($res['rules_ok'] ?? 0)
        . ' offers_candidate=' . (int) ($res['offers_candidate'] ?? 0)
        . ' offers_kept≈' . (int) ($res['offers_written'] ?? 0) . "\n");
    exit(0);
}
if (empty($res['ok'])) {
    fwrite(STDERR, 'FAIL ' . implode('; ', $res['errors'] ?? ['unknown']) . "\n");
    exit(1);
}

fwrite(STDOUT, 'OK rules_total=' . (int) ($res['rules_total'] ?? 0)
    . ' rules_ok=' . (int) ($res['rules_ok'] ?? 0)
    . ' offers_written=' . (int) ($res['offers_written'] ?? 0) . "\n");
exit(0);
