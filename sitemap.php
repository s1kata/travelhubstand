<?php
/**
 * Динамическая карта сайта (sitemap) для поисковых систем.
 * Отдаёт все публичные страницы, включая данные из API (услуги, страны).
 * Подключите в robots.txt: Sitemap: https://ваш-домен.ru/sitemap.php
 */
declare(strict_types=1);

$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base = rtrim($proto . '://' . $host, '/');

// Учёт подпапки, если сайт в поддиректории (например /--main/)
$scriptPath = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$pathPrefix = ($scriptPath !== '/' && $scriptPath !== '\\' && $scriptPath !== '') ? rtrim(str_replace('\\', '/', $scriptPath), '/') : '';

$today = date('Y-m-d');
$lastWeek = date('Y-m-d', strtotime('-7 days'));

$urls = [];

function addUrl(array &$urls, string $base, string $pathPrefix, string $path, string $priority = '0.8', string $changefreq = 'weekly', string $lastmod = null): void {
    $path = ltrim($path, '/');
    $loc = $base . $pathPrefix . '/' . $path;
    $urls[] = [
        'loc' => $loc,
        'lastmod' => $lastmod ?? $GLOBALS['today'],
        'changefreq' => $changefreq,
        'priority' => $priority,
    ];
}

// Главная и основные разделы
addUrl($urls, $base, $pathPrefix, 'frontend/index.php', '1.0', 'daily');
addUrl($urls, $base, $pathPrefix, 'frontend/window/services.php', '0.9', 'weekly');
addUrl($urls, $base, $pathPrefix, 'frontend/window/about.php', '0.8', 'monthly');
addUrl($urls, $base, $pathPrefix, 'frontend/window/contacts.php', '0.8', 'monthly');
addUrl($urls, $base, $pathPrefix, 'frontend/window/countries-list.php', '0.9', 'weekly');
addUrl($urls, $base, $pathPrefix, 'frontend/window/tours.php', '0.9', 'weekly');
addUrl($urls, $base, $pathPrefix, 'frontend/window/offices.php', '0.8', 'monthly');
addUrl($urls, $base, $pathPrefix, 'frontend/window/promotions.php', '0.7', 'weekly');
addUrl($urls, $base, $pathPrefix, 'frontend/window/consent.php', '0.4', 'yearly');
addUrl($urls, $base, $pathPrefix, 'frontend/window/privacy.php', '0.4', 'yearly');
addUrl($urls, $base, $pathPrefix, 'frontend/window/terms.php', '0.4', 'yearly');
addUrl($urls, $base, $pathPrefix, 'frontend/window/tour-calendar.php', '0.7', 'weekly');
addUrl($urls, $base, $pathPrefix, 'frontend/window/turkey-vip-hotels.php', '0.7', 'monthly');

// Страны (те же slug, что в countries-list.php)
$countrySlugs = [
    'turkey', 'egypt', 'thailand', 'uae', 'russia', 'china',
    'abkhazia', 'armenia', 'bahrain', 'cuba', 'india', 'indonesia',
    'jordan', 'mauritius', 'maldives', 'montenegro', 'oman',
    'philippines', 'qatar', 'seychelles', 'sri-lanka', 'tanzania',
    'tunisia', 'venezuela', 'vietnam',
];
foreach ($countrySlugs as $slug) {
    addUrl($urls, $base, $pathPrefix, 'frontend/window/countries/' . $slug . '.php', '0.8', 'weekly');
}

// Офисы (основные страницы)
$officePages = [
    'moscow.php', 'moscow-offices.php', 'samara.php', 'samara-offices.php',
];
foreach ($officePages as $page) {
    addUrl($urls, $base, $pathPrefix, 'frontend/window/offices/' . $page, '0.6', 'monthly');
}
$officeSlugs = [
    'samara-funsun', 'samara-funsun-gudok', 'samara-anex-moskovskoe', 'samara-anex-apelsin',
    'samara-coral', 'moscow-coral-elite', 'moscow-anex',
];
foreach ($officeSlugs as $slug) {
    addUrl($urls, $base, $pathPrefix, 'frontend/window/offices/office.php?slug=' . rawurlencode($slug), '0.6', 'monthly');
}

// Услуги из БД (для SEO — страница услуг одна, но API отдаёт список; добавляем данные в JSON-LD на странице)
// Дополнительные статические страницы
addUrl($urls, $base, $pathPrefix, 'frontend/window/video-tutorials.php', '0.5', 'monthly');
// Вывод XML
header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex', true); // саму sitemap не индексировать как страницу
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1, 'UTF-8') . '</loc>' . "\n";
    echo '    <lastmod>' . $u['lastmod'] . '</lastmod>' . "\n";
    echo '    <changefreq>' . $u['changefreq'] . '</changefreq>' . "\n";
    echo '    <priority>' . $u['priority'] . '</priority>' . "\n";
    echo '  </url>' . "\n";
}
echo '</urlset>';
