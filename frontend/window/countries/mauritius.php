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
  'slug' => 'mauritius',
  'name' => 'Маврикий',
  'nameEn' => 'Mauritius',
  'flag' => '🇲🇺',
  'description' => 'Маврикий — тропический остров в Индийском океане с потрясающими пляжами, роскошными курортами и уникальной культурой.',
  'bio' => 'Маврикий — островное государство в Индийском океане, расположенное к востоку от Мадагаскара. Остров славится своими потрясающими пляжами с белоснежным песком, кристально чистой водой, роскошными курортами и уникальной культурой, сочетающей африканские, индийские, китайские и европейские влияния.

Маврикий — идеальное место для пляжного отдыха, дайвинга, снорклинга и водных видов спорта. Остров окружен коралловыми рифами, создающими идеальные условия для подводного плавания. Помимо пляжей, Маврикий предлагает горные походы, водопады, ботанические сады и культурные достопримечательности.

Маврикийская кухня — это уникальная смесь индийской, китайской, французской и креольской кухонь. Популярны карри, роти, свежие морепродукты, тропические фрукты.',
  'images' => 
  array(
    0 => '../img/маврикий/5b3f1aff1b67ef086b355bc2886f37cf.jpg',
    1 => '../img/маврикий/5dd6833220abc837e603d7de9b3f8728.jpg',
    2 => '../img/маврикий/946ccd7a7b82f2c25b164ada53e46ea5.jpg',
    3 => '../img/маврикий/bf3bfb2e523145e0a3ddcc2f9723d1cb.jpg',
    4 => '../img/маврикий/f37149293ca7fd31b458b42c085465e8.jpg',
    7 => 'https://images.unsplash.com/photo-1469474968028-56623f02e42e?auto=format&fit=crop&w=1920&q=80'
  ),
  'highlights' => 
  array(
    0 => 'Роскошные курорты',
    1 => 'Идеальный дайвинг',
    2 => 'Уникальная культура',
    3 => 'Тропические пляжи',
  ),
  'bestTime' => 'Май-декабрь (сухой сезон)',
  'currency' => 'Маврикийская рупия (MUR)',
  'language' => 'Английский, французский, креольский',
  'visa' => 'Виза не требуется для граждан РФ (до 60 дней)',
  'detailedInfo' => 
  array(
    'climate' => 'Тропический морской климат. Температура: 22-30°C круглый год. Сухой сезон: май-декабрь. Сезон дождей: январь-апрель (кратковременные ливни).',
    'attractions' => 'Роскошные пляжи, коралловые рифы, водопады, ботанические сады, горные походы, культурные достопримечательности, семицветные пески Шамарель.',
    'activities' => 'Пляжный отдых, дайвинг и снорклинг, водные виды спорта, гольф, спа-процедуры, горные походы, экскурсии, наблюдение за китами, рыбалка.',
    'cuisine' => 'Маврикийская кухня: смесь индийской, китайской, французской и креольской. Карри, роти, свежие морепродукты, тропические фрукты, местные специи.',
    'culture' => 'Многонациональная культура с африканскими, индийскими, китайскими и европейскими влияниями. Мирная атмосфера, гостеприимство, традиционные фестивали.',
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
    $page_title = 'Маврикий - Travel Hub | Туры, отели, отдых';
    $page_description = 'Туры в Маврикий от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры на Маврикий, отдых на Маврикии';
    $page_keywords = 'туры на Маврикий, отдых на Маврикии, Travel Hub, туры, отдых, отели';
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
        ['name' => 'Маврикий', 'url' => '']
    ];
    $schema_data = [
        '@type' => 'Place',
        'name' => 'Маврикий',
        'description' => 'Туры в Маврикий от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры на Маврикий, отдых на Маврикии'
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
    <?php $th_country_cta_source = 'country_mauritius'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>

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