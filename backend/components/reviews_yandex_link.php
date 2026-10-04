<?php
/**
 * Виджет отзывов Яндекс.Карт.
 * Перед include: $reviews_yandex_org_id (цифры), опционально $reviews_yandex_query для ссылки-запасного варианта.
 * В .env: YANDEX_REVIEWS_ORG_ID — числовой ID организации из карточки на Яндекс.Картах (обязательно для iframe).
 */
$fallbackQuery = isset($reviews_yandex_query) ? trim((string) $reviews_yandex_query) : 'Travel Hub Москва';

$orgId = '';
if (isset($reviews_yandex_org_id) && (string) $reviews_yandex_org_id !== '') {
    $orgId = preg_replace('/[^0-9]/', '', (string) $reviews_yandex_org_id);
}
if ($orgId === '') {
    $orgId = preg_replace('/[^0-9]/', '', (string) (getenv('YANDEX_REVIEWS_ORG_ID') ?: ($_ENV['YANDEX_REVIEWS_ORG_ID'] ?? '')));
}
if ($orgId === '' && isset($office) && is_array($office) && !empty($office['yandex_org_id'])) {
    $orgId = preg_replace('/[^0-9]/', '', (string) $office['yandex_org_id']);
}

$mapsSearchUrl = 'https://yandex.ru/maps/?text=' . rawurlencode($fallbackQuery);

if ($orgId === '' || $orgId === '1234567890') {
    ?>
    <div class="yandex-reviews-fallback rounded-xl border border-slate-200 bg-slate-50/80 p-8 text-center">
        <p class="text-slate-600 text-sm mb-4 max-w-lg mx-auto leading-relaxed">Виджет отзывов подключается по ID организации на Яндекс.Картах. Пока можно открыть отзывы по ссылке.</p>
        <a href="<?php echo htmlspecialchars($mapsSearchUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-amber-500 text-white font-semibold hover:bg-amber-600 shadow-md">Отзывы на Яндекс.Картах</a>
    </div>
    <?php
    return;
}

$reviewsIframeSrc = 'https://yandex.ru/maps-reviews-widget/' . $orgId . '?comments';
?>
<div class="w-full office-reviews-widget yandex-reviews-wrap min-w-0" style="min-height: 520px;">
    <iframe
        src="<?php echo htmlspecialchars($reviewsIframeSrc, ENT_QUOTES, 'UTF-8'); ?>"
        width="100%"
        height="520"
        frameborder="0"
        loading="eager"
        style="border:0;border-radius:12px;min-height:520px;width:100%;max-width:100%;display:block;background:#f8fafc;"
        class="yandex-reviews-iframe"
        title="Отзывы на Яндекс.Картах"
        referrerpolicy="strict-origin-when-cross-origin"
        allowfullscreen
    ></iframe>
    <p class="mt-4 text-center text-sm text-slate-500">
        <a href="<?php echo htmlspecialchars($mapsSearchUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:text-indigo-800 font-medium">Все отзывы на Яндекс.Картах →</a>
    </p>
</div>
