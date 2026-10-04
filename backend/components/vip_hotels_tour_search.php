<?php
/**
 * Компонент поиска туров для страницы VIP-отелей Турции
 * Фиксированная страна (Турция, countryId=4), выбор курорта вместо страны
 */

// По умолчанию Турция (VIP). На hotel-hub из каталога Tourvisor можно передать $vipCountryId.
$VIP_COUNTRY_ID = isset($vipCountryId) && (int) $vipCountryId > 0 ? (int) $vipCountryId : 4;

// Режим «страница отеля»: при заданных $vipHotelName и $vipHotelCity — фильтрация по отелю, свой заголовок
$vipHotelName = $vipHotelName ?? null;
$vipHotelCity = $vipHotelCity ?? null;
$vipHotelTvIds = $vipHotelTvIds ?? null; // comma-separated Tourvisor hotel ids
$regionIdsMap = ['Antalya' => '20', 'Belek' => '21', 'Kemer' => '22'];
$vipHotelRegionId = ($vipHotelCity && isset($regionIdsMap[$vipHotelCity])) ? $regionIdsMap[$vipHotelCity] : '';

// Единый путь к API (прокси + кэш): один хелпер для всех поисковиков
require_once __DIR__ . '/tourvisor_proxy_url.php';
require_once __DIR__ . '/../config/departure_defaults.php';
$apiBase = get_tourvisor_proxy_base_url();
$imageProxyBase = get_tourvisor_image_proxy_base_url();
$vipTvDepartureId = th_departure_default_id();
?>

<!-- Поиск туров для VIP-отелей / страница отеля -->
<section id="vip-tour-search-section" class="py-16 bg-gradient-to-b from-white to-sky-50">
    <div class="th-container mx-auto px-6">
        <div class="text-center mb-12">
            <span class="pill-badge mb-4">Поиск туров</span>
            <?php if ($vipHotelName): ?>
            <h2 class="heading-font text-3xl sm:text-4xl font-bold text-slate-900 mb-4">Туры в <?php echo htmlspecialchars($vipHotelName); ?></h2>
            <p class="text-slate-700 max-w-2xl mx-auto text-lg">Подберите тур по датам, ночам, туристам и фильтрам</p>
            <?php else: ?>
            <h2 class="heading-font text-3xl sm:text-4xl font-bold text-slate-900 mb-4">Подберём тур под ваш отпуск</h2>
            <p class="text-slate-700 max-w-2xl mx-auto text-lg">Заполните форму — и мы предложим варианты по вашим датам и бюджету</p>
            <?php endif; ?>
        </div>
        
        <!-- Форма поиска -->
        <div class="max-w-7xl mx-auto">
            <div class="surface-card p-4 sm:p-6 md:p-8 lg:p-10">
                <!-- Основные поля -->
                <input type="hidden" id="vip-tv-departure" name="departureId" value="<?php echo (int) $vipTvDepartureId; ?>">
                <div class="flex flex-wrap items-end gap-3 md:gap-4 mb-4">
                    <div class="tv-field flex-1 min-w-[140px] max-w-[220px]">
                        <label class="block text-xs font-medium text-slate-500 mb-1">Даты</label>
                        <div class="relative" id="vip-tv-dates-wrap">
                            <input type="text" id="vip-tv-dates" class="w-full px-3 py-2.5 pr-9 rounded-lg border border-slate-200 text-slate-800 text-sm focus:ring-2 focus:ring-[#5DA9A4]/30 focus:border-[#5DA9A4] cursor-pointer bg-white" placeholder="Выберите период в календаре" data-input readonly autocomplete="off">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"><i class="fas fa-calendar-alt text-sm"></i></span>
                        </div>
                        <div id="vip-tv-date-presets" class="tv-date-presets mt-2"></div>
                    </div>
                    <div class="tv-field min-w-[140px] max-w-[200px]" id="vip-tv-nights-trigger">
                        <label class="block text-xs font-medium text-slate-500 mb-1">Ночей</label>
                        <button type="button" class="w-full px-3 py-2.5 rounded-lg border border-sky-200 text-slate-800 text-sm text-left bg-white hover:bg-sky-50 flex items-center justify-between transition-colors" id="vip-tv-nights-summary">
                            <span id="vip-tv-nights-summary-text">7 — 14</span>
                            <i class="fas fa-chevron-down text-sky-400 text-xs"></i>
                        </button>
                    </div>
                    <div id="vip-tv-nights-popup" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" aria-hidden="true" style="display:none">
                        <div class="tv-nights-modal-card bg-white rounded-2xl shadow-2xl w-full min-w-0 p-5 border border-sky-100" role="dialog" aria-label="Сколько ночей в отеле">
                            <h3 class="font-bold text-slate-900 text-base mb-1">Сколько ночей?</h3>
                            <p class="text-xs text-slate-500 mb-3">Один тап — или свой диапазон ниже</p>
                            <div id="vip-tv-nights-quick" class="tv-nights-quick mb-3"></div>
                            <p class="text-xs text-slate-500 mb-2">Свой диапазон: сначала «от», потом «до»</p>
                            <div id="vip-tv-nights-grid" class="grid grid-cols-4 sm:grid-cols-7 gap-1.5 mb-3">
                                <?php for ($n = 1; $n <= 28; $n++): ?>
                                <button type="button" class="vip-tv-nights-cell tv-nights-cell min-w-[2.25rem] min-h-[2.75rem] px-0 py-1 rounded-lg text-sm font-semibold transition-colors flex flex-col items-center justify-center gap-0 leading-none bg-slate-100 text-slate-700 hover:bg-sky-100 hover:text-sky-700" data-n="<?php echo $n; ?>">
                                    <span class="cell-num"><?php echo $n; ?></span>
                                    <span class="cell-label text-[10px] opacity-0 leading-tight"><?php
                                        if ($n === 1) echo 'ночь';
                                        elseif ($n >= 2 && $n <= 4) echo 'ночи';
                                        else echo 'ночей';
                                    ?></span>
                                </button>
                                <?php endfor; ?>
                            </div>
                            <button type="button" id="vip-tv-nights-apply" class="w-full py-3.5 min-h-[52px] rounded-xl bg-gradient-to-r from-[#5DA9A4] to-[#8CC7C3] text-white text-base font-bold shadow-md shadow-[#5DA9A4]/25 hover:shadow-lg transition-all">Готово</button>
                        </div>
                    </div>
                    <div class="tv-field min-w-[120px] max-w-[150px]" id="vip-tv-tourists-trigger">
                        <label class="block text-xs font-medium text-slate-500 mb-1">Туристы</label>
                        <button type="button" class="w-full px-3 py-2.5 rounded-lg border border-sky-200 text-slate-800 text-sm text-left bg-white hover:bg-sky-50 flex items-center justify-between transition-colors" id="vip-tv-tourists-summary">
                            <span id="vip-tv-tourists-summary-text">2 взр., 0 дет.</span>
                            <i class="fas fa-chevron-down text-sky-400 text-xs"></i>
                        </button>
                    </div>
                </div>
                <div id="vip-tv-tourists-block" class="hidden mt-4 p-4 rounded-xl bg-sky-50/80 border border-sky-100">
                    <p class="text-sm font-semibold text-slate-800 uppercase tracking-wide mb-4">Туристы</p>
                    <div class="flex items-center gap-2 mb-4">
                        <button type="button" id="vip-tv-adults-minus" class="w-9 h-9 rounded-full bg-sky-500 text-white flex items-center justify-center hover:bg-sky-600 flex-shrink-0 transition-colors" aria-label="Меньше"><i class="fas fa-minus text-xs"></i></button>
                        <div class="flex-1 min-w-0 px-4 py-2.5 rounded-full bg-sky-100 text-slate-800 text-sm font-medium text-center"><span id="vip-tv-adults-value">2 взрослых</span></div>
                        <button type="button" id="vip-tv-adults-plus" class="w-9 h-9 rounded-full bg-sky-500 text-white flex items-center justify-center hover:bg-sky-600 flex-shrink-0 transition-colors" aria-label="Больше"><i class="fas fa-plus text-xs"></i></button>
                    </div>
                    <div id="vip-tv-children-rows" class="space-y-2 mb-3"></div>
                    <button type="button" id="vip-tv-add-child-btn" class="w-full py-2.5 rounded-full border border-sky-200 bg-sky-100 text-sky-700 text-sm font-medium hover:bg-sky-200 hover:border-sky-300 mb-4 transition-colors">Добавить ребенка</button>
                    <div id="vip-tv-child-age-picker" class="hidden mb-4 p-3 rounded-xl bg-white border border-sky-200 shadow-lg shadow-sky-100/50">
                        <p class="text-xs text-slate-500 mb-2">Выберите возраст ребенка</p>
                        <div id="vip-tv-child-age-grid" class="flex flex-wrap gap-2"></div>
                    </div>
                    <label class="flex items-center gap-2 mb-4 cursor-pointer text-sm text-slate-700">
                        <input type="checkbox" id="vip-tv-remember-tourists" class="rounded border-sky-300 text-[#5DA9A4] focus:ring-[#5DA9A4]"><span>Запомнить выбор</span>
                    </label>
                    <button type="button" id="vip-tv-tourists-apply" class="w-full py-3 rounded-xl bg-gradient-to-r from-[#5DA9A4] to-[#8CC7C3] text-white text-sm font-semibold shadow-md shadow-[#5DA9A4]/25 hover:shadow-lg hover:-translate-y-0.5 transition-all">Выбрать</button>
                </div>
                <!-- Фильтры (иконка) + Кнопка поиска -->
                <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-slate-100">
                    <details id="vip-tv-filters-details" class="tv-filters-details group">
                        <summary class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-sky-200 text-slate-600 text-sm font-medium cursor-pointer hover:bg-sky-50 hover:border-sky-300 transition-colors list-none [&::-webkit-details-marker]:hidden">
                            <i class="fas fa-sliders-h text-[#5DA9A4]"></i>
                            <span>Фильтры</span>
                            <i class="tv-filters-chevron fas fa-chevron-down text-xs text-slate-400 transition-transform"></i>
                        </summary>
                        <div class="mt-4 p-4 rounded-xl bg-sky-50/80 border border-sky-100">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1.5">Питание</label>
                                    <select id="vip-tv-meal" class="tv-select w-full px-3 py-2 rounded-lg border border-sky-200 text-slate-700 text-sm bg-white focus:ring-2 focus:ring-[#5DA9A4]/30 focus:border-[#5DA9A4]">
                                        <option value="">Любое</option>
                                    </select>
                                </div>
                                <?php if (!$vipHotelName): ?>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1.5">Курорт</label>
                                    <select id="vip-tv-region" class="tv-select w-full px-3 py-2 rounded-lg border border-sky-200 text-slate-700 text-sm bg-white focus:ring-2 focus:ring-[#5DA9A4]/30 focus:border-[#5DA9A4]">
                                        <option value="">Любой</option>
                                        <option value="20">Анталья</option>
                                        <option value="21">Белек</option>
                                        <option value="22">Кемер</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1.5">Категория отеля</label>
                                    <select id="vip-tv-category" class="tv-select w-full px-3 py-2 rounded-lg border border-sky-200 text-slate-700 text-sm bg-white focus:ring-2 focus:ring-[#5DA9A4]/30 focus:border-[#5DA9A4]">
                                        <option value="">Любая</option>
                                        <option value="3">3★ и выше</option>
                                        <option value="4">4★ и выше</option>
                                        <option value="5" selected>5★</option>
                                    </select>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="mt-4 pt-4 border-t border-sky-200">
                                <p class="text-sm font-semibold text-slate-800 uppercase tracking-wide mb-3">Расширенные фильтры</p>
                                <div class="flex items-center gap-2 mb-3"><span class="text-xs font-medium text-sky-600 border-b-2 border-[#5DA9A4] pb-1">ВСЕ</span></div>
                                <div id="vip-tv-adv-selected-tags" class="flex flex-wrap gap-2 mb-3 min-h-[2rem]"></div>
                                <div id="vip-tv-adv-categories" class="space-y-2">
                                    <details class="border border-sky-200 rounded-lg overflow-hidden" open><summary class="px-3 py-2.5 bg-sky-50 text-slate-800 text-sm font-medium cursor-pointer list-none flex items-center justify-between"><span>Пляж и расположение</span><i class="fas fa-chevron-up text-sky-400 text-xs"></i></summary><div id="vip-tv-adv-cat-beach" class="px-3 py-2 border-t border-sky-100 flex flex-wrap gap-3 text-sm"></div></details>
                                    <details class="border border-sky-200 rounded-lg overflow-hidden" open><summary class="px-3 py-2.5 bg-sky-50 text-slate-800 text-sm font-medium cursor-pointer list-none flex items-center justify-between"><span>В отеле</span><i class="fas fa-chevron-up text-sky-400 text-xs"></i></summary><div id="vip-tv-adv-cat-hotel" class="px-3 py-2 border-t border-sky-100 flex flex-wrap gap-3 text-sm"></div></details>
                                    <details class="border border-sky-200 rounded-lg overflow-hidden" open><summary class="px-3 py-2.5 bg-sky-50 text-slate-800 text-sm font-medium cursor-pointer list-none flex items-center justify-between"><span>В номере</span><i class="fas fa-chevron-up text-sky-400 text-xs"></i></summary><div id="vip-tv-adv-cat-room" class="px-3 py-2 border-t border-sky-100 flex flex-wrap gap-3 text-sm"></div></details>
                                    <details class="border border-sky-200 rounded-lg overflow-hidden" open><summary class="px-3 py-2.5 bg-sky-50 text-slate-800 text-sm font-medium cursor-pointer list-none flex items-center justify-between"><span>Детям</span><i class="fas fa-chevron-up text-sky-400 text-xs"></i></summary><div id="vip-tv-adv-cat-children" class="px-3 py-2 border-t border-sky-100 flex flex-wrap gap-3 text-sm"></div></details>
                                </div>
                                <button type="button" id="vip-tv-adv-apply" class="mt-4 px-6 py-2.5 rounded-xl bg-gradient-to-r from-[#5DA9A4] to-[#8CC7C3] text-white text-sm font-medium shadow-md shadow-[#5DA9A4]/25 hover:shadow-lg hover:-translate-y-0.5 transition-all">Выбрать</button>
                            </div>
                        </div>
                    </details>
                    <button id="vip-tv-search-btn" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-[#5DA9A4] to-[#8CC7C3] text-white font-semibold text-sm shadow-md shadow-[#5DA9A4]/25 hover:shadow-lg hover:-translate-y-0.5 transition-all">
                        <i class="fas fa-search mr-2"></i> Найти
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Результаты поиска -->
        <div id="vip-tv-results-wrapper" class="tv-results-shell max-w-7xl mx-auto mt-10 hidden">
            <div class="tv-results-toolbar">
                <h3 class="tv-results-toolbar__title heading-font text-xl font-bold text-slate-900">
                    Результаты <span id="vip-tv-result-count" class="text-[#5DA9A4]">0</span>
                </h3>
                <div class="tv-sort-rail">
                    <select id="vip-tv-sort" class="tv-select tv-sort-select px-3 py-2 rounded-xl border border-slate-200 text-slate-700">
                        <option value="price-asc">Сначала дешевые</option>
                        <option value="price-desc">Сначала дорогие</option>
                        <option value="rating">По рейтингу</option>
                    </select>
                </div>
            </div>
            <div id="vip-tv-search-progress" class="hidden mb-6 p-4 rounded-xl bg-slate-50 border border-slate-200">
                <div class="flex items-center gap-3">
                    <div class="animate-spin w-5 h-5 border-2 border-[#5DA9A4] border-t-transparent rounded-full"></div>
                    <span class="text-slate-600">Поиск туров...</span>
                    <span id="vip-tv-progress-text" class="text-slate-500 text-sm"></span>
                </div>
            </div>
            <div id="vip-tv-search-results" class="tv-search-results-grid">
                <!-- Карточки туров подставляются JS -->
            </div>
            <div id="vip-tv-load-more-wrapper" class="mt-10 text-center hidden">
                <button type="button" id="vip-tv-load-more-btn" class="px-8 py-3.5 rounded-xl bg-gradient-to-r from-[#5DA9A4] to-[#8CC7C3] text-white font-semibold text-sm shadow-md shadow-[#5DA9A4]/25 hover:shadow-lg hover:-translate-y-0.5 transition-all disabled:opacity-70 disabled:pointer-events-none">
                    <i class="fas fa-plus-circle mr-2"></i><span id="vip-tv-load-more-text">Загрузить ещё туры</span>
                </button>
            </div>
        </div>
    </div>
</section>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/ru.js" defer></script>
<?php
$_th_fp_vip = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'tourvisor-flight-pick.js';
$_th_fp_vip_v = is_file($_th_fp_vip) ? (string) filemtime($_th_fp_vip) : '1';
?>
<script src="/frontend/js/tourvisor-flight-pick.js?v=<?php echo htmlspecialchars($_th_fp_vip_v, ENT_QUOTES, 'UTF-8'); ?>"></script>
<style>
    .tv-select { 
        appearance: none; 
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E"); 
        background-repeat: no-repeat; 
        background-position: right 0.5rem center; 
        background-size: 1rem; 
        padding-right: 2rem; 
    }
    .tv-filters-details summary::-webkit-details-marker { display: none; }
    .tv-filters-details[open] .tv-filters-chevron { transform: rotate(180deg); }
    @media (max-width: 640px) {
        .tv-field { max-width: 100% !important; }
    }
    .flatpickr-calendar { border-radius: 16px; box-shadow: 0 22px 48px rgba(93, 169, 164, 0.2); border: 1px solid rgba(93, 169, 164, 0.2); }
    .flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange { background: #5DA9A4 !important; border-color: #5DA9A4 !important; }
    .flatpickr-day.inRange { background: rgba(93, 169, 164, 0.15) !important; box-shadow: none !important; }
    .flatpickr-day:hover { background: rgba(93, 169, 164, 0.2) !important; border-color: #5DA9A4 !important; }
    .flatpickr-months .flatpickr-month { background: #5DA9A4 !important; }
</style>
<script>
(function() {
    let TV_API_BASE = <?php echo json_encode($apiBase); ?>;
    var TV_IMAGE_PROXY = <?php echo json_encode($imageProxyBase); ?>;
    if (typeof location !== 'undefined' && location.protocol === 'https:' && typeof TV_API_BASE === 'string' && TV_API_BASE.indexOf('http://') === 0) {
        TV_API_BASE = 'https:' + TV_API_BASE.substring(5);
    }
    if (typeof location !== 'undefined' && location.protocol === 'https:' && typeof TV_IMAGE_PROXY === 'string' && TV_IMAGE_PROXY.indexOf('http://') === 0) {
        TV_IMAGE_PROXY = 'https:' + TV_IMAGE_PROXY.substring(5);
    }
    function getTourvisorImageUrl(src) {
        var fallback = 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400&q=80';
        if (!src) return fallback;
        if (window.THTourCard && typeof window.THTourCard.mapTourvisorImageUrl === 'function') {
            var mapped = window.THTourCard.mapTourvisorImageUrl(src, TV_IMAGE_PROXY);
            return mapped || fallback;
        }
        var s = String(src).trim();
        if (/^\/\//.test(s)) {
            s = (typeof location !== 'undefined' && location.protocol === 'https:' ? 'https:' : 'http:') + s;
        }
        if (/^https?:\/\/static\.tourvisor\.ru\//i.test(s)) return TV_IMAGE_PROXY + '?url=' + encodeURIComponent(s.replace(/^https:/i, 'http:'));
        if (/^static\.tourvisor\.ru\//i.test(s)) return TV_IMAGE_PROXY + '?url=' + encodeURIComponent('http://' + s);
        if (/^\/hotel_pics\//i.test(s) || /^hotel_pics\//i.test(s)) return TV_IMAGE_PROXY + '?path=' + encodeURIComponent(s.replace(/^\/+/, ''));
        if (/^https?:\/\//i.test(s)) return s;
        return s;
    }
    const VIP_COUNTRY_ID = <?php echo (int)$VIP_COUNTRY_ID; ?>;
    const HOTEL_FILTER_NAME = <?php echo json_encode($vipHotelName ?? ''); ?>;
    const HOTEL_REGION_ID = <?php echo json_encode($vipHotelRegionId); ?>;
    const HOTEL_TV_IDS = <?php echo json_encode($vipHotelTvIds ? (string) $vipHotelTvIds : ''); ?>;

    function hotelNameMatches(ourName, tvName) {
        if (!ourName || !tvName) return false;
        const a = String(ourName).toLowerCase().replace(/\s+/g, ' ').trim();
        const b = String(tvName).toLowerCase().replace(/\s+/g, ' ').trim();
        return a.includes(b) || b.includes(a) || a.replace(/[^a-z0-9]/g, '') === b.replace(/[^a-z0-9]/g, '');
    }

    function safeFetchJson(url, fallback) {
        fallback = fallback || { success: false, data: null };
        return fetch(url, { method: 'GET', cache: 'no-store' })
            .then(r => r.text().then(t => ({ ok: r.ok, status: r.status, text: t })))
            .then(o => {
                const t = (o.text || '').trim();
                if (!t) return fallback;
                try { return JSON.parse(t); } catch (e) { return fallback; }
            })
            .catch(() => fallback);
    }
    (function tvRefsEarlyFetch() {
        if (!TV_API_BASE || typeof TV_API_BASE !== 'string') return;
        const sep = TV_API_BASE.indexOf('?') >= 0 ? '&' : '?';
        window.__vip_tv_refsPromises = {
            dep: safeFetchJson(TV_API_BASE + sep + 'type=departures', { success: false, data: [] })
        };
    })();
    (function tvSearchDebugBanner() {
        var style = 'color: #5DA9A4; font-weight: bold; font-size: 11px;';
        var styleDim = 'color: #64748b; font-size: 10px;';
        console.group('%c[Tourvisor · VIP Отели] Поиск подключён: прокси + кэш', style);
        console.log('%cBase URL:', styleDim, TV_API_BASE);
        console.log('%cСтрана поиска:', styleDim, 'countryId:', VIP_COUNTRY_ID);
        console.log('%cЦепочка кэша: файл → Firestore → all_tours → живой поиск.', styleDim);
        console.groupEnd();
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

    async function tvFetch(type, params = {}) {
        const base = TV_API_BASE;
        const u = new URL(base);
        u.searchParams.set('type', type);
        const forceLive = !!(params && params._forceLive);
        const cacheOnly = !!(params && params._cacheOnly);
        const apiParams = Object.assign({}, params);
        delete apiParams._forceLive;
        delete apiParams._cacheOnly;
        Object.entries(apiParams).forEach(([k, v]) => { if (v != null && v !== '') u.searchParams.set(k, String(v)); });
        if (type === 'search-cached') {
            u.searchParams.set('cacheScope', 'country_page');
            if (forceLive) u.searchParams.set('live', '1');
            if (cacheOnly) u.searchParams.set('cacheOnly', '1');
            u.searchParams.set('_t', String(Date.now()));
        }
        const url = u.toString();
        const paramsStr = Object.keys(params).length ? JSON.stringify(params) : '{}';
        console.log('%c[Tourvisor · VIP] Запрос', 'color: #5DA9A4; font-weight: bold', 'type:', type, 'params:', paramsStr);
        try {
            const r = await fetch(url, { method: 'GET', cache: 'no-store' });
            const cacheHeader = r.headers.get('X-Tourvisor-Cache');
            const cacheSaved = r.headers.get('X-Tourvisor-Cache-Saved');
            const itemsCount = r.headers.get('X-Tourvisor-Items');
            const text = await r.text();
            if (!r.ok) throw new Error('HTTP ' + r.status);
            let j;
            try { j = text ? JSON.parse(text) : { success: false, error: 'Empty response', data: null }; } catch (e) { j = { success: false, error: 'Invalid JSON', data: null }; }
            const summary = tvFetchSummary(type, j);
            if (j.success) {
                const cacheInfo = cacheHeader ? ' | кэш: ' + cacheHeader + (cacheSaved ? ', сохранён: ' + cacheSaved : '') + (itemsCount ? ', записей: ' + itemsCount : '') : '';
                console.log('%c[Tourvisor · VIP] Ответ ✓', 'color: #22c55e; font-weight: bold', 'type:', type, '|', summary + cacheInfo);
            } else if (type === 'search-cached' && (j.error === 'Cache miss' || j.fromCache === false)) {
                console.log('%c[Tourvisor · VIP] Кэш пуст', 'color: #94a3b8', 'по этим параметрам данных нет');
            } else {
                console.warn('%c[Tourvisor · VIP] Ошибка', 'color: #ef4444', 'type:', type, 'error:', j.error || j);
            }
            return j;
        } catch (e) {
            console.error('%c[Tourvisor · VIP] Ошибка запроса ✗', 'color: #ef4444; font-weight: bold', 'type:', type, e.message, 'url:', url);
            return { success: false, error: String(e.message) };
        }
    }

    function formatPrice(price) {
        return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', minimumFractionDigits: 0 }).format(price || 0);
    }
    function formatDateRu(dateStr) {
        if (!dateStr) return '';
        return new Date(dateStr).toLocaleDateString('ru-RU', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    let countryTvDatePicker = null;
    let countryTvLastResults = [];
    const COUNTRY_TV_PAGE_SIZE = 25;
    let countryTvDisplayedCount = 0;
    var countryTvNightsFrom = 7;
    var countryTvNightsTo = 14;

    document.addEventListener('DOMContentLoaded', async function() {
        const depSel = document.getElementById('vip-tv-departure');
        const datesInp = document.getElementById('vip-tv-dates');
        const mealSel = document.getElementById('vip-tv-meal');
        const regionSel = document.getElementById('vip-tv-region');

        // Блок ТУРИСТЫ: как на скрине — строки «Ребенок до 2 лет», «Добавить ребенка», «Запомнить выбор», «Выбрать»
        var countryTvAdultsCount = 2;
        var countryTvChildrenAges = [];
        var countryTvAgeLabels = {0:'до 2 лет',2:'2 года',3:'3 года',4:'4 года',5:'5 лет',6:'6 лет',7:'7 лет',8:'8 лет',9:'9 лет',10:'10 лет',11:'11 лет',12:'12 лет',13:'13 лет',14:'14 лет',15:'15 лет'};
        var countryTvAdultsValueEl = document.getElementById('vip-tv-adults-value');
        var countryTvTouristsSummaryText = document.getElementById('vip-tv-tourists-summary-text');
        var countryTvTouristsBlock = document.getElementById('vip-tv-tourists-block');
        var countryTvChildrenRows = document.getElementById('vip-tv-children-rows');
        var countryTvAddChildBtn = document.getElementById('vip-tv-add-child-btn');
        var countryTvChildAgePicker = document.getElementById('vip-tv-child-age-picker');
        var countryTvChildAgePickerIndex = -1;
        function updateCountryTouristsSummary() {
            var t = countryTvAdultsCount + ' взр.';
            if (countryTvChildrenAges.length > 0) t += ', ' + countryTvChildrenAges.length + ' дет.';
            else t += ', 0 дет.';
            if (countryTvTouristsSummaryText) countryTvTouristsSummaryText.textContent = t;
            if (countryTvAdultsValueEl) countryTvAdultsValueEl.textContent = countryTvAdultsCount + ' взрослых';
        }
        function renderCountryChildrenRows() {
            if (!countryTvChildrenRows) return;
            countryTvChildrenRows.innerHTML = countryTvChildrenAges.map(function(age, i) {
                var label = countryTvAgeLabels[age] || ('возраст ' + age);
                return '<div class="flex items-center gap-2">' +
                    '<button type="button" class="vip-tv-child-remove w-9 h-9 rounded-full bg-sky-500 text-white flex items-center justify-center hover:bg-sky-600 flex-shrink-0 transition-colors" data-index="' + i + '" aria-label="Удалить">−</button>' +
                    '<div class="flex-1 min-w-0 px-4 py-2.5 rounded-full bg-sky-100 text-slate-800 text-sm text-center">' +
                    '<button type="button" class="vip-tv-child-age-btn w-full text-left font-medium" data-index="' + i + '">Ребенок ' + label + '</button></div></div>';
            }).join('');
            countryTvChildrenRows.querySelectorAll('.vip-tv-child-remove').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var i = parseInt(this.dataset.index, 10);
                    countryTvChildrenAges.splice(i, 1);
                    renderCountryChildrenRows();
                    updateCountryTouristsSummary();
                    countryTvChildAgePicker.classList.add('hidden');
                });
            });
            countryTvChildrenRows.querySelectorAll('.vip-tv-child-age-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    countryTvChildAgePickerIndex = parseInt(this.dataset.index, 10);
                    countryTvChildAgePicker.classList.remove('hidden');
                    var grid = document.getElementById('vip-tv-child-age-grid');
                    if (grid && grid.children.length === 0) {
                        [0,2,3,4,5,6,7,8,9,10,11,12,13,14,15].forEach(function(a) {
                            var b = document.createElement('button');
                            b.type = 'button';
                            b.className = 'px-3 py-2 rounded-lg border border-slate-200 text-slate-700 text-sm bg-white hover:border-[#5DA9A4] hover:bg-sky-50';
                            b.textContent = countryTvAgeLabels[a] || a;
                            b.dataset.age = a;
                            b.addEventListener('click', function() {
                                if (countryTvChildAgePickerIndex >= 0 && countryTvChildAgePickerIndex < countryTvChildrenAges.length) {
                                    countryTvChildrenAges[countryTvChildAgePickerIndex] = parseInt(this.dataset.age, 10);
                                    renderCountryChildrenRows();
                                    updateCountryTouristsSummary();
                                }
                                countryTvChildAgePicker.classList.add('hidden');
                            });
                            grid.appendChild(b);
                        });
                    }
                });
            });
            if (countryTvAddChildBtn) countryTvAddChildBtn.style.display = countryTvChildrenAges.length >= 3 ? 'none' : 'block';
        }
        document.getElementById('vip-tv-tourists-trigger')?.addEventListener('click', function() {
            countryTvTouristsBlock.classList.toggle('hidden');
        });
        document.getElementById('vip-tv-adults-minus')?.addEventListener('click', function() {
            if (countryTvAdultsCount > 1) { countryTvAdultsCount--; updateCountryTouristsSummary(); }
        });
        document.getElementById('vip-tv-adults-plus')?.addEventListener('click', function() {
            if (countryTvAdultsCount < 6) { countryTvAdultsCount++; updateCountryTouristsSummary(); }
        });
        countryTvAddChildBtn?.addEventListener('click', function() {
            if (countryTvChildrenAges.length < 3) { countryTvChildrenAges.push(0); renderCountryChildrenRows(); updateCountryTouristsSummary(); }
        });
        document.getElementById('vip-tv-tourists-apply')?.addEventListener('click', function() {
            if (document.getElementById('vip-tv-remember-tourists')?.checked) {
                try { localStorage.setItem('vip_tv_tourists', JSON.stringify({ adults: countryTvAdultsCount, childrenAges: countryTvChildrenAges })); } catch (e) {}
            }
            countryTvTouristsBlock.classList.add('hidden');
        });
        try {
            var saved = localStorage.getItem('vip_tv_tourists');
            if (saved) {
                var d = JSON.parse(saved);
                if (d && typeof d.adults === 'number' && d.adults >= 1 && d.adults <= 6) countryTvAdultsCount = d.adults;
                if (d && Array.isArray(d.childrenAges)) countryTvChildrenAges = d.childrenAges.filter(function(a) { var n = parseInt(a,10); return n >= 0 && n <= 15; }).slice(0, 3);
            }
        } catch (e) {}
        renderCountryChildrenRows();
        updateCountryTouristsSummary();

        // Инициализация календаря (только выбор по календарю)
        const today = new Date();
        const defaultFrom = new Date(today); defaultFrom.setDate(today.getDate() + 7);
        const defaultTo = new Date(today); defaultTo.setDate(today.getDate() + 30);
        countryTvDatePicker = flatpickr(datesInp, {
            mode: 'range',
            dateFormat: 'd-m-Y',
            locale: 'ru',
            allowInput: false,
            clickOpens: true,
            minDate: today,
            defaultDate: [defaultFrom, defaultTo],
            disableMobile: true
        });
        if (countryTvDatePicker) {
            datesInp.addEventListener('focus', function() { countryTvDatePicker.open(); });
            var vipDatesWrap = document.getElementById('vip-tv-dates-wrap');
            if (vipDatesWrap) vipDatesWrap.addEventListener('click', function(e) { e.preventDefault(); datesInp.focus(); countryTvDatePicker.open(); });
        }
        var vipPresetsEl = document.getElementById('vip-tv-date-presets');
        if (vipPresetsEl && window.THDatePresets && typeof window.THDatePresets.renderChips === 'function') {
            window.THDatePresets.renderChips(vipPresetsEl, function (preset) {
                window.THDatePresets.apply(preset, { mainPicker: countryTvDatePicker, input: datesInp });
            });
        }

        // Города вылета и питание — из API; курорты статичны (Анталья, Белек, Кемер)
        const refsPromises = window.__vip_tv_refsPromises;
        const pDep = refsPromises ? refsPromises.dep : tvFetch('departures');
        const pMeal = tvFetch('meals');

        const rDep = await pDep;
        console.log('%c[API → сайт] Ответы API получены (VIP отели)', 'color: #5DA9A4; font-weight: bold', { departures: rDep.success ? (rDep.data?.length ?? 0) + ' шт.' : 'ошибка' });

        let departuresList = [];
        if (rDep.success && Array.isArray(rDep.data) && rDep.data.length > 0) {
            departuresList = rDep.data;
            console.log('%c[API → сайт] Справочник городов вылета (VIP)', 'color: #22c55e', departuresList.length, 'шт.');
        } else {
            console.warn('[API → сайт] Города вылета: данные с API не получены');
        }

        if (window.THDeparturePreference) {
            window.THDeparturePreference.onDeparturesReady(departuresList);
        }

        const rMeal = await pMeal;
        if (rMeal.success && Array.isArray(rMeal.data) && rMeal.data.length > 0) {
            mealSel.innerHTML = '<option value="">Любое</option>' + rMeal.data.map(m =>
                `<option value="${m.id}">${m.russianName || m.name || ''}</option>`
            ).join('');
            console.log('%c[API → сайт] Данные с API применены: питание', 'color: #22c55e', rMeal.data.length, 'типов');
        } else {
            mealSel.innerHTML = '<option value="">Любое</option>';
            console.warn('[API → сайт] Питание: данные с API не получены');
        }

        window.__vip_tv_hotelServicesCache = window.__vip_tv_hotelServicesCache || {};
        var countryTvSelectedServiceIds = [];
        var countryTvServiceIdToName = {};
        function renderCountrySelectedFilterTags() {
            var wrap = document.getElementById('vip-tv-adv-selected-tags');
            if (!wrap) return;
            wrap.innerHTML = countryTvSelectedServiceIds.map(function(id) {
                var name = countryTvServiceIdToName[id] || ('ID ' + id);
                return '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-sky-100 text-sky-800 text-sm">' + name + ' <button type="button" class="vip-tv-adv-remove-tag hover:text-red-600" data-id="' + id + '" aria-label="Снять">×</button></span>';
            }).join('');
            wrap.querySelectorAll('.vip-tv-adv-remove-tag').forEach(function(b) {
                b.addEventListener('click', function() {
                    var id = parseInt(this.dataset.id, 10);
                    countryTvSelectedServiceIds = countryTvSelectedServiceIds.filter(function(x) { return x !== id; });
                    var cb = document.querySelector('.vip-tv-adv-service-cb[data-id="' + id + '"]');
                    if (cb) cb.checked = false;
                    renderCountrySelectedFilterTags();
                });
            });
        }
        function mapCountryGroupToCategory(name) {
            var n = (name || '').toLowerCase();
            if (/пляж|расположен|линия|берег|побереж/i.test(n)) return 'beach';
            if (/удобств|номер|комнат|ванн|wi-fi|кондицион|телевизор|балкон|кухн/i.test(n)) return 'room';
            if (/дет|ребен|клуб|анимац/i.test(n)) return 'children';
            if (/отель|территор|бассейн|спорт|ресторан|бар|услуг/i.test(n)) return 'hotel';
            return 'hotel';
        }
        function renderCountryAdvancedFilters(groups) {
            var beach = document.getElementById('vip-tv-adv-cat-beach');
            var hotel = document.getElementById('vip-tv-adv-cat-hotel');
            var room = document.getElementById('vip-tv-adv-cat-room');
            var children = document.getElementById('vip-tv-adv-cat-children');
            if (!beach || !hotel) return;
            [beach, hotel, room, children].forEach(function(el) { if (el) el.innerHTML = ''; });
            if (!Array.isArray(groups) || groups.length === 0) {
                var msg = document.createElement('p');
                msg.className = 'text-slate-500 text-sm';
                msg.textContent = 'Нет данных по услугам для этой страны. Данные подставляются из API.';
                if (beach) beach.appendChild(msg);
                return;
            }
            groups.forEach(function(gr) {
                var cat = mapCountryGroupToCategory(gr.name);
                var container = cat === 'beach' ? beach : (cat === 'room' ? room : (cat === 'children' ? children : hotel));
                var items = gr.items || [];
                items.forEach(function(item) {
                    var id = parseInt(item.id, 10);
                    var name = (item.name || item.russianName || '').toString();
                    if (!name || !container) return;
                    countryTvServiceIdToName[id] = name;
                    var label = document.createElement('label');
                    label.className = 'inline-flex items-center gap-2 cursor-pointer text-slate-700';
                    var cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.className = 'vip-tv-adv-service-cb rounded border-slate-300 text-[#5DA9A4] focus:ring-[#5DA9A4]';
                    cb.dataset.id = id;
                    if (countryTvSelectedServiceIds.indexOf(id) >= 0) cb.checked = true;
                    cb.addEventListener('change', function() {
                        if (this.checked) {
                            if (countryTvSelectedServiceIds.indexOf(id) < 0) countryTvSelectedServiceIds.push(id);
                        } else {
                            countryTvSelectedServiceIds = countryTvSelectedServiceIds.filter(function(x) { return x !== id; });
                        }
                        renderCountrySelectedFilterTags();
                    });
                    label.appendChild(cb);
                    label.appendChild(document.createTextNode(name));
                    container.appendChild(label);
                });
            });
            renderCountrySelectedFilterTags();
        }
        async function loadCountryAdvancedFilters(countryId) {
            if (!countryId) return;
            var beach = document.getElementById('vip-tv-adv-cat-beach');
            if (beach) beach.innerHTML = '<p class="text-slate-500 text-sm">Загрузка из API…</p>';
            if (window.__vip_tv_hotelServicesCache[countryId]) {
                [document.getElementById('vip-tv-adv-cat-beach'), document.getElementById('vip-tv-adv-cat-hotel'), document.getElementById('vip-tv-adv-cat-room'), document.getElementById('vip-tv-adv-cat-children')].forEach(function(el) { if (el) el.innerHTML = ''; });
                renderCountryAdvancedFilters(window.__vip_tv_hotelServicesCache[countryId]);
                console.log('%c[API → сайт] Расширенные фильтры: данные из кэша (ранее с API)', 'color: #22c55e', 'countryId', countryId);
                return;
            }
            var r = await tvFetch('hotel-services', { countryId: countryId });
            [document.getElementById('vip-tv-adv-cat-beach'), document.getElementById('vip-tv-adv-cat-hotel'), document.getElementById('vip-tv-adv-cat-room'), document.getElementById('vip-tv-adv-cat-children')].forEach(function(el) { if (el) el.innerHTML = ''; });
            if (r.success && Array.isArray(r.data) && r.data.length > 0) {
                window.__vip_tv_hotelServicesCache[countryId] = r.data;
                renderCountryAdvancedFilters(r.data);
                var totalItems = r.data.reduce(function(sum, gr) { return sum + (gr.items && gr.items.length ? gr.items.length : 0); }, 0);
                console.log('%c[API → сайт] Данные с API применены: расширенные фильтры', 'color: #22c55e', r.data.length, 'категорий,', totalItems, 'услуг');
            } else {
                renderCountryAdvancedFilters([]);
                console.warn('[API → сайт] Расширенные фильтры: API не вернул данные');
            }
        }
        loadCountryAdvancedFilters(VIP_COUNTRY_ID);

        console.log('%c[API → сайт] Итог (VIP отели): вылет, питание из API; курорты статичны; расширенные фильтры по Турции.', 'color: #5DA9A4; font-weight: bold');

        // Курорты статичны в HTML (Любой, Анталья 20, Белек 21, Кемер 22) — не перезаписываем

        var countryTvNightsPopup = document.getElementById('vip-tv-nights-popup');
        var countryTvNightsGrid = document.getElementById('vip-tv-nights-grid');
        var vipTvNightsQuick = document.getElementById('vip-tv-nights-quick');
        var countryTvNightsSelectFrom = true;
        function closeVipTvNightsPopup() {
            if (countryTvNightsPopup) {
                countryTvNightsPopup.classList.add('hidden');
                countryTvNightsPopup.style.display = 'none';
                countryTvNightsPopup.setAttribute('aria-hidden', 'true');
            }
        }
        function updateCountryTvNightsSummary() {
            var el = document.getElementById('vip-tv-nights-summary-text');
            if (el) el.textContent = countryTvNightsFrom === countryTvNightsTo ? ('' + countryTvNightsFrom) : (countryTvNightsFrom + ' — ' + countryTvNightsTo);
        }
        function renderCountryTvNightsGrid() {
            if (!countryTvNightsGrid) return;
            countryTvNightsGrid.querySelectorAll('.tv-nights-cell').forEach(function(btn) {
                var n = parseInt(btn.getAttribute('data-n'), 10);
                var lbl = btn.querySelector('.cell-label');
                btn.style.backgroundColor = '';
                btn.style.color = '';
                btn.style.border = '';
                btn.classList.remove('text-white');
                btn.classList.add('bg-slate-100', 'text-slate-700');
                if (lbl) lbl.classList.add('opacity-0');
                if (n === countryTvNightsFrom) {
                    btn.classList.remove('bg-slate-100', 'text-slate-700');
                    btn.style.backgroundColor = '#5DA9A4';
                    btn.style.color = '#fff';
                    btn.style.border = '2px solid #2a8ad4';
                    btn.classList.add('text-white');
                    if (lbl) lbl.classList.remove('opacity-0');
                } else if (n === countryTvNightsTo && countryTvNightsTo !== countryTvNightsFrom) {
                    btn.classList.remove('bg-slate-100', 'text-slate-700');
                    btn.style.backgroundColor = '#1e6bb8';
                    btn.style.color = '#fff';
                    btn.style.border = '2px solid #185a9e';
                    btn.classList.add('text-white');
                    if (lbl) lbl.classList.remove('opacity-0');
                } else if (n > countryTvNightsFrom && n < countryTvNightsTo) {
                    btn.classList.remove('bg-slate-100', 'text-slate-700');
                    btn.style.backgroundColor = 'rgba(121, 188, 183, 0.5)';
                    btn.style.color = '#0c4a6e';
                    btn.classList.add('text-sky-800');
                    if (lbl) lbl.classList.remove('opacity-0');
                }
            });
        }
        if (vipTvNightsQuick && window.THDatePresets && typeof window.THDatePresets.renderNightsChips === 'function') {
            window.THDatePresets.renderNightsChips(vipTvNightsQuick, function (from, to) {
                countryTvNightsFrom = from;
                countryTvNightsTo = to;
                countryTvNightsSelectFrom = true;
                renderCountryTvNightsGrid();
                updateCountryTvNightsSummary();
                closeVipTvNightsPopup();
            });
        }
        document.getElementById('vip-tv-nights-trigger').addEventListener('click', function() {
            countryTvNightsSelectFrom = true;
            renderCountryTvNightsGrid();
            if (countryTvNightsPopup) { countryTvNightsPopup.classList.remove('hidden'); countryTvNightsPopup.style.display = 'flex'; countryTvNightsPopup.setAttribute('aria-hidden', 'false'); }
        });
        countryTvNightsGrid && countryTvNightsGrid.addEventListener('click', function(e) {
            var btn = e.target.closest('.tv-nights-cell');
            if (!btn) return;
            var n = parseInt(btn.getAttribute('data-n'), 10);
            if (n >= 1 && n <= 28) {
                if (countryTvNightsSelectFrom) {
                    countryTvNightsFrom = n;
                    if (countryTvNightsTo < countryTvNightsFrom) countryTvNightsTo = countryTvNightsFrom;
                } else {
                    countryTvNightsTo = n;
                    if (countryTvNightsFrom > countryTvNightsTo) countryTvNightsFrom = countryTvNightsTo;
                }
                countryTvNightsSelectFrom = !countryTvNightsSelectFrom;
                renderCountryTvNightsGrid();
                updateCountryTvNightsSummary();
            }
        });
        document.getElementById('vip-tv-nights-apply').addEventListener('click', function() {
            if (countryTvNightsFrom > 28) countryTvNightsFrom = 28;
            if (countryTvNightsTo > 28) countryTvNightsTo = 28;
            if (countryTvNightsTo < countryTvNightsFrom) countryTvNightsTo = countryTvNightsFrom;
            updateCountryTvNightsSummary();
            closeVipTvNightsPopup();
        });
        countryTvNightsPopup && countryTvNightsPopup.addEventListener('click', function(e) {
            if (e.target === countryTvNightsPopup) document.getElementById('vip-tv-nights-apply').click();
        });
        updateCountryTvNightsSummary();

        // Кнопка поиска
        document.getElementById('vip-tv-search-btn').addEventListener('click', () => performCountryTvSearch());
        document.getElementById('vip-tv-sort').addEventListener('change', applyCountryTvSort);
        document.getElementById('vip-tv-load-more-btn').addEventListener('click', () => loadMoreCountryTvResults());

        // Страница отеля: автопоиск туров (hotel-first hub)
        if (HOTEL_FILTER_NAME || HOTEL_TV_IDS) {
            setTimeout(function() { performCountryTvSearch(); }, 400);
        }
    });

    function updateCountryTvLoadMoreButton() {
        const wrapper = document.getElementById('vip-tv-load-more-wrapper');
        const btn = document.getElementById('vip-tv-load-more-btn');
        const textEl = document.getElementById('vip-tv-load-more-text');
        if (!wrapper || !btn) return;
        const hasResults = countryTvLastResults.length > 0;
        const canLoadMore = countryTvDisplayedCount < countryTvLastResults.length;
        if (hasResults) {
            wrapper.classList.remove('hidden');
            btn.disabled = !canLoadMore;
            if (textEl) textEl.textContent = canLoadMore ? 'Загрузить ещё туры' : 'Загрузить ещё туры';
        } else {
            wrapper.classList.add('hidden');
            if (textEl) textEl.textContent = 'Загрузить ещё туры';
        }
    }

    function loadMoreCountryTvResults() {
        if (countryTvDisplayedCount >= countryTvLastResults.length) return;
        countryTvDisplayedCount = Math.min(countryTvDisplayedCount + COUNTRY_TV_PAGE_SIZE, countryTvLastResults.length);
        applyCountryTvSort();
        updateCountryTvLoadMoreButton();
    }

    async function performCountryTvSearch() {
        const wrapper = document.getElementById('vip-tv-results-wrapper');
        const progress = document.getElementById('vip-tv-search-progress');
        const resultsDiv = document.getElementById('vip-tv-search-results');
        if (!wrapper || !resultsDiv) return;

        // Поиск только по countryId из маппинга (slug → Tourvisor id): совпадает с all_tours, не зависит от API стран
        const effectiveCountryId = VIP_COUNTRY_ID;
        if (!effectiveCountryId) {
            wrapper.classList.remove('hidden');
            progress.classList.add('hidden');
            resultsDiv.innerHTML = '<div class="tv-results-empty"><p class="text-slate-600">Страна не определена. Обновите страницу.</p></div>';
            wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
            console.warn('[VIP · Поиск] countryId не задан');
            return;
        }

        console.log('%c[VIP · Поиск] Нажата кнопка «Найти»', 'color: #5DA9A4; font-weight: bold', 'countryId:', effectiveCountryId, 'Турция');
        wrapper.classList.remove('hidden');
        progress.classList.remove('hidden');
        resultsDiv.innerHTML = '';
        countryTvDisplayedCount = 0;

        const dep = document.getElementById('vip-tv-departure').value || (window.TH_DEPARTURE && window.TH_DEPARTURE.id) || '12';
        let nFrom = typeof countryTvNightsFrom !== 'undefined' ? countryTvNightsFrom : 7;
        let nTo = typeof countryTvNightsTo !== 'undefined' ? countryTvNightsTo : 14;
        if (nTo < nFrom) nTo = nFrom;
        const adults = typeof countryTvAdultsCount !== 'undefined' ? countryTvAdultsCount : 2;
        let childs = '';
        if (typeof countryTvChildrenAges !== 'undefined' && countryTvChildrenAges.length > 0) {
            childs = countryTvChildrenAges.slice(0, 3).join(',');
        }
        
        const datesVal = (document.getElementById('vip-tv-dates').value || '').trim();
        let dateFrom, dateTo;
        if (datesVal && countryTvDatePicker && countryTvDatePicker.selectedDates && countryTvDatePicker.selectedDates.length >= 1) {
            const sel = countryTvDatePicker.selectedDates;
            dateFrom = flatpickr.formatDate(sel[0], 'Y-m-d');
            dateTo = sel.length >= 2 ? flatpickr.formatDate(sel[1], 'Y-m-d') : flatpickr.formatDate(new Date(sel[0].getTime() + 30*864e5), 'Y-m-d');
        }
        if (!dateFrom || !dateTo) {
            wrapper.classList.remove('hidden');
            progress.classList.add('hidden');
            resultsDiv.innerHTML = '<div class="tv-results-empty"><p class="text-slate-600">Выберите даты вылета и возвращения в календаре.</p></div>';
            return;
        }
        
        const params = new URLSearchParams({
            type: 'search',
            departureId: dep,
            countryId: String(effectiveCountryId),
            dateFrom, dateTo,
            nightsFrom: nFrom || 7,
            nightsTo: nTo || 14,
            adults,
            currency: 'RUB'
        });
        if (childs) params.set('childs', childs);
        const mealEl = document.getElementById('vip-tv-meal');
        const meal = mealEl ? mealEl.value : '';
        if (meal) params.set('meal', meal);
        const categoryEl = document.getElementById('vip-tv-category');
        let category = categoryEl ? categoryEl.value : '';
        // При точном hotelIds не форсируем 5★ — иначе режем выдачу
        if (HOTEL_FILTER_NAME && !category && !HOTEL_TV_IDS) category = '5';
        if (category) params.set('hotelCategory', category);
        const regionEl = document.getElementById('vip-tv-region');
        let region = regionEl ? regionEl.value : '';
        if (!region && HOTEL_REGION_ID && !HOTEL_TV_IDS) region = HOTEL_REGION_ID;
        if (region) params.set('regionIds', region);
        if (typeof countryTvSelectedServiceIds !== 'undefined' && countryTvSelectedServiceIds.length > 0) {
            params.set('hotelServices', countryTvSelectedServiceIds.join(','));
        }

        // Проверка кэша
        const cacheParams = {
            departureId: dep,
            countryId: effectiveCountryId,
            countryName: 'country-' + effectiveCountryId,
            dateFrom, dateTo,
            nightsFrom: nFrom || 7,
            nightsTo: nTo || 14,
            adults
        };
        if (childs) cacheParams.childs = childs;
        if (meal) cacheParams.meal = meal;
        if (category) cacheParams.hotelCategory = category;
        if (region) cacheParams.regionIds = region;
        if (!region && HOTEL_REGION_ID && !HOTEL_TV_IDS) cacheParams.regionIds = HOTEL_REGION_ID;
        if (HOTEL_TV_IDS) cacheParams.hotelIds = HOTEL_TV_IDS;
        if (typeof countryTvSelectedServiceIds !== 'undefined' && countryTvSelectedServiceIds.length > 0) {
            cacheParams.hotelServices = countryTvSelectedServiceIds.join(',');
        }
        console.log('%c[API → сайт] Параметры поиска отправляются в API (VIP отели)', 'color: #5DA9A4; font-weight: bold', cacheParams);
        let rCache = await tvFetch('search-cached', Object.assign({}, cacheParams, { _cacheOnly: true }));
        if (!rCache.success || !Array.isArray(rCache.data) || rCache.data.length === 0) {
            rCache = await tvFetch('search-cached', Object.assign({}, cacheParams, { _forceLive: true }));
        }
        if (rCache.success && Array.isArray(rCache.data) && rCache.data.length > 0) {
            progress.classList.add('hidden');
            let rawData = rCache.data;
            if (HOTEL_TV_IDS) {
                const want = HOTEL_TV_IDS.split(',').map(function(x) { return parseInt(x, 10); }).filter(Boolean);
                rawData = rawData.filter(function(h) { return want.indexOf(parseInt(h.id, 10)) >= 0; });
            } else if (HOTEL_FILTER_NAME) {
                rawData = rawData.filter(function(h) { return hotelNameMatches(HOTEL_FILTER_NAME, h.name); });
            }
            if ((HOTEL_TV_IDS || HOTEL_FILTER_NAME) && rawData.length === 1 && rawData[0].tours && rawData[0].tours.length > 0) {
                var h = rawData[0];
                countryTvLastResults = h.tours.map(function(t) { return { _hotel: h, _tour: t }; });
            } else if (HOTEL_TV_IDS || HOTEL_FILTER_NAME) {
                countryTvLastResults = rawData;
            } else {
                countryTvLastResults = rawData;
            }
            countryTvDisplayedCount = Math.min(COUNTRY_TV_PAGE_SIZE, countryTvLastResults.length);
            document.getElementById('vip-tv-result-count').textContent = countryTvLastResults.length;
            applyCountryTvSort();
            updateCountryTvLoadMoreButton();
            document.getElementById('vip-tv-results-wrapper').scrollIntoView({ behavior: 'smooth', block: 'start' });
            console.log('%c[API → сайт] Результаты с API получены и отображены на сайте', 'color: #22c55e; font-weight: bold', { туров: countryTvLastResults.length, изКэша: rCache.fromCache === true });
            return;
        }

        // Ответ без данных (ошибка или пустой результат после живого поиска)
        progress.classList.add('hidden');
        const errMsg = (rCache.error && rCache.error !== 'Cache miss') ? rCache.error : 'Для этих параметров ничего не найдено. Попробуйте другие даты или курорт.';
        resultsDiv.innerHTML = '<div class="tv-results-empty"><p class="text-slate-600 mb-2">' + (errMsg.replace(/</g, '&lt;')) + '</p></div>';
        return;
    }

    function thPickFirstPositivePriceNumVip() {
        const args = Array.from(arguments);
        for (let i = 0; i < args.length; i++) {
            const v = args[i];
            if (v == null || v === '') continue;
            const n = Number(v);
            if (!Number.isNaN(n) && n > 0) return n;
        }
        return 0;
    }
    function vipGridHotelPrice(h) {
        if (!h) return 0;
        const tour = (h.tours || [])[0] || {};
        if (h.tours && h.tours[0]) {
            let n = thPickFirstPositivePriceNumVip(
                tour.totalPrice,
                tour.price,
                tour.priceRub,
                tour.cost
            );
            if (n > 0) return Math.round(n);
            n = thPickFirstPositivePriceNumVip(h.price);
            return n > 0 ? Math.round(n) : 0;
        }
        return Math.round(thPickFirstPositivePriceNumVip(h.price, h.minPrice, h.minprice));
    }
    function vipSingleTourListPrice(t) {
        if (!t) return 0;
        let n = thPickFirstPositivePriceNumVip(t.totalPrice, t.price, t.priceRub, t.cost);
        if (n > 0) return Math.round(n);
        n = Number(t.price);
        return (!Number.isNaN(n) && n > 0) ? Math.round(n) : 0;
    }
    function getVipTourId(h) {
        if (h && h._tour && h._tour.id != null && h._tour.id !== '') return String(h._tour.id);
        const tour = (h && h.tours && h.tours[0]) ? h.tours[0] : {};
        return (tour.id != null && tour.id !== '') ? String(tour.id) : '';
    }
    function loadVipFlightsForTours(hotels, callback) {
        const base = typeof TV_API_BASE !== 'undefined' ? TV_API_BASE : '';
        const depCity = (window.TH_DEPARTURE && window.TH_DEPARTURE.name) || 'Самара';
        if (typeof thLoadTourFlightsForHotels === 'function' && base) {
            thLoadTourFlightsForHotels(hotels, {
                apiBase: base,
                departureCity: depCity,
                maxTours: hotels.length,
                maxConcurrent: 6,
                getTourId: getVipTourId,
                onDone: callback
            });
            return;
        }
        if (callback) callback();
    }
    function applyCountryTvSort() {
        const sortVal = document.getElementById('vip-tv-sort')?.value || 'price-asc';
        let arr = [...countryTvLastResults];
        const isHotelMode = arr.length > 0 && arr[0]._hotel && arr[0]._tour;
        if (isHotelMode) {
            if (sortVal === 'price-asc') arr.sort((a, b) => vipSingleTourListPrice(a._tour) - vipSingleTourListPrice(b._tour));
            else if (sortVal === 'price-desc') arr.sort((a, b) => vipSingleTourListPrice(b._tour) - vipSingleTourListPrice(a._tour));
            else if (sortVal === 'rating') arr.sort((a, b) => ((b._hotel && b._hotel.rating) || 0) - ((a._hotel && a._hotel.rating) || 0));
        } else {
            if (sortVal === 'price-asc') arr.sort((a, b) => vipGridHotelPrice(a) - vipGridHotelPrice(b));
            else if (sortVal === 'price-desc') arr.sort((a, b) => vipGridHotelPrice(b) - vipGridHotelPrice(a));
            else if (sortVal === 'rating') arr.sort((a, b) => (b.rating || 0) - (a.rating || 0));
        }
        renderCountryTvResults(arr.slice(0, countryTvDisplayedCount));
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

    function vipTourPhotoUrlsForCard(h) {
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
            if (!t || seen[t]) return;
            seen[t] = true;
            urls.push(t);
        };
        if (!h) return [fallback];
        raw.forEach(add);
        const max = 4;
        let i = 0;
        while (urls.length > 0 && urls.length < max && i < max) {
            urls.push(urls[urls.length - 1]);
            i++;
        }
        if (urls.length === 0) urls.push(fallback);
        return urls.slice(0, max);
    }

    function vipCardPrimaryImage(h) {
        const raw = vipTourPhotoUrlsForCard(h);
        for (let i = 0; i < raw.length; i++) {
            const u = getTourvisorImageUrl(raw[i]);
            if (u && u.indexOf('unsplash.com') === -1) return u;
        }
        return raw.length ? getTourvisorImageUrl(raw[0]) : getTourvisorImageUrl('');
    }

    function renderCountryTvResults(hotels) {
        const container = document.getElementById('vip-tv-search-results');
        if (!container) return;
        if (hotels.length === 0) {
            container.innerHTML = '<div class="tv-results-empty"><i class="fas fa-search text-5xl text-slate-300 mb-4"></i><p class="text-slate-600">Ничего не найдено</p></div>';
            return;
        }
        const depEl = document.getElementById('vip-tv-departure');
        const departureCity = (window.TH_DEPARTURE && window.TH_DEPARTURE.name) || 'Самара';
        const departureIdVip = (depEl && depEl.value) ? String(depEl.value).trim() : ((window.TH_DEPARTURE && window.TH_DEPARTURE.id) || '12');
        const tourDetailBase = '/frontend/window';

        // Режим страницы отеля: список туров (дата, ночи, питание, цена)
        const isHotelMode = hotels.length > 0 && hotels[0]._hotel && hotels[0]._tour;
        if (isHotelMode) {
            container.className = 'tv-vip-tour-rows';
            container.innerHTML = hotels.map(function(item) {
                var h = item._hotel;
                var t = item._tour;
                var link = h.hotelDescriptionLink || h.hoteldescriptionlink || h.link || '';
                if (window.TourLinkUtils && typeof TourLinkUtils.sanitizeTourLink === 'function') {
                    link = TourLinkUtils.sanitizeTourLink(link) || '';
                }
                var country = h.country?.name || '';
                var region = h.region?.name || '';
                var img = vipCardPrimaryImage(h);
                var meal = t.meal?.russianName || t.meal?.name || '';
                var desc = (h.description || h.hotelDescription || h.descr || '').toString().trim();
                var nN = parseInt(String(t.nights || ''), 10) || 0;
                var sY = tvTourStartYmd(t);
                var rY = (sY && nN) ? tvTourReturnYmd(sY, nN) : '';
                var roomCatHotel = (t.roomType || h.roomCategory || 'Стандарт').toString().trim() || 'Стандарт';
                var tourLinePrice = vipSingleTourListPrice(t);
                var paVip = Math.max(1, Math.min(9, typeof countryTvAdultsCount !== 'undefined' ? countryTvAdultsCount : 2));
                var tourDetailParams = {
                    tour_link: link,
                    country: country,
                    hotel_name: (h.name || ''),
                    price: formatPrice(tourLinePrice || t.price),
                    nights: String(t.nights || ''),
                    meal: meal,
                    room_category: roomCatHotel,
                    region: region,
                    departure_city: departureCity,
                    image: img,
                    description: desc ? desc.substring(0, 4000) : '',
                    rating: String(h.rating || ''),
                    category: String(h.category || ''),
                    date_from: sY || '',
                    date_to: rY || '',
                    tour_id: t.id || '',
                    adults: String(paVip)
                };
                if (h.id) tourDetailParams.hotel_id = String(h.id);
                if (departureIdVip) tourDetailParams.departure_id = departureIdVip;
                try {
                    tourDetailParams.return_url = (window.TourSessionManager && window.TourSessionManager.buildReturnUrl)
                        ? window.TourSessionManager.buildReturnUrl()
                        : (window.location.pathname + window.location.search);
                } catch (e) {}
                var tourDetailUrl = (tourDetailBase + '/tour-detail.php?' + new URLSearchParams(tourDetailParams).toString());
                return '<a href="' + tourDetailUrl + '" class="tv-vip-tour-row__link" target="_blank" rel="noopener"><div class="tv-vip-tour-row__left"><span class="tv-vip-tour-row__date">' + formatDateRu(t.date) + '</span><span><i class="far fa-moon tv-tour-card__meta-accent mr-1"></i>' + (t.nights || '') + ' н.</span>' + (meal ? '<span><i class="fas fa-utensils tv-tour-card__meta-accent mr-1"></i>' + meal + '</span>' : '') + (departureCity ? '<span class="text-slate-500">' + departureCity + '</span>' : '') + '</div><div class="tv-vip-tour-row__right"><span class="tv-vip-tour-row__price"><span class="text-xs text-slate-500 font-normal mr-1">за ' + paVip + ' взр.</span>' + formatPrice(tourLinePrice || t.price) + '</span><span class="tv-vip-tour-row__cta">Выбрать →</span></div></a>';
            }).join('');
            document.getElementById('vip-tv-result-count').textContent = countryTvLastResults.length;
            updateCountryTvLoadMoreButton();
            return;
        }

        container.className = 'tv-search-results-grid th-tour-grid';
        if (window.THTourCard && typeof window.THTourCard.render === 'function') {
            const priceAdultsVip = Math.max(1, Math.min(9, typeof countryTvAdultsCount !== 'undefined' ? countryTvAdultsCount : 2));
            container.innerHTML = hotels.map(h => {
                const tour = (h.tours || [])[0] || {};
                const region = h.region?.name || '';
                const country = h.country?.name || '';
                const meal = tour.meal?.russianName || tour.meal?.name || '';
                const nightsNum = parseInt(String(tour.nights || ''), 10) || 0;
                const startYmd = tvTourStartYmd(tour);
                const retYmd = (startYmd && nightsNum) ? tvTourReturnYmd(startYmd, nightsNum) : '';
                const price = vipGridHotelPrice(h);
                let link = h.hotelDescriptionLink || h.hoteldescriptionlink || h.link || '';
                if (typeof TourLinkUtils !== 'undefined' && TourLinkUtils.sanitizeTourLink) link = TourLinkUtils.sanitizeTourLink(link) || '';
                const cardImg = vipCardPrimaryImage(h);
                const params = {
                    tour_link: link, country, hotel_name: (h.name || ''), price: String(price),
                    nights: String(tour.nights || ''), meal, region, departure_city: departureCity,
                    adults: String(priceAdultsVip),
                    image: cardImg || ''
                };
                if (startYmd) params.date_from = startYmd;
                if (retYmd) params.date_to = retYmd;
                if (departureIdVip) params.departure_id = departureIdVip;
                if (h.id) params.hotel_id = String(h.id);
                const cardHref = country ? (tourDetailBase + '/tour-detail.php?' + new URLSearchParams(params).toString()) : (link || '#');
                return window.THTourCard.render(h, {
                    tour, getImageUrl: getTourvisorImageUrl, imageProxy: TV_IMAGE_PROXY,
                    image: cardImg, detailUrl: cardHref, target: '_blank',
                    adults: priceAdultsVip, dateFrom: startYmd, dateTo: retYmd, price,
                    departureCity, departureId: departureIdVip, carousel: true
                });
            }).join('');
            if (window.THTourCard && typeof window.THTourCard.ensureCarouselsInContainer === 'function') {
                window.THTourCard.ensureCarouselsInContainer(container);
            } else if (window.THTourCard && typeof window.THTourCard.kickImagesInContainer === 'function') {
                window.THTourCard.kickImagesInContainer(container);
            }
            document.getElementById('vip-tv-result-count').textContent = countryTvLastResults.length;
            updateCountryTvLoadMoreButton();
            return;
        }
        container.innerHTML = hotels.map(h => {
            const picRaw = h.picturelink ?? h.pictureLink ?? '';
            const photoUrls = vipTourPhotoUrlsForCard(h);
            const img = getTourvisorImageUrl(picRaw || photoUrls[0] || '');
            const region = h.region?.name || '';
            const country = h.country?.name || '';
            const tour = (h.tours || [])[0] || {};
            const meal = tour.meal?.russianName || tour.meal?.name || '';
            const nights = tour.nights || '';
            const nightsNum = parseInt(String(nights), 10) || 0;
            const startYmd = tvTourStartYmd(tour);
            const retYmd = (startYmd && nightsNum) ? tvTourReturnYmd(startYmd, nightsNum) : '';
            const price = vipGridHotelPrice(h);
            const rating = h.rating || 0;
            let link = h.hotelDescriptionLink || h.hoteldescriptionlink || h.link || '';
            if (typeof TourLinkUtils !== 'undefined' && TourLinkUtils.sanitizeTourLink) {
                link = TourLinkUtils.sanitizeTourLink(link) || '';
            }
            const hasCountry = country && country.length > 0;
            const desc = (h.description || h.hotelDescription || h.descr || '').toString().trim();
            const tidGrid = (tour.id != null && tour.id !== '') ? String(tour.id) : '';
            const roomCatGrid = (tour.roomType || h.roomCategory || 'Стандарт').toString().trim() || 'Стандарт';
            const tourDetailParams = {
                tour_link: link,
                country: country,
                hotel_name: (h.name || ''),
                price: formatPrice(price),
                nights: String(nights),
                meal: meal,
                room_category: roomCatGrid,
                region: region,
                departure_city: departureCity,
                image: img,
                description: desc ? desc.substring(0, 4000) : '',
                rating: String(h.rating || ''),
                category: String(h.category || ''),
            };
            if (startYmd) tourDetailParams.date_from = startYmd;
            if (retYmd) tourDetailParams.date_to = retYmd;
            if (tidGrid) tourDetailParams.tour_id = tidGrid;
            if (h.id) tourDetailParams.hotel_id = String(h.id);
            if (departureIdVip) tourDetailParams.departure_id = departureIdVip;
            const priceAdultsVip = Math.max(1, Math.min(9, typeof countryTvAdultsCount !== 'undefined' ? countryTvAdultsCount : 2));
            tourDetailParams.adults = String(priceAdultsVip);
            try {
                tourDetailParams.return_url = (window.TourSessionManager && window.TourSessionManager.buildReturnUrl)
                    ? window.TourSessionManager.buildReturnUrl()
                    : (window.location.pathname + window.location.search);
            } catch (e) {}
            const tourDetailUrl = hasCountry ? (tourDetailBase + '/tour-detail.php?' + new URLSearchParams(tourDetailParams).toString()) : (link || '#');
            const cardHref = tourDetailUrl !== '#' ? tourDetailUrl : (link || '#');
            const cardTarget = '_blank';
            return `
            <article class="tv-tour-card">
                <a href="${cardHref}" ${cardTarget === '_blank' ? 'target="_blank" rel="noopener"' : ''} class="tv-tour-card__link">
                    <div class="tv-tour-card__media">
                        <img src="${img.replace(/"/g, '&quot;')}" alt="" class="tv-tour-card__img" loading="eager" decoding="async" onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400'">
                    </div>
                    <div class="tv-tour-card__body">
                        <h3 class="tv-tour-card__title">${(h.name || '').replace(/</g, '&lt;')}</h3>
                        <p class="tv-tour-card__location">${(country + (region ? ', ' + region : '')).replace(/</g, '&lt;')}</p>
                        <div class="tv-tour-card__meta">
                            ${nights ? `<span><i class="far fa-moon tv-tour-card__meta-accent mr-1"></i>${nights} н.</span>` : ''}
                            ${meal ? `<span><i class="fas fa-utensils tv-tour-card__meta-accent mr-1"></i>${meal}</span>` : ''}
                            ${rating ? `<span class="tv-tour-card__meta-rating">★ ${rating}</span>` : ''}
                        </div>
                        <div class="tv-tour-card__bottom">
                            <div>
                                <span class="tv-tour-card__price-label">за ${priceAdultsVip} взрослых </span>
                                <span class="tv-tour-card__price">${formatPrice(price)}</span>
                            </div>
                            <span class="tv-tour-card__cta">Просмотреть параметры тура и забронировать →</span>
                        </div>
                    </div>
                </a>
            </article>`;
        }).join('');
        document.getElementById('vip-tv-result-count').textContent = countryTvLastResults.length;
        updateCountryTvLoadMoreButton();
    }
})();
</script>
