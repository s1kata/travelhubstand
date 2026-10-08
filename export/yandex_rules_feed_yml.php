<?php
declare(strict_types=1);

/**
 * Публичная выдача YML по правилам админки. URL: /feed.yml (RewriteRule в корневом .htaccess).
 * Только чтение снимка data/yandex_rules_feed_snapshot.yml. Пересборка — cron-yml-feed.php,
 * backend/scripts/yml_feed_rules_cron.php или CLI: php rebuild_feed.php из корня проекта.
 */
$projectRoot = dirname(__DIR__);
require_once $projectRoot . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
require_once $projectRoot . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'yandex_yml_rules_runner.php';

$snapshot = yandex_yml_rules_feed_snapshot_path();
if (is_file($snapshot) && !yandex_yml_rules_feed_snapshot_is_valid($snapshot)) {
    @unlink($snapshot);
    clearstatcache(true, $snapshot);
}

if (yandex_yml_rules_feed_snapshot_is_valid($snapshot)) {
    header('Content-Type: application/xml; charset=UTF-8');
    header('Cache-Control: public, max-age=300');
    header('X-Content-Type-Options: nosniff');
    readfile($snapshot);
    exit;
}

$siteBase = rtrim((string) (getenv('SITE_URL') ?: ($_ENV['SITE_URL'] ?? '')), '/');
if ($siteBase === '') {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $siteBase = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? '');
}
if (preg_match('#/frontend/?$#i', $siteBase)) {
    $siteBase = (string) preg_replace('#/frontend/?$#i', '', $siteBase);
    $siteBase = rtrim($siteBase, '/');
}

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=120');
header('X-Content-Type-Options: nosniff');

try {
    $built = yandex_yml_rules_render_combined_yml_catalog([], $siteBase);
    echo (string) ($built['xml'] ?? '');
    exit;
} catch (Throwable $e) {
    if (function_exists('yandex_yml_rules_log_line')) {
        yandex_yml_rules_log_line('FAIL feed.yml fallback render: ' . $e->getMessage());
    }
    /* Аварийный XML без зависимостей XMLWriter/DOM — чтобы не отдавать HTTP 500. */
    $safeBase = htmlspecialchars($siteBase, ENT_QUOTES | ENT_XML1, 'UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>'
        . '<yml_catalog date="' . date('Y-m-d H:i') . '">'
        . '<shop>'
        . '<name>Travel Hub</name>'
        . '<company>Travel Hub</company>'
        . '<url>' . $safeBase . '</url>'
        . '<currencies><currency id="RUB" rate="1"/></currencies>'
        . '<categories><category id="200000">Туры</category></categories>'
        . '<offers/>'
        . '</shop>'
        . '</yml_catalog>';
    exit;
}
