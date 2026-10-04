<?php
/**
 * Компонент поиска туров для страниц стран
 * Автоматически определяет страну по slug и предзаполняет форму
 */

// Единый конфиг сопоставления страны (slug/name/резервный id) для стабильной фильтрации.
$countryMatchConfig = require __DIR__ . '/country_match_config.php';
$countryNameMap = $countryMatchConfig['names'] ?? [];
$countryAliasesMap = $countryMatchConfig['aliases'] ?? [];

// Определяем slug страны
if (!isset($countrySlug) || empty($countrySlug)) {
    // Пытаемся получить из $countryData, если он определен
    if (isset($countryData) && is_array($countryData) && isset($countryData['slug'])) {
        $countrySlug = $countryData['slug'];
    } else {
        // Пытаемся определить из имени файла
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if (preg_match('#/countries/([^/]+)\.php$#', $scriptName, $matches)) {
            $countrySlug = $matches[1];
        } else {
            $countrySlug = '';
        }
    }
}
$countryName = $countryNameMap[$countrySlug] ?? null;

// Запасной countryId для TourVisor, если API стран не ответил (по slug страницы)
$countryIdFallbackMap = $countryMatchConfig['fallback_ids'] ?? [];
$countryIdFallback = isset($countrySlug) ? ($countryIdFallbackMap[$countrySlug] ?? 12) : 12;

// Единый путь к API (прокси + кэш): один хелпер для всех поисковиков
require_once __DIR__ . '/tourvisor_proxy_url.php';
require_once __DIR__ . '/../config/departure_defaults.php';
$apiBase = get_tourvisor_proxy_base_url();
$imageProxyBase = get_tourvisor_image_proxy_base_url();
$countryTvDepartureId = th_departure_default_id();
?>

<!-- Поиск туров для страны (облегчённый путь: страна уже выбрана) -->
<section id="country-tour-search-section" class="py-14 md:py-16 bg-[#f7f9fb]">
    <div class="th-container mx-auto px-6">
        <div class="text-center mb-10">
            <span class="pill-badge mb-4">Шаг за шагом</span>
                <h2 class="heading-font text-3xl sm:text-4xl font-bold text-slate-900 mb-3">Подберём тур <?php echo $countryName ? 'в ' . htmlspecialchars($countryName, ENT_QUOTES, 'UTF-8') : ''; ?></h2>
                <p class="text-slate-600 max-w-2xl mx-auto text-lg">Страна уже выбрана — укажите даты, ночи и кто едет</p>
        </div>
        
        <!-- Форма поиска -->
        <div class="max-w-3xl mx-auto">
            <div class="th-wizard th-wizard--country surface-card p-4 sm:p-6 md:p-8">
                <nav class="th-wizard__progress" aria-label="Шаги поиска по стране" style="pointer-events:none">
                    <div class="th-wizard__dot is-done">
                        <span class="th-wizard__dot-num"><i class="fas fa-check text-xs"></i></span>
                        <span class="th-wizard__dot-label">Страна</span>
                    </div>
                    <div class="th-wizard__dot is-active">
                        <span class="th-wizard__dot-num">2</span>
                        <span class="th-wizard__dot-label">Когда</span>
                    </div>
                    <div class="th-wizard__dot">
                        <span class="th-wizard__dot-num">3</span>
                        <span class="th-wizard__dot-label">Кто</span>
                    </div>
                </nav>
                <!-- Основные поля -->
                <input type="hidden" id="country-tv-departure" name="departureId" value="<?php echo (int) $countryTvDepartureId; ?>">
                <div class="flex flex-col gap-4 mb-4">
                    <div class="tv-field w-full">
                        <label class="block text-xs font-medium text-slate-500 mb-1">Даты</label>
                        <div class="relative" id="country-tv-dates-wrap">
                            <input type="text" id="country-tv-dates" class="w-full px-3 py-2.5 pr-9 rounded-lg border border-slate-200 text-slate-800 text-sm focus:ring-2 focus:ring-[#5DA9A4]/30 focus:border-[#5DA9A4] cursor-pointer bg-white" placeholder="Выберите период" data-input readonly autocomplete="off">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"><i class="fas fa-calendar-alt text-sm"></i></span>
                        </div>
                        <div id="country-tv-date-presets" class="tv-date-presets mt-2"></div>
                    </div>
                    <div class="tv-field w-full" id="country-tv-nights-trigger">
                        <label class="block text-xs font-medium text-slate-500 mb-1">Ночей</label>
                        <button type="button" class="w-full px-3 py-3 rounded-xl border border-slate-200 text-slate-800 text-sm text-left bg-white hover:bg-slate-50 flex items-center justify-between transition-colors" id="country-tv-nights-summary">
                            <span id="country-tv-nights-summary-text">6 — 9</span>
                            <i class="fas fa-chevron-down text-slate-400 text-xs"></i>
                        </button>
                    </div>
                    <div id="country-tv-nights-popup" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" aria-hidden="true" style="display:none">
                        <div class="tv-nights-modal-card bg-white rounded-2xl shadow-2xl w-full min-w-0 p-5 border border-sky-100" role="dialog" aria-label="Сколько ночей в отеле">
                            <h3 class="font-bold text-slate-900 text-base mb-1">Сколько ночей?</h3>
                            <p class="text-xs text-slate-500 mb-3">Один тап — или свой диапазон ниже</p>
                            <div id="country-tv-nights-quick" class="tv-nights-quick mb-3"></div>
                            <p class="text-xs text-slate-500 mb-2">Свой диапазон: сначала «от», потом «до»</p>
                            <div id="country-tv-nights-grid" class="grid grid-cols-4 sm:grid-cols-7 gap-1.5 mb-3">
                                <?php for ($n = 1; $n <= 28; $n++): ?>
                                <button type="button" class="country-tv-nights-cell tv-nights-cell min-w-[2.25rem] min-h-[2.75rem] px-0 py-1 rounded-lg text-sm font-semibold transition-colors flex flex-col items-center justify-center gap-0 leading-none bg-slate-100 text-slate-700 hover:bg-sky-100 hover:text-sky-700" data-n="<?php echo $n; ?>">
                                    <span class="cell-num"><?php echo $n; ?></span>
                                    <span class="cell-label text-[10px] opacity-0 leading-tight"><?php
                                        if ($n === 1) echo 'ночь';
                                        elseif ($n >= 2 && $n <= 4) echo 'ночи';
                                        else echo 'ночей';
                                    ?></span>
                                </button>
                                <?php endfor; ?>
                            </div>
                            <button type="button" id="country-tv-nights-apply" class="w-full py-3.5 min-h-[52px] rounded-xl bg-gradient-to-r from-[#5DA9A4] to-[#8CC7C3] text-white text-base font-bold shadow-md shadow-[#5DA9A4]/25 hover:shadow-lg transition-all">Готово</button>
                        </div>
                    </div>
                    <div class="tv-field w-full" id="country-tv-tourists-trigger">
                        <label class="block text-xs font-medium text-slate-500 mb-1">Туристы</label>
                        <button type="button" class="w-full px-3 py-3 rounded-xl border border-slate-200 text-slate-800 text-sm text-left bg-white hover:bg-slate-50 flex items-center justify-between transition-colors" id="country-tv-tourists-summary">
                            <span id="country-tv-tourists-summary-text">2 взр., 0 дет.</span>
                            <i class="fas fa-chevron-down text-slate-400 text-xs"></i>
                        </button>
                    </div>
                </div>
                <div id="country-tv-tourists-block" class="hidden mt-4 p-4 rounded-xl bg-slate-50 border border-slate-100">
                    <p class="text-sm font-semibold text-slate-800 uppercase tracking-wide mb-4">Туристы</p>
                    <div class="flex items-center gap-2 mb-4">
                        <button type="button" id="country-tv-adults-minus" class="w-9 h-9 rounded-full bg-[#5DA9A4] text-white flex items-center justify-center hover:bg-[#457F7B] flex-shrink-0 transition-colors" aria-label="Меньше"><i class="fas fa-minus text-xs"></i></button>
                        <div class="flex-1 min-w-0 px-4 py-2.5 rounded-full bg-[#5DA9A4]/15 text-slate-800 text-sm font-medium text-center"><span id="country-tv-adults-value">2 взрослых</span></div>
                        <button type="button" id="country-tv-adults-plus" class="w-9 h-9 rounded-full bg-[#5DA9A4] text-white flex items-center justify-center hover:bg-[#457F7B] flex-shrink-0 transition-colors" aria-label="Больше"><i class="fas fa-plus text-xs"></i></button>
                    </div>
                    <div id="country-tv-children-rows" class="space-y-2 mb-3"></div>
                    <button type="button" id="country-tv-add-child-btn" class="w-full py-2.5 rounded-full border border-slate-200 bg-white text-slate-700 text-sm font-medium hover:bg-slate-50 mb-4 transition-colors">Добавить ребенка</button>
                    <div id="country-tv-child-age-picker" class="hidden mb-4 p-3 rounded-xl bg-white border border-slate-200 shadow-lg">
                        <p class="text-xs text-slate-500 mb-2">Выберите возраст ребенка</p>
                        <div id="country-tv-child-age-grid" class="flex flex-wrap gap-2"></div>
                    </div>
                    <label class="flex items-center gap-2 mb-4 cursor-pointer text-sm text-slate-700">
                        <input type="checkbox" id="country-tv-remember-tourists" class="rounded border-slate-300 text-[#5DA9A4] focus:ring-[#5DA9A4]"><span>Запомнить выбор</span>
                    </label>
                    <button type="button" id="country-tv-tourists-apply" class="w-full py-3 rounded-xl bg-[#FF6B6B] hover:bg-[#f65252] text-white text-sm font-semibold shadow-md shadow-[#FF6B6B]/25 transition-all">Выбрать</button>
                </div>
                <!-- Доп. параметры (свёрнуты) + Кнопка поиска -->
                <div class="flex flex-col gap-3 pt-3 border-t border-slate-100">
                    <details id="country-tv-filters-details" class="tv-filters-details group">
                        <summary class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 text-sm font-medium cursor-pointer hover:bg-slate-50 transition-colors list-none [&::-webkit-details-marker]:hidden">
                            <i class="fas fa-sliders-h text-[#5DA9A4]"></i>
                            <span>Доп. параметры</span>
                            <i class="tv-filters-chevron fas fa-chevron-down text-xs text-slate-400 transition-transform"></i>
                        </summary>
                        <div class="mt-4 p-4 rounded-xl bg-slate-50 border border-slate-100">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1.5">Питание</label>
                                    <select id="country-tv-meal" class="tv-select w-full px-3 py-2 rounded-lg border border-sky-200 text-slate-700 text-sm bg-white focus:ring-2 focus:ring-[#5DA9A4]/30 focus:border-[#5DA9A4]">
                                        <option value="">Любое</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1.5">Курорт</label>
                                    <select id="country-tv-region" class="tv-select w-full px-3 py-2 rounded-lg border border-sky-200 text-slate-700 text-sm bg-white focus:ring-2 focus:ring-[#5DA9A4]/30 focus:border-[#5DA9A4]">
                                        <option value="">Любой</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1.5">Категория отеля</label>
                                    <select id="country-tv-category" class="tv-select w-full px-3 py-2 rounded-lg border border-sky-200 text-slate-700 text-sm bg-white focus:ring-2 focus:ring-[#5DA9A4]/30 focus:border-[#5DA9A4]">
                                        <option value="">Любая</option>
                                        <option value="3">3★ и выше</option>
                                        <option value="4">4★ и выше</option>
                                        <option value="5">5★</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-4 pt-4 border-t border-sky-200">
                                <p class="text-sm font-semibold text-slate-800 uppercase tracking-wide mb-3">Расширенные фильтры</p>
                                <div class="flex items-center gap-2 mb-3"><span class="text-xs font-medium text-sky-600 border-b-2 border-[#5DA9A4] pb-1">ВСЕ</span></div>
                                <div id="country-tv-adv-selected-tags" class="flex flex-wrap gap-2 mb-3 min-h-[2rem]"></div>
                                <div id="country-tv-adv-categories" class="space-y-2">
                                    <details class="border border-sky-200 rounded-lg overflow-hidden" open><summary class="px-3 py-2.5 bg-sky-50 text-slate-800 text-sm font-medium cursor-pointer list-none flex items-center justify-between"><span>Пляж и расположение</span><i class="fas fa-chevron-up text-sky-400 text-xs"></i></summary><div id="country-tv-adv-cat-beach" class="px-3 py-2 border-t border-sky-100 flex flex-wrap gap-3 text-sm"></div></details>
                                    <details class="border border-sky-200 rounded-lg overflow-hidden" open><summary class="px-3 py-2.5 bg-sky-50 text-slate-800 text-sm font-medium cursor-pointer list-none flex items-center justify-between"><span>В отеле</span><i class="fas fa-chevron-up text-sky-400 text-xs"></i></summary><div id="country-tv-adv-cat-hotel" class="px-3 py-2 border-t border-sky-100 flex flex-wrap gap-3 text-sm"></div></details>
                                    <details class="border border-sky-200 rounded-lg overflow-hidden" open><summary class="px-3 py-2.5 bg-sky-50 text-slate-800 text-sm font-medium cursor-pointer list-none flex items-center justify-between"><span>В номере</span><i class="fas fa-chevron-up text-sky-400 text-xs"></i></summary><div id="country-tv-adv-cat-room" class="px-3 py-2 border-t border-sky-100 flex flex-wrap gap-3 text-sm"></div></details>
                                    <details class="border border-sky-200 rounded-lg overflow-hidden" open><summary class="px-3 py-2.5 bg-sky-50 text-slate-800 text-sm font-medium cursor-pointer list-none flex items-center justify-between"><span>Детям</span><i class="fas fa-chevron-up text-sky-400 text-xs"></i></summary><div id="country-tv-adv-cat-children" class="px-3 py-2 border-t border-sky-100 flex flex-wrap gap-3 text-sm"></div></details>
                                </div>
                                <button type="button" id="country-tv-adv-apply" class="mt-4 px-6 py-2.5 rounded-xl bg-gradient-to-r from-[#5DA9A4] to-[#8CC7C3] text-white text-sm font-medium shadow-md shadow-[#5DA9A4]/25 hover:shadow-lg hover:-translate-y-0.5 transition-all">Выбрать</button>
                            </div>
                        </div>
                    </details>
                    <button id="country-tv-search-btn" class="w-full sm:w-auto px-8 py-3.5 rounded-xl bg-[#FF6B6B] hover:bg-[#f65252] text-white font-bold text-sm shadow-md shadow-[#FF6B6B]/30 transition-all">
                        <i class="fas fa-search mr-2"></i> Найти туры
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Результаты поиска -->
        <div id="country-tv-results-wrapper" class="tv-results-shell max-w-7xl mx-auto mt-4 hidden">
            <div class="tv-results-toolbar">
                <h3 class="tv-results-toolbar__title heading-font text-xl font-bold text-slate-900">
                    Результаты <span id="country-tv-result-count" class="text-[#5DA9A4]">0</span>
                </h3>
                <div class="tv-sort-rail">
                    <select id="country-tv-sort" class="tv-select tv-sort-select px-3 py-2 rounded-xl border border-slate-200 text-slate-700">
                        <option value="price-asc">Сначала дешевые</option>
                        <option value="price-desc">Сначала дорогие</option>
                        <option value="rating">По рейтингу</option>
                    </select>
                </div>
            </div>
            <div id="country-tv-search-progress" class="hidden mb-6 p-4 rounded-xl bg-slate-50 border border-slate-200">
                <div class="flex items-center gap-3">
                    <div class="animate-spin w-5 h-5 border-2 border-[#5DA9A4] border-t-transparent rounded-full"></div>
                    <span class="text-slate-600">Поиск туров...</span>
                    <span id="country-tv-progress-text" class="text-slate-500 text-sm"></span>
                </div>
            </div>
            <div id="country-tv-search-results" class="tv-search-results-grid">
                <!-- Карточки туров подставляются JS -->
            </div>
            <div id="country-tv-load-more-wrapper" class="mt-10 text-center hidden">
                <button type="button" id="country-tv-load-more-btn" class="px-8 py-3.5 rounded-xl bg-gradient-to-r from-[#5DA9A4] to-[#8CC7C3] text-white font-semibold text-sm shadow-md shadow-[#5DA9A4]/25 hover:shadow-lg hover:-translate-y-0.5 transition-all disabled:opacity-70 disabled:pointer-events-none">
                    <i class="fas fa-plus-circle mr-2"></i><span id="country-tv-load-more-text">Загрузить ещё туры</span>
                </button>
            </div>
        </div>
    </div>
</section>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/ru.js" defer></script>
<?php
$_th_fpick_path_ctv = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'tourvisor-flight-pick.js';
$_th_fpick_ver_ctv = is_file($_th_fpick_path_ctv) ? (string) filemtime($_th_fpick_path_ctv) : '1';
?>
<script src="/frontend/js/tourvisor-flight-pick.js?v=<?php echo htmlspecialchars($_th_fpick_ver_ctv, ENT_QUOTES, 'UTF-8'); ?>"></script>
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
    const COUNTRY_NAME = <?php echo json_encode($countryName); ?>;
    const COUNTRY_SLUG = <?php echo json_encode($countrySlug); ?>;
    const COUNTRY_ALIASES = <?php echo json_encode($countryAliasesMap); ?>;
    // Резервный countryId по slug страницы используется только если API стран недоступен.
    const COUNTRY_ID_FALLBACK = <?php echo (int)$countryIdFallback; ?>;
    let COUNTRY_ID = null;
    let COUNTRY_LIST = [];

    function normalizeCountryToken(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/ё/g, 'е')
            .replace(/[^a-zа-я0-9]+/g, ' ')
            .trim();
    }

    function getTokenVariants(token) {
        const base = normalizeCountryToken(token);
        if (!base) return [];
        const compact = base.replace(/\s+/g, '');
        return compact && compact !== base ? [base, compact] : [base];
    }

    function getCountrySearchTokens() {
        let tokens = [];
        if (COUNTRY_NAME) tokens = tokens.concat(getTokenVariants(COUNTRY_NAME));
        if (COUNTRY_SLUG) tokens = tokens.concat(getTokenVariants(COUNTRY_SLUG));
        const aliases = (COUNTRY_ALIASES && COUNTRY_SLUG && Array.isArray(COUNTRY_ALIASES[COUNTRY_SLUG])) ? COUNTRY_ALIASES[COUNTRY_SLUG] : [];
        aliases.forEach(function(a) { tokens = tokens.concat(getTokenVariants(a)); });
        return Array.from(new Set(tokens.filter(Boolean)));
    }

    const COUNTRY_RESOLVER_KEY = '__th_country_id_resolver_state';
    const COUNTRY_RESOLVER_MAP = (window[COUNTRY_RESOLVER_KEY] = window[COUNTRY_RESOLVER_KEY] || {});
    const COUNTRY_RESOLVER_TOKEN = normalizeCountryToken(COUNTRY_SLUG || COUNTRY_NAME || '');

    function toCountryId(value) {
        const num = Number(value);
        return Number.isFinite(num) && num > 0 ? num : null;
    }
    function getCountryResolverState() {
        if (!COUNTRY_RESOLVER_MAP[COUNTRY_RESOLVER_TOKEN]) {
            COUNTRY_RESOLVER_MAP[COUNTRY_RESOLVER_TOKEN] = {
                apiId: null,
                fallbackId: toCountryId(COUNTRY_ID_FALLBACK),
                source: null,
                resolvePromise: null
            };
        } else if (!COUNTRY_RESOLVER_MAP[COUNTRY_RESOLVER_TOKEN].fallbackId) {
            COUNTRY_RESOLVER_MAP[COUNTRY_RESOLVER_TOKEN].fallbackId = toCountryId(COUNTRY_ID_FALLBACK);
        }
        return COUNTRY_RESOLVER_MAP[COUNTRY_RESOLVER_TOKEN];
    }
    function setResolvedCountryId(id, source) {
        const state = getCountryResolverState();
        const normalizedId = toCountryId(id);
        if (!normalizedId) return null;
        if (source === 'api') {
            state.apiId = normalizedId;
            state.source = 'api';
            COUNTRY_ID = normalizedId;
            return normalizedId;
        }
        if (!state.apiId) {
            state.fallbackId = normalizedId;
            state.source = 'fallback';
            COUNTRY_ID = normalizedId;
            return normalizedId;
        }
        COUNTRY_ID = state.apiId;
        return state.apiId;
    }
    function resolveCountryIdShared(fetchCountriesFn) {
        const state = getCountryResolverState();
        if (state.apiId) {
            COUNTRY_ID = state.apiId;
            return Promise.resolve(state.apiId);
        }
        if (state.resolvePromise) return state.resolvePromise;
        state.resolvePromise = Promise.resolve()
            .then(function() { return fetchCountriesFn(); })
            .then(function(resp) {
                const countries = resp && Array.isArray(resp.data) ? resp.data : [];
                if (countries.length) COUNTRY_LIST = countries.slice();
                const apiId = resolveCountryIdFromApiList(countries);
                if (apiId) return setResolvedCountryId(apiId, 'api');
                return setResolvedCountryId(state.fallbackId, 'fallback');
            })
            .catch(function() {
                return setResolvedCountryId(state.fallbackId, 'fallback');
            })
            .finally(function() {
                state.resolvePromise = null;
            });
        return state.resolvePromise;
    }

    function resolveCountryIdFromApiList(countries) {
        if (!Array.isArray(countries) || countries.length === 0) return null;
        const tokens = getCountrySearchTokens();
        for (let i = 0; i < countries.length; i++) {
            const c = countries[i] || {};
            const values = []
                .concat(getTokenVariants(c.name))
                .concat(getTokenVariants(c.russianName))
                .concat(getTokenVariants(c.englishName))
                .concat(getTokenVariants(c.slug))
                .filter(Boolean);
            const hasMatch = tokens.some(function(t) {
                return values.some(function(v) {
                    return v === t || v.includes(t) || t.includes(v);
                });
            });
            if (hasMatch && c.id != null && c.id !== '') {
                return Number(c.id);
            }
        }
        return null;
    }

    function getEffectiveCountryId() {
        const state = getCountryResolverState();
        return state.apiId || COUNTRY_ID || state.fallbackId || null;
    }

    // Данные для формы (вылет, страны, питание, курорты, расширенные фильтры) поставляются из API; при пустом/ошибке подставляются запасные значения.
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
            window.__country_tv_refsPromises = {
                dep: safeFetchJson(TV_API_BASE + sep + 'type=departures', { success: false, data: [] }),
                countries: safeFetchJson(TV_API_BASE + sep + 'type=countries', { success: false, data: [] })
            };
    })();
    (function tvSearchDebugBanner() {
        var style = 'color: #5DA9A4; font-weight: bold; font-size: 11px;';
        var styleDim = 'color: #64748b; font-size: 10px;';
        console.group('%c[Tourvisor · Страна] Поиск подключён: прокси + кэш', style);
        console.log('%cBase URL:', styleDim, TV_API_BASE);
        console.log('%cСтрана:', styleDim, COUNTRY_NAME || '—', '| fallback countryId:', COUNTRY_ID_FALLBACK);
        console.log('%cЦепочка кэша: файл → Firestore → all_tours → живой поиск. Логи запросов ниже.', styleDim);
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
            if (type === 'results' || type === 'search-cached' || type === 'tours') return `Туры/отели: ${d.length} шт.`;
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
        console.log('%c[Tourvisor · Страна] Запрос', 'color: #5DA9A4; font-weight: bold', 'type:', type, 'params:', paramsStr);
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
                console.log('%c[Tourvisor · Страна] Ответ ✓', 'color: #22c55e; font-weight: bold', 'type:', type, '|', summary + cacheInfo);
            } else if (type === 'search-cached' && (j.error === 'Cache miss' || j.fromCache === false)) {
                console.log('%c[Tourvisor · Страна] Кэш пуст', 'color: #94a3b8', 'по этим параметрам данных нет, пробуем по стране');
            } else {
                console.warn('%c[Tourvisor · Страна] Ошибка', 'color: #ef4444', 'type:', type, 'error:', j.error || j);
            }
            return j;
        } catch (e) {
            console.error('%c[Tourvisor · Страна] Ошибка запроса ✗', 'color: #ef4444; font-weight: bold', 'type:', type, e.message, 'url:', url);
            return { success: false, error: String(e.message) };
        }
    }

    function formatPrice(price) {
        return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', minimumFractionDigits: 0 }).format(price || 0);
    }

    let countryTvDatePicker = null;
    let countryTvLastResults = [];
    const COUNTRY_TV_PAGE_SIZE = 25;
    let countryTvDisplayedCount = 0;
    var countryTvNightsFrom = 6;
    var countryTvNightsTo = 9;

    document.addEventListener('DOMContentLoaded', async function() {
        const depSel = document.getElementById('country-tv-departure');
        const datesInp = document.getElementById('country-tv-dates');
        const mealSel = document.getElementById('country-tv-meal');
        const regionSel = document.getElementById('country-tv-region');
        if (!depSel || !datesInp || !mealSel || !regionSel) return;

        // Блок ТУРИСТЫ: как на скрине — строки «Ребенок до 2 лет», «Добавить ребенка», «Запомнить выбор», «Выбрать»
        var countryTvAdultsCount = 2;
        var countryTvChildrenAges = [];
        var countryTvAgeLabels = {0:'до 2 лет',2:'2 года',3:'3 года',4:'4 года',5:'5 лет',6:'6 лет',7:'7 лет',8:'8 лет',9:'9 лет',10:'10 лет',11:'11 лет',12:'12 лет',13:'13 лет',14:'14 лет',15:'15 лет'};
        var countryTvAdultsValueEl = document.getElementById('country-tv-adults-value');
        var countryTvTouristsSummaryText = document.getElementById('country-tv-tourists-summary-text');
        var countryTvTouristsBlock = document.getElementById('country-tv-tourists-block');
        var countryTvChildrenRows = document.getElementById('country-tv-children-rows');
        var countryTvAddChildBtn = document.getElementById('country-tv-add-child-btn');
        var countryTvChildAgePicker = document.getElementById('country-tv-child-age-picker');
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
                    '<button type="button" class="country-tv-child-remove w-9 h-9 rounded-full bg-sky-500 text-white flex items-center justify-center hover:bg-sky-600 flex-shrink-0 transition-colors" data-index="' + i + '" aria-label="Удалить">−</button>' +
                    '<div class="flex-1 min-w-0 px-4 py-2.5 rounded-full bg-sky-100 text-slate-800 text-sm text-center">' +
                    '<button type="button" class="country-tv-child-age-btn w-full text-left font-medium" data-index="' + i + '">Ребенок ' + label + '</button></div></div>';
            }).join('');
            countryTvChildrenRows.querySelectorAll('.country-tv-child-remove').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var i = parseInt(this.dataset.index, 10);
                    countryTvChildrenAges.splice(i, 1);
                    renderCountryChildrenRows();
                    updateCountryTouristsSummary();
                    countryTvChildAgePicker.classList.add('hidden');
                });
            });
            countryTvChildrenRows.querySelectorAll('.country-tv-child-age-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    countryTvChildAgePickerIndex = parseInt(this.dataset.index, 10);
                    countryTvChildAgePicker.classList.remove('hidden');
                    var grid = document.getElementById('country-tv-child-age-grid');
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
        document.getElementById('country-tv-tourists-trigger')?.addEventListener('click', function() {
            countryTvTouristsBlock.classList.toggle('hidden');
        });
        document.getElementById('country-tv-adults-minus')?.addEventListener('click', function() {
            if (countryTvAdultsCount > 1) { countryTvAdultsCount--; updateCountryTouristsSummary(); }
        });
        document.getElementById('country-tv-adults-plus')?.addEventListener('click', function() {
            if (countryTvAdultsCount < 6) { countryTvAdultsCount++; updateCountryTouristsSummary(); }
        });
        countryTvAddChildBtn?.addEventListener('click', function() {
            if (countryTvChildrenAges.length < 3) { countryTvChildrenAges.push(0); renderCountryChildrenRows(); updateCountryTouristsSummary(); }
        });
        document.getElementById('country-tv-tourists-apply')?.addEventListener('click', function() {
            if (document.getElementById('country-tv-remember-tourists')?.checked) {
                try { localStorage.setItem('country_tv_tourists', JSON.stringify({ adults: countryTvAdultsCount, childrenAges: countryTvChildrenAges })); } catch (e) {}
            }
            countryTvTouristsBlock.classList.add('hidden');
        });
        try {
            var saved = localStorage.getItem('country_tv_tourists');
            if (saved) {
                var d = JSON.parse(saved);
                if (d && typeof d.adults === 'number' && d.adults >= 1 && d.adults <= 6) countryTvAdultsCount = d.adults;
                if (d && Array.isArray(d.childrenAges)) countryTvChildrenAges = d.childrenAges.filter(function(a) { var n = parseInt(a,10); return n >= 0 && n <= 15; }).slice(0, 3);
            }
        } catch (e) {}
        renderCountryChildrenRows();
        updateCountryTouristsSummary();

        // Инициализация календаря
        const today = new Date();
        const defaultFrom = new Date(today);
        const defaultTo = new Date(today); defaultTo.setDate(today.getDate() + 14);
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
            datesInp.addEventListener('focus', function () { countryTvDatePicker.open(); });
            var countryDatesWrap = document.getElementById('country-tv-dates-wrap');
            if (countryDatesWrap) {
                countryDatesWrap.addEventListener('click', function (e) {
                    e.preventDefault();
                    datesInp.focus();
                    countryTvDatePicker.open();
                });
            }
        }
        var presetsEl = document.getElementById('country-tv-date-presets');
        if (presetsEl && window.THDatePresets && typeof window.THDatePresets.renderChips === 'function') {
            window.THDatePresets.renderChips(presetsEl, function (preset) {
                window.THDatePresets.apply(preset, { mainPicker: countryTvDatePicker, input: datesInp });
            });
        }

        // Приоритет: города вылета и страны — уже запрошены при загрузке скрипта; заполняем селекты по готовности, не ждём meals
        const refsPromises = window.__country_tv_refsPromises;
        const pDep = refsPromises ? refsPromises.dep : tvFetch('departures');
        const pCountries = refsPromises ? refsPromises.countries : tvFetch('countries');
        const pMeal = tvFetch('meals');

        const [rDep, rCountries] = await Promise.all([pDep, pCountries]);
        console.log('%c[API → сайт] Ответы API получены (страница страны)', 'color: #5DA9A4; font-weight: bold', { departures: rDep.success ? (rDep.data?.length ?? 0) + ' шт.' : 'ошибка', countries: rCountries.success ? (rCountries.data?.length ?? 0) + ' шт.' : 'ошибка' });

        let departuresList = [];
        if (rDep.success && Array.isArray(rDep.data) && rDep.data.length > 0) {
            departuresList = rDep.data;
            console.log('%c[API → сайт] Справочник городов вылета', 'color: #22c55e', departuresList.length, 'шт.');
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
            console.log('[Фильтры] meals:', rMeal.data.length, 'записей');
        } else {
            const mealFallback = [{ id: 1, name: 'RO', russianName: 'Без питания' }, { id: 2, name: 'BB', russianName: 'Завтрак' }, { id: 3, name: 'HB', russianName: 'Завтрак + ужин' }, { id: 4, name: 'FB', russianName: 'Полный пансион' }, { id: 5, name: 'AI', russianName: 'Всё включено' }, { id: 6, name: 'UAI', russianName: 'Ультра всё включено' }];
            mealSel.innerHTML = '<option value="">Любое</option>' + mealFallback.map(m => '<option value="' + m.id + '">' + (m.russianName || m.name) + '</option>').join('');
            console.warn('[API → сайт] Питание: данные с API не получены, подставлен fallback');
            console.log('[Фильтры] Ошибка meals: использован fallback', mealFallback.length, 'записей');
        }

        const resolvedCountryId = await resolveCountryIdShared(function() {
            if (rCountries && rCountries.success && Array.isArray(rCountries.data)) return Promise.resolve(rCountries);
            return tvFetch('countries');
        });
        if (resolvedCountryId && getCountryResolverState().apiId) {
            console.log('%c[Страна · Tourvisor] Единый countryId (API):', 'color: #22c55e', resolvedCountryId, '| страна:', COUNTRY_NAME || COUNTRY_SLUG || '—');
        } else {
            console.warn('[Страна · Tourvisor] API countryId не найден, используется fallback countryId:', resolvedCountryId || COUNTRY_ID_FALLBACK, '| страна:', COUNTRY_NAME || COUNTRY_SLUG || '—');
        }

        window.__country_tv_hotelServicesCache = window.__country_tv_hotelServicesCache || {};
        var countryTvSelectedServiceIds = [];
        var countryTvServiceIdToName = {};
        function renderCountrySelectedFilterTags() {
            var wrap = document.getElementById('country-tv-adv-selected-tags');
            if (!wrap) return;
            wrap.innerHTML = countryTvSelectedServiceIds.map(function(id) {
                var name = countryTvServiceIdToName[id] || ('ID ' + id);
                return '<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-sky-100 text-sky-800 text-sm">' + name + ' <button type="button" class="country-tv-adv-remove-tag hover:text-red-600" data-id="' + id + '" aria-label="Снять">×</button></span>';
            }).join('');
            wrap.querySelectorAll('.country-tv-adv-remove-tag').forEach(function(b) {
                b.addEventListener('click', function() {
                    var id = parseInt(this.dataset.id, 10);
                    countryTvSelectedServiceIds = countryTvSelectedServiceIds.filter(function(x) { return x !== id; });
                    var cb = document.querySelector('.country-tv-adv-service-cb[data-id="' + id + '"]');
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
            var beach = document.getElementById('country-tv-adv-cat-beach');
            var hotel = document.getElementById('country-tv-adv-cat-hotel');
            var room = document.getElementById('country-tv-adv-cat-room');
            var children = document.getElementById('country-tv-adv-cat-children');
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
                    cb.className = 'country-tv-adv-service-cb rounded border-slate-300 text-[#5DA9A4] focus:ring-[#5DA9A4]';
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
            var beach = document.getElementById('country-tv-adv-cat-beach');
            if (beach) beach.innerHTML = '<p class="text-slate-500 text-sm">Загрузка из API…</p>';
            if (window.__country_tv_hotelServicesCache[countryId]) {
                [document.getElementById('country-tv-adv-cat-beach'), document.getElementById('country-tv-adv-cat-hotel'), document.getElementById('country-tv-adv-cat-room'), document.getElementById('country-tv-adv-cat-children')].forEach(function(el) { if (el) el.innerHTML = ''; });
                renderCountryAdvancedFilters(window.__country_tv_hotelServicesCache[countryId]);
                console.log('%c[API → сайт] Расширенные фильтры: данные из кэша (ранее с API)', 'color: #22c55e', 'countryId', countryId);
                return;
            }
            var r = await tvFetch('hotel-services', { countryId: countryId });
            [document.getElementById('country-tv-adv-cat-beach'), document.getElementById('country-tv-adv-cat-hotel'), document.getElementById('country-tv-adv-cat-room'), document.getElementById('country-tv-adv-cat-children')].forEach(function(el) { if (el) el.innerHTML = ''; });
            if (r.success && Array.isArray(r.data) && r.data.length > 0) {
                window.__country_tv_hotelServicesCache[countryId] = r.data;
                renderCountryAdvancedFilters(r.data);
                var totalItems = r.data.reduce(function(sum, gr) { return sum + (gr.items && gr.items.length ? gr.items.length : 0); }, 0);
                console.log('%c[API → сайт] Данные с API применены: расширенные фильтры', 'color: #22c55e', r.data.length, 'категорий,', totalItems, 'услуг');
            } else {
                renderCountryAdvancedFilters([]);
                console.warn('[API → сайт] Расширенные фильтры: API не вернул данные');
            }
        }
        loadCountryAdvancedFilters(getEffectiveCountryId());

        console.log('%c[API → сайт] Итог (страница страны): вылет, страны, питание заполнены из API; расширенные фильтры загружаются по countryId.', 'color: #5DA9A4; font-weight: bold');

        regionSel.innerHTML = '<option value="">Любой</option>';

        async function loadCountryTvRegions() {
            const did = depSel ? String(depSel.value || '').trim() : '';
            const effectiveCountryId = getEffectiveCountryId();
            if (!did || !effectiveCountryId) {
                regionSel.innerHTML = '<option value="">Любой</option>';
                return;
            }
            regionSel.innerHTML = '<option value="">Загрузка...</option>';
            let rReg = await tvFetch('regions', { countryId: effectiveCountryId });
            if (!rReg.success && (!rReg.error || String(rReg.error).indexOf('timeout') >= 0 || String(rReg.error).indexOf('failed') >= 0)) {
                await new Promise(r => setTimeout(r, 800));
                rReg = await tvFetch('regions', { countryId: effectiveCountryId });
            }
            if (rReg.success && Array.isArray(rReg.data) && rReg.data.length > 0) {
                regionSel.innerHTML = '<option value="">Любой</option>' + rReg.data.map(r =>
                    `<option value="${r.id}">${r.name || ''}</option>`
                ).join('');
                console.log('%c[API → сайт] Данные с API применены: курорты', 'color: #22c55e', rReg.data.length, 'курортов');
                console.log('[Фильтры] regions:', rReg.data.length, 'записей');
            } else {
                regionSel.innerHTML = '<option value="">Любой</option>';
                console.warn('[API → сайт] Курорты: пусто или ошибка');
                console.log('[Фильтры] Ошибка regions:', rReg.error || 'пустой ответ', '— курорты временно недоступны');
            }
        }

        // hidden #country-tv-departure: автовыбор вылета не шлёт change — грузим курорты явно
        if (depSel) depSel.addEventListener('change', loadCountryTvRegions);
        window.addEventListener('th-departure-saved', function () {
            if (depSel && depSel.value) loadCountryTvRegions();
        });
        await loadCountryTvRegions();

        var countryTvNightsPopup = document.getElementById('country-tv-nights-popup');
        var countryTvNightsGrid = document.getElementById('country-tv-nights-grid');
        var countryTvNightsQuick = document.getElementById('country-tv-nights-quick');
        var countryTvNightsSelectFrom = true;
        function closeCountryTvNightsPopup() {
            if (countryTvNightsPopup) {
                countryTvNightsPopup.classList.add('hidden');
                countryTvNightsPopup.style.display = 'none';
                countryTvNightsPopup.setAttribute('aria-hidden', 'true');
            }
        }
        function updateCountryTvNightsSummary() {
            var el = document.getElementById('country-tv-nights-summary-text');
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
        if (countryTvNightsQuick && window.THDatePresets && typeof window.THDatePresets.renderNightsChips === 'function') {
            window.THDatePresets.renderNightsChips(countryTvNightsQuick, function (from, to) {
                countryTvNightsFrom = from;
                countryTvNightsTo = to;
                countryTvNightsSelectFrom = true;
                renderCountryTvNightsGrid();
                updateCountryTvNightsSummary();
                closeCountryTvNightsPopup();
            });
        }
        var nightsTrigger = document.getElementById('country-tv-nights-trigger');
        if (nightsTrigger) {
            nightsTrigger.addEventListener('click', function() {
                countryTvNightsSelectFrom = true;
                renderCountryTvNightsGrid();
                if (countryTvNightsPopup) { countryTvNightsPopup.classList.remove('hidden'); countryTvNightsPopup.style.display = 'flex'; countryTvNightsPopup.setAttribute('aria-hidden', 'false'); }
            });
        }
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
        var nightsApplyBtn = document.getElementById('country-tv-nights-apply');
        if (nightsApplyBtn) {
            nightsApplyBtn.addEventListener('click', function() {
                if (countryTvNightsFrom > 28) countryTvNightsFrom = 28;
                if (countryTvNightsTo > 28) countryTvNightsTo = 28;
                if (countryTvNightsTo < countryTvNightsFrom) countryTvNightsTo = countryTvNightsFrom;
                updateCountryTvNightsSummary();
                closeCountryTvNightsPopup();
            });
        }
        countryTvNightsPopup && countryTvNightsPopup.addEventListener('click', function(e) {
            if (e.target === countryTvNightsPopup && nightsApplyBtn) nightsApplyBtn.click();
        });
        updateCountryTvNightsSummary();

        // Кнопка поиска
        document.getElementById('country-tv-search-btn')?.addEventListener('click', () => performCountryTvSearch());
        setTimeout(function () {
            if (getEffectiveCountryId() && depSel && depSel.value && typeof performCountryTvSearch === 'function') {
                performCountryTvSearch();
            }
        }, 600);
        document.getElementById('country-tv-sort')?.addEventListener('change', applyCountryTvSort);
        document.getElementById('country-tv-load-more-btn')?.addEventListener('click', () => loadMoreCountryTvResults());
    });

    function updateCountryTvLoadMoreButton() {
        const wrapper = document.getElementById('country-tv-load-more-wrapper');
        const btn = document.getElementById('country-tv-load-more-btn');
        const textEl = document.getElementById('country-tv-load-more-text');
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
        const wrapper = document.getElementById('country-tv-results-wrapper');
        const progress = document.getElementById('country-tv-search-progress');
        const resultsDiv = document.getElementById('country-tv-search-results');
        if (!wrapper || !resultsDiv) return;

        // Приоритет: countryId, определённый по API стран. Резерв: fallback по slug.
        const effectiveCountryId = getEffectiveCountryId();
        if (!effectiveCountryId) {
            wrapper.classList.remove('hidden');
            progress.classList.add('hidden');
            resultsDiv.innerHTML = '<div class="tv-results-empty"><p class="text-slate-600">Страна не определена. Обновите страницу или выберите страну из списка.</p></div>';
            wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
            console.warn('[Страна · Поиск] countryId не задан');
            return;
        }

        console.log('%c[Страна · Поиск] Нажата кнопка «Найти»', 'color: #5DA9A4; font-weight: bold', 'countryId:', effectiveCountryId, 'страна:', COUNTRY_NAME);
        wrapper.classList.remove('hidden');
        progress.classList.remove('hidden');
        resultsDiv.innerHTML = '';
        countryTvDisplayedCount = 0;

        const dep = document.getElementById('country-tv-departure').value || (window.TH_DEPARTURE && window.TH_DEPARTURE.id) || '12';
        let nFrom = typeof countryTvNightsFrom !== 'undefined' ? countryTvNightsFrom : 6;
        let nTo = typeof countryTvNightsTo !== 'undefined' ? countryTvNightsTo : 9;
        const origNFrom = nFrom;
        const origNTo = nTo;
        if (nTo < nFrom) nTo = nFrom;
        const adults = typeof countryTvAdultsCount !== 'undefined' ? countryTvAdultsCount : 2;
        let childs = '';
        if (typeof countryTvChildrenAges !== 'undefined' && countryTvChildrenAges.length > 0) {
            childs = countryTvChildrenAges.slice(0, 3).join(',');
        }
        
        const datesVal = (document.getElementById('country-tv-dates').value || '').trim();
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
        
        const meal = document.getElementById('country-tv-meal').value;
        const category = document.getElementById('country-tv-category').value;
        const region = document.getElementById('country-tv-region').value;

        // Полный поиск по стране (все туры по фильтрам), не только акции — через search-cached + live.
        const cacheParams = {
            departureId: dep,
            countryId: effectiveCountryId,
            countryName: COUNTRY_NAME || '',
            dateFrom, dateTo,
            nightsFrom: nFrom || 6,
            nightsTo: nTo || 9,
            adults
        };
        if (childs) cacheParams.childs = childs;
        if (meal) cacheParams.meal = meal;
        if (category) cacheParams.hotelCategory = category;
        if (region) cacheParams.regionIds = region;
        if (typeof countryTvSelectedServiceIds !== 'undefined' && countryTvSelectedServiceIds.length > 0) {
            cacheParams.hotelServices = countryTvSelectedServiceIds.join(',');
        }
        console.log('%c[API → сайт] Параметры поиска отправляются в API (страница страны)', 'color: #5DA9A4; font-weight: bold', cacheParams);
        console.log('[Фильтры] Поиск с параметрами:', { meal: meal || '—', regionIds: region || '—', hotelCategory: category || '—', hotelServices: (typeof countryTvSelectedServiceIds !== 'undefined' && countryTvSelectedServiceIds.length) ? countryTvSelectedServiceIds.join(',') : '—' });
        let rCache = await tvFetch('search-cached', Object.assign({}, cacheParams, { _cacheOnly: true }));
        const cacheEmpty = !rCache.success || !Array.isArray(rCache.data) || rCache.data.length === 0;
        if (cacheEmpty) {
            rCache = await tvFetch('search-cached', Object.assign({}, cacheParams, { _forceLive: true }));
        }
        if ((!rCache.success || !Array.isArray(rCache.data) || rCache.data.length === 0)) {
            var altParams = null;
            if (origNFrom === 6 && origNTo === 9) {
                altParams = Object.assign({}, cacheParams, { nightsFrom: 5, nightsTo: 10 });
            } else if (origNFrom >= 10) {
                var relaxedFrom = Math.max(7, origNFrom - 2);
                if (relaxedFrom < origNFrom) {
                    altParams = Object.assign({}, cacheParams, { nightsFrom: relaxedFrom, nightsTo: origNTo });
                }
            }
            if (altParams) {
            let rAlt = await tvFetch('search-cached', Object.assign({}, altParams, { _cacheOnly: true }));
            if (!rAlt.success || !Array.isArray(rAlt.data) || rAlt.data.length === 0) {
                rAlt = await tvFetch('search-cached', Object.assign({}, altParams, { _forceLive: true }));
            }
            if (rAlt.success && Array.isArray(rAlt.data) && rAlt.data.length > 0) rCache = rAlt;
            }
        }
        const loadedTours = Array.isArray(rCache.data) ? rCache.data : [];
        if (rCache.success && loadedTours.length > 0) {
            progress.classList.add('hidden');
            countryTvLastResults = loadedTours;
            countryTvDisplayedCount = Math.min(COUNTRY_TV_PAGE_SIZE, countryTvLastResults.length);
            document.getElementById('country-tv-result-count').textContent = countryTvLastResults.length;
            applyCountryTvSort();
            updateCountryTvLoadMoreButton();
            document.getElementById('country-tv-results-wrapper').scrollIntoView({ behavior: 'smooth', block: 'start' });
            console.log('[API → страна] Туров загружено:', countryTvLastResults.length);
            console.log('%c[API → сайт] Результаты с API получены и отображены на сайте', 'color: #22c55e; font-weight: bold', { туров: countryTvLastResults.length, тип: 'search-cached' });
            return;
        }

        progress.classList.add('hidden');
        if (rCache.success) {
            console.log('[API → страна] Туров загружено:', 0);
            const emptyMsg = (origNFrom === 6 && origNTo === 9)
                ? 'Нет туров на 6–9 ночей. Попробуйте другой диапазон'
                : 'По выбранным параметрам туры не найдены. Измените даты, курорт или фильтры и попробуйте снова.';
            resultsDiv.innerHTML = '<div class="tv-results-empty"><p class="text-slate-600 mb-2">' + emptyMsg.replace(/</g, '&lt;') + '</p></div>';
            document.getElementById('country-tv-result-count').textContent = '0';
        } else {
            const errMsg = (rCache.error || 'Не удалось загрузить туры. Попробуйте позже.');
            resultsDiv.innerHTML = '<div class="tv-results-empty"><p class="text-slate-600 mb-2">' + (String(errMsg).replace(/</g, '&lt;')) + '</p></div>';
            console.error('[API → страна] Ошибка загрузки туров:', rCache.error || rCache);
            console.log('[API → страна] Туров загружено:', 0);
        }
        return;
    }

    function applyCountryTvSort() {
        const sortVal = document.getElementById('country-tv-sort')?.value || 'price-asc';
        let arr = [...countryTvLastResults];
        if (sortVal === 'price-asc') arr.sort((a, b) => countryTourListPrice(a) - countryTourListPrice(b));
        else if (sortVal === 'price-desc') arr.sort((a, b) => countryTourListPrice(b) - countryTourListPrice(a));
        else if (sortVal === 'rating') arr.sort((a, b) => (b.rating || 0) - (a.rating || 0));
        renderCountryTvResults(arr.slice(0, countryTvDisplayedCount));
    }

    window.__countryFlightsByTourId = window.__countryFlightsByTourId || {};
    function getCountryTourId(h) {
        const tour = (h.tours && h.tours[0]) ? h.tours[0] : {};
        return (tour.id != null && tour.id !== '') ? String(tour.id) : '';
    }
    function loadCountryFlightsForTours(hotels, callback) {
        const base = typeof TV_API_BASE !== 'undefined' ? TV_API_BASE : '';
        const depCityFl = (window.TH_DEPARTURE && window.TH_DEPARTURE.name) || 'Самара';
        if (typeof thLoadTourFlightsForHotels === 'function' && base) {
            thLoadTourFlightsForHotels(hotels, {
                apiBase: base,
                departureCity: depCityFl,
                maxTours: hotels.length,
                maxConcurrent: 6,
                getTourId: getCountryTourId,
                onDone: callback
            });
            return;
        }
        if (callback) callback();
    }

    function tvTourStartYmdCountry(tour) {
        if (!tour) return '';
        const raw = String(tour.date || tour.startDate || tour.departureDate || '').trim();
        const m = raw.match(/^(\d{4}-\d{2}-\d{2})/);
        return m ? m[1] : '';
    }
    function tvTourReturnYmdCountry(startYmd, nightsNum) {
        if (!startYmd || !nightsNum) return '';
        const p = startYmd.split('-');
        if (p.length !== 3) return '';
        const d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10), 12, 0, 0);
        if (isNaN(d.getTime())) return '';
        d.setDate(d.getDate() + nightsNum);
        const pad = (n) => String(n).padStart(2, '0');
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    }
    function thPickFirstPositivePriceNumCountry() {
        const args = Array.from(arguments);
        for (let i = 0; i < args.length; i++) {
            const v = args[i];
            if (v == null || v === '') continue;
            const n = Number(v);
            if (!Number.isNaN(n) && n > 0) return n;
        }
        return 0;
    }
    /** Единая логика с акциями (promoHotelListPrice): totalPrice и др. до поля price из обложки. */
    function countryTourListPrice(h) {
        if (!h) return 0;
        if (window.THTourPriceSanity && typeof window.THTourPriceSanity.hotelMinPlausiblePrice === 'function') {
            var sane = window.THTourPriceSanity.hotelMinPlausiblePrice(h, {
                countryId: (typeof countryIdFallback !== 'undefined' ? countryIdFallback : 0)
                    || (h.country && h.country.id) || 0
            });
            return sane > 0 ? sane : 0;
        }
        const tour = (h.tours || [])[0] || {};
        if (h.tours && h.tours[0]) {
            let n = thPickFirstPositivePriceNumCountry(
                tour.totalPrice,
                tour.price,
                tour.priceRub,
                tour.cost
            );
            if (n > 0) return Math.round(n);
            n = thPickFirstPositivePriceNumCountry(h.price);
            return n > 0 ? Math.round(n) : 0;
        }
        return Math.round(thPickFirstPositivePriceNumCountry(h.price, h.minPrice, h.minprice));
    }

    function countryTourPhotoUrlsForCard(h) {
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

    function countryCardPrimaryImage(h) {
        const raw = countryTourPhotoUrlsForCard(h);
        for (let i = 0; i < raw.length; i++) {
            const u = getTourvisorImageUrl(raw[i]);
            if (u && u.indexOf('unsplash.com') === -1) return u;
        }
        return raw.length ? getTourvisorImageUrl(raw[0]) : getTourvisorImageUrl('');
    }

    function renderCountryTvResults(hotels) {
        const container = document.getElementById('country-tv-search-results');
        if (!container) return;
        if (hotels.length === 0) {
            container.innerHTML = '<div class="tv-results-empty"><i class="fas fa-search text-5xl text-slate-300 mb-4"></i><p class="text-slate-600">Ничего не найдено</p></div>';
            return;
        }
        const depEl = document.getElementById('country-tv-departure');
        const departureCity = (window.TH_DEPARTURE && window.TH_DEPARTURE.name) || 'Самара';
        const departureIdVal = (depEl && depEl.value) ? String(depEl.value).trim() : ((window.TH_DEPARTURE && window.TH_DEPARTURE.id) || '12');
        const tourDetailBase = '/frontend/window';
        if (window.THTourCard && typeof window.THTourCard.render === 'function') {
            const adultsCt = typeof countryTvAdultsCount !== 'undefined' ? countryTvAdultsCount : 2;
            const priceAdults = Math.max(1, Math.min(9, adultsCt));
            container.innerHTML = hotels.map(h => {
                const tour = (h.tours || [])[0] || {};
                const region = h.region?.name || '';
                const country = h.country?.name || '';
                const meal = tour.meal?.russianName || tour.meal?.name || '';
                const nightsNum = parseInt(String(tour.nights || ''), 10) || 0;
                const startYmd = countryTvTourStartYmd(tour);
                const retYmd = (startYmd && nightsNum) ? countryTvTourReturnYmd(startYmd, nightsNum) : '';
                const price = countryTvHotelListPrice(h);
                let link = h.hotelDescriptionLink || h.hoteldescriptionlink || h.link || '';
                if (typeof TourLinkUtils !== 'undefined' && TourLinkUtils.sanitizeTourLink) link = TourLinkUtils.sanitizeTourLink(link) || '';
                const tourId = getCountryTourId(h);
                const cardImg = countryCardPrimaryImage(h);
                const params = {
                    tour_link: link, country, hotel_name: (h.name || ''), price: String(price),
                    nights: String(tour.nights || ''), meal, region, departure_city: departureCity,
                    adults: String(priceAdults), tour_id: tourId,
                    image: cardImg || ''
                };
                if (startYmd) params.date_from = startYmd;
                if (retYmd) params.date_to = retYmd;
                if (departureIdVal) params.departure_id = departureIdVal;
                if (h.id) params.hotel_id = String(h.id);
                try {
                    params.return_url = (window.TourSessionManager && window.TourSessionManager.buildReturnUrl)
                        ? window.TourSessionManager.buildReturnUrl()
                        : (window.location.pathname + window.location.search);
                } catch (eRu) {}
                const cardHref = country ? (tourDetailBase + '/tour-detail.php?' + new URLSearchParams(params).toString()) : (link || '#');
                return window.THTourCard.render(h, {
                    tour, getImageUrl: getTourvisorImageUrl, imageProxy: TV_IMAGE_PROXY,
                    image: cardImg, detailUrl: cardHref,
                    adults: priceAdults, dateFrom: startYmd, dateTo: retYmd, price,
                    departureCity, departureId: departureIdVal, carousel: true,
                    flightMeta: (typeof thFlightsCacheGet === 'function' && tourId)
                        ? thFlightsCacheGet(tourId, departureCity)
                        : ((window.__countryFlightsByTourId && tourId) ? window.__countryFlightsByTourId[tourId] : null)
                });
            }).join('');
            if (typeof thLoadTourFlightsForHotels === 'function' && TV_API_BASE) {
                thLoadTourFlightsForHotels(hotels, {
                    apiBase: TV_API_BASE.replace(/\/$/, ''),
                    departureCity: departureCity,
                    maxTours: hotels.length,
                    maxConcurrent: 6,
                    patchEvery: 2,
                    getTourId: getCountryTourId,
                    patchContainer: container,
                    onDone: function () {
                        if (window.THTourCard && typeof window.THTourCard.mountInContainer === 'function') {
                            window.THTourCard.mountInContainer(container);
                        }
                    }
                });
            }
            if (window.THTourCard && typeof window.THTourCard.mountInContainer === 'function') {
                window.THTourCard.mountInContainer(container);
            } else if (window.THTourCard && typeof window.THTourCard.ensureCarouselsInContainer === 'function') {
                window.THTourCard.ensureCarouselsInContainer(container);
            } else if (window.THTourCard && typeof window.THTourCard.kickImagesInContainer === 'function') {
                window.THTourCard.kickImagesInContainer(container);
            }
            document.getElementById('country-tv-result-count').textContent = countryTvLastResults.length;
            updateCountryTvLoadMoreButton();
            return;
        }
        container.innerHTML = hotels.map(h => {
            const picRaw = h.picturelink ?? h.pictureLink ?? '';
            const photoUrls = countryTourPhotoUrlsForCard(h);
            const img = getTourvisorImageUrl(picRaw || photoUrls[0] || '');
            const region = h.region?.name || '';
            const country = h.country?.name || '';
            const tour = (h.tours || [])[0] || {};
            const meal = tour.meal?.russianName || tour.meal?.name || '';
            const nights = tour.nights || '';
            const nightsNum = parseInt(String(nights), 10) || 0;
            const startYmd = tvTourStartYmdCountry(tour);
            const retYmd = (startYmd && nightsNum) ? tvTourReturnYmdCountry(startYmd, nightsNum) : '';
            const price = countryTourListPrice(h);
            const rating = h.rating || 0;
            let link = h.hotelDescriptionLink || h.hoteldescriptionlink || h.link || '';
            if (typeof TourLinkUtils !== 'undefined' && TourLinkUtils.sanitizeTourLink) {
                link = TourLinkUtils.sanitizeTourLink(link) || '';
            }
            const hasCountry = country && country.length > 0;
            const desc = (h.description || h.hotelDescription || h.descr || '').toString().trim();
            const tourId = getCountryTourId(h);
            const flightData = window.__countryFlightsByTourId[tourId];
            const airlineLabel = (flightData && flightData.companies && flightData.companies[0]) ? flightData.companies[0] : '—';
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
            if (tourId) params.tour_id = tourId;
            if (h.id) params.hotel_id = String(h.id);
            if (startYmd) params.date_from = startYmd;
            if (retYmd) params.date_to = retYmd;
            try {
                params.return_url = (window.TourSessionManager && window.TourSessionManager.buildReturnUrl)
                    ? window.TourSessionManager.buildReturnUrl()
                    : (window.location.pathname + window.location.search);
            } catch (eRu2) {}
            const adultsCt = typeof countryTvAdultsCount !== 'undefined' ? countryTvAdultsCount : 2;
            const priceAdults = Math.max(1, Math.min(9, adultsCt));
            params.adults = String(priceAdults);
            if (typeof countryTvChildrenAges !== 'undefined' && countryTvChildrenAges.length > 0) {
                params.childs = countryTvChildrenAges.slice(0, 3).join(',');
            }
            if (departureIdVal) params.departure_id = departureIdVal;
            const tourDetailUrl = hasCountry ? (tourDetailBase + '/tour-detail.php?' + new URLSearchParams(params).toString()) : (link || '#');
            const cardHref = tourDetailUrl !== '#' ? tourDetailUrl : (link || '#');
            return `
            <article class="tv-tour-card"${h.id ? ' data-th-hotel-id="' + String(h.id).replace(/"/g, '&quot;') + '"' : ''}>
                <a href="${cardHref}" class="tv-tour-card__link th-tour-card__link--main">
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
                        <div class="tv-tour-card__flight">
                            <i class="fas fa-plane" aria-hidden="true"></i>
                            <span>Перелёт: ${(airlineLabel || '—').replace(/</g, '&lt;')}</span>
                        </div>
                        <div class="tv-tour-card__bottom">
                            <div>
                                <span class="tv-tour-card__price-label">за ${priceAdults} взрослых </span>
                                <span class="tv-tour-card__price">${formatPrice(price)}</span>
                            </div>
                            <span class="tv-tour-card__cta">Просмотреть параметры тура и забронировать →</span>
                        </div>
                    </div>
                </a>
            </article>`;
        }).join('');
        document.getElementById('country-tv-result-count').textContent = countryTvLastResults.length;
        updateCountryTvLoadMoreButton();
    }

    // После «Назад» из карточки тура (bfcache) браузер восстанавливает скролл к результатам —
    // на узком экране форма поиска оказывается выше вьюпорта и кажется «пропавшей».
    window.addEventListener('pageshow', function(ev) {
        if (!ev.persisted) return;
        if (window.TourSessionManager && window.TourSessionManager.hasPendingScrollRestore && window.TourSessionManager.hasPendingScrollRestore()) return;
        var rw = document.getElementById('country-tv-results-wrapper');
        var sec = document.getElementById('country-tour-search-section');
        if (!rw || !sec || rw.classList.contains('hidden')) return;
        if (typeof window.matchMedia === 'function' && !window.matchMedia('(max-width: 1023px)').matches) return;
        requestAnimationFrame(function() {
            sec.scrollIntoView({ behavior: 'auto', block: 'start' });
        });
    });
})();
</script>
