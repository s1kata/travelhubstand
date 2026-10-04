<?php
/**
 * Календарь выгодных дат — свод лучших цен по всем направлениям на даты вперёд.
 * Минимальный фильтр: только город вылета.
 */
require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/components/tourvisor_proxy_url.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = 'tour-calendar';

$qDepartureId = isset($_GET['departureId']) ? (int) $_GET['departureId'] : 0;
$defaultDepartureId = ($qDepartureId === 1) ? 1 : 7;
$imageProxy = get_tourvisor_image_proxy_base_url();
require_once __DIR__ . '/../../backend/components/deals_calendar.php';
$ladder = th_deals_calendar_ladder();

$popularCountries = [];
$countriesFile = __DIR__ . '/../../backend/config/popular_countries.php';
if (is_file($countriesFile)) {
    $loaded = require $countriesFile;
    if (is_array($loaded)) {
        $popularCountries = $loaded;
    }
}
if ($popularCountries === []) {
    $popularCountries = [
        ['id' => 4, 'name' => 'Турция'],
        ['id' => 1, 'name' => 'Египет'],
        ['id' => 2, 'name' => 'Таиланд'],
    ];
}

$defaultCountryId = (int) ($popularCountries[0]['id'] ?? 4);
$defaultCountryName = (string) ($popularCountries[0]['name'] ?? 'Турция');
$qCountryId = isset($_GET['countryId']) ? (int) $_GET['countryId'] : 0;
$qDepartureId = isset($_GET['departureId']) ? (int) $_GET['departureId'] : 0;
if ($qCountryId > 0) {
    foreach ($popularCountries as $c) {
        if ((int) ($c['id'] ?? 0) === $qCountryId) {
            $defaultCountryId = $qCountryId;
            $defaultCountryName = (string) ($c['name'] ?? $defaultCountryName);
            break;
        }
    }
}
$defaultDepartureId = ($qDepartureId === 1) ? 1 : 7;
$imageProxy = get_tourvisor_image_proxy_base_url();
$assetV = '11';

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <title>Календарь выгодных дат | Travel Hub</title>
    <meta name="description" content="Календарь выгодных туров Travel Hub: выгодные и пониженные цены по всем направлениям на ближайшие месяцы. Самара и Москва.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__ . '/../../backend/components/design_system_head.php'; ?>
    <link rel="stylesheet" href="/frontend/css/pages/deals-calendar.css?v=<?php echo htmlspecialchars($assetV, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="ds-page antialiased th-deals-cal-page">
    <?php include __DIR__ . '/../../backend/components/header.php'; ?>

    <main class="th-deals-cal-main relative z-10">
        <div class="th-deals-cal-shell" id="th-deals-cal">
            <header class="th-deals-cal-hero">
                <div class="th-deals-cal-hero__glow" aria-hidden="true"></div>
                <div class="th-deals-cal-hero__row">
                    <div class="th-deals-cal-hero__inner">
                        <p class="th-deals-cal-hero__brand">Travel Hub</p>
                        <h1 class="th-deals-cal-hero__title">Календарь выгодных туров</h1>
                        <p class="th-deals-cal-hero__lead">Свод лучших цен по всем направлениям. Две метки: выгодная и пониженная цена. Окно месяцев сдвигается вперёд вместе с сезоном.</p>
                    </div>
                    <div class="th-deals-cal-hero__tools">
                        <span class="th-deals-cal-hero__tools-label">Вылет</span>
                        <div class="th-deals-cal__chips th-deals-cal__chips--hero" id="th-dc-departure-chips" role="group" aria-label="Город вылета">
                            <button type="button" class="th-deals-cal__chip<?php echo $defaultDepartureId === 7 ? ' is-active' : ''; ?>" data-dep="7">Самара</button>
                            <button type="button" class="th-deals-cal__chip<?php echo $defaultDepartureId === 1 ? ' is-active' : ''; ?>" data-dep="1">Москва</button>
                        </div>
                        <select id="th-dc-departure" class="th-deals-cal__sr-only" tabindex="-1" aria-hidden="true">
                            <option value="7"<?php echo $defaultDepartureId === 7 ? ' selected' : ''; ?>>Самара</option>
                            <option value="1"<?php echo $defaultDepartureId === 1 ? ' selected' : ''; ?>>Москва</option>
                        </select>
                        <p class="th-deals-cal-hero__summary" id="th-dc-summary" aria-live="polite">Самара · все направления</p>
                    </div>
                </div>
            </header>

            <section class="th-deals-cal-board th-deals-cal-board--full" aria-label="Календарь и туры">
                <div class="th-deals-cal__panel th-deals-cal__panel--cal" id="th-dc-cal-panel">
                    <div class="th-deals-cal__month-head">
                        <div>
                            <p class="th-deals-cal__eyebrow">Лучшие цены по дням</p>
                            <h2 class="th-deals-cal__month-title" id="th-dc-month-title">Месяц</h2>
                        </div>
                        <div class="th-deals-cal__nav">
                            <button type="button" id="th-dc-prev" aria-label="Предыдущий месяц"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
                            <button type="button" id="th-dc-next" aria-label="Следующий месяц"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <div class="th-deals-cal__weekdays" id="th-dc-weekdays" aria-hidden="true"></div>
                    <div class="th-deals-cal__grid" id="th-dc-grid" role="grid" aria-label="Календарь выгодных туров"></div>
                    <div class="th-deals-cal__legend">
                        <span><i class="lg-deal" aria-hidden="true"></i>Выгодная цена</span>
                        <span><i class="lg-reduced" aria-hidden="true"></i>Пониженная цена</span>
                        <span><i class="lg-empty" aria-hidden="true"></i>Пока пусто</span>
                    </div>
                </div>

                <div class="th-deals-cal__panel th-deals-cal__panel--results" id="th-dc-results-panel">
                    <div class="th-deals-cal__results-head">
                        <div>
                            <p class="th-deals-cal__eyebrow">Туры на день</p>
                            <h2 class="th-deals-cal__results-title" id="th-dc-results-title">Выберите дату</h2>
                        </div>
                        <p class="th-deals-cal__results-sub" id="th-dc-results-sub"></p>
                    </div>
                    <div class="th-deals-cal__empty-state" id="th-dc-empty">
                        <div class="th-deals-cal__empty-icon" aria-hidden="true"><i class="fas fa-calendar-day"></i></div>
                        <p class="th-deals-cal__empty-title">Выберите дату с ценой</p>
                        <p class="th-deals-cal__empty-text">Красная метка — выгодная цена, бирюзовая — пониженная. Нажмите день — покажем туры.</p>
                    </div>
                    <div class="th-deals-cal__list" id="th-dc-list"></div>
                    <div class="th-deals-cal__cta-row">
                        <a href="/frontend/window/promotions.php" class="th-deals-cal__btn th-deals-cal__btn--ghost">Все акции</a>
                        <a href="/index.php#tour-search-section" class="th-deals-cal__btn th-deals-cal__btn--primary">Полный поиск</a>
                    </div>
                </div>
            </section>

            <p class="th-deals-cal-footnote">Цены ориентировочные из кэша акций по популярным направлениям. Точная бронь — в карточке тура или в офисе.</p>
        </div>
    </main>

    <?php include __DIR__ . '/../../backend/components/footer.php'; ?>

    <script>
    window.TH_DEALS_CAL = {
        departureId: <?php echo (int) $defaultDepartureId; ?>,
        countryId: 0,
        monthsAhead: <?php echo (int) $ladder['monthsAhead']; ?>,
        viewMaxYm: <?php echo json_encode($ladder['viewMaxYm'], JSON_UNESCAPED_UNICODE); ?>,
        priceMapUrl: '/backend/api/calendar_price_map.php',
        dayToursUrl: '/backend/api/calendar_day_tours.php',
        imageProxy: <?php echo json_encode($imageProxy, JSON_UNESCAPED_SLASHES); ?>
    };
    </script>
    <script src="/frontend/js/deals-calendar.js?v=<?php echo htmlspecialchars($assetV, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>
</html>
