<?php
declare(strict_types=1);
/**
 * HTTP-вызов пересборки YML (ротация или правила).
 * GET https://<сайт>/backend/api/cron-yml-feed.php?key=<CRON_YML_SECRET>
 * Force rotation: &force_rotation=1
 */
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/components/yandex_yml_rules_runner.php';
require_once dirname(__DIR__) . '/components/yandex_yml_rotation.php';

function cron_yml_feed_read_key(): string
{
    $k = '';
    if (isset($_GET['key'])) {
        $k = (string) $_GET['key'];
    } elseif (isset($_POST['key'])) {
        $k = (string) $_POST['key'];
    }
    $k = trim($k);
    if ($k === '' && !empty($_SERVER['HTTP_X_CRON_KEY'])) {
        $k = trim((string) $_SERVER['HTTP_X_CRON_KEY']);
    }

    return $k;
}

$secret = trim((string) (getenv('CRON_YML_SECRET') ?: ($_ENV['CRON_YML_SECRET'] ?? '')));
if ($secret === '') {
    http_response_code(503);
    echo "CRON_YML_SECRET is not set in .env\n";
    exit;
}

$key = cron_yml_feed_read_key();
if ($key === '' || !hash_equals($secret, $key)) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

if (!$pdo) {
    http_response_code(500);
    echo "No database connection\n";
    exit;
}

$forceRotation = isset($_GET['force_rotation']) && (string) $_GET['force_rotation'] === '1';

if (yandex_feed_rotation_is_active($pdo)) {
    $res = yandex_yml_rotation_run($pdo, false, $forceRotation);
} else {
    $res = yandex_yml_rules_run($pdo, false);
}

if (!empty($res['ok'])) {
    if (!empty($res['skipped'])) {
        http_response_code(200);
        echo 'SKIP ' . (string) ($res['message'] ?? 'skipped') . "\n";
        exit;
    }
    if (!empty($res['rotated'])) {
        http_response_code(200);
        echo 'OK rotated=1 offers_written=' . (int) ($res['offers_written'] ?? 0)
            . ' msg=' . (string) ($res['message'] ?? '') . "\n";
        exit;
    }
    if (!empty($res['stale_kept'])) {
        http_response_code(200);
        echo 'OK stale_kept=1 rules_total=' . (int) ($res['rules_total'] ?? 0)
            . ' rules_ok=' . (int) ($res['rules_ok'] ?? 0)
            . ' offers_candidate=' . (int) ($res['offers_candidate'] ?? 0)
            . ' offers_kept≈' . (int) ($res['offers_written'] ?? 0) . "\n";
        exit;
    }
    http_response_code(200);
    echo 'OK rules_total=' . (int) ($res['rules_total'] ?? 0)
        . ' rules_ok=' . (int) ($res['rules_ok'] ?? 0)
        . ' offers_written=' . (int) ($res['offers_written'] ?? 0) . "\n";
    exit;
}
if (!empty($res['lock_busy'])) {
    http_response_code(200);
    echo "SKIP lock_busy\n";
    exit;
}
if (!empty($res['stale_kept'])) {
    http_response_code(200);
    echo 'OK stale_kept=1 msg=' . (string) ($res['message'] ?? '') . "\n";
    exit;
}
http_response_code(500);
echo 'FAIL ' . implode('; ', $res['errors'] ?? []) . "\n";
exit;
