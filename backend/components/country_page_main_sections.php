<?php
/**
 * Общая вёрстка страниц стран: hero, акции, фотогалерея + CTA, кратко о стране, поиск TourVisor.
 *
 * Ожидает: $countryData (name, slug, flag, description, bio, images, highlights,
 * bestTime, currency, language, visa), функцию getCountryCode($slug) в области видимости.
 */
if (empty($countryData) || empty($countryData['name'])) {
    return;
}
$countrySlug = $countryData['slug'] ?? '';
$heroImg = $countryData['images'][0] ?? '';
$codeMobile = function_exists('getCountryCode')
    ? htmlspecialchars(getCountryCode($countrySlug), ENT_QUOTES, 'UTF-8')
    : htmlspecialchars(strtoupper(substr((string) $countrySlug, 0, 2)), ENT_QUOTES, 'UTF-8');

/** Ссылка на страницу акций с предвыбранной страной (как в promotions.php). */
$country_promo_listing_url = '/frontend/window/promotions.php';
if (!empty($countrySlug)) {
    $promoMapFile = __DIR__ . '/../config/country_promo_tourvisor_map.php';
    $promoPack = is_file($promoMapFile) ? require $promoMapFile : [];
    $slugToId = $promoPack['slug_to_id'] ?? [];
    $tvCid = (int) ($slugToId[$countrySlug] ?? 0);
    if ($tvCid > 0) {
        // Город вылета берётся из localStorage на promotions.php — не фиксируем PROMO_* из .env в ссылке.
        $country_promo_listing_url = '/frontend/window/promotions.php?countryId=' . $tvCid
            . '&countryName=' . rawurlencode((string) ($countryData['name'] ?? ''));
    }
}

$bioSnippet = '';
if (!empty($countryData['bio'])) {
    $parts = preg_split("/\r\n|\n|\r/", trim((string) $countryData['bio']));
    $buf = '';
    foreach ($parts as $line) {
        $line = trim($line);
        if ($line === '') {
            if ($buf !== '') {
                break;
            }
            continue;
        }
        $buf .= ($buf === '' ? '' : ' ') . $line;
        $maxLen = 420;
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($buf, 'UTF-8') > $maxLen) {
                $buf = mb_substr($buf, 0, $maxLen - 1, 'UTF-8') . '…';
                break;
            }
        } elseif (strlen($buf) > $maxLen) {
            $buf = substr($buf, 0, $maxLen - 3) . '…';
            break;
        }
    }
    $bioSnippet = $buf;
}

if (!function_exists('th_country_page_image_src')) {
    /**
     * Абсолютный URL картинки для src (пути ../img/... из шаблонов стран).
     */
    function th_country_page_image_src(string $path): string
    {
        $p = trim($path);
        if ($p === '') {
            return '';
        }
        if (preg_match('#\Ahttps?://#i', $p)) {
            return htmlspecialchars($p, ENT_QUOTES, 'UTF-8');
        }
        if (isset($p[0]) && $p[0] === '/') {
            return htmlspecialchars($p, ENT_QUOTES, 'UTF-8');
        }
        if (strncmp($p, '../', 3) === 0) {
            return htmlspecialchars('/frontend/window/' . substr($p, 3), ENT_QUOTES, 'UTF-8');
        }

        return htmlspecialchars('/frontend/window/' . ltrim($p, '/'), ENT_QUOTES, 'UTF-8');
    }
}
?>
    <!-- Hero: название + фон-фото -->
    <section class="th-country-hero relative min-h-[44vh] md:min-h-[50vh] flex items-center py-16 md:py-24 overflow-hidden text-white">
        <div class="absolute inset-0 z-0">
            <?php if ($heroImg !== ''): ?>
            <img src="<?php echo th_country_page_image_src($heroImg); ?>"
                 alt="<?php echo htmlspecialchars($countryData['name'] . ' — фон', ENT_QUOTES, 'UTF-8'); ?>"
                 class="absolute inset-0 w-full h-full object-cover scale-105"
                 loading="eager"
                 decoding="async"
                 aria-hidden="true">
            <?php else: ?>
            <div class="absolute inset-0 bg-gradient-to-br from-slate-800 to-slate-900" aria-hidden="true"></div>
            <?php endif; ?>
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/95 via-slate-900/78 to-slate-900/52 th-country-hero__overlay" aria-hidden="true"></div>
        </div>
        <div class="th-container mx-auto px-6 relative z-10 w-full">
            <div class="max-w-4xl mx-auto text-center space-y-5">
                <div class="inline-flex items-center gap-3 mb-1 th-country-hero__badge-row">
                    <?php /* Один код страны (ISO): эмодзи-флаг на Windows/Edge рисуется как второй «ID» */ ?>
                    <div class="th-country-hero__iso" aria-label="<?php echo $codeMobile; ?>">
                        <span class="th-country-hero__iso-code"><?php echo $codeMobile; ?></span>
                    </div>
                </div>
                <h1 class="heading-font text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-bold text-white leading-tight drop-shadow-md">
                    <?php echo htmlspecialchars($countryData['name']); ?>
                </h1>
                <?php if (!empty($countryData['description'])): ?>
                <p class="text-lg md:text-xl text-white/90 max-w-3xl mx-auto leading-relaxed drop-shadow">
                    <?php echo htmlspecialchars($countryData['description']); ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/country_promo_tours.php'; ?>

    <?php if (($countryData['slug'] ?? '') === 'turkey'): ?>
    <section class="py-8 md:py-10 bg-gradient-to-br from-amber-50/80 via-white to-indigo-50/60 border-b border-amber-100/80" id="turkey-vip-hotels-banner" aria-label="VIP отели Турции">
        <div class="th-container mx-auto px-6">
            <div class="max-w-5xl mx-auto flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6 rounded-2xl border border-white/80 bg-white/95 p-6 md:p-8 shadow-sm ring-1 ring-slate-100">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wider text-amber-700 mb-1.5">Премиум</p>
                    <h2 class="heading-font text-2xl md:text-3xl font-bold text-slate-900">VIP отели Турции</h2>
                    <p class="text-slate-600 mt-2 text-sm md:text-base leading-relaxed">Подборка отелей Антальи, Белека и Кемера. Описания и фото — из каталога; <strong class="text-slate-800">цена «от»</strong> подгружается для каждого отеля отдельно из поиска Tourvisor.</p>
                </div>
                <a href="/frontend/window/turkey-vip-hotels.php"
                   class="inline-flex items-center justify-center gap-2 shrink-0 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 text-white px-6 py-3.5 font-semibold shadow-md shadow-amber-500/25 hover:opacity-95 transition min-h-[48px]">
                    <i class="fas fa-hotel" aria-hidden="true"></i>
                    Смотреть VIP отели
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($countryData['images']) && is_array($countryData['images'])): ?>
    <!-- Фотогалерея -->
    <section class="py-12 md:py-14 bg-white border-b border-slate-100" id="country-photo-gallery" aria-label="Фотогалерея">
        <div class="th-container mx-auto px-6">
            <div class="max-w-6xl mx-auto">
                <h2 class="heading-font text-2xl md:text-3xl font-bold text-slate-900 mb-2 flex items-center gap-3">
                    <i class="fas fa-images text-sky-500" aria-hidden="true"></i>
                    Фотогалерея <?php echo htmlspecialchars($countryData['name'], ENT_QUOTES, 'UTF-8'); ?>
                </h2>
                <p class="text-slate-600 text-sm md:text-base mb-6">Нажмите на фото, чтобы открыть полноэкранный просмотр</p>
                <div class="overflow-x-auto pb-4 -mx-2 px-2 md:mx-0 md:px-0">
                    <div class="flex gap-4 min-w-max md:min-w-0 md:flex-wrap">
                        <?php foreach ($countryData['images'] as $index => $image): ?>
                        <?php $imgSrc = th_country_page_image_src((string) $image); ?>
                        <div class="flex-shrink-0 w-[280px] sm:w-80 h-56 sm:h-64 rounded-2xl overflow-hidden shadow-lg relative cursor-pointer gallery-image group"
                             data-image="<?php echo $imgSrc !== '' ? $imgSrc : 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800'; ?>"
                             data-index="<?php echo (int) $index; ?>">
                            <img src="<?php echo $imgSrc !== '' ? $imgSrc : 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800'; ?>"
                                 alt="<?php echo htmlspecialchars($countryData['name'] . ' — фото ' . ($index + 1), ENT_QUOTES, 'UTF-8'); ?>"
                                 class="w-full h-full object-cover group-hover:scale-105 transition duration-500"
                                 loading="lazy"
                                 decoding="async"
                                 onerror="this.src='https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800'">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/55 to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-3">
                                <span class="text-white text-xs font-medium">Фото <?php echo (int) $index + 1; ?> из <?php echo count($countryData['images']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="gallery-lightbox" class="fixed inset-0 bg-black/90 z-[60] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Просмотр фото">
                    <button type="button" class="absolute top-4 right-4 text-white hover:text-sky-300 transition text-3xl z-10" id="close-lightbox" aria-label="Закрыть">
                        <i class="fas fa-times"></i>
                    </button>
                    <button type="button" class="absolute left-2 sm:left-4 top-1/2 -translate-y-1/2 text-white hover:text-sky-300 transition text-2xl sm:text-3xl z-10" id="prev-image" aria-label="Предыдущее фото">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button type="button" class="absolute right-2 sm:right-4 top-1/2 -translate-y-1/2 text-white hover:text-sky-300 transition text-2xl sm:text-3xl z-10" id="next-image" aria-label="Следующее фото">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <div class="max-w-7xl w-full h-full flex items-center justify-center pt-10 pb-14">
                        <img id="lightbox-image" src="" alt="" class="max-w-full max-h-[85vh] object-contain rounded-lg">
                    </div>
                    <div class="absolute bottom-4 left-1/2 -translate-x-1/2 text-white/90 text-sm" id="image-counter"></div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- CTA после галереи: акционный поиск (страница акций), офисы, обратная связь -->
    <section class="py-8 md:py-10 bg-slate-50/80 border-b border-slate-100" aria-label="Действия">
        <div class="th-container mx-auto px-6">
            <div class="max-w-xl mx-auto flex flex-col items-stretch gap-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <a href="<?php echo htmlspecialchars($country_promo_listing_url, ENT_QUOTES, 'UTF-8'); ?>"
                       data-th-promo-cta="1"
                       data-th-country-slug="<?php echo htmlspecialchars((string) $countrySlug, ENT_QUOTES, 'UTF-8'); ?>"
                       data-th-country-name="<?php echo htmlspecialchars((string) ($countryData['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                       class="inline-flex items-center justify-center gap-2 rounded-2xl px-5 py-3.5 font-semibold text-white shadow-lg shadow-sky-500/25 transition hover:opacity-95 hover:shadow-xl min-h-[52px]"
                       style="background: linear-gradient(135deg, #0c4a6e 0%, #366360 45%, #5DA9A4 100%);">
                        <i class="fas fa-search" aria-hidden="true"></i>
                        Поиск туров
                    </a>
                    <a href="/frontend/window/offices.php"
                       class="inline-flex items-center justify-center gap-2 rounded-2xl border-2 border-sky-200 bg-white px-5 py-3.5 font-semibold text-slate-800 shadow-sm transition hover:border-sky-400 hover:text-sky-800 min-h-[52px]">
                        <i class="fas fa-building text-sky-700" aria-hidden="true"></i>
                        Наши офисы
                    </a>
                </div>
                <button type="button" data-th-site-feedback
                   class="inline-flex items-center justify-center gap-2 rounded-2xl border-2 border-sky-200 bg-white px-5 py-3.5 font-semibold text-slate-800 shadow-sm transition hover:border-sky-400 hover:text-sky-800 min-h-[52px] sm:max-w-md sm:mx-auto sm:w-full cursor-pointer">
                    <i class="fas fa-envelope text-sky-700" aria-hidden="true"></i>
                    Форма обратной связи
                </button>
            </div>
        </div>
    </section>

    <script>
    (function () {
        'use strict';
        function safeLsGet(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
        function buildUrl(baseHref, depId, depName) {
            try {
                var u = new URL(baseHref, window.location.origin);
                if (depId) u.searchParams.set('departureId', String(depId));
                if (depName) u.searchParams.set('departureName', String(depName));
                return u.pathname + (u.search ? u.search : '') + (u.hash ? u.hash : '');
            } catch (e) {
                return baseHref;
            }
        }
        function apply() {
            var a = document.querySelector('a[data-th-promo-cta="1"]');
            if (!a) return;
            var depId = safeLsGet('th_departure_id');
            var depName = safeLsGet('th_departure_name');
            if (!depId || !depName) return;
            var baseHref = a.getAttribute('href') || '';
            if (!baseHref) return;
            a.setAttribute('href', buildUrl(baseHref, depId, depName));
        }
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', apply);
        else apply();
        window.addEventListener('th-departure-saved', function () { apply(); });
    })();
    </script>

    <!-- Поиск туров по стране -->
    <?php include __DIR__ . '/country_tour_search.php'; ?>
