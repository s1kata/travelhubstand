<?php
declare(strict_types=1);

/**
 * CLI: ручная пересборка YML (то же, что yml_feed_rules_cron.php, с блокирующим lock).
 *
 *   cd ~/hub63_ru/public_html
 *   php backend/scripts/rebuild_feed.php
 *
 * Альтернатива из корня репозитория: php rebuild_feed.php
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../components/yandex_yml_rules_runner.php';

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
