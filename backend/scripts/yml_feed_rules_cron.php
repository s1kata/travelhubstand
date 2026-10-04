<?php
/**
 * Крон: YML для Яндекс.Бизнеса.
 * При активной ротации — пакет стран → /feed-samara.yml, /feed-moscow.yml, /feed.yml.
 * Иначе — правила yandex_yml_feed_rules.
 *
 *   20 0 * * * cd /path/to/travelhub-v2 && php backend/scripts/yml_feed_rules_cron.php >> data/yandex_yml_rules_cron.log 2>&1
 * Force: php backend/scripts/yml_feed_rules_cron.php --force-rotation
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../components/yandex_yml_rules_runner.php';
require_once __DIR__ . '/../components/yandex_yml_rotation.php';

if (!$pdo) {
    fwrite(STDERR, "No database connection.\n");
    exit(1);
}

$forceRotation = in_array('--force-rotation', $argv ?? [], true);

if (yandex_feed_rotation_is_active($pdo)) {
    $res = yandex_yml_rotation_run($pdo, false, $forceRotation);
} else {
    $res = yandex_yml_rules_run($pdo, false);
}

if (!empty($res['ok'])) {
    if (!empty($res['skipped'])) {
        echo date('c') . ' SKIP ' . (string) ($res['message'] ?? 'skipped') . "\n";
        exit(0);
    }
    if (!empty($res['rotated'])) {
        echo date('c') . ' ROTATED offers=' . (int) ($res['offers_written'] ?? 0) . ' msg=' . (string) ($res['message'] ?? '') . "\n";
        exit(0);
    }
    $files = implode(', ', $res['files'] ?? []);
    if (!empty($res['stale_kept'])) {
        echo date('c') . ' stale_kept rules=' . ($res['rules_total'] ?? 0) . ' ok=' . ($res['rules_ok'] ?? 0)
            . ' offers_candidate=' . (int) ($res['offers_candidate'] ?? 0)
            . ' offers_kept≈' . (int) ($res['offers_written'] ?? 0) . ' files=' . $files . "\n";
        exit(0);
    }
    echo date('c') . ' rules=' . ($res['rules_total'] ?? 0) . ' ok=' . ($res['rules_ok'] ?? 0) . ' offers=' . ($res['offers_written'] ?? 0) . ' files=' . $files . "\n";
    exit(0);
}
if (!empty($res['lock_busy'])) {
    fwrite(STDERR, "Lock busy, skipped.\n");
    exit(0);
}
if (!empty($res['stale_kept'])) {
    echo date('c') . ' stale_kept ' . (string) ($res['message'] ?? '') . "\n";
    exit(0);
}
fwrite(STDERR, 'Failed: ' . implode('; ', $res['errors'] ?? []) . "\n");
exit(1);
