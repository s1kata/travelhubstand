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
  'slug' => 'sri-lanka',
  'name' => 'Шри-Ланка',
  'nameEn' => 'Sri Lanka',
  'flag' => '🇱🇰',
  'description' => 'Шри-Ланка — тропический остров с богатой историей, древними храмами, чайными плантациями и потрясающими пляжами.',
  'bio' => 'Шри-Ланка — островное государство в Индийском океане, расположенное к югу от Индии. Страна известна как "жемчужина Индийского океана" благодаря своим потрясающим пляжам, богатой истории, древним храмам и чайным плантациям. Шри-Ланка славится своим культурным наследием, включая древние города Анурадхапура и Полоннарува, скальную крепость Сигирия и множество буддийских храмов.

От пляжей на побережье до горных районов с чайными плантациями, от древних городов до национальных парков с дикими слонами — Шри-Ланка предлагает невероятное разнообразие. Страна известна своим чаем, специями, драгоценными камнями и гостеприимством местных жителей.

Шри-ланкийская кухня острая и ароматная, с использованием кокосового молока, специй и свежих ингредиентов. Популярны карри, роти (лепешки), хопперы (блинчики), свежие морепродукты.',
  'images' => 
  array(
    0 => '../img/шриланка/5af0ebd87bd018ea6076bf0fd7a3a524.jpg',
    1 => '../img/шриланка/01b65831dec9266e9ccfea78f991fb2d.jpg',
    2 => '../img/шриланка/31dba33a2e1cd3eb152ff24a35e3f73d.jpg',
    3 => '../img/шриланка/8bd273484e927f25219ac7a3e80fe003.jpg',
    4 => '../img/шриланка/f419d8a20b9f44ff98fe53e62f0f6ce5.jpg'
  ),
  'highlights' => 
  array(
    0 => 'Древние храмы',
    1 => 'Чайные плантации',
    2 => 'Потрясающие пляжи',
    3 => 'Богатая культура',
  ),
  'bestTime' => 'Декабрь-март (сухой сезон)',
  'currency' => 'Шри-ланкийская рупия (LKR)',
  'language' => 'Сингальский, тамильский, английский',
  'visa' => 'Требуется виза для граждан РФ',
  'detailedInfo' => 
  array(
    'climate' => 'Тропический климат. Температура на побережье: 27-30°C круглый год. В горах прохладнее. Сухой сезон: декабрь-март. Сезон дождей: май-сентябрь.',
    'attractions' => 'Сигирия (скальная крепость), древние города Анурадхапура и Полоннарува, Канди (храм Зуба Будды), чайные плантации, пляжи, национальные парки со слонами.',
    'activities' => 'Пляжный отдых, экскурсии к древним храмам, посещение чайных плантаций, сафари в национальных парках, наблюдение за китами, треккинг, дегустация чая.',
    'cuisine' => 'Шри-ланкийская кухня: карри, роти (лепешки), хопперы (блинчики), свежие морепродукты, кокосовое молоко, специи. Острая и ароматная еда.',
    'culture' => 'Богатое буддийское наследие с древними традициями. Гостеприимство местных жителей, традиционные ремесла, чайная культура, разнообразие религий.',
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
    $page_title = 'Шри-Ланка - Travel Hub | Туры, отели, отдых';
    $page_description = 'Туры в Шри-Ланка от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры на Шри-Ланку, отдых на Шри-Ланке';
    $page_keywords = 'туры на Шри-Ланку, отдых на Шри-Ланке, Travel Hub, туры, отдых, отели';
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
        ['name' => 'Шри-Ланка', 'url' => '']
    ];
    $schema_data = [
        '@type' => 'Place',
        'name' => 'Шри-Ланка',
        'description' => 'Туры в Шри-Ланка от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры на Шри-Ланку, отдых на Шри-Ланке'
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
    <?php $th_country_cta_source = 'country_sri-lanka'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>

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