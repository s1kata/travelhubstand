<?php
declare(strict_types=1);

/**
 * Публичная выдача YML по городу вылета: /feed-samara.yml, /feed-moscow.yml (RewriteRule → ?slug=).
 * Файл снимка: data/yandex_feed_{slug}.yml — пишется при сборке, если задан YML_FEED_DEPARTURE_ALIASES (slug:departureId).
 */
$projectRoot = dirname(__DIR__);
require_once $projectRoot . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
require_once $projectRoot . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'yandex_yml_rules_runner.php';

$slugRaw = isset($_GET['slug']) ? (string) $_GET['slug'] : '';
$slug = strtolower(preg_replace('/[^a-z0-9-]+/', '', $slugRaw));
$snapshot = yandex_yml_rules_public_departure_feed_snapshot_path($slug);
if ($snapshot === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Unknown feed slug. Set YML_FEED_DEPARTURE_ALIASES (e.g. samara:12,moscow:28).\n";
    exit;
}

if (is_file($snapshot) && !yandex_yml_rules_public_slug_snapshot_is_ready($snapshot)) {
    @unlink($snapshot);
    clearstatcache(true, $snapshot);
}

if (!yandex_yml_rules_public_slug_snapshot_is_ready($snapshot)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo "Фид временно недоступен, обновление через крон\n";
    exit;
}

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');
readfile($snapshot);
exit;
