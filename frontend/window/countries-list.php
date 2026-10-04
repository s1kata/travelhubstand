<?php
require_once __DIR__ . '/../../backend/components/page_cache_early.php';
if (isset($_GET['nocache']) || isset($_GET['debug_images'])) {
    PageCache::clear();
}
if (PageCache::get()) exit;
require_once __DIR__ . '/../../backend/config/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
PageCache::start();

/**
 * Текущий запрос идёт по HTTPS (учёт прокси: X-Forwarded-Proto, порт 443).
 */
function countries_list_is_https_request(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && stripos((string) $_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') !== false) {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443') {
        return true;
    }
    return false;
}

function countries_list_request_scheme(): string {
    return countries_list_is_https_request() ? 'https' : 'http';
}

// Фото стран из TourVisor API (отели/туры)
$countryImagesMap = [];
$apiUrl = countries_list_request_scheme() . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/backend/api/countries-with-images.php';
$ctx = stream_context_create(['http' => ['timeout' => 5]]);
$apiResponse = @file_get_contents($apiUrl, false, $ctx);
$logDir = dirname(__DIR__, 2) . '/data';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
$logLine = date('Y-m-d H:i:s') . ' [countries-list] url=' . $apiUrl . ' response_len=' . (is_string($apiResponse) ? strlen($apiResponse) : 0);
if ($apiResponse) {
    $apiData = json_decode($apiResponse, true) ?: [];
    $logLine .= ' countries=' . (isset($apiData['countries']) ? count($apiData['countries']) : 0);
    $withImg = 0;
    if (!empty($apiData['countries'])) {
        foreach ($apiData['countries'] as $c) {
            $slug = $c['slug'] ?? '';
            if ($slug !== '' && !empty($c['images'])) {
                $countryImagesMap[$slug] = $c['images'];
                $withImg++;
            }
        }
    }
    $logLine .= ' with_images=' . $withImg . ' slugs=' . implode(',', array_keys($countryImagesMap));
} else {
    $apiData = [];
    $logLine .= ' error=fetch_failed';
    if (function_exists('error_get_last')) {
        $err = error_get_last();
        $logLine .= ' php_error=' . ($err['message'] ?? '');
    }
}
@file_put_contents($logDir . '/country_images.log', $logLine . "\n", FILE_APPEND | LOCK_EX);

$countriesListCards = require __DIR__ . '/../../backend/config/countries_list_cards.php';
$countriesListFallbackImages = $countriesListCards['images'] ?? [];
$countriesListIso = $countriesListCards['iso'] ?? [];

/**
 * Абсолютный URL для картинки (API может отдать путь от корня сайта).
 */
function countries_list_abs_url(string $path): string {
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return countries_list_upgrade_http_image_url($path);
    }
    if ($path[0] === '/') {
        return countries_list_request_scheme() . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $path;
    }
    return $path;
}

/**
 * На HTTPS-странице не отдаём http:// для картинок (mixed content).
 */
function countries_list_upgrade_http_image_url(string $url): string {
    if ($url === '') {
        return '';
    }
    if (countries_list_is_https_request() && strncasecmp($url, 'http://', 7) === 0) {
        return 'https://' . substr($url, 7);
    }
    return $url;
}

/**
 * Фон карточки: локальный путь из конфига (/) — приоритетнее внешних URL API
 * (Tourvisor/Unsplash на мобильных часто блокируются или не грузятся).
 */
function countries_list_card_image(string $slug, array $apiImages, array $fallback): string {
    $fb = $fallback[$slug] ?? '';
    if ($fb !== '' && $fb[0] === '/') {
        return countries_list_abs_url($fb);
    }
    $c = '';
    if (!empty($apiImages[0])) {
        $c = countries_list_abs_url((string) $apiImages[0]);
    }
    if ($c !== '' && preg_match('#^https?://#i', $c)) {
        return $c;
    }
    if ($fb !== '') {
        return countries_list_abs_url($fb);
    }
    return $fallback['_default'] ?? 'https://images.unsplash.com/photo-1488646953014-85cb4e065289?auto=format&fit=crop&w=1400&q=82';
}

$debugCountryImages = [
    'apiUrl' => $apiUrl,
    'responseLen' => is_string($apiResponse) ? strlen($apiResponse) : 0,
    'countriesTotal' => (isset($apiData['countries']) && is_array($apiData['countries'])) ? count($apiData['countries']) : 0,
    'withImages' => count($countryImagesMap),
    'slugs' => array_keys($countryImagesMap),
    'error' => $apiResponse ? null : 'fetch_failed',
];

// Список всех стран с флагами и ссылками
$countries = [
    // Популярные
    ['name' => 'Турция', 'flag' => '🇹🇷', 'slug' => 'turkey', 'popular' => true],
    ['name' => 'Египет', 'flag' => '🇪🇬', 'slug' => 'egypt', 'popular' => true],
    ['name' => 'ОАЭ', 'flag' => '🇦🇪', 'slug' => 'uae', 'popular' => true],
    ['name' => 'Таиланд', 'flag' => '🇹🇭', 'slug' => 'thailand', 'popular' => true],
    ['name' => 'Мальдивы', 'flag' => '🇲🇻', 'slug' => 'maldives', 'popular' => true],
    ['name' => 'Шри-Ланка', 'flag' => '🇱🇰', 'slug' => 'sri-lanka', 'popular' => true],
    ['name' => 'Сочи', 'flag' => '🇷🇺', 'slug' => 'russia', 'popular' => true],
    ['name' => 'Абхазия', 'flag' => '🏔️', 'slug' => 'abkhazia', 'popular' => true],
    ['name' => 'Китай', 'flag' => '🇨🇳', 'slug' => 'china', 'popular' => true],
    ['name' => 'Вьетнам', 'flag' => '🇻🇳', 'slug' => 'vietnam', 'popular' => true],
    // Все страны
    // (Абхазия перенесена в популярные)
    ['name' => 'Армения', 'flag' => '🇦🇲', 'slug' => 'armenia', 'popular' => false],
    ['name' => 'Бахрейн', 'flag' => '🇧🇭', 'slug' => 'bahrain', 'popular' => false],
    ['name' => 'Куба', 'flag' => '🇨🇺', 'slug' => 'cuba', 'popular' => false],
    ['name' => 'Индия', 'flag' => '🇮🇳', 'slug' => 'india', 'popular' => false],
    ['name' => 'Индонезия', 'flag' => '🇮🇩', 'slug' => 'indonesia', 'popular' => false],
    ['name' => 'Иордания', 'flag' => '🇯🇴', 'slug' => 'jordan', 'popular' => false],
    ['name' => 'Маврикий', 'flag' => '🇲🇺', 'slug' => 'mauritius', 'popular' => false],
    ['name' => 'Мальдивы', 'flag' => '🇲🇻', 'slug' => 'maldives', 'popular' => false],
    ['name' => 'Черногория', 'flag' => '🇲🇪', 'slug' => 'montenegro', 'popular' => false],
    ['name' => 'Оман', 'flag' => '🇴🇲', 'slug' => 'oman', 'popular' => false],
    ['name' => 'Филиппины', 'flag' => '🇵🇭', 'slug' => 'philippines', 'popular' => false],
    ['name' => 'Катар', 'flag' => '🇶🇦', 'slug' => 'qatar', 'popular' => false],
    ['name' => 'Сейшелы', 'flag' => '🇸🇨', 'slug' => 'seychelles', 'popular' => false],
    ['name' => 'Шри-Ланка', 'flag' => '🇱🇰', 'slug' => 'sri-lanka', 'popular' => false],
    ['name' => 'Танзания', 'flag' => '🇹🇿', 'slug' => 'tanzania', 'popular' => false],
    ['name' => 'Тунис', 'flag' => '🇹🇳', 'slug' => 'tunisia', 'popular' => false],
    ['name' => 'Венесуэла', 'flag' => '🇻🇪', 'slug' => 'venezuela', 'popular' => false],
    ['name' => 'Вьетнам', 'flag' => '🇻🇳', 'slug' => 'vietnam', 'popular' => false],
];

// Жёсткий порядок популярных направлений (по ТЗ)
$popularOrder = ['turkey', 'egypt', 'vietnam', 'uae', 'thailand', 'maldives', 'sri-lanka', 'russia', 'abkhazia', 'china'];

$popularCountries = array_filter($countries, function($country) {
    return $country['popular'];
});
$popularBySlug = [];
foreach ($popularCountries as $pc) {
    $popularBySlug[$pc['slug']] = $pc;
}
$popularCountries = [];
foreach ($popularOrder as $slug) {
    if (isset($popularBySlug[$slug])) {
        $popularCountries[] = $popularBySlug[$slug];
    }
}

$allCountries = array_filter($countries, function($country) {
    return !$country['popular'];
});

// Сортируем страны по алфавиту
usort($allCountries, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <title>Все страны - Travel Hub</title>
    <meta name="description" content="Направления для путешествий: Турция, Египет, Таиланд, ОАЭ, Россия, Китай и другие страны. Подбор туров от Travel Hub.">
    <?php
    $seo_proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $seo_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $seo_base = rtrim($seo_proto . '://' . $seo_host, '/');
    $sn = $_SERVER['SCRIPT_NAME'] ?? '';
    $seo_prefix = (strpos($sn, '/frontend/') !== false) ? rtrim(substr($sn, 0, strpos($sn, '/frontend/')), '/') : '';
    $countryListSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'name' => 'Направления для путешествий — Travel Hub',
        'description' => 'Список стран для туров: Турция, Египет, Таиланд, ОАЭ и другие.',
        'numberOfItems' => count($countries),
        'itemListElement' => [],
    ];
    foreach (array_values($countries) as $idx => $c) {
        $countryListSchema['itemListElement'][] = [
            '@type' => 'ListItem',
            'position' => $idx + 1,
            'item' => [
                '@type' => 'Place',
                'name' => $c['name'],
                'url' => $seo_base . $seo_prefix . '/frontend/window/countries/' . $c['slug'] . '.php',
            ],
        ];
    }
    ?>
    <script type="application/ld+json"><?php echo json_encode($countryListSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="/frontend/css/pages/countries-list.css?v=5">
    <?php include __DIR__ . '/../../backend/components/design_system_head.php'; ?>
    <script src="/backend/api/country-images-debug.js.php" defer></script>
</head>
<body class="text-slate-900 countries-list-page-wrap">
    <script>
    (function(){
        var d = <?php echo json_encode($debugCountryImages); ?>;
        console.log('[Country Images] API:', d.apiUrl);
        console.log('[Country Images] response_len:', d.responseLen, '| countries:', d.countriesTotal, '| with_images:', d.withImages, '| slugs:', d.slugs);
        if (d.error) console.warn('[Country Images] error:', d.error);
    })();
    </script>
    <?php 
    $current_page = 'countries';
    include __DIR__ . '/../../backend/components/header.php'; 
    ?>

    <!-- Hero Section -->
    <section class="th-page-hero-v2">
        <div class="container mx-auto px-6 relative z-10">
            <div class="max-w-3xl mx-auto text-center space-y-4">
                <p class="text-white/80 text-sm font-semibold tracking-[0.18em] uppercase">Travel Hub</p>
                <h1 class="heading-font text-4xl md:text-5xl lg:text-6xl font-bold text-white leading-tight">
                    Страны для отдыха
                </h1>
                <p class="text-lg md:text-xl text-white/90 max-w-2xl mx-auto leading-relaxed">
                    Выберите направление — откроем туры и подсказки по стране
                </p>
            </div>
        </div>
    </section>

    <!-- Countries Section -->
    <section class="py-14 md:py-16 bg-[#f7f9fb]">
        <div class="container mx-auto px-6">
            <div class="max-w-6xl mx-auto">
                <!-- Quick filter -->
                <div class="mb-10 max-w-xl mx-auto">
                    <label for="countries-list-filter" class="sr-only">Быстрый поиск страны</label>
                    <div class="relative">
                        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                        <input type="search" id="countries-list-filter" autocomplete="off"
                               placeholder="Найти страну…"
                               class="w-full pl-11 pr-4 py-3.5 rounded-2xl border border-slate-200 bg-white text-slate-800 shadow-sm focus:outline-none focus:ring-2 focus:ring-[#5DA9A4]/30 focus:border-[#5DA9A4]">
                    </div>
                </div>
                <!-- Popular Countries -->
                <?php if (!empty($popularCountries)): ?>
                <div class="mb-14" data-countries-block="popular">
                    <div class="flex items-center gap-3 mb-8">
                        <div class="th-section-accent" aria-hidden="true"></div>
                        <h2 class="heading-font text-2xl md:text-3xl font-bold text-slate-900">Популярные направления</h2>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 gap-6">
                        <?php foreach ($popularCountries as $country):
                            $imgs = $countryImagesMap[$country['slug']] ?? [];
                            $cardImage = countries_list_card_image($country['slug'], $imgs, $countriesListFallbackImages);
                            $iso = $countriesListIso[$country['slug']] ?? '';
                        ?>
                        <a href="/frontend/window/countries/<?php echo htmlspecialchars($country['slug']); ?>.php" class="country-card group" data-country-name="<?php echo htmlspecialchars(mb_strtolower($country['name'], 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars('Туры — ' . $country['name']); ?>">
                            <div class="country-card-media">
                                <img src="<?php echo htmlspecialchars($cardImage); ?>" alt="<?php echo htmlspecialchars($country['name']); ?>" loading="lazy" decoding="async"
                                    data-fallback="<?php echo htmlspecialchars($countriesListFallbackImages[$country['slug']] ?? $countriesListFallbackImages['_default'] ?? ''); ?>"
                                    onerror="var fb=this.getAttribute('data-fallback'); if(fb){this.onerror=null;this.src=fb;}">
                                <div class="country-card-shine" aria-hidden="true"></div>
                                <div class="country-card-overlay"></div>
                                <?php if ($iso !== ''): ?>
                                <span class="country-card-iso" title="<?php echo htmlspecialchars($country['name']); ?>"><?php echo htmlspecialchars(mb_substr($iso, 0, 3, 'UTF-8')); ?></span>
                                <?php endif; ?>
                                <div class="country-card-copy">
                                    <div class="country-card-title-row">
                                        <span class="country-card-emoji" aria-hidden="true"><?php echo $country['flag']; ?></span>
                                        <p class="country-card-title"><?php echo htmlspecialchars($country['name']); ?></p>
                                    </div>
                                    <p class="country-card-subtitle"><i class="fas fa-star text-[10px] opacity-90"></i> Популярное направление</p>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- All Countries -->
                <?php if (!empty($allCountries)): ?>
                <div data-countries-block="all">
                    <div class="flex items-center gap-3 mb-8">
                        <div class="th-section-accent" aria-hidden="true"></div>
                        <h2 class="heading-font text-2xl md:text-3xl font-bold text-slate-900">Все страны</h2>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        <?php foreach ($allCountries as $country):
                            $imgs = $countryImagesMap[$country['slug']] ?? [];
                            $cardImage = countries_list_card_image($country['slug'], $imgs, $countriesListFallbackImages);
                            $iso = $countriesListIso[$country['slug']] ?? '';
                        ?>
                        <a href="/frontend/window/countries/<?php echo htmlspecialchars($country['slug']); ?>.php" class="country-card group" data-country-name="<?php echo htmlspecialchars(mb_strtolower($country['name'], 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars('Туры — ' . $country['name']); ?>">
                            <div class="country-card-media">
                                <img src="<?php echo htmlspecialchars($cardImage); ?>" alt="<?php echo htmlspecialchars($country['name']); ?>" loading="lazy" decoding="async"
                                    data-fallback="<?php echo htmlspecialchars($countriesListFallbackImages[$country['slug']] ?? $countriesListFallbackImages['_default'] ?? ''); ?>"
                                    onerror="var fb=this.getAttribute('data-fallback'); if(fb){this.onerror=null;this.src=fb;}">
                                <div class="country-card-shine" aria-hidden="true"></div>
                                <div class="country-card-overlay"></div>
                                <?php if ($iso !== ''): ?>
                                <span class="country-card-iso" title="<?php echo htmlspecialchars($country['name']); ?>"><?php echo htmlspecialchars(mb_substr($iso, 0, 3, 'UTF-8')); ?></span>
                                <?php endif; ?>
                                <div class="country-card-copy">
                                    <div class="country-card-title-row">
                                        <span class="country-card-emoji" aria-hidden="true"><?php echo $country['flag']; ?></span>
                                        <p class="country-card-title"><?php echo htmlspecialchars($country['name']); ?></p>
                                    </div>
                                    <p class="country-card-subtitle"><i class="fas fa-compass text-[10px] opacity-90"></i> Подбор туров</p>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/../../backend/components/footer.php'; ?>
    <script>
    (function () {
        var input = document.getElementById('countries-list-filter');
        if (!input) return;
        input.addEventListener('input', function () {
            var q = (input.value || '').trim().toLowerCase();
            document.querySelectorAll('.country-card[data-country-name]').forEach(function (card) {
                var name = card.getAttribute('data-country-name') || '';
                var match = !q || name.indexOf(q) !== -1;
                card.hidden = !match;
            });
            document.querySelectorAll('[data-countries-block]').forEach(function (block) {
                var any = false;
                block.querySelectorAll('.country-card[data-country-name]').forEach(function (card) {
                    if (!card.hidden) any = true;
                });
                block.hidden = !any;
            });
        });
    })();
    </script>
<?php PageCache::end(); ?>
</body>
</html>