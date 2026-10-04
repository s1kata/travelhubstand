<?php
declare(strict_types=1);

/**
 * Страница тура на нашем сайте (для CRM / заявок) — допустимая «своя» ссылка.
 */
function tour_link_is_internal_tour_detail_page(string $url): bool
{
    $url = trim($url);
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return false;
    }
    $p = parse_url($url);
    if (!$p || empty($p['path'])) {
        return false;
    }
    if (stripos((string) $p['path'], 'tour-detail.php') === false) {
        return false;
    }
    $host = strtolower((string) ($p['host'] ?? ''));
    if ($host === '') {
        return false;
    }
    if (preg_match('/(^|\.)travelhub63\.ru$/i', $host)) {
        return true;
    }
    $envHost = strtolower(trim((string) (getenv('APP_PUBLIC_HOST') ?: ($_ENV['APP_PUBLIC_HOST'] ?? ''))));
    if ($envHost !== '' && ($host === $envHost || $host === 'www.' . preg_replace('#^www\.#i', '', $envHost))) {
        return true;
    }
    $reqHost = isset($_SERVER['HTTP_HOST']) ? strtolower((string) $_SERVER['HTTP_HOST']) : '';
    return $reqHost !== '' && $host === $reqHost;
}

/**
 * Ссылки на тур у оператора не должны указывать на наш же сайт — иначе ломается навигация.
 */
function tour_link_is_self_referential(string $url): bool
{
    $url = trim($url);
    if ($url === '') {
        return false;
    }
    if (preg_match('/travelhub63\.ru/i', $url)) {
        return true;
    }
    $envHost = getenv('APP_PUBLIC_HOST');
    $hosts = array_filter(array_map('strtolower', array_filter([
        is_string($envHost) ? trim($envHost) : '',
        'www.travelhub63.ru',
    ])));
    if (!preg_match('#^https?://#i', $url)) {
        return false;
    }
    $p = parse_url($url);
    if (!$p || empty($p['host'])) {
        return false;
    }
    $host = strtolower((string) $p['host']);
    foreach ($hosts as $h) {
        if ($h !== '' && $h === $host) {
            return true;
        }
    }
    $reqHost = isset($_SERVER['HTTP_HOST']) ? strtolower((string) $_SERVER['HTTP_HOST']) : '';
    if ($reqHost !== '' && $host === $reqHost) {
        return true;
    }

    return false;
}

/** Возвращает пустую строку, если ссылка ведёт на наш домен или некорректна для внешнего туроператора. */
function tour_link_sanitize_for_app(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (tour_link_is_internal_tour_detail_page($url)) {
        return $url;
    }
    if (tour_link_is_self_referential($url)) {
        return '';
    }

    return $url;
}
