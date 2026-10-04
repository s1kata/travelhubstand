<?php
/**
 * Страница акций — по городам вылета (без выбора страны на первом уровне).
 * - Шаг 1: выбор города вылета (Москва, СПб и т.д.)
 * - Шаг 2: выбор страны для выбранного города вылета
 * - Шаг 3: курорты и туры (фильтр по городу/курорту)
 */
require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/components/tourvisor_proxy_url.php';
require_once __DIR__ . '/../../backend/components/th_feature_flags.php';
session_start();
$current_page = 'promotions';
$tv_api_base = get_tourvisor_proxy_base_url();
$tv_image_proxy = get_tourvisor_image_proxy_base_url();
require_once __DIR__ . '/../../backend/config/departure_defaults.php';
require_once __DIR__ . '/../../backend/components/promo_speed_cache.php';
require_once __DIR__ . '/../../backend/components/promo_virtual_destinations.php';
$promo_samara_id = th_departure_default_id();
$promo_samara_name = th_departure_default_name();
$departure_id = isset($_GET['departureId']) ? (int)$_GET['departureId'] : 0;
$departure_name = isset($_GET['departureName']) ? trim((string)$_GET['departureName']) : '';
if ($departure_name !== '' && th_departure_is_blocked_name($departure_name)) {
    $departure_id = 7;
    $departure_name = 'Самара';
}
if ($departure_id <= 0) {
    $departure_id = 7;
    $departure_name = 'Самара';
}
$departure_id = th_departure_normalize_id((int) $departure_id);
if ($departure_id === 1) {
    $departure_name = 'Москва';
} else {
    $departure_id = 7;
    $departure_name = 'Самара';
}
$promo_default_departure_id = th_departure_normalize_id(th_departure_default_id());
$promo_default_departure_name = th_departure_default_name();
$country_id = isset($_GET['countryId']) ? (int)$_GET['countryId'] : 0;
$country_name = isset($_GET['countryName']) ? trim((string)$_GET['countryName']) : '';

/** Винительный падеж для заголовков «Акционные туры в …» (напр. Турция → Турцию). */
if (!function_exists('th_promo_country_accusative')) {
    function th_promo_country_accusative(string $nom): string
    {
        $t = trim($nom);
        static $map = [
            'Турция' => 'Турцию', 'Индия' => 'Индию', 'Греция' => 'Грецию', 'Индонезия' => 'Индонезию',
            'Куба' => 'Кубу', 'Шри-Ланка' => 'Шри-Ланку', 'Черногория' => 'Черногорию',
            'Иордания' => 'Иорданию', 'Танзания' => 'Танзанию', 'Армения' => 'Армению',
            'Абхазия' => 'Абхазию', 'Россия' => 'Россию', 'Венесуэла' => 'Венесуэлу',
            'Испания' => 'Испанию', 'Доминикана' => 'Доминикану',             'Филиппины' => 'Филиппины',
            'Фукуок' => 'Фукуок',
        ];
        return $map[$t] ?? $t;
    }
}
$country_name_acc = $country_name !== '' ? th_promo_country_accusative($country_name) : '';

/** Родительный падеж города для «из …» (из Самары). */
if (!function_exists('th_promo_departure_genitive')) {
    function th_promo_departure_genitive(string $nom): string
    {
        $t = trim($nom);
        static $map = [
            'Самара' => 'Самары', 'Москва' => 'Москвы', 'Казань' => 'Казани',
            'Санкт-Петербург' => 'Санкт-Петербурга', 'С.Петербург' => 'Санкт-Петербурга',
            'Екатеринбург' => 'Екатеринбурга', 'Новосибирск' => 'Новосибирска',
            'Ростов-на-Дону' => 'Ростова-на-Дону', 'Краснодар' => 'Краснодара',
            'Уфа' => 'Уфы', 'Пермь' => 'Перми', 'Воронеж' => 'Воронежа',
            'Челябинск' => 'Челябинска', 'Сочи' => 'Сочи',
            'Нижний Новгород' => 'Нижнего Новгорода', 'Саратов' => 'Саратова',
            'Омск' => 'Омска', 'Тюмень' => 'Тюмени', 'Иркутск' => 'Иркутска',
        ];
        return $map[$t] ?? $t;
    }
}

// Если передан countryId без departureId — город подставит клиент из localStorage (th_departure_*).
$departure_name_gen = $departure_name !== '' ? th_promo_departure_genitive($departure_name) : '';

$promo_pick_city_url = '/frontend/window/promotions.php?choose_departure=1';
$promo_change_country_url = ($departure_id > 0)
    ? ('/frontend/window/promotions.php?departureId=' . (int) $departure_id . '&departureName=' . rawurlencode($departure_name) . '#promo-countries-section')
    : '';

// Полезная информация для туристов (виза, валюта, климат и т.д.)
$promo_country_info = [];
$promo_info_file = __DIR__ . '/../../backend/config/promo_country_info.php';
if (is_file($promo_info_file)) {
    $promo_country_info = require $promo_info_file;
}
$country_tourist_info = isset($promo_country_info[$country_name]) ? $promo_country_info[$country_name] : null;

// Маппинг countryId (TourVisor) -> slug для amg-countries
$country_id_to_slug = [
    1 => 'egypt', 2 => 'thailand', 3 => 'india', 4 => 'turkey', 5 => 'tunisia', 6 => 'greece',
    7 => 'indonesia', 8 => 'maldives', 9 => 'uae', 10 => 'cuba', 11 => 'dominican', 12 => 'sri-lanka',
    13 => 'china', 14 => 'spain', 15 => 'cyprus', 16 => 'vietnam', 21 => 'montenegro', 27 => 'mauritius',
    28 => 'seychelles', 29 => 'jordan', 53 => 'armenia', 59 => 'bahrain', 46 => 'abkhazia', 90 => 'venezuela', 47 => 'russia',
];

// Локальные фото из папки img (приоритет над API для стран, где есть факты/галерея)
$local_country_images = [
    'Египет' => '/frontend/window/img/египет/photo_2025-11-27_17-02-33.jpg',
    'Таиланд' => '/frontend/window/img/таиланд/f6abf1e77961201063281c7d41fea1ef.jpg',
    'Индия' => '/frontend/window/img/индия/0d87b5b6b3b2cb8e7662da522fc1acef.jpg',
    'Турция' => '/frontend/window/img/турция/ostrovok-filters-4-10.jpg',
    'Тунис' => '/frontend/window/img/тунис/2e4143bbfb3ce492ea7f52dfc2b698ed.jpg',
    'Мальдивы' => '/frontend/window/img/мальдивы/1a8d863f3a6095f994c7d10d6f82960c.jpg',
    'ОАЭ' => '/frontend/window/img/ОАЭ/293d346edb7b418fb57d0370087191ae.jpg',
    'Куба' => '/frontend/window/img/куба/03fd6fb1dce49565754e29a2c93f4643.jpg',
    'Шри-Ланка' => '/frontend/window/img/шриланка/5af0ebd87bd018ea6076bf0fd7a3a524.jpg',
    'Китай' => '/frontend/window/img/китай/photo_2025-12-02_23-30-37.jpg',
    'Вьетнам' => '/frontend/window/img/вьетнам/0d1951e284d67cca12e1f58edebf5e0a.jpg',
    'Фукуок' => '/frontend/window/img/фукуок/grand-world-phu-quoc.png',
    'Индонезия' => '/frontend/window/img/индонезия/0d0460b8cfd98f78d4ae7379d45dfb57.jpg',
    'Черногория' => '/frontend/window/img/черногорие/07d6f0e65f7ff0ae1570156147bd9c08.jpg',
    'Сейшелы' => '/frontend/window/img/сейшелы/35ff072cc8f8c7eda59a97b543a7f1e4.jpg',
    'Маврикий' => '/frontend/window/img/маврикий/5b3f1aff1b67ef086b355bc2886f37cf.jpg',
    'Иордания' => '/frontend/window/img/иордания/15b34ae290d2e35fe95a4157675c86b9.jpg',
    'Оман' => '/frontend/window/img/оман/2fb42418ab2074c08a26ec54e7b39ba5.jpg',
    'Катар' => '/frontend/window/img/катар/1edd5b3a9b9a6a34427d0c0b4377785a.jpg',
    'Филиппины' => '/frontend/window/img/филипины/4747920dc098d6df1521b4a5105e48ed.jpg',
    'Танзания' => '/frontend/window/img/танзания/0c631e48a4f617df070155bdd186080b.jpg',
    'Армения' => '/frontend/window/img/армения/photo_2025-12-02_23-31-35.jpg',
    'Бахрейн' => '/frontend/window/img/бахрейн/photo_2025-12-02_23-32-08.jpg',
    'Абхазия' => '/frontend/window/img/абхазия/photo_2025-12-02_23-31-08.jpg',
    'Сочи' => '/frontend/window/img/россия/076410508be961edd39cbb41af08d2a4.jpg',
    'Россия' => '/frontend/window/img/россия/076410508be961edd39cbb41af08d2a4.jpg',
    'Венесуэла' => '/frontend/window/img/венесуэла/64acd63a88ec243ac9ebc4b0f065279c.jpg',
];

// Фото городов вылета — расширенный список (все города из API TourVisor). Подключаем конфиг.
$departure_city_images = is_file(__DIR__ . '/../../backend/config/departure_city_images.php')
    ? require __DIR__ . '/../../backend/config/departure_city_images.php'
    : [
        'Москва' => 'https://images.unsplash.com/photo-1520106212299-d99c443e4568?w=800',
        'Санкт-Петербург' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800',
        'С.Петербург' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800',
        'Казань' => 'https://images.unsplash.com/photo-1547448415-e9f5b28e570d?w=800',
        'Екатеринбург' => 'https://images.unsplash.com/photo-1513326738677-b964603b136d?w=800',
        '_default' => 'https://images.unsplash.com/photo-1436491865332-7a61a109cc05?w=800',
    ];

// Фото стран для карточек акций (fallback для всех стран из API) — подходящее изображение под каждую страну.
$promo_country_fallback_images = is_file(__DIR__ . '/../../backend/config/promo_country_images.php')
    ? require __DIR__ . '/../../backend/config/promo_country_images.php'
    : [];

// 3 фото страны для ленты — из img (реальные фото страны) или TourVisor (отели страны)
/** Доп. курорты для блока «Курорты и города» (если TourVisor /regions отдаёт не всё или только russianName) */
$promo_regions_supplement = [];
if ($country_name === 'Турция' && $country_id > 0) {
    $suppFile = __DIR__ . '/../../backend/config/promo_turkey_regions_supplement.php';
    if (is_file($suppFile)) {
        $promo_regions_supplement = require $suppFile;
    }
}

$amg_country_tape = [
    'Турция' => ['/frontend/window/img/турция/ostrovok-filters-4-10.jpg', '/frontend/window/img/турция/8adb6bb9b9dffab0eaabe4b0bc19c702.jpg', '/frontend/window/img/турция/bodrum-1.jpg'],
    'Египет' => ['/frontend/window/img/египет/photo_2025-11-27_17-02-33.jpg', '/frontend/window/img/египет/photo_2025-11-27_17-02-34.jpg', '/frontend/window/img/египет/OIP.png'],
    'Таиланд' => ['/frontend/window/img/таиланд/f6abf1e77961201063281c7d41fea1ef.jpg', '/frontend/window/img/таиланд/870112554ed554357b844f61493ce547.jpg', '/frontend/window/img/таиланд/91ab2281edaad46dd43d245fccc3d9a0.jpg'],
    'ОАЭ' => ['/frontend/window/img/ОАЭ/293d346edb7b418fb57d0370087191ae.jpg', '/frontend/window/img/ОАЭ/d7d6b9977c657fc97e65d3d22219d8dc.jpg', '/frontend/window/img/ОАЭ/734f8edf8d0c999bb97522274e25b32c.jpg'],
    'Мальдивы' => ['/frontend/window/img/мальдивы/1a8d863f3a6095f994c7d10d6f82960c.jpg', '/frontend/window/img/мальдивы/3fadde43a70f778ca631c0a28ed34a40.jpg', '/frontend/window/img/мальдивы/1a8d863f3a6095f994c7d10d6f82960c.jpg'],
    'Сейшелы' => ['/frontend/window/img/сейшелы/35ff072cc8f8c7eda59a97b543a7f1e4.jpg', '/frontend/window/img/сейшелы/a605d9c888f456b0bd001f4b3ef79d68.jpg', '/frontend/window/img/сейшелы/c3ef99b44739059a79cbe4c652b198df.jpg'],
];

/** Без фильтра по звёздам: гостевые дома / без категории звёзд — только Абхазия. */
$promo_no_star_filters = ($country_id === 46);

require_once __DIR__ . '/../../backend/components/yandex_metrika.php';
$th_ym_id = th_yandex_metrika_counter_id();

$th_promo_page_config = [
    'step' => 'unified',
    'countryId' => $country_id,   /* pre-selected country (if came from direct URL) */
    'tvApiBase' => $tv_api_base,
    'tvImageProxy' => $tv_image_proxy,
    'ymId' => $th_ym_id,
    'departureId' => $departure_id,
    'departureName' => $departure_name,
    'defaultDepartureId' => $promo_default_departure_id,
    'defaultDepartureName' => $promo_default_departure_name,
    /* countryId уже задан выше; оставляем дублирующие поля для совместимости */
    'countryName' => $country_name,
    'countryNameAcc' => $country_name_acc,
    'localCountryImages' => $local_country_images,
    'promoCountryFallbackImages' => $promo_country_fallback_images,
    'departureCityImages' => $departure_city_images ?? [],
    'promoRegionsSupplement' => $promo_regions_supplement ?? [],
    'promoNoStarFilters' => $promo_no_star_filters,
    'thFeaturePromoDirectFlightsThVn' => th_feature_promo_direct_flights_thailand_vietnam(),
    'thFeaturePromoVietnamNearestFallback' => th_feature_promo_vietnam_nearest_fallback(),
    'popularCountries' => (static function (): array {
        $f = __DIR__ . '/../../backend/config/popular_countries.php';
        return is_file($f) ? (require $f) : [];
    })(),
    'promoExcludedCountryIds' => (static function (): array {
        $f = __DIR__ . '/../../backend/config/promo_excluded_country_ids.php';
        return is_file($f) ? (require $f) : [];
    })(),
    /* Манифест data/promo_cache_index.json — бейджи «акции» без ожидания API */
    'promoCacheIndexByDeparture' => th_promo_speed_index_for_frontend(),
    'promoVirtualDestinations' => th_promo_virtual_destinations_for_frontend(),
    'promoInstantCacheCountryIds' => (static function (): array {
        $f = __DIR__ . '/../../backend/config/promo_instant_cache_country_ids.php';
        $ids = is_file($f) ? (require $f) : [4, 1, 16, 2, 47, 46, 8];
        $ex = is_file(__DIR__ . '/../../backend/config/promo_excluded_country_ids.php')
            ? (require __DIR__ . '/../../backend/config/promo_excluded_country_ids.php') : [];
        $exMap = array_fill_keys(array_map('intval', $ex), true);
        $out = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0 && !isset($exMap[$id])) {
                $out[] = $id;
            }
        }
        return $out;
    })(),
];

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <title><?php echo ($country_id && $departure_id) ? htmlspecialchars('Туры со скидкой в ' . $country_name_acc . ' из ' . ($departure_name_gen ?: $departure_name)) : ($departure_id ? htmlspecialchars('Горящие туры из ' . ($departure_name_gen ?: $departure_name) . ' — выбор страны') : 'Горящие туры — выберите город вылета'); ?> - Travel Hub</title>
    <meta name="description" content="<?php echo ($country_id && $departure_id) ? htmlspecialchars('Акционные туры в ' . $country_name_acc . ' из ' . ($departure_name_gen ?: $departure_name) . '. Специальные цены от туроператоров.') : ($departure_id ? 'Выберите страну для акционных туров из ' . ($departure_name_gen ?: $departure_name) . '.' : 'Выберите город вылета для просмотра акционных туров.'); ?>">
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    <?php include __DIR__ . '/../../backend/components/design_system_head.php'; ?>
    <link rel="stylesheet" href="/frontend/css/pages/promotions.css?v=6">
    <script>window.__TH_YM_ID=<?php echo json_encode((string)$th_ym_id, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script></head>
<body class="th-promo-page pb-24 md:pb-28">
    <?php include __DIR__ . '/../../backend/components/header.php'; ?>

    <?php if (!$departure_id): ?>
    <!-- ══ ШАГ 1: Выбор города вылета ══ -->
    <section class="promo-unified-hero">
        <div class="promo-hero-content container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto text-center">
                <span class="th-promo-hot-badge-wrap text-white text-[11px] uppercase tracking-widest font-bold mb-5 inline-flex">
                    <i class="fas fa-fire-alt mr-1.5 opacity-95"></i>Горящие предложения
                </span>
                <p class="text-white/80 text-sm font-semibold tracking-[0.18em] uppercase mb-3">Travel Hub</p>
                <h1 class="heading-font font-bold mb-4"
                    style="font-size: clamp(2rem, 5vw, 3.5rem); color:#ffffff; line-height:1.15;">
                    Горящие туры <span style="color:#FF6B6B;">до −30%</span>
                </h1>
                <p style="font-size:1.125rem; color:rgba(255,255,255,0.85); margin-bottom:0;">
                    Шаг 1: город вылета → шаг 2: страна → акционные туры
                </p>
            </div>
        </div>
    </section>
    <section class="py-12 bg-white">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <div id="promo-departures-loading" class="text-center py-16">
                    <i class="fas fa-spinner fa-spin text-4xl text-sky-500 mb-4"></i>
                    <p class="text-slate-600">Загрузка городов вылета...</p>
                </div>
                <div id="promo-departures-grid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4 hidden"></div>
                <div id="promo-departures-empty" class="hidden text-center py-12 max-w-lg mx-auto">
                    <i class="fas fa-plane text-4xl text-slate-300 mb-4"></i>
                    <p class="text-slate-600">Не удалось загрузить города вылета. Попробуйте ещё раз.</p>
                    <button type="button" id="promo-departures-retry" class="mt-4 inline-flex items-center justify-center gap-2 rounded-xl px-5 py-2.5 font-semibold text-white bg-sky-600 hover:bg-sky-700">Обновить</button>
                    <form id="promo-departures-fallback-lead" class="mt-6 text-left space-y-3" data-th-lead-source="promo_departures_fallback">
                        <input type="text" name="name" required maxlength="100" placeholder="ФИО" class="w-full px-4 py-3 rounded-xl border border-slate-200 text-slate-900" autocomplete="name">
                        <input type="tel" name="phone" required placeholder="Телефон" class="w-full px-4 py-3 rounded-xl border border-slate-200 text-slate-900" autocomplete="tel">
                        <label class="flex items-start gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="agree" required class="mt-1 rounded border-slate-300">
                            <span><?php require_once __DIR__ . '/../../backend/components/legal_consent_label.php'; echo th_legal_consent_checkbox_html(); ?></span>
                        </label>
                        <input type="text" name="website" class="absolute opacity-0 pointer-events-none w-px h-px overflow-hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
                        <button type="submit" class="w-full rounded-xl bg-sky-600 text-white font-semibold py-3 hover:bg-sky-700">Отправить заявку</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <?php else: ?>
    <!-- ══ UNIFIED: Заголовок + Плитка стран + Туры ══ -->

    <!-- 1. ЗАГОЛОВОК -->
    <section class="promo-unified-hero">
        <div class="promo-hero-content container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-5xl mx-auto">
                <div>
                    <span class="th-promo-hot-badge-wrap text-white text-[11px] uppercase tracking-widest font-bold mb-5 inline-flex">
                        <i class="fas fa-fire-alt mr-1.5 opacity-95"></i>Горящие предложения
                    </span>
                    <p class="text-white/80 text-sm font-semibold tracking-[0.18em] uppercase mb-3">Travel Hub</p>
                    <h1 class="heading-font text-4xl sm:text-5xl lg:text-6xl font-bold leading-tight"
                        style="color:#ffffff;">
                        Горящие туры<br>
                        из&nbsp;<span id="promo-hero-departure-gen" style="color:#FF6B6B;"><?php echo htmlspecialchars($departure_name_gen ?: $departure_name ?: 'Самары'); ?></span>
                    </h1>
                    <p class="mt-4 text-base sm:text-lg max-w-xl" style="color:rgba(255,255,255,0.85);">
                        Выберите страну — покажем акции. Фильтры по отелям — уже в списке туров.
                    </p>
                    <div class="promo-hero-lead mt-6 max-w-md">
                        <button type="button" id="promo-hero-lead-btn" class="promo-hero-lead__btn">
                            <i class="fas fa-phone" aria-hidden="true"></i>
                            Перезвонить с лучшими акциями
                        </button>
                        <p class="promo-hero-lead__hint">Без спама · перезвоним за 15 минут</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 1b. ГОРОД ВЫЛЕТА -->
    <section class="promo-departure-bar" aria-label="Город вылета">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-5xl mx-auto promo-departure-bar__inner">
                <span class="promo-departure-picker__label promo-departure-picker__label--bar">Город вылета</span>
                <div class="promo-departure-picker promo-departure-picker--bar">
                    <div class="promo-departure-trigger-wrap">
                        <button type="button"
                                id="promo-departure-trigger"
                                class="promo-departure-trigger"
                                aria-haspopup="listbox"
                                aria-expanded="false"
                                aria-controls="promo-departure-menu">
                            <i class="fas fa-plane-departure" aria-hidden="true"></i>
                            <span id="promo-departure-label"><?php echo htmlspecialchars($departure_name ?: 'Самара', ENT_QUOTES, 'UTF-8'); ?></span>
                            <i class="fas fa-chevron-down text-xs opacity-90" aria-hidden="true"></i>
                        </button>
                        <div id="promo-departure-menu" class="promo-departure-menu" role="listbox" aria-label="Город вылета" hidden>
                            <button type="button" class="promo-departure-menu__item<?php echo $departure_id === 7 ? ' is-active' : ''; ?>"
                                    data-departure-id="7" data-departure-name="Самара" role="option" aria-selected="<?php echo $departure_id === 7 ? 'true' : 'false'; ?>">Самара</button>
                            <button type="button" class="promo-departure-menu__item<?php echo $departure_id === 1 ? ' is-active' : ''; ?>"
                                    data-departure-id="1" data-departure-name="Москва" role="option" aria-selected="<?php echo $departure_id === 1 ? 'true' : 'false'; ?>">Москва</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 2. ПЛИТКА СТРАН -->
    <section class="py-8 sm:py-10 bg-white" id="promo-countries-section">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <div id="promo-countries-loading" class="text-center py-12">
                    <i class="fas fa-spinner fa-spin text-3xl text-orange-500 mb-3"></i>
                    <p class="text-slate-500 text-sm">Загружаем популярные направления…</p>
                </div>

                <!-- Популярные направления -->
                <div id="promo-popular-wrap" class="hidden">
                    <div class="promo-section-hd">
                        <span class="promo-section-accent"></span>
                        <h2 class="promo-section-title">Популярные направления</h2>
                    </div>
                    <div id="promo-popular-grid" class="promo-cp-grid"></div>
                    <div class="mt-6 mb-2 border-t border-slate-100"></div>
                </div>

                <!-- «Все направления» отключены — только популярные -->
                <div id="promo-other-loading" class="hidden" aria-hidden="true"></div>
                <div id="promo-other-wrap" class="promo-other-countries hidden" hidden aria-hidden="true">
                    <div id="promo-countries-grid" class="promo-cp-grid"></div>
                </div>

                <div id="promo-countries-empty" class="hidden text-center py-10 max-w-lg mx-auto">
                    <i class="fas fa-globe text-4xl text-slate-300 mb-4"></i>
                    <p id="promo-countries-empty-msg" class="text-slate-600">Не удалось загрузить страны. Попробуйте позже.</p>
                    <button type="button" id="promo-countries-retry"
                            class="mt-4 inline-flex items-center justify-center gap-2 rounded-xl px-5 py-2.5 font-semibold text-white bg-orange-500 hover:bg-orange-600">
                        Обновить список
                    </button>
                    <button type="button" class="mt-3 inline-flex items-center justify-center gap-2 rounded-xl px-5 py-2.5 font-semibold text-white bg-[#FF6B6B] hover:opacity-95"
                            data-th-site-feedback data-th-track="lead_bar">
                        Перезвонить с подбором
                    </button>
                </div>

                <p class="text-center text-sm text-slate-500 mt-8 mb-2">
                    Гибкие даты?
                    <a href="/frontend/window/tour-calendar.php?departureId=<?php echo (int) $departure_id; ?>"
                       class="font-semibold text-[#5DA9A4] hover:underline">Календарь выгодных дат</a>
                </p>
            </div>
        </div>
    </section>

    <!-- 3. БЛОК ТУРОВ (скрыт до выбора страны) -->
    <section class="py-10 bg-slate-50 hidden" id="promo-tours-section">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <div id="promo-tours-header" class="mb-8">
                    <button type="button" id="promo-back-to-countries"
                            class="promo-back-btn">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i>
                        Другие горящие туры
                    </button>
                    <h2 class="heading-font text-2xl sm:text-3xl font-bold text-slate-900">
                        <span id="promo-tours-title">Акционные туры</span>
                    </h2>
                    <p id="promo-tours-subtitle" class="text-slate-500 mt-1 text-sm">
                        Цены за 2 взрослых (по умолчанию). Состав туристов меняется на главной странице.
                    </p>
                    <div id="promo-filters-row" class="mt-5">
                        <button type="button" id="promo-stars-trigger" class="promo-stars-trigger" aria-haspopup="dialog" aria-expanded="false">
                            <span class="promo-stars-trigger__badge" aria-hidden="true">
                                <i class="fas fa-sliders-h"></i>
                            </span>
                            <span class="promo-stars-trigger__inner">
                                <span class="promo-stars-trigger__title">Категория отеля</span>
                                <span id="promo-stars-label" class="promo-stars-trigger__hint" aria-live="polite">Необязательно — можно смотреть все туры</span>
                            </span>
                            <span class="promo-stars-trigger__chev" aria-hidden="true">
                                <i class="fas fa-chevron-down promo-stars-trigger__icon"></i>
                            </span>
                        </button>
                    </div>

                    <!-- Попап выбора звёздности -->
                    <div id="promo-stars-backdrop" class="promo-stars-backdrop" style="display:none" aria-hidden="true"></div>
                    <div id="promo-stars-popup" role="dialog" aria-modal="true" aria-label="Выбор категории отеля" style="display:none" class="promo-stars-popup">
                        <div class="promo-stars-popup__header">
                            <span class="promo-stars-popup__title">Категория отеля</span>
                            <button type="button" id="promo-stars-close" class="promo-stars-popup__close" aria-label="Закрыть">✕</button>
                        </div>
                        <div class="promo-stars-popup__list">
                            <label class="promo-stars-popup__item">
                                <input type="checkbox" class="promo-star-cb" value="3">
                                <span class="promo-stars-popup__item-face"><span>★★★</span><small>3 звезды</small></span>
                            </label>
                            <label class="promo-stars-popup__item">
                                <input type="checkbox" class="promo-star-cb" value="4">
                                <span class="promo-stars-popup__item-face"><span>★★★★</span><small>4 звезды</small></span>
                            </label>
                            <label class="promo-stars-popup__item">
                                <input type="checkbox" class="promo-star-cb" value="5">
                                <span class="promo-stars-popup__item-face"><span>★★★★★</span><small>5 звёзд</small></span>
                            </label>
                            <label class="promo-stars-popup__item">
                                <input type="checkbox" class="promo-star-cb" value="all" checked>
                                <span class="promo-stars-popup__item-face"><span>Все</span><small>любая категория</small></span>
                            </label>
                        </div>
                        <button type="button" id="promo-stars-apply" class="promo-stars-popup__apply">Показать туры</button>
                    </div>
                </div>

                <div id="promo-tours-loading" class="hidden text-center py-10">
                    <i class="fas fa-spinner fa-spin text-3xl text-orange-500 mb-3"></i>
                    <p class="text-slate-500 text-sm">Загружаем туры со скидкой...</p>
                </div>

                <div id="promo-tours-results" class="th-tour-grid hidden"></div>
                <div id="promo-tours-empty" class="hidden" style="text-align:center;padding:40px 16px">
                    <div style="font-size:48px;margin-bottom:12px;opacity:.35">✈</div>
                    <p id="promo-tours-empty-msg" class="text-slate-600" style="font-size:16px;font-weight:600;color:#334155;margin-bottom:8px">
                        По этому направлению горящих туров пока нет
                    </p>
                    <p id="promo-tours-empty-hint" style="font-size:14px;color:#64748b;margin-bottom:24px;max-width:420px;margin-left:auto;margin-right:auto">
                        Горящие туры появляются за&nbsp;1–7&nbsp;дней до вылета и разбираются быстро.
                        Мы можем подобрать тур в&nbsp;это направление индивидуально — оставьте заявку.
                    </p>
                    <div style="display:flex;flex-wrap:wrap;gap:12px;justify-content:center">
                        <button type="button" id="promo-back-to-countries-2" class="promo-back-btn">
                            <i class="fas fa-arrow-left" aria-hidden="true"></i> Другие горящие туры
                        </button>
                        <button type="button" id="promo-empty-pick-btn" class="th-promo-pick-tour-btn">
                            <i class="fas fa-fire-alt" aria-hidden="true"></i> Подобрать тур
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <script>
    window.__TH_PROMO_PAGE__ = <?php echo json_encode($th_promo_page_config, JSON_UNESCAPED_UNICODE); ?>;
    (function () {
      var cfg = window.__TH_PROMO_PAGE__;
      if (!cfg) return;
      var hadDepQuery = <?php echo (isset($_GET['departureId']) && (int) $_GET['departureId'] > 0) ? 'true' : 'false'; ?>;
      try {
        if (!hadDepQuery) {
          var lid = localStorage.getItem('th_departure_id');
          var lnm = localStorage.getItem('th_departure_name');
          function normDep(n) {
            return String(n || '').toLowerCase().replace(/ё/g, 'е').trim();
          }
          function depBlocked(n) {
            var s = normDep(n);
            return s === 'красноярск' || s === 'krasnoyarsk';
          }
          function normDepId(x) {
            var n = parseInt(String(x), 10);
            if (isNaN(n) || n <= 0) return cfg.defaultDepartureId || 7;
            if (n === 12) return cfg.defaultDepartureId || 7;
            if (n === 28) return 1;
            return n;
          }
          if (lid && lnm && !depBlocked(lnm)) {
            var x = normDepId(lid);
            if (x > 0) {
              cfg.departureId = x;
              cfg.departureName = String(lnm);
            }
          } else if (window.TH_DEPARTURE && window.TH_DEPARTURE.id && !depBlocked(window.TH_DEPARTURE.name)) {
            cfg.departureId = normDepId(window.TH_DEPARTURE.id) || cfg.defaultDepartureId || 7;
            cfg.departureName = String(window.TH_DEPARTURE.name || cfg.defaultDepartureName || 'Самара');
          }
        }
        if (cfg.departureName && String(cfg.departureName).toLowerCase().replace(/ё/g, 'е').trim() === 'красноярск') {
          cfg.departureId = cfg.defaultDepartureId || 7;
          cfg.departureName = cfg.defaultDepartureName || 'Самара';
        }
        if (cfg.defaultDepartureId === 12 || cfg.defaultDepartureId === 28) {
          cfg.defaultDepartureId = cfg.defaultDepartureId === 28 ? 1 : 7;
        }
        if (cfg.departureId === 12 || cfg.departureId === 28) {
          cfg.departureId = cfg.departureId === 28 ? 1 : 7;
        }
        if (cfg.departureId === 1) {
          cfg.departureName = 'Москва';
        } else {
          cfg.departureId = cfg.defaultDepartureId || 7;
          if (cfg.departureId !== 1) cfg.departureId = 7;
          cfg.departureName = cfg.defaultDepartureName || 'Самара';
        }
        /* Не менять cfg.step: он должен совпадать с разметкой PHP. Иначе при LS-городе без ?departureId=
           остаётся только блок «города вылета», а JS уходит в ветку countries и вечный спиннер. */
      } catch (e) {}
    })();
    </script>
    <?php
    $_th_promo_js_path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'promotions-page.js';
    $_th_promo_js_ver = is_file($_th_promo_js_path) ? (string) filemtime($_th_promo_js_path) : '1';
    $_th_fp_pick_path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'tourvisor-flight-pick.js';
    $_th_fp_pick_v = is_file($_th_fp_pick_path) ? (string) filemtime($_th_fp_pick_path) : '1';
    ?>
    <script src="/frontend/js/tourvisor-flight-pick.js?v=<?php echo htmlspecialchars($_th_fp_pick_v, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <?php
    $_th_promo_lead_path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'th-promo-lead.js';
    $_th_promo_lead_v = is_file($_th_promo_lead_path) ? (string) filemtime($_th_promo_lead_path) : '1';
    ?>
    <script src="/frontend/js/th-promo-lead.js?v=<?php echo htmlspecialchars($_th_promo_lead_v, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="/frontend/js/promotions-page.js?v=<?php echo htmlspecialchars($_th_promo_js_ver, ENT_QUOTES, 'UTF-8'); ?>" defer></script>

    <?php include __DIR__ . '/../../backend/components/footer.php'; ?>

    <nav id="promo-sticky-cta" aria-label="Быстрая заявка">
        <div class="mx-auto max-w-7xl px-3 py-3 flex justify-center">
            <button type="button" id="promo-sticky-cta-btn" class="th-promo-pick-tour-btn">
                <i class="fas fa-phone" aria-hidden="true"></i>
                Оставить заявку
            </button>
        </div>
    </nav>

    <div id="promo-results-sticky-lead" class="th-results-sticky-lead" aria-label="Быстрая заявка по направлению">
        <button type="button" id="promo-results-sticky-lead-btn" class="th-results-sticky-lead__btn">
            <i class="fas fa-phone" aria-hidden="true"></i>
            Подобрать горящий тур
        </button>
    </div>
</body>
</html>