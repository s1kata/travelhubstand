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
  'slug' => 'china',
  'name' => 'Китай',
  'nameEn' => 'China',
  'flag' => '🇨🇳',
  'description' => 'Китай — древняя цивилизация с богатой историей, уникальной культурой, современными мегаполисами и потрясающими природными достопримечательностями.',
  'bio' => 'Китай — одна из древнейших цивилизаций мира с историей более 5000 лет. Страна занимает огромную территорию в Восточной Азии и является самой населенной страной в мире. Китай сочетает древние традиции с современными технологиями, создавая уникальный культурный ландшафт.

От Великой Китайской стены до современных небоскребов Шанхая, от древних храмов до высокоскоростных поездов — Китай предлагает невероятное разнообразие впечатлений. Пекин, Шанхай, Гуанчжоу, Сиань, Чэнду — каждый город имеет свою уникальную атмосферу и достопримечательности.

Китай славится своей кухней, которая считается одной из лучших в мире, богатой культурой, включающей традиционные искусства, музыку, театр, и потрясающими природными достопримечательностями, такими как горы Хуаншань, река Ли, пустыня Гоби.',
  'images' => 
  array(
    0 => '../img/китай/photo_2025-12-02_23-30-37.jpg',
    1 => '../img/китай/photo_2025-12-02_23-30-38.jpg',
    2 => '../img/китай/photo_2025-12-02_23-30-39.jpg',
    3 => '../img/китай/photo_2025-12-02_23-30-41.jpg',
    4 => '../img/китай/photo_2025-12-02_23-30-43.jpg'
  ),
  'highlights' => 
  array(
    0 => 'Великая Китайская стена',
    1 => 'Уникальная культура и традиции',
    2 => 'Современные мегаполисы',
    3 => 'Потрясающая кухня',
  ),
  'bestTime' => 'Апрель-май, сентябрь-октябрь',
  'currency' => 'Китайский юань (CNY)',
  'language' => 'Китайский (мандарин)',
  'visa' => 'Требуется виза для граждан РФ',
  'detailedInfo' => 
  array(
    'climate' => 'Климат разнообразный: от умеренного на севере до субтропического на юге. Лучшее время для посещения: апрель-май и сентябрь-октябрь.',
    'attractions' => 'Великая Китайская стена, Запретный город в Пекине, Терракотовая армия в Сиане, Шанхайская башня, Храм Неба, Западное озеро в Ханчжоу, горы Хуаншань.',
    'activities' => 'Экскурсии по историческим местам, посещение древних храмов и дворцов, шопинг, дегустация местной кухни, круизы по реке Янцзы, посещение чайных плантаций.',
    'cuisine' => 'Китайская кухня: пекинская утка, кисло-сладкая свинина, димсам, лапша, рис, различные виды чая. Региональные кухни: сычуаньская, кантонская, шанхайская.',
    'culture' => 'Одна из древнейших цивилизаций мира с богатой историей, традициями, искусством, философией. Уникальная архитектура, каллиграфия, традиционные праздники и фестивали.',
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
    $page_title = 'Китай - Travel Hub | Туры, отели, отдых';
    $page_description = 'Туры в Китай от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Китай, отдых в Китае, туры Пекин, туры Шанхай';
    $page_keywords = 'туры в Китай, отдых в Китае, туры Пекин, туры Шанхай, Travel Hub, туры, отдых, отели';
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
        ['name' => 'Китай', 'url' => '']
    ];
    $schema_data = [
        '@type' => 'Place',
        'name' => 'Китай',
        'description' => 'Туры в Китай от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Китай, отдых в Китае, туры Пекин, туры Шанхай'
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
    <?php $th_country_cta_source = 'country_china'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>

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