<?php
require_once __DIR__ . '/../../backend/components/page_cache_early.php';
if (PageCache::get()) exit;
require_once __DIR__ . '/../../backend/config/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
PageCache::start();

// Загрузка услуг из БД (для вывода цен и ссылок)
$servicesList = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, name, price, description, url FROM services WHERE available = 1 ORDER BY display_order ASC, name ASC");
        $servicesList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Таблица services может отсутствовать
    }
}

// Маппинг иконок по ключевым словам в названии (для услуг из БД)
$iconMap = [
    'авиа' => 'fa-plane', 'билет' => 'fa-plane', 'самолёт' => 'fa-plane',
    'отель' => 'fa-hotel', 'гостиниц' => 'fa-hotel',
    'виз' => 'fa-passport', 'паспорт' => 'fa-passport',
    'страхов' => 'fa-shield-alt',
    'трансфер' => 'fa-car', 'авто' => 'fa-car',
    'экскурс' => 'fa-map-marked-alt', 'тур' => 'fa-map-marked-alt',
];
$defaultIcon = 'fa-star';
function getServiceIcon($name, $iconMap, $default) {
    $name = mb_strtolower($name);
    foreach ($iconMap as $key => $icon) {
        if (mb_strpos($name, $key) !== false) return $icon;
    }
    return $default;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title>Услуги - Travel Hub</title>
    <meta name="description" content="Полный спектр туристических услуг: авиабилеты, отели, визы, страхование, трансферы, экскурсии. Travel Hub — премиум-сервис для путешествий.">
    <?php
    $seo_proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $seo_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $seo_base = rtrim($seo_proto . '://' . $seo_host, '/');
    $seo_path = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $seo_prefix = ($seo_path !== '/' && $seo_path !== '') ? rtrim(str_replace('\\', '/', $seo_path), '/') : '';
    $serviceListSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'name' => 'Туристические услуги Travel Hub',
        'description' => 'Список услуг: авиабилеты, отели, визы, страхование, трансферы, экскурсии.',
        'numberOfItems' => count($servicesList),
        'itemListElement' => [],
    ];
    foreach (array_slice($servicesList, 0, 20) as $i => $svc) {
        $serviceListSchema['itemListElement'][] = [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'item' => [
                '@type' => 'Service',
                'name' => $svc['name'] ?? '',
                'description' => $svc['description'] ?? '',
                'provider' => ['@type' => 'Organization', 'name' => 'Travel Hub'],
            ],
        ];
        if (isset($svc['price']) && (float)$svc['price'] > 0) {
            $serviceListSchema['itemListElement'][$i]['item']['offers'] = [
                '@type' => 'Offer',
                'price' => (float)$svc['price'],
                'priceCurrency' => 'RUB',
            ];
        }
    }
    if (empty($servicesList)) {
        $serviceListSchema['itemListElement'] = [
            ['@type' => 'ListItem', 'position' => 1, 'item' => ['@type' => 'Service', 'name' => 'Авиабилеты', 'provider' => ['@type' => 'Organization', 'name' => 'Travel Hub']]],
            ['@type' => 'ListItem', 'position' => 2, 'item' => ['@type' => 'Service', 'name' => 'Отели', 'provider' => ['@type' => 'Organization', 'name' => 'Travel Hub']]],
            ['@type' => 'ListItem', 'position' => 3, 'item' => ['@type' => 'Service', 'name' => 'Визы', 'provider' => ['@type' => 'Organization', 'name' => 'Travel Hub']]],
        ];
        $serviceListSchema['numberOfItems'] = 3;
    }
    ?>
    <script type="application/ld+json"><?php echo json_encode($serviceListSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="alternate icon" href="/frontend/favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__ . '/../../backend/components/design_system_head.php'; ?>
</head>
<body class="ds-page text-slate-900 antialiased bg-white">
    <?php 
    $current_page = 'services';
    include __DIR__ . '/../../backend/components/header.php'; 
    ?>

    <!-- Hero Section -->
    <section class="ds-page-hero relative py-20 md:py-28 bg-gradient-to-br from-indigo-50 via-white to-slate-50">
        <div class="th-container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-4xl mx-auto">
                <span class="pill-badge mb-6 inline-flex items-center gap-2">
                    <i class="fas fa-star"></i>
                    Наши услуги
                </span>
                <h1 class="heading-font text-4xl sm:text-5xl md:text-6xl font-bold text-slate-900 mb-6">
                    Полный спектр <span class="gradient-text">туристических услуг</span>
                </h1>
                <p class="text-xl text-slate-700 mb-8 max-w-2xl mx-auto">
                    От бронирования туров до оформления виз — мы позаботимся обо всех деталях вашего путешествия. Премиум-сервис без компромиссов.
                </p>
            </div>
        </div>
    </section>

    <!-- Services Content -->
    <section class="py-16 sm:py-20 md:py-28 bg-white">
        <div class="th-container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-16">
                    <?php if (!empty($servicesList)): ?>
                        <?php foreach ($servicesList as $svc): ?>
                        <?php
                            $svcUrl = !empty($svc['url']) ? $svc['url'] : '/frontend/window/services.php';
                            if (!str_starts_with($svcUrl, 'http') && !str_starts_with($svcUrl, '/')) {
                                $svcUrl = '/' . $svcUrl;
                            }
                            $priceVal = (float)($svc['price'] ?? 0);
                            $icon = getServiceIcon($svc['name'], $iconMap, $defaultIcon);
                        ?>
                        <div class="surface-card p-8">
                            <div class="service-icon mx-auto mb-6">
                                <i class="fas <?php echo htmlspecialchars($icon); ?> text-4xl text-indigo-600"></i>
                            </div>
                            <h3 class="heading-font text-2xl font-bold text-slate-900 mb-4 text-center"><?php echo htmlspecialchars($svc['name']); ?></h3>
                            <?php if ($priceVal > 0): ?>
                            <p class="text-center text-indigo-600 font-bold text-xl mb-4">от <?php echo number_format($priceVal, 0, '.', ' '); ?> ₽</p>
                            <?php endif; ?>
                            <p class="text-slate-700 mb-6 text-center"><?php echo nl2br(htmlspecialchars($svc['description'] ?? '')); ?></p>
                            <a href="<?php echo htmlspecialchars($svcUrl); ?>" class="block text-center text-indigo-600 font-semibold hover:text-indigo-800">
                                Подробнее <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Fallback: статичные услуги, если в БД пусто -->
                        <?php
                        $staticServices = [
                            ['icon' => 'fa-plane', 'name' => 'Авиабилеты', 'desc' => 'Бронирование авиабилетов по лучшим ценам. Работаем со всеми авиакомпаниями мира.'],
                            ['icon' => 'fa-hotel', 'name' => 'Отели', 'desc' => 'Подбор отелей любого класса. От бюджетных вариантов до люксовых курортов.'],
                            ['icon' => 'fa-passport', 'name' => 'Визы', 'desc' => 'Оформление виз в любую страну мира. Полное сопровождение процесса.'],
                            ['icon' => 'fa-shield-alt', 'name' => 'Страхование', 'desc' => 'Туристическое страхование для защиты вашего путешествия и здоровья.'],
                            ['icon' => 'fa-car', 'name' => 'Трансферы', 'desc' => 'Комфортабельные трансферы из аэропорта в отель и обратно.'],
                            ['icon' => 'fa-map-marked-alt', 'name' => 'Экскурсии', 'desc' => 'Организация индивидуальных и групповых экскурсий по всему миру.'],
                        ];
                        foreach ($staticServices as $ss): ?>
                        <div class="surface-card p-8">
                            <div class="service-icon mx-auto mb-6">
                                <i class="fas <?php echo $ss['icon']; ?> text-4xl text-indigo-600"></i>
                            </div>
                            <h3 class="heading-font text-2xl font-bold text-slate-900 mb-4 text-center"><?php echo htmlspecialchars($ss['name']); ?></h3>
                            <p class="text-slate-700 mb-6 text-center"><?php echo htmlspecialchars($ss['desc']); ?></p>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </section>

    <?php
    $th_cta_source = 'services_page';
    $th_cta_title = 'Нужна услуга под ключ? Оставьте телефон';
    $th_cta_sub = 'Визы, страховки, трансферы, подбор тура — перезвоним за 15 минут.';
    $th_cta_id = 'th-services-cta';
    include __DIR__ . '/../../backend/components/page_cta_band.php';
    ?>

    <?php include __DIR__ . '/../../backend/components/footer.php'; ?>

<?php PageCache::end(); ?>
</body>
</html>






