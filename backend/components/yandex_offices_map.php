<?php
/**
 * Интерактивная карта Яндекс с несколькими офисами.
 *
 * Перед include задайте:
 *   $yandex_offices_map_id      — id контейнера (уникальный на странице)
 *   $yandex_offices_map_points  — массив точек (name, lat, lon, geo, address, phone, page_url)
 *
 * Необязательно:
 *   $yandex_offices_map_title
 *   $yandex_offices_map_subtitle
 *   $yandex_offices_map_preset
 *   $yandex_offices_map_height
 *   $yandex_offices_map_center   — [lat, lon]
 *   $yandex_offices_map_zoom
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/maps.php';

$mapId = isset($yandex_offices_map_id) && is_string($yandex_offices_map_id) && $yandex_offices_map_id !== ''
    ? preg_replace('/[^a-zA-Z0-9_-]/', '', $yandex_offices_map_id)
    : 'yandex-offices-map';
$mapPoints = isset($yandex_offices_map_points) && is_array($yandex_offices_map_points)
    ? $yandex_offices_map_points
    : [];
$mapTitle = isset($yandex_offices_map_title) ? (string) $yandex_offices_map_title : 'Расположение офисов на карте';
$mapSubtitle = isset($yandex_offices_map_subtitle) ? (string) $yandex_offices_map_subtitle : '';
$mapPreset = isset($yandex_offices_map_preset) && is_string($yandex_offices_map_preset) && $yandex_offices_map_preset !== ''
    ? $yandex_offices_map_preset
    : 'islands#blueCircleIcon';
$mapHeight = isset($yandex_offices_map_height) && is_string($yandex_offices_map_height) && $yandex_offices_map_height !== ''
    ? $yandex_offices_map_height
    : '450px';
$mapCenter = isset($yandex_offices_map_center) && is_array($yandex_offices_map_center) && count($yandex_offices_map_center) === 2
    ? [(float) $yandex_offices_map_center[0], (float) $yandex_offices_map_center[1]]
    : [53.2335, 50.2010];
$mapZoom = isset($yandex_offices_map_zoom) ? (int) $yandex_offices_map_zoom : 12;
$mapJson = json_encode($mapPoints, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($mapJson === false) {
    $mapJson = '[]';
}
$apiJs = th_maps()['api_js'];
?>
<section class="th-off-map mt-10 rounded-2xl overflow-hidden border border-slate-200 bg-white shadow-sm">
    <div class="px-4 py-4 sm:px-6 border-b border-slate-100 text-center sm:text-left">
        <h2 class="heading-font text-xl sm:text-2xl font-bold text-slate-900 m-0"><?php echo htmlspecialchars($mapTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
        <?php if ($mapSubtitle !== ''): ?>
        <p class="text-slate-600 mt-1 mb-0"><?php echo $mapSubtitle; ?></p>
        <?php endif; ?>
    </div>
    <div id="<?php echo htmlspecialchars($mapId, ENT_QUOTES, 'UTF-8'); ?>"
         class="th-off-map__canvas w-full"
         style="height:<?php echo htmlspecialchars($mapHeight, ENT_QUOTES, 'UTF-8'); ?>;min-height:400px;background:#e2e8f0;"
         role="region"
         aria-label="<?php echo htmlspecialchars($mapTitle, ENT_QUOTES, 'UTF-8'); ?>"></div>
</section>
<script>
(function () {
  var mapId = <?php echo json_encode($mapId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  var officesData = <?php echo $mapJson; ?>;
  var mapCenter = <?php echo json_encode($mapCenter); ?>;
  var mapZoom = <?php echo (int) $mapZoom; ?>;
  var preset = <?php echo json_encode($mapPreset, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  var apiSrc = <?php echo json_encode($apiJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

  function esc(s) {
    if (s == null) return '';
    return String(s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function boot(ymaps) {
    var el = document.getElementById(mapId);
    if (!el || !ymaps) return;

    ymaps.ready(function () {
      var map = new ymaps.Map(mapId, {
        center: mapCenter,
        zoom: mapZoom,
        controls: ['zoomControl', 'fullscreenControl']
      });
      var collection = new ymaps.GeoObjectCollection();
      map.geoObjects.add(collection);

      function addPlacemark(lat, lon, o) {
        var phone = (o.phone || '').replace(/[^\d+]/g, '');
        var html = '<div style="padding:4px 0;min-width:220px;max-width:280px;line-height:1.45">' +
          '<strong>' + esc(o.name) + '</strong><br>' +
          esc(o.address) + '<br>' +
          (phone ? '<a href="tel:' + esc(phone) + '">' + esc(o.phone) + '</a><br>' : '') +
          (o.page_url ? '<a href="' + esc(o.page_url) + '">Подробнее об офисе</a>' : '') +
          '</div>';
        collection.add(new ymaps.Placemark([lat, lon], {
          balloonContent: html,
          hintContent: o.name
        }, { preset: preset }));
      }

      function fitBounds() {
        if (collection.getLength() > 1) {
          try {
            map.setBounds(collection.getBounds(), { checkZoomRange: true, zoomMargin: 48 });
          } catch (e) {}
        } else if (collection.getLength() === 1) {
          try {
            var c = collection.get(0).geometry.getCoordinates();
            map.setCenter(c, 15);
          } catch (e2) {}
        }
      }

      var pending = 0;
      function doneGeocode() {
        pending--;
        if (pending <= 0) fitBounds();
      }

      officesData.forEach(function (o) {
        var lat = parseFloat(o.lat);
        var lon = parseFloat(o.lon);
        if (!isNaN(lat) && !isNaN(lon) && !(lat === 0 && lon === 0)) {
          addPlacemark(lat, lon, o);
          return;
        }
        if (!o.geo) return;
        pending++;
        ymaps.geocode('Россия, ' + o.geo, { results: 1 }).then(function (res) {
          var first = res.geoObjects.get(0);
          if (first) {
            var coords = first.geometry.getCoordinates();
            addPlacemark(coords[0], coords[1], o);
          }
          doneGeocode();
        }, function () { doneGeocode(); });
      });

      if (pending === 0) fitBounds();
    });
  }

  function ensureYmaps(cb) {
    if (typeof ymaps !== 'undefined') {
      cb(ymaps);
      return;
    }
    var existing = document.querySelector('script[data-th-ymaps]');
    if (existing) {
      existing.addEventListener('load', function () { cb(window.ymaps); });
      return;
    }
    var s = document.createElement('script');
    s.src = apiSrc;
    s.async = true;
    s.setAttribute('data-th-ymaps', '1');
    s.onload = function () { cb(window.ymaps); };
    document.head.appendChild(s);
  }

  ensureYmaps(boot);
})();
</script>
