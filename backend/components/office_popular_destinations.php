<?php
/**
 * Блок «Популярные направления» для страниц офисов (Москва / Самара).
 * Подключается после стилей .tourvisor-widget на странице офиса.
 */
if (!isset($officePopularDestinations)) {
    $rows = require dirname(__DIR__) . '/config/site_popular_destinations_cards.php';
    $officePopularDestinations = array_map(static function (array $r): array {
        return [
            'name' => (string) ($r['name'] ?? ''),
            'href' => (string) ($r['href'] ?? ''),
            'image' => (string) ($r['image'] ?? ''),
        ];
    }, is_array($rows) ? $rows : []);
}
?>
<section class="mb-12 office-popular-destinations w-full max-w-full min-w-0 box-border" aria-labelledby="office-popular-dest-heading">
    <div class="tourvisor-widget office-popular-destinations__inner w-full max-w-full min-w-0 box-border">
        <div class="text-center mb-6 sm:mb-8">
            <h2 id="office-popular-dest-heading" class="heading-font text-2xl font-bold text-slate-900 mb-2 sm:mb-3">Популярные направления</h2>
            <p class="text-slate-600 text-sm sm:text-base max-w-2xl mx-auto px-1">Выберите страну и получите подборку лучших туров от наших менеджеров</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5 lg:gap-6 min-w-0 w-full">
            <?php foreach ($officePopularDestinations as $destination): ?>
            <a href="<?php echo htmlspecialchars($destination['href'], ENT_QUOTES, 'UTF-8'); ?>" class="office-popular-card group flex flex-col sm:flex-row sm:items-stretch w-full max-w-full min-w-0 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm transition hover:shadow-md hover:border-sky-200/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400 focus-visible:ring-offset-0 sm:focus-visible:ring-offset-2">
                <div class="relative aspect-[16/10] w-full sm:w-[44%] sm:min-h-[7.5rem] sm:aspect-auto overflow-hidden bg-slate-100 shrink-0 min-w-0">
                    <img src="<?php echo htmlspecialchars($destination['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($destination['name'], ENT_QUOTES, 'UTF-8'); ?>" class="office-popular-card__img h-full w-full max-w-full object-cover transition duration-300 group-hover:scale-[1.04]" loading="lazy" decoding="async">
                </div>
                <div class="flex flex-1 min-w-0 items-center justify-between gap-3 p-4 sm:p-5">
                    <span class="heading-font text-lg sm:text-xl font-bold text-slate-900 min-w-0 flex-1 text-left break-words"><?php echo htmlspecialchars($destination['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-600 transition group-hover:bg-sky-200" aria-hidden="true">
                        <i class="fas fa-arrow-right text-sm"></i>
                    </span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
