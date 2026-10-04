<?php
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/contacts.php';
require_once __DIR__ . '/../../../backend/config/offices_catalog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$thc = th_contacts();
$offices = th_offices_by_city('moscow');
$page_title = 'Офисы в Москве';
$current_page = 'offices';
$heroCover = $offices[0]['cover'] ?? '/frontend/window/img/hero/e978c0767c0fe7bc778596c86b2b54f3%201.png';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title); ?> — Travel Hub</title>
    <meta name="description" content="Офисы Travel Hub в Москве: Coral Elite и Anex Tour на Первомайской. Фото, телефоны, заявка за минуту.">
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/frontend/css/th-offices.css?v=4">
    <?php include __DIR__ . '/../../../backend/components/design_system_head.php'; ?>
    <script src="https://api-maps.yandex.ru/2.1/?lang=ru_RU" type="text/javascript"></script>
</head>
<body class="th-off-page text-slate-900">
    <?php include __DIR__ . '/../../../backend/components/header.php'; ?>

    <header class="th-off-hero">
        <img class="th-off-hero__bg" src="<?php echo htmlspecialchars($heroCover, ENT_QUOTES, 'UTF-8'); ?>" alt="Офисы Travel Hub в Москве" loading="eager">
        <div class="th-off-hero__shade" aria-hidden="true"></div>
        <div class="th-off-hero__inner">
            <p class="th-off-hero__brand">Москва</p>
            <p class="th-off-hero__lead">Офисы на Первомайской, 42 — фото и контакты сразу. Позвоните или оставьте заявку.</p>
            <div class="th-off-hero__cta">
                <a class="th-off-hero__cta-primary" href="#th-moscow-cta-form">Оставить заявку</a>
                <a class="th-off-hero__cta-ghost" href="tel:+74993220297"><i class="fas fa-phone" aria-hidden="true"></i> +7 (499) 322-02-97</a>
                <a class="th-off-hero__cta-ghost" href="/frontend/window/offices.php">Все города</a>
            </div>
        </div>
    </header>

    <main class="th-off-wrap">
        <div class="th-off-grid" style="max-width:48rem;margin:0 auto;">
            <?php foreach ($offices as $i => $o):
                $maxHref = $thc['max_url'];
            ?>
            <article class="th-off-card" style="animation-delay:<?php echo min(0.08 * $i, 0.4); ?>s">
                <a class="th-off-card__media" href="<?php echo htmlspecialchars($o['page_url'], ENT_QUOTES, 'UTF-8'); ?>">
                    <img src="<?php echo htmlspecialchars($o['cover'], ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($o['name'], ENT_QUOTES, 'UTF-8'); ?>"
                         loading="<?php echo $i < 2 ? 'eager' : 'lazy'; ?>">
                    <span class="th-off-card__badge"><?php echo htmlspecialchars($o['brand'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
                <div class="th-off-card__body">
                    <h2 class="th-off-card__name"><a href="<?php echo htmlspecialchars($o['page_url'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($o['name'], ENT_QUOTES, 'UTF-8'); ?></a></h2>
                    <p class="th-off-card__meta"><i class="fas fa-map-marker-alt" aria-hidden="true"></i><?php echo htmlspecialchars($o['address'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="th-off-card__meta"><i class="fas fa-clock" aria-hidden="true"></i><?php echo htmlspecialchars($o['hours'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="th-off-card__blurb"><?php echo htmlspecialchars($o['blurb'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="th-off-card__actions">
                        <a class="th-off-card__call" href="tel:<?php echo htmlspecialchars($o['phone_tel'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-phone" aria-hidden="true"></i> Позвонить</a>
                        <a class="th-off-card__wa th-off-card__max" href="<?php echo htmlspecialchars($maxHref, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">MAX</a>
                        <button type="button" class="th-off-card__lead" data-th-office-lead-btn="1" data-office-city="Москва" data-office-name="<?php echo htmlspecialchars($o['name'], ENT_QUOTES, 'UTF-8'); ?>">Заявка в этот офис</button>
                        <a class="th-off-card__more" href="<?php echo htmlspecialchars($o['page_url'], ENT_QUOTES, 'UTF-8'); ?>">Фото и детали <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <section class="mt-10 rounded-2xl overflow-hidden border border-slate-200 bg-white shadow-sm">
            <div class="px-4 py-4 sm:px-6 border-b border-slate-100 text-center sm:text-left">
                <h2 class="heading-font text-xl sm:text-2xl font-bold text-slate-900 m-0">Расположение офисов на карте</h2>
                <p class="text-slate-600 mt-1 mb-0">Яндекс.Карта — офисы на Первомайской, 42</p>
            </div>
            <div id="moscow-offices-map" class="w-full" style="height:450px;background:#e2e8f0;"></div>
        </section>
    </main>

    <?php
    $th_cta_source = 'offices_moscow';
    $th_cta_title = 'В Москве или онлайн — подберём тур одинаково быстро';
    $th_cta_sub = 'Оставьте телефон: перезвоним и пришлём варианты под ваши даты.';
    $th_cta_id = 'th-moscow-cta';
    include __DIR__ . '/../../../backend/components/page_cta_band.php';
    ?>

    <?php include __DIR__ . '/../../../backend/components/footer.php'; ?>
    <script src="/frontend/js/office-lead-modal.js?v=5" defer></script>
    <script>
    (function () {
      if (typeof ymaps === 'undefined') return;
      ymaps.ready(function () {
        function esc(s) {
          if (s == null) return '';
          return String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }
        var map = new ymaps.Map('moscow-offices-map', {
          center: [55.7558, 37.6173],
          zoom: 11,
          controls: ['zoomControl', 'fullscreenControl']
        });
        var officesData = <?php echo json_encode(array_map(static function ($o) {
            return [
                'name' => $o['name'],
                'lat' => $o['lat'] ?? 55.794376,
                'lon' => $o['lon'] ?? 37.798078,
                'address' => $o['address'],
                'phone' => $o['phone'],
                'page_url' => $o['page_url'],
            ];
        }, $offices), JSON_UNESCAPED_UNICODE); ?>;
        officesData.forEach(function (o) {
          var phone = (o.phone || '').replace(/\D/g, '');
          var html = '<div style="padding:4px 0;min-width:200px">' +
            '<strong>' + esc(o.name) + '</strong><br>' +
            esc(o.address) + '<br>' +
            (phone ? '<a href="tel:' + esc(phone) + '">' + esc(o.phone) + '</a><br>' : '') +
            '<a href="' + esc(o.page_url) + '" target="_blank" rel="noopener">Подробнее</a></div>';
          map.geoObjects.add(new ymaps.Placemark([o.lat, o.lon], {
            balloonContent: html,
            hintContent: o.name
          }, { preset: 'islands#goldCircleIcon' }));
        });
        if (officesData.length) {
          try {
            map.setCenter([officesData[0].lat, officesData[0].lon], 15);
          } catch (e) {}
        }
      });
    })();
    </script>
</body>
</html>
