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
  'slug' => 'cuba',
  'name' => 'Куба',
  'nameEn' => 'Cuba',
  'flag' => '🇨🇺',
  'description' => 'Куба — остров в Карибском море с уникальной культурой, колониальной архитектурой, потрясающими пляжами и живой музыкой.',
  'bio' => 'Куба — островное государство в Карибском море, известное своей уникальной культурой, колониальной архитектурой, потрясающими пляжами и живой музыкой. Гавана — столица с ее старыми кварталами, классическими автомобилями и атмосферой 1950-х годов привлекает туристов со всего мира.

От столицы Гаваны до пляжей Варадеро, от табачных плантаций до сахарных полей — Куба предлагает невероятное разнообразие впечатлений. Страна известна своими пляжами с белоснежным песком, кристально чистой водой, возможностями для дайвинга и уникальной атмосферой.

Кубинская кухня — это смесь испанских, африканских и карибских влияний. Популярны мохито, дайкири, ром, свежие морепродукты, рис с черной фасолью, жареная свинина.',
  'images' => 
  array(
    0 => '../img/куба/03fd6fb1dce49565754e29a2c93f4643.jpg',
    1 => '../img/куба/264f2096666f61e0d7d1aeaa59c3574b.jpg',
    2 => '../img/куба/e261084e4ba3280be2c07967b993577a.jpg',
    3 => '../img/куба/ed73da48951cf8157545e36b03222fd3.jpg',
    7 => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1920&q=80'
  ),
  'highlights' => 
  array(
    0 => 'Колониальная архитектура',
    1 => 'Потрясающие пляжи',
    2 => 'Живая музыка',
    3 => 'Уникальная культура',
  ),
  'bestTime' => 'Декабрь-апрель (сухой сезон)',
  'currency' => 'Кубинское песо (CUP)',
  'language' => 'Испанский',
  'visa' => 'Требуется виза для граждан РФ',
  'detailedInfo' => 
  array(
    'climate' => 'Тропический климат. Температура круглый год: 24-30°C. Сухой сезон: декабрь-апрель. Сезон дождей: май-ноябрь. Влажность высокая.',
    'attractions' => 'Гавана (старый квартал, классические автомобили), Варадеро (пляжи), Тринидад (колониальный город), долина Виньялес, Сантьяго-де-Куба, табачные плантации.',
    'activities' => 'Пляжный отдых, дайвинг и снорклинг, экскурсии по колониальным городам, посещение табачных плантаций, сальса, живая музыка, дегустация рома, экотуризм.',
    'cuisine' => 'Кубинская кухня: мохито, дайкири, ром, свежие морепродукты, рис с черной фасолью, жареная свинина, юка. Смесь испанских, африканских и карибских влияний.',
    'culture' => 'Уникальная культура с колониальной архитектурой и живой музыкой. Сальса, джаз, классические автомобили, гостеприимство, атмосфера 1950-х годов.',
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
    $page_title = 'Куба - Travel Hub | Туры, отели, отдых';
    $page_description = 'Туры в Куба от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры на Кубу, отдых на Кубе, туры Гавана';
    $page_keywords = 'туры на Кубу, отдых на Кубе, туры Гавана, Travel Hub, туры, отдых, отели';
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
        ['name' => 'Куба', 'url' => '']
    ];
    $schema_data = [
        '@type' => 'Place',
        'name' => 'Куба',
        'description' => 'Туры в Куба от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры на Кубу, отдых на Кубе, туры Гавана'
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
    <?php $th_country_cta_source = 'country_cuba'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>

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