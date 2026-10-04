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
  'slug' => 'oman',
  'name' => 'Оман',
  'nameEn' => 'Oman',
  'flag' => '🇴🇲',
  'description' => 'Оман — арабское государство с богатой историей, потрясающими пляжами, пустынями и современными курортами.',
  'bio' => 'Оман — султанат на Аравийском полуострове, известный своей богатой историей, потрясающими пляжами, пустынями и современными курортами. Страна славится своим гостеприимством, безопасностью и уникальным сочетанием традиций и современности.

От столицы Маската с его белыми зданиями и фортами до пустыни Вахиба, от горных районов до побережья — Оман предлагает разнообразные впечатления. Страна известна своими фортами, традиционными рынками (суками), роскошными курортами и отличными условиями для дайвинга.

Оманская кухня — это смесь арабских, индийских и африканских влияний. Популярны шаверма, кебабы, свежие морепродукты, финики, оманский хлеб.',
  'images' => 
  array(
    0 => '../img/оман/2fb42418ab2074c08a26ec54e7b39ba5.jpg',
    1 => '../img/оман/3ce73c742fdd0439c3229997a49e2591.jpg',
    2 => '../img/оман/88ca7c7143627642c1cdbc8175fa7cf0.jpg',
    3 => '../img/оман/c1724708683d253d5a5627c26ffd8970.jpg',
    4 => '../img/оман/ec97ec6076ed68e943f95de888ca3b71.jpg',
    7 => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1920&q=80'
  ),
  'highlights' => 
  array(
    0 => 'Роскошные курорты',
    1 => 'Богатая история',
    2 => 'Пустынные сафари',
    3 => 'Отличный дайвинг',
  ),
  'bestTime' => 'Октябрь-апрель (лучшее время)',
  'currency' => 'Оманский риал (OMR)',
  'language' => 'Арабский, английский',
  'visa' => 'Требуется виза для граждан РФ',
  'detailedInfo' => 
  array(
    'climate' => 'Пустынный климат. Лето очень жаркое (35-45°C), зима теплая (20-25°C). Лучшее время: октябрь-апрель. Осадки редкие, в основном в горах.',
    'attractions' => 'Маскат (столица с белыми зданиями), форты, пустыня Вахиба, горы, побережье, традиционные рынки (суки), роскошные курорты, вади (пересыхающие реки).',
    'activities' => 'Пустынные сафари, дайвинг, пляжный отдых, экскурсии к фортам, горные походы, посещение традиционных рынков, катание на верблюдах, спа-процедуры.',
    'cuisine' => 'Оманская кухня: шаверма, кебабы, свежие морепродукты, финики, оманский хлеб, мачбус (рис с мясом), арабский кофе. Смесь арабских, индийских и африканских влияний.',
    'culture' => 'Богатая история с традиционными фортами и современными курортами. Гостеприимство, безопасность, уникальное сочетание традиций и современности.',
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
    $page_title = 'Оман - Travel Hub | Туры, отели, отдых';
    $page_description = 'Туры в Оман от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Оман, отдых в Омане';
    $page_keywords = 'туры в Оман, отдых в Омане, Travel Hub, туры, отдых, отели';
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
        ['name' => 'Оман', 'url' => '']
    ];
    $schema_data = [
        '@type' => 'Place',
        'name' => 'Оман',
        'description' => 'Туры в Оман от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Оман, отдых в Омане'
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
    <?php $th_country_cta_source = 'country_oman'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>

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