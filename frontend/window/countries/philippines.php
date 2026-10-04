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
  'slug' => 'philippines',
  'name' => 'Филиппины',
  'nameEn' => 'Philippines',
  'flag' => '🇵🇭',
  'description' => 'Филиппины — тропический архипелаг с потрясающими пляжами, кристально чистой водой, дружелюбными людьми и уникальной культурой.',
  'bio' => 'Филиппины — архипелаг из более чем 7000 островов в западной части Тихого океана. Страна славится своими потрясающими пляжами с белоснежным песком, кристально чистой водой, идеальными условиями для дайвинга и снорклинга. Филиппины известны дружелюбием местных жителей и уникальной культурой, сочетающей азиатские и испанские влияния.

Популярные направления включают Боракай с его знаменитым белым пляжем, Палаван с подземной рекой, Себу с историческими достопримечательностями, Бохол с шоколадными холмами. Каждый остров предлагает что-то уникальное — от пляжного отдыха до экотуризма, от дайвинга до культурных экскурсий.

Филиппинская кухня — это смесь малайских, китайских, испанских и американских влияний. Популярны адобо (тушеное мясо), синиган (кислый суп), лечон (жареный поросенок), хало-хало (десерт).',
  'images' => 
  array(
    0 => '../img/филипины/4747920dc098d6df1521b4a5105e48ed.jpg',
    1 => '../img/филипины/5a77050f3a6736616f1a2d3a83a97d98.jpg',
    2 => '../img/филипины/6f9fff88612ece306e71b9f3bd1945b1.jpg',
    3 => '../img/филипины/c5778f40507f5bb4dee49709042456f1.jpg',
    4 => '../img/филипины/d7535ea4feedd6d1349a7d2b28c790eb.jpg'
  ),
  'highlights' => 
  array(
    0 => 'Потрясающие пляжи',
    1 => 'Идеальный дайвинг',
    2 => 'Дружелюбные люди',
    3 => 'Уникальная культура',
  ),
  'bestTime' => 'Ноябрь-апрель (сухой сезон)',
  'currency' => 'Филиппинское песо (PHP)',
  'language' => 'Филиппинский, английский',
  'visa' => 'Виза не требуется для граждан РФ (до 30 дней)',
  'detailedInfo' => 
  array(
    'climate' => 'Тропический климат. Температура круглый год: 25-32°C. Сухой сезон: ноябрь-апрель. Сезон дождей: май-октябрь. Влажность высокая.',
    'attractions' => 'Боракай (белый пляж), Палаван (подземная река), Себу (исторические достопримечательности), Бохол (шоколадные холмы), Манила, рисовые террасы Банауэ.',
    'activities' => 'Пляжный отдых, дайвинг и снорклинг, кайтсерфинг, экскурсии к природным достопримечательностям, посещение исторических мест, шопинг, спа-процедуры.',
    'cuisine' => 'Филиппинская кухня: адобо (тушеное мясо), синиган (кислый суп), лечон (жареный поросенок), хало-хало (десерт), свежие морепродукты, тропические фрукты.',
    'culture' => 'Уникальная культура с азиатскими и испанскими влияниями. Дружелюбные люди, традиционные фестивали, музыка, танцы, гостеприимство.',
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
    $page_title = 'Филиппины - Travel Hub | Туры, отели, отдых';
    $page_description = 'Туры в Филиппины от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры на Филиппины, отдых на Филиппинах';
    $page_keywords = 'туры на Филиппины, отдых на Филиппинах, Travel Hub, туры, отдых, отели';
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
        ['name' => 'Филиппины', 'url' => '']
    ];
    $schema_data = [
        '@type' => 'Place',
        'name' => 'Филиппины',
        'description' => 'Туры в Филиппины от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры на Филиппины, отдых на Филиппинах'
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
    <?php $th_country_cta_source = 'country_philippines'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>

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