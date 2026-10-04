<?php
/**
 * Единый базовый URL для Tourvisor API (прокси + кэш).
 * Подключайте во все поисковики сайта, чтобы поиск шёл через backend/components/api/tourvisor-proxy.php
 * (файловый кэш + Firestore searchCache + живой поиск при промахе).
 *
 * Где уже подключено:
 * — frontend/index.php (главная): TV_API_BASE, source=main
 * — frontend/window/promotions.php (акции): source=promo
 * — backend/components/country_tour_search.php (страницы стран): source=countries
 *
 * Опциональный $source — для разных токенов на поисковик (TOURVISOR_TOKEN_MAIN, TOURVISOR_TOKEN_PROMO и т.д.).
 * Если не передан — используется TOURVISOR_TOKEN (общий).
 *
 * Где виджет Tourvisor (init.js) — идёт напрямую в api.tourvisor.ru, не через наш кэш:
 * — hotel-detail.php, offices/*.php, country.php, guest-template.php (виджет Tourvisor)
 * — tour-calendar.php использует свой API (promo_cache), не виджет
 */
declare(strict_types=1);

function get_tourvisor_proxy_path(): string {
    $sn = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($sn, '/frontend/') !== false) {
        $path = preg_replace('#^(.*/frontend)/.*$#', '$1/api/tourvisor-proxy.php', $sn);
        if ($path === $sn) {
            $path = '/frontend/api/tourvisor-proxy.php';
        }
    } else {
        $path = '/backend/api/tourvisor-proxy.php';
    }
    return preg_replace('#/+#', '/', $path);
}

/**
 * Запрос пришёл по HTTPS (в т.ч. за reverse proxy / CDN, когда первый сегмент X-Forwarded-Proto = https).
 */
function tourvisor_request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    $xfProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    if ($xfProto !== '') {
        $first = strtolower(trim(explode(',', (string) $xfProto)[0]));
        if ($first === 'https') {
            return true;
        }
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && (string) $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
        return true;
    }
    if (!empty($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443') {
        return true;
    }
    if (!empty($_SERVER['REQUEST_SCHEME']) && strtolower((string) $_SERVER['REQUEST_SCHEME']) === 'https') {
        return true;
    }

    return false;
}

function get_tourvisor_proxy_base_url(?string $source = null): string {
    $proto = tourvisor_request_is_https() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = get_tourvisor_proxy_path();
    $url = rtrim($proto . '://' . $host . $path, '/');
    if ($source !== null && $source !== '') {
        $url .= (strpos($url, '?') !== false ? '&' : '?') . 'source=' . rawurlencode($source);
    }
    return $url;
}

/**
 * Базовый URL прокси картинок Tourvisor (static.tourvisor.ru без HTTPS).
 * Используйте для подстановки в picturelink, чтобы избежать ERR_SSL_PROTOCOL_ERROR.
 */
function get_tourvisor_image_proxy_base_url(): string {
    $proto = tourvisor_request_is_https() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $proto . '://' . $host . '/backend/api/tourvisor-image-proxy.php';
}
