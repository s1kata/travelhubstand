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
  'slug' => 'india',
  'name' => 'Индия',
  'nameEn' => 'India',
  'flag' => '🇮🇳',
  'description' => 'Индия — страна контрастов с богатой историей, разнообразной культурой, древними храмами и потрясающими природными красотами.',
  'bio' => 'Индия — вторая по численности населения страна в мире с более чем 1,4 миллиарда жителей. Страна занимает огромную территорию в Южной Азии и является колыбелью одной из древнейших цивилизаций. Индия славится своим культурным разнообразием, древними храмами, богатой историей и уникальными традициями.

От Тадж-Махала в Агре до храмов Варанаси, от пляжей Гоа до Гималаев, от шумного Дели до спокойного Кералы — Индия предлагает невероятное разнообразие впечатлений. Страна известна своими религиозными центрами, йогой, аюрведой, разнообразной кухней и гостеприимством местных жителей.

Индийская кухня — одна из самых разнообразных в мире, с региональными вариациями от острой южной до сладкой бенгальской. Популярны карри, наан, самоса, ласси и множество вегетарианских блюд.',
  'images' => 
  array(
    0 => '../img/индия/0d87b5b6b3b2cb8e7662da522fc1acef.jpg',
    1 => '../img/индия/62d93c379888bddba507e6e3e960178b.jpg',
    2 => '../img/индия/95cd5794f71966bf3c0e1e341802d525.jpg',
    3 => '../img/индия/e21d2ddf32e1a2e6a7cd0ea18f1285a4.jpg',
    4 => '../img/индия/e6db22d8dc84cd25b5977292bc5ac2a0.jpg',
    7 => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1920&q=80'
  ),
  'highlights' => 
  array(
    0 => 'Тадж-Махал',
    1 => 'Древние храмы',
    2 => 'Разнообразная культура',
    3 => 'Йога и аюрведа',
  ),
  'bestTime' => 'Октябрь-март (сухой сезон)',
  'currency' => 'Индийская рупия (INR)',
  'language' => 'Хинди, английский',
  'visa' => 'Требуется виза для граждан РФ',
  'detailedInfo' => 
  array(
    'climate' => 'Разнообразный климат: от тропического на юге до альпийского в Гималаях. Сухой сезон: октябрь-март (20-30°C). Сезон дождей: июнь-сентябрь.',
    'attractions' => 'Тадж-Махал в Агре, храмы Варанаси, Дели (Красный форт), Мумбаи, пляжи Гоа, Гималаи, Раджастан (дворцы), Керала (бэквотеры), Золотой храм в Амритсаре.',
    'activities' => 'Йога и медитация, аюрведа, экскурсии к храмам и дворцам, пляжный отдых в Гоа, треккинг в Гималаях, сафари, шопинг, дегустация индийской кухни.',
    'cuisine' => 'Индийская кухня: карри, наан, самоса, бирьяни, ласси, чай масала. Региональные кухни: южная (острая), северная (тандури), бенгальская (сладкая). Много вегетарианских блюд.',
    'culture' => 'Одна из древнейших цивилизаций с богатым культурным наследием. Разнообразие религий, языков, традиций. Йога, аюрведа, классические танцы, музыка, архитектура.',
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
    $page_title = 'Индия - Travel Hub | Туры, отели, отдых';
    $page_description = 'Туры в Индия от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Индию, отдых в Индии, туры Гоа';
    $page_keywords = 'туры в Индию, отдых в Индии, туры Гоа, Travel Hub, туры, отдых, отели';
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
        ['name' => 'Индия', 'url' => '']
    ];
    $schema_data = [
        '@type' => 'Place',
        'name' => 'Индия',
        'description' => 'Туры в Индия от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Индию, отдых в Индии, туры Гоа'
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
    <?php $th_country_cta_source = 'country_india'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>

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