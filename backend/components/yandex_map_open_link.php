<?php
/**
 * Блок Яндекс.Карт (iframe-виджет).
 * До include: $yandex_map_open_url (полный URL карты).
 */
require_once dirname(__DIR__) . '/config/maps.php';
$url = isset($yandex_map_open_url) && is_string($yandex_map_open_url) && trim($yandex_map_open_url) !== ''
    ? trim($yandex_map_open_url)
    : th_maps()['widget_default'];
?>
<iframe class="w-full h-96 sm:h-80 md:h-[28rem] border-0 rounded-2xl"
    src="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
    frameborder="0"
    allowfullscreen
    loading="lazy"
    referrerpolicy="no-referrer-when-downgrade"
    title="Карта на Яндексе"></iframe>
