<?php
// Сначала только кэш — без config и без сессии, чтобы при отдаче из кэша не тратить время
require_once __DIR__ . '/../backend/components/page_cache.php';
// Сессия только если есть cookie (чтобы проверить авторизацию для isAdminRequest)
if (isset($_COOKIE[session_name()]) && $_COOKIE[session_name()] !== '') {
    session_start();
}
if (PageCache::get()) {
    exit; // Страница отдана из кэша
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../backend/config/config.php';
require_once __DIR__ . '/../backend/components/legal_consent_label.php';
PageCache::start();

require_once __DIR__ . '/../backend/components/yandex_metrika.php';
$th_ym_id = th_yandex_metrika_counter_id();

// Language support
$lang = $_GET['lang'] ?? 'ru';
$translations = [
    'ru' => [
        'home' => 'Главная',
        'tours' => 'Туры',
        'services' => 'Услуги',
        'about' => 'О нас',
        'contacts' => 'Контакты',
        'login' => 'Войти',
        'register' => 'Регистрация',
        'special_offers' => 'Специальные предложения',
        
    ],
    'en' => [
        'home' => 'Home',
        'tours' => 'Tours',
        'services' => 'Services',
        'about' => 'About',
        'contacts' => 'Contacts',
        'login' => 'Login',
        'register' => 'Register',
        'special_offers' => 'Special Offers',
        
    ]
];

$current_page = 'home';

// Search UI: wizard = поэтапный (default). Опции: ?search=tv | ?search=v2 | ?search=legacy
if (isset($_GET['search']) && is_string($_GET['search']) && $_GET['search'] !== '') {
    $th_search_ui_raw = strtolower(trim($_GET['search']));
} else {
    $th_search_ui_raw = strtolower(trim((string) (getenv('TH_SEARCH_UI') ?: ($_ENV['TH_SEARCH_UI'] ?? 'wizard'))));
}
if ($th_search_ui_raw === 'legacy') {
    $th_search_ui = 'legacy';
} elseif ($th_search_ui_raw === 'v2') {
    $th_search_ui = 'v2';
} elseif ($th_search_ui_raw === 'tv') {
    $th_search_ui = 'tv';
} else {
    $th_search_ui = 'wizard';
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" href="/frontend/favicon.svg">
    <link rel="alternate icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="shortcut icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <?php
    // SEO настройки для главной страницы
    $page_title = 'Туры и горящие туры — Travel Hub';
    $page_description = 'Туры и горящие туры от Travel Hub: подбор отелей, перелётов, виз, страхования и трансферов. Путешествия по всему миру с персональным консьержем. Ответим за 15 минут.';
    $page_keywords = 'туры, путешествия, горящие туры, премиум туры, эксклюзивные туры, отели, перелёты, визы, страхование, трансферы, турагентство, Travel Hub, отдых, туризм';
    // Путь от корня домена; seo_head превратит в абсолютный URL для Open Graph
    $page_image = '/frontend/window/img/hero/home-hero-1280.webp';
    $hero_img_base = '/frontend/window/img/hero/home-hero';
    $hero_img_srcset = $hero_img_base . '-640.webp 640w, '
        . $hero_img_base . '-960.webp 960w, '
        . $hero_img_base . '-1280.webp 1280w, '
        . $hero_img_base . '-1920.webp 1920w';
    $page_type = 'website';
    $page_lang = $lang;

    if (!function_exists('tourvisor_request_is_https')) {
        require_once __DIR__ . '/../backend/components/tourvisor_proxy_url.php';
    }
    $schema_public_base = (tourvisor_request_is_https() ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'travelhub63.ru');
    
    // Дополнительная Schema.org разметка для главной страницы
    $schema_data = [
        [
            '@type' => 'TravelAgency',
            'name' => 'Travel Hub',
            'description' => 'Премиум туристическое агентство с персональным консьерж-сервисом',
            'url' => $schema_public_base . '/frontend/index.php',
            'sameAs' => [
                'https://t.me/TravelHub63',
                'https://vk.ru/hubtravel',
                'https://max.ru/u/f9LHodD0cOJpBbwh-zr3lqTmDxZiZMLDP-FuyTUa8fyzWO3S2tgc4_Mirnk',
            ],
            'email' => 'hello@travelhub63.ru',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'Московское шоссе, 81Б, ТЦ «Парк Хаус»',
                'addressLocality' => 'Самара',
                'addressCountry' => 'RU'
            ],
            'telephone' => '+78462541656',
            'priceRange' => '$$',
            'currenciesAccepted' => 'RUB, USD, EUR',
            'serviceType' => [
                'Туры',
                'Горящие туры',
                'Подбор отелей',
                'Визы',
                'Страхование'
            ]
        ]
    ];
    
    include __DIR__ . '/../backend/components/seo_head.php';
    ?>
    <?php include __DIR__ . '/../backend/components/tailwind_css.php'; ?>
    <!-- Системные шрифты — отложенная загрузка -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <!-- Critical CSS inline — первый экран сразу -->
    <?php include __DIR__ . '/../backend/components/critical_css.php'; ?>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Work+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Work+Sans:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap"></noscript>
    <link rel="preconnect" href="https://firebase.googleapis.com">
    <link rel="preconnect" href="https://firebaseinstallations.googleapis.com">
    <link rel="preconnect" href="https://maps.yastatic.net">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preload" href="<?php echo htmlspecialchars($hero_img_base . '-960.webp', ENT_QUOTES, 'UTF-8'); ?>" as="image"
          imagesrcset="<?php echo htmlspecialchars($hero_img_srcset, ENT_QUOTES, 'UTF-8'); ?>" imagesizes="100vw"
          fetchpriority="high" type="image/webp">
    
    <!-- Отложенная загрузка некритических стилей -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css"></noscript>
    
    <link rel="stylesheet" href="/frontend/css/tokens.css?v=3">
    <link rel="stylesheet" href="/frontend/css/responsive.css?v=17" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="/frontend/css/responsive.css?v=17"></noscript>
    <link rel="stylesheet" href="/frontend/css/design-system.css?v=14">
    <link rel="stylesheet" href="/frontend/css/redesign.css?v=41">
    <link rel="stylesheet" href="/frontend/css/v2-theme.css?v=5">
    <?php if ($th_search_ui === 'legacy'): ?>
    <link rel="stylesheet" href="/frontend/search-legacy/css/tour-search-wizard.css?v=legacy1">
    <link rel="stylesheet" href="/frontend/search-legacy/css/th-coral-search.css?v=legacy3">
    <?php elseif ($th_search_ui === 'v2'): ?>
    <link rel="stylesheet" href="/frontend/css/tour-search-wizard.css?v=16">
    <link rel="stylesheet" href="/frontend/css/th-coral-search.css?v=29">
    <link rel="stylesheet" href="/frontend/css/th-search-v2.css?v=1">
    <?php elseif ($th_search_ui === 'tv'): ?>
    <link rel="stylesheet" href="/frontend/css/tour-search-wizard.css?v=16">
    <link rel="stylesheet" href="/frontend/css/th-coral-search.css?v=29">
    <link rel="stylesheet" href="/frontend/css/th-search-tv.css?v=7">
    <?php else: ?>
    <link rel="stylesheet" href="/frontend/css/tour-search-wizard.css?v=16">
    <link rel="stylesheet" href="/frontend/css/th-coral-search.css?v=29">
    <?php endif; ?>
    <link rel="stylesheet" href="/frontend/css/th-hard-funnel.css?v=8">
    <link rel="stylesheet" href="/frontend/css/mobile-adult.css?v=16">
    <link rel="stylesheet" href="/frontend/css/th-site-lead.css?v=11">
    <link rel="stylesheet" href="/frontend/css/yandex-mobile.css?v=11">
    <link rel="stylesheet" href="/frontend/css/pages/home.css?v=29">
    <link rel="stylesheet" href="/frontend/css/th-sheet.css?v=7">
    <?php include __DIR__ . '/../backend/components/mobile_site_head.php'; ?>
    <link rel="stylesheet" href="/frontend/css/th-unified-ui.css?v=12">
    <link rel="stylesheet" href="/frontend/css/support-chat.css?v=9">
    <!-- After unified-ui: mobile results dock overrides Call/MAX/Заявка bar -->
    <link rel="stylesheet" href="/frontend/css/th-results-ux.css?v=3">
    <script>window.__TH_YM_ID=<?php echo json_encode((string)$th_ym_id, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
    <script>window.__TH_SEARCH_UI=<?php echo json_encode($th_search_ui, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
    <script src="/frontend/js/v2-theme.js?v=1" defer></script>
    <?php if ($th_search_ui === 'legacy'): ?>
    <script src="/frontend/search-legacy/js/tour-search-wizard.js?v=legacy2" defer></script>
    <script src="/frontend/search-legacy/js/th-coral-search.js?v=legacy1" defer></script>
    <?php elseif ($th_search_ui === 'v2'): ?>
    <script src="/frontend/js/tour-search-wizard.js?v=20" defer></script>
    <script src="/frontend/js/th-coral-search.js?v=14" defer></script>
    <script src="/frontend/js/th-search-v2.js?v=1" defer></script>
    <?php elseif ($th_search_ui === 'tv'): ?>
    <script src="/frontend/js/tour-search-wizard.js?v=20" defer></script>
    <script src="/frontend/js/th-coral-search.js?v=14" defer></script>
    <script src="/frontend/js/th-search-tv.js?v=3" defer></script>
    <?php else: ?>
    <script src="/frontend/js/tour-search-wizard.js?v=20" defer></script>
    <script src="/frontend/js/th-coral-search.js?v=14" defer></script>
    <?php endif; ?>
    <script src="/frontend/js/th-lead-capture.js?v=2" defer></script>
    <script src="/frontend/js/th-mobile.js?v=16" defer></script>
    <script src="/frontend/js/th-modal.js?v=2" defer></script>
    <script src="/frontend/js/th-gallery.js?v=1" defer></script>
    <script src="/frontend/js/support-chat-widget.js?v=4" defer></script>
    
</head>
<body class="text-[#111827] antialiased th-search-ui-<?php echo htmlspecialchars($th_search_ui, ENT_QUOTES, 'UTF-8'); ?>">
    <?php include __DIR__ . '/../backend/components/header.php'; ?>
    <!-- Загрузчик поиска туров (этапы, без фейк %) -->
    <div id="tv-search-loader" class="tv-search-loader" aria-hidden="true">
        <div class="tv-search-loader-box">
            <div class="tv-search-loader-ring tv-search-loader-ring--spin">
                <svg viewBox="0 0 120 120" aria-hidden="true">
                    <defs>
                        <linearGradient id="tv-loader-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#1A1A40"/>
                            <stop offset="100%" stop-color="#5DA9A4"/>
                        </linearGradient>
                    </defs>
                    <circle class="bg" cx="60" cy="60" r="52"/>
                    <circle id="tv-loader-fill" class="fill" cx="60" cy="60" r="52"/>
                </svg>
                <span id="tv-loader-percent" class="tv-search-loader-percent tv-search-loader-percent--stage" aria-hidden="true">···</span>
            </div>
            <p id="tv-loader-msg" class="tv-search-loader-msg">Проверяем кэш...</p>
            <p id="tv-loader-sub" class="tv-search-loader-sub tv-search-loader-sub--sla">Обычно быстро · при необходимости уточняем у операторов</p>
            <p id="tv-loader-instant" aria-live="polite"></p>
        </div>
    </div>
    <!-- Баннер геолокации: только по кнопке «Определить» в форме, не показываем при загрузке -->
    <div id="geo-banner" class="geo-banner hidden" style="display:none">
        <p><i class="fas fa-map-marker-alt text-indigo-600 mr-1"></i>Определить город вылета по местоположению?</p>
        <div class="geo-banner-btns">
            <button type="button" id="geo-allow" class="geo-allow">Разрешить</button>
            <button type="button" id="geo-deny" class="geo-deny">Нет</button>
        </div>
    </div>

    <!-- Hero: полноэкранный фон + поиск в карточке (на мобильных — контент от верха; с md — по центру hero) -->
    <section class="home-hero-section relative flex flex-col justify-start md:justify-center pt-24 pb-12 sm:pt-28 sm:pb-16 md:pb-20 overflow-x-hidden">
        <img src="<?php echo htmlspecialchars($hero_img_base . '-960.webp', ENT_QUOTES, 'UTF-8'); ?>"
                 srcset="<?php echo htmlspecialchars($hero_img_srcset, ENT_QUOTES, 'UTF-8'); ?>"
                 sizes="100vw"
                 alt="Бирюзовое море и песчаный пляж"
                 class="hero-background-img"
                 width="1920"
                 height="1280"
                 loading="eager"
                 fetchpriority="high"
                 decoding="async"
                 data-fallback="<?php echo htmlspecialchars($hero_img_base . '-1280.webp', ENT_QUOTES, 'UTF-8'); ?>"
                 onload="this.classList.add('is-ready');var s=this.closest('.home-hero-section');if(s)s.classList.add('hero-bg-ready');"
                 onerror="if(this.dataset.fallback){this.removeAttribute('srcset');this.src=this.dataset.fallback;this.dataset.fallback='';this.classList.add('is-ready');}">
        <div class="hero-overlay"></div>

        <div class="th-container home-hero-inner mx-auto px-4 sm:px-6 md:px-8 relative z-10 w-full flex flex-col flex-1 min-h-0 justify-start md:justify-center items-center">
            <div class="w-full max-w-5xl mx-auto text-center hero-content mb-5 sm:mb-6 md:mb-8">
                <p class="heading-font text-white/90 text-sm sm:text-base font-semibold tracking-[0.18em] uppercase mb-3 drop-shadow-[0_1px_10px_rgba(0,0,0,0.35)]">Travel Hub</p>
                <h1 class="heading-font text-[1.85rem] sm:text-4xl md:text-5xl font-bold text-white mb-2 leading-[1.2] tracking-tight drop-shadow-[0_2px_24px_rgba(0,0,0,0.45)] th-home-hero__title">
                    <span class="th-home-hero__title-line">Поможем найти тур</span>
                </h1>
                <p class="th-home-hero__steps text-[15px] sm:text-lg text-white/90 max-w-xl mx-auto leading-relaxed drop-shadow-[0_1px_12px_rgba(0,0,0,0.35)]">
                    <span>Откуда</span><span class="th-home-hero__dot" aria-hidden="true">·</span><span>Куда</span><span class="th-home-hero__dot" aria-hidden="true">·</span><span>Даты</span><span class="th-home-hero__dot" aria-hidden="true">·</span><span>Ночи</span><span class="th-home-hero__dot" aria-hidden="true">·</span><span>Туристы</span>
                </p>
            </div>

            <!-- Поиск: wizard = поэтапно (default). ?search=tv — все поля сразу. Логика #tv-* без изменений. -->
            <div id="tour-search-section"
                 class="tv-sc-shell th-coral-search th-coral-wizard th-wizard w-full<?php echo $th_search_ui === 'tv' ? ' th-search-tv' : ($th_search_ui === 'v2' ? ' th-search-v2' : ' th-search-wizard'); ?>"
                 data-th-wizard="home"
                 data-step="<?php echo $th_search_ui === 'tv' ? '5' : '1'; ?>"
                 data-start-step="<?php echo $th_search_ui === 'tv' ? '5' : '1'; ?>"
                 data-search-mode="tours">
                <?php
                if (!function_exists('th_departure_default_id')) {
                    require_once __DIR__ . '/../backend/config/departure_defaults.php';
                }
                $th_def_dep_name = htmlspecialchars(th_departure_default_name(), ENT_QUOTES, 'UTF-8');
                ?>

                <div class="th-search-mode" role="tablist" aria-label="Тип поиска">
                    <button type="button" class="th-search-mode__btn is-active" data-th-search-mode="tours" role="tab" aria-selected="true">Туры</button>
                    <button type="button" class="th-search-mode__btn" data-th-search-mode="hotels" role="tab" aria-selected="false">Отели</button>
                </div>
                <p class="th-search-mode__hint" id="th-hotel-mode-hint" hidden>Цены только за проживание — без перелёта</p>

                <?php if ($th_search_ui === 'tv'): ?>
                <div class="th-search-tv__form">
                <?php endif; ?>
                <nav class="th-coral-wizard__rail" aria-label="Параметры поиска">
                    <button type="button" class="th-coral-wizard__rail-item<?php echo $th_search_ui === 'tv' ? '' : ' is-active'; ?>" data-thw-goto="1" data-th-search-open="departure"<?php echo $th_search_ui === 'tv' ? '' : ' aria-current="step"'; ?>>
                        <span class="th-coral-search__label" data-th-dep-label>Откуда</span>
                        <span class="th-coral-wizard__rail-value" data-th-label="departure"><?php echo $th_def_dep_name; ?></span>
                    </button>
                    <button type="button" class="th-coral-wizard__rail-item" data-thw-goto="2" data-th-search-open="country">
                        <span class="th-coral-search__label">Куда</span>
                        <span class="th-coral-wizard__rail-value is-placeholder" data-th-label="country">Загрузка…</span>
                    </button>
                    <button type="button" class="th-coral-wizard__rail-item" data-thw-goto="3" data-th-search-open="dates">
                        <span class="th-coral-search__label" data-th-dates-label>Даты вылета</span>
                        <span class="th-coral-wizard__rail-value is-placeholder" data-th-label="dates">Даты</span>
                    </button>
                    <button type="button" class="th-coral-wizard__rail-item" data-thw-goto="4" data-th-search-open="nights">
                        <span class="th-coral-search__label">Ночей</span>
                        <span class="th-coral-wizard__rail-value" data-th-label="nights">6–9 ночей</span>
                    </button>
                    <button type="button" class="th-coral-wizard__rail-item" data-thw-goto="5" data-th-search-open="tourists">
                        <span class="th-coral-search__label">Туристы</span>
                        <span class="th-coral-wizard__rail-value" data-th-label="tourists">2 взрослых</span>
                    </button>
                </nav>
                <?php if ($th_search_ui === 'tv'): ?>
                    <button type="button" class="th-search-tv__find" data-th-tv-find>
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <span>Найти</span>
                    </button>
                </div>
                <?php endif; ?>

                <div class="th-wizard__stepbar" aria-live="polite">
                    <div class="th-wizard__stepbar-track" aria-hidden="true">
                        <span class="th-wizard__stepbar-fill" data-thw-progress style="width:20%"></span>
                    </div>
                    <p class="th-wizard__stepbar-label" id="th-wizard-step-label">1 из 5 · Откуда</p>
                </div>

                <nav class="th-wizard__progress sr-only" aria-label="Шаги поиска">
                    <button type="button" class="th-wizard__dot is-active" data-thw-goto="1" aria-current="step"><span class="th-wizard__dot-num">1</span></button>
                    <button type="button" class="th-wizard__dot" data-thw-goto="2"><span class="th-wizard__dot-num">2</span></button>
                    <button type="button" class="th-wizard__dot" data-thw-goto="3"><span class="th-wizard__dot-num">3</span></button>
                    <button type="button" class="th-wizard__dot" data-thw-goto="4"><span class="th-wizard__dot-num">4</span></button>
                    <button type="button" class="th-wizard__dot" data-thw-goto="5"><span class="th-wizard__dot-num">5</span></button>
                </nav>

                <div class="th-wizard__panels">
                    <div class="th-wizard__panel is-active" data-panel="1">
                        <button type="button" class="th-coral-search__field th-coral-search__field--step" data-th-search-open="departure" aria-label="Откуда">
                            <span class="th-coral-search__field-inner">
                                <span class="th-coral-search__label">Откуда</span>
                                <span class="th-coral-search__value" data-th-label="departure"><?php echo $th_def_dep_name; ?></span>
                            </span>
                            <i class="fas fa-chevron-right th-coral-search__chevron" aria-hidden="true"></i>
                        </button>
                        <div class="th-wizard__nav">
                            <button type="button" class="th-wizard__back" data-thw-back hidden>Назад</button>
                            <button type="button" class="th-wizard__next" data-thw-next>Далее</button>
                        </div>
                    </div>

                    <div class="th-wizard__panel" data-panel="2" hidden>
                        <button type="button" class="th-coral-search__field th-coral-search__field--step" data-th-search-open="country" aria-label="Куда">
                            <span class="th-coral-search__field-inner">
                                <span class="th-coral-search__label">Куда</span>
                                <span class="th-coral-search__value is-placeholder" data-th-label="country">Выберите страну</span>
                            </span>
                            <i class="fas fa-chevron-right th-coral-search__chevron" aria-hidden="true"></i>
                        </button>
                        <div class="th-wizard__nav">
                            <button type="button" class="th-wizard__back" data-thw-back>Назад</button>
                            <button type="button" class="th-wizard__next" data-thw-next>Далее</button>
                        </div>
                    </div>

                    <div class="th-wizard__panel" data-panel="3" hidden>
                        <button type="button" class="th-coral-search__field th-coral-search__field--step" data-th-search-open="dates" aria-label="Когда">
                            <span class="th-coral-search__field-inner">
                                <span class="th-coral-search__label" data-th-dates-label>Даты вылета</span>
                                <span class="th-coral-search__value is-placeholder" data-th-label="dates">Выберите даты</span>
                            </span>
                            <i class="fas fa-chevron-right th-coral-search__chevron" aria-hidden="true"></i>
                        </button>
                        <div class="th-wizard__nav th-wizard__nav--dates">
                            <button type="button" class="th-wizard__back" data-thw-back>Назад</button>
                            <button type="button" class="th-wizard__next" data-thw-next>Далее</button>
                            <button type="button" class="th-hotel-find-btn th-wizard__cta" data-th-hotel-find>
                                <i class="fas fa-hotel" aria-hidden="true"></i>
                                <span>Найти отели</span>
                            </button>
                        </div>
                    </div>

                    <div class="th-wizard__panel" data-panel="4" hidden>
                        <button type="button" class="th-coral-search__field th-coral-search__field--step" data-th-search-open="nights" aria-label="Сколько ночей">
                            <span class="th-coral-search__field-inner">
                                <span class="th-coral-search__label">Ночей</span>
                                <span class="th-coral-search__value" data-th-label="nights">6–9 ночей</span>
                            </span>
                            <i class="fas fa-chevron-right th-coral-search__chevron" aria-hidden="true"></i>
                        </button>
                        <div class="th-wizard__nav">
                            <button type="button" class="th-wizard__back" data-thw-back>Назад</button>
                            <button type="button" class="th-wizard__next" data-thw-next>Далее</button>
                        </div>
                    </div>

                    <div class="th-wizard__panel" data-panel="5" hidden>
                        <button type="button" class="th-coral-search__field th-coral-search__field--step" data-th-search-open="tourists" aria-label="Туристы">
                            <span class="th-coral-search__field-inner">
                                <span class="th-coral-search__label">Туристы</span>
                                <span class="th-coral-search__value" data-th-label="tourists">2 взрослых</span>
                            </span>
                            <i class="fas fa-chevron-right th-coral-search__chevron" aria-hidden="true"></i>
                        </button>
                        <div class="th-wizard__nav">
                            <button type="button" class="th-wizard__back" data-thw-back>Назад</button>
                            <button id="tv-search-btn" type="button" class="th-coral-search__search-btn button button-primary tv-sc-search-btn">
                                <i class="fas fa-search" aria-hidden="true"></i>
                                <span class="tv-sc-search-text">Найти</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="th-coral-wizard__filters-wrap">
                    <button type="button" class="th-coral-wizard__filters-btn" id="th-wizard-toggle-filters"
                            aria-expanded="false" aria-controls="th-wizard-filters-panel">
                        <i class="fas fa-sliders-h" aria-hidden="true"></i>
                        <span>Фильтры</span>
                    </button>
                    <div class="th-wizard__extra-filters" id="th-wizard-filters-panel" hidden aria-label="Дополнительные фильтры">
                        <div class="th-wizard__extra-filters-grid">
                            <div class="tv-sc-field tv-sc-field--sel th-wizard__filter-field">
                                <span class="tv-sc-field-label">Питание</span>
                                <button type="button" class="th-wizard__filter-trigger" data-th-filter-open="meal"
                                        aria-haspopup="dialog" aria-controls="th-search-meal-sheet">
                                    <span class="th-wizard__filter-value" data-th-filter-label="meal">Любое</span>
                                    <i class="fas fa-chevron-down th-wizard__filter-chevron" aria-hidden="true"></i>
                                </button>
                                <select id="tv-meal" class="tv-sc-select tv-select tv-filter-field th-wizard__filter-native" aria-label="Питание" tabindex="-1">
                                    <option value="">Любое</option>
                                </select>
                            </div>
                            <div class="tv-sc-field tv-sc-field--sel th-wizard__filter-field">
                                <span class="tv-sc-field-label">Курорт</span>
                                <button type="button" class="th-wizard__filter-trigger" data-th-filter-open="region"
                                        aria-haspopup="dialog" aria-controls="th-search-region-sheet">
                                    <span class="th-wizard__filter-value" data-th-filter-label="region">Любой</span>
                                    <i class="fas fa-chevron-down th-wizard__filter-chevron" aria-hidden="true"></i>
                                </button>
                                <select id="tv-region" class="tv-sc-select tv-select tv-filter-field th-wizard__filter-native" aria-label="Курорт" tabindex="-1">
                                    <option value="">Любой</option>
                                </select>
                            </div>
                            <div class="tv-sc-field tv-sc-field--sel th-wizard__filter-field">
                                <span class="tv-sc-field-label">Звёзды</span>
                                <button type="button" class="th-wizard__filter-trigger" data-th-filter-open="category"
                                        aria-haspopup="dialog" aria-controls="th-search-category-sheet">
                                    <span class="th-wizard__filter-value" data-th-filter-label="category">Любая</span>
                                    <i class="fas fa-chevron-down th-wizard__filter-chevron" aria-hidden="true"></i>
                                </button>
                                <select id="tv-category" class="tv-sc-select tv-select tv-filter-field th-wizard__filter-native" aria-label="Категория отеля" tabindex="-1">
                                    <option value="">Любая</option>
                                    <option value="3">3★+</option>
                                    <option value="4">4★+</option>
                                    <option value="5">5★</option>
                                </select>
                            </div>
                        </div>
                        <div class="th-wizard__flight-toggles" role="group" aria-label="Тип перелёта">
                            <label class="th-wizard__flight-toggle">
                                <input type="checkbox" id="tv-only-charter" class="th-wizard__flight-toggle-input">
                                <span>Только чартер</span>
                            </label>
                            <label class="th-wizard__flight-toggle">
                                <input type="checkbox" id="tv-only-direct" class="th-wizard__flight-toggle-input">
                                <span>Только прямой</span>
                            </label>
                        </div>
                        <button type="button" id="tv-filters-modal-open" class="sr-only" aria-hidden="true" tabindex="-1"
                                aria-haspopup="dialog" aria-controls="tv-filters-modal" aria-expanded="false">Фильтры</button>
                    </div>
                </div>

                <div class="th-coral-search__native" aria-hidden="true">
                    <select id="tv-departure" name="departureId" class="tv-select" tabindex="-1">
                        <option value="<?php echo (int) th_departure_default_id(); ?>"><?php echo $th_def_dep_name; ?></option>
                    </select>
                    <select id="tv-country" name="countryId" class="tv-select" tabindex="-1">
                        <option value="">Страна</option>
                    </select>
                    <div id="tv-sc-dates-field">
                        <button type="button" id="tv-sc-dates-btn" aria-haspopup="dialog" aria-expanded="false"
                                aria-controls="tv-sc-date-popup" tabindex="-1">
                            <span id="tv-sc-dates-display">Даты</span>
                        </button>
                        <div id="tv-dates-wrap" class="tv-sc-fp-hidden">
                            <input type="text" id="tv-dates" class="tv-search-control"
                                   placeholder="Выберите период" data-input readonly autocomplete="off" tabindex="-1">
                        </div>
                    </div>
                    <div id="tv-nights-trigger">
                        <button type="button" id="tv-nights-summary" tabindex="-1" aria-hidden="true">
                            <span id="tv-nights-summary-text">6–9 ночей</span>
                        </button>
                    </div>
                    <div id="tv-tourists-trigger">
                        <button type="button" id="tv-tourists-summary" tabindex="-1">
                            <span id="tv-tourists-summary-text">2 взрослых</span>
                        </button>
                    </div>
                </div>

                <div class="tv-sc-row th-wizard__legacy-fields" id="tv-sc-main-row" aria-hidden="true"></div>

                <!-- ─── ПОПАП: ТУРИСТЫ ─── -->
                <div id="tv-tourists-block" class="th-coral-popup th-coral-tourists-popup hidden" role="dialog" aria-label="Туристы" aria-modal="true">
                    <div class="th-coral-popup__backdrop" data-tv-tourists-close></div>
                    <div class="th-coral-popup__panel">
                        <div class="th-coral-popup__head">
                            <div class="th-coral-popup__head-main">
                                <span class="th-coral-popup__eyebrow">Travel Hub</span>
                                <span class="th-coral-popup__title">Туристы</span>
                            </div>
                            <button type="button" id="tv-tourists-close-btn" class="th-coral-popup__close" data-tv-tourists-close aria-label="Закрыть">
                                <i class="fas fa-times" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="th-coral-popup__body">
                    <div class="tv-sc-counter-row">
                        <span class="tv-sc-counter-label">Взрослые</span>
                        <div class="tv-sc-counter-ctrl">
                            <button type="button" id="tv-adults-minus" class="tv-sc-cnt-btn" aria-label="Меньше">
                                <i class="fas fa-minus"></i>
                            </button>
                            <span class="tv-sc-cnt-val" id="tv-adults-value">2</span>
                            <button type="button" id="tv-adults-plus" class="tv-sc-cnt-btn" aria-label="Больше">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div id="tv-children-rows" class="tv-sc-children-rows"></div>
                    <button type="button" id="tv-add-child-btn" class="tv-sc-add-child">
                        <i class="fas fa-plus"></i> Добавить ребёнка
                    </button>
                    <div id="tv-child-age-picker" class="hidden tv-sc-age-picker">
                        <p class="tv-sc-age-hint">Возраст ребёнка</p>
                        <div id="tv-child-age-grid" class="tv-sc-age-grid"></div>
                    </div>
                    <label class="tv-sc-remember">
                        <input type="checkbox" id="tv-remember-tourists">
                        <span>Запомнить</span>
                    </label>
                        </div>
                    <button type="button" id="tv-tourists-apply" class="th-coral-popup__apply">
                        <i class="fas fa-check" aria-hidden="true"></i> Применить
                    </button>
                    </div>
                </div>

                <!-- ─── ПОПАП: ДАТЫ ─── -->
                <div id="tv-sc-date-popup" class="th-coral-popup th-coral-date-popup hidden"
                     role="dialog" aria-label="Когда вылетаете" aria-modal="true">
                    <div class="th-coral-popup__backdrop" data-sc-close="tv-sc-date-popup"></div>
                    <div class="th-coral-popup__panel">
                        <div class="th-coral-popup__head">
                            <div class="th-coral-popup__head-main">
                                <span class="th-coral-popup__eyebrow">Travel Hub</span>
                                <span class="th-coral-popup__title">Когда вылетаете?</span>
                            </div>
                            <button type="button" class="th-coral-popup__close" data-sc-close="tv-sc-date-popup" aria-label="Закрыть">
                                <i class="fas fa-times" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="th-coral-popup__body">
                            <p id="tv-sc-dates-preview" class="th-coral-popup__preview" aria-live="polite"></p>
                            <p id="tv-sc-dates-step" class="th-coral-popup__hint" aria-live="polite">Выберите период вылета (от и до)</p>
                            <div id="tv-sc-cal-panel" class="tv-sc-cal-panel">
                                <div id="tv-sc-cal-container" class="tv-sc-cal-container"></div>
                                <div class="th-flight-legend th-flight-legend--cal" role="note" aria-label="Обозначения календаря">
                                    <span class="th-flight-legend__chip th-flight-legend__chip--direct"><span class="th-flight-legend__mark" aria-hidden="true"></span>Прямой рейс</span>
                                    <span class="th-flight-legend__chip th-flight-legend__chip--fly"><span class="th-flight-legend__mark" aria-hidden="true"></span>Перелёт (с пересадкой)</span>
                                    <span class="th-flight-legend__chip th-flight-legend__chip--deal"><span class="th-flight-legend__mark" aria-hidden="true"></span>Выгодная цена</span>
                                </div>
                            </div>
                        </div>
                        <button type="button" id="tv-sc-dates-apply" class="th-coral-popup__apply">
                            <i class="fas fa-check" aria-hidden="true"></i> Применить
                        </button>
                    </div>
                </div>

                <div id="tv-sc-overlay" class="tv-sc-overlay" style="display:none" aria-hidden="true"></div>

            </div>
                <div id="main-quick-lead" class="hidden" aria-hidden="true">
                    <p class="hidden">removed</p>
                    <form id="main-quick-lead-form" class="space-y-3 relative">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <input type="text" name="name" required placeholder="ФИО" autocomplete="name" class="rounded-xl px-3 py-2.5 text-slate-900 text-sm border-0 shadow-inner">
                            <input type="tel" name="phone" required placeholder="Телефон" autocomplete="tel" class="rounded-xl px-3 py-2.5 text-slate-900 text-sm border-0 shadow-inner">
                        </div>
                        <label class="flex items-start gap-2 text-xs text-white/90 cursor-pointer">
                            <input type="checkbox" name="agree" required class="mt-0.5 rounded border-white/40">
                            <span><?php echo th_legal_consent_checkbox_html(); ?></span>
                        </label>
                        <input type="text" name="website" class="absolute opacity-0 pointer-events-none w-px h-px overflow-hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
                        <button type="submit" class="w-full rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 text-sm transition-colors">Отправить</button>
                        <p id="main-quick-lead-msg" class="hidden text-xs rounded-lg p-2"></p>
                    </form>
                </div>
            </div>
    </section>

            <!-- Результаты поиска -->
            <section id="tv-results-section" class="bg-[#F9FAFB] py-4 md:py-6 border-t border-gray-200/60">
            <div class="th-container mx-auto px-4 sm:px-6 md:px-8 max-w-7xl">
            <div id="tv-results-wrapper" class="tv-results-shell hidden">
                <div class="tv-results-lead-bar hidden" id="tv-results-lead-bar">
                    <button type="button" class="tv-results-lead-bar__btn" data-open-lead-modal="results-toolbar">
                        <i class="fas fa-phone" aria-hidden="true"></i>
                        Не нашли идеальный тур? Оставьте телефон — подберём
                    </button>
                </div>
                <div id="tv-search-alt-banner" class="tv-search-alt-banner hidden" role="status"></div>
                <!-- Двухколоночный макет: sidebar слева + карточки справа -->
                <div class="tv-results-layout">
                    <!-- Sidebar с фильтрами (только на десктопе) -->
                    <aside class="tv-results-sidebar tv-post-filters" id="tv-results-sidebar" aria-label="Фильтры результатов">
                        <div class="tv-results-sidebar__title">
                            <i class="fas fa-sliders-h"></i>
                            Уточнить выдачу
                        </div>
                        <div class="tv-sidebar-filter-group">
                            <span class="tv-sidebar-filter-label th-filter-stars-label">Звёздность</span>
                            <div class="tv-pf-chips">
                                <button type="button" class="tv-pf-chip" data-pf-star data-pf-value="5" aria-pressed="false">5 звёзд</button>
                                <button type="button" class="tv-pf-chip" data-pf-star data-pf-value="4" aria-pressed="false">4 звезды</button>
                                <button type="button" class="tv-pf-chip" data-pf-star data-pf-value="3plus" aria-pressed="false">3 и выше</button>
                            </div>
                        </div>
                        <div class="tv-sidebar-filter-group">
                            <span class="tv-sidebar-filter-label">Питание</span>
                            <div class="tv-pf-chips" data-pf-meals>
                                <button type="button" class="tv-pf-chip" data-pf-meal data-pf-value="AI" aria-pressed="false">Всё включено</button>
                                <button type="button" class="tv-pf-chip" data-pf-meal data-pf-value="HB" aria-pressed="false">Завтрак + ужин</button>
                                <button type="button" class="tv-pf-chip" data-pf-meal data-pf-value="BB" aria-pressed="false">Завтрак</button>
                                <button type="button" class="tv-pf-chip" data-pf-meal data-pf-value="RO" aria-pressed="false">Без питания</button>
                            </div>
                        </div>
                        <div class="tv-sidebar-filter-group">
                            <span class="tv-sidebar-filter-label">Бюджет, ₽</span>
                            <div class="tv-pf-budget-row">
                                <input type="number" data-pf-price-min class="tv-filter-field" inputmode="numeric" min="0" step="1000" placeholder="От" autocomplete="off">
                                <span aria-hidden="true">—</span>
                                <input type="number" data-pf-price-max class="tv-filter-field" inputmode="numeric" min="0" step="1000" placeholder="До" autocomplete="off">
                            </div>
                            <div class="tv-pf-chips tv-pf-chips--budget">
                                <button type="button" class="tv-pf-chip tv-pf-chip--sm" data-pf-budget-quick="150000">до 150 тыс.</button>
                                <button type="button" class="tv-pf-chip tv-pf-chip--sm" data-pf-budget-quick="200000">до 200 тыс.</button>
                                <button type="button" class="tv-pf-chip tv-pf-chip--sm" data-pf-budget-quick="300000">до 300 тыс.</button>
                            </div>
                        </div>
                        <div class="tv-sidebar-filter-group">
                            <span class="tv-sidebar-filter-label">Курорты</span>
                            <div class="tv-pf-regions" data-pf-regions><p class="tv-pf-hint">Появятся после поиска</p></div>
                        </div>
                        <div class="tv-sidebar-filter-group" data-pf-beach-group style="display:none">
                            <span class="tv-sidebar-filter-label">Линия пляжа</span>
                            <div class="tv-pf-chips">
                                <button type="button" class="tv-pf-chip" data-pf-beach="1" aria-pressed="false">1-я линия (у моря)</button>
                                <button type="button" class="tv-pf-chip" data-pf-beach="2" aria-pressed="false">2-я линия</button>
                            </div>
                        </div>
                        <button type="button" data-pf-reset class="tv-pf-reset">Сбросить фильтры</button>
                    </aside>

                    <!-- Основная колонка: прогресс + карточки -->
                    <div class="tv-results-main" style="min-width:0">
                        <div class="tv-results-toolbar">
                            <div class="tv-results-toolbar__head">
                            <h3 class="tv-results-toolbar__title heading-font text-xl font-bold text-slate-900">
                                Найдено <span id="tv-result-count">0</span> <span id="tv-result-noun">туров</span>
                            </h3>
                            <p class="tv-results-hotel-note" id="tv-results-hotel-note" hidden>Цены без перелёта — только проживание</p>
                            </div>
                            <div class="tv-sort-rail">
                                <div class="tv-results-calendar-quick" role="group" aria-label="Сдвиг дат">
                                    <button type="button" class="tv-cal-quick-btn" data-tv-shift-days="-3">−3 дня</button>
                                    <button type="button" class="tv-cal-quick-btn" data-tv-shift-days="3">+3 дня</button>
                                </div>
                                <select id="tv-sort" class="tv-select tv-sort-select px-3 py-2 rounded-xl border border-slate-200 text-slate-700" aria-label="Сортировка">
                                    <option value="price-asc">Сначала дешевые</option>
                                    <option value="price-desc">Сначала дорогие</option>
                                    <option value="rating">По рейтингу</option>
                                </select>
                                <button type="button" id="tv-compare-open-btn" class="tv-compare-open-btn is-empty" aria-haspopup="dialog" aria-controls="tv-compare-panel">
                                    Сравнение (<span id="tv-compare-count">0</span>)
                                </button>
                            </div>
                        </div>
                        <div id="tv-price-calendar" class="tv-price-calendar" aria-live="polite"></div>
                        <div id="tv-search-progress" class="hidden mb-6 p-4 rounded-xl bg-slate-50 border border-slate-200">
                            <div class="flex items-center gap-3">
                                <div class="animate-spin w-5 h-5 border-2 border-[#FF6B6B] border-t-transparent rounded-full"></div>
                                <span class="text-slate-600">Поиск туров...</span>
                                <span id="tv-progress-text" class="text-slate-500 text-sm"></span>
                            </div>
                        </div>
                        <div id="tv-live-refine" class="tv-live-refine hidden" aria-live="polite">
                            <span class="tv-live-refine__spin" aria-hidden="true"></span>
                            <span id="tv-live-refine-text">Уточняем цены у операторов…</span>
                        </div>
                        <div id="tv-flight-legend" class="th-flight-legend th-flight-legend--compact hidden" role="note" aria-label="Обозначения перелёта на карточках"></div>
                        <div id="tv-search-results" class="tv-search-results-grid th-tour-grid">
                            <!-- Карточки туров подставляются JS -->
                        </div>
                        <div id="tv-load-more-wrapper" class="mt-10 text-center hidden">
                            <button type="button" id="tv-load-more-btn" class="button button-primary px-8 py-3.5 text-sm disabled:opacity-70 disabled:pointer-events-none disabled:hover:scale-100">
                                <i class="fas fa-plus-circle mr-2"></i><span id="tv-load-more-text">Загрузить ещё туры</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div id="tv-compare-panel" class="tv-compare-panel tv-compare-modal hidden" role="dialog" aria-modal="true" aria-labelledby="tv-compare-title" aria-hidden="true">
        <div class="tv-compare-modal__backdrop" data-tv-compare-close></div>
        <div class="tv-compare-modal__sheet">
            <div class="tv-compare-panel__head">
                <h4 id="tv-compare-title">Сравнение туров</h4>
                <div class="tv-compare-modal__actions">
                    <button type="button" id="tv-compare-clear-btn" class="tv-compare-clear-btn">Очистить</button>
                    <button type="button" class="tv-compare-modal__close" data-tv-compare-close aria-label="Закрыть">×</button>
                </div>
            </div>
            <div id="tv-compare-best" class="tv-compare-best hidden"></div>
            <div id="tv-compare-list" class="tv-compare-list"></div>
        </div>
    </div>

    <!-- Горящие туры: готовые карточки отелей с ценой -->
    <?php
    $homeShowcaseBoot = [
        'success' => true,
        'departureId' => 7,
        'departureName' => 'Самара',
        'mode' => 'tours',
        'source' => 'ssr_empty',
        'flightsByTourId' => [],
        'moods' => [],
        'hot' => [],
        'tours' => [],
    ];
    try {
        require_once __DIR__ . '/../backend/components/home_showcase_build.php';
        $bootDep = (int) (function_exists('th_departure_default_id') ? th_departure_default_id() : 7);
        if ($bootDep !== 1 && $bootDep !== 7) {
            $bootDep = 7;
        }
        $homeShowcaseBoot = th_home_showcase_build($bootDep, false);
    } catch (Throwable $e) {
        // оставляем пустую витрину — JS догрузит
    }
    $homeShowcaseBootTours = [];
    if (!empty($homeShowcaseBoot['moods']['beach']['items']) && is_array($homeShowcaseBoot['moods']['beach']['items'])) {
        $homeShowcaseBootTours = $homeShowcaseBoot['moods']['beach']['items'];
    } elseif (!empty($homeShowcaseBoot['hot']) && is_array($homeShowcaseBoot['hot'])) {
        $homeShowcaseBootTours = $homeShowcaseBoot['hot'];
    } elseif (!empty($homeShowcaseBoot['tours']) && is_array($homeShowcaseBoot['tours'])) {
        $homeShowcaseBootTours = $homeShowcaseBoot['tours'];
    }
    $homeShowcaseBootTours = array_slice($homeShowcaseBootTours, 0, 8);
    ?>
    <?php
    /* Блок «Хотите дешевле?» убран с главной — промо на /promotions и в апп-флоу */
    ?>
    <section class="th-home-showcase py-6 md:py-10 bg-[#F9FAFB]" aria-labelledby="dest-heading">
        <div class="th-container mx-auto px-4 sm:px-6 md:px-8 max-w-7xl">
            <div class="th-showcase-head mb-5 md:mb-6">
                <div>
                    <h2 id="dest-heading" class="heading-font text-2xl sm:text-3xl font-bold text-[#111827]">Горящие туры</h2>
                    <p class="text-[#6B7280] text-sm sm:text-base mt-1">Готовые варианты с ценой — открывайте сразу</p>
                </div>
                <a class="th-showcase-hot__all" href="/frontend/window/promotions.php">Смотреть все</a>
            </div>

            <div id="home-hot-tours-grid" class="th-tour-grid">
                <?php for ($sk = 0; $sk < 4; $sk++): ?>
                    <article class="th-tour-card th-tour-card--skeleton" aria-hidden="true">
                        <div class="th-tour-card__media th-tour-card__media--skeleton"></div>
                        <div class="th-tour-card__body">
                            <p class="th-tour-card__geo">…</p>
                            <h3 class="th-tour-card__name">Загружаем туры</h3>
                        </div>
                    </article>
                <?php endfor; ?>
            </div>
            <p id="home-hot-tours-empty" class="th-showcase-empty<?php echo $homeShowcaseBootTours !== [] ? ' hidden' : ''; ?>">Сейчас подгружаем актуальные туры… Если долго не появляются — откройте <a href="/frontend/window/promotions.php">все акции</a>.</p>
            <p class="mt-5 text-center text-sm text-[#6B7280]">
                Больше вариантов — в <a href="/frontend/window/promotions.php" class="font-semibold text-[#5DA9A4] hover:underline">акциях</a>
                и <a href="/frontend/window/tour-calendar.php" class="font-semibold text-[#5DA9A4] hover:underline">календаре выгодных дат</a>.
            </p>
        </div>
    </section>

    <section class="th-home-trust py-8 md:py-10 bg-white" aria-label="Почему Travel Hub">
        <div class="th-container mx-auto px-4 sm:px-6 md:px-8 max-w-7xl">
            <ul class="th-home-trust__list">
                <li class="th-home-trust__item">
                    <span class="th-home-trust__icon" aria-hidden="true"><i class="fas fa-search"></i></span>
                    <div>
                        <strong>Поиск по операторам</strong>
                        <p>Актуальные цены туров в одном месте</p>
                    </div>
                </li>
                <li class="th-home-trust__item">
                    <span class="th-home-trust__icon" aria-hidden="true"><i class="fas fa-headset"></i></span>
                    <div>
                        <strong>Живая поддержка</strong>
                        <p>Ответим в мессенджере или по телефону</p>
                    </div>
                </li>
                <li class="th-home-trust__item">
                    <span class="th-home-trust__icon" aria-hidden="true"><i class="fas fa-map-marker-alt"></i></span>
                    <div>
                        <strong>Офисы в Самаре и Москве</strong>
                        <p><a href="/frontend/window/offices.php">Адреса и как добраться</a></p>
                    </div>
                </li>
            </ul>
        </div>
    </section>

    <?php
    require_once __DIR__ . '/../backend/config/contacts.php';
    $_th_sticky_c = th_contacts();
    ?>
    <nav id="th-results-sticky-lead" class="th-results-sticky-lead th-results-sticky-lead--actions th-results-sticky-lead--dock" aria-label="Фильтры и связь">
        <!-- Mobile/tablet: post-filters dock (Call/MAX/Заявка → компакт справа) -->
        <div class="th-rsd__filters" aria-label="Пост-фильтры выдачи">
            <button type="button" class="th-rsd__all-btn" id="th-rsd-open-filters"
                    aria-expanded="false" aria-controls="th-results-pf-sheet">
                <i class="fas fa-sliders-h" aria-hidden="true"></i>
                <span>Фильтры</span>
            </button>
        </div>
        <div class="th-rsd__contact" aria-label="Быстрая связь">
            <a class="th-results-sticky-lead__action th-results-sticky-lead__action--call th-rsd__icon-btn"
               href="tel:<?php echo htmlspecialchars($_th_sticky_c['phone_tel'], ENT_QUOTES, 'UTF-8'); ?>"
               data-th-track="call_bar" aria-label="Позвонить">
                <i class="fas fa-phone" aria-hidden="true"></i>
                <span class="th-rsd__label">Звонок</span>
            </a>
            <a class="th-results-sticky-lead__action th-results-sticky-lead__action--max th-rsd__icon-btn"
               href="<?php echo htmlspecialchars($_th_sticky_c['max_url'], ENT_QUOTES, 'UTF-8'); ?>"
               target="_blank" rel="noopener noreferrer"
               data-th-track="max_bar" aria-label="MAX">
                <span>MAX</span>
            </a>
            <button type="button" class="th-results-sticky-lead__action th-results-sticky-lead__action--lead th-rsd__icon-btn"
                    data-open-lead-modal="results-sticky"
                    data-th-track="lead_bar" aria-label="Заявка">
                <i class="fas fa-comment-dots" aria-hidden="true"></i>
                <span class="th-rsd__label">Заявка</span>
            </button>
        </div>
    </nav>

    <!-- Mobile: полный сайдбар пост-фильтров как bottom sheet -->
    <div id="th-results-pf-sheet" class="th-sheet th-results-pf-sheet hidden" aria-hidden="true">
        <div class="th-sheet__backdrop" data-th-rsd-close></div>
        <div class="th-sheet__panel" role="dialog" aria-modal="true" aria-labelledby="th-rsd-sheet-title">
            <button type="button" class="th-sheet__close" data-th-rsd-close aria-label="Закрыть">&times;</button>
            <div class="th-results-pf-sheet__head">
                <h2 id="th-rsd-sheet-title" class="th-results-pf-sheet__title">Уточнить выдачу</h2>
                <p class="th-results-pf-sheet__sub">Звёзды, питание, бюджет, курорты и оценки гостей</p>
            </div>
            <div class="th-results-pf-sheet__body" id="th-results-pf-sheet-body"></div>
            <div class="th-results-pf-sheet__foot">
                <button type="button" class="th-results-pf-sheet__apply" data-th-rsd-close>Показать туры</button>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../backend/components/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/ru.js" defer></script>
    <?php
    $_th_fpick_path_idx = __DIR__ . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'tourvisor-flight-pick.js';
    $_th_fpick_ver_idx = is_file($_th_fpick_path_idx) ? (string) filemtime($_th_fpick_path_idx) : '1';
    ?>
    <script src="/frontend/js/tourvisor-flight-pick.js?v=<?php echo htmlspecialchars($_th_fpick_ver_idx, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script>
        <?php
        require_once __DIR__ . '/../backend/components/tourvisor_proxy_url.php';
        $tv_proxy_base = get_tourvisor_proxy_base_url();
        $tv_image_proxy_base = get_tourvisor_image_proxy_base_url();
        $departure_city_images = is_file(__DIR__ . '/../backend/config/departure_city_images.php')
            ? require __DIR__ . '/../backend/config/departure_city_images.php'
            : [];
        ?>
        var TV_API_BASE = <?php echo json_encode($tv_proxy_base); ?>;
        var DEPARTURE_CITY_IMAGES = <?php echo json_encode($departure_city_images); ?>;
        if (typeof location !== 'undefined' && location.protocol === 'https:' && typeof TV_API_BASE === 'string' && TV_API_BASE.indexOf('http://') === 0) {
            TV_API_BASE = 'https:' + TV_API_BASE.substring(5);
        }
        var TV_IMAGE_PROXY = <?php echo json_encode($tv_image_proxy_base); ?>;
        if (typeof location !== 'undefined' && location.protocol === 'https:' && typeof TV_IMAGE_PROXY === 'string' && TV_IMAGE_PROXY.indexOf('http://') === 0) {
            TV_IMAGE_PROXY = 'https:' + TV_IMAGE_PROXY.substring(5);
        }
        const TOUR_DETAIL_BASE = '<?php $sn = $_SERVER["SCRIPT_NAME"] ?? ""; echo (strpos($sn, "frontend") !== false) ? "/frontend" : ""; ?>';
        var TV_IMG_FALLBACK = 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400&q=80';
        function getTourvisorImageUrl(src) {
            var s = (src == null || src === '') ? '' : String(src).trim();
            if (!s) return TV_IMG_FALLBACK;
            if (window.THTourCard && typeof window.THTourCard.mapTourvisorImageUrl === 'function') {
                var mapped = window.THTourCard.mapTourvisorImageUrl(s, TV_IMAGE_PROXY);
                return mapped || TV_IMG_FALLBACK;
            }
            if (/^\/\//.test(s)) {
                s = (typeof location !== 'undefined' && location.protocol === 'https:' ? 'https:' : 'http:') + s;
            }
            if (/^https?:\/\/static\.tourvisor\.ru\//i.test(s)) {
                return TV_IMAGE_PROXY + '?url=' + encodeURIComponent(s.replace(/^https:/i, 'http:'));
            }
            if (/^static\.tourvisor\.ru\//i.test(s)) {
                return TV_IMAGE_PROXY + '?url=' + encodeURIComponent('http://' + s);
            }
            if (/^https?:\/\//i.test(s)) return s;
            if (/^\/hotel_pics\//i.test(s) || /^hotel_pics\//i.test(s)) {
                return TV_IMAGE_PROXY + '?path=' + encodeURIComponent(s.replace(/^\/+/, ''));
            }
            return TV_IMG_FALLBACK;
        }

        // Справочники: прокси (Firestore → файл → API Tourvisor). Два источника — сначала dictionaries.php, при ошибке напрямую прокси.
        var DICTIONARIES_URL = <?php
            $sn = $_SERVER['SCRIPT_NAME'] ?? '';
            $proto = 'http';
            if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
                $proto = 'https';
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
                $proto = 'https';
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
                $proto = 'https';
            } elseif (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === '443') {
                $proto = 'https';
            }
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $path = (strpos($sn, '/frontend/') !== false) ? rtrim(dirname($sn), '/') . '/api/dictionaries.php' : '/backend/api/dictionaries.php';
            echo json_encode($proto . '://' . $host . $path);
        ?>;
        if (typeof location !== 'undefined' && location.protocol === 'https:' && typeof DICTIONARIES_URL === 'string' && DICTIONARIES_URL.indexOf('http://') === 0) {
            DICTIONARIES_URL = 'https:' + DICTIONARIES_URL.substring(5);
        }
        (function tvRefsEarlyFetch() {
            const base = TV_API_BASE;
            if (!base || typeof base !== 'string') return;
            const sep = base.indexOf('?') >= 0 ? '&' : '?';
            function safeFetchJson(url, fallback) {
                fallback = fallback || { success: false, data: null };
                return fetch(url, { method: 'GET', cache: 'no-store' })
                    .then(function(r) { return r.text().then(function(t) { return { ok: r.ok, text: t }; }); })
                    .then(function(o) {
                        var t = (o.text || '').trim();
                        if (!t) return fallback;
                        try { return JSON.parse(t); } catch (e) { return fallback; }
                    })
                    .catch(function() { return fallback; });
            }
            var dictPromise = safeFetchJson(DICTIONARIES_URL).then(function(j) {
                window.__tv_dictionaries_raw = j;
                if (j && j.success && Array.isArray(j.departures) && Array.isArray(j.countries)) {
                    return {
                        success: true,
                        departures: j.departures,
                        countries: j.countries,
                        meals: Array.isArray(j.meals) ? j.meals : []
                    };
                }
                return null;
            });
            var depPromise = dictPromise.then(function(o) {
                if (o && o.departures && o.departures.length) return { success: true, data: o.departures };
                return Promise.all([
                    safeFetchJson(base + sep + 'type=departures', { success: false, data: [] }),
                    safeFetchJson(base + sep + 'type=departures&departureCountryId=1', { success: false, data: [] })
                ]).then(function(res) {
                    var byId = {};
                    res.forEach(function(j) {
                        var list = (j && j.success && Array.isArray(j.data)) ? j.data : [];
                        list.forEach(function(d) { if (d && d.id != null) byId[d.id] = d; });
                    });
                    return { success: true, data: Object.values(byId) };
                });
            });
            var countriesPromise = dictPromise.then(function(o) {
                function mergeCountryLists(lists) {
                    var byId = {};
                    (lists || []).forEach(function (list) {
                        (list || []).forEach(function (c) {
                            if (c && c.id != null) byId[c.id] = c;
                        });
                    });
                    return Object.keys(byId).map(function (k) { return byId[k]; });
                }
                function fetchCountriesForDep(depId) {
                    var id = parseInt(String(depId || ''), 10) || 1;
                    var q = 'type=countries&departureId=' + encodeURIComponent(String(id));
                    return Promise.all([
                        safeFetchJson(base + sep + q, { success: false, data: [] }),
                        safeFetchJson(base + sep + q + '&onlyCharter=1', { success: false, data: [] })
                    ]).then(function (res) {
                        return mergeCountryLists(res.map(function (j) {
                            return (j && j.success && Array.isArray(j.data)) ? j.data : [];
                        }));
                    });
                }
                var dictCountries = (o && Array.isArray(o.countries)) ? o.countries : [];
                var depId = 1;
                try {
                    var stored = localStorage.getItem('th_departure_id');
                    if (stored) depId = parseInt(String(stored), 10) || 1;
                } catch (eDep) {}

                /* Сразу отдаём словарь (UI не висит на «Страна»), прокси дотягиваем фоном. */
                var proxyEnrich = fetchCountriesForDep(depId).then(function (proxyList) {
                    var merged = mergeCountryLists([dictCountries, proxyList]);
                    if (merged.length >= 15 || depId === 1) return merged;
                    return fetchCountriesForDep(1).then(function (msk) {
                        return mergeCountryLists([merged, msk]);
                    });
                }).catch(function () {
                    return dictCountries.slice();
                });

                window.__tv_countriesEnrichPromise = proxyEnrich;

                if (dictCountries.length >= 8) {
                    return { success: true, data: dictCountries.slice(), _enrichPending: true };
                }

                return proxyEnrich.then(function (merged) {
                    return { success: true, data: merged };
                });
            });
            var mealsPromise = dictPromise.then(function(o) {
                if (o && o.meals && o.meals.length) return { success: true, data: o.meals };
                return safeFetchJson(base + sep + 'type=meals', { success: false, data: [] });
            });
            window.__tv_refsPromises = {
                dep: depPromise,
                countries: countriesPromise,
                meals: mealsPromise
            };
        })();
        function tvFetchSummary(type, j) {
            if (!j) return '—';
            const d = j.data;
            if (Array.isArray(d)) {
                if (type === 'departures') return `Города вылета: ${d.length} шт.`;
                if (type === 'countries') return `Страны: ${d.length} шт.`;
                if (type === 'meals') return `Типы питания: ${d.length} шт.`;
                if (type === 'regions') return `Курорты: ${d.length} шт.`;
                if (type === 'dates') return `Доступные даты: ${d.length} шт.`;
                if (type === 'results' || type === 'search-cached') return `Туры/отели: ${d.length} шт.`;
                return `Массив: ${d.length} элементов`;
            }
            if (type === 'search' && j.searchId) return `Поиск запущен, searchId: ${j.searchId}`;
            const sd = j.data;
            if (type === 'status' && sd) return `Статус: ${sd.status || '—'}, прогресс: ${sd.progress ?? '—'}%, мин. цена: ${sd.minPrice ?? '—'}`;
            return j.success ? 'OK' : (j.error || 'Ошибка');
        }

        var tvFetchInflight = Object.create(null);
        var tvFetchMissMemo = Object.create(null);
        var TV_FETCH_MISS_TTL_MS = 45000;
        var tvFetchQueue = [];
        var tvFetchActive = 0;
        var TV_FETCH_MAX_CONCURRENT = 3;

        function tvFetchReleaseSlot() {
            tvFetchActive = Math.max(0, tvFetchActive - 1);
            while (tvFetchQueue.length && tvFetchActive < TV_FETCH_MAX_CONCURRENT) {
                var next = tvFetchQueue.shift();
                if (next) next();
            }
        }

        function tvFetchAcquireSlot() {
            return new Promise(function (resolve) {
                function tryAcquire() {
                    if (tvFetchActive < TV_FETCH_MAX_CONCURRENT) {
                        tvFetchActive++;
                        resolve(tvFetchReleaseSlot);
                        return;
                    }
                    tvFetchQueue.push(tryAcquire);
                }
                tryAcquire();
            });
        }

        function tvFetchBuildUrl(type, params, opts) {
            opts = opts || {};
            const base = TV_API_BASE;
            const u = new URL(base);
            u.searchParams.set('type', type);
            Object.entries(params || {}).forEach(([k, v]) => {
                if (k === 'childs') {
                    if (v === undefined || v === null) return;
                    const s = String(v).trim();
                    if (s !== '') u.searchParams.set(k, s);
                    return;
                }
                if (v != null && v !== '') u.searchParams.set(k, String(v));
            });
            if (type === 'search-cached') {
                if (opts.cacheScope) u.searchParams.set('cacheScope', String(opts.cacheScope));
                if (opts.cacheOnly) u.searchParams.set('cacheOnly', '1');
                if (opts.live || opts.forceLive || opts.backgroundRefresh) u.searchParams.set('live', '1');
                if (opts.slim !== false) u.searchParams.set('slim', '1');
                if (opts.full) u.searchParams.set('full', '1');
                if (!opts.cacheOnly) u.searchParams.set('_t', String(Date.now()));
            }
            if (type === 'results') {
                if (opts.slim !== false) u.searchParams.set('slim', '1');
                if (opts.persist === false || opts.persist === 0 || opts.persist === '0') {
                    u.searchParams.set('persist', '0');
                }
            }
            if (type === 'status' && params.operatorStatus == null) {
                u.searchParams.set('operatorStatus', '1');
            }
            return u.toString();
        }

        async function tvFetch(type, params = {}, opts) {
            opts = opts || {};
            const dedupKey = tvFetchBuildUrl(type, params, opts);
            if (opts.cacheOnly && type === 'search-cached') {
                var memo = tvFetchMissMemo[dedupKey];
                if (memo && (Date.now() - memo.ts) < TV_FETCH_MISS_TTL_MS) {
                    return memo.result;
                }
            }
            if (tvFetchInflight[dedupKey]) {
                return tvFetchInflight[dedupKey];
            }
            const url = dedupKey;
            const paramsStr = Object.keys(params).length ? JSON.stringify(params) : '{}';
            console.log('%c[Tourvisor] Запрос', 'color: #1A1A40; font-weight: bold', 'type:', type, 'params:', paramsStr);
            var timeoutMs = opts.timeoutMs;
            if (timeoutMs == null) {
                if (type === 'search') timeoutMs = 65000;
                else if (type === 'status') timeoutMs = 45000;
                else if (type === 'results') timeoutMs = 60000;
                else if (type === 'search-cached') timeoutMs = opts.cacheOnly ? 12000 : 65000;
                else if (type === 'tour-flights') timeoutMs = 45000;
                else timeoutMs = 25000;
            }
            var run = (async function () {
                var releaseSlot = await tvFetchAcquireSlot();
                var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
                var abortTimer = null;
                if (ctrl && timeoutMs > 0) {
                    abortTimer = setTimeout(function () {
                        try { ctrl.abort(); } catch (eAb) {}
                    }, timeoutMs);
                }
                try {
                    const r = await fetch(url, {
                        method: 'GET',
                        cache: 'no-store',
                        signal: ctrl ? ctrl.signal : undefined
                    });
                    const cacheHeader = r.headers.get('X-Tourvisor-Cache');
                    const cacheSaved = r.headers.get('X-Tourvisor-Cache-Saved');
                    const itemsCount = r.headers.get('X-Tourvisor-Items');
                    const text = await r.text();
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    let j;
                    try { j = text ? JSON.parse(text) : { success: false, error: 'Empty response', data: null }; } catch (e) { j = { success: false, error: 'Invalid JSON', data: null }; }
                    const summary = tvFetchSummary(type, j);
                    if (j.success) {
                        const cacheInfo = cacheHeader ? ` | кэш: ${cacheHeader}${cacheSaved ? ', сохранён в кэш: ' + cacheSaved : ''}${itemsCount ? ', записей: ' + itemsCount : ''}` : '';
                        console.log('%c[Tourvisor] Ответ ✓', 'color: #22c55e; font-weight: bold', 'type:', type, '|', summary + cacheInfo, j);
                    } else if (type === 'search-cached' && (j.error === 'Cache miss' || j.fromCache === false)) {
                        console.log('%c[Tourvisor] Кэш пуст', 'color: #94a3b8', 'type: search-cached | для этих параметров кэша нет, будет выполнен живой поиск');
                        if (opts.cacheOnly) {
                            tvFetchMissMemo[dedupKey] = { ts: Date.now(), result: j };
                        }
                    } else if (type === 'tour-flights' && (j.tourGone || (typeof j.error === 'string' && /not found|не найден/i.test(j.error)))) {
                        if (!opts.quiet) {
                            console.debug('%c[Tourvisor] tour-flights: тур устарел (кэш поиска)', 'color: #94a3b8', 'tourId:', (params && params.tourId) || '');
                        }
                    } else if (!opts.quiet) {
                        console.warn('%c[Tourvisor] Ответ с ошибкой', 'color: #ef4444', 'type:', type, '| error:', j.error || j, j);
                        if (typeof j.error === 'string' && (j.error.includes('SSL') || j.error.includes('timeout'))) {
                            console.info('%c[API → сайт] На localhost SSL/timeout бывает из-за сети или фаервола. На продакшене обычно стабильнее. Настройка верная.', 'color: #64748b; font-size: 10px;');
                        }
                    }
                    return j;
                } catch (e) {
                    var aborted = !!(e && (e.name === 'AbortError' || /abort/i.test(String(e.message || ''))));
                    console.error('%c[Tourvisor] Ошибка запроса ✗', 'color: #ef4444; font-weight: bold', 'type:', type, '|', aborted ? 'timeout' : e.message, '| URL:', url);
                    return { success: false, error: aborted ? 'timeout' : String(e.message) };
                } finally {
                    if (abortTimer) clearTimeout(abortTimer);
                    releaseSlot();
                }
            })();
            tvFetchInflight[dedupKey] = run;
            try {
                return await run;
            } finally {
                delete tvFetchInflight[dedupKey];
            }
        }

        /** Session SWR: мгновенный paint повторного поиска (PHP-only / SpaceWeb). */
        var TV_HOME_SWR_PREFIX = 'th_home_search_swr:';
        var TV_HOME_SWR_MAX_MS = 15 * 60 * 1000;
        var tvPrefetchTimer = null;
        var tvPrefetchInFlight = '';

        function tvHomeSwrKey(p) {
            p = p || {};
            return TV_HOME_SWR_PREFIX + [
                p.departureId || '',
                p.countryId || '',
                p.dateFrom || '',
                p.dateTo || '',
                p.nightsFrom || '',
                p.nightsTo || '',
                p.adults || '2',
                p.childs || '',
                p.meal || '',
                p.hotelCategory || '',
                p.regionIds || ''
            ].join(':');
        }

        function tvHomeSwrRead(params) {
            try {
                var raw = sessionStorage.getItem(tvHomeSwrKey(params));
                if (!raw) return null;
                var o = JSON.parse(raw);
                if (!o || !Array.isArray(o.data) || !o.data.length) return null;
                if ((Date.now() - (o.ts || 0)) > TV_HOME_SWR_MAX_MS) return null;
                return o;
            } catch (e) { return null; }
        }

        function tvHomeSwrWrite(params, hotels) {
            if (!params || !Array.isArray(hotels) || !hotels.length) return;
            try {
                var payload = JSON.stringify({ ts: Date.now(), data: hotels });
                if (payload.length > 4.5 * 1024 * 1024) return;
                sessionStorage.setItem(tvHomeSwrKey(params), payload);
            } catch (e) {
                try { sessionStorage.removeItem(tvHomeSwrKey(params)); } catch (e2) {}
            }
        }

        function tvCollectHomeCacheParams() {
            var dep = '7';
            try {
                if (typeof tvEffectiveSearchDeparture === 'function') {
                    var ed = tvEffectiveSearchDeparture();
                    if (ed && ed.id) dep = String(ed.id);
                } else if (typeof tvActiveDeparture === 'function') {
                    var da = tvActiveDeparture();
                    if (da && da.id) dep = String(da.id);
                } else if (document.getElementById('tv-departure') && document.getElementById('tv-departure').value) {
                    dep = String(document.getElementById('tv-departure').value);
                }
            } catch (eDep) {}
            var country = document.getElementById('tv-country') && document.getElementById('tv-country').value;
            if (!country) return null;
            var datesVal = (document.getElementById('tv-dates') && document.getElementById('tv-dates').value) || '';
            var dateFrom = '', dateTo = '';
            var parseD = function (s) {
                s = String(s || '').trim();
                var m = s.match(/^(\d{1,2})[.\-\/](\d{1,2})[.\-\/](\d{4})$/);
                if (m) return m[3] + '-' + String(m[2]).padStart(2, '0') + '-' + String(m[1]).padStart(2, '0');
                if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s;
                return '';
            };
            if (datesVal) {
                var parts = datesVal.split(/\s+(?:по|to)\s+|\s+[-–—]\s+/i);
                if (parts.length >= 2) {
                    dateFrom = parseD(parts[0]);
                    dateTo = parseD(parts[1]);
                }
            }
            if ((!dateFrom || !dateTo) && typeof flatpickr !== 'undefined' && window.tvDatePicker && window.tvDatePicker.selectedDates && window.tvDatePicker.selectedDates.length >= 1) {
                var sel = window.tvDatePicker.selectedDates;
                dateFrom = flatpickr.formatDate(sel[0], 'Y-m-d');
                dateTo = sel.length >= 2 ? flatpickr.formatDate(sel[1], 'Y-m-d') : flatpickr.formatDate(new Date(sel[0].getTime() + 30 * 864e5), 'Y-m-d');
            }
            if (!dateFrom || !dateTo) {
                var defWin = typeof getDefaultDateWindow === 'function' ? getDefaultDateWindow() : null;
                if (defWin && defWin[0] && defWin[1]) {
                    var padYmd = function (d) {
                        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                    };
                    dateFrom = (typeof flatpickr !== 'undefined' && flatpickr.formatDate) ? flatpickr.formatDate(defWin[0], 'Y-m-d') : padYmd(defWin[0]);
                    dateTo = (typeof flatpickr !== 'undefined' && flatpickr.formatDate) ? flatpickr.formatDate(defWin[1], 'Y-m-d') : padYmd(defWin[1]);
                }
            }
            var nFrom = (typeof tvNightsFrom !== 'undefined' ? parseInt(tvNightsFrom, 10) : 6) || 6;
            var nTo = (typeof tvNightsTo !== 'undefined' ? parseInt(tvNightsTo, 10) : 9) || 9;
            var adults = String(typeof tvAdultsCount !== 'undefined' ? tvAdultsCount : 2);
            var childs = typeof tvChildsParam === 'function' ? tvChildsParam() : '';
            var countryName = '';
            try {
                countryName = (document.getElementById('tv-country').selectedOptions[0].textContent || '').trim();
            } catch (eN) {}
            var p = {
                departureId: dep,
                countryId: country,
                countryName: countryName,
                dateFrom: dateFrom,
                dateTo: dateTo,
                nightsFrom: nFrom,
                nightsTo: nTo,
                adults: adults
            };
            if (childs) p.childs = childs;
            var meal = document.getElementById('tv-meal') && document.getElementById('tv-meal').value;
            if (meal) p.meal = meal;
            var category = document.getElementById('tv-category') && document.getElementById('tv-category').value;
            if (category) p.hotelCategory = category;
            var region = document.getElementById('tv-region') && document.getElementById('tv-region').value;
            if (region) p.regionIds = region;
            return p;
        }

        function tvPrefetchHomeSearchCache() {
            if (window.__tvSearchBusy || window.__thDeferHeavyCards) return;
            var params = tvCollectHomeCacheParams();
            if (!params || !params.countryId || !params.dateFrom || !params.dateTo) return;
            var key = tvHomeSwrKey(params);
            if (tvPrefetchInFlight === key) return;
            if (tvHomeSwrRead(params)) return;
            tvPrefetchInFlight = key;
            tvFetch('search-cached', params, { cacheOnly: true, cacheScope: 'country_page', slim: true }).then(function (r) {
                if (r && r.success && Array.isArray(r.data) && r.data.length) {
                    tvHomeSwrWrite(params, r.data);
                    console.log('%c[Главная · Prefetch] Кэш прогрет', 'color: #5DA9A4', params.countryName || params.countryId, r.data.length);
                }
            }).catch(function () {}).finally(function () {
                if (tvPrefetchInFlight === key) tvPrefetchInFlight = '';
            });
        }

        function tvSchedulePrefetchHomeSearch() {
            if (window.__tvSearchBusy || window.__thDeferHeavyCards) return;
            if (tvPrefetchTimer) clearTimeout(tvPrefetchTimer);
            tvPrefetchTimer = setTimeout(tvPrefetchHomeSearchCache, 900);
        }

        function tvResultsSkeletonHtml(n) {
            n = Math.max(2, Math.min(n || 4, 6));
            var cards = '';
            for (var i = 0; i < n; i++) {
                cards += '<article class="th-tour-card th-tour-card--skeleton" aria-hidden="true">' +
                    '<div class="th-skel th-skel--media"></div>' +
                    '<div class="th-tour-card__body">' +
                    '<div class="th-skel th-skel--line th-skel--w40"></div>' +
                    '<div class="th-skel th-skel--line th-skel--w80"></div>' +
                    '<div class="th-skel th-skel--line th-skel--w55"></div>' +
                    '<div class="th-skel th-skel--price"></div>' +
                    '<div class="th-skel th-skel--btn"></div>' +
                    '</div></article>';
            }
            return cards;
        }

        function tvShowResultsSkeleton() {
            var resultsDiv = document.getElementById('tv-search-results');
            if (resultsDiv) resultsDiv.innerHTML = tvResultsSkeletonHtml(4);
            showTvResultsChrome();
        }

        function formatPrice(price) {
            return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', minimumFractionDigits: 0 }).format(price || 0);
        }

        let tvDatePicker = null;
        var tvNightsFrom = 6;
        var tvNightsTo = 9;
        var TV_DEFAULT_NIGHTS_FROM = 6;
        var TV_DEFAULT_NIGHTS_TO = 9;

        function findDefaultCountry(countries) {
            if (!Array.isArray(countries) || !countries.length) return null;
            return countries.find(function (c) {
                return (c.name || '').toLowerCase().indexOf('египет') >= 0 || c.id == 1;
            }) || countries.find(function (c) {
                return (c.name || '').toLowerCase().indexOf('турци') >= 0 || c.id == 12 || c.id == 4;
            }) || countries[0];
        }

        function sortCountriesWithDefaultFirst(countries) {
            var list = (countries || []).slice();
            var def = findDefaultCountry(list);
            if (!def) return list;
            return [def].concat(list.filter(function (c) {
                return String(c.id) !== String(def.id);
            }));
        }

        function setDefaultNightsRange() {
            window.tvNightsFrom = TV_DEFAULT_NIGHTS_FROM;
            window.tvNightsTo = TV_DEFAULT_NIGHTS_TO;
            tvNightsFrom = TV_DEFAULT_NIGHTS_FROM;
            tvNightsTo = TV_DEFAULT_NIGHTS_TO;
            var summaryText = document.getElementById('tv-nights-summary-text');
            if (summaryText) summaryText.textContent = TV_DEFAULT_NIGHTS_FROM + '–' + TV_DEFAULT_NIGHTS_TO + ' ночей';
        }
        window.setDefaultNightsRange = setDefaultNightsRange;

        function updateDateDisplayOnly(d0, d1) {
            if (!d0 || !d1) return;
            var dispEl = document.getElementById('tv-sc-dates-display');
            if (dispEl) {
                var pad = function (n) { return String(n).padStart(2, '0'); };
                dispEl.textContent = pad(d0.getDate()) + '.' + pad(d0.getMonth() + 1) + '–' +
                    pad(d1.getDate()) + '.' + pad(d1.getMonth() + 1);
            }
            try {
                if (window.THTourSearchWizard && typeof window.THTourSearchWizard.refreshSummary === 'function') {
                    window.THTourSearchWizard.refreshSummary();
                }
            } catch (eDisp) {}
        }
        window.updateDateDisplayOnly = updateDateDisplayOnly;

        function getDefaultDateWindow() {
            if (window.THDatePresets && typeof window.THDatePresets.getDefaultRange === 'function') {
                return window.THDatePresets.getDefaultRange();
            }
            if (window.THDatePresets && typeof window.THDatePresets.getRange === 'function') {
                return window.THDatePresets.getRange('14d');
            }
            var d1 = new Date();
            d1.setHours(0, 0, 0, 0);
            var d2 = new Date(d1);
            d2.setDate(d2.getDate() + 14);
            return [d1, d2];
        }
        window.getDefaultDateWindow = getDefaultDateWindow;

        function applyDefaultDateWindow() {
            var range = getDefaultDateWindow();
            var d1 = range[0];
            var d2 = range[1];
            setDefaultNightsRange();
            if (window.tvDatePicker && typeof window.tvDatePicker.setDate === 'function') {
                window.tvDatePicker.setDate([d1, d2], true);
            } else {
                var datesInp = document.getElementById('tv-dates');
                if (datesInp) {
                    var pad = function (n) { return String(n).padStart(2, '0'); };
                    datesInp.value = pad(d1.getDate()) + '-' + pad(d1.getMonth() + 1) + '-' + d1.getFullYear() +
                        ' — ' + pad(d2.getDate()) + '-' + pad(d2.getMonth() + 1) + '-' + d2.getFullYear();
                }
            }
            updateDateDisplayOnly(d1, d2);
        }
        window.applyDefaultDateWindow = applyDefaultDateWindow;

        function tvNightsWord(n) {
            n = parseInt(n, 10) || 0;
            if (n === 1) return '1 ночь';
            if (n >= 2 && n <= 4) return n + ' ночи';
            return n + ' ночей';
        }
        window.tvNightsWord = tvNightsWord;

        function syncNightsFromDates(d0, d1) {
            if (!d0 || !d1) return;
            var nights = Math.max(1, Math.round((d1.getTime() - d0.getTime()) / 86400000));
            window.tvNightsFrom = nights;
            window.tvNightsTo = nights;
            tvNightsFrom = nights;
            tvNightsTo = nights;
            var summaryText = document.getElementById('tv-nights-summary-text');
            if (summaryText) summaryText.textContent = tvNightsWord(nights);
            var dispEl = document.getElementById('tv-sc-dates-display');
            if (dispEl) {
                var pad = function (n) { return String(n).padStart(2, '0'); };
                dispEl.textContent = pad(d0.getDate()) + '.' + pad(d0.getMonth() + 1) + '–' +
                    pad(d1.getDate()) + '.' + pad(d1.getMonth() + 1);
            }
            try {
                if (window.THTourSearchWizard && typeof window.THTourSearchWizard.refreshSummary === 'function') {
                    window.THTourSearchWizard.refreshSummary();
                }
            } catch (eSum2) {}
        }
        window.syncNightsFromDates = syncNightsFromDates;

        function advanceWizardAfterDates() {
            /* НЕ вызывать go(3): openStepModal снова открывает календарь и сбрасывает выбор.
               Переход на «Ночи» делает tour-search-wizard по клику «Применить». */
            try {
                if (window.THTourSearchWizard && typeof window.THTourSearchWizard.refreshSummary === 'function') {
                    window.THTourSearchWizard.refreshSummary();
                }
            } catch (eAdv) {}
        }
        /** Состав туристов для поиска и карточек: должен быть вне DOMContentLoaded — performTvSearch/renderTvResults вызываются снаружи */
        var tvAdultsCount = 2;
        var tvChildrenAges = [];
        function tvChildAgesForSearch() {
            if (!tvChildrenAges || !tvChildrenAges.length) return [];
            return tvChildrenAges.slice(0, 3).map(function (a) {
                var n = parseInt(a, 10);
                if (isNaN(n)) n = 0;
                return Math.max(0, Math.min(17, n));
            });
        }
        function tvChildsParam() {
            var ages = tvChildAgesForSearch();
            return ages.length ? ages.join(',') : '';
        }
        function tvPartyPriceLabel(adults) {
            var a = Math.max(1, parseInt(adults, 10) || 2);
            if (window.THTourCard && typeof window.THTourCard.partyPriceLabel === 'function') {
                return window.THTourCard.partyPriceLabel(a, tvChildAgesForSearch());
            }
            var parts = [(a === 1 ? '1 взрослого' : a + ' взрослых')];
            tvChildAgesForSearch().forEach(function (age) {
                var lbl = (window.THTourCard && window.THTourCard.childAgeLabel)
                    ? window.THTourCard.childAgeLabel(age)
                    : ('возраст ' + age);
                parts.push('1 реб. (' + lbl + ')');
            });
            return 'за ' + parts.join(' + ');
        }
        function tvPartySummaryLabel(adults) {
            var a = Math.max(1, parseInt(adults, 10) || 2);
            if (window.THTourCard && typeof window.THTourCard.partySummaryLabel === 'function') {
                return window.THTourCard.partySummaryLabel(a, tvChildAgesForSearch());
            }
            return tvPartyPriceLabel(a).replace(/^за /, '');
        }
        document.addEventListener('DOMContentLoaded', async function() {
            var depPopup = document.getElementById('tv-departure-popup');
            var countryPopup = document.getElementById('tv-country-popup');
            if (depPopup && depPopup.parentNode !== document.body) document.body.appendChild(depPopup);
            if (countryPopup && countryPopup.parentNode !== document.body) document.body.appendChild(countryPopup);

            const depSel = document.getElementById('tv-departure');
            const countrySel = document.getElementById('tv-country');
            const datesInp = document.getElementById('tv-dates');
            const mealSel = document.getElementById('tv-meal');
            const regionSel = document.getElementById('tv-region');

            var homeToursGrid = document.getElementById('home-hot-tours-grid');
            var homeToursEmpty = document.getElementById('home-hot-tours-empty');
            var moodChips = Array.prototype.slice.call(document.querySelectorAll('[data-home-mood]'));
            var departureButtons = Array.prototype.slice.call(document.querySelectorAll('[data-showcase-departure-city]'));
            var homeMoodActive = 'beach';
            var homeShowcaseData = <?php echo json_encode($homeShowcaseBoot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            var homeShowcaseSeq = 0;
            function escHomePopularAttr(s) {
                return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
            }
            function fmtPriceRu(v) {
                var n = parseInt(String(v || ''), 10);
                if (!n || n <= 0) return '';
                try {
                    return new Intl.NumberFormat('ru-RU').format(n) + ' ₽';
                } catch (e) {
                    return String(n) + ' ₽';
                }
            }
            function fmtDateShortHome(ymd) {
                var m = String(ymd || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
                if (!m) return '';
                return m[3] + '.' + m[2];
            }
            function nightsLabelHome(n) {
                var num = parseInt(String(n || ''), 10) || 0;
                if (!num) return '';
                if (num === 1) return '1 ночь';
                if (num < 5) return num + ' ночи';
                return num + ' ночей';
            }
            function depNameById(depId) {
                var id = parseInt(String(depId || ''), 10);
                if (id === 1) return 'Москва';
                if (id === 7) return 'Самара';
                if (!id) return '';
                if (depSel && depSel.options && depSel.options.length) {
                    for (var i = 0; i < depSel.options.length; i++) {
                        var opt = depSel.options[i];
                        if (parseInt(String(opt.value || ''), 10) === id) return String(opt.textContent || '').trim();
                    }
                }
                if (Array.isArray(departuresList)) {
                    var found = departuresList.find(function (d) { return parseInt(String(d.id), 10) === id; });
                    if (found) return String(found.name || '').trim();
                }
                return '';
            }
            function syncDepartureButtons(depId) {
                var id = parseInt(String(depId || ''), 10);
                departureButtons.forEach(function(btn) {
                    var btnId = parseInt(String(btn.getAttribute('data-showcase-departure-id') || ''), 10);
                    var city = String(btn.getAttribute('data-showcase-departure-city') || '').toLowerCase();
                    var shouldActive = false;
                    if (btnId) shouldActive = btnId === id;
                    else {
                        var name = depNameById(id).toLowerCase();
                        if (city === 'samara') shouldActive = /самар/.test(name) || id === 7;
                        if (city === 'moscow') shouldActive = /моск/.test(name) || id === 1;
                    }
                    btn.classList.toggle('is-active', shouldActive);
                });
            }
            function pickShowcaseTours() {
                if (!homeShowcaseData) return [];
                if (homeShowcaseData.moods && homeShowcaseData.moods[homeMoodActive]
                    && Array.isArray(homeShowcaseData.moods[homeMoodActive].items)
                    && homeShowcaseData.moods[homeMoodActive].items.length) {
                    return homeShowcaseData.moods[homeMoodActive].items.slice(0, 12);
                }
                if (Array.isArray(homeShowcaseData.hot) && homeShowcaseData.hot.length) {
                    return homeShowcaseData.hot.slice(0, 12);
                }
                if (Array.isArray(homeShowcaseData.tours) && homeShowcaseData.tours.length) {
                    return homeShowcaseData.tours.slice(0, 12);
                }
                return [];
            }
            function homeShowcaseExpandMeal(raw) {
                if (window.THTourCard && typeof window.THTourCard.expandMeal === 'function') {
                    return window.THTourCard.expandMeal(raw);
                }
                return String(raw || '');
            }
            function homeShowcaseItemToHotel(it) {
                if (it && it.hotel) {
                    var h = it.hotel;
                    if (!h.tours || !h.tours.length) {
                        h.tours = it.tour ? [it.tour] : [];
                    }
                    return h;
                }
                var tour = it.tour || {
                    id: it.tourId,
                    nights: it.nights,
                    meal: it.meal ? { name: it.meal, russianName: it.meal } : null,
                    date: it.dateFrom,
                    price: it.price,
                    totalPrice: it.price
                };
                return {
                    id: it.hotelId,
                    name: it.hotelName || '',
                    category: it.stars || 0,
                    rating: it.rating || 0,
                    region: { name: it.region || '' },
                    country: { id: it.countryId, name: it.countryName || '' },
                    picturelink: it.image || '',
                    tours: [tour]
                };
            }
            function homeApplyShowcaseFlights(data) {
                if (!data || !data.flightsByTourId || typeof data.flightsByTourId !== 'object') return;
                var depNm = data.departureName || depNameById(data.departureId) || 'Самара';
                var depIdFl = data.departureId || 7;
                Object.keys(data.flightsByTourId).forEach(function (tourId) {
                    var json = data.flightsByTourId[tourId];
                    if (!tourId || !json) return;
                    try {
                        if (typeof thFlightsCacheFromJson === 'function') {
                            thFlightsCacheFromJson(tourId, json, depNm, depIdFl);
                        }
                    } catch (eFl) {}
                });
            }
            function homeShowcaseFlightMeta(tourId, depCity) {
                if (!tourId || typeof thFlightsCacheGet !== 'function') return null;
                return thFlightsCacheGet(String(tourId), depCity);
            }
            function homeShowcaseLoadFlights(list, depCity, depId, gridEl) {
                if (!list.length || typeof thLoadTourFlightsForHotels !== 'function' || !TV_API_BASE) return;
                var hotels = list.map(homeShowcaseItemToHotel);
                thLoadTourFlightsForHotels(hotels, {
                    apiBase: TV_API_BASE,
                    departureCity: depCity,
                    departureId: depId,
                    maxTours: hotels.length,
                    maxConcurrent: 3,
                    patchEvery: 1,
                    patchContainer: gridEl,
                    getTourId: function (h) {
                        var t = (h && h.tours && h.tours[0]) ? h.tours[0] : {};
                        return String(t.id || t.tourId || '');
                    },
                    onDone: function () {
                        if (gridEl && window.THTourCard && typeof window.THTourCard.mountInContainer === 'function') {
                            window.THTourCard.mountInContainer(gridEl);
                        }
                    }
                });
            }
            function renderTourOffers(items) {
                if (!homeToursGrid) return;
                var list = Array.isArray(items) ? items : [];
                if (!list.length) {
                    homeToursGrid.innerHTML = '';
                    if (homeToursEmpty) {
                        homeToursEmpty.classList.remove('hidden');
                        homeToursEmpty.innerHTML = 'Пока нет готовых туров в кэше. Откройте <a href="/frontend/window/promotions.php">все акции</a> или подождите ночной прогрев.';
                    }
                    return;
                }
                if (homeToursEmpty) homeToursEmpty.classList.add('hidden');
                homeToursGrid.className = 'th-tour-grid';
                var depId = parseInt(String((homeShowcaseData && homeShowcaseData.departureId) || defaultDepartureIdStr() || '7'), 10) || 7;
                var depCity = (homeShowcaseData && homeShowcaseData.departureName) || depNameById(depId) || 'Самара';
                homeApplyShowcaseFlights(homeShowcaseData);
                if (window.THTourCard && typeof window.THTourCard.render === 'function') {
                    homeToursGrid.innerHTML = list.map(function (it) {
                        var h = homeShowcaseItemToHotel(it);
                        var tour = it.tour || ((h.tours && h.tours[0]) ? h.tours[0] : {});
                        var tourId = String(it.tourId || tour.id || tour.tourId || '');
                        var mealRaw = it.meal || (tour.meal && (tour.meal.russianName || tour.meal.name)) || '';
                        var meal = homeShowcaseExpandMeal(mealRaw);
                        var region = it.region || (h.region && h.region.name) || '';
                        var country = it.countryName || (h.country && h.country.name) || '';
                        var flightMeta = homeShowcaseFlightMeta(tourId, depCity);
                        var href = it.href || it.promoHref || '/frontend/window/promotions.php';
                        return window.THTourCard.render(h, {
                            tour: tour,
                            promo: true,
                            promoLead: false,
                            getImageUrl: (typeof getTourvisorImageUrl === 'function') ? getTourvisorImageUrl : undefined,
                            imageProxy: (typeof TV_IMAGE_PROXY !== 'undefined') ? TV_IMAGE_PROXY : '',
                            image: it.image || '',
                            detailUrl: href,
                            adults: 2,
                            dateFrom: it.dateFrom || '',
                            dateTo: it.dateTo || '',
                            price: it.price || 0,
                            meal: meal,
                            departureCity: depCity,
                            departureId: depId,
                            carousel: true,
                            country: country,
                            countryId: it.countryId,
                            flightMeta: flightMeta,
                            directBadge: !!(flightMeta && flightMeta.direct === true),
                            transferBadge: !!(flightMeta && flightMeta.direct === false)
                        });
                    }).join('');
                    if (window.THTourCard && typeof window.THTourCard.mountInContainer === 'function') {
                        window.THTourCard.mountInContainer(homeToursGrid);
                    } else if (window.THTourCard && typeof window.THTourCard.ensureCarouselsInContainer === 'function') {
                        window.THTourCard.ensureCarouselsInContainer(homeToursGrid);
                    }
                    homeShowcaseLoadFlights(list, depCity, depId, homeToursGrid);
                    return;
                }
                homeToursGrid.innerHTML = list.map(function(it) {
                    var href = escHomePopularAttr(it.href || it.promoHref || '/frontend/window/promotions.php');
                    var img = escHomePopularAttr(it.image || '');
                    var hotel = escHomePopularAttr(it.hotelName || it.name || '');
                    var country = escHomePopularAttr(it.countryName || '');
                    var price = fmtPriceRu(it.price || it.minPrice || 0);
                    var nights = nightsLabelHome(it.nights || 0);
                    var dates = '';
                    if (it.dateFrom) {
                        dates = fmtDateShortHome(it.dateFrom);
                        if (it.dateTo) dates += '–' + fmtDateShortHome(it.dateTo);
                    }
                    var metaParts = [];
                    if (country) metaParts.push(country);
                    if (dates) metaParts.push(dates);
                    if (nights) metaParts.push(nights);
                    var meta = metaParts.join(' · ') || 'Горящий тур';
                    var badge = price ? ('от ' + price) : 'Горящее';
                    return '<a href="' + href + '" class="th-tour-offer">' +
                        '<div class="th-tour-offer__image" style="background-image:url(\'' + img + '\');"></div>' +
                        '<div class="th-tour-offer__body">' +
                        '<span class="th-tour-offer__badge">' + escHomePopularAttr(badge) + '</span>' +
                        '<strong class="th-tour-offer__title">' + hotel + '</strong>' +
                        '<span class="th-tour-offer__meta">' + escHomePopularAttr(meta) + '</span>' +
                        '</div></a>';
                }).join('');
            }
            function renderShowcaseActive() {
                renderTourOffers(pickShowcaseTours());
            }
            async function loadHomeShowcaseShelves(depId) {
                var id = parseInt(String(depId || ''), 10);
                if (!id) return;
                var my = ++homeShowcaseSeq;
                syncDepartureButtons(id);
                if (homeToursEmpty) {
                    homeToursEmpty.classList.remove('hidden');
                    homeToursEmpty.textContent = 'Подгружаем актуальные туры…';
                }
                try {
                    var u = '/backend/api/home_showcase_shelves.php?cacheOnly=1&departureId=' + encodeURIComponent(id);
                    var r = await fetch(u, { method: 'GET', cache: 'no-store' });
                    var txt = await r.text();
                    var j = {};
                    if ((txt || '').trim()) { try { j = JSON.parse(txt); } catch (e) {} }
                    if (my !== homeShowcaseSeq) return;
                    if (!j || !j.success) {
                        renderTourOffers([]);
                        return;
                    }
                    homeShowcaseData = j;
                    renderShowcaseActive();
                } catch (e) {
                    if (my === homeShowcaseSeq) renderTourOffers([]);
                }
            }
            function setShowcaseDeparture(depId) {
                var id = parseInt(String(depId || ''), 10);
                if (!id) return;
                if (depSel) {
                    depSel.value = String(id);
                    try { depSel.dispatchEvent(new Event('change')); } catch (e) {}
                }
                syncDepartureButtons(id);
                loadHomeShowcaseShelves(id);
            }

            function defaultDepartureIdStr() {
                return String((window.TH_DEPARTURE && window.TH_DEPARTURE.id) || 7);
            }
            function ensureDepartureSelected() {
                if (!depSel || depSel.tagName !== 'SELECT') return;
                var cur = String(depSel.value || '').trim();
                if (cur && depSel.options[depSel.selectedIndex]) return;
                if (window.THDeparturePreference && typeof window.THDeparturePreference.ensureSelectValue === 'function') {
                    window.THDeparturePreference.ensureSelectValue(depSel, departuresList);
                    return;
                }
                var defId = defaultDepartureIdStr();
                var hit = departuresList.find(function (d) { return String(d.id) === defId; })
                    || departuresList.find(function (d) { return /самара/i.test(String(d.name || '')); })
                    || departuresList.find(function (d) { return d.id && !isBlockedDepartureName(d.name); });
                if (hit) depSel.value = String(hit.id);
                else if (depSel.options.length > 1) depSel.selectedIndex = 1;
            }

            // Keep SSR defaults in selects until dictionaries arrive (no «Загрузка...» flash)
            moodChips.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var mood = String(btn.getAttribute('data-home-mood') || 'beach');
                    homeMoodActive = mood;
                    thTrackGoal('mood_click');
                    moodChips.forEach(function(it) {
                        var active = it === btn;
                        it.classList.toggle('is-active', active);
                        it.setAttribute('aria-selected', active ? 'true' : 'false');
                    });
                    renderShowcaseActive();
                });
            });
            departureButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var id = parseInt(String(btn.getAttribute('data-showcase-departure-id') || ''), 10);
                    if (!id) {
                        var city = String(btn.getAttribute('data-showcase-departure-city') || '').toLowerCase();
                        id = city === 'moscow' ? 1 : 7;
                    }
                    thTrackGoal('showcase_departure_switch');
                    setShowcaseDeparture(id);
                });
            });
            renderShowcaseActive();
            loadHomeShowcaseShelves(parseInt(defaultDepartureIdStr(), 10) || 7);

            // Приоритет: города вылета, страны и питание — из кэша (__tv_refsPromises). Полный список стран: объединение onlyCharter=0 и onlyCharter=1.
            const refsPromises = window.__tv_refsPromises;
            const pDep = refsPromises ? refsPromises.dep : Promise.all([tvFetch('departures'), tvFetch('departures', { departureCountryId: 1 })]).then(function(res) {
                const byId = {};
                res.forEach(function(j) {
                    const list = (j && j.success && Array.isArray(j.data)) ? j.data : [];
                    list.forEach(function(d) { if (d && d.id != null) byId[d.id] = d; });
                });
                return { success: true, data: Object.values(byId) };
            });
            const pCountries = refsPromises ? refsPromises.countries : Promise.all([
                tvFetch('countries', { departureId: '1' }),
                tvFetch('countries', { departureId: '1', onlyCharter: '1' })
            ]).then(function(res) {
                const byId = {};
                res.forEach(function(j) {
                    const list = (j && j.success && Array.isArray(j.data)) ? j.data : [];
                    list.forEach(function(c) { if (c && c.id != null) byId[c.id] = c; });
                });
                return { success: true, data: Object.values(byId) };
            });
            const pMeal = refsPromises ? refsPromises.meals : tvFetch('meals');

            function initTvDatePicker() {
                var defRange = getDefaultDateWindow();
                var defaultFrom = defRange[0];
                var defaultTo = defRange[1];
                if (!datesInp) return;
                if (typeof flatpickr !== 'function') return;
                try {
                    if (tvDatePicker) return;
                    var minPick = new Date();
                    minPick.setHours(0, 0, 0, 0);
                    var maxPick = new Date(minPick);
                    maxPick.setFullYear(maxPick.getFullYear() + 1);
                    window.__tvCalPriceMap = window.__tvCalPriceMap || {};
                    window.__tvCalPriceMapKey = '';
                    window.__tvFlyDates = window.__tvFlyDates || { key: '', all: {}, charter: {}, direct: {} };
                    window.__tvFlyNights = window.__tvFlyNights || { key: '', all: {}, charter: {}, direct: {} };
                    function tvFmtCalPrice(n) {
                        var v = parseInt(String(n || ''), 10) || 0;
                        if (!v) return '';
                        if (v >= 1000) return Math.round(v / 1000) + 'к';
                        return String(v);
                    }
                    function tvFlightFlags() {
                        return {
                            charter: !!(document.getElementById('tv-only-charter') && document.getElementById('tv-only-charter').checked),
                            direct: !!(document.getElementById('tv-only-direct') && document.getElementById('tv-only-direct').checked)
                        };
                    }
                    function tvDatesToMap(list) {
                        var map = {};
                        (list || []).forEach(function (d) {
                            var s = String(d || '').trim();
                            if (/^\d{4}-\d{2}-\d{2}/.test(s)) map[s.slice(0, 10)] = true;
                            else if (/^\d{2}\.\d{2}\.\d{4}/.test(s)) {
                                var p = s.split('.');
                                map[p[2] + '-' + p[1] + '-' + p[0]] = true;
                            }
                        });
                        return map;
                    }
                    /** flatpickr 4.6: onDayCreate(selectedDates, inputValue, fp, dayEl) — дата в dayEl.dateObj */
                    function tvCalDayCreate(_selectedDates, _dStr, fp, dayEl) {
                        if (!dayEl || !dayEl.dateObj) return;
                        try {
                            var dObj = dayEl.dateObj;
                            var y = dObj.getFullYear();
                            var m = String(dObj.getMonth() + 1).padStart(2, '0');
                            var d = String(dObj.getDate()).padStart(2, '0');
                            var key = y + '-' + m + '-' + d;
                            dayEl.classList.remove('tv-cal-fly', 'tv-cal-fly-direct', 'tv-cal-fly-off', 'tv-cal-deal', 'tv-cal-reduced', 'tv-cal-has-price');
                            var fly = window.__tvFlyDates || {};
                            var flags = tvFlightFlags();
                            var inAll = !!(fly.all && fly.all[key]);
                            var inCharter = !!(fly.charter && fly.charter[key]);
                            var inDirect = !!(fly.direct && fly.direct[key]);
                            if (inDirect) {
                                dayEl.classList.add('tv-cal-fly-direct');
                            } else if (inAll || inCharter) {
                                dayEl.classList.add('tv-cal-fly');
                            }
                            /* «Только чартер» = только прямые рейсы */
                            if ((flags.direct || flags.charter) && !inDirect && (inAll || inCharter || Object.keys(fly.direct || {}).length > 0)) {
                                dayEl.classList.add('tv-cal-fly-off');
                            }
                            var info = window.__tvCalPriceMap && window.__tvCalPriceMap[key];
                            if (!info || !info.minPrice) return;
                            dayEl.classList.add('tv-cal-has-price');
                            if (info.deal) dayEl.classList.add('tv-cal-deal');
                            else if (info.reduced) dayEl.classList.add('tv-cal-reduced');
                            if (!dayEl.querySelector('.tv-cal-day-price')) {
                                var badge = document.createElement('span');
                                var badgeKind = info.deal ? 'deal' : (info.reduced ? 'reduced' : 'price');
                                badge.className = 'tv-cal-day-price is-' + badgeKind;
                                badge.textContent = info.deal ? 'выгодно' : (info.reduced ? 'ниже' : ('от ' + tvFmtCalPrice(info.minPrice)));
                                dayEl.appendChild(badge);
                            }
                        } catch (eDay) {}
                    }
                    window.__tvCalDayCreate = tvCalDayCreate;
                    window.tvFlightFlags = tvFlightFlags;
                    function tvMapsEqual(a, b) {
                        var ka = Object.keys(a || {});
                        var kb = Object.keys(b || {});
                        if (ka.length !== kb.length) return false;
                        for (var i = 0; i < ka.length; i++) {
                            if (!b[ka[i]]) return false;
                        }
                        return true;
                    }
                    function tvYmdFromTs(ts) {
                        var d = new Date(ts);
                        var p = function (n) { return String(n).padStart(2, '0'); };
                        return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
                    }
                    /** Прямые даты: /tours/dates&onlyDirect сломан — окна search-cached&onlyDirect (только кэш, без live). */
                    async function tvLoadDirectDatesViaSearch(depId, cid, allMap) {
                        if (window.__tvSearchBusy || window.__thDeferHeavyCards) return {};
                        var keys = Object.keys(allMap || {}).sort();
                        var directMap = {};
                        if (!keys.length || typeof tvFetch !== 'function') return directMap;
                        var i = 0;
                        var windows = 0;
                        var maxWindows = 2;
                        while (i < keys.length && windows < maxWindows) {
                            var from = keys[i];
                            var fromTs = Date.parse(from + 'T12:00:00');
                            if (!isFinite(fromTs)) { i++; continue; }
                            var toTs = fromTs + 6 * 86400 * 1000;
                            var toStr = tvYmdFromTs(toTs);
                            var inWin = [];
                            while (i < keys.length) {
                                var kt = Date.parse(keys[i] + 'T12:00:00');
                                if (!isFinite(kt) || kt > toTs) break;
                                inWin.push(keys[i]);
                                i++;
                            }
                            windows++;
                            if (!inWin.length) continue;
                            var baseParams = {
                                departureId: String(depId),
                                countryId: String(cid),
                                dateFrom: from,
                                dateTo: toStr,
                                nightsFrom: '7',
                                nightsTo: '14',
                                adults: '2',
                                currency: 'RUB',
                                onlyDirect: '1'
                            };
                            /* Без live-fallback: на холодных направлениях (Шри-Ланка) live onlyDirect вешает FPM → 503. */
                            var r = await tvFetch('search-cached', baseParams, { cacheOnly: true, slim: false });
                            if (r && r.success && Array.isArray(r.data) && r.data.length) {
                                inWin.forEach(function (k) { directMap[k] = true; });
                            }
                        }
                        return directMap;
                    }
                    async function tvTourIdIsDirectFlight(tourId, depName, depId) {
                        if (!tourId) return null;
                        if (typeof thFlightsCacheGet === 'function') {
                            var cachedMeta = thFlightsCacheGet(String(tourId), depName);
                            if (cachedMeta && typeof cachedMeta.direct === 'boolean') return cachedMeta.direct;
                        }
                        try {
                            var j;
                            if (typeof thFetchTourFlightsJson === 'function') {
                                j = await thFetchTourFlightsJson(tourId, { departureCity: depName });
                            } else if (typeof tvFetch === 'function') {
                                j = await tvFetch('tour-flights', { tourId: String(tourId), currency: 'RUB' }, {});
                            } else {
                                return null;
                            }
                            if (j && (j._fromPackagesCache || j._fromCache)) {
                                var cm = typeof thFlightsCacheGet === 'function' ? thFlightsCacheGet(String(tourId), depName) : null;
                                if (cm && typeof cm.direct === 'boolean') return cm.direct;
                                if (j._fromPackagesCache && j.flights && j.flights.length && typeof window.thPickTourvisorFlightPackage === 'function') {
                                    var pickCached = window.thPickTourvisorFlightPackage(j.flights, depName, depId);
                                    var metaCached = (typeof window.thFlightMetaFromPackage === 'function')
                                        ? window.thFlightMetaFromPackage(pickCached, depName)
                                        : null;
                                    if (metaCached && typeof metaCached.direct === 'boolean') return metaCached.direct;
                                }
                                return null;
                            }
                            if (j && j._memoFail) return null;
                            var flights = (j && Array.isArray(j.flights))
                                ? j.flights
                                : (j && j.data && Array.isArray(j.data.flights) ? j.data.flights : []);
                            if (!flights.length) return null;
                            var pick = (typeof window.thPickTourvisorFlightPackage === 'function')
                                ? window.thPickTourvisorFlightPackage(flights, depName, depId)
                                : flights[0];
                            var meta = (typeof window.thFlightMetaFromPackage === 'function')
                                ? window.thFlightMetaFromPackage(pick, depName)
                                : null;
                            if (meta && typeof meta.direct === 'boolean') return meta.direct;
                            if (!pick) return null;
                            var fw = pick.forward;
                            var bw = pick.backward || pick.back;
                            if (!Array.isArray(fw) || fw.length !== 1) return false;
                            if (Array.isArray(bw) && bw.length > 1) return false;
                            return true;
                        } catch (eTf) {
                            return null;
                        }
                    }
                    /** Уточнение прямых ночей через tour-flights (когда onlyDirect в search бесполезен). */
                    async function tvEnrichDirectNightsFromFlights(depId, depName, tourIdsByNight, seedDirect) {
                        if (window.__tvSearchBusy || window.__thDeferHeavyCards || window.__tvSearchResultsFromCache === true) {
                            return Object.assign({}, seedDirect || {});
                        }
                        var directMap = Object.assign({}, seedDirect || {});
                        var nights = Object.keys(tourIdsByNight || {});
                        if (!nights.length) return directMap;
                        var queue = [];
                        nights.forEach(function (n) {
                            var ids = tourIdsByNight[n] || [];
                            for (var t = 0; t < Math.min(1, ids.length); t++) {
                                queue.push({ n: n, id: ids[t] });
                            }
                        });
                        queue = queue.slice(0, 8);
                        var known = {};
                        for (var qi = 0; qi < queue.length; qi++) {
                            var item = queue[qi];
                            if (directMap[item.n]) continue;
                            var key = String(item.id);
                            var isD;
                            if (Object.prototype.hasOwnProperty.call(known, key)) {
                                isD = known[key];
                            } else {
                                isD = await tvTourIdIsDirectFlight(item.id, depName, depId);
                                known[key] = isD;
                                if (qi < queue.length - 1) {
                                    await new Promise(function (r) { setTimeout(r, 450); });
                                }
                            }
                            if (isD === true) directMap[item.n] = true;
                        }
                        return directMap;
                    }
                    function tvCollectTourIdsByNight(list, target) {
                        target = target || {};
                        (list || []).forEach(function (h) {
                            if (!h) return;
                            var tours = h.tours ? h.tours : [];
                            if (!tours.length && (h.nights || h.minNights)) {
                                var hn = parseInt(String(h.nights || h.minNights || ''), 10);
                                var hid = h.id || h.hotelId;
                                if (hn >= 1 && hn <= 28 && hid) {
                                    if (!target[hn]) target[hn] = [];
                                    // без tourId не проверяем flights
                                }
                                return;
                            }
                            tours.forEach(function (t) {
                                if (!t) return;
                                var n = parseInt(String(t.nights || t.nightsCount || ''), 10);
                                var tid = t.id || t.tourId || t.tourid;
                                if (!(n >= 1 && n <= 28) || tid == null || tid === '') return;
                                if (!target[n]) target[n] = [];
                                var s = String(tid);
                                if (target[n].indexOf(s) < 0) target[n].push(s);
                            });
                        });
                        return target;
                    }
                    window.tvLoadFlyAvailability = async function () {
                        /* dates — лёгкий запрос; не блокируем календарь на время search-cached */
                        if (window.__thDeferHeavyCards) return;
                        var depId = parseInt(String((depSel && depSel.value) || '7'), 10) || 7;
                        var cid = parseInt(String((countrySel && countrySel.value) || ''), 10) || 0;
                        if (!cid || typeof tvFetch !== 'function') {
                            window.__tvFlyDates = { key: '', all: {}, charter: {}, direct: {} };
                            return;
                        }
                        var flyKey = depId + ':' + cid;
                        if (window.__tvFlyDates && window.__tvFlyDates.key === flyKey && Object.keys(window.__tvFlyDates.all || {}).length) {
                            if (tvDatePicker && typeof tvDatePicker.redraw === 'function') tvDatePicker.redraw();
                            if (window.tvDatePickerInline && typeof window.tvDatePickerInline.redraw === 'function') {
                                window.tvDatePickerInline.redraw();
                            }
                            return;
                        }
                        try {
                            var res = await Promise.all([
                                tvFetch('dates', { departureId: String(depId), countryId: String(cid) }),
                                tvFetch('dates', { departureId: String(depId), countryId: String(cid), onlyCharter: '1' })
                            ]);
                            var allMap = tvDatesToMap(res[0] && res[0].success ? res[0].data : []);
                            var charterMap = tvDatesToMap(res[1] && res[1].success ? res[1].data : []);
                            var directMap = await tvLoadDirectDatesViaSearch(depId, cid, allMap);
                            // Если «прямой» совпал со всеми датами — API не дал различия; оставляем метки (всё прямое).
                            var directUseful = Object.keys(directMap).length > 0;
                            window.__tvFlyDates = {
                                key: flyKey,
                                all: allMap,
                                charter: charterMap,
                                direct: directUseful ? directMap : {}
                            };
                            if (tvDatePicker && typeof tvDatePicker.redraw === 'function') tvDatePicker.redraw();
                            if (window.tvDatePickerInline && typeof window.tvDatePickerInline.redraw === 'function') {
                                window.tvDatePickerInline.redraw();
                            }
                        } catch (eFly) {}
                    };
                    window.tvLoadFlyNights = async function () {
                        if (window.__tvSearchBusy || window.__thDeferHeavyCards) return;
                        var depId = parseInt(String((depSel && depSel.value) || '7'), 10) || 7;
                        var depName = (depSel && depSel.options && depSel.selectedIndex >= 0)
                            ? String(depSel.options[depSel.selectedIndex].textContent || '').trim()
                            : ((window.TH_DEPARTURE && window.TH_DEPARTURE.name) || 'Самара');
                        var cid = parseInt(String((countrySel && countrySel.value) || ''), 10) || 0;
                        var datesVal = (document.getElementById('tv-dates') && document.getElementById('tv-dates').value) || '';
                        var dateFrom = '';
                        var dateTo = '';
                        if (typeof tvResolveSearchDatesYmd === 'function') {
                            var rd = tvResolveSearchDatesYmd();
                            dateFrom = rd.dateFrom || '';
                            dateTo = rd.dateTo || '';
                        }
                        if (!dateFrom || !dateTo) {
                            var parseD = function (s) {
                                var t = (s || '').trim();
                                if (/^\d{4}-\d{2}-\d{2}$/.test(t)) return t;
                                var m = t.replace(/\./g, '-').match(/^(\d{1,2})-(\d{1,2})-(\d{4})$/);
                                return m ? (m[3] + '-' + m[2].padStart(2, '0') + '-' + m[1].padStart(2, '0')) : '';
                            };
                            if (datesVal) {
                                var parts = datesVal.split(/\s*(?:—|–|-|по|to)\s*/i);
                                if (parts.length >= 2) {
                                    dateFrom = parseD(parts[0]);
                                    dateTo = parseD(parts[1]);
                                }
                            }
                            if ((!dateFrom || !dateTo) && tvDatePicker && tvDatePicker.selectedDates && tvDatePicker.selectedDates.length >= 1) {
                                dateFrom = flatpickr.formatDate(tvDatePicker.selectedDates[0], 'Y-m-d');
                                dateTo = tvDatePicker.selectedDates.length >= 2
                                    ? flatpickr.formatDate(tvDatePicker.selectedDates[1], 'Y-m-d')
                                    : dateFrom;
                            }
                        }
                        if (!cid || !dateFrom || !dateTo || typeof tvFetch !== 'function') return;
                        var flags = tvFlightFlags();
                        var nKey = [depId, cid, dateFrom, dateTo, flags.charter ? 1 : 0, flags.direct ? 1 : 0].join(':');
                        if (window.__tvFlyNights && window.__tvFlyNights.key === nKey && Object.keys(window.__tvFlyNights.all || {}).length
                            && window.__tvFlyNights.enriched) {
                            if (typeof window.renderTvNightsGrid === 'function') window.renderTvNightsGrid(true);
                            return;
                        }
                        function nightsFromHotels(list) {
                            var map = {};
                            (list || []).forEach(function (h) {
                                if (!h) return;
                                var hn = parseInt(String(h.nights || h.minNights || ''), 10);
                                if (hn >= 1 && hn <= 28) map[hn] = true;
                                var tours = h.tours ? h.tours : [];
                                tours.forEach(function (t) {
                                    var n = parseInt(String(t && (t.nights || t.nightsCount) || ''), 10);
                                    if (n >= 1 && n <= 28) map[n] = true;
                                });
                            });
                            return map;
                        }
                        function mergeNightMaps(target, src) {
                            Object.keys(src || {}).forEach(function (k) { target[k] = true; });
                            return target;
                        }
                        function mapSize(m) { return Object.keys(m || {}).length; }
                        var nFromSel = (typeof tvNightsFrom !== 'undefined' ? parseInt(tvNightsFrom, 10) : 6) || 6;
                        var nToSel = (typeof tvNightsTo !== 'undefined' ? parseInt(tvNightsTo, 10) : 9) || 9;
                        var bandLo = Math.max(1, Math.min(nFromSel, nToSel) - 2);
                        var bandHi = Math.min(28, Math.max(nFromSel, nToSel) + 2);
                        var nightBands = [[bandLo, bandHi]];
                        var reqId = (window.__tvFlyNightsReqId = (window.__tvFlyNightsReqId || 0) + 1);
                        try {
                            var allMap = {};
                            var charterMap = {};
                            var directMap = {};
                            var tourIdsByNight = {};
                            var wantCharterDirect = flags.charter || flags.direct;
                            async function fetchBand(band, cacheOnly, withDirect) {
                                var baseParams = {
                                    departureId: String(depId),
                                    countryId: String(cid),
                                    dateFrom: dateFrom,
                                    dateTo: dateTo,
                                    nightsFrom: String(band[0]),
                                    nightsTo: String(band[1]),
                                    adults: '2',
                                    currency: 'RUB'
                                };
                                var opts = { cacheOnly: !!cacheOnly, slim: false };
                                var nr = await tvFetch('search-cached', baseParams, opts);
                                var allList = nr && nr.success ? nr.data : [];
                                mergeNightMaps(allMap, nightsFromHotels(allList));
                                tvCollectTourIdsByNight(allList, tourIdsByNight);
                                if (cacheOnly && wantCharterDirect && withDirect && (flags.charter || flags.direct)) {
                                    var nd = await tvFetch('search-cached', Object.assign({}, baseParams, { onlyDirect: '1' }), opts);
                                    mergeNightMaps(directMap, nightsFromHotels(nd && nd.success ? nd.data : []));
                                }
                            }
                            for (var bi = 0; bi < nightBands.length; bi++) {
                                await fetchBand(nightBands[bi], true, true);
                                if (bi < nightBands.length - 1) await new Promise(function (r) { setTimeout(r, 250); });
                            }
                            /* Без live-fallback: search-cached без cacheOnly вешает FPM и рвёт основной поиск. */
                            if (mapSize(allMap) === 0) {
                                window.__tvFlyNights = {
                                    key: nKey,
                                    all: {},
                                    charter: {},
                                    direct: {},
                                    enriched: true
                                };
                                if (typeof window.renderTvNightsGrid === 'function') window.renderTvNightsGrid(true);
                                return;
                            } else if (mapSize(directMap) === 0 || tvMapsEqual(directMap, allMap)) {
                                /* Direct-метки без live fan-out: только tour-flights по уже известным tourId. */
                            }
                            if (reqId !== window.__tvFlyNightsReqId) return;
                            var onlyDirectUseful = mapSize(directMap) > 0 && !tvMapsEqual(directMap, allMap);
                            if (!onlyDirectUseful) {
                                // onlyDirect в search часто бесполезен (совпадает с all / пуст) — подтверждаем через tour-flights
                                window.__tvFlyNights = {
                                    key: nKey,
                                    all: allMap,
                                    charter: charterMap,
                                    direct: {},
                                    enriched: false
                                };
                                if (typeof window.renderTvNightsGrid === 'function') window.renderTvNightsGrid(true);
                                directMap = await tvEnrichDirectNightsFromFlights(depId, depName, tourIdsByNight, {});
                                if (reqId !== window.__tvFlyNightsReqId) return;
                            }
                            window.__tvFlyNights = {
                                key: nKey,
                                all: allMap,
                                charter: charterMap,
                                direct: mapSize(directMap) > 0 ? directMap : {},
                                enriched: true
                            };
                            if (typeof window.renderTvNightsGrid === 'function') window.renderTvNightsGrid(true);
                        } catch (eNights) {}
                    };
                    window.tvLoadCalendarPriceMap = async function () {
                        var depId = parseInt(String((depSel && depSel.value) || '7'), 10) || 7;
                        var cid = parseInt(String((countrySel && countrySel.value) || ''), 10) || 0;
                        if (!cid) {
                            window.__tvCalPriceMap = {};
                            window.__tvCalPriceMapKey = '';
                            return;
                        }
                        var mapKey = depId + ':' + cid;
                        if (window.__tvCalPriceMapKey === mapKey && window.__tvCalPriceMap && Object.keys(window.__tvCalPriceMap).length) {
                            if (typeof window.tvLoadFlyAvailability === 'function') window.tvLoadFlyAvailability();
                            return;
                        }
                        try {
                            var url = '/backend/api/calendar_price_map.php?departureId=' + encodeURIComponent(depId)
                                + '&countryId=' + encodeURIComponent(cid);
                            var r = await fetch(url, { cache: 'default' });
                            var j = await r.json();
                            if (j && j.success && j.dates && typeof j.dates === 'object') {
                                window.__tvCalPriceMap = j.dates;
                                window.__tvCalPriceMapKey = mapKey;
                                if (tvDatePicker && typeof tvDatePicker.redraw === 'function') tvDatePicker.redraw();
                                if (window.tvDatePickerInline && typeof window.tvDatePickerInline.redraw === 'function') {
                                    window.tvDatePickerInline.redraw();
                                }
                            }
                        } catch (eMap) {}
                        if (typeof window.tvLoadFlyAvailability === 'function') window.tvLoadFlyAvailability();
                    };
                    tvDatePicker = flatpickr(datesInp, {
                        mode: 'range',
                        dateFormat: 'd-m-Y',
                        locale: 'ru',
                        allowInput: false,
                        clickOpens: true,
                        minDate: minPick,
                        maxDate: maxPick,
                        defaultDate: [defaultFrom, defaultTo],
                        disableMobile: true,
                        monthSelectorType: 'static',
                        onDayCreate: tvCalDayCreate,
                        onReady: function () {
                            if (typeof window.tvCalendarRefreshUI === 'function') window.tvCalendarRefreshUI(tvDatePicker);
                            if (typeof window.tvLoadCalendarPriceMap === 'function') window.tvLoadCalendarPriceMap();
                        },
                        onMonthChange: function () { if (typeof window.tvCalendarRefreshUI === 'function') window.tvCalendarRefreshUI(tvDatePicker); },
                        onYearChange: function () { if (typeof window.tvCalendarRefreshUI === 'function') window.tvCalendarRefreshUI(tvDatePicker); },
                        onOpen: function () { if (typeof window.tvLoadCalendarPriceMap === 'function') window.tvLoadCalendarPriceMap(); }
                    });
                    if (tvDatePicker) {
                        try { window.tvDatePicker = tvDatePicker; } catch (e) {}
                        datesInp.addEventListener('focus', function() { tvDatePicker.open(); });
                        var wrap = document.getElementById('tv-dates-wrap');
                        if (wrap) wrap.addEventListener('click', function(e) { e.preventDefault(); datesInp.focus(); tvDatePicker.open(); });
                        var dispEl = document.getElementById('tv-sc-dates-display');
                        if (dispEl && tvDatePicker.selectedDates && tvDatePicker.selectedDates.length >= 2) {
                            setDefaultNightsRange();
                            updateDateDisplayOnly(tvDatePicker.selectedDates[0], tvDatePicker.selectedDates[1]);
                        }
                    }
                } catch (_) {}
            }
            initTvDatePicker();
            if (!tvDatePicker && datesInp) {
                window.addEventListener('load', function () {
                    initTvDatePicker();
                    var disp = document.getElementById('tv-sc-dates-display');
                    var emptyDates = !disp || !disp.textContent.trim() || disp.textContent.trim() === 'Даты';
                    if (emptyDates && !window.__tvRestoringFromBack) applyDefaultDateWindow();
                });
            }

            var rDep, rCountries, rMeal;
            try {
                var results = await Promise.all([pDep, pCountries, pMeal]);
                rDep = results[0]; rCountries = results[1]; rMeal = results[2];
            } catch (err) {
                console.error('[Tourvisor] Ошибка загрузки справочников', err);
                rDep = { success: false, data: [] }; rCountries = { success: false, data: [] }; rMeal = { success: false, data: [] };
            }
            console.log('%c[API → сайт] Ответы API получены', 'color: #5DA9A4; font-weight: bold', { departures: rDep.success ? (rDep.data?.length ?? 0) + ' шт.' : 'ошибка', countries: rCountries.success ? (rCountries.data?.length ?? 0) + ' шт.' : 'ошибка', meals: rMeal.success ? (rMeal.data?.length ?? 0) + ' шт.' : 'ошибка' });
            var needDebug = !rDep.success || !rCountries.success || !rMeal.success || !(rDep.data && rDep.data.length) || !(rCountries.data && rCountries.data.length) || !(rMeal.data && rMeal.data.length);
            if (needDebug) {
                var debugPayload = {
                    dictionaries_response: window.__tv_dictionaries_raw || null,
                    dep: { success: rDep.success, error: rDep.error, error_detail: rDep.error_detail || null, dataLength: Array.isArray(rDep.data) ? rDep.data.length : 0, raw: rDep },
                    countries: { success: rCountries.success, error: rCountries.error, error_detail: rCountries.error_detail || null, dataLength: Array.isArray(rCountries.data) ? rCountries.data.length : 0, raw: rCountries },
                    meals: { success: rMeal.success, error: rMeal.error, error_detail: rMeal.error_detail || null, dataLength: Array.isArray(rMeal.data) ? rMeal.data.length : 0, raw: rMeal },
                    urls: { DICTIONARIES_URL: typeof DICTIONARIES_URL !== 'undefined' ? DICTIONARIES_URL : '—', TV_API_BASE: typeof TV_API_BASE !== 'undefined' ? TV_API_BASE : '—' }
                };
                console.group('%c[API → сайт] Подробная отладка: почему данные пустые или с ошибкой', 'color: #f59e0b; font-weight: bold');
                if (window.__tv_dictionaries_raw && window.__tv_dictionaries_raw._debug) {
                    console.log('%c▼ Ответ dictionaries.php с сервера (_debug):', 'color: #ef4444; font-weight: bold', window.__tv_dictionaries_raw._debug);
                }
                console.log('Города вылета (departures):', debugPayload.dep);
                if (rDep && rDep.error_detail) console.log('%c▼ Ошибка сервера (departures):', 'color: #ef4444', rDep.error_detail);
                console.log('Страны (countries):', debugPayload.countries);
                if (rCountries && rCountries.error_detail) console.log('%c▼ Ошибка сервера (countries):', 'color: #ef4444', rCountries.error_detail);
                console.log('Питание (meals):', debugPayload.meals);
                if (rMeal && rMeal.error_detail) console.log('%c▼ Ошибка сервера (meals) — скинь это разработчику:', 'color: #ef4444; font-weight: bold', rMeal.error_detail);
                console.log('URL справочников:', debugPayload.urls.DICTIONARIES_URL);
                console.log('Базовый URL прокси:', debugPayload.urls.TV_API_BASE);
                console.log('%c▼ СКОПИРУЙ ВЕСЬ ЭТОТ ОБЪЕКТ И ОТПРАВЬ (Ctrl+A в поддереве, правый клик → Copy object):', 'color: #5DA9A4; font-weight: bold');
                console.log(debugPayload);
                console.groupEnd();
            }
            let departuresList = [];
            let countriesList = [];
            function filterDepartureList(list) {
                if (window.THDeparturePreference && typeof window.THDeparturePreference.filterDepartures === 'function') {
                    return window.THDeparturePreference.filterDepartures(list || []);
                }
                return (list || []).filter(function (d) {
                    var n = String((d && d.name) || '').toLowerCase().trim();
                    return n !== 'красноярск' && n !== 'krasnoyarsk';
                });
            }
            function isBlockedDepartureName(name) {
                if (window.THDeparturePreference && typeof window.THDeparturePreference.isBlockedDepartureName === 'function') {
                    return window.THDeparturePreference.isBlockedDepartureName(name);
                }
                var n = String(name || '').toLowerCase().trim();
                return n === 'красноярск' || n === 'krasnoyarsk';
            }
            if (rDep.success && Array.isArray(rDep.data) && rDep.data.length > 0) {
                departuresList = filterDepartureList(rDep.data);
                if (depSel && depSel.tagName === 'SELECT') {
                    depSel.innerHTML = '<option value="">— Выберите город —</option>' + departuresList.map(d =>
                        `<option value="${d.id}">${d.name || ''}</option>`
                    ).join('');
                }
                console.log('%c[API → сайт] Справочник городов вылета', 'color: #22c55e', departuresList.length, 'шт.');
            } else {
                if (depSel && depSel.tagName === 'SELECT') {
                    var defId = defaultDepartureIdStr();
                    var defNm = (window.TH_DEPARTURE && window.TH_DEPARTURE.name) || 'Самара';
                    depSel.innerHTML = '<option value="' + defId + '">' + defNm + '</option>';
                    depSel.value = defId;
                }
                console.warn('[API → сайт] Города вылета: данные с API не получены. Причина:', rDep && rDep.error ? rDep.error : (rDep && Array.isArray(rDep.data) && rDep.data.length === 0 ? 'массив пустой' : (rDep && rDep.success === false ? 'success=false' : 'нет данных')), 'Ответ:', rDep);
            }

            if (window.THDeparturePreference) {
                window.THDeparturePreference.onDeparturesReady(departuresList);
            }
            ensureDepartureSelected();

            if (rCountries.success && Array.isArray(rCountries.data) && rCountries.data.length > 0) {
                let countries = sortCountriesWithDefaultFirst(rCountries.data);
                const defaultCountry = findDefaultCountry(countries);
                countrySel.innerHTML = '<option value="">— Страна —</option>' + countries.map(c =>
                    `<option value="${c.id}">${c.name || ''}</option>`
                ).join('');
                countrySel.value = defaultCountry ? defaultCountry.id : (countries[0]?.id || '');
                if (!depSel.value) {
                    var samId = defaultDepartureIdStr();
                    var sam = departuresList.find(function(d) { return String(d.id) === String(samId); })
                        || departuresList.find(function(d) { return /самара/i.test(String(d.name || '')); });
                    if (sam) depSel.value = sam.id;
                    else {
                        var depFb = departuresList.find(function(d) { return !isBlockedDepartureName(d.name); });
                        if (depFb) depSel.value = depFb.id;
                    }
                }
                ensureDepartureSelected();
                if (countrySel.value && depSel.value) countrySel.dispatchEvent(new Event('change'));
                else if (!window.__tvRestoringFromBack) applyDefaultDateWindow();
                if (window.THTourSearchWizard && typeof window.THTourSearchWizard.refreshSummary === 'function') {
                    window.THTourSearchWizard.refreshSummary();
                }
                console.log('%c[API → сайт] Данные с API применены: страны', 'color: #22c55e', countries.length, 'стран', countries.slice(0, 5).map(c => c.name));
            } else if (rCountries.success && Array.isArray(rCountries.data)) {
                countrySel.innerHTML = '<option value="">— Нет стран —</option>';
                console.warn('[API → сайт] Страны: список пуст от API (success=true, но data пустой). Ответ:', rCountries);
            } else {
                countrySel.innerHTML = '<option value="">— Не удалось загрузить —</option>';
                console.warn('[API → сайт] Страны: данные с API не получены. Причина:', rCountries && rCountries.error ? rCountries.error : (rCountries && rCountries.success === false ? 'success=false' : 'нет данных'), 'Ответ:', rCountries);
            }

            if (rMeal.success && Array.isArray(rMeal.data) && rMeal.data.length > 0) {
                mealSel.innerHTML = '<option value="">Любое</option>' + rMeal.data.map(m =>
                    `<option value="${m.id}">${m.russianName || m.name || ''}</option>`
                ).join('');
                console.log('%c[API → сайт] Данные с API применены: питание', 'color: #22c55e', rMeal.data.length, 'типов', rMeal.data.map(m => m.russianName || m.name));
                console.log('[Фильтры] meals:', rMeal.data.length, 'записей');
            } else {
                var mealFallback = [{ id: 1, name: 'RO', russianName: 'Без питания' }, { id: 2, name: 'BB', russianName: 'Завтрак' }, { id: 3, name: 'HB', russianName: 'Завтрак + ужин' }, { id: 4, name: 'FB', russianName: 'Полный пансион' }, { id: 5, name: 'AI', russianName: 'Всё включено' }, { id: 6, name: 'UAI', russianName: 'Ультра всё включено' }];
                mealSel.innerHTML = '<option value="">Любое</option>' + mealFallback.map(m => '<option value="' + m.id + '">' + (m.russianName || m.name) + '</option>').join('');
                console.warn('[API → сайт] Питание: данные с API не получены, подставлен fallback. Причина:', rMeal && rMeal.error ? rMeal.error : (rMeal && rMeal.success === false ? 'success=false' : 'нет данных'));
                console.log('[Фильтры] Ошибка meals: использован fallback', mealFallback.length, 'записей');
            }
            if (window.THSearchUI && typeof window.THSearchUI.refreshFilterLabels === 'function') {
                window.THSearchUI.refreshFilterLabels();
            }

            if (rCountries.success && Array.isArray(rCountries.data)) {
                countriesList = sortCountriesWithDefaultFirst(rCountries.data);
            }

            function applyTvCountriesList(list, opts) {
                opts = opts || {};
                var keepId = opts.keepCountryId != null
                    ? String(opts.keepCountryId)
                    : (countrySel ? String(countrySel.value || '') : '');
                countriesList = sortCountriesWithDefaultFirst(list || []);
                if (!countrySel) return;
                var defaultCountry = findDefaultCountry(countriesList);
                countrySel.innerHTML = '<option value="">— Страна —</option>' + countriesList.map(function (c) {
                    return '<option value="' + c.id + '">' + (c.name || '') + '</option>';
                }).join('');
                var keepOk = keepId && countriesList.some(function (c) { return String(c.id) === keepId; });
                if (keepOk) countrySel.value = keepId;
                else countrySel.value = defaultCountry ? defaultCountry.id : (countriesList[0] ? countriesList[0].id : '');
                if (typeof renderCountryList === 'function') renderCountryList('');
                if (window.THTourSearchWizard && typeof window.THTourSearchWizard.refreshSummary === 'function') {
                    window.THTourSearchWizard.refreshSummary();
                }
                if (window.THSearchUI && typeof window.THSearchUI.refreshLabels === 'function') {
                    window.THSearchUI.refreshLabels();
                }
            }

            /* Фоновый догруз из прокси (полный список под departure) */
            if (window.__tv_countriesEnrichPromise && typeof window.__tv_countriesEnrichPromise.then === 'function') {
                window.__tv_countriesEnrichPromise.then(function (merged) {
                    if (!merged || !merged.length) return;
                    if (merged.length <= countriesList.length) return;
                    applyTvCountriesList(merged, { keepCountryId: countrySel ? countrySel.value : '' });
                }).catch(function () {});
            }

            function reloadTvCountriesForDeparture(depId) {
                var id = parseInt(String(depId || ''), 10) || 0;
                if (!id || typeof tvFetch !== 'function') return Promise.resolve();
                return Promise.all([
                    tvFetch('countries', { departureId: String(id) }),
                    tvFetch('countries', { departureId: String(id), onlyCharter: '1' })
                ]).then(function (res) {
                    var byId = {};
                    res.forEach(function (j) {
                        var list = (j && j.success && Array.isArray(j.data)) ? j.data : [];
                        list.forEach(function (c) { if (c && c.id != null) byId[c.id] = c; });
                    });
                    var merged = Object.keys(byId).map(function (k) { return byId[k]; });
                    if (!merged.length) return;
                    applyTvCountriesList(merged, { keepCountryId: countrySel ? countrySel.value : '' });
                    if (countrySel && countrySel.value) {
                        try { countrySel.dispatchEvent(new Event('change', { bubbles: true })); } catch (eCh) {}
                    }
                }).catch(function () {});
            }
            window.reloadTvCountriesForDeparture = reloadTvCountriesForDeparture;

            function resolveHomePopularDepartureId() {
                if (!departuresList || !departuresList.length) return 0;
                try {
                    var sid = localStorage.getItem('th_departure_id');
                    var sname = localStorage.getItem('th_departure_name');
                    if (sname && isBlockedDepartureName(sname)) {
                        sid = null;
                        sname = null;
                    }
                    if (sid) {
                        var idNum = parseInt(String(sid), 10);
                        if (idNum && departuresList.some(function (d) { return parseInt(String(d.id), 10) === idNum; })) {
                            return idNum;
                        }
                    }
                    if (sname && window.THDeparturePreference && typeof window.THDeparturePreference.matchDeparture === 'function') {
                        var m = window.THDeparturePreference.matchDeparture(sname, departuresList);
                        if (m && m.id != null) return parseInt(String(m.id), 10);
                    }
                } catch (e) {}
                if (depSel && depSel.value) return parseInt(String(depSel.value), 10) || 0;
                return 0;
            }
            var homePopularDepId = resolveHomePopularDepartureId();
            if (homePopularDepId) loadHomeShowcaseShelves(homePopularDepId);

            console.log('%c[API → сайт] Итог: форма заполнена данными с API. Курорты и расширенные фильтры подгрузятся при выборе страны.', 'color: #5DA9A4; font-weight: bold');

            function renderDepList(filter) {
                var listEl = document.getElementById('tv-departure-list');
                if (!listEl) return;
                var q = (filter || '').toLowerCase().trim();
                var list = q ? departuresList.filter(function(d) { return (d.name || '').toLowerCase().indexOf(q) !== -1; }) : departuresList;
                listEl.innerHTML = list.map(function(d) {
                    var name = (d.name || '').toString().replace(/</g, '&lt;').replace(/"/g, '&quot;');
                    return '<button type="button" class="tv-choice-item" data-id="' + d.id + '">' + name + '</button>';
                }).join('');
                listEl.querySelectorAll('.tv-choice-item').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        depSel.value = btn.getAttribute('data-id');
                        var p = document.getElementById('tv-departure-popup');
                        if (p) p.style.display = 'none';
                        depSel.dispatchEvent(new Event('change'));
                    });
                });
            }
            function renderCountryList(filter) {
                var listEl = document.getElementById('tv-country-list');
                if (!listEl) return;
                var q = (filter || '').toLowerCase().trim();
                var list = q ? countriesList.filter(function(c) { return (c.name || '').toLowerCase().indexOf(q) !== -1; }) : countriesList;
                listEl.innerHTML = list.map(function(c) {
                    var name = (c.name || '').toString().replace(/</g, '&lt;').replace(/"/g, '&quot;');
                    return '<button type="button" class="tv-choice-item" data-id="' + c.id + '">' + name + '</button>';
                }).join('');
                listEl.querySelectorAll('.tv-choice-item').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        countrySel.value = btn.getAttribute('data-id');
                        var p = document.getElementById('tv-country-popup');
                        if (p) p.style.display = 'none';
                        countrySel.dispatchEvent(new Event('change'));
                    });
                });
            }
            window.__tv_hotelServicesCache = window.__tv_hotelServicesCache || {};
            function mapGroupToCategory(name) {
                var n = (name || '').toLowerCase();
                if (/пляж|расположен|линия|берег|побереж/i.test(n)) return 'beach';
                if (/отель|территор|бассейн|спорт|ресторан|бар/i.test(n)) return 'hotel';
                if (/удобств|номер|комнат|ванн|wi-fi|кондицион|телевизор|балкон|кухн/i.test(n)) return 'room';
                if (/дет|ребен|клуб|анимац/i.test(n)) return 'children';
                return 'hotel';
            }
            var tvSelectedServiceIds = [];
            var tvServiceIdToName = {};
            function renderSelectedFilterTags() {
                var wrap = document.getElementById('tv-adv-selected-tags');
                if (!wrap) return;
                wrap.innerHTML = tvSelectedServiceIds.map(function(id) {
                    var name = tvServiceIdToName[id] || ('ID ' + id);
                    return '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-sky-100 text-sky-800 text-sm">' + name + ' <button type="button" class="tv-adv-remove-tag hover:text-red-600" data-id="' + id + '" aria-label="Снять">×</button></span>';
                }).join('');
                wrap.querySelectorAll('.tv-adv-remove-tag').forEach(function(b) {
                    b.addEventListener('click', function() {
                        var id = parseInt(this.dataset.id, 10);
                        tvSelectedServiceIds = tvSelectedServiceIds.filter(function(x) { return x !== id; });
                        var cb = document.querySelector('.tv-adv-service-cb[data-id="' + id + '"]');
                        if (cb) cb.checked = false;
                        renderSelectedFilterTags();
                    });
                });
            }
            function renderAdvancedFilters(groups) {
                var beach = document.getElementById('tv-adv-cat-beach');
                var hotel = document.getElementById('tv-adv-cat-hotel');
                var room = document.getElementById('tv-adv-cat-room');
                var children = document.getElementById('tv-adv-cat-children');
                if (!beach || !hotel) return;
                [beach, hotel, room, children].forEach(el => { if (el) el.innerHTML = ''; });
                if (!Array.isArray(groups) || groups.length === 0) {
                    var msg = document.createElement('p');
                    msg.className = 'text-slate-500 text-sm';
                    msg.textContent = 'Нет данных по услугам для этой страны. Данные подставляются из API.';
                    if (beach) beach.appendChild(msg);
                    return;
                }
                groups.forEach(function(gr) {
                    var cat = mapGroupToCategory(gr.name);
                    var container = cat === 'beach' ? beach : (cat === 'room' ? room : (cat === 'children' ? children : hotel));
                    var items = gr.items || [];
                    items.forEach(function(item) {
                        var id = parseInt(item.id, 10);
                        var name = (item.name || item.russianName || '').toString();
                        if (!name || !container) return;
                        tvServiceIdToName[id] = name;
                        var label = document.createElement('label');
                        label.className = 'inline-flex items-center gap-2 cursor-pointer text-slate-700';
                        var cb = document.createElement('input');
                        cb.type = 'checkbox';
                        cb.className = 'tv-adv-service-cb rounded border-slate-300 text-[#1A1A40] focus:ring-[#1A1A40]';
                        cb.dataset.id = id;
                        if (tvSelectedServiceIds.indexOf(id) >= 0) cb.checked = true;
                        cb.addEventListener('change', function() {
                            if (this.checked) {
                                if (tvSelectedServiceIds.indexOf(id) < 0) tvSelectedServiceIds.push(id);
                            } else {
                                tvSelectedServiceIds = tvSelectedServiceIds.filter(function(x) { return x !== id; });
                            }
                            renderSelectedFilterTags();
                        });
                        label.appendChild(cb);
                        label.appendChild(document.createTextNode(name));
                        container.appendChild(label);
                    });
                });
                renderSelectedFilterTags();
            }
            async function loadAdvancedFilters(countryId) {
                if (!countryId) return;
                var beach = document.getElementById('tv-adv-cat-beach');
                if (beach) beach.innerHTML = '<p class="text-slate-500 text-sm">Загрузка из API…</p>';
                if (window.__tv_hotelServicesCache[countryId]) {
                    [document.getElementById('tv-adv-cat-beach'), document.getElementById('tv-adv-cat-hotel'), document.getElementById('tv-adv-cat-room'), document.getElementById('tv-adv-cat-children')].forEach(el => { if (el) el.innerHTML = ''; });
                    renderAdvancedFilters(window.__tv_hotelServicesCache[countryId]);
                    console.log('%c[API → сайт] Расширенные фильтры: данные из кэша (ранее загружены с API)', 'color: #22c55e', 'countryId', countryId);
                    return;
                }
                var r = await tvFetch('hotel-services', { countryId: countryId });
                [document.getElementById('tv-adv-cat-beach'), document.getElementById('tv-adv-cat-hotel'), document.getElementById('tv-adv-cat-room'), document.getElementById('tv-adv-cat-children')].forEach(el => { if (el) el.innerHTML = ''; });
                if (r.success && Array.isArray(r.data) && r.data.length > 0) {
                    window.__tv_hotelServicesCache[countryId] = r.data;
                    renderAdvancedFilters(r.data);
                    var totalItems = r.data.reduce(function(sum, gr) { return sum + (gr.items && gr.items.length ? gr.items.length : 0); }, 0);
                    console.log('%c[API → сайт] Данные с API применены: расширенные фильтры (услуги)', 'color: #22c55e', r.data.length, 'категорий,', totalItems, 'услуг для countryId', countryId);
                } else {
                    renderAdvancedFilters([]);
                    console.warn('[API → сайт] Расширенные фильтры: API не вернул данные для countryId', countryId);
                }
            }
            async function loadTvRegions() {
                if (!regionSel) return;
                const cid = countrySel ? String(countrySel.value || '').trim() : '';
                if (!cid) {
                    regionSel.innerHTML = '<option value="">Любой</option>';
                    if (window.THSearchUI && typeof window.THSearchUI.refreshFilterLabels === 'function') {
                        window.THSearchUI.refreshFilterLabels();
                    }
                    return;
                }
                regionSel.innerHTML = '<option value="">Загрузка...</option>';
                let rReg = await tvFetch('regions', { countryId: cid });
                if (!rReg.success || !Array.isArray(rReg.data) || rReg.data.length === 0) {
                    await new Promise(function (r) { setTimeout(r, 700); });
                    rReg = await tvFetch('regions', { countryId: cid });
                }
                if (rReg.success && Array.isArray(rReg.data) && rReg.data.length > 0) {
                    regionSel.innerHTML = '<option value="">Любой</option>' + rReg.data.map(function (r) {
                        return '<option value="' + r.id + '">' + (r.name || '') + '</option>';
                    }).join('');
                    console.log('%c[API → сайт] Данные с API применены: курорты', 'color: #22c55e', rReg.data.length, 'курортов для countryId', cid);
                } else {
                    regionSel.innerHTML = '<option value="">Любой</option>';
                    console.warn('[API → сайт] Курорты: пусто или ошибка, оставлен «Любой»', rReg && rReg.error ? rReg.error : '');
                }
                if (window.THSearchUI && typeof window.THSearchUI.refreshFilterLabels === 'function') {
                    window.THSearchUI.refreshFilterLabels();
                }
            }
            window.loadTvRegions = loadTvRegions;

            countrySel.addEventListener('change', async function() {
                window.__tvFlyDates = { key: '', all: {}, charter: {}, direct: {} };
                window.__tvFlyNights = { key: '', all: {}, charter: {}, direct: {}, enriched: false };
                window.__tvCalPriceMap = {};
                window.__tvCalPriceMapKey = '';
                await loadTvRegions();
                applyDefaultDateWindow();
                tvSchedulePrefetchHomeSearch();
                if (typeof window.tvLoadCalendarPriceMap === 'function') window.tvLoadCalendarPriceMap();
                if (typeof window.tvLoadFlyAvailability === 'function') window.tvLoadFlyAvailability();
            });
            if (depSel) {
                depSel.addEventListener('change', function () {
                    reloadTvCountriesForDeparture(depSel.value);
                    window.__tvFlyDates = { key: '', all: {}, charter: {}, direct: {} };
                    window.__tvFlyNights = { key: '', all: {}, charter: {}, direct: {} };
                    if (typeof window.tvLoadFlyAvailability === 'function') window.tvLoadFlyAvailability();
                    if (countrySel && countrySel.value) {
                        loadTvRegions();
                        tvSchedulePrefetchHomeSearch();
                        if (typeof window.tvLoadCalendarPriceMap === 'function') window.tvLoadCalendarPriceMap();
                    }
                });
            }
            if (countrySel.value) {
                loadTvRegions();
            }

            function onSearchBtnClick() {
                if (window.THTourSearchWizard && typeof window.THTourSearchWizard.validateSearchReady === 'function') {
                    if (!window.THTourSearchWizard.validateSearchReady()) return;
                } else if (window.THTourSearchWizard && typeof window.THTourSearchWizard.validateCurrent === 'function') {
                    if (!window.THTourSearchWizard.validateCurrent()) return;
                }
                if (typeof performTvSearch === 'function') {
                    performTvSearch(true);
                }
            }
            var searchBtn = document.getElementById('tv-search-btn');
            if (searchBtn) searchBtn.addEventListener('click', onSearchBtnClick);
            document.querySelectorAll('[data-th-hotel-find]').forEach(function (btn) {
                btn.addEventListener('click', onSearchBtnClick);
            });
            var tvSortEl = document.getElementById('tv-sort');
            if (tvSortEl) tvSortEl.addEventListener('change', applyTvSort);
            document.getElementById('tv-load-more-btn').addEventListener('click', () => loadMoreTvResults());
            document.addEventListener('click', function(e) {
                var shiftBtn = e.target && e.target.closest ? e.target.closest('[data-tv-shift-days]') : null;
                if (!shiftBtn) return;
                if (shiftBtn.disabled || shiftBtn.getAttribute('aria-disabled') === 'true' || shiftBtn.classList.contains('is-disabled')) {
                    e.preventDefault();
                    return;
                }
                e.preventDefault();
                tvShiftSearchDates(parseInt(String(shiftBtn.getAttribute('data-tv-shift-days') || '0'), 10));
            });
            var tvCompareOpenBtn = document.getElementById('tv-compare-open-btn');
            var tvComparePanel = document.getElementById('tv-compare-panel');
            var tvCompareClearBtn = document.getElementById('tv-compare-clear-btn');
            if (tvCompareOpenBtn) {
                tvCompareOpenBtn.addEventListener('click', function() {
                    tvCompareOpenModal();
                    thTrackGoal('compare_open');
                });
            }
            if (tvComparePanel) {
                tvComparePanel.addEventListener('click', function(e) {
                    if (e.target && e.target.closest('[data-tv-compare-close]')) {
                        tvCompareCloseModal();
                    }
                });
            }
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') tvCompareCloseModal();
            });
            if (tvCompareClearBtn) {
                tvCompareClearBtn.addEventListener('click', function() {
                    tvCompareMap = {};
                    tvComparePersist();
                    tvCompareSyncButtons();
                    tvCompareRenderPanel();
                    thTrackGoal('compare_clear');
                });
            }
            var tvCompareList = document.getElementById('tv-compare-list');
            if (tvCompareList) {
                tvCompareList.addEventListener('click', function(e) {
                    var rm = e.target && e.target.closest('[data-tv-compare-remove]');
                    if (!rm) return;
                    var key = String(rm.getAttribute('data-tv-compare-remove') || '');
                    if (key && tvCompareMap[key]) {
                        delete tvCompareMap[key];
                        tvComparePersist();
                        tvCompareSyncButtons();
                        tvCompareRenderPanel();
                        thTrackGoal('compare_remove');
                    }
                });
            }
            document.addEventListener('click', function(e) {
                var btn = e.target && e.target.closest('[data-th-compare-toggle]');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                tvCompareToggleFromButton(btn);
            }, true);
            document.querySelectorAll('[data-th-search-mode]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    thApplySearchMode(btn.getAttribute('data-th-search-mode') || 'tours');
                });
            });
            try {
                var savedMode = sessionStorage.getItem('th_search_mode');
                if (savedMode === 'hotels' || savedMode === 'tours') thApplySearchMode(savedMode);
            } catch (eMode) {}
            var showcaseRoot = document.querySelector('.th-home-showcase');
            if (showcaseRoot) {
                showcaseRoot.addEventListener('click', function(e) {
                    if (e.target && e.target.closest('.th-tour-offer')) thTrackGoal('hot_tour_click');
                    if (e.target && e.target.closest('.th-mood-chip')) thTrackGoal('mood_click');
                });
            }
            tvCompareRestore();
            tvCompareRenderPanel();

            setDefaultNightsRange();
            var tvNightsPopup = document.getElementById('tv-nights-popup');
            if (tvNightsPopup && tvNightsPopup.parentNode !== document.body) {
                document.body.appendChild(tvNightsPopup);
            }
            var tvNightsGrid = document.getElementById('tv-nights-grid');
            var tvNightsQuick = document.getElementById('tv-nights-quick');
            var tvNightsSelectFrom = true;
            var draftNightsFrom = tvNightsFrom;
            var draftNightsTo = tvNightsTo;
            function closeTvNightsPopup() {
                if (tvNightsPopup) {
                    tvNightsPopup.classList.add('hidden');
                    tvNightsPopup.classList.remove('is-open');
                    tvNightsPopup.style.display = 'none';
                    tvNightsPopup.setAttribute('aria-hidden', 'true');
                }
            }
            function notifyWizardNightsDone() {
                try {
                    document.dispatchEvent(new CustomEvent('th:wizard-nights-done'));
                } catch (eN) {}
            }
            function updateTvNightsSummary() {
                var el = document.getElementById('tv-nights-summary-text');
                function nWord(n) { return n % 10 === 1 && n % 100 !== 11 ? 'ночь' : (n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 10 || n % 100 >= 20) ? 'ночи' : 'ночей'); }
                if (el) el.textContent = tvNightsFrom === tvNightsTo ? (tvNightsFrom + ' ' + nWord(tvNightsFrom)) : (tvNightsFrom + '–' + tvNightsTo + ' ' + nWord(tvNightsTo));
                var fromLbl = document.getElementById('tv-nights-from-label');
                var toLbl = document.getElementById('tv-nights-to-label');
                var hintEl = document.getElementById('tv-nights-hint');
                if (fromLbl) fromLbl.textContent = tvNightsFrom;
                if (toLbl) toLbl.textContent = tvNightsTo;
                if (hintEl) {
                    hintEl.textContent = tvNightsSelectFrom
                        ? 'Свой диапазон: нажмите число «от»'
                        : 'Теперь нажмите число «до»';
                }
            }
            function applyTvNightsQuick(from, to) {
                draftNightsFrom = from;
                draftNightsTo = to;
                tvNightsSelectFrom = true;
                renderTvNightsGrid(true);
                updateTvNightsDraftHint();
                syncTvNightsQuickActive();
            }
            function syncTvNightsQuickActive() {
                if (!tvNightsQuick) return;
                tvNightsQuick.querySelectorAll('.tv-nights-quick__chip').forEach(function (chip) {
                    var f = parseInt(chip.getAttribute('data-nights-from') || chip.getAttribute('data-from'), 10);
                    var t = parseInt(chip.getAttribute('data-nights-to') || chip.getAttribute('data-to'), 10);
                    var on = Number.isFinite(f) && Number.isFinite(t)
                        && f === draftNightsFrom && t === draftNightsTo;
                    chip.classList.toggle('active', on);
                });
            }
            if (tvNightsQuick) {
                if (window.THDatePresets && typeof window.THDatePresets.renderNightsChips === 'function') {
                    window.THDatePresets.renderNightsChips(tvNightsQuick, function (from, to) {
                        applyTvNightsQuick(from, to);
                    });
                } else {
                    [[7, 7, '7 ночей'], [7, 10, '7–10'], [10, 14, '10–14'], [14, 21, '14–21']].forEach(function (p) {
                        var b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'tv-nights-quick__chip';
                        b.textContent = p[2];
                        b.setAttribute('data-nights-from', String(p[0]));
                        b.setAttribute('data-nights-to', String(p[1]));
                        b.addEventListener('click', function () { applyTvNightsQuick(p[0], p[1]); });
                        tvNightsQuick.appendChild(b);
                    });
                }
            }
            function updateTvNightsDraftHint() {
                var hintEl = document.getElementById('tv-nights-hint');
                var fromLbl = document.getElementById('tv-nights-from-label');
                var toLbl = document.getElementById('tv-nights-to-label');
                if (fromLbl) fromLbl.textContent = draftNightsFrom;
                if (toLbl) toLbl.textContent = draftNightsTo;
                if (hintEl) {
                    hintEl.textContent = tvNightsSelectFrom
                        ? 'Свой диапазон: нажмите число «от»'
                        : 'Теперь нажмите число «до»';
                }
                var preview = document.getElementById('tv-nights-draft-preview');
                if (preview) {
                    function nWord(n) { return n % 10 === 1 && n % 100 !== 11 ? 'ночь' : (n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 10 || n % 100 >= 20) ? 'ночи' : 'ночей'); }
                    preview.textContent = draftNightsFrom === draftNightsTo
                        ? (draftNightsFrom + ' ' + nWord(draftNightsFrom))
                        : (draftNightsFrom + '–' + draftNightsTo + ' ' + nWord(draftNightsTo));
                }
            }
            var tvNightsHoverTo = null;
            function renderTvNightsGrid(useDraft) {
                if (!tvNightsGrid) return;
                var fromN = useDraft ? draftNightsFrom : tvNightsFrom;
                var toN = useDraft ? draftNightsTo : tvNightsTo;
                if (!tvNightsSelectFrom && tvNightsHoverTo != null) {
                    var h = tvNightsHoverTo;
                    if (h < fromN) {
                        toN = fromN;
                        fromN = h;
                    } else {
                        toN = h;
                    }
                }
                var flags = (typeof tvFlightFlags === 'function')
                    ? tvFlightFlags()
                    : {
                        charter: !!(document.getElementById('tv-only-charter') && document.getElementById('tv-only-charter').checked),
                        direct: !!(document.getElementById('tv-only-direct') && document.getElementById('tv-only-direct').checked)
                    };
                var flyN = window.__tvFlyNights || {};
                var availMap = (flags.direct || flags.charter)
                    ? (flyN.direct || {})
                    : (flyN.all || {});
                var hasAvail = Object.keys(availMap).length > 0;
                var legend = document.querySelector('#tv-nights-popup .tv-nights-legend');
                if (legend) {
                    legend.classList.toggle('is-filter-on', !!(flags.direct || flags.charter));
                    legend.classList.toggle('has-data', !!(
                        (flyN.nights && Object.keys(flyN.all || {}).length)
                        || (flyN.direct && Object.keys(flyN.direct).length)
                    ));
                    legend.setAttribute('aria-hidden', legend.classList.contains('has-data') ? 'false' : 'true');
                }
                tvNightsGrid.querySelectorAll('.tv-nights-cell').forEach(function(btn) {
                    var n = parseInt(btn.getAttribute('data-n'), 10);
                    btn.classList.remove('is-from', 'is-to', 'is-in-range', 'is-range-hover', 'text-white', 'is-fly-available', 'is-fly-direct', 'is-fly-off');
                    if (n === fromN) {
                        btn.classList.add('is-from', 'text-white');
                    } else if (n === toN && toN !== fromN) {
                        btn.classList.add('is-to', 'text-white');
                    } else if (n > fromN && n < toN) {
                        btn.classList.add(!tvNightsSelectFrom && tvNightsHoverTo != null ? 'is-range-hover' : 'is-in-range');
                    }
                    if (flyN.direct && flyN.direct[n]) btn.classList.add('is-fly-direct');
                    else if (flyN.all && flyN.all[n]) btn.classList.add('is-fly-available');
                    if ((flags.direct || flags.charter) && hasAvail && !availMap[n]) {
                        btn.classList.add('is-fly-off');
                    }
                });
                syncTvNightsQuickActive();
            }
            window.renderTvNightsGrid = renderTvNightsGrid;
            function openTvNightsPopup() {
                draftNightsFrom = tvNightsFrom;
                draftNightsTo = tvNightsTo;
                tvNightsSelectFrom = true;
                updateTvNightsDraftHint();
                renderTvNightsGrid(true);
                if (typeof window.tvLoadFlyNights === 'function') window.tvLoadFlyNights();
                if (tvNightsPopup) {
                    tvNightsPopup.style.zIndex = '10260';
                    tvNightsPopup.classList.remove('hidden');
                    tvNightsPopup.classList.add('is-open');
                    tvNightsPopup.style.display = 'flex';
                    tvNightsPopup.setAttribute('aria-hidden', 'false');
                    var popupCard = document.getElementById('tv-nights-popup-card');
                    if (popupCard) popupCard.style.pointerEvents = 'auto';
                }
            }
            window.__thWizardOpenNightsPopup = openTvNightsPopup;
            var tvNightsTrigger = document.getElementById('tv-nights-trigger');
            var tvNightsSummaryBtn = document.getElementById('tv-nights-summary');
            var tvNightsPopupCard = document.getElementById('tv-nights-popup-card');
            if (tvNightsTrigger) tvNightsTrigger.addEventListener('click', openTvNightsPopup);
            if (tvNightsSummaryBtn) tvNightsSummaryBtn.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); openTvNightsPopup(); });
            function handleTvNightsCellSelect(btn, e) {
                if (!btn) return;
                if (e) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                var n = parseInt(btn.getAttribute('data-n'), 10);
                if (!Number.isFinite(n) || n < 1 || n > 28) return;
                tvNightsHoverTo = null;
                if (tvNightsSelectFrom) {
                    draftNightsFrom = n;
                    draftNightsTo = n;
                    tvNightsSelectFrom = false;
                } else {
                    if (n < draftNightsFrom) {
                        draftNightsTo = draftNightsFrom;
                        draftNightsFrom = n;
                    } else {
                        draftNightsTo = n;
                    }
                    tvNightsSelectFrom = true;
                }
                renderTvNightsGrid(true);
                updateTvNightsDraftHint();
            }
            tvNightsGrid && tvNightsGrid.addEventListener('click', function(e) {
                handleTvNightsCellSelect(e.target.closest('.tv-nights-cell'), e);
            });
            if (tvNightsGrid) {
                tvNightsGrid.addEventListener('pointerover', function (e) {
                    if (tvNightsSelectFrom) return;
                    var btn = e.target.closest('.tv-nights-cell');
                    if (!btn || !tvNightsGrid.contains(btn)) return;
                    var n = parseInt(btn.getAttribute('data-n'), 10);
                    if (!Number.isFinite(n) || n === tvNightsHoverTo) return;
                    tvNightsHoverTo = n;
                    renderTvNightsGrid(true);
                    var fromLbl = document.getElementById('tv-nights-from-label');
                    var toLbl = document.getElementById('tv-nights-to-label');
                    var a = draftNightsFrom;
                    var b = n;
                    if (b < a) { var t = a; a = b; b = t; }
                    if (fromLbl) fromLbl.textContent = a;
                    if (toLbl) toLbl.textContent = b;
                });
                tvNightsGrid.addEventListener('pointerleave', function () {
                    if (tvNightsHoverTo == null) return;
                    tvNightsHoverTo = null;
                    renderTvNightsGrid(true);
                    updateTvNightsDraftHint();
                });
                tvNightsGrid.querySelectorAll('.tv-nights-cell').forEach(function (btn) {
                    btn.addEventListener('touchend', function (e) { handleTvNightsCellSelect(btn, e); }, { passive: false });
                });
            }
            var tvNightsApplyBtn = document.getElementById('tv-nights-apply');
            if (tvNightsApplyBtn) tvNightsApplyBtn.addEventListener('click', function(e) {
                if (e) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                if (draftNightsFrom > 28) draftNightsFrom = 28;
                if (draftNightsTo > 28) draftNightsTo = 28;
                if (draftNightsTo < draftNightsFrom) draftNightsTo = draftNightsFrom;
                tvNightsFrom = draftNightsFrom;
                tvNightsTo = draftNightsTo;
                window.tvNightsFrom = tvNightsFrom;
                window.tvNightsTo = tvNightsTo;
                updateTvNightsSummary();
                closeTvNightsPopup();
                notifyWizardNightsDone();
                if (typeof tvSchedulePrefetchHomeSearch === 'function') tvSchedulePrefetchHomeSearch();
            });
            tvNightsPopup && tvNightsPopup.querySelectorAll('[data-th-nights-close]').forEach(function (el) {
                el.addEventListener('click', function (e) {
                    e.preventDefault();
                    closeTvNightsPopup();
                });
            });
            tvNightsPopup && tvNightsPopup.addEventListener('click', function(e) {
                if (e.target === tvNightsPopup || (e.target.classList && e.target.classList.contains('th-coral-popup__backdrop'))) {
                    closeTvNightsPopup();
                }
            });
            if (tvNightsPopupCard) {
                tvNightsPopupCard.addEventListener('click', function (e) { e.stopPropagation(); });
                tvNightsPopupCard.addEventListener('touchend', function (e) { e.stopPropagation(); }, { passive: true });
            }
            if (tvNightsPopup) tvNightsPopup.dataset.fallbackBound = '1';
            updateTvNightsSummary();
            
            // Блок ТУРИСТЫ: tvAdultsCount / tvChildrenAges объявлены выше (общая область с performTvSearch)
            var tvAgeLabels = {0:'до 2 лет',2:'2 года',3:'3 года',4:'4 года',5:'5 лет',6:'6 лет',7:'7 лет',8:'8 лет',9:'9 лет',10:'10 лет',11:'11 лет',12:'12 лет',13:'13 лет',14:'14 лет',15:'15 лет'};
            var tvAdultsValueEl = document.getElementById('tv-adults-value');
            var tvTouristsSummaryText = document.getElementById('tv-tourists-summary-text');
            var tvTouristsBlock = document.getElementById('tv-tourists-block');
            var tvChildrenRows = document.getElementById('tv-children-rows');
            var tvAddChildBtn = document.getElementById('tv-add-child-btn');
            var tvChildAgePicker = document.getElementById('tv-child-age-picker');
            var tvChildAgeGrid = document.getElementById('tv-child-age-grid');
            var tvChildAgePickerIndex = -1;
            function updateTouristsSummary() {
                var t = tvAdultsCount === 1 ? '1 взрослый' : tvAdultsCount + ' взрослых';
                if (tvChildrenAges.length > 0) t += ', ' + (tvChildrenAges.length === 1 ? '1 ребёнок' : tvChildrenAges.length + ' детей');
                if (tvTouristsSummaryText) tvTouristsSummaryText.textContent = t;
                if (tvAdultsValueEl) tvAdultsValueEl.textContent = tvAdultsCount + ' взрослых';
            }
            function renderChildrenRows() {
                if (!tvChildrenRows) return;
                tvChildrenRows.innerHTML = tvChildrenAges.map(function(age, i) {
                    var label = tvAgeLabels[age] || ('возраст ' + age);
                    return '<div class="flex items-center gap-2">' +
                        '<button type="button" class="tv-child-remove" data-index="' + i + '" aria-label="Удалить">−</button>' +
                        '<div class="tv-sc-child-pill">' +
                        '<button type="button" class="tv-child-age-btn" data-index="' + i + '">Ребенок ' + label + '</button>' +
                        '</div></div>';
                }).join('');
                tvChildrenRows.querySelectorAll('.tv-child-remove').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var i = parseInt(this.dataset.index, 10);
                        tvChildrenAges.splice(i, 1);
                        renderChildrenRows();
                        updateTouristsSummary();
                        tvChildAgePicker.classList.add('hidden');
                    });
                });
                tvChildrenRows.querySelectorAll('.tv-child-age-btn').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        tvChildAgePickerIndex = parseInt(this.dataset.index, 10);
                        tvChildAgePicker.classList.remove('hidden');
                        var grid = document.getElementById('tv-child-age-grid');
                        if (grid && grid.children.length === 0) {
                            [0,2,3,4,5,6,7,8,9,10,11,12,13,14,15].forEach(function(a) {
                                var b = document.createElement('button');
                                b.type = 'button';
                                b.textContent = tvAgeLabels[a] || a;
                                b.dataset.age = a;
                                b.addEventListener('click', function() {
                                    if (tvChildAgePickerIndex >= 0 && tvChildAgePickerIndex < tvChildrenAges.length) {
                                        tvChildrenAges[tvChildAgePickerIndex] = parseInt(this.dataset.age, 10);
                                        renderChildrenRows();
                                        updateTouristsSummary();
                                    }
                                    tvChildAgePicker.classList.add('hidden');
                                });
                                grid.appendChild(b);
                            });
                        }
                    });
                });
                if (tvAddChildBtn) tvAddChildBtn.style.display = tvChildrenAges.length >= 3 ? 'none' : 'block';
            }
            function openTvTouristsPopup() {
                if (!tvTouristsBlock) return;
                tvTouristsBlock.classList.remove('hidden');
                tvTouristsBlock.classList.add('is-open');
                tvTouristsBlock.style.display = 'flex';
                tvTouristsBlock.setAttribute('aria-hidden', 'false');
            }
            function closeTvTouristsPopup() {
                if (!tvTouristsBlock) return;
                tvTouristsBlock.classList.add('hidden');
                tvTouristsBlock.classList.remove('is-open');
                tvTouristsBlock.style.display = 'none';
                tvTouristsBlock.setAttribute('aria-hidden', 'true');
            }
            document.getElementById('tv-tourists-trigger')?.addEventListener('click', function() {
                if (tvTouristsBlock.classList.contains('hidden')) openTvTouristsPopup();
                else closeTvTouristsPopup();
            });
            document.getElementById('tv-adults-minus')?.addEventListener('click', function() {
                if (tvAdultsCount > 1) { tvAdultsCount--; updateTouristsSummary(); }
            });
            document.getElementById('tv-adults-plus')?.addEventListener('click', function() {
                if (tvAdultsCount < 6) { tvAdultsCount++; updateTouristsSummary(); }
            });
            tvAddChildBtn?.addEventListener('click', function() {
                if (tvChildrenAges.length < 3) { tvChildrenAges.push(7); renderChildrenRows(); updateTouristsSummary(); }
            });
            document.getElementById('tv-tourists-apply')?.addEventListener('click', function() {
                if (document.getElementById('tv-remember-tourists')?.checked) {
                    try {
                        localStorage.setItem('tv_tourists', JSON.stringify({ adults: tvAdultsCount, childrenAges: tvChildrenAges }));
                    } catch (e) {}
                }
                closeTvTouristsPopup();
                var w = document.getElementById('tv-results-wrapper');
                if (w && !w.classList.contains('hidden') && typeof performTvSearch === 'function') {
                    performTvSearch(true);
                }
            });
            ['tv-only-charter', 'tv-only-direct'].forEach(function (fid) {
                var el = document.getElementById(fid);
                if (!el) return;
                el.addEventListener('change', function () {
                    if (typeof window.tvLoadFlyAvailability === 'function') {
                        window.__tvFlyDates.key = '';
                        window.tvLoadFlyAvailability();
                    }
                    if (typeof window.tvLoadFlyNights === 'function') {
                        window.__tvFlyNights.key = '';
                        window.tvLoadFlyNights();
                    }
                    if (tvDatePicker && typeof tvDatePicker.redraw === 'function') tvDatePicker.redraw();
                    if (typeof renderTvNightsGrid === 'function') renderTvNightsGrid(true);
                    var w = document.getElementById('tv-results-wrapper');
                    if (w && !w.classList.contains('hidden') && typeof performTvSearch === 'function') {
                        performTvSearch(true);
                    }
                });
            });
            try {
                var saved = localStorage.getItem('tv_tourists');
                if (saved) {
                    var d = JSON.parse(saved);
                    if (d && typeof d.adults === 'number' && d.adults >= 1 && d.adults <= 6) tvAdultsCount = d.adults;
                    if (d && Array.isArray(d.childrenAges)) tvChildrenAges = d.childrenAges.filter(function(a) { var n = parseInt(a,10); return n >= 0 && n <= 17; }).slice(0, 3);
                }
            } catch (e) {}
            renderChildrenRows();
            updateTouristsSummary();

            const cityToDeparture = {
                'москва':'Москва','moscow':'Москва','moskva':'Москва',
                'санкт-петербург':'Санкт-Петербург','saint petersburg':'Санкт-Петербург','st petersburg':'Санкт-Петербург','sankt-peterburg':'Санкт-Петербург','spb':'Санкт-Петербург',
                'казань':'Казань','kazan':'Казань',
                'екатеринбург':'Екатеринбург','yekaterinburg':'Екатеринбург','ekaterinburg':'Екатеринбург',
                'краснодар':'Краснодар','krasnodar':'Краснодар',
                'сочи':'Сочи','sochi':'Сочи',
                'минеральные воды':'Минеральные Воды','mineralnye vody':'Минеральные Воды',
                'ростов-на-дону':'Ростов-на-Дону','rostov':'Ростов-на-Дону',
                'самара':'Самара','samara':'Самара',
                'воронеж':'Воронеж','voronezh':'Воронеж',
                'нижний новгород':'Нижний Новгород','nizhny novgorod':'Нижний Новгород',
                'новосибирск':'Новосибирск','novosibirsk':'Новосибирск',
                'уфа':'Уфа','ufa':'Уфа',
                'волгоград':'Волгоград','volgograd':'Волгоград',
                'астрахань':'Астрахань','astrakhan':'Астрахань',
                'калининград':'Калининград','kaliningrad':'Калининград',
                'мурманск':'Мурманск','murmansk':'Мурманск',
                'симферополь':'Симферополь','simferopol':'Симферополь',
                'минераловодск':'Минеральные Воды','mineralovodsk':'Минеральные Воды'
            };
            async function detectCityByGeolocation() {
                if (!navigator.geolocation) return false;
                return new Promise((resolve) => {
                    navigator.geolocation.getCurrentPosition(
                        async (pos) => {
                            const lat = pos.coords.latitude, lon = pos.coords.longitude;
                            try {
                                const r = await fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lon}&format=json&accept-language=ru`, { headers: { 'Accept': 'application/json' } });
                                const text = await r.text();
                                let j = {};
                                if ((text || '').trim()) { try { j = JSON.parse(text); } catch (e) {} }
                                const addr = j.address || {};
                                const cityRaw = addr.city || addr.town || addr.village || addr.municipality || addr.county || addr.state || '';
                                const cityLower = String(cityRaw).toLowerCase().trim();
                                let matchName = cityToDeparture[cityLower];
                                if (!matchName && cityLower) {
                                    const found = departuresList.find(d => {
                                        if (isBlockedDepartureName(d.name)) return false;
                                        const dn = (d.name || '').toLowerCase();
                                        return dn.includes(cityLower) || cityLower.includes(dn);
                                    });
                                    if (found) matchName = found.name;
                                }
                                if (matchName && !isBlockedDepartureName(matchName)) {
                                    const dep = departuresList.find(d => (d.name || '').toLowerCase() === matchName.toLowerCase());
                                    if (dep) { depSel.value = dep.id; resolve(true); return; }
                                }
                            } catch (_) {}
                            resolve(false);
                            return;
                        },
                        () => resolve(false),
                        { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 }
                    );
                });
            }
            const geoBanner = document.getElementById('geo-banner');
            const geoAllow = document.getElementById('geo-allow');
            const geoDeny = document.getElementById('geo-deny');
            const geoDetectBtn = document.getElementById('geo-detect-btn');
            if (geoBanner && !sessionStorage.geoAnswered) {
                geoAllow?.addEventListener('click', async () => {
                    geoBanner.querySelector('p').textContent = 'Определяем город...';
                    geoAllow.disabled = true;
                    await detectCityByGeolocation();
                    var hpGeo = resolveHomePopularDepartureId();
                    if (hpGeo) loadHomeShowcaseShelves(hpGeo);
                    sessionStorage.geoAnswered = 'allowed';
                    geoBanner.classList.add('hidden');
                });
                geoDeny?.addEventListener('click', () => {
                    sessionStorage.geoAnswered = 'denied';
                    geoBanner.classList.add('hidden');
                });
            } else {
                geoBanner?.classList.add('hidden');
            }
            geoDetectBtn?.addEventListener('click', async () => {
                geoDetectBtn.textContent = 'Определяю...';
                geoDetectBtn.disabled = true;
                let set = false;
                try {
                    const r = await fetch((typeof TOUR_DETAIL_BASE !== 'undefined' && TOUR_DETAIL_BASE ? '' : '') + '/backend/api/geo.php', { method: 'GET' });
                    const text = await r.text();
                    let j = {};
                    if ((text || '').trim()) { try { j = JSON.parse(text); } catch (e) {} }
                    if (j.success && j.city && departuresList.length) {
                        const cityLower = String(j.city).toLowerCase().trim();
                        if (!isBlockedDepartureName(j.city)) {
                            const found = departuresList.find(d => {
                                if (isBlockedDepartureName(d.name)) return false;
                                const n = (d.name || '').toLowerCase();
                                return n === cityLower || n.includes(cityLower) || cityLower.includes(n);
                            });
                            if (found) { depSel.value = found.id; set = true; }
                        }
                    }
                } catch (_) {}
                if (!set) await detectCityByGeolocation();
                var hpBtn = resolveHomePopularDepartureId();
                if (hpBtn) loadHomeShowcaseShelves(hpBtn);
                geoDetectBtn.textContent = '📍 Определить';
                geoDetectBtn.disabled = false;
            });
            depSel?.addEventListener('change', function() {
                var v = parseInt(String(this.value || ''), 10);
                if (!v) return;
                var opt = this.options[this.selectedIndex];
                var depName = opt ? (opt.textContent || '').trim() : '';
                if (window.THDeparturePreference && typeof window.THDeparturePreference.save === 'function') {
                    window.THDeparturePreference.save(v, depName);
                }
                if (window.__tvRestoringFromBack) {
                    loadHomeShowcaseShelves(v);
                    return;
                }
                if (countrySel && countrySel.value) {
                    try { countrySel.dispatchEvent(new Event('change', { bubbles: true })); } catch (e) {}
                }
                var resultsWrap = document.getElementById('tv-results-wrapper');
                if (resultsWrap && !resultsWrap.classList.contains('hidden') && typeof performTvSearch === 'function') {
                    performTvSearch();
                }
                loadHomeShowcaseShelves(v);
            });
            window.addEventListener('th-departure-saved', function(ev) {
                var id = ev && ev.detail && ev.detail.id;
                if (!id) return;
                window.setTimeout(function() {
                    loadHomeShowcaseShelves(parseInt(String(id), 10));
                }, 0);
            });

            var pfRoot = document.body;
            if (window.THTourPostFilters && typeof window.THTourPostFilters.mount === 'function') {
                tvPostFiltersCtrl = window.THTourPostFilters.mount({
                    root: pfRoot,
                    onChange: function () {
                        if (tvHotelsBeforeBudgetFilter && tvHotelsBeforeBudgetFilter.length) tvApplyClientFiltersAndRender();
                    }
                });
            }

            (function bindResultsPfSheet() {
                var sheet = document.getElementById('th-results-pf-sheet');
                var openBtn = document.getElementById('th-rsd-open-filters');
                var bodySlot = document.getElementById('th-results-pf-sheet-body');
                var sidebar = document.getElementById('tv-results-sidebar');
                var layout = document.querySelector('.tv-results-layout');
                if (!sheet || !openBtn || !bodySlot || !sidebar) return;
                var homeParent = sidebar.parentNode;
                var homeNext = sidebar.nextSibling;

                function openSheet() {
                    if (sidebar.parentNode !== bodySlot) {
                        bodySlot.appendChild(sidebar);
                    }
                    sheet.classList.remove('hidden');
                    sheet.classList.add('is-open', 'th-sheet--open');
                    sheet.setAttribute('aria-hidden', 'false');
                    openBtn.setAttribute('aria-expanded', 'true');
                    document.body.classList.add('th-rsd-sheet-open');
                }
                function closeSheet() {
                    sheet.classList.add('hidden');
                    sheet.classList.remove('is-open', 'th-sheet--open');
                    sheet.setAttribute('aria-hidden', 'true');
                    openBtn.setAttribute('aria-expanded', 'false');
                    document.body.classList.remove('th-rsd-sheet-open');
                    if (homeParent && sidebar.parentNode === bodySlot) {
                        if (homeNext && homeNext.parentNode === homeParent) {
                            homeParent.insertBefore(sidebar, homeNext);
                        } else if (layout) {
                            layout.insertBefore(sidebar, layout.firstChild);
                        } else {
                            homeParent.appendChild(sidebar);
                        }
                    }
                    if (window.THMobile && typeof window.THMobile.sync === 'function') window.THMobile.sync();
                }
                openBtn.addEventListener('click', openSheet);
                sheet.querySelectorAll('[data-th-rsd-close]').forEach(function (el) {
                    el.addEventListener('click', closeSheet);
                });
                window.__thCloseResultsPfSheet = closeSheet;
            })();

            if (typeof tryRestoreTvMainSearchFromSnapshot === 'function') {
                var hasRestoreParam = false;
                try {
                    var trp = new URLSearchParams(window.location.search).get('tv_restore');
                    hasRestoreParam = trp === '1' || String(trp || '').toLowerCase() === 'true';
                } catch (eTrp) {}
                if (!hasRestoreParam && !window.__tvRestoringFromBack) {
                    var dispDates = document.getElementById('tv-sc-dates-display');
                    var datesUnset = !dispDates || !dispDates.textContent.trim() || dispDates.textContent.trim() === 'Даты';
                    if (datesUnset) applyDefaultDateWindow();
                }
                var restoredFromSnap = tryRestoreTvMainSearchFromSnapshot(function() {
                    updateTouristsSummary();
                    renderChildrenRows();
                    showTvResultsChrome();
                    if (tvPostFiltersCtrl && tvHotelsBeforeBudgetFilter.length) {
                        tvPostFiltersCtrl.updateFromHotels(tvHotelsBeforeBudgetFilter);
                    }
                });
                if (hasRestoreParam && window.TourSessionManager && typeof window.TourSessionManager.restore === 'function') {
                    setTimeout(function() {
                        window.TourSessionManager.restore();
                    }, restoredFromSnap ? 200 : 500);
                } else if (!hasRestoreParam) {
                    window.__tvRestoringFromBack = false;
                }
            }
            if (window.THTourSearchWizard && typeof window.THTourSearchWizard.refreshSummary === 'function') {
                window.THTourSearchWizard.refreshSummary();
            }
        });

        let tvLastResults = [];
        /** Сырые отели с последнего поиска (до клиентского фильтра по бюджету) */
        let tvHotelsBeforeBudgetFilter = [];
        let tvPostFiltersCtrl = null;
        let tvSearchAltNightsBanner = false;
        const TV_PAGE_SIZE = 25;
        let tvDisplayedCount = 0;
        let tvCompareMap = {};
        const TV_COMPARE_MAX = 3;
        window.__thSearchMode = window.__thSearchMode || 'tours';
        /** Tourvisor: departureId=99 («Без перелёта») — цена только за проживание, без авиа. */
        var TH_HOTEL_DEPARTURE_ID = 99;
        var TH_HOTEL_DEPARTURE_NAME = 'Без перелёта';
        /** Прямые рейсы приоритетно: Таиланд(2), Шри-Ланка(12), Вьетнам(16/18), Фукуок(16104). Без UI-текста. */
        var TH_DIRECT_PRIORITY_COUNTRY_IDS = { '2': 1, '12': 1, '16': 1, '18': 1, '16104': 1 };
        function thIsDirectPriorityCountry(countryId) {
            var id = parseInt(String(countryId != null ? countryId : ''), 10);
            if (!id) return false;
            return !!TH_DIRECT_PRIORITY_COUNTRY_IDS[String(id)];
        }
        function tvFlightMetaLookup(tourId, depCity) {
            if (!tourId) return null;
            if (typeof thFlightsCacheGet === 'function') {
                var cached = thFlightsCacheGet(String(tourId), depCity);
                if (cached) return cached;
            }
            if (!window.__mainFlightsByTourId) return null;
            var cityKey = String(depCity || '').trim().toLowerCase().replace(/ё/g, 'е');
            return window.__mainFlightsByTourId[String(tourId) + '@' + cityKey]
                || window.__mainFlightsByTourId[String(tourId)]
                || null;
        }
        function tvHotelFlightIsDirect(h) {
            if (!h) return false;
            if (h._thDirectWave) return true;
            var tour = (typeof tvHotelCheapestTour === 'function')
                ? tvHotelCheapestTour(h)
                : ((h.tours && h.tours[0]) ? h.tours[0] : {});
            var tourId = tour && (tour.id != null && tour.id !== '') ? String(tour.id) : '';
            if (!tourId) return false;
            var depCity = (typeof tvActiveDeparture === 'function' ? tvActiveDeparture().name : '') || 'Самара';
            var meta = tvFlightMetaLookup(tourId, depCity);
            return !!(meta && meta.direct === true);
        }
        function tvSortHotelsDirectFlightFirst(list) {
            if (!Array.isArray(list) || !list.length) return list || [];
            return list.slice().sort(function (a, b) {
                var da = tvHotelFlightIsDirect(a) ? 0 : 1;
                var db = tvHotelFlightIsDirect(b) ? 0 : 1;
                if (da !== db) return da - db;
                return (tvHotelListPrice(a) || 0) - (tvHotelListPrice(b) || 0);
            });
        }
        function tvMaybeResortDirectPriority() {
            if (thIsHotelSearchMode()) return;
            var cid = document.getElementById('tv-country') && document.getElementById('tv-country').value;
            if (!thIsDirectPriorityCountry(cid)) return;
            if (window.__tvSearchFlightFlags && (window.__tvSearchFlightFlags.onlyDirect || window.__tvSearchFlightFlags.onlyCharter)) return;
            if (!Array.isArray(tvHotelsBeforeBudgetFilter) || !tvHotelsBeforeBudgetFilter.length) return;
            var before = tvHotelsBeforeBudgetFilter.map(function (h) { return tvHotelKey(h); }).join(',');
            tvHotelsBeforeBudgetFilter = tvSortHotelsDirectFlightFirst(tvHotelsBeforeBudgetFilter);
            var after = tvHotelsBeforeBudgetFilter.map(function (h) { return tvHotelKey(h); }).join(',');
            if (before === after) return;
            var list = tvHotelsBeforeBudgetFilter.slice();
            list = list.filter(function (h) { return tvHotelListPrice(h) > 0 && !h._catalogOnly; });
            if (window.THTourPostFilters && tvPostFiltersCtrl) {
                list = window.THTourPostFilters.filterHotels(list, tvPostFiltersCtrl.state, { getPrice: tvHotelListPrice });
            }
            list = applyBudgetFilterToHotels(list);
            tvLastResults = list;
        }
        function tvHotelKey(h) {
            return h && h.id != null ? String(h.id) : '';
        }
        var TV_MAIN_SNAPSHOT_KEY = 'tv_main_search_snapshot_v1';
        var TV_MAIN_SNAPSHOT_TTL_MS = 45 * 60 * 1000;

        function saveTvMainSearchSnapshot() {
            try {
                if (!tvLastResults || tvLastResults.length === 0) return;
                var sortEl = document.getElementById('tv-sort');
                var snap = {
                    ts: Date.now(),
                    hotels: tvLastResults,
                    displayed: tvDisplayedCount,
                    sort: (sortEl && sortEl.value) ? sortEl.value : 'price-asc',
                    flights: window.__mainFlightsByTourId || {},
                    adults: tvAdultsCount,
                    childAges: tvChildrenAges ? tvChildrenAges.slice() : [],
                    scrollY: window.scrollY || window.pageYOffset || 0
                };
                sessionStorage.setItem(TV_MAIN_SNAPSHOT_KEY, JSON.stringify(snap));
            } catch (e) {}
        }
        /* Экспорт для overlay и session-manager */
        window.saveTvMainSearchSnapshot = saveTvMainSearchSnapshot;

        function scrollMainResultsIntoView(behavior) {
            var w = document.getElementById('tv-results-wrapper');
            if (!w || w.classList.contains('hidden')) return false;
            w.scrollIntoView({ behavior: behavior || 'smooth', block: 'start' });
            return true;
        }

        function tryRestoreTvMainSearchFromSnapshot(onAfter) {
            try {
                var sp = new URLSearchParams(window.location.search);
                var tr = sp.get('tv_restore');
                if (tr !== '1' && String(tr || '').toLowerCase() !== 'true') return false;
                var raw = sessionStorage.getItem(TV_MAIN_SNAPSHOT_KEY);
                if (!raw) return false;
                var p = JSON.parse(raw);
                if (!p || !Array.isArray(p.hotels) || p.hotels.length === 0) return false;
                if (p.ts && (Date.now() - p.ts > TV_MAIN_SNAPSHOT_TTL_MS)) return false;
                window.__tvRestoringFromBack = true;
                tvLastResults = p.hotels;
                tvHotelsBeforeBudgetFilter = Array.isArray(p.hotels) ? p.hotels.slice() : [];
                tvDisplayedCount = Math.min(Math.max(parseInt(p.displayed, 10) || TV_PAGE_SIZE, 1), tvLastResults.length);
                if (typeof p.adults === 'number' && p.adults >= 1 && p.adults <= 9) tvAdultsCount = p.adults;
                if (Array.isArray(p.childAges)) {
                    tvChildrenAges = p.childAges.filter(function(a) {
                        var n = parseInt(a, 10);
                        return !isNaN(n) && n >= 0 && n <= 17;
                    }).slice(0, 3);
                }
                window.__mainFlightsByTourId = (p.flights && typeof p.flights === 'object') ? p.flights : {};
                var sortEl2 = document.getElementById('tv-sort');
                if (sortEl2 && p.sort) sortEl2.value = p.sort;
                var wrapper = document.getElementById('tv-results-wrapper');
                if (wrapper) wrapper.classList.remove('hidden');
                applyTvSort();
                updateTvLoadMoreButton();
                var rC = document.getElementById('tv-result-count');
                if (rC) rC.textContent = String(tvLastResults.length);
                if (typeof onAfter === 'function') onAfter();
                sp.delete('tv_restore');
                var np = sp.toString();
                var clean = window.location.pathname + (np ? ('?' + np) : '') + window.location.hash;
                if (history.replaceState) history.replaceState(null, '', clean);
                if (!(window.TourSessionManager && window.TourSessionManager.hasPendingScrollRestore && window.TourSessionManager.hasPendingScrollRestore())) {
                    setTimeout(function() {
                        var scrolled = scrollMainResultsIntoView('smooth');
                        if (!scrolled && p.scrollY > 80) {
                            window.scrollTo({ top: p.scrollY, behavior: 'smooth' });
                        }
                        window.__tvRestoringFromBack = false;
                    }, 150);
                }
                return true;
            } catch (e) {
                return false;
            }
        }

        window.addEventListener('pageshow', function(ev) {
            if (!ev.persisted) return;
            if (window.TourSessionManager && window.TourSessionManager.hasPendingScrollRestore && window.TourSessionManager.hasPendingScrollRestore()) return;
            var w = document.getElementById('tv-results-wrapper');
            if (!w || w.classList.contains('hidden')) return;
            requestAnimationFrame(function() {
                scrollMainResultsIntoView('auto');
            });
        });

        const TV_LOADER_CIRCLE = 2 * Math.PI * 52;
        let tvLoaderRotateInterval = null;
        const TV_LOADER_STAGES = {
            cache: { label: 'Проверяем кэш...', sub: 'Если нашли — покажем сразу' },
            search: { label: 'Ищем у операторов...', sub: 'Уточняем актуальные цены и места' },
            paint: { label: 'Собираем карточки...', sub: 'Почти готово' },
            done: { label: 'Готово!', sub: 'Показываем результаты' }
        };
        const TV_LOADER_WAIT_MESSAGES = [
            'Сверяем цены по направлению...',
            'Проверяем доступность мест...',
            'Подбираем лучшие варианты...',
            'Ещё секунду — уточняем детали...'
        ];
        let tvLoaderSlowTimer = null;
        function tvLoaderShow() {
            const el = document.getElementById('tv-search-loader');
            const msg = document.getElementById('tv-loader-msg');
            if (el) { el.classList.add('active'); el.setAttribute('aria-hidden', 'false'); }
            if (msg) msg.classList.remove('done');
            if (tvLoaderRotateInterval) { clearInterval(tvLoaderRotateInterval); tvLoaderRotateInterval = null; }
            if (tvLoaderSlowTimer) { clearTimeout(tvLoaderSlowTimer); tvLoaderSlowTimer = null; }
            const instant = document.getElementById('tv-loader-instant');
            if (instant) instant.textContent = '';
            tvLoaderSetStage('cache');
        }
        function tvLoaderSetStage(stage) {
            const conf = TV_LOADER_STAGES[stage] || TV_LOADER_STAGES.cache;
            const fill = document.getElementById('tv-loader-fill');
            const percentEl = document.getElementById('tv-loader-percent');
            const msg = document.getElementById('tv-loader-msg');
            const sub = document.getElementById('tv-loader-sub');
            const ring = document.querySelector('.tv-search-loader-ring');
            if (ring) ring.classList.toggle('is-done', stage === 'done');
            if (fill) {
                // Indeterminate: keep arc partial; on done fill circle
                fill.style.strokeDashoffset = stage === 'done'
                    ? '0'
                    : String(TV_LOADER_CIRCLE * 0.72);
            }
            if (percentEl) percentEl.textContent = stage === 'done' ? '✓' : '···';
            if (msg) {
                msg.textContent = conf.label;
                if (stage === 'done') msg.classList.add('done');
                else msg.classList.remove('done');
            }
            if (sub) sub.textContent = conf.sub;
            if (stage === 'search' && !tvLoaderRotateInterval) {
                let idx = 0;
                tvLoaderRotateInterval = setInterval(function() {
                    if (!document.getElementById('tv-search-loader') || !document.getElementById('tv-search-loader').classList.contains('active')) {
                        if (tvLoaderRotateInterval) { clearInterval(tvLoaderRotateInterval); tvLoaderRotateInterval = null; }
                        return;
                    }
                    const subEl = document.getElementById('tv-loader-sub');
                    if (subEl) subEl.textContent = TV_LOADER_WAIT_MESSAGES[idx % TV_LOADER_WAIT_MESSAGES.length];
                    idx++;
                }, 2400);
            }
        }
        /** @deprecated use tvLoaderSetStage — kept for callers that still pass percent */
        function tvLoaderSetProgress(percent, text, subText) {
            if (percent >= 100) tvLoaderSetStage('done');
            else if (percent >= 50) tvLoaderSetStage('search');
            else tvLoaderSetStage('cache');
            const msg = document.getElementById('tv-loader-msg');
            const sub = document.getElementById('tv-loader-sub');
            if (msg && text) msg.textContent = text;
            if (sub && subText !== undefined) sub.textContent = subText;
        }
        function tvLoaderHide() {
            const el = document.getElementById('tv-search-loader');
            if (tvLoaderRotateInterval) { clearInterval(tvLoaderRotateInterval); tvLoaderRotateInterval = null; }
            if (tvLoaderSlowTimer) { clearTimeout(tvLoaderSlowTimer); tvLoaderSlowTimer = null; }
            if (el) { el.classList.remove('active'); el.setAttribute('aria-hidden', 'true'); }
        }

        function tvShowLiveRefine(show, text) {
            var el = document.getElementById('tv-live-refine');
            var txt = document.getElementById('tv-live-refine-text');
            if (show) {
                window.__thDeferHeavyCards = true;
                if (el) {
                    if (txt) txt.textContent = text || 'Уточняем цены у операторов…';
                    el.classList.remove('hidden');
                }
                return;
            }
            if (el) el.classList.add('hidden');
            var wasDeferred = !!window.__thDeferHeavyCards;
            window.__thDeferHeavyCards = false;
            if (window.THTourCard && typeof window.THTourCard.scheduleCarouselHydrate === 'function') {
                var resEl = document.getElementById('tv-search-results');
                window.THTourCard.scheduleCarouselHydrate(resEl || document, 800);
            }
            if (wasDeferred && Array.isArray(tvLastResults) && tvLastResults.length && typeof applyTvSort === 'function') {
                setTimeout(function () {
                    if (!window.__thDeferHeavyCards) applyTvSort({});
                }, 400);
            }
        }

        function tvNormalizeHotelsList(raw) {
            if (Array.isArray(raw)) return raw;
            if (raw && Array.isArray(raw.hotels)) return raw.hotels;
            if (raw && Array.isArray(raw.data)) return raw.data;
            return [];
        }

        function tvActiveDeparture() {
            if (window.THDeparturePreference && typeof window.THDeparturePreference.getActive === 'function') {
                return window.THDeparturePreference.getActive();
            }
            var el = document.getElementById('tv-departure');
            if (el && el.value) {
                var opt = el.options[el.selectedIndex];
                return {
                    id: String(el.value).trim(),
                    name: (opt && (opt.textContent || '').trim()) || ((window.TH_DEPARTURE && window.TH_DEPARTURE.name) || 'Самара')
                };
            }
            return {
                id: String((window.TH_DEPARTURE && window.TH_DEPARTURE.id) || '7'),
                name: (window.TH_DEPARTURE && window.TH_DEPARTURE.name) || 'Самара'
            };
        }

        /** Город вылета для API. «Отели» → dep=99 (только проживание, без авиа). */
        function tvEffectiveSearchDeparture() {
            if (typeof thIsHotelSearchMode === 'function' && thIsHotelSearchMode()) {
                return { id: String(TH_HOTEL_DEPARTURE_ID), name: TH_HOTEL_DEPARTURE_NAME };
            }
            return tvActiveDeparture();
        }

        function tvSleep(ms) {
            return new Promise(function (resolve) { setTimeout(resolve, ms); });
        }

        /**
         * Live по доке Tourvisor: search → wait 3с → status → results.
         * Early paint с ~30% / ~3.5с после старта poll; дальше фоновая догрузка.
         * Один searchId = один слот суточной квоты search.
         */
        async function tvProgressiveLiveSearch(params, opts) {
            opts = opts || {};
            var gen = opts.gen;
            var onPartial = typeof opts.onPartial === 'function' ? opts.onPartial : null;
            var softMaxMs = Math.max(8000, Math.min(25000, opts.softMaxMs || 16000));
            var earlyProgress = Math.max(20, Math.min(80, opts.earlyProgress || 30));
            var earlyLimit = Math.max(40, Math.min(200, opts.earlyLimit || 80));
            var finalLimit = Math.max(80, Math.min(500, opts.finalLimit || 200));
            var isStale = function () {
                return gen != null && gen !== window.__tvSearchGen;
            };
            if (isStale()) return { success: false, aborted: true, data: [] };

            var started = await tvFetch('search', params);
            if (isStale()) return { success: false, aborted: true, data: [] };
            if (!started || !started.success || !started.searchId) {
                return {
                    success: false,
                    data: [],
                    error: (started && started.error) ? started.error : 'Не удалось запустить поиск'
                };
            }
            var sid = parseInt(String(started.searchId), 10);
            if (!sid) {
                return { success: false, data: [], error: 'searchId missing' };
            }

            // Дока: первых результатов обычно нет раньше 3–5с
            await tvSleep(2800);
            if (isStale()) return { success: false, aborted: true, data: [] };

            var paintedPartial = false;
            var lastPaintCount = 0;
            var t0 = Date.now();
            var lastProgress = 0;
            var done = false;

            while ((Date.now() - t0) < softMaxMs) {
                if (isStale()) return { success: false, aborted: true, data: [] };
                var st = await tvFetch('status', { searchId: String(sid), operatorStatus: '1' });
                if (isStale()) return { success: false, aborted: true, data: [] };
                if (!st || !st.success) {
                    var err = String((st && st.error) || '');
                    if (/429|rate/i.test(err)) {
                        await tvSleep(4000);
                        continue;
                    }
                    if (/timeout/i.test(err)) {
                        // не рвём цикл сразу — пробуем забрать накопленное
                        break;
                    }
                    break;
                }
                var sd = st.data || {};
                var status = String(sd.status || '').toLowerCase();
                lastProgress = parseInt(String(sd.progress != null ? sd.progress : 0), 10) || 0;
                if (status === 'error') {
                    return { success: false, data: [], error: 'Search error', searchId: sid };
                }

                var elapsed = Date.now() - t0;
                var shouldTryResults = onPartial && (
                    lastProgress >= earlyProgress
                    || elapsed >= 3500
                    || (paintedPartial && lastProgress >= earlyProgress - 5)
                );
                if (shouldTryResults) {
                    var early = await tvFetch('results', {
                        searchId: String(sid),
                        limit: String(earlyLimit)
                    }, { persist: false, slim: true });
                    if (isStale()) return { success: false, aborted: true, data: [] };
                    var earlyList = tvNormalizeHotelsList(early && early.data);
                    if (early && early.success && earlyList.length > lastPaintCount) {
                        lastPaintCount = earlyList.length;
                        paintedPartial = true;
                        try {
                            onPartial(earlyList, { partial: true, progress: lastProgress, searchId: sid });
                        } catch (ePart) {}
                    }
                }

                if (status === 'completed' || lastProgress >= 100) {
                    done = true;
                    break;
                }
                await tvSleep(paintedPartial ? 1500 : 1800);
            }

            if (isStale()) return { success: false, aborted: true, data: [] };
            var fin = await tvFetch('results', {
                searchId: String(sid),
                limit: String(finalLimit)
            }, { slim: true });
            if (isStale()) return { success: false, aborted: true, data: [] };
            var finList = tvNormalizeHotelsList(fin && fin.data);
            if (fin && fin.success && finList.length) {
                fin.data = finList;
                fin.searchId = sid;
                fin.partial = !done;
                fin.progress = lastProgress;
                return fin;
            }
            if (paintedPartial && lastPaintCount > 0) {
                return {
                    success: true,
                    data: [],
                    searchId: sid,
                    partial: true,
                    progress: lastProgress,
                    keptPartial: true
                };
            }
            return {
                success: false,
                data: [],
                searchId: sid,
                error: (fin && fin.error) ? fin.error : 'No results',
                progress: lastProgress
            };
        }

        function tvCountPlausibleHotels(list) {
            if (!Array.isArray(list)) return 0;
            return list.filter(function (h) {
                return tvHotelListPrice(h) > 0 && !h._catalogOnly;
            }).length;
        }
        function tvBuildAltNightsParams(baseParams, origFrom, origTo) {
            origFrom = parseInt(origFrom, 10) || 6;
            origTo = parseInt(origTo, 10) || 9;
            if (origFrom === 6 && origTo === 9) {
                return Object.assign({}, baseParams, { nightsFrom: 5, nightsTo: 10 });
            }
            if (origFrom >= 10) {
                var relaxedFrom = Math.max(7, origFrom - 2);
                if (relaxedFrom < origFrom) {
                    return Object.assign({}, baseParams, { nightsFrom: relaxedFrom, nightsTo: origTo });
                }
            }
            return null;
        }
        function tvMaybeShowAltNightsBanner(list, origFrom, origTo, forced) {
            if (forced) {
                tvShowAltNightsBanner(true, origFrom, origTo);
                return;
            }
            origFrom = parseInt(origFrom, 10) || 0;
            origTo = parseInt(origTo, 10) || 0;
            if (origFrom < 10 || !Array.isArray(list) || !list.length) return;
            var hasExact = list.some(function (h) {
                var t = (h && h.tours && h.tours[0]) ? h.tours[0] : null;
                var n = t ? (parseInt(String(t.nights || ''), 10) || 0) : 0;
                return n >= origFrom && n <= origTo;
            });
            if (!hasExact) tvShowAltNightsBanner(true, origFrom, origTo);
        }

        function tvYmdAddDays(ymd, days) {
            var ts = Date.parse(String(ymd || '').slice(0, 10) + 'T12:00:00');
            if (!isFinite(ts)) return ymd;
            var d = new Date(ts + (parseInt(days, 10) || 0) * 86400000);
            return d.getFullYear() + '-'
                + String(d.getMonth() + 1).padStart(2, '0') + '-'
                + String(d.getDate()).padStart(2, '0');
        }
        function tvFmtYmdShort(ymd) {
            if (!ymd) return '';
            try {
                var p = String(ymd).slice(0, 10).split('-');
                if (p.length === 3) return parseInt(p[2], 10) + '.' + parseInt(p[1], 10);
            } catch (e) {}
            return String(ymd);
        }
        function tvShowCharterDateShiftBanner(show, origFrom, origTo, newFrom, newTo) {
            var el = document.getElementById('tv-search-alt-banner');
            if (!el) return;
            if (!show) {
                if (el.dataset.charterShift === '1') {
                    el.classList.add('hidden');
                    el.textContent = '';
                    delete el.dataset.charterShift;
                }
                return;
            }
            el.textContent = 'На выбранные даты (' + tvFmtYmdShort(origFrom) + '–' + tvFmtYmdShort(origTo)
                + ') прямых рейсов нет — показаны ближайшие (' + tvFmtYmdShort(newFrom) + '–' + tvFmtYmdShort(newTo) + ').';
            el.dataset.charterShift = '1';
            el.classList.remove('hidden');
        }
        async function tvTryNearestDirectSearch(baseParams, dateFrom, dateTo, opts) {
            opts = opts || {};
            var shifts = [3, -3, 7, -7, 14, -14, 21, -21];
            var fetchOpts = opts.cacheOpts || { cacheScope: 'country_page', slim: true };
            for (var si = 0; si < shifts.length; si++) {
                var shift = shifts[si];
                var newFrom = tvYmdAddDays(dateFrom, shift);
                var newTo = tvYmdAddDays(dateTo, shift);
                var p = Object.assign({}, baseParams, { dateFrom: newFrom, dateTo: newTo, onlyDirect: '1' });
                delete p.onlyCharter;
                var r = await tvFetch('search-cached', p, fetchOpts);
                if (r && r.success && Array.isArray(r.data) && r.data.length > 0 && tvCountPlausibleHotels(r.data) > 0) {
                    return { result: r, dateFrom: newFrom, dateTo: newTo, shiftDays: shift };
                }
                if (opts.tryLive && typeof tvProgressiveLiveSearch === 'function') {
                    var rLive = await tvProgressiveLiveSearch(p, {
                        gen: opts.gen,
                        earlyProgress: 30,
                        softMaxMs: 12000
                    });
                    if (rLive && rLive.success && Array.isArray(rLive.data) && rLive.data.length > 0
                        && tvCountPlausibleHotels(rLive.data) > 0) {
                        return { result: rLive, dateFrom: newFrom, dateTo: newTo, shiftDays: shift };
                    }
                }
            }
            return null;
        }
        async function performTvSearch(manual, opts) {
            opts = opts || {};
            var softSearch = !!opts.soft;
            if (window.__tvRestoringFromBack && !manual && !softSearch) return;
            if (manual) window.__tvRestoringFromBack = false;
            if (window.THLeadCapture && !softSearch) window.THLeadCapture.reachGoal('search_start');
            const depActive = tvEffectiveSearchDeparture();
            const dep = depActive.id || document.getElementById('tv-departure')?.value || '7';
            const country = document.getElementById('tv-country')?.value;
            if (!country) { alert('Выберите страну'); return; }
            
            let nFrom = typeof tvNightsFrom !== 'undefined' ? tvNightsFrom : 6;
            let nTo = typeof tvNightsTo !== 'undefined' ? tvNightsTo : 9;
            const origNFrom = nFrom;
            const origNTo = nTo;
            if (nTo < nFrom) nTo = nFrom;
            
            // Туристы: взрослые и возрасты детей (tvAdultsCount / tvChildrenAges — общие var выше)
            let adults = Math.max(1, Math.min(9, parseInt(tvAdultsCount, 10) || 2));
            let childs = tvChildsParam();
            var resolvedDates = tvResolveSearchDatesYmd();
            let dateFrom = resolvedDates.dateFrom;
            let dateTo = resolvedDates.dateTo;
            
            const params = new URLSearchParams({
                type: 'search',
                departureId: dep,
                countryId: country,
                dateFrom, dateTo,
                nightsFrom: nFrom || 6,
                nightsTo: nTo || 9,
                adults,
                currency: 'RUB'
            });
            if (childs) params.set('childs', childs);
            const meal = document.getElementById('tv-meal')?.value;
            if (meal) params.set('meal', meal);
            const category = document.getElementById('tv-category')?.value;
            if (category) params.set('hotelCategory', category);
            const region = document.getElementById('tv-region')?.value;
            if (region) params.set('regionIds', region);
            if (typeof tvSelectedServiceIds !== 'undefined' && tvSelectedServiceIds.length > 0) {
                params.set('hotelServices', tvSelectedServiceIds.join(','));
            }
            var hotelModeSearch = thIsHotelSearchMode();
            var onlyCharterOn = !hotelModeSearch && !!(document.getElementById('tv-only-charter') && document.getElementById('tv-only-charter').checked);
            var onlyDirectOn = !hotelModeSearch && !!(document.getElementById('tv-only-direct') && document.getElementById('tv-only-direct').checked);
            var charterDirectMode = onlyCharterOn || onlyDirectOn;
            var preferDirectFirst = !hotelModeSearch && !charterDirectMode && thIsDirectPriorityCountry(country);
            /* «Только чартер» = только прямые рейсы (onlyDirect), не onlyCharter в API */
            if (charterDirectMode) params.set('onlyDirect', '1');
            window.__tvSearchFlightFlags = {
                onlyCharter: onlyCharterOn,
                onlyDirect: charterDirectMode,
                preferDirect: preferDirectFirst
            };

            const wrapper = document.getElementById('tv-results-wrapper');
            const resultsDiv = document.getElementById('tv-search-results');
            showTvResultsChrome();
            tvDisplayedCount = 0;
            tvHotelsBeforeBudgetFilter = [];
            tvLastResults = [];
            tvShowAltNightsBanner(false);
            tvShowCharterDateShiftBanner(false);
            if (typeof thFlightLoadResetAll === 'function') thFlightLoadResetAll();
            window.__thFlightsLoadGen = (window.__thFlightsLoadGen || 0) + 1;
            window.__thDeferHeavyCards = true;
            window.__tvSearchBusy = true;
            if (window.THTourCard && typeof window.THTourCard.clearCarouselHydrateQueue === 'function') {
                window.THTourCard.clearCarouselHydrateQueue();
            }
            tvShowLiveRefine(false);
            /* defer снова: tvShowLiveRefine(false) сбрасывает флаг — держим до конца live */
            window.__thDeferHeavyCards = true;
            if (tvPostFiltersCtrl && typeof tvPostFiltersCtrl.reset === 'function') tvPostFiltersCtrl.reset();
            tvShowResultsSkeleton();
            if (wrapper) wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });

            const countryNameForCache = document.getElementById('tv-country')?.selectedOptions?.[0]?.textContent?.trim() || '';
            const cacheParams = {
                departureId: dep,
                countryId: country,
                countryName: countryNameForCache,
                dateFrom, dateTo,
                nightsFrom: nFrom || 6,
                nightsTo: nTo || 9,
                adults
            };
            if (childs) cacheParams.childs = childs;
            if (meal) cacheParams.meal = meal;
            if (category) cacheParams.hotelCategory = category;
            if (region) cacheParams.regionIds = region;
            if (typeof tvSelectedServiceIds !== 'undefined' && tvSelectedServiceIds.length > 0) {
                cacheParams.hotelServices = tvSelectedServiceIds.join(',');
            }
            if (charterDirectMode) cacheParams.onlyDirect = '1';
            var earlyDirectHotels = [];
            var usedCharterDateShift = false;

            function tvMergeHotelsDirectFirst(directList, allList) {
                var byId = {};
                var order = [];
                function add(h, prefer) {
                    if (!h) return;
                    var id = tvHotelKey(h);
                    if (!id) return;
                    if (byId[id]) {
                        if (prefer && !byId[id]._thDirectWave) {
                            h._thDirectWave = true;
                            byId[id] = h;
                        }
                        return;
                    }
                    if (prefer) h._thDirectWave = true;
                    byId[id] = h;
                    order.push(id);
                }
                (directList || []).forEach(function (h) { add(h, true); });
                (allList || []).forEach(function (h) { add(h, false); });
                var directs = [];
                var rest = [];
                order.forEach(function (id) {
                    var h = byId[id];
                    if (h && h._thDirectWave) directs.push(h);
                    else if (h) rest.push(h);
                });
                return directs.concat(rest);
            }
            function tvPaintSearchHotels(list, logLabel, paintOpts) {
                paintOpts = paintOpts || {};
                if (typeof paintOpts.fromCache === 'boolean') {
                    window.__tvSearchResultsFromCache = paintOpts.fromCache;
                }
                var raw = Array.isArray(list) ? list.slice() : [];
                tvHotelsBeforeBudgetFilter = raw.filter(function (h) {
                    return tvHotelListPrice(h) > 0 && !h._catalogOnly;
                });
                if (preferDirectFirst) {
                    tvHotelsBeforeBudgetFilter = tvSortHotelsDirectFlightFirst(tvHotelsBeforeBudgetFilter);
                }
                if (tvPostFiltersCtrl && typeof tvPostFiltersCtrl.updateFromHotels === 'function') {
                    tvPostFiltersCtrl.updateFromHotels(tvHotelsBeforeBudgetFilter);
                }
                tvApplyClientFiltersAndRender();
                showTvResultsChrome();
                if (logLabel) console.log(logLabel, 'color: #22c55e', tvLastResults.length, thIsHotelSearchMode() ? 'отелей' : 'туров');
            }

            function tvBackgroundRefreshPrices() {
                // Пользовательский cache-hit не должен запускать ещё один live search.
                // Свежесть cover-кэша поддерживает warm_home_search_cache cron.
                return Promise.resolve();
            }

            /* Session SWR — paint до сети */
            var searchGen = (window.__tvSearchGen = (window.__tvSearchGen || 0) + 1);
            var swrHit = tvHomeSwrRead(cacheParams);
            var paintedFromSwr = false;
            var paintedAny = false;
            if (swrHit) {
                paintedFromSwr = true;
                paintedAny = true;
                tvLoaderHide();
                tvPaintSearchHotels(swrHit.data, '%c[Главная · Поиск] Session SWR', { fromCache: true });
            } else if (!softSearch) {
                tvLoaderShow();
                tvLoaderUpdateInstantPreview(countryNameForCache, dep);
            } else {
                var prog = document.getElementById('tv-search-progress');
                if (prog) {
                    prog.classList.remove('hidden');
                    var pt = document.getElementById('tv-progress-text');
                    if (pt) pt.textContent = 'Обновляем даты...';
                }
            }

            function tvIsSearchStale() {
                return searchGen !== window.__tvSearchGen;
            }

            let rCache = { success: false, data: [] };
            var usedAltNights = false;
            try {
                if (!softSearch && !paintedFromSwr) tvLoaderSetStage('cache');
                console.log('%c[API → сайт] Параметры поиска отправляются в API', 'color: #5DA9A4; font-weight: bold', cacheParams);

                /* 1) Параллельный cacheOnly: основной + alt + (для TH/VN/LK) прямые */
                const cacheOpts = { cacheOnly: true, cacheScope: 'country_page', slim: true };
                var wantAltNights = !charterDirectMode && ((origNFrom === 6 && origNTo === 9) || origNFrom >= 10);
                var altParams = tvBuildAltNightsParams(cacheParams, origNFrom, origNTo);
                var cacheReqs = [tvFetch('search-cached', cacheParams, cacheOpts)];
                var altReqIdx = -1;
                var directReqIdx = -1;
                if (altParams) {
                    altReqIdx = cacheReqs.length;
                    cacheReqs.push(tvFetch('search-cached', altParams, cacheOpts));
                }
                if (preferDirectFirst) {
                    directReqIdx = cacheReqs.length;
                    cacheReqs.push(tvFetch('search-cached', Object.assign({}, cacheParams, { onlyDirect: '1' }), cacheOpts));
                }
                var cachePack = await Promise.all(cacheReqs);
                if (tvIsSearchStale()) return;
                rCache = cachePack[0] || rCache;
                if ((!rCache.success || !Array.isArray(rCache.data) || rCache.data.length === 0) && altReqIdx >= 0 && cachePack[altReqIdx]) {
                    var rAlt = cachePack[altReqIdx];
                    if (rAlt.success && Array.isArray(rAlt.data) && rAlt.data.length > 0) {
                        rCache = rAlt;
                        usedAltNights = true;
                        tvShowAltNightsBanner(true, origNFrom, origNTo);
                    }
                }
                if ((!rCache.success || !Array.isArray(rCache.data) || rCache.data.length === 0 || tvCountPlausibleHotels(rCache.data) <= 0)
                    && onlyCharterOn && !softSearch) {
                    var nearCharter = await tvTryNearestDirectSearch(cacheParams, dateFrom, dateTo, { cacheOpts: cacheOpts });
                    if (nearCharter) {
                        rCache = nearCharter.result;
                        usedCharterDateShift = true;
                        tvShowCharterDateShiftBanner(true, dateFrom, dateTo, nearCharter.dateFrom, nearCharter.dateTo);
                    }
                }
                if (preferDirectFirst && directReqIdx >= 0 && cachePack[directReqIdx]) {
                    var rDirect = cachePack[directReqIdx];
                    if (rDirect.success && Array.isArray(rDirect.data) && rDirect.data.length > 0) {
                        earlyDirectHotels = rDirect.data.map(function (h) {
                            var copy = Object.assign({}, h);
                            copy._thDirectWave = true;
                            return copy;
                        });
                        if (!paintedAny && !softSearch) {
                            paintedAny = true;
                            tvLoaderHide();
                            tvShowLiveRefine(true, 'Показаны прямые рейсы · подгружаем остальные варианты…');
                            tvPaintSearchHotels(earlyDirectHotels, '%c[Главная · Поиск] Early direct (cache TH/VN/LK)', { fromCache: true });
                            try {
                                var wrapD = document.getElementById('tv-results-wrapper');
                                if (wrapD) wrapD.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            } catch (eD) {}
                        }
                    }
                }

                var hadInstantCache = rCache.success && Array.isArray(rCache.data) && rCache.data.length > 0;
                if (hadInstantCache) {
                    var paintListCache = preferDirectFirst
                        ? tvMergeHotelsDirectFirst(earlyDirectHotels, rCache.data)
                        : rCache.data;
                    if (tvCountPlausibleHotels(paintListCache) > 0) {
                        tvLoaderHide();
                        tvShowLiveRefine(false);
                        var progOk = document.getElementById('tv-search-progress');
                        if (progOk) progOk.classList.add('hidden');
                        tvHomeSwrWrite(cacheParams, paintListCache);
                        paintedAny = true;
                        if (rCache.nightsExpanded) usedAltNights = true;
                        tvMaybeShowAltNightsBanner(paintListCache, origNFrom, origNTo, usedAltNights || !!rCache.nightsExpanded);
                        tvPaintSearchHotels(paintListCache, softSearch ? '%c[Главная · Поиск] Soft date shift (cache)' : '%c[Главная · Поиск] Мгновенная выдача из кэша', { fromCache: true });
                        if (!softSearch) document.getElementById('tv-results-wrapper').scrollIntoView({ behavior: 'smooth', block: 'start' });
                        return;
                    }
                }

                /* 2) Soft ±3/±6: один hop без live=1 (exact→cover→live) — cover часто закрывает сдвиг дат */
                if (softSearch) {
                    if (!paintedFromSwr) {
                        rCache = await tvFetch('search-cached', cacheParams, { cacheScope: 'country_page', slim: true });
                        if (tvIsSearchStale()) return;
                        if (rCache.success && Array.isArray(rCache.data) && rCache.data.length > 0) {
                            paintedAny = true;
                            tvHomeSwrWrite(cacheParams, rCache.data);
                            tvPaintSearchHotels(rCache.data, '%c[Главная · Поиск] Soft date shift (cover/live)', { fromCache: rCache.fromCache === true });
                            return;
                        }
                        // One-hop уже мог сходить в live — второй progressive не жжём
                    } else {
                        rCache = await tvFetch('search-cached', cacheParams, { cacheScope: 'country_page', slim: true });
                        if (tvIsSearchStale()) return;
                        if (rCache.success && Array.isArray(rCache.data) && rCache.data.length > 0) {
                            tvHomeSwrWrite(cacheParams, rCache.data);
                            tvPaintSearchHotels(rCache.data, '%c[Главная · Поиск] Soft SWR refresh', { fromCache: rCache.fromCache === true });
                        }
                        return;
                    }
                } else if (paintedFromSwr) {
                    /* SWR уже на экране — one-hop refresh без progressive */
                    rCache = await tvFetch('search-cached', cacheParams, { cacheScope: 'country_page', slim: true });
                    if (tvIsSearchStale()) return;
                    if (rCache.success && Array.isArray(rCache.data) && rCache.data.length > 0) {
                        tvHomeSwrWrite(cacheParams, rCache.data);
                        tvPaintSearchHotels(rCache.data, '%c[Главная · Поиск] SWR refresh', { fromCache: rCache.fromCache === true });
                    }
                    return;
                } else {
                    /* 3) Progressive live — без onlyDirect (кроме явной галочки).
                       Для TH/VN/LK: если уже показали прямые из кэша — догружаем остальные. */
                    tvLoaderSetStage('search');
                    if (preferDirectFirst && earlyDirectHotels.length && paintedAny) {
                        tvShowLiveRefine(true, 'Подгружаем варианты с пересадкой…');
                    }
                    rCache = await tvProgressiveLiveSearch(cacheParams, {
                        gen: searchGen,
                        earlyProgress: 30,
                        softMaxMs: 16000,
                        onPartial: function (list, meta) {
                            if (tvIsSearchStale()) return;
                            paintedAny = true;
                            tvLoaderHide();
                            var merged = preferDirectFirst
                                ? tvMergeHotelsDirectFirst(earlyDirectHotels, list)
                                : list;
                            tvShowLiveRefine(true, preferDirectFirst
                                ? ('Прямые сверху · уточняем выдачу' + (meta && meta.progress != null ? ' (' + meta.progress + '%)' : '…'))
                                : ((meta && meta.progress)
                                    ? ('Показываем первые варианты · уточняем ещё (' + meta.progress + '%)')
                                    : 'Показываем первые варианты · уточняем цены…'));
                            tvPaintSearchHotels(merged, '%c[Главная · Поиск] Early paint (live)', { fromCache: false });
                            try {
                                var wrap = document.getElementById('tv-results-wrapper');
                                if (wrap && !wrap.dataset.thEarlyScrolled) {
                                    wrap.dataset.thEarlyScrolled = '1';
                                    wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
                                }
                            } catch (eScr) {}
                        }
                    });
                    if (tvIsSearchStale()) return;

                    if ((!rCache.success || !Array.isArray(rCache.data) || rCache.data.length === 0 || tvCountPlausibleHotels(rCache.data) <= 0)
                        && onlyCharterOn && !usedCharterDateShift) {
                        var nearCharterLive = await tvTryNearestDirectSearch(cacheParams, dateFrom, dateTo, {
                            cacheOpts: { cacheScope: 'country_page', slim: true },
                            tryLive: true,
                            gen: searchGen
                        });
                        if (nearCharterLive) {
                            rCache = nearCharterLive.result;
                            usedCharterDateShift = true;
                            tvShowCharterDateShiftBanner(true, dateFrom, dateTo, nearCharterLive.dateFrom, nearCharterLive.dateTo);
                        }
                    }

                    /* 4) Alt-ночи: только если основной live пуст (не параллельный второй live) */
                    if ((!rCache.success || !Array.isArray(rCache.data) || rCache.data.length === 0) && !rCache.keptPartial && wantAltNights && altParams) {
                        var altLive = await tvProgressiveLiveSearch(altParams, {
                            gen: searchGen,
                            earlyProgress: 30,
                            softMaxMs: 16000,
                            onPartial: function (list, meta) {
                                if (tvIsSearchStale()) return;
                                paintedAny = true;
                                usedAltNights = true;
                                tvShowAltNightsBanner(true, origNFrom, origNTo);
                                tvLoaderHide();
                                tvShowLiveRefine(true, 'Показываем первые варианты · уточняем цены…');
                                tvPaintSearchHotels(
                                    preferDirectFirst ? tvMergeHotelsDirectFirst(earlyDirectHotels, list) : list,
                                    '%c[Главная · Поиск] Early paint (alt nights)'
                                );
                            }
                        });
                        if (tvIsSearchStale()) return;
                        if (altLive.success && Array.isArray(altLive.data) && altLive.data.length > 0) {
                            rCache = altLive;
                            usedAltNights = true;
                            tvShowAltNightsBanner(true, origNFrom, origNTo);
                        }
                    }
                }
            } catch (err) {
                console.error('[Главная · Поиск] Ошибка запроса поиска', err);
                rCache = { success: false, data: [], error: String(err && err.message ? err.message : err) };
            } finally {
                if (tvIsSearchStale()) return;
                window.__tvSearchBusy = false;
                if (!softSearch) tvLoaderSetStage('done');
                var progEnd = document.getElementById('tv-search-progress');
                if (progEnd) progEnd.classList.add('hidden');
                if (!paintedAny && !softSearch) await new Promise(r => setTimeout(r, 120));
                tvLoaderHide();
                tvShowLiveRefine(false);
                try {
                    var wrapClr = document.getElementById('tv-results-wrapper');
                    if (wrapClr) delete wrapClr.dataset.thEarlyScrolled;
                } catch (eClr) {}
            }

            if (tvIsSearchStale()) return;

            if (rCache.success && Array.isArray(rCache.data) && rCache.data.length > 0) {
                var finalList = preferDirectFirst
                    ? tvMergeHotelsDirectFirst(earlyDirectHotels, rCache.data)
                    : rCache.data;
                if (tvCountPlausibleHotels(finalList) <= 0) {
                    if (resultsDiv) resultsDiv.innerHTML = tvEmptyResultsHtml(origNFrom, origNTo, usedAltNights);
                    showTvResultsChrome();
                    updateTvLoadMoreButton();
                    return;
                }
                tvHomeSwrWrite(cacheParams, finalList);
                if (usedAltNights || rCache.nightsExpanded) usedAltNights = true;
                tvMaybeShowAltNightsBanner(finalList, origNFrom, origNTo, usedAltNights);
                const fromCache = rCache.fromCache === true;
                tvPaintSearchHotels(finalList, null, { fromCache: fromCache });
                document.getElementById('tv-results-wrapper').scrollIntoView({ behavior: 'smooth', block: 'start' });
                console.log('%c[API → сайт] Результаты с API получены и отображены на сайте', 'color: #22c55e; font-weight: bold', { туров: tvLastResults.length, изКэша: fromCache, progressive: !fromCache, preferDirect: preferDirectFirst });
                console.log('%c[Главная · Поиск] ' + (fromCache ? 'Показаны данные из кэша:' : 'Показаны результаты progressive live:'), 'color: #22c55e', tvLastResults.length, 'туров');
                return;
            }

            if (paintedFromSwr || paintedAny || rCache.keptPartial) {
                return;
            }

            tvHotelsBeforeBudgetFilter = [];
            tvLastResults = [];
            document.getElementById('tv-result-count').textContent = '0';
            thSyncResultNoun(0);
            var isApiErr = rCache && rCache.error && String(rCache.error).length > 0;
            if (resultsDiv) resultsDiv.innerHTML = isApiErr ? tvSearchErrorHtml(rCache.error) : tvEmptyResultsHtml(origNFrom, origNTo, usedAltNights);
            showTvResultsChrome();
            updateTvLoadMoreButton();
            if (isApiErr && window.THLeadCapture) window.THLeadCapture.reachGoal('search_error');
            console.warn('[Главная · Поиск] Нет результатов по выбранным параметрам.', rCache.error || '');
            return;
        }

        function tvLoaderUpdateInstantPreview(countryName, depId) {
            var el = document.getElementById('tv-loader-instant');
            if (!el || !countryName) return;
            el.textContent = 'Ищем туры: ' + countryName + '…';
            fetch('/backend/api/home_popular_destinations.php?departureId=' + encodeURIComponent(depId || '7'), { cache: 'no-store' })
                .then(function(r) { return r.json(); })
                .then(function(j) {
                    if (!el || !j || !j.success) return;
                    var hit = (j.items || []).find(function(it) {
                        return String(it.name || '').toLowerCase().indexOf(String(countryName).toLowerCase().slice(0, 4)) >= 0;
                    });
                    if (hit && hit.minPrice) {
                        el.textContent = 'Туры в ' + countryName + ' от ~' + new Intl.NumberFormat('ru-RU').format(hit.minPrice) + ' ₽ · уточняем цены…';
                    }
                }).catch(function() {});
        }
        function tvSearchErrorHtml(errMsg) {
            var sub = String(errMsg || 'Сервис временно недоступен').replace(/</g, '&lt;');
            return '<div class="tv-empty-state tv-empty-state--error"><div class="tv-empty-state__icon">⚠️</div><div class="tv-empty-state__title">Не удалось загрузить туры</div><div class="tv-empty-state__sub">' + sub + '</div><button type="button" class="tv-empty-state__btn" data-open-lead-modal="search-error"><i class="fas fa-headset"></i>Помочь с подбором</button></div>';
        }

                /** Как promoHotelListPrice / страница акций: totalPrice ближе к деталям тура, чем поле price из поиска. */
        function thPickFirstPositivePriceNum() {
            const args = Array.from(arguments);
            for (let i = 0; i < args.length; i++) {
                const v = args[i];
                if (v == null || v === '') continue;
                const n = Number(v);
                if (!Number.isNaN(n) && n > 0) return n;
            }
            return 0;
        }
        /** Цена одной позиции тура (totalPrice приоритетнее сырого price). */
        function tvTourUnitPrice(tour) {
            if (!tour) return 0;
            if (window.THTourPriceSanity && typeof window.THTourPriceSanity.pickPriceNum === 'function') {
                return window.THTourPriceSanity.pickPriceNum(tour);
            }
            return Math.round(thPickFirstPositivePriceNum(
                tour.totalPrice,
                tour.price,
                tour.priceRub,
                tour.cost
            ));
        }
        function tvPriceSanityOpts(h) {
            var adults = Math.max(1, Math.min(9, parseInt(String(typeof tvAdultsCount !== 'undefined' ? tvAdultsCount : 2), 10) || 2));
            var batch = (Array.isArray(tvHotelsBeforeBudgetFilter) && tvHotelsBeforeBudgetFilter.length >= 3)
                ? tvHotelsBeforeBudgetFilter
                : ((Array.isArray(tvLastResults) && tvLastResults.length >= 3) ? tvLastResults : (h ? [h] : []));
            return { hotelOnly: false, adults: adults, batchHotels: batch };
        }
        /**
         * Мин. цена по отелю: минимум по всем турам в выдаче, иначе hotel.price.
         * На карточке всегда «от», не цена случайного tours[0].
         * Мусор Tourvisor: битый парсинг полей и выбросы относительно выдачи (не ручные полы).
         */
        function tvHotelListPrice(h) {
            if (!h) return 0;
            var hotelOnly = typeof thIsHotelSearchMode === 'function' && thIsHotelSearchMode();
            if (!hotelOnly && window.THTourPriceSanity && typeof window.THTourPriceSanity.hotelMinPlausiblePrice === 'function') {
                var sane = window.THTourPriceSanity.hotelMinPlausiblePrice(h, tvPriceSanityOpts(h));
                return sane > 0 ? sane : 0;
            }
            let min = 0;
            const tours = Array.isArray(h.tours) ? h.tours : [];
            for (let i = 0; i < tours.length; i++) {
                const n = tvTourUnitPrice(tours[i]);
                if (n > 0 && (min === 0 || n < min)) min = n;
            }
            if (min > 0) return min;
            return Math.round(thPickFirstPositivePriceNum(h.price, h.minPrice, h.minprice));
        }
        /** Тур с минимальной ценой — для дат/питания/ссылки на карточке. */
        function tvHotelCheapestTour(h) {
            const tours = (h && Array.isArray(h.tours)) ? h.tours : [];
            if (!tours.length) return {};
            var hotelOnly = typeof thIsHotelSearchMode === 'function' && thIsHotelSearchMode();
            if (!hotelOnly && window.THTourPriceSanity && typeof window.THTourPriceSanity.pickCheapestPlausibleTour === 'function') {
                var saneTour = window.THTourPriceSanity.pickCheapestPlausibleTour(h, tvPriceSanityOpts(h));
                if (saneTour) return saneTour;
                return {};
            }
            let best = tours[0];
            let bestPrice = tvTourUnitPrice(best) || Number.POSITIVE_INFINITY;
            for (let i = 1; i < tours.length; i++) {
                const n = tvTourUnitPrice(tours[i]);
                if (n > 0 && n < bestPrice) {
                    best = tours[i];
                    bestPrice = n;
                }
            }
            return best || {};
        }
        function showTvResultsChrome() {
            var wrapper = document.getElementById('tv-results-wrapper');
            var leadBar = document.getElementById('tv-results-lead-bar');
            if (wrapper) wrapper.classList.remove('hidden');
            if (leadBar) leadBar.classList.remove('hidden');
            var stickyLead = document.getElementById('th-results-sticky-lead');
            if (stickyLead) {
                stickyLead.classList.add('is-visible');
                try {
                    if (stickyLead.parentNode !== document.body) {
                        document.body.appendChild(stickyLead);
                    }
                } catch (eMove) {}
                document.body.classList.add('has-results-sticky');
            }
            if (window.THMobile && typeof window.THMobile.sync === 'function') window.THMobile.sync();
            if (window.THMobile && typeof window.THMobile.pinFixedBottoms === 'function') {
                window.THMobile.pinFixedBottoms();
            }
            if (window.THLeadCapture) window.THLeadCapture.reachGoal('search_results_shown');
        }
        function thTrackGoal(goal) {
            try {
                if (window.THLeadCapture && typeof window.THLeadCapture.reachGoal === 'function') {
                    window.THLeadCapture.reachGoal(goal);
                }
            } catch (e) {}
        }
        function thIsHotelSearchMode() {
            return window.__thSearchMode === 'hotels';
        }
        function thSyncHotelModeChrome() {
            var hotel = thIsHotelSearchMode();
            var hint = document.getElementById('th-hotel-mode-hint');
            if (hint) {
                if (hotel) hint.removeAttribute('hidden');
                else hint.setAttribute('hidden', '');
            }
            var note = document.getElementById('tv-results-hotel-note');
            if (note) {
                if (hotel) note.removeAttribute('hidden');
                else note.setAttribute('hidden', '');
            }
            document.querySelectorAll('[data-th-dates-label]').forEach(function (el) {
                el.textContent = hotel ? 'Даты заезда' : 'Даты вылета';
            });
            document.querySelectorAll('[data-th-search-open="departure"]').forEach(function (el) {
                el.classList.toggle('is-hotel-locked', hotel);
                if (hotel) el.setAttribute('aria-disabled', 'true');
                else el.removeAttribute('aria-disabled');
            });
            if (window.THSearchUI && typeof window.THSearchUI.refreshLabels === 'function') {
                window.THSearchUI.refreshLabels();
            } else {
                document.querySelectorAll('[data-th-label="departure"]').forEach(function (el) {
                    if (hotel) {
                        el.textContent = TH_HOTEL_DEPARTURE_NAME;
                        el.classList.remove('is-placeholder');
                    }
                });
            }
        }
        function thResultNoun(n) {
            n = parseInt(String(n), 10) || 0;
            if (thIsHotelSearchMode()) {
                var hmod = n % 100;
                if (hmod >= 11 && hmod <= 14) return 'отелей';
                var h1 = n % 10;
                if (h1 === 1) return 'отель';
                if (h1 >= 2 && h1 <= 4) return 'отеля';
                return 'отелей';
            }
            var tmod = n % 100;
            if (tmod >= 11 && tmod <= 14) return 'туров';
            var t1 = n % 10;
            if (t1 === 1) return 'тур';
            if (t1 >= 2 && t1 <= 4) return 'тура';
            return 'туров';
        }
        function thSyncResultNoun(count) {
            var el = document.getElementById('tv-result-noun');
            if (el) el.textContent = thResultNoun(count);
            var title = document.getElementById('tv-compare-title');
            if (title) title.textContent = thIsHotelSearchMode() ? 'Сравнение отелей' : 'Сравнение туров';
        }
        function thApplySearchMode(mode) {
            mode = mode === 'hotels' ? 'hotels' : 'tours';
            var prev = window.__thSearchMode;
            window.__thSearchMode = mode;
            document.body.classList.toggle('th-search-mode-hotels', mode === 'hotels');
            var root = document.getElementById('tour-search-section');
            if (root) root.setAttribute('data-search-mode', mode);
            document.querySelectorAll('[data-th-search-mode]').forEach(function (btn) {
                var on = btn.getAttribute('data-th-search-mode') === mode;
                btn.classList.toggle('is-active', on);
                btn.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            var hero = document.querySelector('.th-home-hero__title-line');
            if (hero) hero.textContent = mode === 'hotels' ? 'Найдите отель за минуту' : 'Найдите тур за минуту';
            var steps = document.querySelector('.th-home-hero__steps');
            if (steps) {
                steps.innerHTML = mode === 'hotels'
                    ? '<span>Куда</span><span class="th-home-hero__dot" aria-hidden="true">·</span><span>Даты</span><span class="th-home-hero__dot" aria-hidden="true">·</span><span>Фильтры</span>'
                    : '<span>Откуда</span><span class="th-home-hero__dot" aria-hidden="true">·</span><span>Куда</span><span class="th-home-hero__dot" aria-hidden="true">·</span><span>Даты</span><span class="th-home-hero__dot" aria-hidden="true">·</span><span>Ночи</span><span class="th-home-hero__dot" aria-hidden="true">·</span><span>Туристы</span>';
            }
            thSyncHotelModeChrome();
            try { sessionStorage.setItem('th_search_mode', mode); } catch (eS) {}
            if (window.THTourSearchWizard && typeof window.THTourSearchWizard.go === 'function') {
                var w = window.THTourSearchWizard;
                if (typeof w.onSearchModeChange === 'function') {
                    w.onSearchModeChange(mode);
                } else {
                    var min = typeof w.minStep === 'function' ? w.minStep() : 1;
                    var max = typeof w.maxStep === 'function' ? w.maxStep() : 5;
                    w.go(mode === 'hotels' ? min : Math.min(Math.max(w.step || 1, min), max), true);
                }
            }
            thSyncResultNoun(document.getElementById('tv-result-count') ? document.getElementById('tv-result-count').textContent : 0);
            if (prev !== mode && typeof window.reloadTvCountriesForDeparture === 'function') {
                var depForCountries = mode === 'hotels'
                    ? TH_HOTEL_DEPARTURE_ID
                    : (tvActiveDeparture().id || 7);
                window.reloadTvCountriesForDeparture(depForCountries);
            }
            if (typeof tvSchedulePrefetchHomeSearch === 'function') tvSchedulePrefetchHomeSearch();
        }
        function tvCompareCount() {
            return Object.keys(tvCompareMap || {}).length;
        }
        function tvComparePersist() {
            try { sessionStorage.setItem('th_tv_compare', JSON.stringify(tvCompareMap || {})); } catch (eP) {}
        }
        function tvCompareRestore() {
            try {
                var raw = sessionStorage.getItem('th_tv_compare');
                if (raw) {
                    var parsed = JSON.parse(raw);
                    if (parsed && typeof parsed === 'object') tvCompareMap = parsed;
                }
            } catch (eR) {}
        }
        function tvCompareOpenModal() {
            var panel = document.getElementById('tv-compare-panel');
            if (!panel) return;
            tvCompareRenderPanel();
            panel.classList.remove('hidden');
            panel.setAttribute('aria-hidden', 'false');
            document.body.classList.add('th-modal-open');
        }
        function tvCompareCloseModal() {
            var panel = document.getElementById('tv-compare-panel');
            if (!panel) return;
            panel.classList.add('hidden');
            panel.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('th-modal-open');
        }
        function tvCompareSyncButtons() {
            var container = document.getElementById('tv-search-results');
            if (!container) return;
            container.querySelectorAll('[data-th-compare-toggle]').forEach(function(btn) {
                var key = String(btn.getAttribute('data-compare-key') || '');
                var active = !!(key && tvCompareMap[key]);
                btn.classList.toggle('is-active', active);
                btn.setAttribute('aria-pressed', active ? 'true' : 'false');
                btn.textContent = active ? 'В сравнении' : 'Сравнить';
            });
        }
        function tvCompareRenderPanel() {
            var countEl = document.getElementById('tv-compare-count');
            var openBtn = document.getElementById('tv-compare-open-btn');
            var list = document.getElementById('tv-compare-list');
            var bestBox = document.getElementById('tv-compare-best');
            var count = tvCompareCount();
            if (countEl) countEl.textContent = String(count);
            if (openBtn) {
                openBtn.disabled = false;
                openBtn.classList.toggle('is-empty', count < 2);
            }
            thSyncResultNoun(document.getElementById('tv-result-count') ? document.getElementById('tv-result-count').textContent : 0);
            if (!list) return;
            var items = Object.values(tvCompareMap || {});
            if (!items.length) {
                list.innerHTML = '<p class="tv-compare-empty">Добавьте 2–3 варианта кнопкой «Сравнить» на карточке. Потом откроется таблица: цена, питание, ночи, рейтинг.</p>';
                if (bestBox) {
                    bestBox.classList.add('hidden');
                    bestBox.innerHTML = '';
                }
                return;
            }
            var priceNums = items.map(function(it) { return parseInt(String(it.price || ''), 10) || 0; }).filter(function(v) { return v > 0; });
            var minPrice = priceNums.length ? Math.min.apply(null, priceNums) : 0;
            var bestItem = null;
            items.forEach(function (it) {
                if (!bestItem) {
                    bestItem = it;
                    return;
                }
                var pA = parseInt(String(it.price || ''), 10) || 0;
                var pB = parseInt(String(bestItem.price || ''), 10) || 0;
                if (pA > 0 && (pB <= 0 || pA < pB)) {
                    bestItem = it;
                    return;
                }
                if (pA === pB) {
                    var rA = parseFloat(String(it.rating || 0)) || 0;
                    var rB = parseFloat(String(bestItem.rating || 0)) || 0;
                    if (rA > rB) bestItem = it;
                }
            });
            function escCmp(s) {
                return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
            }
            function cellHotel(it) {
                var low = minPrice > 0 && (parseInt(String(it.price || ''), 10) || 0) === minPrice;
                var best = bestItem && bestItem.key === it.key;
                return '<div class="tv-cmp-hotel">' +
                    '<strong>' + escCmp(it.hotel || '') + '</strong>' +
                    (low ? '<span class="tv-cmp-cheapest">Выгоднее</span>' : '') +
                    (best ? '<span class="tv-cmp-best">Рекомендуем</span>' : '') +
                    '</div>';
            }
            function cellPrice(it) {
                var p = parseInt(String(it.price || ''), 10) || 0;
                return p > 0 ? ('от ' + p.toLocaleString('ru-RU') + ' ₽') : '—';
            }
            function cellDirection(it) {
                var c = String(it.country || '').trim();
                var r = String(it.region || '').trim();
                return escCmp(c + (r ? (', ' + r) : ''));
            }
            function cellNights(it) {
                var n = parseInt(String(it.nights || ''), 10) || 0;
                return n > 0 ? (n + ' ночей') : '—';
            }
            function cellMeal(it) {
                return escCmp(String(it.meal || '').trim() || 'Любое');
            }
            function cellRating(it) {
                var r = parseFloat(String(it.rating || 0)) || 0;
                return r > 0 ? escCmp(String(r.toFixed(1))) : '—';
            }
            function cellDates(it) {
                return escCmp(String(it.dates || '').trim() || 'Уточняется');
            }
            function cellActions(it) {
                return '<div class="tv-cmp-actions">' +
                    '<a class="tv-cmp-go" href="' + escCmp(it.href || '#') + '">Открыть</a>' +
                    '<button type="button" class="tv-cmp-remove" data-tv-compare-remove="' + escCmp(it.key || '') + '">Убрать</button>' +
                    '</div>';
            }
            list.innerHTML =
                '<div class="tv-cmp-wrap"><table class="tv-cmp-table"><tbody>' +
                '<tr><th>Тур</th>' + items.map(function(it) { return '<td>' + cellHotel(it) + '</td>'; }).join('') + '</tr>' +
                '<tr><th>Цена</th>' + items.map(function(it) { return '<td class="tv-cmp-price">' + cellPrice(it) + '</td>'; }).join('') + '</tr>' +
                '<tr><th>Направление</th>' + items.map(function(it) { return '<td>' + cellDirection(it) + '</td>'; }).join('') + '</tr>' +
                '<tr><th>Ночи</th>' + items.map(function(it) { return '<td>' + cellNights(it) + '</td>'; }).join('') + '</tr>' +
                '<tr><th>Питание</th>' + items.map(function(it) { return '<td>' + cellMeal(it) + '</td>'; }).join('') + '</tr>' +
                '<tr><th>Рейтинг</th>' + items.map(function(it) { return '<td>' + cellRating(it) + '</td>'; }).join('') + '</tr>' +
                '<tr><th>Даты</th>' + items.map(function(it) { return '<td>' + cellDates(it) + '</td>'; }).join('') + '</tr>' +
                '<tr><th>Действие</th>' + items.map(function(it) { return '<td>' + cellActions(it) + '</td>'; }).join('') + '</tr>' +
                '</tbody></table></div>';
            if (bestBox && bestItem) {
                var bestPrice = parseInt(String(bestItem.price || ''), 10) || 0;
                var bp = bestPrice > 0 ? (bestPrice.toLocaleString('ru-RU') + ' ₽') : '—';
                bestBox.innerHTML = '<p><strong>Рекомендуем:</strong> ' + escCmp(bestItem.hotel || '') + ' · от ' + escCmp(bp) + '</p>'
                    + '<a class="tv-compare-best__go" href="' + escCmp(bestItem.href || '#') + '">Открыть лучший вариант</a>';
                bestBox.classList.remove('hidden');
            }
        }
        function tvRenderPriceCalendar() {
            var box = document.getElementById('tv-price-calendar');
            if (!box) return;
            if (!Array.isArray(tvLastResults) || !tvLastResults.length) {
                box.innerHTML = '';
                tvSyncDateShiftButtons();
                return;
            }
            var minPrice = tvLastResults.reduce(function(min, h) {
                var p = tvHotelListPrice(h);
                if (!p || p <= 0) return min;
                if (!min || p < min) return p;
                return min;
            }, 0);
            var minText = minPrice > 0 ? ('от ' + minPrice.toLocaleString('ru-RU') + ' ₽') : 'цена уточняется';
            var points = [-6, -3, 0, 3, 6];
            var backRoom = tvSearchDateBackroomDays();
            box.innerHTML = '<div class="tv-price-calendar__head">Календарь цен (быстрый сдвиг дат)</div>' +
                '<div class="tv-price-calendar__row">' + points.map(function(p) {
                    var label = (p === 0 ? 'Текущие даты' : (p > 0 ? ('+' + p + ' дн') : (p + ' дн')));
                    var val = (p === 0 ? minText : 'Показать');
                    var blocked = p < 0 && backRoom < Math.abs(p);
                    var cls = 'tv-price-point' + (p === 0 ? ' is-current' : '') + (blocked ? ' is-disabled' : '');
                    var title = blocked ? 'Раньше сегодня нельзя — вылет уже у нижней границы' : '';
                    return '<button type="button" class="' + cls + '" data-tv-shift-days="' + p + '"' +
                        (blocked ? ' disabled aria-disabled="true"' : '') +
                        (title ? ' title="' + title + '"' : '') + '>' +
                        '<span>' + label + '</span><strong>' + (blocked ? 'Недоступно' : val) + '</strong></button>';
                }).join('') + '</div>';
            tvSyncDateShiftButtons();
        }
        function tvCompareToggleFromButton(btn) {
            if (!btn) return;
            var key = String(btn.getAttribute('data-compare-key') || '');
            if (!key) return;
            var wasEmpty = !tvCompareMap[key];
            if (tvCompareMap[key]) {
                delete tvCompareMap[key];
                thTrackGoal('compare_remove');
            } else {
                if (tvCompareCount() >= TV_COMPARE_MAX) {
                    tvCompareOpenModal();
                    return;
                }
                tvCompareMap[key] = {
                    key: key,
                    href: String(btn.getAttribute('data-compare-href') || '#'),
                    hotel: String(btn.getAttribute('data-compare-hotel') || ''),
                    country: String(btn.getAttribute('data-compare-country') || ''),
                    region: String(btn.getAttribute('data-compare-region') || ''),
                    price: String(btn.getAttribute('data-compare-price') || ''),
                    rating: String(btn.getAttribute('data-compare-rating') || ''),
                    nights: String(btn.getAttribute('data-compare-nights') || ''),
                    meal: String(btn.getAttribute('data-compare-meal') || ''),
                    dates: String(btn.getAttribute('data-compare-dates') || '')
                };
                thTrackGoal('compare_add');
            }
            tvComparePersist();
            tvCompareSyncButtons();
            tvCompareRenderPanel();
            if (wasEmpty && tvCompareCount() >= 2) tvCompareOpenModal();
        }
        function tvParseRuDate(s) {
            var str = String(s || '').trim();
            var iso = str.match(/(\d{4})-(\d{1,2})-(\d{1,2})/);
            if (iso) {
                var di = new Date(parseInt(iso[1], 10), parseInt(iso[2], 10) - 1, parseInt(iso[3], 10));
                return isNaN(di.getTime()) ? null : di;
            }
            var m = str.match(/(\d{1,2})[./](\d{1,2})[./](\d{2,4})/);
            if (!m) return null;
            var y = parseInt(m[3], 10);
            if (y < 100) y += 2000;
            var d = new Date(y, parseInt(m[2], 10) - 1, parseInt(m[1], 10));
            return isNaN(d.getTime()) ? null : d;
        }
        function tvReadSearchDateRange() {
            function fromFp(fp) {
                if (!fp || !fp.selectedDates || fp.selectedDates.length < 1) return null;
                var a = new Date(fp.selectedDates[0]);
                var b = fp.selectedDates.length >= 2 ? new Date(fp.selectedDates[1]) : new Date(fp.selectedDates[0]);
                if (b < a) { var t = a; a = b; b = t; }
                return [a, b];
            }
            var range = fromFp(window.tvDatePickerInline) || fromFp(window.tvDatePicker) || fromFp(typeof tvDatePicker !== 'undefined' ? tvDatePicker : null);
            if (range) return range;
            var raw = '';
            var datesEl = document.getElementById('tv-dates');
            if (datesEl && datesEl.value) raw = String(datesEl.value);
            if (!raw) {
                var disp = document.getElementById('tv-sc-dates-display');
                if (disp) raw = String(disp.textContent || '');
            }
            if (!raw) {
                var preview = document.getElementById('tv-sc-dates-preview');
                if (preview) raw = String(preview.textContent || '');
            }
            var parts = String(raw).split(/\s*(?:—|–|-|по)\s*/);
            if (parts.length >= 2) {
                var a2 = tvParseRuDate(parts[0]);
                var b2 = tvParseRuDate(parts[1]);
                if (a2 && b2) return b2 < a2 ? [b2, a2] : [a2, b2];
            }
            return null;
        }
        function tvWriteSearchDateRange(from, to) {
            if (window.tvDatePicker && typeof window.tvDatePicker.setDate === 'function') {
                try { window.tvDatePicker.setDate([from, to], true); } catch (e1) {}
            }
            if (window.tvDatePickerInline && typeof window.tvDatePickerInline.setDate === 'function') {
                try { window.tvDatePickerInline.setDate([from, to], true); } catch (e2) {}
            }
            var datesEl = document.getElementById('tv-dates');
            if (datesEl) {
                var pad = function (n) { return (n < 10 ? '0' : '') + n; };
                var fmt = function (d) { return pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + d.getFullYear(); };
                datesEl.value = fmt(from) + ' — ' + fmt(to);
                try { datesEl.dispatchEvent(new Event('change', { bubbles: true })); } catch (e3) {}
            }
            if (typeof window.updateDateDisplayOnly === 'function') {
                window.updateDateDisplayOnly(from, to);
            }
            if (typeof tvSyncDateShiftButtons === 'function') tvSyncDateShiftButtons();
        }
        function tvResolveSearchDatesYmd() {
            var range = tvReadSearchDateRange();
            if (range && typeof flatpickr !== 'undefined' && flatpickr.formatDate) {
                return {
                    dateFrom: flatpickr.formatDate(range[0], 'Y-m-d'),
                    dateTo: flatpickr.formatDate(range[1], 'Y-m-d')
                };
            }
            var datesVal = (document.getElementById('tv-dates')?.value || '').trim();
            var dateFrom, dateTo;
            var parseD = function (s) {
                var t = (s || '').trim();
                if (/^\d{4}-\d{2}-\d{2}$/.test(t)) return t;
                if (/^\d{2}-\d{2}-\d{4}$/.test(t)) {
                    var p = t.split('-');
                    return p[2] + '-' + p[1] + '-' + p[0];
                }
                var m = t.replace(/\./g, '-').match(/^(\d{1,2})-(\d{1,2})-(\d{4})$/);
                return m ? m[3] + '-' + m[2].padStart(2, '0') + '-' + m[1].padStart(2, '0') : t;
            };
            if (datesVal) {
                var parts = datesVal.split(/\s*(?:—|–|-|по|to)\s*/i);
                if (parts.length >= 2) {
                    dateFrom = parseD(parts[0]);
                    dateTo = parseD(parts[1]);
                }
            }
            if ((!dateFrom || !dateTo) && typeof flatpickr !== 'undefined' && tvDatePicker && tvDatePicker.selectedDates && tvDatePicker.selectedDates.length >= 1) {
                var sel = tvDatePicker.selectedDates;
                dateFrom = flatpickr.formatDate(sel[0], 'Y-m-d');
                dateTo = sel.length >= 2 ? flatpickr.formatDate(sel[1], 'Y-m-d') : flatpickr.formatDate(new Date(sel[0].getTime() + 30 * 864e5), 'Y-m-d');
            }
            if ((!dateFrom || !dateTo) && typeof flatpickr !== 'undefined' && window.tvDatePickerInline && window.tvDatePickerInline.selectedDates && window.tvDatePickerInline.selectedDates.length >= 1) {
                var selIn = window.tvDatePickerInline.selectedDates;
                dateFrom = flatpickr.formatDate(selIn[0], 'Y-m-d');
                dateTo = selIn.length >= 2 ? flatpickr.formatDate(selIn[1], 'Y-m-d') : flatpickr.formatDate(new Date(selIn[0].getTime() + 30 * 864e5), 'Y-m-d');
            }
            if (!dateFrom || !dateTo) {
                var defWin = getDefaultDateWindow();
                if (typeof flatpickr !== 'undefined' && flatpickr.formatDate) {
                    dateFrom = flatpickr.formatDate(defWin[0], 'Y-m-d');
                    dateTo = flatpickr.formatDate(defWin[1], 'Y-m-d');
                } else {
                    var padYmd = function (d) {
                        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                    };
                    dateFrom = padYmd(defWin[0]);
                    dateTo = padYmd(defWin[1]);
                }
                applyDefaultDateWindow();
            }
            return { dateFrom: dateFrom, dateTo: dateTo };
        }
        function tvShiftSearchDates(days) {
            var d = parseInt(String(days || ''), 10);
            if (isNaN(d)) return;
            if (!d) {
                thTrackGoal('date_shift_click');
                if (typeof performTvSearch === 'function') performTvSearch(true, { soft: true });
                return;
            }
            if (!tvCanShiftSearchDates(d)) {
                tvSyncDateShiftButtons();
                return;
            }
            thTrackGoal('date_shift_click');
            var range = tvReadSearchDateRange();
            if (!range) {
                console.warn('[Главная · Поиск] Сдвиг дат: нет выбранного периода');
                return;
            }
            var from = new Date(range[0]);
            var to = new Date(range[1]);
            var spanDays = Math.max(0, Math.round((to.getTime() - from.getTime()) / 86400000));
            from.setDate(from.getDate() + d);
            to.setDate(to.getDate() + d);
            var minD = new Date();
            minD.setHours(0, 0, 0, 0);
            from.setHours(0, 0, 0, 0);
            to.setHours(0, 0, 0, 0);
            if (from < minD) {
                from = new Date(minD);
                to = new Date(minD);
                to.setDate(to.getDate() + spanDays);
            }
            tvWriteSearchDateRange(from, to);
            tvSyncDateShiftButtons();
            if (typeof syncNightsFromDates === 'function') syncNightsFromDates();
            if (typeof performTvSearch === 'function') performTvSearch(true, { soft: true });
        }
        /** Сколько дней можно сдвинуть вылет назад (до сегодня). */
        function tvSearchDateBackroomDays() {
            var range = tvReadSearchDateRange();
            if (!range || !range[0]) return 0;
            var minD = new Date();
            minD.setHours(0, 0, 0, 0);
            var from = new Date(range[0]);
            from.setHours(0, 0, 0, 0);
            var diff = Math.round((from.getTime() - minD.getTime()) / 86400000);
            return Math.max(0, diff);
        }
        function tvCanShiftSearchDates(days) {
            var d = parseInt(String(days || ''), 10);
            if (isNaN(d) || d >= 0) return true;
            return tvSearchDateBackroomDays() >= Math.abs(d);
        }
        function tvSyncDateShiftButtons() {
            var backRoom = tvSearchDateBackroomDays();
            document.querySelectorAll('[data-tv-shift-days]').forEach(function (btn) {
                var d = parseInt(String(btn.getAttribute('data-tv-shift-days') || '0'), 10);
                if (isNaN(d) || d >= 0) {
                    btn.disabled = false;
                    btn.removeAttribute('aria-disabled');
                    btn.classList.remove('is-disabled');
                    btn.removeAttribute('title');
                    return;
                }
                var blocked = backRoom < Math.abs(d);
                btn.disabled = blocked;
                btn.setAttribute('aria-disabled', blocked ? 'true' : 'false');
                btn.classList.toggle('is-disabled', blocked);
                if (blocked) {
                    btn.title = 'Раньше сегодня нельзя — вылет уже у нижней границы';
                } else {
                    btn.removeAttribute('title');
                }
            });
        }
        function tvApplyClientFiltersAndRender() {
            var list = Array.isArray(tvHotelsBeforeBudgetFilter) ? tvHotelsBeforeBudgetFilter.slice() : [];
            list = list.filter(function (h) { return tvHotelListPrice(h) > 0 && !h._catalogOnly; });
            if (window.THTourPostFilters && tvPostFiltersCtrl) {
                list = window.THTourPostFilters.filterHotels(list, tvPostFiltersCtrl.state, { getPrice: tvHotelListPrice });
            }
            list = applyBudgetFilterToHotels(list);
            tvLastResults = list;
            tvDisplayedCount = Math.min(TV_PAGE_SIZE, Math.max(tvLastResults.length, 0));
            var rC = document.getElementById('tv-result-count');
            if (rC) rC.textContent = String(tvLastResults.length);
            thSyncResultNoun(tvLastResults.length);
            tvRenderPriceCalendar();
            applyTvSort();
            updateTvLoadMoreButton();
        }
        function tvShowAltNightsBanner(show, origFrom, origTo) {
            var el = document.getElementById('tv-search-alt-banner');
            if (!el) return;
            if (show) {
                if (el.dataset.charterShift === '1') return;
                origFrom = parseInt(origFrom, 10) || 0;
                origTo = parseInt(origTo, 10) || 0;
                if (origFrom >= 10) {
                    el.textContent = 'На ' + origFrom + '–' + origTo + ' ночей сейчас нет живых туров — показаны ближайшие варианты (диапазон расширен).';
                } else {
                    el.textContent = 'Подобраны ближайшие альтернативы (диапазон ночей расширен).';
                }
                el.classList.remove('hidden');
            } else {
                el.classList.add('hidden');
                el.textContent = '';
            }
        }
        function tvEmptyResultsHtml(nFrom, nTo, triedAlt) {
            if (thIsHotelSearchMode()) {
                return '<div class="tv-empty-state"><div class="tv-empty-state__icon">🏨</div><div class="tv-empty-state__title">Отелей не найдено</div><div class="tv-empty-state__sub">Попробуйте другую страну, даты или снимите фильтры — звёзды и курорт.</div><button type="button" class="tv-empty-state__btn" data-open-lead-modal="empty-state"><i class="fas fa-headset"></i>Подобрать отель с менеджером</button></div>';
            }
            var sub = (nFrom === 6 && nTo === 9 && !triedAlt)
                ? 'Нет туров на 6–9 ночей. Попробуйте другой диапазон'
                : 'Попробуйте изменить даты, страну или город вылета — и мы найдём подходящий вариант.';
            return '<div class="tv-empty-state"><div class="tv-empty-state__icon">🔍</div><div class="tv-empty-state__title">Туров не найдено</div><div class="tv-empty-state__sub">' + sub.replace(/</g, '&lt;') + '</div><button type="button" class="tv-empty-state__btn" data-open-lead-modal="empty-state"><i class="fas fa-headset"></i>Получить подбор от менеджера</button></div>';
        }
        function applyBudgetFilterToHotels(arr) {
            if (!Array.isArray(arr)) return [];
            var minInp = document.querySelector('[data-pf-price-min]') || document.getElementById('tv-price-min');
            var maxInp = document.querySelector('[data-pf-price-max]') || document.getElementById('tv-price-max');
            var minRaw = minInp ? String(minInp.value || '').trim() : '';
            var maxRaw = maxInp ? String(maxInp.value || '').trim() : '';
            var minV = minRaw ? parseInt(minRaw, 10) : NaN;
            var maxV = maxRaw ? parseInt(maxRaw, 10) : NaN;
            var out = arr.slice();
            if (!isNaN(minV) && minV > 0) {
                out = out.filter(function(h) { return tvHotelListPrice(h) >= minV; });
            }
            if (!isNaN(maxV) && maxV > 0) {
                out = out.filter(function(h) { return tvHotelListPrice(h) <= maxV; });
            }
            return out;
        }
        function applyTvSort(opts) {
            opts = opts || {};
            const sortVal = document.getElementById('tv-sort')?.value || 'price-asc';
            let arr = [...tvLastResults];
            if (sortVal === 'price-asc') arr.sort((a, b) => tvHotelListPrice(a) - tvHotelListPrice(b));
            else if (sortVal === 'price-desc') arr.sort((a, b) => tvHotelListPrice(b) - tvHotelListPrice(a));
            else if (sortVal === 'rating') arr.sort((a, b) => (Number(b.rating) || 0) - (Number(a.rating) || 0));
            var preferDirect = !thIsHotelSearchMode()
                && window.__tvSearchFlightFlags
                && window.__tvSearchFlightFlags.preferDirect
                && !window.__tvSearchFlightFlags.onlyDirect;
            if (preferDirect && (sortVal === 'price-asc' || sortVal === 'rating')) {
                arr = tvSortHotelsDirectFlightFirst(arr);
            }
            var slice = arr.slice(0, tvDisplayedCount);
            renderTvResults(slice);
            tvCompareSyncButtons();
            tvCompareRenderPanel();
            var tvResEl = document.getElementById('tv-search-results');
            if (tvResEl && window.THTourCard && typeof window.THTourCard.mountInContainer === 'function') {
                window.THTourCard.mountInContainer(tvResEl);
            }
            var skipFlightFetch = !!opts.skipFlights || thIsHotelSearchMode();
            if (!skipFlightFetch) {
                tvKickSearchFlightsLoad(slice, {
                    onDone: function () {
                        var preferDirect = !thIsHotelSearchMode()
                            && window.__tvSearchFlightFlags
                            && window.__tvSearchFlightFlags.preferDirect
                            && !window.__tvSearchFlightFlags.onlyDirect;
                        if (preferDirect) {
                            tvMaybeResortDirectPriority();
                            applyTvSort({ skipFlights: true });
                        }
                    }
                });
            }
            if (!window.__tvRestoringFromBack) saveTvMainSearchSnapshot();
        }

        function updateTvLoadMoreButton() {
            const wrapper = document.getElementById('tv-load-more-wrapper');
            const btn = document.getElementById('tv-load-more-btn');
            const textEl = document.getElementById('tv-load-more-text');
            if (!wrapper || !btn) return;
            const hasResults = tvLastResults.length > 0;
            const canLoadMore = tvDisplayedCount < tvLastResults.length;
            if (hasResults) {
                wrapper.classList.remove('hidden');
                btn.disabled = !canLoadMore;
                var moreLabel = thIsHotelSearchMode() ? 'Загрузить ещё отели' : 'Загрузить ещё туры';
                if (textEl) textEl.textContent = moreLabel;
            } else {
                wrapper.classList.add('hidden');
                if (textEl) textEl.textContent = thIsHotelSearchMode() ? 'Загрузить ещё отели' : 'Загрузить ещё туры';
            }
        }

        function loadMoreTvResults() {
            if (tvDisplayedCount >= tvLastResults.length) return;
            tvDisplayedCount = Math.min(tvDisplayedCount + TV_PAGE_SIZE, tvLastResults.length);
            applyTvSort();
            updateTvLoadMoreButton();
        }

        window.__mainFlightsByTourId = window.__mainFlightsByTourId || {};
        function getMainTourId(h) {
            const tour = (typeof tvHotelCheapestTour === 'function')
                ? tvHotelCheapestTour(h)
                : ((h.tours && h.tours[0]) ? h.tours[0] : {});
            const tourId = tour.id ?? tour.tourId ?? tour.tourid ?? '';
            return (tourId != null && tourId !== '') ? String(tourId) : '';
        }
        var tvFlightLoadTimer = null;
        function loadMainFlightsForTours(hotels, callback) {
            tvKickSearchFlightsLoad(hotels, { onDone: callback });
        }

        function tvKickSearchFlightsLoad(priorityHotels, opts) {
            opts = opts || {};
            if (thIsHotelSearchMode()) {
                if (opts.onDone) opts.onDone();
                return Promise.resolve();
            }
            if (tvFlightLoadTimer) clearTimeout(tvFlightLoadTimer);
            return new Promise(function (resolve) {
                tvFlightLoadTimer = setTimeout(function () {
                    tvFlightLoadTimer = null;
                    var allHotels = opts.allHotels || tvLastResults || [];
                    var base = typeof TV_API_BASE !== 'undefined' ? TV_API_BASE : '';
                    if (!base || !allHotels.length || typeof thLoadTourFlightsForHotels !== 'function') {
                        if (opts.onDone) opts.onDone();
                        resolve();
                        return;
                    }
                    var loadGen = window.__thFlightsLoadGen;
                    var depActiveFl = tvEffectiveSearchDeparture();
                    var depCityMainFl = depActiveFl.name || 'Самара';
                    var container = document.getElementById('tv-search-results');
                    var priorityIds = {};
                    (priorityHotels || []).forEach(function (h) {
                        var tid = getMainTourId(h);
                        if (tid) priorityIds[tid] = true;
                    });
                    thLoadTourFlightsForHotels(allHotels, {
                        apiBase: base,
                        departureCity: depCityMainFl,
                        departureId: depActiveFl.id,
                        maxTours: 0,
                        maxConcurrent: 4,
                        waveSize: 12,
                        patchEvery: 1,
                        loadGen: loadGen,
                        priorityTourIds: priorityIds,
                        getTourId: getMainTourId,
                        patchContainer: container,
                        onDone: function () {
                            if (loadGen !== window.__thFlightsLoadGen) {
                                if (opts.onDone) opts.onDone();
                                resolve();
                                return;
                            }
                            if (container && typeof thLoadFlightsForVisibleCards === 'function') {
                                thLoadFlightsForVisibleCards(container, {
                                    apiBase: base,
                                    departureCity: depCityMainFl,
                                    departureId: depActiveFl.id,
                                    maxConcurrent: 4,
                                    waveSize: 12,
                                    patchEvery: 1,
                                    loadGen: loadGen,
                                    patchContainer: container,
                                    onDone: function () {
                                        if (opts.onDone) opts.onDone();
                                        resolve();
                                    }
                                });
                                return;
                            }
                            if (opts.onDone) opts.onDone();
                            resolve();
                        }
                    });
                }, opts.immediate ? 0 : 120);
            });
        }

        function tvTourStartYmd(tour) {
            if (!tour) return '';
            const raw = String(tour.date || tour.startDate || tour.departureDate || '').trim();
            const m = raw.match(/^(\d{4}-\d{2}-\d{2})/);
            return m ? m[1] : '';
        }
        function tvTourReturnYmd(startYmd, nightsNum) {
            if (!startYmd || !nightsNum) return '';
            const p = startYmd.split('-');
            if (p.length !== 3) return '';
            const d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10), 12, 0, 0);
            if (isNaN(d.getTime())) return '';
            d.setDate(d.getDate() + nightsNum);
            const pad = (n) => String(n).padStart(2, '0');
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
        }

        function thTourPhotoNormalizeKey(u) {
            if (!u || typeof u !== 'string') return '';
            const s = u.trim();
            if (!s) return '';
            try {
                const abs = /^https?:/i.test(s) ? s : (s.indexOf('//') === 0 ? 'https:' + s : 'https://' + s.replace(/^\/+/, ''));
                const x = new URL(abs);
                const host = x.hostname.toLowerCase().replace(/^www\./, '');
                let path = (x.pathname || '/').replace(/\/+/g, '/');
                if (path.length > 1) path = path.replace(/\/+$/, '');
                const search = x.search || '';
                return host + path + search;
            } catch (e) {
                return s.toLowerCase().replace(/\/+$/, '');
            }
        }
        function tvHotelPhotoUrls(h) {
            const fallback = 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400&q=80';
            let raw = [];
            if (window.THTourCard && typeof window.THTourCard.collectHotelPhotoRawUrls === 'function') {
                raw = window.THTourCard.collectHotelPhotoRawUrls(h);
            } else if (h) {
                raw.push((h.picturelink || h.pictureLink || '').toString());
                const pics = h.pictures;
                if (pics && Array.isArray(pics)) {
                    pics.forEach((p) => {
                        if (typeof p === 'string') raw.push(p);
                        else if (p && typeof p === 'object') raw.push(String(p.src || p.url || p.link || p.picturelink || p.pictureLink || ''));
                    });
                }
                const hid = parseInt(String(h.id || ''), 10);
                if (!raw.filter(Boolean).length && hid > 0) raw.push('hotel_pics/main400/' + hid + '.jpg');
            }
            const urls = [];
            const seen = {};
            const add = (u) => {
                if (!u || typeof u !== 'string') return;
                const t = u.trim();
                if (!t) return;
                const k = thTourPhotoNormalizeKey(t);
                if (!k || seen[k]) return;
                seen[k] = true;
                urls.push(t);
            };
            if (!h) return [fallback];
            raw.forEach(add);
            if (urls.length === 0) urls.push(fallback);
            return urls.slice(0, (window.THTourCard && window.THTourCard.PHOTO_SLIDE_MAX) ? window.THTourCard.PHOTO_SLIDE_MAX : 6);
        }

        function tvCardPrimaryImage(h) {
            const raw = tvHotelPhotoUrls(h);
            for (let i = 0; i < raw.length; i++) {
                const u = getTourvisorImageUrl(raw[i]);
                if (u && u.indexOf('unsplash.com') === -1) return u;
            }
            return raw.length ? getTourvisorImageUrl(raw[0]) : getTourvisorImageUrl('');
        }

        function renderTvResults(hotels) {
            const container = document.getElementById('tv-search-results');
            if (!container) return;
            hotels = (hotels || []).filter(function (h) { return tvHotelListPrice(h) > 0; });
                        if (hotels.length === 0) {
                var nf0 = typeof tvNightsFrom !== 'undefined' ? tvNightsFrom : 6;
                var nt0 = typeof tvNightsTo !== 'undefined' ? tvNightsTo : 9;
                container.innerHTML = tvEmptyResultsHtml(nf0, nt0, false);
                return;
            }
            const depActive = tvEffectiveSearchDeparture();
            const departureCity = depActive.name || 'Самара';
            const departureIdMain = depActive.id || '7';
            const tourDetailBase = (typeof TOUR_DETAIL_BASE !== 'undefined' ? TOUR_DETAIL_BASE : '') || '/frontend';
            if (window.THTourCard && typeof window.THTourCard.render === 'function') {
                const priceAdults = Math.max(1, Math.min(9, parseInt(tvAdultsCount, 10) || 2));
                const hotelMode = thIsHotelSearchMode();
                container.innerHTML = hotels.map(h => {
                    const tour = tvHotelCheapestTour(h);
                    const region = h.region?.name || '';
                    const country = h.country?.name || '';
                    const meal = tour.meal?.russianName || tour.meal?.name || '';
                    const nightsNum = parseInt(String(tour.nights || ''), 10) || 0;
                    const startYmd = tvTourStartYmd(tour);
                    const retYmd = (startYmd && nightsNum) ? tvTourReturnYmd(startYmd, nightsNum) : '';
                    const price = tvHotelListPrice(h);
                    let link = h.hotelDescriptionLink || h.hoteldescriptionlink || h.link || '';
                    if (typeof TourLinkUtils !== 'undefined' && TourLinkUtils.sanitizeTourLink) {
                        link = TourLinkUtils.sanitizeTourLink(link) || '';
                    }
                    const tourId = getMainTourId({ tours: [tour] });
                    const opName = (tour.operator && (tour.operator.russianName || tour.operator.name))
                        || tour.operatorName || '';
                    const cardImg = tvCardPrimaryImage(h);
                    const params = {
                        tour_link: link, country, hotel_name: (h.name || ''),
                        price: String(price), nights: String(tour.nights || ''), meal,
                        room_category: (tour.roomType || h.roomCategory || 'Стандарт').toString().trim() || 'Стандарт',
                        region, departure_city: departureCity,
                        image: cardImg || '',
                        rating: String(h.rating || ''), category: String(h.category || ''),
                        adults: String(priceAdults), tour_id: tourId
                    };
                    if (opName) params.tour_operator = String(opName).trim();
                    var childsStr = tvChildsParam();
                    if (childsStr) params.childs = childsStr;
                    if (startYmd) params.date_from = startYmd;
                    if (retYmd) params.date_to = retYmd;
                    if (departureIdMain) params.departure_id = departureIdMain;
                    if (h.id) params.hotel_id = String(h.id);
                    try {
                        if (window.TourSessionManager && typeof window.TourSessionManager.buildReturnUrl === 'function') {
                            params.return_url = window.TourSessionManager.buildReturnUrl();
                        } else {
                            var retU = new URL(window.location.href);
                            retU.searchParams.set('tv_restore', '1');
                            params.return_url = retU.pathname + (retU.search || '');
                        }
                    } catch (eRet) {}
                    var hotelHref = h.id
                        ? (tourDetailBase + '/window/hotels/tv-hotel-detail.php?id=' + encodeURIComponent(h.id)
                            + '&countryId=' + encodeURIComponent((h.country && h.country.id) || document.getElementById('tv-country')?.value || '')
                            + (hotelMode ? ('&departureId=' + encodeURIComponent(String(TH_HOTEL_DEPARTURE_ID))
                                + '&departureName=' + encodeURIComponent(TH_HOTEL_DEPARTURE_NAME)) : ''))
                        : '';
                    const tourHref = country ? (tourDetailBase + '/window/tour-detail.php?' + new URLSearchParams(params).toString()) : (link || '#');
                    const cardHref = (hotelMode && hotelHref) ? hotelHref : tourHref;
                    return window.THTourCard.render(h, {
                        tour, getImageUrl: getTourvisorImageUrl, imageProxy: TV_IMAGE_PROXY,
                        image: cardImg, detailUrl: cardHref,
                        adults: priceAdults, childAges: tvChildAgesForSearch(), dateFrom: startYmd, dateTo: retYmd,
                        price, departureCity, departureId: departureIdMain, carousel: true,
                        compareEnabled: true,
                        priceLabel: hotelMode ? 'проживание от' : null,
                        hideFlight: !!hotelMode,
                        directBadge: !!(!hotelMode && (
                            (window.__tvSearchFlightFlags && window.__tvSearchFlightFlags.onlyDirect)
                            || (tourId && (function () {
                                var fm = tvFlightMetaLookup(tourId, departureCity);
                                return fm && fm.direct === true;
                            })())
                        )),
                        transferBadge: !!(!hotelMode && !(window.__tvSearchFlightFlags && window.__tvSearchFlightFlags.onlyDirect) && (function () {
                            var fm = tvFlightMetaLookup(tourId, departureCity);
                            return fm && fm.direct === false;
                        })()),
                        flightMeta: (!hotelMode && tourId) ? tvFlightMetaLookup(tourId, departureCity) : null
                    });
                }).join('');
                document.getElementById('tv-result-count').textContent = tvLastResults.length;
                thSyncResultNoun(tvLastResults.length);
                updateTvLoadMoreButton();
                if (window.THTourCard && typeof window.THTourCard.mountInContainer === 'function') {
                    window.THTourCard.mountInContainer(container);
                } else if (window.THTourCard && typeof window.THTourCard.ensureCarouselsInContainer === 'function') {
                    window.THTourCard.ensureCarouselsInContainer(container);
                } else if (window.THTourCard && typeof window.THTourCard.kickImagesInContainer === 'function') {
                    window.THTourCard.kickImagesInContainer(container);
                }
                return;
            }
            container.innerHTML = hotels.map(h => {
                const photoUrls = tvHotelPhotoUrls(h);
                const slideDedup = {};
                const slideSrcs = [];
                photoUrls.forEach((u) => {
                    const s = getTourvisorImageUrl(u);
                    if (!s || !String(s).trim()) return;
                    if (slideDedup[s]) return;
                    slideDedup[s] = true;
                    slideSrcs.push(s);
                });
                const fallbackImg = 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=600&q=80';
                const slideMax = (window.THTourCard && window.THTourCard.PHOTO_SLIDE_MAX) ? window.THTourCard.PHOTO_SLIDE_MAX : 6;
                const slidesForCard = (slideSrcs.length ? slideSrcs : [fallbackImg]).slice(0, slideMax);
                const img = slidesForCard[0];
                const region = h.region?.name || '';
                const country = h.country?.name || '';
                const tour = tvHotelCheapestTour(h);
                const meal = tour.meal?.russianName || tour.meal?.name || '';
                const nights = tour.nights || '';
                const nightsNum = parseInt(String(nights), 10) || 0;
                const startYmd = tvTourStartYmd(tour);
                const retYmd = (startYmd && nightsNum) ? tvTourReturnYmd(startYmd, nightsNum) : '';
                const price = tvHotelListPrice(h);
                const hotelModeLegacy = thIsHotelSearchMode();
                const rating = h.rating || 0;
                let link = h.hotelDescriptionLink || h.hoteldescriptionlink || h.link || '';
                if (typeof TourLinkUtils !== 'undefined' && TourLinkUtils.sanitizeTourLink) {
                    link = TourLinkUtils.sanitizeTourLink(link) || '';
                }
                const hasCountry = country && country.length > 0;
                const desc = (h.description || h.hotelDescription || h.descr || '').toString().trim();
                const tourId = getMainTourId(h);
                const flightData = tvFlightMetaLookup(tourId, departureCity);
                const airlineLabel = (flightData && flightData.companies && flightData.companies[0]) ? flightData.companies[0] : '—';
                var pickTourists = tvAdultsCount + ' взр.';
                if (tvChildrenAges && tvChildrenAges.length > 0) pickTourists += ', ' + tvChildrenAges.length + ' реб.';
                const roomCategory = (tour.roomType || h.roomCategory || 'Стандарт').toString().trim() || 'Стандарт';
                const params = {
                    tour_link: link,
                    country: country,
                    hotel_name: (h.name || ''),
                    price: formatPrice(price),
                    nights: String(nights),
                    meal: meal,
                    room_category: roomCategory,
                    region: region,
                    departure_city: departureCity,
                    image: img,
                    description: desc ? desc.substring(0, 4000) : '',
                    rating: String(h.rating || ''),
                    category: String(h.category || ''),
                };
                if (startYmd) params.date_from = startYmd;
                if (retYmd) params.date_to = retYmd;
                try {
                    if (window.TourSessionManager && typeof window.TourSessionManager.buildReturnUrl === 'function') {
                        params.return_url = window.TourSessionManager.buildReturnUrl();
                    } else {
                        var retU = new URL(window.location.href);
                        retU.searchParams.set('tv_restore', '1');
                        params.return_url = retU.pathname + (retU.search || '');
                    }
                } catch (e) {
                    try {
                        params.return_url = window.location.pathname + window.location.search + (window.location.search.indexOf('?') >= 0 ? '&' : '?') + 'tv_restore=1';
                    } catch (e2) {}
                }
                if (tourId) params.tour_id = tourId;
                if (h.id) params.hotel_id = String(h.id);
                params.adults = String(Math.max(1, Math.min(9, parseInt(tvAdultsCount, 10) || 2)));
                var childsLegacy = tvChildsParam();
                if (childsLegacy) params.childs = childsLegacy;
                if (departureIdMain) params.departure_id = departureIdMain;
                const tourDetailUrl = hasCountry ? (tourDetailBase + '/window/tour-detail.php?' + new URLSearchParams(params).toString()) : '#';
                let cardHref = tourDetailUrl !== '#' ? tourDetailUrl : (link || '#');
                if (window.THTourCard && typeof window.THTourCard.appendGalleryToDetailUrl === 'function') {
                    cardHref = window.THTourCard.appendGalleryToDetailUrl(cardHref, slidesForCard);
                }
                const priceAdults = Math.max(1, Math.min(9, parseInt(tvAdultsCount, 10) || 2));
                // Звёзды отеля
                const catNum = parseInt(String(h.category || ''), 10) || 0;
                const starsHtml = catNum > 0 ? '★'.repeat(Math.min(catNum, 5)) : '';
                // Форматирование дат
                const fmtDate = (ymd) => { if (!ymd) return ''; const [y,m,d] = ymd.split('-'); return `${d}.${m}.${String(y).slice(2)}`; };
                const pricePartyLabel = hotelModeLegacy ? 'проживание от' : tvPartyPriceLabel(priceAdults);
                const partySummary = tvPartySummaryLabel(priceAdults);
                const datesMeta = (startYmd && retYmd)
                    ? `${fmtDate(startYmd)} – ${fmtDate(retYmd)}, ${nightsNum} ${nightsNum === 1 ? 'ночь' : (nightsNum < 5 ? 'ночи' : 'ночей')}, ${partySummary}`
                    : (nights ? `${nights} ${nightsNum < 5 ? 'ночи' : 'ночей'}, ${partySummary}` : partySummary);
                const mediaHtml = (window.THTourCard && typeof window.THTourCard.buildCarouselMediaHtml === 'function')
                    ? window.THTourCard.buildCarouselMediaHtml(slidesForCard, { fallbackImg, hotelName: h.name || '' })
                    : `<div class="th-tour-card__media th-tour-card__media--carousel"><div class="th-tour-card__strip-scroll" tabindex="-1">${slidesForCard.map((src, idx) => {
                        const esc = String(src).replace(/"/g, '&quot;');
                        return `<img src="${esc}" alt="" class="th-tour-card__strip-img" loading="eager" decoding="async" onerror="this.onerror=null;this.src='${fallbackImg}'">`;
                    }).join('')}</div></div>`;
                return `
                <article class="th-tour-card"${h.id ? ' data-th-hotel-id="' + String(h.id).replace(/"/g, '&quot;') + '"' : ''}>
                    <a href="${cardHref}" class="th-tour-card__link th-tour-card__link--main">
                        ${mediaHtml}
                        <div class="th-tour-card__body">
                            <p class="th-tour-card__geo">${(country + (region ? ', ' + region : '')).replace(/</g,'&lt;')}</p>
                            <div class="th-tour-card__name-row">
                                <h3 class="th-tour-card__name">${(h.name || '').replace(/</g,'&lt;')}</h3>
                                ${starsHtml ? `<span class="th-tour-card__stars">${starsHtml}</span>` : ''}
                            </div>
                            ${meal ? `<span class="th-tour-card__meal-badge">${meal.replace(/</g,'&lt;')}</span>` : ''}
                            <div class="th-tour-card__price-block">
                                <span class="th-tour-card__price-label">${pricePartyLabel.replace(/</g,'&lt;')}</span>
                                <span class="th-tour-card__price">${formatPrice(price)}</span>
                                ${datesMeta ? `<span class="th-tour-card__dates">${datesMeta.replace(/</g,'&lt;')}</span>` : ''}
                            </div>
                            <span class="th-tour-card__btn">Выбрать этот тур</span>
                        </div>
                    </a>
                </article>`;
            }).join('');
            document.getElementById('tv-result-count').textContent = tvLastResults.length;
            updateTvLoadMoreButton();
            if (window.THTourCard && typeof window.THTourCard.ensureCarouselsInContainer === 'function') {
                window.THTourCard.ensureCarouselsInContainer(container);
            } else if (window.THTourCard && typeof window.THTourCard.kickImagesInContainer === 'function') {
                window.THTourCard.kickImagesInContainer(container);
            }
        }
    </script>

    <script>
        (function () {
            var supportsIO = ('IntersectionObserver' in window);
            var nodes = document.querySelectorAll('.reveal-on-scroll');
            if (!nodes || !nodes.length) {
                // Даже если сейчас нет элементов, будем отслеживать появление
                nodes = [];
            }

            if (!supportsIO) {
                (nodes || []).forEach(function (el) { el.classList.add('in-view'); });
                return;
            }

            var obs = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('in-view');
                        obs.unobserve(entry.target);
                    }
                });
            /* threshold/rootMargin: мягче для мобильных WebView (в т.ч. Яндекс.Браузер), иначе блоки остаются opacity:0 */
            }, { threshold: 0, rootMargin: '0px 0px 8% 0px' });

            function watch(root) {
                if (!root || root.nodeType !== 1) return;
                if (!root.querySelectorAll) return;
                /* querySelectorAll не включает сам root — динамические карточки (напр. «Популярные направления») иначе остаются opacity:0 */
                if (root.matches && root.classList && root.classList.contains('reveal-on-scroll')) {
                    obs.observe(root);
                }
                var list = root.querySelectorAll('.reveal-on-scroll');
                if (!list || !list.length) return;
                list.forEach(function (el) { obs.observe(el); });
            }

            // Старт: что уже есть в DOM
            watch(document);

            // Динамика: что добавляется позже JS-ом
            var mo = new MutationObserver(function (muts) {
                muts.forEach(function (m) {
                    if (m.addedNodes && m.addedNodes.length) {
                        m.addedNodes.forEach(function (n) { watch(n); });
                    }
                });
            });
            mo.observe(document.body, { childList: true, subtree: true });

            /* UX-страховка: контент не должен оставаться невидимым, даже если
               IntersectionObserver не сработал (старые WebView, ошибки). Через 2.5с
               показываем всё, что так и не получило .in-view. */
            function revealFallback() {
                document.querySelectorAll('.reveal-on-scroll:not(.in-view)').forEach(function (el) {
                    el.classList.add('in-view');
                });
            }
            setTimeout(revealFallback, 2500);
            window.addEventListener('load', function () { setTimeout(revealFallback, 1200); });
        })();
    </script>

    <script>
        (function () {
            var link = document.querySelector('a[href="#tour-search-section"]');
            var target = document.getElementById('tour-search-section');
            if (!link || !target) return;

            link.addEventListener('click', function (e) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                try {
                    if (history && history.pushState) history.pushState(null, '', '#tour-search-section');
                    else window.location.hash = '#tour-search-section';
                } catch (err) {}
            }, { passive: false });
        })();
    </script>
    <script>
        (function () {
            function bindNightsPopupFallback() {
                var trigger = document.getElementById('tv-nights-trigger');
                var summaryBtn = document.getElementById('tv-nights-summary');
                var popup = document.getElementById('tv-nights-popup');
                var popupCard = document.getElementById('tv-nights-popup-card');
                var grid = document.getElementById('tv-nights-grid');
                var applyBtn = document.getElementById('tv-nights-apply');
                if (!trigger || !popup || !grid || !applyBtn) return;
                if (popup.dataset.fallbackBound === '1') return;
                popup.dataset.fallbackBound = '1';
                var selectFrom = true;
                var nightsFrom = Number.isFinite(parseInt(window.tvNightsFrom, 10)) ? parseInt(window.tvNightsFrom, 10) : 6;
                var nightsTo = Number.isFinite(parseInt(window.tvNightsTo, 10)) ? parseInt(window.tvNightsTo, 10) : 9;

                function nWord(n) {
                    return n % 10 === 1 && n % 100 !== 11 ? 'ночь' : (n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 10 || n % 100 >= 20) ? 'ночи' : 'ночей');
                }

                function updatePreview() {
                    var preview = document.getElementById('tv-nights-draft-preview');
                    if (preview) {
                        preview.textContent = nightsFrom === nightsTo
                            ? (nightsFrom + ' ' + nWord(nightsFrom))
                            : (nightsFrom + '–' + nightsTo + ' ' + nWord(nightsTo));
                    }
                    var hintEl = document.getElementById('tv-nights-hint');
                    if (hintEl) {
                        hintEl.textContent = selectFrom
                            ? 'Свой диапазон: нажмите число «от»'
                            : 'Теперь нажмите число «до»';
                    }
                }

                function paintGrid() {
                    grid.querySelectorAll('.tv-nights-cell').forEach(function (cell) {
                        var n = parseInt(cell.getAttribute('data-n'), 10);
                        cell.classList.remove('is-from', 'is-to', 'is-in-range', 'text-white');
                        if (n === nightsFrom) {
                            cell.classList.add('is-from', 'text-white');
                        } else if (n === nightsTo && nightsTo !== nightsFrom) {
                            cell.classList.add('is-to', 'text-white');
                        } else if (n > nightsFrom && n < nightsTo) {
                            cell.classList.add('is-in-range');
                        }
                    });
                }

                function openPopup(e) {
                    if (e) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    nightsFrom = Number.isFinite(parseInt(window.tvNightsFrom, 10)) ? parseInt(window.tvNightsFrom, 10) : 6;
                    nightsTo = Number.isFinite(parseInt(window.tvNightsTo, 10)) ? parseInt(window.tvNightsTo, 10) : 9;
                    popup.style.zIndex = '10260';
                    popup.classList.remove('hidden');
                    popup.classList.add('is-open');
                    popup.style.display = 'flex';
                    popup.setAttribute('aria-hidden', 'false');
                    if (popupCard) popupCard.style.pointerEvents = 'auto';
                    selectFrom = true;
                    updatePreview();
                    paintGrid();
                }

                function closePopup() {
                    popup.classList.add('hidden');
                    popup.classList.remove('is-open');
                    popup.style.display = 'none';
                    popup.setAttribute('aria-hidden', 'true');
                }

                function commitNights() {
                    if (nightsFrom > 28) nightsFrom = 28;
                    if (nightsTo > 28) nightsTo = 28;
                    if (nightsTo < nightsFrom) nightsTo = nightsFrom;
                    window.tvNightsFrom = nightsFrom;
                    window.tvNightsTo = nightsTo;
                    var summaryText = document.getElementById('tv-nights-summary-text');
                    if (summaryText) {
                        summaryText.textContent = nightsFrom === nightsTo
                            ? (nightsFrom + ' ' + nWord(nightsFrom))
                            : (nightsFrom + '–' + nightsTo + ' ' + nWord(nightsTo));
                    }
                    try {
                        document.dispatchEvent(new CustomEvent('th:wizard-nights-done'));
                    } catch (eN) {}
                }

                window.__thWizardOpenNightsPopup = openPopup;

                trigger.addEventListener('click', openPopup);
                if (summaryBtn) summaryBtn.addEventListener('click', openPopup);
                function handleCellSelect(btn, e) {
                    if (!btn) return;
                    if (e) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    var n = parseInt(btn.getAttribute('data-n'), 10);
                    if (!Number.isFinite(n) || n < 1 || n > 28) return;
                    if (selectFrom) {
                        nightsFrom = n;
                        nightsTo = n;
                        selectFrom = false;
                    } else {
                        if (n < nightsFrom) {
                            nightsTo = nightsFrom;
                            nightsFrom = n;
                        } else {
                            nightsTo = n;
                        }
                        selectFrom = true;
                    }
                    updatePreview();
                    paintGrid();
                }
                grid.addEventListener('click', function (e) {
                    var btn = e.target.closest('.tv-nights-cell');
                    handleCellSelect(btn, e);
                });
                grid.querySelectorAll('.tv-nights-cell').forEach(function (btn) {
                    btn.addEventListener('touchend', function (e) { handleCellSelect(btn, e); }, { passive: false });
                });
                applyBtn.addEventListener('click', function () {
                    commitNights();
                    closePopup();
                });
                popup.querySelectorAll('[data-th-nights-close]').forEach(function (el) {
                    el.addEventListener('click', closePopup);
                });
                popup.addEventListener('click', function (e) {
                    if (e.target === popup || e.target.classList.contains('th-coral-popup__backdrop')) closePopup();
                });
                if (popupCard) {
                    popupCard.addEventListener('click', function (e) { e.stopPropagation(); });
                    popupCard.addEventListener('touchend', function (e) { e.stopPropagation(); }, { passive: true });
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bindNightsPopupFallback);
            } else {
                bindNightsPopupFallback();
            }
        })();
    </script>
    <!-- Модалка «Ночей» вне hero/карточки: иначе overflow/transform/backdrop в предках обрезают fixed в WebKit/Яндекс.Браузер -->
    <div id="tv-nights-popup" class="th-coral-popup th-coral-nights-popup hidden" aria-hidden="true">
        <div class="th-coral-popup__backdrop" data-th-nights-close></div>
        <div id="tv-nights-popup-card" class="th-coral-popup__panel" role="dialog" aria-label="Сколько ночей в отеле">
            <div class="th-coral-popup__head">
                <div class="th-coral-popup__head-main">
                    <span class="th-coral-popup__eyebrow">Travel Hub</span>
                    <span class="th-coral-popup__title">Сколько ночей?</span>
                </div>
                <button type="button" class="th-coral-popup__close" data-th-nights-close aria-label="Закрыть">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="th-coral-popup__body">
                <div class="tv-nights-range-bar" aria-live="polite">
                    <div class="tv-nights-range-bar__item"><span>От</span><strong id="tv-nights-from-label">6</strong></div>
                    <div class="tv-nights-range-bar__sep">—</div>
                    <div class="tv-nights-range-bar__item"><span>До</span><strong id="tv-nights-to-label">9</strong></div>
                </div>
                <p id="tv-nights-draft-preview" class="th-coral-popup__preview" aria-hidden="true">6–9 ночей</p>
                <div id="tv-nights-quick" class="tv-nights-quick" aria-label="Быстрый выбор ночей"></div>
                <p id="tv-nights-hint" class="th-coral-popup__hint">Свой диапазон: сначала «от», потом «до»</p>
                <div class="tv-nights-legend th-flight-legend th-flight-legend--nights" aria-hidden="true">
                    <span class="th-flight-legend__chip th-flight-legend__chip--fly"><span class="th-flight-legend__mark" aria-hidden="true"></span>Есть вылеты</span>
                    <span class="th-flight-legend__chip th-flight-legend__chip--direct"><span class="th-flight-legend__mark" aria-hidden="true"></span>Прямой рейс</span>
                    <span class="th-flight-legend__chip th-flight-legend__chip--off"><span class="th-flight-legend__mark" aria-hidden="true"></span>Нет под фильтром</span>
                </div>
                <div id="tv-nights-grid" class="tv-nights-grid th-coral-nights-popup__grid">
                    <?php for ($n = 1; $n <= 28; $n++): ?>
                    <button type="button" class="tv-nights-cell" data-n="<?php echo $n; ?>">
                        <span class="cell-num"><?php echo $n; ?></span>
                        <span class="cell-label"><?php
                            if ($n === 1) echo 'ночь';
                            elseif ($n >= 2 && $n <= 4) echo 'ночи';
                            else echo 'ночей';
                        ?></span>
                    </button>
                    <?php endfor; ?>
                </div>
            </div>
            <button type="button" id="tv-nights-apply" class="th-coral-popup__apply">
                <i class="fas fa-check" aria-hidden="true"></i> Применить
            </button>
        </div>
    </div>
    <?php include __DIR__ . '/../backend/components/performance_scripts.php'; ?>
    <script>
    (function () {
        var YM = <?php echo json_encode($th_ym_id, JSON_UNESCAPED_UNICODE); ?>;
        function ymg(g) {
            try {
                var id = YM && String(YM).replace(/\D/g, '');
                if (id && typeof ym === 'function') ym(parseInt(id, 10), 'reachGoal', g);
            } catch (e) {}
        }
        document.addEventListener('DOMContentLoaded', function () {
            var f = document.getElementById('main-quick-lead-form');
            var msg = document.getElementById('main-quick-lead-msg');
            if (!f) return;
            f.addEventListener('submit', function (ev) {
                ev.preventDefault();
                var fd = new FormData(f);
                var payload = {
                    name: String(fd.get('name') || '').trim(),
                    phone: String(fd.get('phone') || '').trim(),
                    agree: !!fd.get('agree'),
                    website: String(fd.get('website') || ''),
                    message: 'Главная: быстрая заявка «подберём сами».'
                };
                if (!payload.name || !payload.phone) {
                    if (msg) { msg.textContent = 'Укажите имя и телефон.'; msg.className = 'text-xs rounded-lg p-2 bg-red-100 text-red-900 block'; msg.classList.remove('hidden'); }
                    return;
                }
                if (!payload.agree) {
                    if (msg) { msg.textContent = 'Нужно согласие на обработку данных.'; msg.className = 'text-xs rounded-lg p-2 bg-red-100 text-red-900 block'; msg.classList.remove('hidden'); }
                    return;
                }
                fetch('/backend/api/uon-lead.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                    .then(function (r) { return r.json().catch(function () { return { success: false }; }); })
                    .then(function (data) {
                        if (data && data.success) {
                            ymg('main_quick_lead_ok');
                            if (window.THLeadCapture) window.THLeadCapture.reachGoal('lead_ok');
                            else ymg('lead_ok');
                            f.reset();
                            if (msg) {
                                msg.textContent = 'Заявка отправлена. Перезвоним за 15 минут.';
                                msg.className = 'text-xs rounded-lg p-2 bg-emerald-100 text-emerald-900 block';
                                msg.classList.remove('hidden');
                            }
                        } else {
                            ymg('main_quick_lead_err');
                            if (msg) {
                                msg.textContent = (data && data.error) ? data.error : 'Ошибка отправки.';
                                msg.className = 'text-xs rounded-lg p-2 bg-red-100 text-red-900 block';
                                msg.classList.remove('hidden');
                            }
                        }
                    })
                    .catch(function () {
                        ymg('main_quick_lead_err');
                        if (msg) {
                            msg.textContent = 'Нет связи. Попробуйте позже.';
                            msg.className = 'text-xs rounded-lg p-2 bg-red-100 text-red-900 block';
                            msg.classList.remove('hidden');
                        }
                    });
            });
        });
    })();
    </script>


    <!-- ===== QUICK BOOKING MODAL (упрощённый) ===== -->
    <div id="quick-booking-modal" class="th-qbm-modal" style="display:none;">
        <div class="th-qbm-modal__backdrop" id="qbm-backdrop"></div>
        <div class="th-qbm-modal__panel">
            <button type="button" id="qbm-close" class="th-qbm-modal__close" aria-label="Закрыть">✕</button>
        <h3 class="th-qbm-modal__title">Подберём тур для вас</h3>
        <p class="th-qbm-modal__sub">Перезвоним за 15 минут. Без спама.</p>
        <p class="th-qbm-modal__proof"><i class="fas fa-clock"></i> Ответ за 15 минут · <i class="fas fa-shield-alt"></i> Без спама</p>
        <form id="qbm-form" class="th-qbm-modal__form">
            <div class="th-qbm-modal__field"><input type="text" name="name" placeholder="ФИО" required maxlength="100" class="th-qbm-modal__input" autocomplete="name"></div>
            <input type="tel" name="phone" placeholder="+7 (___) ___-__-__" required class="th-qbm-modal__input">
                <label class="th-qbm-modal__agree">
                    <input type="checkbox" name="agree" required>
                    <span><?php echo th_legal_consent_checkbox_html(); ?></span>
                </label>
                <input type="text" name="website" class="th-qbm-modal__hp" tabindex="-1" autocomplete="off">
                <div id="qbm-msg" class="th-qbm-modal__msg"></div>
                <button type="submit" id="qbm-submit" class="th-qbm-modal__submit">Отправить заявку менеджеру</button>
            </form>
        </div>
    </div>

    <!-- ===== ФИЛЬТРЫ: центральное модальное окно (оверлей + диалог) ===== -->
    <div id="tv-filters-modal-overlay" class="tv-filters-modal-overlay" aria-hidden="true"></div>
    <div id="tv-filters-modal" class="tv-filters-modal" role="dialog" aria-modal="true" aria-labelledby="tv-filters-modal-h" hidden>
        <button type="button" id="tv-filters-modal-close" class="tv-filters-modal__close" aria-label="Закрыть">×</button>
        <h2 id="tv-filters-modal-h" class="tv-filters-modal__title">Расширенные фильтры</h2>
        <p class="tv-filters-modal__intro">Питание, курорт и звёзды — в форме поиска ниже. Здесь — бюджет и линия пляжа.</p>
        <div class="tv-filters-modal__field">
            <span class="tv-filters-modal__lbl">Береговая линия</span>
            <div class="tv-filters-modal__stars tv-filters-modal__beach" role="group" aria-label="Береговая линия">
                <button type="button" class="tv-filters-modal__star-chip is-active" data-tv-beach="" aria-pressed="true">Любая</button>
                <button type="button" class="tv-filters-modal__star-chip" data-tv-beach="1" aria-pressed="false">1-я линия</button>
                <button type="button" class="tv-filters-modal__star-chip" data-tv-beach="2" aria-pressed="false">2-я линия</button>
            </div>
        </div>
        <div class="tv-filters-modal__field">
            <span class="tv-filters-modal__lbl">Бюджет за тур, ₽</span>
            <div class="tv-filters-modal__budget-row">
                <input type="number" id="tv-price-min" class="tv-filter-field" inputmode="numeric" min="0" step="1000" placeholder="От" autocomplete="off">
                <span aria-hidden="true">—</span>
                <input type="number" id="tv-price-max" class="tv-filter-field" inputmode="numeric" min="0" step="1000" placeholder="До" autocomplete="off">
            </div>
        </div>
        <button type="button" id="tv-filters-modal-apply" class="tv-filters-modal__apply">Применить</button>
        <button type="button" id="tv-filters-modal-reset" class="tv-filters-modal__reset">Сбросить</button>
    </div>

    <script>
    /* ===== REDESIGN v2: Compact Search + Popups + Sticky CTA ===== */
    (function() {
        'use strict';

        /* ══════════════════════════════════════════════
           ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
        ══════════════════════════════════════════════ */

        /** Форматирует Date → 'dd-mm-yyyy' (формат flatpickr) */
        function fmtDate(d) {
            return String(d.getDate()).padStart(2,'0') + '-' +
                   String(d.getMonth()+1).padStart(2,'0') + '-' +
                   d.getFullYear();
        }

        /** Красивый лейбл дат для кнопки-триггера */
        function fmtLabel(d) {
            return String(d.getDate()).padStart(2,'0') + '.' + String(d.getMonth()+1).padStart(2,'0');
        }
        /** Лейбл с годом — для превью в попапе календаря */
        function fmtLabelFull(d) {
            return fmtLabel(d) + '.' + d.getFullYear();
        }
        /** Компактный диапазон для узкой кнопки (без пробелов вокруг тире) */
        function fmtCompactRange(d0, d1) {
            if (!d0 || !d1) return '';
            return fmtLabel(d0) + '–' + fmtLabel(d1);
        }

        /** Мин. дата — сегодня (локальная полночь). */
        function tvCalendarMinDate() {
            var d = new Date();
            d.setHours(0, 0, 0, 0);
            return d;
        }

        /** Макс. дата — ровно +1 год от сегодня (можно бронировать на следующий год). */
        function tvCalendarMaxDate() {
            var d = tvCalendarMinDate();
            d.setFullYear(d.getFullYear() + 1);
            return d;
        }

        function tvCalendarApplyBounds(fp) {
            if (!fp || typeof fp.set !== 'function') return;
            var minD = tvCalendarMinDate();
            var maxD = tvCalendarMaxDate();
            fp.set('minDate', minD);
            fp.set('maxDate', maxD);
            if (typeof fp.redraw === 'function') fp.redraw();
            tvCalendarRefreshUI(fp);
        }

        function tvCalendarMonthLonghand() {
            try {
                if (typeof flatpickr !== 'undefined' && flatpickr.l10n && flatpickr.l10n.ru && flatpickr.l10n.ru.months) {
                    return flatpickr.l10n.ru.months.longhand;
                }
            } catch (eMonths) {}
            return ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
        }

        /** Все месяцы от min до max (включая следующий год). */
        function tvCalendarPeriodOptions(minD, maxD) {
            var labels = tvCalendarMonthLonghand();
            var opts = [];
            var cur = new Date(minD.getFullYear(), minD.getMonth(), 1);
            var end = new Date(maxD.getFullYear(), maxD.getMonth(), 1);
            while (cur.getTime() <= end.getTime()) {
                opts.push({
                    key: cur.getFullYear() + '-' + cur.getMonth(),
                    label: labels[cur.getMonth()] + ' ' + cur.getFullYear()
                });
                cur.setMonth(cur.getMonth() + 1);
            }
            return opts;
        }

        /** Заменяет узкий список месяцев flatpickr на «Месяц Год» на весь диапазон бронирования. */
        function tvCalendarEnhanceNav(fp) {
            if (!fp || !fp.calendarContainer) return;
            tvCalendarBindNavArrows(fp);

            var minD = fp.config.minDate;
            var maxD = fp.config.maxDate;
            var monthNav = fp.calendarContainer.querySelector('.flatpickr-month');
            if (!monthNav) return;
            var currentMonthDiv = monthNav.querySelector('.flatpickr-current-month');
            if (!currentMonthDiv) return;

            var nativeMonth = currentMonthDiv.querySelector('.flatpickr-monthDropdown-months:not(.tv-cal-period-select)');
            var nativeYear = currentMonthDiv.querySelector('.numInputWrapper');
            var staticMonth = currentMonthDiv.querySelector('.cur-month');
            var labels = tvCalendarMonthLonghand();
            var fallbackLabel = labels[fp.currentMonth] + ' ' + fp.currentYear;

            if (!minD || !maxD) {
                /* Без min/max — хотя бы показываем «Август 2026», не пустую шапку */
                if (staticMonth) {
                    staticMonth.style.display = '';
                    staticMonth.textContent = fallbackLabel;
                }
                return;
            }

            var opts = tvCalendarPeriodOptions(minD, maxD);
            var optsKey = String(minD.getTime()) + '-' + String(maxD.getTime());
            var select = currentMonthDiv.querySelector('.tv-cal-period-select');
            if (!select) {
                select = document.createElement('select');
                select.className = 'tv-cal-period-select flatpickr-monthDropdown-months';
                select.setAttribute('aria-label', 'Месяц и год');
                currentMonthDiv.insertBefore(select, currentMonthDiv.firstChild);
                select.addEventListener('change', function () {
                    var parts = String(select.value || '').split('-');
                    if (parts.length < 2) return;
                    var y = parseInt(parts[0], 10);
                    var m = parseInt(parts[1], 10);
                    if (isNaN(y) || isNaN(m)) return;
                    if (typeof fp.jumpToDate === 'function') {
                        fp.jumpToDate(new Date(y, m, 1), false);
                    }
                    tvCalendarRefreshUI(fp);
                });
            }
            select.style.display = '';
            select.style.visibility = 'visible';
            select.style.opacity = '1';
            select.style.color = '#fff';

            if (select.dataset.optsKey !== optsKey) {
                select.innerHTML = '';
                opts.forEach(function (o) {
                    var opt = document.createElement('option');
                    opt.value = o.key;
                    opt.textContent = o.label;
                    select.appendChild(opt);
                });
                select.dataset.optsKey = optsKey;
            }

            var selectedKey = fp.currentYear + '-' + fp.currentMonth;
            if (select.value !== selectedKey) select.value = selectedKey;

            // Прячем native только когда наш select уже с подписью месяца
            if (select.options && select.options.length) {
                if (nativeMonth) nativeMonth.style.display = 'none';
                if (nativeYear) nativeYear.style.display = 'none';
                if (staticMonth) staticMonth.style.display = 'none';
            } else if (staticMonth) {
                staticMonth.style.display = '';
                staticMonth.textContent = fallbackLabel;
            }
        }

        /** Стрелки ‹ › для листания месяцев */
        function tvCalendarBindNavArrows(fp) {
            if (!fp || !fp.calendarContainer) return;
            var monthsWrap = fp.calendarContainer.querySelector('.flatpickr-months');
            if (!monthsWrap) return;

            monthsWrap.style.display = 'flex';
            monthsWrap.style.alignItems = 'center';
            monthsWrap.style.gap = '2px';

            function ensureArrow(cls, label, delta) {
                var btn = monthsWrap.querySelector('.' + cls);
                if (!btn) {
                    btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = cls + ' tv-cal-nav-btn';
                    btn.setAttribute('aria-label', label);
                    btn.innerHTML = delta < 0 ? '&#8249;' : '&#8250;';
                    if (delta < 0) {
                        monthsWrap.insertBefore(btn, monthsWrap.firstChild);
                    } else {
                        monthsWrap.appendChild(btn);
                    }
                }
                if (btn.onclick) btn.onclick = null;
                btn.onclick = function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (btn.disabled) return;
                    if (typeof fp.changeMonth === 'function') {
                        fp.changeMonth(delta);
                    } else if (typeof fp.jumpToDate === 'function') {
                        fp.jumpToDate(new Date(fp.currentYear, fp.currentMonth + delta, 1), false);
                    }
                    window.setTimeout(function () { tvCalendarRefreshUI(fp); }, 0);
                };
            }

            ensureArrow('tv-cal-nav-prev', 'Предыдущий месяц', -1);
            ensureArrow('tv-cal-nav-next', 'Следующий месяц', 1);

            /* Скрыть дублирующие нативные стрелки flatpickr */
            monthsWrap.querySelectorAll('.flatpickr-prev-month, .flatpickr-next-month').forEach(function (el) {
                el.style.display = 'none';
            });

            tvCalendarUpdateNavDisabled(fp);
        }

        function tvCalendarUpdateNavDisabled(fp) {
            if (!fp || !fp.calendarContainer) return;
            var prevBtn = fp.calendarContainer.querySelector('.tv-cal-nav-prev');
            var nextBtn = fp.calendarContainer.querySelector('.tv-cal-nav-next');
            if (!prevBtn || !nextBtn) return;
            var minD = fp.config.minDate;
            var maxD = fp.config.maxDate;
            var cur = new Date(fp.currentYear, fp.currentMonth, 1);
            var prevDisabled = minD && cur <= new Date(minD.getFullYear(), minD.getMonth(), 1);
            var showMonths = fp.config.showMonths || 1;
            var lastVisible = new Date(fp.currentYear, fp.currentMonth + showMonths - 1, 1);
            var nextDisabled = maxD && lastVisible >= new Date(maxD.getFullYear(), maxD.getMonth(), 1);
            prevBtn.disabled = !!prevDisabled;
            nextBtn.disabled = !!nextDisabled;
            prevBtn.classList.toggle('is-disabled', !!prevDisabled);
            nextBtn.classList.toggle('is-disabled', !!nextDisabled);
        }

        function tvCalendarFixLayout(fp) {
            if (!fp || !fp.calendarContainer) return;
            var cal = fp.calendarContainer;
            cal.style.width = '100%';
            cal.style.maxWidth = '100%';
            cal.querySelectorAll('.flatpickr-innerContainer, .flatpickr-rContainer, .flatpickr-days').forEach(function (el) {
                el.style.width = '100%';
                el.style.maxWidth = '100%';
                el.style.minWidth = '0';
            });
            cal.querySelectorAll('.dayContainer').forEach(function (el) {
                el.style.width = '';
                el.style.maxWidth = '';
                el.style.minWidth = '';
            });
        }

        function tvCalendarJumpToSelection(fp) {
            if (!fp || typeof fp.jumpToDate !== 'function') return;
            var sel = fp.selectedDates;
            if (sel && sel.length >= 1) fp.jumpToDate(sel[0], false);
        }

        function tvCalendarDecorateMonths(fp) {
            if (!fp || !fp.calendarContainer) return;
            var cal = fp.calendarContainer;
            var showMonths = fp.config.showMonths || 1;
            cal.classList.toggle('tv-cal-layout-multi', showMonths > 1);

            /* Сброс прошлого wrap — иначе копятся колонки/подписи */
            cal.querySelectorAll('.tv-cal-month-column').forEach(function (col) {
                var dc = col.querySelector('.dayContainer');
                if (dc && col.parentNode) {
                    col.parentNode.insertBefore(dc, col);
                    col.remove();
                } else {
                    col.remove();
                }
            });
            cal.querySelectorAll('.tv-cal-month-caption, .tv-cal-weekdays-row').forEach(function (el) {
                el.remove();
            });

            var monthsHeader = cal.querySelector('.flatpickr-months');
            if (monthsHeader) {
                monthsHeader.classList.toggle('tv-cal-months-header--solo', showMonths > 1);
                monthsHeader.style.display = 'flex';
                monthsHeader.style.visibility = 'visible';
                monthsHeader.style.minHeight = showMonths > 1 ? '52px' : '';
            }

            var sharedWd = cal.querySelector('.flatpickr-weekdays');
            /* Дни недели ВСЕГДА из flatpickr — не прячем блок целиком.
               При 2 месяцах flatpickr рисует 2 weekdaycontainer → скрываем только лишние. */
            if (sharedWd) {
                sharedWd.classList.remove('tv-cal-weekdays--hidden');
                sharedWd.style.display = '';
                sharedWd.style.height = '';
                sharedWd.style.minHeight = '';
                sharedWd.style.visibility = 'visible';
                sharedWd.style.opacity = '1';
                sharedWd.style.overflow = '';
                var wdBoxes = sharedWd.querySelectorAll('.flatpickr-weekdaycontainer');
                for (var wi = 0; wi < wdBoxes.length; wi++) {
                    wdBoxes[wi].style.display = (showMonths > 1 && wi > 0) ? 'none' : '';
                    wdBoxes[wi].setAttribute('aria-hidden', (showMonths > 1 && wi > 0) ? 'true' : 'false');
                }
            }

            var daysWrap = cal.querySelector('.flatpickr-days');
            if (!daysWrap) return;

            var dayContainers = Array.prototype.slice.call(daysWrap.querySelectorAll(':scope > .dayContainer'));
            if (!dayContainers.length) {
                dayContainers = Array.prototype.slice.call(daysWrap.children).filter(function (el) {
                    return el && el.classList && el.classList.contains('dayContainer');
                });
            }
            if (!dayContainers.length) {
                dayContainers = Array.prototype.slice.call(daysWrap.querySelectorAll('.dayContainer'));
            }

            if (showMonths <= 1 || dayContainers.length < 2) {
                daysWrap.classList.remove('tv-cal-days-grid');
                return;
            }

            /* Multi: подписи месяцев над каждой сеткой; Пн–Вс — один общий ряд сверху */
            var labels = tvCalendarMonthLonghand();
            daysWrap.classList.add('tv-cal-days-grid');

            dayContainers.forEach(function (dc, idx) {
                if (dc.closest && dc.closest('.tv-cal-month-column')) return;
                var monthDate = new Date(fp.currentYear, fp.currentMonth + idx, 1);
                var col = document.createElement('div');
                col.className = 'tv-cal-month-column';

                var cap = document.createElement('div');
                cap.className = 'tv-cal-month-caption';
                cap.textContent = labels[monthDate.getMonth()] + ' ' + monthDate.getFullYear();

                daysWrap.insertBefore(col, dc);
                col.appendChild(cap);
                col.appendChild(dc);
            });
        }

        function tvCalendarRefreshUI(fp) {
            tvCalendarFixLayout(fp);
            tvCalendarEnhanceNav(fp);
            tvCalendarDecorateMonths(fp);
            tvCalendarUpdateNavDisabled(fp);
        }
        window.tvCalendarEnhanceNav = tvCalendarEnhanceNav;
        window.tvCalendarRefreshUI = tvCalendarRefreshUI;

        function tvCalendarShowMonths() {
            try {
                return window.matchMedia('(min-width: 640px)').matches ? 2 : 1;
            } catch (e) {
                return 1;
            }
        }

        /** Возвращает [from, to] для пресета */
        function presetDates(preset) {
            if (window.THDatePresets && typeof window.THDatePresets.getRange === 'function') {
                return window.THDatePresets.getRange(preset);
            }
            var t = new Date(), f = new Date(t), to = new Date(t);
            var key = String(preset || '');
            if (/^m(\d+)$/.test(key)) {
                var off = parseInt(RegExp.$1, 10);
                var y = t.getFullYear(), m = t.getMonth() + off;
                var y2 = y + Math.floor(m / 12), m2 = ((m % 12) + 12) % 12;
                if (off === 0) {
                    f = new Date(t.getFullYear(), t.getMonth(), t.getDate());
                    to = new Date(y2, m2 + 1, 0);
                } else {
                    f = new Date(y2, m2, 1);
                    to = new Date(y2, m2 + 1, 0);
                }
                return [f, to];
            }
            if (key === '3d') {
                f.setDate(t.getDate() + 1); to.setDate(t.getDate() + 3);
            } else if (key === '14d' || key === 'soon') {
                f.setTime(t.getTime()); to.setDate(t.getDate() + 14);
            } else if (key === 'week' || key === '7d') {
                f.setDate(t.getDate() + 1); to.setDate(t.getDate() + 7);
            } else if (key === 'month' || key === 'endmonth') {
                var yy = t.getFullYear(), mm = t.getMonth();
                f = new Date(yy, mm, t.getDate());
                to = new Date(yy, mm + 1, 0);
                if (f > to) { f = new Date(yy, mm + 1, 1); to = new Date(yy, mm + 2, 0); }
            } else {
                f.setDate(t.getDate() + 1); to.setDate(t.getDate() + 7);
            }
            return [f, to];
        }

        /* Попапы дат и туристов — в document.body: иначе overflow у hero обрезает fixed. */
        (function relocTvScPopupsToBody() {
            var dp = document.getElementById('tv-sc-date-popup');
            var tb = document.getElementById('tv-tourists-block');
            if (dp && dp.parentNode !== document.body) document.body.appendChild(dp);
            if (tb && tb.parentNode !== document.body) document.body.appendChild(tb);
        })();

        /* ══════════════════════════════════════════════
           ОВЕРЛЕЙ (общий для всех попапов на мобиле)
        ══════════════════════════════════════════════ */
        var overlay = document.getElementById('tv-sc-overlay');
        function showOverlay(onClose) {
            if (!overlay) return;
            overlay.style.display = 'block';
            overlay._onClose = onClose;
        }
        function hideOverlay() {
            if (!overlay) return;
            overlay.style.display = 'none';
            overlay._onClose = null;
        }
        if (overlay) {
            overlay.addEventListener('click', function() {
                if (typeof overlay._onClose === 'function') overlay._onClose();
                hideOverlay();
            });
        }

        /* ══════════════════════════════════════════════
           1. ПОПАП ДАТ
        ══════════════════════════════════════════════ */
        function initDatePopup() {
            var triggerBtn   = document.getElementById('tv-sc-dates-btn');
            var popup        = document.getElementById('tv-sc-date-popup');
            var display      = document.getElementById('tv-sc-dates-display');
            var preview      = document.getElementById('tv-sc-dates-preview');
            var stepEl       = document.getElementById('tv-sc-dates-step');
            var calPanel     = document.getElementById('tv-sc-cal-panel');
            var calContainer = document.getElementById('tv-sc-cal-container');
            var datesInp     = document.getElementById('tv-dates');
            var wizardRoot   = document.getElementById('tour-search-section');
            var isWizardMode = !!(wizardRoot && wizardRoot.classList.contains('th-wizard'));
            if (!triggerBtn || !popup) return;

            popup.classList.add('tv-sc-date-popup--cal-only');
            if (isWizardMode) popup.classList.add('tv-sc-date-popup--wizard-cal');
            var popupPanel = popup.querySelector('.th-coral-popup__panel');

            var isOpen = false;
            var mountCalTries = 0;

            function setStep(msg) {
                if (stepEl) stepEl.textContent = msg || '';
            }

            function updateDateLabels(d0, d1) {
                if (!d0 || !d1) return;
                var verbose = fmtLabelFull(d0) + ' — ' + fmtLabelFull(d1);
                if (preview) preview.textContent = 'Период вылета: ' + verbose;
                if (typeof window.updateDateDisplayOnly === 'function') {
                    window.updateDateDisplayOnly(d0, d1);
                }
            }

            function applyDates(dates, opts) {
                opts = opts || {};
                if (!dates || dates.length < 2) return;
                if (window.tvDatePicker && typeof window.tvDatePicker.setDate === 'function') {
                    window.tvDatePicker.setDate(dates, true);
                    tvCalendarApplyBounds(window.tvDatePicker);
                } else if (datesInp) {
                    datesInp.value = fmtDate(dates[0]) + ' — ' + fmtDate(dates[1]);
                    try { datesInp.dispatchEvent(new Event('change', { bubbles: true })); } catch (e) {}
                }
                if (window.tvDatePickerInline && typeof window.tvDatePickerInline.setDate === 'function') {
                    try { window.tvDatePickerInline.setDate(dates, false); } catch (e2) {}
                }
                if (typeof window.setDefaultNightsRange === 'function') window.setDefaultNightsRange();
                updateDateLabels(dates[0], dates[1]);
            }

            function syncInlineFromMain() {
                var inl = window.tvDatePickerInline;
                if (!inl || typeof inl.setDate !== 'function') return;
                var main = window.tvDatePicker;
                if (main && main.selectedDates && main.selectedDates.length >= 1) {
                    inl.setDate(main.selectedDates, false);
                    return;
                }
                if (datesInp && datesInp.value && typeof flatpickr !== 'undefined' && flatpickr.parseDate) {
                    var raw = String(datesInp.value).trim();
                    var parts = raw.split(/\s+(?:по|to)\s+|\s+[-–]\s+/i);
                    if (parts.length >= 2) {
                        var d0 = flatpickr.parseDate(parts[0].trim(), 'd-m-Y');
                        var d1 = flatpickr.parseDate(parts[1].trim(), 'd-m-Y');
                        if (d0 && d1) inl.setDate([d0, d1], false);
                    }
                }
            }

            function mountInlineCalendar() {
                if (!calContainer) return;
                if (typeof flatpickr !== 'function') {
                    mountCalTries++;
                    if (mountCalTries < 80) window.setTimeout(mountInlineCalendar, 80);
                    return;
                }
                mountCalTries = 0;
                var minDate = tvCalendarMinDate();
                var maxDate = tvCalendarMaxDate();
                var showMonths = tvCalendarShowMonths();
                if (!window.tvDatePickerInline) {
                    var inlineInput = document.createElement('input');
                    inlineInput.type = 'text';
                    inlineInput.setAttribute('aria-hidden', 'true');
                    inlineInput.style.cssText = 'position:absolute;opacity:0;pointer-events:none;width:0;height:0;overflow:hidden';
                    calContainer.appendChild(inlineInput);
                    window.tvDatePickerInline = flatpickr(inlineInput, {
                        inline: true,
                        mode: 'range',
                        dateFormat: 'd-m-Y',
                        locale: 'ru',
                        minDate: minDate,
                        maxDate: maxDate,
                        showMonths: showMonths,
                        monthSelectorType: 'static',
                        onDayCreate: function (dObj, dStr, fpInst, dayEl) {
                            if (typeof window.__tvCalDayCreate === 'function') window.__tvCalDayCreate(dObj, dStr, fpInst, dayEl);
                        },
                        onReady: function () {
                            tvCalendarRefreshUI(window.tvDatePickerInline);
                            if (typeof window.tvLoadFlyAvailability === 'function') window.tvLoadFlyAvailability();
                            if (typeof window.tvLoadCalendarPriceMap === 'function') window.tvLoadCalendarPriceMap();
                        },
                        onMonthChange: function () {
                            /* Сначала redraw flatpickr, потом наш chrome — иначе Пн–Вс/месяцы пропадают */
                            try {
                                if (typeof window.tvDatePickerInline.redraw === 'function') {
                                    window.tvDatePickerInline.redraw();
                                }
                            } catch (eRedraw) {}
                            tvCalendarRefreshUI(window.tvDatePickerInline);
                        },
                        onYearChange: function () { tvCalendarRefreshUI(window.tvDatePickerInline); },
                        onChange: function (selectedDates) {
                            /* После клика по дню flatpickr может сбросить wrap — восстанавливаем подписи */
                            window.setTimeout(function () {
                                tvCalendarRefreshUI(window.tvDatePickerInline);
                            }, 0);
                            if (selectedDates.length === 1) {
                                if (preview) preview.textContent = 'С: ' + fmtLabelFull(selectedDates[0]);
                                setStep('Теперь выберите конец периода вылета');
                                return;
                            }
                            if (selectedDates.length === 2) {
                                updateDateLabels(selectedDates[0], selectedDates[1]);
                                setStep('Нажмите «Применить» для подтверждения');
                            }
                        }
                    });
                } else {
                    tvCalendarApplyBounds(window.tvDatePickerInline);
                    if (window.tvDatePickerInline.config.showMonths !== showMonths) {
                        window.tvDatePickerInline.set('showMonths', showMonths);
                    }
                }
                syncInlineFromMain();
                tvCalendarJumpToSelection(window.tvDatePickerInline);
                tvCalendarRefreshUI(window.tvDatePickerInline);
                if (window.tvDatePickerInline && typeof window.tvDatePickerInline.redraw === 'function') {
                    window.tvDatePickerInline.redraw();
                }
                setStep('Выберите период вылета (от и до)');
            }

            function openPopup() {
                /* Не toggle: rail имеет и data-thw-goto, и data-th-search-open —
                   двойной вызов openDates иначе сразу закрывал календарь. */
                if (Date.now() < (window.__thDatePopupSuppressOpenUntil || 0)) return;
                if (isOpen) return;
                isOpen = true;
                mountCalTries = 0;
                popup.classList.remove('hidden');
                popup.classList.add('is-open');
                popup.style.display = 'flex';
                popup.setAttribute('aria-hidden', 'false');
                triggerBtn.setAttribute('aria-expanded', 'true');
                showOverlay(closePopup);
                requestAnimationFrame(function () {
                    mountInlineCalendar();
                    if (typeof window.tvLoadFlyAvailability === 'function') window.tvLoadFlyAvailability();
                    if (typeof window.tvLoadCalendarPriceMap === 'function') window.tvLoadCalendarPriceMap();
                    requestAnimationFrame(function () {
                        var fp = window.tvDatePickerInline;
                        if (fp) {
                            syncInlineFromMain();
                            tvCalendarJumpToSelection(fp);
                            tvCalendarRefreshUI(fp);
                        }
                        if (window.tvDatePicker && window.tvDatePicker.selectedDates && window.tvDatePicker.selectedDates.length >= 2) {
                            updateDateLabels(window.tvDatePicker.selectedDates[0], window.tvDatePicker.selectedDates[1]);
                        } else if (preview) {
                            preview.textContent = '';
                        }
                    });
                });
            }
            function closePopup() {
                isOpen = false;
                popup.classList.add('hidden');
                popup.classList.remove('is-open');
                popup.style.display = 'none';
                popup.setAttribute('aria-hidden', 'true');
                triggerBtn.setAttribute('aria-expanded', 'false');
                hideOverlay();
            }
            function togglePopup() {
                if (isOpen) closePopup();
                else openPopup();
            }

            popup.querySelectorAll('[data-sc-close="tv-sc-date-popup"]').forEach(function (el) {
                el.addEventListener('click', function (e) {
                    e.preventDefault();
                    closePopup();
                });
            });
            if (popupPanel) {
                popupPanel.addEventListener('click', function (e) { e.stopPropagation(); });
            }
            popup.addEventListener('click', function (e) {
                if (e.target === popup || (e.target.classList && e.target.classList.contains('th-coral-popup__backdrop'))) {
                    closePopup();
                }
            });

            triggerBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                togglePopup();
            });

            if (datesInp) {
                datesInp.addEventListener('change', function () {
                    var val = datesInp.value;
                    if (val && display) {
                        var parts = val.split(/\s+(?:по|to)\s+|\s+[-–—]\s+/i);
                        if (parts.length === 2) {
                            var f = parts[0].split('-'), t2 = parts[1].split('-');
                            if (f.length >= 2 && t2.length >= 2) {
                                display.textContent = f[0] + '.' + f[1] + '–' + t2[0] + '.' + t2[1];
                                if (preview) preview.textContent = 'Выбрано: ' + f[0] + '.' + f[1] + ' — ' + t2[0] + '.' + t2[1];
                            }
                        } else {
                            display.textContent = val;
                        }
                    }
                });
            }

            document.addEventListener('click', function (e) {
                if (!isOpen) return;
                var field = document.getElementById('tv-sc-dates-field');
                var coralTrigger = e.target && e.target.closest && e.target.closest('[data-th-search-open="dates"]');
                if (field && !field.contains(e.target) && !popup.contains(e.target) && !coralTrigger) {
                    closePopup();
                }
            });

            var closeBtn = popup.querySelector('[data-sc-close]');
            if (closeBtn) closeBtn.addEventListener('click', closePopup);
            var applyBtn = document.getElementById('tv-sc-dates-apply');
            if (applyBtn) {
                applyBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var inl = window.tvDatePickerInline;
                    var picked = inl && inl.selectedDates ? inl.selectedDates : null;
                    if (!picked || picked.length < 2) {
                        setStep('Выберите день вылета и день возвращения');
                        return;
                    }
                    applyDates(picked, { autoClose: false });
                    /* Блок повторного openDates (wizard go / бабл) сразу после Применить */
                    window.__thDatePopupSuppressOpenUntil = Date.now() + 700;
                    closePopup();
                    if (isWizardMode) {
                        advanceWizardAfterDates();
                    }
                });
            }

            window.__thWizardOpenDatePopup = openPopup;
        }

        /* ══════════════════════════════════════════════
           2. ТУРИСТЫ-ПОПАП: показываем оверлей на мобиле,
              добавляем кнопку-закрывашку
        ══════════════════════════════════════════════ */
        function initTouristsPopup() {
            var trigger      = document.getElementById('tv-tourists-trigger');
            var touristsBlock= document.getElementById('tv-tourists-block');
            var closeNewBtn  = document.getElementById('tv-tourists-close-btn');
            var applyBtn     = document.getElementById('tv-tourists-apply');
            if (!touristsBlock) return;

            /* Существующий JS уже вешает handler на trigger.
               Наблюдаем изменение класса 'hidden' → показываем/скрываем оверлей */
            var mo = new MutationObserver(function(muts) {
                muts.forEach(function(m) {
                    if (m.attributeName === 'class') {
                        if (!touristsBlock.classList.contains('hidden')) {
                            showOverlay(closeTotourists);
                        } else {
                            hideOverlay();
                        }
                    }
                });
            });
            mo.observe(touristsBlock, { attributes: true });

            function closeTotourists() {
                touristsBlock.classList.add('hidden');
                touristsBlock.classList.remove('is-open');
                touristsBlock.style.display = 'none';
                touristsBlock.setAttribute('aria-hidden', 'true');
                hideOverlay();
            }

            touristsBlock.querySelectorAll('[data-tv-tourists-close]').forEach(function (el) {
                el.addEventListener('click', function (e) {
                    e.preventDefault();
                    closeTotourists();
                });
            });
            var touristsPanel = touristsBlock.querySelector('.th-coral-popup__panel');
            if (touristsPanel) touristsPanel.addEventListener('click', function (e) { e.stopPropagation(); });

            if (closeNewBtn) closeNewBtn.addEventListener('click', closeTotourists);

            /* Закрытие по клику вне попапа */
            document.addEventListener('click', function(e) {
                if (touristsBlock.classList.contains('hidden')) return;
                var coralTrigger = e.target && e.target.closest && e.target.closest('[data-th-search-open="tourists"]');
                if (!touristsBlock.contains(e.target) && !trigger.contains(e.target) && !coralTrigger) {
                    closeTotourists();
                }
            });

            window.__thWizardOpenTouristsPopup = function () {
                touristsBlock.classList.remove('hidden');
                touristsBlock.classList.add('is-open');
                touristsBlock.style.display = 'flex';
                touristsBlock.setAttribute('aria-hidden', 'false');
                showOverlay(closeTotourists);
            };
        }

        /* ══════════════════════════════════════════════
           3. ФИЛЬТРЫ: центральное модальное окно
        ══════════════════════════════════════════════ */
        function initTvFiltersModal() {
            var overlay = document.getElementById('tv-filters-modal-overlay');
            var modal = document.getElementById('tv-filters-modal');
            var openBtn = document.getElementById('tv-filters-modal-open');
            var closeBtn = document.getElementById('tv-filters-modal-close');
            var applyBtn = document.getElementById('tv-filters-modal-apply');
            var resetBtn = document.getElementById('tv-filters-modal-reset');
            if (!overlay || !modal) return;

            function syncBeachChips(val) {
                modal.querySelectorAll('[data-tv-beach]').forEach(function (b) {
                    var sv = b.getAttribute('data-tv-beach');
                    if (sv === null) return;
                    var active = (sv === '' && !val) || (sv !== '' && sv === String(val));
                    b.classList.toggle('is-active', active);
                    b.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
            }
            function getBeachValue() {
                var active = modal.querySelector('[data-tv-beach].is-active');
                return active ? (active.getAttribute('data-tv-beach') || '') : '';
            }
            function setBeachValue(val) {
                window.__tvWizardBeachLine = val ? String(val) : '';
                syncBeachChips(window.__tvWizardBeachLine);
                if (window.tvPostFiltersCtrl && window.tvPostFiltersCtrl.state) {
                    window.tvPostFiltersCtrl.state.beachLine = window.__tvWizardBeachLine;
                }
            }

            function syncStarsFromSelect() {
                var sel = document.getElementById('tv-category');
                var v = sel ? String(sel.value || '') : '';
                modal.querySelectorAll('[data-tv-stars]').forEach(function(b) {
                    var sv = b.getAttribute('data-tv-stars');
                    if (sv === null) return;
                    var active = (sv === '' && v === '') || (sv !== '' && sv === v);
                    b.classList.toggle('is-active', active);
                    b.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
            }
            function setStarsValue(val) {
                var sel = document.getElementById('tv-category');
                if (!sel) return;
                sel.value = val;
                try { sel.dispatchEvent(new Event('change', { bubbles: true })); } catch (e) {}
                syncStarsFromSelect();
            }

            function openModal() {
                syncStarsFromSelect();
                syncBeachChips(window.__tvWizardBeachLine || '');
                var countrySel = document.getElementById('tv-country');
                var depSel = document.getElementById('tv-departure');
                var regionEl = document.getElementById('tv-region');
                if (countrySel && depSel && regionEl && countrySel.value && depSel.value && regionEl.options.length <= 1) {
                    try { countrySel.dispatchEvent(new Event('change', { bubbles: true })); } catch (e) {}
                }
                overlay.classList.add('tv-filters-modal--show');
                modal.removeAttribute('hidden');
                modal.classList.add('tv-filters-modal--show');
                overlay.setAttribute('aria-hidden', 'false');
                document.body.classList.add('th-modal-open');
            if (window.THMobile && window.THMobile.sync) window.THMobile.sync();
                if (openBtn) openBtn.setAttribute('aria-expanded', 'true');
            }
            function closeModal() {
                overlay.classList.remove('tv-filters-modal--show');
                modal.classList.remove('tv-filters-modal--show');
                modal.setAttribute('hidden', '');
                overlay.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('th-modal-open');
            if (window.THMobile && window.THMobile.sync) window.THMobile.sync();
                if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
            }

            window.openTvFiltersModal = openModal;

            if (openBtn) {
                openBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    openModal();
                });
            }
            if (closeBtn) closeBtn.addEventListener('click', function(e) { e.preventDefault(); closeModal(); });
            overlay.addEventListener('click', function() { closeModal(); });
            modal.addEventListener('click', function(e) { e.stopPropagation(); });

            modal.querySelectorAll('.tv-filters-modal__star-chip').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    if (btn.hasAttribute('data-tv-beach')) {
                        setBeachValue(btn.getAttribute('data-tv-beach') || '');
                        return;
                    }
                    var v = btn.getAttribute('data-tv-stars');
                    setStarsValue(v === null ? '' : v);
                });
            });

            if (applyBtn) {
                applyBtn.addEventListener('click', function() {
                    setBeachValue(getBeachValue());
                    closeModal();
                    var w = document.getElementById('tv-results-wrapper');
                    if (w && !w.classList.contains('hidden') && typeof performTvSearch === 'function') {
                        performTvSearch();
                    }
                });
            }
            if (resetBtn) {
                resetBtn.addEventListener('click', function() {
                    var meal = document.getElementById('tv-meal');
                    var region = document.getElementById('tv-region');
                    var pmin = document.getElementById('tv-price-min');
                    var pmax = document.getElementById('tv-price-max');
                    if (meal) { meal.value = ''; try { meal.dispatchEvent(new Event('change', { bubbles: true })); } catch (e) {} }
                    if (region) { region.value = ''; try { region.dispatchEvent(new Event('change', { bubbles: true })); } catch (e) {} }
                    if (pmin) pmin.value = '';
                    if (pmax) pmax.value = '';
                    setStarsValue('');
                    setBeachValue('');
                    if (typeof tvSelectedServiceIds !== 'undefined' && tvSelectedServiceIds.length) {
                        tvSelectedServiceIds.length = 0;
                    }
                    var w = document.getElementById('tv-results-wrapper');
                    if (w && !w.classList.contains('hidden') && typeof performTvSearch === 'function') {
                        performTvSearch();
                    }
                });
            }
        }

        /* ══════════════════════════════════════════════
           4. Nights popup: стиль обновления ячеек на orange
        ══════════════════════════════════════════════ */
        function patchNightsPopupStyle() {
            /* Перехватываем patinGrid из существующего JS — 
               меняем синий цвет на оранжевый через inline-стили наблюдателем */
            var grid = document.getElementById('tv-nights-grid');
            if (!grid) return;
            var mo = new MutationObserver(function() {
                grid.querySelectorAll('.tv-nights-cell').forEach(function(cell) {
                    var bg = cell.style.backgroundColor;
                    if (bg && bg.indexOf('79, 70, 229') !== -1) {
                        cell.style.backgroundColor = '#FF6B6B';
                        cell.style.borderColor = '#F65252';
                    } else if (bg && bg.indexOf('30, 107, 184') !== -1) {
                        cell.style.backgroundColor = '#CC5200';
                        cell.style.borderColor = '#B04800';
                    } else if (bg && bg.indexOf('99, 102, 241') !== -1) {
                        cell.style.backgroundColor = 'rgba(255,107,107,0.18)';
                        cell.style.color = '#9a3a00';
                        cell.style.borderColor = 'rgba(255,107,107,0.35)';
                    }
                });
            });
            mo.observe(grid, { attributes: true, subtree: true, attributeFilter: ['style'] });
        }

        /* ══════════════════════════════════════════════
           5. Закрытие всех попапов по Escape
        ══════════════════════════════════════════════ */
        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Escape') return;
            /* Дата-попап */
            var dp = document.getElementById('tv-sc-date-popup');
            if (dp && dp.style.display !== 'none') { dp.style.display = 'none'; hideOverlay(); }
            /* Туристы */
            var tb = document.getElementById('tv-tourists-block');
            if (tb && !tb.classList.contains('hidden')) { tb.classList.add('hidden'); hideOverlay(); }
            /* Фильтры: модальное окно */
            var fm = document.getElementById('tv-filters-modal');
            var fo = document.getElementById('tv-filters-modal-overlay');
            if (fm && fm.classList.contains('tv-filters-modal--show')) {
                fm.classList.remove('tv-filters-modal--show');
                fm.setAttribute('hidden', '');
                if (fo) { fo.classList.remove('tv-filters-modal--show'); fo.setAttribute('aria-hidden', 'true'); }
                document.body.classList.remove('th-modal-open');
            if (window.THMobile && window.THMobile.sync) window.THMobile.sync();
                var ob = document.getElementById('tv-filters-modal-open');
                if (ob) ob.setAttribute('aria-expanded', 'false');
            }
        });


        /* ===== ФИЛЬТРЫ: центральное модальное окно (оверлей + диалог) ===== */
        function openQuickModal(source) {
            if (window.THConversionBoost && window.THConversionBoost.applyIntent) {
                window.THConversionBoost.applyIntent(source || 'home_quick_modal');
            }
            var modal = document.getElementById('quick-booking-modal');
            if (!modal) return;
            modal.dataset.thLeadSource = source || 'home_quick_modal';
            modal.style.display = 'flex';
            document.body.classList.add('th-modal-open');
            if (window.THMobile && window.THMobile.sync) window.THMobile.sync();
            var msg = document.getElementById('qbm-msg');
            if (msg) msg.style.display = 'none';
            var phoneInp = modal.querySelector('[name="phone"]');
            if (phoneInp) try { phoneInp.focus(); } catch (e) {}
        }
        function closeQuickModal() {
            var modal = document.getElementById('quick-booking-modal');
            if (!modal) return;
            modal.style.display = 'none';
            document.body.classList.remove('th-modal-open');
            if (window.THMobile && window.THMobile.sync) window.THMobile.sync();
        }
        function initQuickModal() {
            var backdrop = document.getElementById('qbm-backdrop');
            var closeBtn = document.getElementById('qbm-close');
            var form     = document.getElementById('qbm-form');
            var submit   = document.getElementById('qbm-submit');
            var msgEl    = document.getElementById('qbm-msg');
            if (backdrop) backdrop.addEventListener('click', closeQuickModal);
            if (closeBtn) closeBtn.addEventListener('click', closeQuickModal);
            document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeQuickModal(); });
            if (form) {
                form.setAttribute('data-th-lead-source', 'home_quick_modal');
                if (window.THLeadCapture && window.THLeadCapture.formatPhoneInput) {
                    window.THLeadCapture.formatPhoneInput(form.querySelector('[name="phone"]'));
                }
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (submit.disabled) return;
                    var fd = new FormData(form);
                    function showMsg(t, ok) {
                        if (!msgEl) return;
                        msgEl.textContent = t; msgEl.style.display = 'block';
                        msgEl.style.background = ok ? '#D1FAE5' : '#FEE2E2';
                        msgEl.style.color = ok ? '#065F46' : '#991B1B';
                    }
                    submit.disabled = true; submit.textContent = 'Отправка…';
                    var send = (window.THLeadCapture && window.THLeadCapture.submit)
                        ? window.THLeadCapture.submit({
                            name: String(fd.get('name') || '').trim() || 'Клиент сайта',
                            phone: String(fd.get('phone') || '').trim(),
                            agree: !!fd.get('agree'),
                            website: String(fd.get('website') || ''),
                            source: (document.getElementById('quick-booking-modal') || {}).dataset?.thLeadSource || 'home_quick_modal',
                            phoneOnly: (document.getElementById('quick-booking-modal') || {}).dataset?.thPhoneOnly === '1',
                            message: 'Заявка из модального окна (жёсткая воронка)'
                        })
                        : Promise.resolve({ success: false, error: 'Модуль заявки не загружен' });
                    send.then(function (data) {
                        if (data && data.success) {
                            showMsg(data.message || 'Заявка принята! Перезвоним за 15 минут.', true);
                            form.reset();
                            document.dispatchEvent(new Event('leadSubmitted'));
                            setTimeout(closeQuickModal, 2500);
                        } else {
                            showMsg((data && data.error) || 'Ошибка отправки', false);
                        }
                    }).finally(function () {
                        submit.disabled = false;
                        submit.textContent = 'Отправить заявку менеджеру';
                    });
                });
            }
            document.addEventListener('click', function(e) {
                var t=e.target.closest('[data-open-lead-modal]');
                if(t){e.preventDefault();openQuickModal(t.dataset.openLeadModal||'trigger');}
            });
            window.openQuickLeadModal = openQuickModal;
        }

        /* ══════════════════════════════════════════════
           8. Пустые результаты
        ══════════════════════════════════════════════ */
        function initEmptyState() {
            document.addEventListener('click', function(e) {
                if (e.target.closest('.tv-empty-state__btn')) {
                    e.preventDefault();
                    openQuickModal('empty-state');
                }
            });
        }

        /* ══════════════════════════════════════════════
           9. Sidebar sync (результаты поиска)
        ══════════════════════════════════════════════ */
        function initSidebarSync() {
            ['tv-meal','tv-region','tv-category'].forEach(function(id) {
                var main    = document.getElementById(id);
                var sidebar = document.getElementById(id+'-sidebar');
                if (!main || !sidebar) return;
                if (main.options.length > 1) { sidebar.innerHTML = main.innerHTML; sidebar.value = main.value; }
                new MutationObserver(function() {
                    var v = main.value; sidebar.innerHTML = main.innerHTML; sidebar.value = v;
                }).observe(main, { childList: true, subtree: true });
                sidebar.addEventListener('change', function() { main.value = sidebar.value; });
                main.addEventListener('change', function() { sidebar.value = main.value; });
            });
            var sidebarBtn = document.getElementById('tv-sidebar-search-btn');
            var mainBtn    = document.getElementById('tv-search-btn');
            if (sidebarBtn && mainBtn) sidebarBtn.addEventListener('click', function() { mainBtn.click(); });
        }

        function initLoaderLeadForm() {
            var form = document.getElementById('tv-loader-lead-form');
            if (!form || form.__thBound) return;
            form.__thBound = true;
            var phone = form.querySelector('[name="phone"]');
            var msg = document.getElementById('tv-loader-lead-msg');
            if (window.THLeadCapture && phone) window.THLeadCapture.formatPhoneInput(phone);
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var btn = form.querySelector('[type="submit"]');
                var fd = new FormData(form);
                if (btn) { btn.disabled = true; }
                if (window.THLeadCapture) window.THLeadCapture.reachGoal('slow_search_lead_attempt');
                var send = (window.THLeadCapture && window.THLeadCapture.submit)
                    ? window.THLeadCapture.submit({
                        name: 'Клиент сайта',
                        phone: String(fd.get('phone') || '').trim(),
                        agree: !!fd.get('agree'),
                        website: String(fd.get('website') || ''),
                        source: 'slow_search_lead',
                        phoneOnly: true,
                        message: 'Лид во время долгого поиска на главной'
                    })
                    : Promise.resolve({ success: false, error: 'Форма недоступна' });
                send.then(function(res) {
                    if (msg) {
                        msg.classList.remove('hidden');
                        msg.textContent = res.success ? (res.message || 'Заявка принята! Поиск продолжается…') : (res.error || 'Ошибка');
                    }
                    if (res.success) form.reset();
                }).finally(function() {
                    if (btn) btn.disabled = false;
                });
            });
        }

        /* ══════════════════════════════════════════════
           INIT
        ══════════════════════════════════════════════ */
        function init() {
            initDatePopup();
            initTouristsPopup();
            initTvFiltersModal();
            patchNightsPopupStyle();
            initQuickModal();
            initEmptyState();
            initSidebarSync();
            initLoaderLeadForm();
        }

        /* --- 1. Date Presets (legacy — оставляем для совместимости) --- */
        function initDatePresets() {
            var presets = document.querySelectorAll('.tv-date-preset-chip[data-preset]');
            if (!presets.length) return;

            function getPresetDates(preset) {
                var today = new Date();
                var from = new Date(today);
                var to   = new Date(today);
                if (preset === '3d') {
                    from.setDate(today.getDate() + 1);
                    to.setDate(today.getDate() + 3);
                } else if (preset === '7d') {
                    from.setDate(today.getDate() + 1);
                    to.setDate(today.getDate() + 7);
                } else if (preset === '14d') {
                    from.setTime(today.getTime());
                    to.setDate(today.getDate() + 14);
                } else if (preset === 'endmay') {
                    var y = today.getFullYear();
                    // Если сейчас уже после мая — берём май следующего года
                    if (today.getMonth() >= 5) y++;
                    from = new Date(y, 4, 20); // 20 мая
                    to   = new Date(y, 4, 31); // 31 мая
                }
                return [from, to];
            }

            function fmt(d) {
                var dd = String(d.getDate()).padStart(2, '0');
                var mm = String(d.getMonth() + 1).padStart(2, '0');
                var yyyy = d.getFullYear();
                return dd + '-' + mm + '-' + yyyy;
            }

            presets.forEach(function(chip) {
                chip.addEventListener('click', function() {
                    var preset = chip.dataset.preset;
                    var dates  = getPresetDates(preset);
                    // Снимаем active со всех
                    presets.forEach(function(c) { c.classList.remove('active'); });
                    chip.classList.add('active');

                    // Обновляем flatpickr если уже инициализирован
                    if (window.tvDatePicker && typeof window.tvDatePicker.setDate === 'function') {
                        window.tvDatePicker.setDate(dates, true);
                    } else {
                        // Fallback: обновляем текстовое значение напрямую
                        var inp = document.getElementById('tv-dates');
                        if (inp) inp.value = fmt(dates[0]) + ' — ' + fmt(dates[1]);
                    }
                });
            });

            // Снимаем active при ручном изменении дат
            var datesInp = document.getElementById('tv-dates');
            if (datesInp) {
                datesInp.addEventListener('change', function() {
                    presets.forEach(function(c) { c.classList.remove('active'); });
                });
            }
        }

        /* --- 2. Sidebar filters sync (синхронизируем с основными селектами) --- */
        function initSidebarSync() {
            var pairs = [
                ['tv-meal',     'tv-meal-sidebar'],
                ['tv-region',   'tv-region-sidebar'],
                ['tv-category', 'tv-category-sidebar'],
            ];
            pairs.forEach(function(pair) {
                var main    = document.getElementById(pair[0]);
                var sidebar = document.getElementById(pair[1]);
                if (!main || !sidebar) return;

                // Копируем текущие options если уже есть
                if (main.options.length > 1) {
                    sidebar.innerHTML = main.innerHTML;
                    sidebar.value     = main.value;
                }

                // Отслеживаем наполнение основного select → копируем в sidebar
                var ob = new MutationObserver(function() {
                    var val = main.value;
                    sidebar.innerHTML = main.innerHTML;
                    sidebar.value     = val;
                });
                ob.observe(main, { childList: true, subtree: true });

                // Sync value: sidebar → main
                sidebar.addEventListener('change', function() {
                    main.value = sidebar.value;
                });
                // Sync value: main → sidebar
                main.addEventListener('change', function() {
                    sidebar.value = main.value;
                });
            });

            // Кнопка поиска в сайдбаре — кликает на основную
            var sidebarBtn = document.getElementById('tv-sidebar-search-btn');
            var mainBtn    = document.getElementById('tv-search-btn');
            if (sidebarBtn && mainBtn) {
                sidebarBtn.addEventListener('click', function() {
                    mainBtn.click();
                });
            }
        }

        /* --- 3. Sticky Mobile CTA: см. initStickyCta выше --- */

        /* --- 5. Quick Booking Modal --- */
        function openQuickModal(source) {
            if (window.THConversionBoost && window.THConversionBoost.applyIntent) {
                window.THConversionBoost.applyIntent(source || 'home_quick_modal');
            }
            var modal = document.getElementById('quick-booking-modal');
            if (!modal) return;
            modal.dataset.thLeadSource = source || 'home_quick_modal';
            modal.style.display = 'flex';
            document.body.classList.add('th-modal-open');
            if (window.THMobile && window.THMobile.sync) window.THMobile.sync();
            var msg = document.getElementById('qbm-msg');
            if (msg) msg.style.display = 'none';
            var phoneInp = modal.querySelector('[name="phone"]');
            if (phoneInp) try { phoneInp.focus(); } catch (e) {}
        }

        function closeQuickModal() {
            var modal = document.getElementById('quick-booking-modal');
            if (!modal) return;
            modal.style.display = 'none';
            document.body.classList.remove('th-modal-open');
            if (window.THMobile && window.THMobile.sync) window.THMobile.sync();
        }

        function initQuickModal() {
            var backdrop = document.getElementById('qbm-backdrop');
            var closeBtn = document.getElementById('qbm-close');
            var form     = document.getElementById('qbm-form');
            var submit   = document.getElementById('qbm-submit');
            var msgEl    = document.getElementById('qbm-msg');

            if (backdrop) backdrop.addEventListener('click', closeQuickModal);
            if (closeBtn) closeBtn.addEventListener('click', closeQuickModal);

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closeQuickModal();
            });

            if (form) {
                // Маска телефона
                var phoneInp = form.querySelector('[name="phone"]');
                if (phoneInp) {
                    phoneInp.addEventListener('input', function() {
                        var v = this.value.replace(/\D/g, '');
                        if (v.length > 0 && v[0] === '8') v = '7' + v.slice(1);
                        if (v.length > 0 && v[0] !== '7') v = '7' + v;
                        if (v.length > 11) v = v.slice(0, 11);
                        var f2 = '';
                        if (v.length > 0)  f2 += '+7';
                        if (v.length > 1)  f2 += ' (' + v.slice(1, 4);
                        if (v.length > 4)  f2 += ') ' + v.slice(4, 7);
                        if (v.length > 7)  f2 += '-' + v.slice(7, 9);
                        if (v.length > 9)  f2 += '-' + v.slice(9, 11);
                        this.value = f2;
                    });
                }

                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (submit.disabled) return;
                    var fd      = new FormData(form);
                    var name    = String(fd.get('name')  || '').trim();
                    var phone   = String(fd.get('phone') || '').trim();
                    var agree   = !!fd.get('agree');
                    var website = String(fd.get('website') || '');

                    function showMsg(text, ok) {
                        if (!msgEl) return;
                        msgEl.textContent = text;
                        msgEl.style.display = 'block';
                        msgEl.style.background = ok ? '#D1FAE5' : '#FEE2E2';
                        msgEl.style.color      = ok ? '#065F46' : '#991B1B';
                    }

                    if (!name || !phone) { showMsg('Укажите имя и телефон', false); return; }
                    if (!agree)         { showMsg('Нужно согласие на обработку данных', false); return; }

                    submit.disabled = true;
                    submit.textContent = 'Отправка...';

                    fetch('/backend/api/uon-lead.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ name: name, phone: phone, agree: true, website: website, message: 'Заявка из модального окна (главная)' })
                    })
                    .then(function(r) { return r.json().catch(function() { return { success: false }; }); })
                    .then(function(data) {
                        if (data && data.success) {
                            showMsg('Заявка принята! Перезвоним за 15 минут.', true);
                            form.reset();
                            document.dispatchEvent(new Event('leadSubmitted'));
                            setTimeout(closeQuickModal, 2500);
                        } else {
                            showMsg((data && data.error) || 'Ошибка отправки, попробуйте ещё раз', false);
                        }
                    })
                    .catch(function() {
                        showMsg('Ошибка сети. Попробуйте позже.', false);
                    })
                    .finally(function() {
                        submit.disabled = false;
                        submit.textContent = 'Отправить заявку менеджеру';
                    });
                });
            }

            // Открыть модал через кнопки с data-open-lead-modal
            document.addEventListener('click', function(e) {
                var trigger = e.target.closest('[data-open-lead-modal]');
                if (trigger) {
                    e.preventDefault();
                    openQuickModal(trigger.dataset.openLeadModal || 'trigger');
                }
            });

            // Экспорт для других модулей
            window.openQuickLeadModal = openQuickModal;
        }

        /* --- 6. Пустые результаты поиска --- */
        function initEmptyState() {
            // Кнопка «Подберём сами» в пустом состоянии открывает modal
            document.addEventListener('click', function(e) {
                if (e.target.closest('.tv-empty-state__btn')) {
                    e.preventDefault();
                    openQuickModal('empty-state');
                }
            });
        }

        /* --- Legacy date preset chips (оставляем) --- */
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                initDatePresets();
                init();
            });
        } else {
            initDatePresets();
            init();
        }
    })();
    </script>
    <?php
    $_th_app_promo_path = __DIR__ . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'th-app-promo.js';
    $_th_app_promo_ver = is_file($_th_app_promo_path) ? (string) filemtime($_th_app_promo_path) : '1';
    ?>
    <script src="/frontend/js/th-app-promo.js?v=<?php echo htmlspecialchars($_th_app_promo_ver, ENT_QUOTES, 'UTF-8'); ?>" defer></script>

    <?php
    // Завершаем буферизацию и сохраняем в кэш
    PageCache::end();
    ?>
</body>
</html>