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
  'slug' => 'venezuela',
  'name' => 'Венесуэла',
  'nameEn' => 'Venezuela',
  'flag' => '🇻🇪',
  'description' => 'Венесуэла — страна в Южной Америке с потрясающей природой, водопадом Анхель и карибским побережьем.',
  'bio' => 'Венесуэла — страна в Южной Америке, известная своей потрясающей природой, включая самый высокий водопад в мире — Анхель, карибское побережье с тропическими пляжами и уникальной экосистемой. Страна славится своими национальными парками, дикой природой и гостеприимством местных жителей.

От водопада Анхель до карибских островов, от горных районов до тропических лесов — Венесуэла предлагает невероятное разнообразие природных красот. Страна известна своими пляжами, возможностями для экотуризма и уникальной флорой и фауной.

Венесуэльская кухня — это смесь испанских, африканских и индейских влияний. Популярны арепа (кукурузные лепешки), пабельон (национальное блюдо), свежие морепродукты, тропические фрукты.',
  'images' => 
  array(
    0 => '../img/венесуэла/64acd63a88ec243ac9ebc4b0f065279c.jpg',
    1 => '../img/венесуэла/7973f28e032f4f743f94c75690dcf237.jpg',
    2 => '../img/венесуэла/95698f7cbeb29d436ccb7014ea1a439e.jpg',
    3 => '../img/венесуэла/9cc66d2c84207af631a3d6245f3b3e0b.jpg',
    4 => '../img/венесуэла/a62dd2564ef067a57e7adee4cb01b758.jpg'
  ),
  'highlights' => 
  array(
    0 => 'Водопад Анхель',
    1 => 'Карибское побережье',
    2 => 'Уникальная природа',
    3 => 'Экотуризм',
  ),
  'bestTime' => 'Декабрь-апрель (сухой сезон)',
  'currency' => 'Венесуэльский боливар (VES)',
  'language' => 'Испанский',
  'visa' => 'Требуется виза для граждан РФ',
  'detailedInfo' => 
  array(
    'climate' => 'Тропический климат. Температура на побережье: 25-30°C круглый год. В горах прохладнее. Сухой сезон: декабрь-апрель. Сезон дождей: май-ноябрь.',
    'attractions' => 'Водопад Анхель (самый высокий в мире), карибские острова, национальные парки, горы, тропические леса, Лос-Рокес, Мерида, Каракас.',
    'activities' => 'Экотуризм, посещение водопада Анхель, пляжный отдых на карибских островах, треккинг, наблюдение за дикой природой, дайвинг, рафтинг.',
    'cuisine' => 'Венесуэльская кухня: арепа (кукурузные лепешки), пабельон (национальное блюдо), свежие морепродукты, тропические фрукты. Смесь испанских, африканских и индейских влияний.',
    'culture' => 'Богатая культура с латиноамериканскими традициями. Музыка, танцы, гостеприимство, уникальная природа, разнообразие экосистем.',
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
    $page_title = 'Венесуэла - Travel Hub | Туры, отели, отдых';
    $page_description = 'Туры в Венесуэла от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Венесуэлу, отдых в Венесуэле';
    $page_keywords = 'туры в Венесуэлу, отдых в Венесуэле, Travel Hub, туры, отдых, отели';
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
        ['name' => 'Венесуэла', 'url' => '']
    ];
    $schema_data = [
        '@type' => 'Place',
        'name' => 'Венесуэла',
        'description' => 'Туры в Венесуэла от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Венесуэлу, отдых в Венесуэле'
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
    <?php $th_country_cta_source = 'country_venezuela'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>

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