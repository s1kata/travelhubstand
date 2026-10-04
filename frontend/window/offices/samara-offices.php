<?php
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/contacts.php';
require_once __DIR__ . '/../../../backend/config/offices_catalog.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$thc = th_contacts();
$offices = th_offices_by_city('samara');
$page_title = 'Офисы в Самаре';
$current_page = 'offices';
$heroCover = $offices[0]['cover'] ?? '/frontend/window/img/hero/e978c0767c0fe7bc778596c86b2b54f3%201.png';
require_once __DIR__ . '/../../../backend/config/maps.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title); ?> — Travel Hub</title>
    <meta name="description" content="Офисы Travel Hub в Самаре с фото, адресами и телефонами. Fun&Sun, Anex Tour, Coral Travel.">
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/frontend/css/th-offices.css?v=4">
    <?php include __DIR__ . '/../../../backend/components/design_system_head.php'; ?>
    <script src="<?php echo htmlspecialchars(th_maps()['api_js'], ENT_QUOTES, 'UTF-8'); ?>" type="text/javascript"></script>
</head>
<body class="th-off-page text-slate-900">
    <?php include __DIR__ . '/../../../backend/components/header.php'; ?>

    <header class="th-off-hero">
        <img class="th-off-hero__bg" src="<?php echo htmlspecialchars($heroCover, ENT_QUOTES, 'UTF-8'); ?>" alt="Офисы Travel Hub в Самаре" loading="eager">
        <div class="th-off-hero__shade" aria-hidden="true"></div>
        <div class="th-off-hero__inner">
            <p class="th-off-hero__brand">Самара</p>
            <p class="th-off-hero__lead"><?php echo count($offices); ?> офиса Travel Hub — фото, адреса и телефоны сразу. Заявка или звонок — ответ за 15 минут.</p>
            <div class="th-off-hero__cta">
                <a class="th-off-hero__cta-primary" href="#th-samara-cta-form">Оставить заявку</a>
                <a class="th-off-hero__cta-ghost" href="tel:<?php echo htmlspecialchars($thc['phone_tel'], ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-phone" aria-hidden="true"></i>
                    <?php echo htmlspecialchars($thc['phone_display'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
                <a class="th-off-hero__cta-ghost" href="/frontend/window/offices.php">Все города</a>
            </div>
        </div>
    </header>

    <main class="th-off-wrap">
        <div class="th-off-grid">
            <?php foreach ($offices as $i => $o):
                $maxHref = $thc['max_url'];
            ?>
            <article class="th-off-card" style="animation-delay:<?php echo min(0.08 * $i, 0.4); ?>s">
                <a class="th-off-card__media" href="<?php echo htmlspecialchars($o['page_url'], ENT_QUOTES, 'UTF-8'); ?>">
                    <img src="<?php echo htmlspecialchars($o['cover'], ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($o['name'], ENT_QUOTES, 'UTF-8'); ?>"
                         loading="<?php echo $i < 2 ? 'eager' : 'lazy'; ?>">
                    <span class="th-off-card__badge"><?php echo htmlspecialchars($o['brand'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php if (!empty($o['gallery']) && count($o['gallery']) > 1): ?>
                    <div class="th-off-thumbs" aria-hidden="true">
                        <?php foreach (array_slice($o['gallery'], 0, 4) as $_): ?><span></span><?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </a>
                <div class="th-off-card__body">
                    <h2 class="th-off-card__name"><a href="<?php echo htmlspecialchars($o['page_url'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($o['name'], ENT_QUOTES, 'UTF-8'); ?></a></h2>
                    <p class="th-off-card__meta"><i class="fas fa-map-marker-alt" aria-hidden="true"></i><?php echo htmlspecialchars($o['address'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="th-off-card__meta"><i class="fas fa-clock" aria-hidden="true"></i><?php echo htmlspecialchars($o['hours'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="th-off-card__blurb"><?php echo htmlspecialchars($o['blurb'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="th-off-card__actions">
                        <a class="th-off-card__call" href="tel:<?php echo htmlspecialchars($o['phone_tel'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-phone" aria-hidden="true"></i> Позвонить</a>
                        <a class="th-off-card__wa th-off-card__max" href="<?php echo htmlspecialchars($maxHref, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">MAX</a>
                        <button type="button" class="th-off-card__lead" data-th-office-lead-btn="1" data-office-city="Самара" data-office-name="<?php echo htmlspecialchars($o['name'], ENT_QUOTES, 'UTF-8'); ?>">Заявка в этот офис</button>
                        <a class="th-off-card__more" href="<?php echo htmlspecialchars($o['page_url'], ENT_QUOTES, 'UTF-8'); ?>">Фото и детали <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <?php
        $yandex_offices_map_id = 'samara-offices-map';
        $yandex_offices_map_points = th_offices_map_points('samara');
        $yandex_offices_map_title = 'Расположение офисов на карте';
        $yandex_offices_map_subtitle = 'Интерактивная карта Яндекс — все офисы в Самаре';
        $yandex_offices_map_center = [53.2335, 50.2010];
        $yandex_offices_map_zoom = 12;
        include __DIR__ . '/../../../backend/components/yandex_offices_map.php';
        ?>
    </main>

    <?php
    $th_cta_source = 'offices_samara';
    $th_cta_title = 'Приедете в офис или подберём тур удалённо?';
    $th_cta_sub = 'Оставьте телефон — скажем, какой офис ближе, и пришлём варианты туров.';
    $th_cta_id = 'th-samara-cta';
    include __DIR__ . '/../../../backend/components/page_cta_band.php';
    ?>

    <?php include __DIR__ . '/../../../backend/components/footer.php'; ?>
    <script src="/frontend/js/office-lead-modal.js?v=5" defer></script>
</body>
</html>
