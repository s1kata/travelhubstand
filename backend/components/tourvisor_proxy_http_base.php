<?php
/**
 * Базовый URL tourvisor-proxy для HTTP-запросов с сервера (cron, CLI), без веб-контекста.
 * На [travelhub63.ru](https://travelhub63.ru/frontend/index.php) страницы из /frontend/ ходят в
 * /frontend/api/tourvisor-proxy.php (см. tourvisor_proxy_url.php). По умолчанию используем тот же путь.
 *
 * Приоритет:
 * 1) TOURVISOR_PROXY_URL — полный URL до скрипта (или каталога сайта, тогда допишется относительный путь)
 * 2) SITE_URL (origin, без хвоста /frontend) + TOURVISOR_PROXY_RELATIVE_PATH (по умолчанию frontend/api/tourvisor-proxy.php)
 */
declare(strict_types=1);

function tourvisor_proxy_http_relative_path(): string
{
    $p = trim((string)(getenv('TOURVISOR_PROXY_RELATIVE_PATH') ?: ($_ENV['TOURVISOR_PROXY_RELATIVE_PATH'] ?? '')), '/');
    if ($p === '') {
        return '/frontend/api/tourvisor-proxy.php';
    }
    return '/' . str_replace('\\', '/', $p);
}

function get_tourvisor_proxy_http_base_url(): string
{
    $explicit = rtrim(getenv('TOURVISOR_PROXY_URL') ?: ($_ENV['TOURVISOR_PROXY_URL'] ?? ''), '/');
    if ($explicit !== '') {
        if (!str_contains($explicit, 'tourvisor-proxy')) {
            $explicit = rtrim($explicit, '/') . tourvisor_proxy_http_relative_path();
        }
        return $explicit;
    }

    $siteUrl = rtrim(getenv('SITE_URL') ?: ($_ENV['SITE_URL'] ?? ''), '/');
    if ($siteUrl !== '' && preg_match('#/frontend/?$#i', $siteUrl)) {
        $siteUrl = (string)preg_replace('#/frontend/?$#i', '', $siteUrl);
        $siteUrl = rtrim($siteUrl, '/');
    }

    if ($siteUrl !== '') {
        return $siteUrl . tourvisor_proxy_http_relative_path();
    }

    if (function_exists('th_site_base_url')) {
        $auto = th_site_base_url();
        if ($auto !== '') {
            return $auto . tourvisor_proxy_http_relative_path();
        }
    }

    if (php_sapi_name() !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $proto . '://' . $_SERVER['HTTP_HOST'] . tourvisor_proxy_http_relative_path();
    }

    return 'https://travelhub63.ru' . tourvisor_proxy_http_relative_path();
}
