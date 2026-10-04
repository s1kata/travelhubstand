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
  'slug' => 'indonesia',
  'name' => 'Индонезия',
  'nameEn' => 'Indonesia',
  'flag' => '🇮🇩',
  'description' => 'Индонезия — крупнейший архипелаг в мире с тропическими пляжами, вулканами, богатой культурой и уникальной природой.',
  'bio' => 'Индонезия — крупнейший архипелаг в мире, состоящий из более чем 17 тысяч островов. Страна расположена в Юго-Восточной Азии и является четвертой по численности населения страной в мире. Индонезия славится своими тропическими пляжами, вулканами, богатой культурой и уникальной природой.

Бали — самый популярный остров для туристов, известный своими пляжами, храмами, рисовыми террасами и роскошными курортами. Но Индонезия — это не только Бали. Ява с вулканами и древними храмами, Суматра с тропическими лесами, Комодо с драконами — каждый остров имеет свою уникальную атмосферу.

Индонезийская кухня разнообразна и ароматна, с региональными вариациями. Популярны наси-горенг (жареный рис), сате (шашлыки), гадо-гадо (овощной салат), ренданг (острое мясное блюдо).',
  'images' => 
  array(
    0 => '../img/индонезия/0d0460b8cfd98f78d4ae7379d45dfb57.jpg',
    1 => '../img/индонезия/18934d770ccf0492ac89346283f05e1d.jpg',
    2 => '../img/индонезия/1e4662ddf1f7421657cd10e998677623.jpg',
    3 => '../img/индонезия/9b7c0e29fac713df2cd5b2eb9441721f.jpg',
    4 => '../img/индонезия/d43f0acd730d5436fa50d05a070b6945.jpg'
  ),
  'highlights' => 
  array(
    0 => 'Тропические пляжи Бали',
    1 => 'Вулканы и природа',
    2 => 'Богатая культура',
    3 => 'Уникальная кухня',
  ),
  'bestTime' => 'Апрель-октябрь (сухой сезон)',
  'currency' => 'Индонезийская рупия (IDR)',
  'language' => 'Индонезийский',
  'visa' => 'Виза не требуется для граждан РФ (до 30 дней)',
  'detailedInfo' => 
  array(
    'climate' => 'Тропический климат. Температура круглый год: 25-30°C. Сухой сезон: апрель-октябрь. Сезон дождей: ноябрь-март (кратковременные ливни).',
    'attractions' => 'Бали (храмы, рисовые террасы), Боробудур и Прамбанан на Яве, вулкан Бромо, остров Комодо (драконы), Суматра (тропические леса), Джакарта.',
    'activities' => 'Пляжный отдых, дайвинг и снорклинг, восхождение на вулканы, экскурсии к храмам, наблюдение за драконами Комодо, серфинг, спа-процедуры, экотуризм.',
    'cuisine' => 'Индонезийская кухня: наси-горенг (жареный рис), сате (шашлыки), гадо-гадо (овощной салат), ренданг (острое мясо), бами горенг, свежие морепродукты.',
    'culture' => 'Многонациональная страна с богатой культурой. Балийские храмы, традиционные танцы, батик, гамелан (музыка), гостеприимство местных жителей.',
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
    $page_title = 'Индонезия - Travel Hub | Туры, отели, отдых';
    $page_description = 'Туры в Индонезия от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Индонезию, отдых в Индонезии, туры Бали';
    $page_keywords = 'туры в Индонезию, отдых в Индонезии, туры Бали, Travel Hub, туры, отдых, отели';
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
        ['name' => 'Индонезия', 'url' => '']
    ];
    $schema_data = [
        '@type' => 'Place',
        'name' => 'Индонезия',
        'description' => 'Туры в Индонезия от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Индонезию, отдых в Индонезии, туры Бали'
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
    <?php $th_country_cta_source = 'country_indonesia'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>

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