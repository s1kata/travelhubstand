<?php
require_once __DIR__ . '/../../../backend/components/page_cache_early.php';
if (PageCache::get()) exit;
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/country_content_helper.php';
if (session_status() === PHP_SESSION_NONE) session_start();
PageCache::start();

// Функция для получения буквенного кода страны
function getCountryCode($slug) {
    $codes = [
        'turkey' => 'TR', 'egypt' => 'EG', 'thailand' => 'TH', 'uae' => 'AE',
        'russia' => 'RU', 'china' => 'CN', 'abkhazia' => 'AB', 'armenia' => 'AM',
        'bahrain' => 'BH', 'cuba' => 'CU', 'india' => 'IN', 'indonesia' => 'ID',
        'jordan' => 'JO', 'mauritius' => 'MU', 'maldives' => 'MV', 'montenegro' => 'ME',
        'oman' => 'OM', 'philippines' => 'PH', 'qatar' => 'QA', 'seychelles' => 'SC',
        'sri-lanka' => 'LK', 'tanzania' => 'TZ', 'tunisia' => 'TN', 'venezuela' => 'VE',
        'vietnam' => 'VN',
    ];
    return $codes[$slug] ?? strtoupper(substr($slug, 0, 2));
}

// Статичные данные о стране (написаны вручную)
$countryData = array(
  'slug' => 'maldives',
  'name' => 'Мальдивы',
  'nameEn' => 'Maldives',
  'flag' => '🇲🇻',
  'description' => 'Мальдивы — это тропический рай с кристально чистой водой, белоснежными пляжами и роскошными виллами над водой. Идеальное место для романтического отдыха и медового месяца.',
  'bio' => 'Мальдивы — это архипелаг из 26 атоллов, состоящий из более чем 1000 коралловых островов в Индийском океане. Столица — Мале. Климат тропический муссонный, температура круглый год 26-30°C. Мальдивы славятся своими курортами с виллами над водой, идеальными условиями для дайвинга и снорклинга, а также исключительным уровнем сервиса.

Мальдивы — это настоящий тропический рай, где каждый остров-курорт представляет собой отдельный мир роскоши и уединения. Страна состоит из 26 атоллов, разбросанных на площади более 90 тысяч квадратных километров в Индийском океане. Большинство островов необитаемы, а на обитаемых расположены эксклюзивные курорты мирового класса.

Мальдивы — идеальное место для романтического отдыха, медового месяца и уединения. Здесь нет шумных городов, толп туристов или суеты. Только бескрайний океан, белоснежные пляжи, кристально чистая вода и роскошные виллы над водой. Каждый курорт — это отдельный остров с собственным пляжем, ресторанами, спа-центром и всеми необходимыми удобствами.',
  'extendedInfo' => 
  array(
    'history' => 'Мальдивы были заселены более 2000 лет назад выходцами из Индии и Шри-Ланки. В XII веке страна приняла ислам, который остается государственной религией. С XVI века Мальдивы были под протекторатом различных европейских держав (португальцы, голландцы, британцы), но сохраняли внутреннюю автономию. В 1965 году Мальдивы получили независимость от Великобритании, а в 1968 году стали республикой. Сегодня Мальдивы — президентская республика с населением около 500 тысяч человек, большинство из которых живет в столице Мале.',
    'geography' => 'Мальдивы расположены в Индийском океане, к юго-западу от Шри-Ланки. Архипелаг состоит из 26 атоллов, которые включают более 1000 коралловых островов. Общая площадь суши составляет всего около 300 квадратных километров, но территория страны простирается на 90 тысяч квадратных километров океана. Самая высокая точка страны — всего 2,4 метра над уровнем моря, что делает Мальдивы самой низкой страной в мире. Климат тропический муссонный с двумя сезонами: сухой (декабрь-апрель) и влажный (май-ноябрь).',
    'nature' => 'Подводный мир Мальдив — один из самых богатых и разнообразных в мире. Здесь обитает более 2000 видов рыб, 187 видов кораллов, 5 видов морских черепах, множество акул, скатов, дельфинов и китов. Коралловые рифы окружают каждый остров, создавая идеальные условия для дайвинга и снорклинга. На суше растительность ограничена кокосовыми пальмами, хлебными деревьями и тропическими цветами. Мальдивы известны своими белоснежными пляжами, которые состоят из кораллового песка.',
    'tourism' => 'Туризм — основа экономики Мальдив. Страна специализируется на эксклюзивном туризме класса люкс. Каждый курорт расположен на отдельном острове, что обеспечивает полное уединение и приватность. Популярны виллы над водой, подводные рестораны, спа-центры мирового класса, частные пляжи. Многие курорты работают по системе "все включено" премиум-класса. Популярные развлечения: дайвинг, снорклинг, рыбалка, катание на водных лыжах, кайтсерфинг, парусный спорт, спа-процедуры.',
    'transport' => 'Международный аэропорт Велана находится на острове Хулуле, рядом со столицей Мале. Прямые рейсы выполняются из Москвы, Дубая, Стамбула и других городов. От аэропорта до курортов можно добраться на скоростных катерах, гидросамолетах или внутренних рейсах на небольших самолетах. Расстояние до курортов варьируется от 15 минут до 1,5 часов. На самих островах-курортах передвижение пешком, так как они небольшие по размеру.',
    'tips' => 'Лучшее время для посещения — с декабря по апрель (сухой сезон), когда дожди минимальны, а море спокойное. Мальдивы — мусульманская страна, поэтому на местных островах нужно соблюдать дресс-код (скромная одежда), но на курортах можно одеваться свободно. Алкоголь продается только на курортах. Чаевые обычно включены в счет, но дополнительное вознаграждение приветствуется. Местная валюта — руфия, но доллары США широко принимаются. Обязательно возьмите с собой солнцезащитный крем и подводную камеру для съемки подводного мира.',
  ),
  'images' => 
  array(
    0 => '../img/мальдивы/1a8d863f3a6095f994c7d10d6f82960c.jpg',
    1 => '../img/мальдивы/3fadde43a70f778ca631c0a28ed34a40.jpg',
    2 => '../img/мальдивы/43acc6bb21bf4e895f7c144d7e13d156.jpg',
    3 => '../img/мальдивы/84a42e8e4c013a3adc26b1f5cf89a30d.jpg',
    4 => '../img/мальдивы/ad7fd2eb79a48fe8b9cae8869d7fd5fd.jpg'
  ),
  'highlights' => 
  array(
    0 => 'Виллы над водой',
    1 => 'Идеальный дайвинг и снорклинг',
    2 => 'Роскошные курорты',
    3 => 'Романтическая атмосфера',
  ),
  'bestTime' => 'Декабрь-апрель (сухой сезон)',
  'currency' => 'Мальдивская руфия (MVR), USD',
  'language' => 'Мальдивский (дивехи), английский',
  'visa' => 'Виза не требуется для граждан РФ (до 30 дней)',
  'detailedInfo' => 
  array(
    'climate' => 'Тропический муссонный климат. Температура круглый год 26-30°C. Сухой сезон: декабрь-апрель, сезон дождей: май-ноябрь (кратковременные ливни).',
    'attractions' => 'Виллы над водой, коралловые рифы, подводный мир, песчаные пляжи, спа-центры мирового класса, романтические ужины на пляже.',
    'activities' => 'Дайвинг и снорклинг, катание на водных лыжах, кайтсерфинг, парусный спорт, рыбалка, спа-процедуры, йога, наблюдение за дельфинами.',
    'cuisine' => 'Международная кухня в отелях, свежие морепродукты, тропические фрукты. Романтические ужины на пляже и подводные рестораны.',
    'culture' => 'Мусульманская страна с уникальной островной культурой. Мирная атмосфера, идеальная для романтического отдыха и медового месяца.',
  ),
);;;

$countrySlug = $countryData['slug'];

// Загружаем контент из БД, если он есть
applyCountryContentFromDB($pdo, $countrySlug, $countryData);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <?php
    $page_title = 'Мальдивы - Travel Hub | Туры, отели, отдых';
    $page_description = 'Туры в Мальдивы от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры на Мальдивы, отдых на Мальдивах, туры Мальдивы';
    $page_keywords = 'туры на Мальдивы, отдых на Мальдивах, туры Мальдивы, Travel Hub, туры, отдых, отели';
    $__ogImg = $countryData['images'][0] ?? '';
    if ($__ogImg !== '' && preg_match('#\Ahttps?://#i', $__ogImg)) {
        $page_image = $__ogImg;
    } elseif ($__ogImg !== '') {
        $page_image = preg_replace('#^\.\./#', '/frontend/window/', $__ogImg);
    } else {
        $page_image = '/frontend/favicon.svg';
    }
    $page_type = 'Place';
    $breadcrumbs = [
        ['name' => 'Главная', 'url' => (isset($seo_base) ? $seo_base : '') . '/frontend/index.php'],
        ['name' => 'Страны', 'url' => (isset($seo_base) ? $seo_base : '') . '/frontend/window/countries-list.php'],
        ['name' => 'Мальдивы', 'url' => '']
    ];
    $schema_data = [
        '@type' => 'Place',
        'name' => 'Мальдивы',
        'description' => 'Туры в Мальдивы от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры на Мальдивы, отдых на Мальдивах, туры Мальдивы'
    ];
    include __DIR__ . '/../../../backend/components/seo_head.php';
    ?>

    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="alternate icon" href="/frontend/favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__ . '/../../../backend/components/design_system_head.php'; ?>

</head>
<body class="ds-page text-slate-900 antialiased">
    <?php 
    $current_page = 'countries';
    include __DIR__ . '/../../../backend/components/header.php'; 
    ?>

    <?php include __DIR__ . '/../../../backend/components/country_page_main_sections.php'; ?>
    <?php $th_country_cta_source = 'country_maldives'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>

    <?php include __DIR__ . '/../../../backend/components/footer.php'; ?>


    <script>
        // Gallery Lightbox functionality
        const galleryImages = document.querySelectorAll('.gallery-image');
        const lightbox = document.getElementById('gallery-lightbox');
        const lightboxImage = document.getElementById('lightbox-image');
        const closeLightbox = document.getElementById('close-lightbox');
        const prevButton = document.getElementById('prev-image');
        const nextButton = document.getElementById('next-image');
        const imageCounter = document.getElementById('image-counter');
        let currentImageIndex = 0;
        const allImages = <?php echo json_encode($countryData['images'] ?? []); ?>;

        if (galleryImages.length > 0) {
            galleryImages.forEach((img, index) => {
                img.addEventListener('click', () => {
                    currentImageIndex = index;
                    openLightbox();
                });
            });

            function openLightbox() {
                if (allImages.length > 0 && allImages[currentImageIndex]) {
                    lightboxImage.src = allImages[currentImageIndex];
                    lightboxImage.alt = '<?php echo htmlspecialchars($countryData['name']); ?> - Фото ' + (currentImageIndex + 1);
                    imageCounter.textContent = (currentImageIndex + 1) + ' / ' + allImages.length;
                    lightbox.classList.remove('hidden');
                    lightbox.classList.add('flex');
                    if (window.THMobile && THMobile.lockScroll) THMobile.lockScroll(true);
                }
            }

            function closeLightboxFunc() {
                lightbox.classList.add('hidden');
                lightbox.classList.remove('flex');
                if (window.THMobile && THMobile.lockScroll) THMobile.lockScroll(false);
            }

            function showNextImage() {
                currentImageIndex = (currentImageIndex + 1) % allImages.length;
                openLightbox();
            }

            function showPrevImage() {
                currentImageIndex = (currentImageIndex - 1 + allImages.length) % allImages.length;
                openLightbox();
            }

            closeLightbox.addEventListener('click', closeLightboxFunc);
            prevButton.addEventListener('click', showPrevImage);
            nextButton.addEventListener('click', showNextImage);
            
            lightbox.addEventListener('click', (e) => {
                if (e.target === lightbox) {
                    closeLightboxFunc();
                }
            });

            document.addEventListener('keydown', (e) => {
                if (!lightbox.classList.contains('hidden')) {
                    if (e.key === 'Escape') {
                        closeLightboxFunc();
                    } else if (e.key === 'ArrowRight') {
                        showNextImage();
                    } else if (e.key === 'ArrowLeft') {
                        showPrevImage();
                    }
                }
            });
        }

    </script>
<?php PageCache::end(); ?>
</body>
</html>