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
  'slug' => 'bahrain',
  'name' => 'Бахрейн',
  'nameEn' => 'Bahrain',
  'flag' => '🇧🇭',
  'description' => 'Бахрейн — островное государство в Персидском заливе с современной инфраструктурой, богатой историей и роскошными курортами.',
  'bio' => 'Бахрейн — небольшое островное государство в Персидском заливе, известное своей современной инфраструктурой, богатой историей и роскошными курортами. Страна славится своими небоскребами, торговыми центрами, гоночной трассой Формулы-1 и уникальным сочетанием традиций и современности.

От столицы Манамы с ее современными зданиями до древних крепостей и археологических памятников — Бахрейн предлагает разнообразные впечатления. Страна известна своими роскошными отелями, отличными условиями для дайвинга и гостеприимством местных жителей.

Бахрейнская кухня — это смесь арабских, персидских и индийских влияний. Популярны мачбус (рис с мясом), свежие морепродукты, финики, арабский кофе.',
  'images' => 
  array(
    0 => '../img/бахрейн/photo_2025-12-02_23-32-08.jpg',
    1 => '../img/бахрейн/photo_2025-12-02_23-32-09.jpg',
    2 => '../img/бахрейн/photo_2025-12-02_23-32-10.jpg',
    3 => '../img/бахрейн/photo_2025-12-02_23-32-11.jpg',
    4 => '../img/бахрейн/photo_2025-12-02_23-32-12.jpg',
    5 => '../img/бахрейн/photo_2025-12-02_23-32-13.jpg'
  ),
  'highlights' => 
  array(
    0 => 'Роскошные курорты',
    1 => 'Современная инфраструктура',
    2 => 'Богатая история',
    3 => 'Отличный дайвинг',
  ),
  'bestTime' => 'Октябрь-апрель (лучшее время)',
  'currency' => 'Бахрейнский динар (BHD)',
  'language' => 'Арабский, английский',
  'visa' => 'Требуется виза для граждан РФ',
  'detailedInfo' => 
  array(
    'climate' => 'Пустынный климат. Лето очень жаркое (35-40°C), зима теплая (15-25°C). Лучшее время: октябрь-апрель. Осадки редкие. Высокая влажность летом.',
    'attractions' => 'Манама (столица), форт Бахрейн, Дерево жизни, гоночная трасса Формулы-1, небоскребы, торговые центры, древние крепости, археологические памятники.',
    'activities' => 'Дайвинг, пляжный отдых, шопинг, экскурсии к историческим памятникам, гольф, спа-процедуры, посещение традиционных рынков, яхтинг.',
    'cuisine' => 'Бахрейнская кухня: мачбус (рис с мясом), свежие морепродукты, финики, арабский кофе, хумус, фалафель. Смесь арабских, персидских и индийских влияний.',
    'culture' => 'Современная страна с богатой историей. Уникальное сочетание традиций и современности, гостеприимство, безопасность, высокий уровень сервиса.',
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
    $page_title = 'Бахрейн - Travel Hub | Туры, отели, отдых';
    $page_description = 'Туры в Бахрейн от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Бахрейн, отдых в Бахрейне';
    $page_keywords = 'туры в Бахрейн, отдых в Бахрейне, Travel Hub, туры, отдых, отели';
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
        ['name' => 'Бахрейн', 'url' => '']
    ];
    $schema_data = [
        '@type' => 'Place',
        'name' => 'Бахрейн',
        'description' => 'Туры в Бахрейн от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Бахрейн, отдых в Бахрейне'
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
    <?php $th_country_cta_source = 'country_bahrain'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>

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