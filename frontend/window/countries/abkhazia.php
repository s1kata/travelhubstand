<?php
require_once __DIR__ . '/../../../backend/components/page_cache_early.php';
if (PageCache::get()) exit;
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/country_content_helper.php';
if (session_status() === PHP_SESSION_NONE) session_start();
PageCache::start();

// Статичные данные о стране (написаны вручную)
$countryData = array(
  'slug' => 'abkhazia',
  'name' => 'Абхазия',
  'nameEn' => 'Abkhazia',
  'flag' => '🇦🇧',
  'description' => 'Абхазия — живописный регион на побережье Черного моря с субтропическим климатом, горами и богатой природой.',
  'bio' => 'Абхазия — регион на побережье Черного моря, известный своим субтропическим климатом, потрясающими пляжами, горными пейзажами и богатой природой. Регион славится своими курортами, минеральными источниками, пещерами и гостеприимством местных жителей.

От побережья Черного моря до горных вершин, от древних храмов до современных санаториев — Абхазия предлагает разнообразные возможности для отдыха. Популярны пляжный отдых, экскурсии к озеру Рица, Новоафонская пещера, минеральные источники.

Абхазская кухня — это смесь кавказских и средиземноморских традиций. Популярны хачапури, аджика, свежие морепродукты, местные вина.',
  'images' => 
  array(
    0 => '../img/абхазия/photo_2025-12-02_23-31-08.jpg',
    1 => '../img/абхазия/photo_2025-12-02_23-31-09.jpg',
    2 => '../img/абхазия/photo_2025-12-02_23-31-10.jpg',
    3 => '../img/абхазия/photo_2025-12-02_23-31-11.jpg'
  ),
  'highlights' => 
  array(
    0 => 'Побережье Черного моря',
    1 => 'Горные пейзажи',
    2 => 'Минеральные источники',
    3 => 'Богатая природа',
  ),
  'bestTime' => 'Май-сентябрь (пляжный сезон)',
  'currency' => 'Российский рубль (RUB)',
  'language' => 'Абхазский, русский',
  'visa' => 'Не требуется для граждан РФ',
  'detailedInfo' => 
  array(
    'climate' => 'Субтропический климат. Лето теплое (22-28°C), зима мягкая (5-10°C). Пляжный сезон: май-сентябрь. В горах прохладнее. Высокая влажность летом.',
    'attractions' => 'Озеро Рица, Новоафонская пещера, Сухум, Гагра, Пицунда, минеральные источники, горные пейзажи, древние храмы, ботанические сады.',
    'activities' => 'Пляжный отдых, экскурсии к озеру Рица, посещение пещер, минеральные источники, горные походы, культурные экскурсии, дегустация местных вин.',
    'cuisine' => 'Абхазская кухня: хачапури, аджика, свежие морепродукты, местные вина, сыры, мед, орехи. Смесь кавказских и средиземноморских традиций.',
    'culture' => 'Уникальная культура с кавказскими традициями. Гостеприимство местных жителей, древние обычаи, богатая природа, курорты и санатории.',
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
    $page_title = 'Абхазия - Travel Hub | Туры, отели, отдых';
    $page_description = 'Туры в Абхазия от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Абхазию, отдых в Абхазии, туры Сухум';
    $page_keywords = 'туры в Абхазию, отдых в Абхазии, туры Сухум, Travel Hub, туры, отдых, отели';
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
        ['name' => 'Абхазия', 'url' => '']
    ];
    $schema_data = [
        '@type' => 'Place',
        'name' => 'Абхазия',
        'description' => 'Туры в Абхазия от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Абхазию, отдых в Абхазии, туры Сухум'
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
    <?php $th_country_cta_source = 'country_abkhazia'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>

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