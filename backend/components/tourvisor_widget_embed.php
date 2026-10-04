<?php
declare(strict_types=1);
/**
 * Виджет Tourvisor (tourvisor.ru/module/init.js).
 * Перед include задайте:
 *   $tourvisor_widget_module — например search | minprice | calendar (как в кабинете Tourvisor)
 *   $tourvisor_widget_container_id — уникальный id контейнера (по умолчанию tourvisor-search)
 * Опционально: $tourvisor_widget_id — числовой ID модуля; иначе TOURVISOR_WIDGET_ID из .env или 9974456.
 */
$tvMod = isset($tourvisor_widget_module) ? (string) $tourvisor_widget_module : 'search';
$tvCont = isset($tourvisor_widget_container_id) ? (string) $tourvisor_widget_container_id : 'tourvisor-search';
$wid = trim((string) (getenv('TOURVISOR_WIDGET_ID') ?: ($_ENV['TOURVISOR_WIDGET_ID'] ?? '')));
if ($wid === '') {
    $wid = isset($tourvisor_widget_id) ? (string) $tourvisor_widget_id : '9974456';
}
$initJs = 'https://tourvisor.ru/module/init.js?id=' . rawurlencode($wid);
if (!defined('TRAVELHUB_TOURVISOR_INIT_LOADED')) {
    define('TRAVELHUB_TOURVISOR_INIT_LOADED', true);
    echo '<script async src="' . htmlspecialchars($initJs, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
}
$wrapClass = isset($tourvisor_widget_wrap_class) ? (string) $tourvisor_widget_wrap_class : 'tv-widget-wrap w-full min-h-[400px]';
?>
<div id="<?php echo htmlspecialchars($tvCont, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($wrapClass, ENT_QUOTES, 'UTF-8'); ?>"></div>
<script>
(function(){
  var c = <?php echo json_encode($tvCont, JSON_UNESCAPED_UNICODE); ?>;
  var m = <?php echo json_encode($tvMod, JSON_UNESCAPED_UNICODE); ?>;
  function readSavedDepartureId() {
    try {
      var raw = localStorage.getItem('th_departure_id');
      if (!raw) return null;
      var n = parseInt(String(raw), 10);
      return (!isNaN(n) && n > 0) ? n : null;
    } catch (e) { return null; }
  }
  function run() {
    var api = typeof TourvisorSearch !== 'undefined' ? TourvisorSearch : (typeof TourvisorWidget !== 'undefined' ? TourvisorWidget : null);
    if (!api || typeof api.init !== 'function') { setTimeout(run, 100); return; }
    try {
      var depId = readSavedDepartureId();
      var opts = { module: m, container: c, lang: 'ru' };
      if (depId != null) {
        opts.departure = depId;
        opts.tvDeparture = depId;
      }
      api.init(opts);
    } catch (e) { console.warn('Tourvisor', e); }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
})();
</script>
