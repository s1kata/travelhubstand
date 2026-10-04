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
  'slug' => 'tanzania',
  'name' => 'Танзания',
  'nameEn' => 'Tanzania',
  'flag' => '🇹🇿',
  'description' => 'Танзания — страна сафари, где можно увидеть Большую пятерку, подняться на Килиманджаро и отдохнуть на острове Занзибар.',
  'bio' => 'Танзания — страна в Восточной Африке, известная своими национальными парками, сафари и островом Занзибар. Страна славится возможностью увидеть "Большую пятерку" (лев, слон, буйвол, носорог, леопард) в их естественной среде обитания.

Серенгети, Нгоронгоро, Тарангире — эти национальные парки предлагают незабываемые сафари-приключения. Килиманджаро — самая высокая гора Африки привлекает альпинистов со всего мира. Занзибар — тропический остров с потрясающими пляжами и богатой историей.

Танзания — это сочетание дикой природы, культуры и пляжного отдыха. От сафари в саванне до дайвинга у побережья, от восхождения на Килиманджаро до отдыха на Занзибаре.',
  'images' => 
  array(
    0 => '../img/танзания/0c631e48a4f617df070155bdd186080b.jpg',
    1 => '../img/танзания/57ae40a5fc33d8b59ef8ffb3b62b5567.jpg',
    2 => '../img/танзания/8c1f5d7116e02bb9617dd8790fd513ad.jpg',
    3 => '../img/танзания/a8225f9f7782746209a42f5a9219bde4.jpg',
    4 => '../img/танзания/b99dcf391b6780af5f030614f7275daa.jpg'
  ),
  'highlights' => 
  array(
    0 => 'Сафари и дикая природа',
    1 => 'Килиманджаро',
    2 => 'Остров Занзибар',
    3 => 'Большая пятерка',
  ),
  'bestTime' => 'Июнь-октябрь (сухой сезон)',
  'currency' => 'Танзанийский шиллинг (TZS)',
  'language' => 'Суахили, английский',
  'visa' => 'Требуется виза для граждан РФ',
  'detailedInfo' => 
  array(
    'climate' => 'Тропический климат на побережье, умеренный в горах. Сухой сезон: июнь-октябрь (20-30°C). Сезон дождей: март-май и ноябрь-декабрь. На Занзибаре: 25-30°C круглый год.',
    'attractions' => 'Серенгети, Нгоронгоро, Килиманджаро, Занзибар, Тарангире, озеро Маньяра, национальные парки с дикой природой, пляжи Занзибара.',
    'activities' => 'Сафари в национальных парках, восхождение на Килиманджаро, пляжный отдых на Занзибаре, дайвинг, наблюдение за Большой пятеркой, культурные экскурсии.',
    'cuisine' => 'Танзанийская кухня: угали (кукурузная каша), ньяма чома (жареное мясо), свежие морепродукты на Занзибаре, тропические фрукты, специи. Влияние арабской и индийской кухни.',
    'culture' => 'Многонациональная страна с богатой культурой. Традиции масаев, суахили, гостеприимство, музыка, танцы, сочетание африканских и арабских влияний.',
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
    $page_title = 'Танзания - Travel Hub | Туры, отели, отдых';
    $page_description = 'Туры в Танзания от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Танзанию, отдых в Танзании, сафари';
    $page_keywords = 'туры в Танзанию, отдых в Танзании, сафари, Travel Hub, туры, отдых, отели';
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
        ['name' => 'Танзания', 'url' => '']
    ];
    $schema_data = [
        '@type' => 'Place',
        'name' => 'Танзания',
        'description' => 'Туры в Танзания от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Танзанию, отдых в Танзании, сафари'
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
    <?php $th_country_cta_source = 'country_tanzania'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>

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