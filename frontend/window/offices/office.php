<?php
declare(strict_types=1);
/**
 * Единая подробная страница офиса: /frontend/window/offices/office.php?slug=samara-funsun
 */

require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/contacts.php';
require_once __DIR__ . '/../../../backend/config/maps.php';
require_once __DIR__ . '/../../../backend/config/offices_catalog.php';
require_once __DIR__ . '/../../../backend/components/employee_photo_url.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
$office = $slug !== '' ? th_office_by_slug($slug) : null;

if (!$office) {
    http_response_code(404);
    header('Location: /frontend/window/offices.php');
    exit;
}

$thc = th_contacts();
$current_page = 'offices';
$page_title = $office['name'] . ' — ' . $office['city_name'];
$cityListUrl = $office['city'] === 'moscow'
    ? '/frontend/window/offices/moscow-offices.php'
    : '/frontend/window/offices/samara-offices.php';

$employees = [];
if (!empty($pdo)) {
    try {
        $stmt = $pdo->prepare('SELECT id FROM offices WHERE name = ? AND city = ? LIMIT 1');
        $stmt->execute([(string) $office['db_name'], (string) $office['city']]);
        $officeId = $stmt->fetchColumn();
        if ($officeId) {
            $stmt = $pdo->prepare('SELECT id, office_id, name, position, phone, email, photo, info FROM office_employees WHERE office_id = ? ORDER BY name');
            $stmt->execute([(int) $officeId]);
            $employees = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (Throwable $e) {
        error_log('[office.php] employees: ' . $e->getMessage());
    }
}

// Fun&Sun Парк Хаус — актуальные портреты команды
if ($slug === 'samara-funsun') {
    $team = [
        'Новикова Соня' => '/img/employees/samara/novikova-sonya.png',
        'Пермякова Елизавета' => '/img/employees/samara/permyakova-elizaveta.png',
        'Быкова Виктория' => '/img/employees/samara/bykova-viktoria.png',
    ];
    foreach ($employees as &$emp) {
        $n = trim((string) ($emp['name'] ?? ''));
        if ($n !== '' && isset($team[$n])) {
            $emp['photo'] = $team[$n];
        }
    }
    unset($emp);
    if (empty($employees)) {
        foreach ($team as $name => $photo) {
            $employees[] = ['name' => $name, 'position' => 'Менеджер', 'phone' => '', 'email' => '', 'photo' => $photo, 'info' => ''];
        }
    }
}

$photos = $office['photos'] ?? [];
$cover = $office['cover'] ?? '';
$maxHref = $thc['max_url'];
$mapQuery = rawurlencode((string) ($office['geo'] ?? $office['address']));
$yandex_map_open_url = th_map_widget_url_for_geo((string) ($office['geo'] ?? $office['address']));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?> | Travel Hub</title>
    <meta name="description" content="<?php echo htmlspecialchars($office['description'], ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/frontend/css/th-offices.css?v=4">
    <?php include __DIR__ . '/../../../backend/components/design_system_head.php'; ?>
</head>
<body class="th-off-page text-slate-900">
    <?php include __DIR__ . '/../../../backend/components/header.php'; ?>

    <header class="th-off-hero th-off-hero--detail">
        <img class="th-off-hero__bg" src="<?php echo htmlspecialchars($cover, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($office['name'], ENT_QUOTES, 'UTF-8'); ?>" loading="eager">
        <div class="th-off-hero__shade" aria-hidden="true"></div>
        <div class="th-off-hero__inner">
            <nav class="th-off-breadcrumb" aria-label="Навигация">
                <a href="/frontend/window/offices.php">Офисы</a>
                <span>/</span>
                <a href="<?php echo htmlspecialchars($cityListUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($office['city_name'], ENT_QUOTES, 'UTF-8'); ?></a>
                <span>/</span>
                <span><?php echo htmlspecialchars($office['brand'], ENT_QUOTES, 'UTF-8'); ?></span>
            </nav>
            <p class="th-off-hero__brand"><?php echo htmlspecialchars($office['name'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="th-off-hero__lead"><?php echo htmlspecialchars($office['address_short'], ENT_QUOTES, 'UTF-8'); ?></p>
            <div class="th-off-hero__cta">
                <a class="th-off-hero__cta-primary" href="tel:<?php echo htmlspecialchars($office['phone_tel'], ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-phone" aria-hidden="true"></i>
                    <?php echo htmlspecialchars($office['phone'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
                <a class="th-off-hero__cta-ghost" href="<?php echo htmlspecialchars($maxHref, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                    MAX
                </a>
                <button type="button" class="th-off-hero__cta-ghost" data-th-office-lead-btn="1"
                        data-office-city="<?php echo htmlspecialchars($office['city_name'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-office-name="<?php echo htmlspecialchars($office['name'], ENT_QUOTES, 'UTF-8'); ?>">
                    Оставить заявку
                </button>
            </div>
        </div>
    </header>

    <main class="th-off-wrap th-off-detail">
        <div class="th-off-detail__grid">
            <section class="th-off-detail__main">
                <h1 class="th-off-detail__h">Об офисе</h1>
                <p class="th-off-detail__text"><?php echo htmlspecialchars($office['description'], ENT_QUOTES, 'UTF-8'); ?></p>

                <?php if (!empty($office['services'])): ?>
                <h2 class="th-off-detail__h2">Услуги</h2>
                <ul class="th-off-detail__services">
                    <?php foreach ($office['services'] as $svc): ?>
                    <li><i class="fas fa-check" aria-hidden="true"></i><?php echo htmlspecialchars((string) $svc, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

                <?php if (count($photos) > 0): ?>
                <h2 class="th-off-detail__h2">Фото офиса</h2>
                <div class="th-off-detail__gallery">
                    <?php foreach ($photos as $i => $ph): ?>
                    <a class="th-off-detail__shot" href="<?php echo htmlspecialchars($ph['image_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                        <img src="<?php echo htmlspecialchars($ph['image_url'], ENT_QUOTES, 'UTF-8'); ?>"
                             alt="<?php echo htmlspecialchars($office['name'] . ' — фото ' . ($i + 1), ENT_QUOTES, 'UTF-8'); ?>"
                             loading="<?php echo $i < 2 ? 'eager' : 'lazy'; ?>">
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($employees)): ?>
                <h2 class="th-off-detail__h2">Команда</h2>
                <div class="th-off-detail__team">
                    <?php foreach ($employees as $emp):
                        $photo = th_employee_photo_public_href($emp['photo'] ?? '');
                    ?>
                    <article class="th-off-team-card">
                        <div class="th-off-team-card__ava">
                            <?php if ($photo !== ''): ?>
                            <img src="<?php echo htmlspecialchars($photo, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) $emp['name'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
                            <?php else: ?>
                            <span><i class="fas fa-user" aria-hidden="true"></i></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h3 class="th-off-team-card__name"><?php echo htmlspecialchars((string) ($emp['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
                            <?php if (!empty($emp['position'])): ?>
                            <p class="th-off-team-card__role"><?php echo htmlspecialchars((string) $emp['position'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($emp['phone'])):
                                $empTel = preg_replace('/\D+/', '', (string) $emp['phone']);
                                if ($empTel !== '' && $empTel[0] === '8') {
                                    $empTel = '7' . substr($empTel, 1);
                                }
                                if ($empTel !== '' && $empTel[0] !== '+') {
                                    $empTel = '+' . $empTel;
                                }
                            ?>
                            <a class="th-off-team-card__phone" href="tel:<?php echo htmlspecialchars($empTel, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars((string) $emp['phone'], ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>

            <aside class="th-off-detail__aside">
                <div class="th-off-aside-card">
                    <h2 class="th-off-detail__h2" style="margin-top:0">Контакты</h2>
                    <p class="th-off-aside-row"><i class="fas fa-map-marker-alt" aria-hidden="true"></i><?php echo htmlspecialchars($office['address'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="th-off-aside-row"><i class="fas fa-clock" aria-hidden="true"></i><?php echo htmlspecialchars($office['hours'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="th-off-aside-row">
                        <i class="fas fa-phone" aria-hidden="true"></i>
                        <a href="tel:<?php echo htmlspecialchars($office['phone_tel'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($office['phone'], ENT_QUOTES, 'UTF-8'); ?></a>
                    </p>
                    <p class="th-off-aside-row">
                        <i class="fas fa-envelope" aria-hidden="true"></i>
                        <a href="mailto:<?php echo htmlspecialchars($office['email'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($office['email'], ENT_QUOTES, 'UTF-8'); ?></a>
                    </p>
                    <div class="th-off-aside-actions">
                        <a class="th-off-card__call" href="tel:<?php echo htmlspecialchars($office['phone_tel'], ENT_QUOTES, 'UTF-8'); ?>">Позвонить</a>
                        <a class="th-off-card__wa th-off-card__max" href="<?php echo htmlspecialchars($maxHref, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">MAX</a>
                        <button type="button" class="th-off-card__lead" data-th-office-lead-btn="1"
                                data-office-city="<?php echo htmlspecialchars($office['city_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-office-name="<?php echo htmlspecialchars($office['name'], ENT_QUOTES, 'UTF-8'); ?>">
                            Заявка в этот офис
                        </button>
                        <a class="th-off-card__more" href="https://yandex.ru/maps/?text=<?php echo $mapQuery; ?>" target="_blank" rel="noopener noreferrer">
                            Открыть на карте
                        </a>
                    </div>
                </div>
                <a class="th-off-back" href="<?php echo htmlspecialchars($cityListUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    ← Все офисы в <?php echo htmlspecialchars($office['city_name'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
            </aside>
        </div>

        <section class="mt-8 rounded-2xl overflow-hidden border border-slate-200 bg-white shadow-sm">
            <div class="px-4 py-3 border-b border-slate-100">
                <h2 class="heading-font text-lg font-bold text-slate-900 m-0">Офис на Яндекс.Карте</h2>
            </div>
            <?php include __DIR__ . '/../../../backend/components/yandex_map_open_link.php'; ?>
        </section>
    </main>

    <?php
    $th_cta_source = 'office_detail_' . $slug;
    $th_cta_title = 'Хотите, подберём тур из этого офиса?';
    $th_cta_sub = 'Оставьте телефон — менеджер ' . $office['brand'] . ' перезвонит за 15 минут.';
    $th_cta_id = 'th-office-detail-cta';
    include __DIR__ . '/../../../backend/components/page_cta_band.php';
    include __DIR__ . '/../../../backend/components/footer.php';
    ?>
    <script src="/frontend/js/office-lead-modal.js?v=5" defer></script>
</body>
</html>
