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
  'slug' => 'qatar',
  'name' => 'Катар',
  'nameEn' => 'Qatar',
  'flag' => '🇶🇦',
  'description' => 'Катар — современное государство в Персидском заливе с роскошными отелями, современной архитектурой и богатой культурой.',
  'bio' => 'Катар — небольшое государство на Аравийском полуострове, известное своими роскошными отелями, современной архитектурой, богатой культурой и высоким уровнем жизни. Столица Доха славится своими небоскребами, музеями, торговыми центрами и культурными центрами.

От современной Дохи до традиционных рынков (суков), от пустыни до побережья Персидского залива — Катар предлагает разнообразные впечатления. Страна известна своими роскошными курортами, отличными условиями для дайвинга и уникальным сочетанием традиций и современности.

Катарская кухня — это смесь арабских, персидских и индийских влияний. Популярны мачбус, свежие морепродукты, финики, арабский кофе, восточные сладости.',
  'images' => 
  array(
    0 => '../img/катар/1edd5b3a9b9a6a34427d0c0b4377785a.jpg',
    1 => '../img/катар/295b5c76a0459390d0b0e5e5447909d4.jpg',
    2 => '../img/катар/894029478ac874dddfdf4276ac585c53.jpg',
    3 => '../img/катар/b2580d738b1558189590184e5365df33.jpg',
    4 => '../img/катар/e7749333bb440e72b4e98bb07ea91d6f.jpg'
  ),
  'highlights' => 
  array(
    0 => 'Роскошные отели',
    1 => 'Современная архитектура',
    2 => 'Богатая культура',
    3 => 'Высокий уровень сервиса',
  ),
  'bestTime' => 'Октябрь-апрель (лучшее время)',
  'currency' => 'Катарский риал (QAR)',
  'language' => 'Арабский, английский',
  'visa' => 'Требуется виза для граждан РФ',
  'detailedInfo' => 
  array(
    'climate' => 'Пустынный климат. Лето очень жаркое (35-45°C), зима теплая (20-25°C). Лучшее время: октябрь-апрель. Осадки редкие. Высокая влажность летом.',
    'attractions' => 'Доха (столица с небоскребами), Музей исламского искусства, Сук Вакиф, Пальмовая роща, пустыня, побережье Персидского залива, культурные центры.',
    'activities' => 'Шопинг в торговых центрах, экскурсии к музеям, пляжный отдых, дайвинг, гольф, спа-процедуры, посещение традиционных рынков, яхтинг, пустынные сафари.',
    'cuisine' => 'Катарская кухня: мачбус, свежие морепродукты, финики, арабский кофе, восточные сладости. Смесь арабских, персидских и индийских влияний. Рестораны мирового класса.',
    'culture' => 'Современное государство с богатой культурой. Уникальное сочетание традиций и современности, высокий уровень жизни, роскошные отели, отличный сервис.',
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
    $page_title = 'Катар - Travel Hub | Туры, отели, отдых';
    $page_description = 'Туры в Катар от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Катар, отдых в Катаре, туры Доха';
    $page_keywords = 'туры в Катар, отдых в Катаре, туры Доха, Travel Hub, туры, отдых, отели';
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
        ['name' => 'Катар', 'url' => '']
    ];
    $schema_data = [
        '@type' => 'Place',
        'name' => 'Катар',
        'description' => 'Туры в Катар от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Катар, отдых в Катаре, туры Доха'
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
    <?php $th_country_cta_source = 'country_qatar'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>

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