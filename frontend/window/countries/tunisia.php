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
  'slug' => 'tunisia',
  'name' => 'Тунис',
  'nameEn' => 'Tunisia',
  'flag' => '🇹🇳',
  'description' => 'Тунис — страна на побережье Средиземного моря с богатой историей, пляжами, пустыней Сахара и уникальной культурой.',
  'bio' => 'Тунис — страна в Северной Африке на побережье Средиземного моря. Страна славится своими пляжами, богатой историей, включая древний Карфаген, пустыней Сахара и уникальной культурой, сочетающей арабские, берберские и французские влияния.

От столицы Туниса с ее мединой (старым городом) до курортов Хаммамета и Сусса, от пустыни Сахара с оазисами до древних руин Карфагена — Тунис предлагает разнообразные впечатления. Страна известна своими отелями "все включено", доступными ценами и гостеприимством местных жителей.

Тунисская кухня — это смесь средиземноморской и арабской кухонь. Популярны кус-кус, тажин, брик (жареные пирожки), свежие морепродукты, оливки.',
  'images' => 
  array(
    0 => '../img/тунис/2e4143bbfb3ce492ea7f52dfc2b698ed.jpg',
    1 => '../img/тунис/723391ea4fba6b9669cf24acfceb9e5f.jpg',
    2 => '../img/тунис/8b22a5eece35854fb5fd732d6b53385e.jpg',
    3 => '../img/тунис/ec300994a725f53ce183961fe26b35a2.jpg',
    4 => '../img/тунис/efd262241b1b2fb443de56a079b4ace9.jpg'
  ),
  'highlights' => 
  array(
    0 => 'Пляжи Средиземного моря',
    1 => 'Пустыня Сахара',
    2 => 'Богатая история',
    3 => 'Доступные цены',
  ),
  'bestTime' => 'Апрель-октябрь (пляжный сезон)',
  'currency' => 'Тунисский динар (TND)',
  'language' => 'Арабский, французский',
  'visa' => 'Виза не требуется для граждан РФ (до 90 дней)',
  'detailedInfo' => 
  array(
    'climate' => 'Средиземноморский климат на побережье. Лето жаркое (25-35°C), зима мягкая (10-20°C). Пустынный климат во внутренних районах. Пляжный сезон: апрель-октябрь.',
    'attractions' => 'Карфаген (древние руины), Тунис (медина), Хаммамет, Сусс, пустыня Сахара, оазисы, амфитеатр в Эль-Джеме, остров Джерба, Сиди-Бу-Саид.',
    'activities' => 'Пляжный отдых, экскурсии к древним руинам, сафари по пустыне, посещение оазисов, шопинг, спа-процедуры, талассотерапия, дайвинг.',
    'cuisine' => 'Тунисская кухня: кус-кус, тажин, брик (жареные пирожки), свежие морепродукты, оливки, харисса (острый соус). Смесь средиземноморской и арабской кухонь.',
    'culture' => 'Богатая история с арабскими, берберскими и французскими влияниями. Гостеприимство местных жителей, традиционные базары, современные курорты.',
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
    $page_title = 'Тунис - Travel Hub | Туры, отели, отдых';
    $page_description = 'Туры в Тунис от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Тунис, отдых в Тунисе';
    $page_keywords = 'туры в Тунис, отдых в Тунисе, Travel Hub, туры, отдых, отели';
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
        ['name' => 'Тунис', 'url' => '']
    ];
    $schema_data = [
        '@type' => 'Place',
        'name' => 'Тунис',
        'description' => 'Туры в Тунис от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Тунис, отдых в Тунисе'
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
    <?php $th_country_cta_source = 'country_tunisia'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>

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