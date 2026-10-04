<?php
/**
 * Главная: готовые горящие туры (отели из promo_cache / live), не страны.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/components/home_showcase_build.php';
require_once dirname(__DIR__) . '/components/security_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
security_apply_default_headers();

$guard = security_guard_public_api('home_showcase_shelves', '', 60, 4096);
if (empty($guard['ok'])) {
    http_response_code((int) ($guard['code'] ?? 400));
    echo json_encode(['success' => false, 'error' => (string) ($guard['error'] ?? 'Bad request')], JSON_UNESCAPED_UNICODE);
    exit;
}

$departureId = isset($_GET['departureId']) ? (int) $_GET['departureId'] : 0;
// Витрина обслуживается promo warm; live разрешается только вручную для диагностики.
$allowLive = isset($_GET['live']) && $_GET['live'] === '1';

$payload = th_home_showcase_build($departureId, $allowLive);

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
