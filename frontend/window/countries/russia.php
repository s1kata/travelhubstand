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
  'slug' => 'russia',
  'name' => 'Россия',
  'nameEn' => 'Russia',
  'flag' => '🇷🇺',
  'description' => 'Россия — самая большая страна в мире, предлагающая невероятное разнообразие: от исторических городов до природных чудес, от арктических просторов до субтропических курортов.',
  'bio' => 'Россия — крупнейшая страна в мире, занимающая площадь более 17 миллионов квадратных километров и простирающаяся от Европы до Азии. Страна славится богатой историей, культурным наследием, уникальной природой и разнообразием климатических зон.

Россия предлагает невероятное разнообразие для путешественников: от исторических городов Золотого кольца до современного Санкт-Петербурга, от суровых просторов Сибири до курортов Черноморского побережья. Москва и Санкт-Петербург привлекают любителей культуры и истории, Сочи и Крым — пляжным отдыхом, Алтай и Байкал — природными красотами.

Россия — это страна контрастов, где древние традиции сочетаются с современностью, а разнообразие культур и народов создает уникальную атмосферу. От арктических просторов до субтропических курортов, от мегаполисов до заповедных уголков природы.',
  'images' => 
  array(
    0 => '../img/россия/076410508be961edd39cbb41af08d2a4.jpg',
    1 => '../img/россия/75643a9d6fe4fe8d1ddfbe4adf9eb0a8.jpg',
    2 => '../img/россия/7a88cb652a1fc4b36c82958ad26fc80d.jpg',
    3 => '../img/россия/8a0f4800a620f27380798d59b7e2686c.jpg',
    4 => '../img/россия/cc2d048394e535b572f04c62e8a6ddaf.jpg'
  ),
  'highlights' => 
  array(
    0 => 'Богатое культурное наследие',
    1 => 'Разнообразие природных зон',
    2 => 'Исторические города',
    3 => 'Курорты Черноморского побережья',
  ),
  'bestTime' => 'Круглый год (зависит от региона)',
  'currency' => 'Российский рубль (RUB)',
  'language' => 'Русский',
  'visa' => 'Не требуется для граждан РФ',
  'detailedInfo' => 
  array(
    'climate' => 'Разнообразный климат: от арктического на севере до субтропического на юге. Лето в центральной части: 20-25°C, зима: -10 до -15°C. На Черноморском побережье: лето 25-30°C, зима 5-10°C.',
    'attractions' => 'Москва (Кремль, Красная площадь), Санкт-Петербург (Эрмитаж, Петергоф), Золотое кольцо, озеро Байкал, Алтай, Сочи, Крым, Камчатка.',
    'activities' => 'Культурные экскурсии, пляжный отдых на Черноморском побережье, горнолыжный спорт, экотуризм, круизы по рекам, посещение исторических городов.',
    'cuisine' => 'Русская кухня: борщ, пельмени, блины, икра, водка. Региональные кухни: кавказская, сибирская, уральская. Популярны также международные рестораны.',
    'culture' => 'Богатое культурное наследие с многовековой историей. Разнообразие народов и традиций. Театры, музеи, музыка, литература мирового уровня.',
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
    $page_title = 'Россия - Travel Hub | Туры, отели, отдых';
    $page_description = 'Туры в Россия от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры по России, отдых в России, внутренний туризм';
    $page_keywords = 'туры по России, отдых в России, внутренний туризм, Travel Hub, туры, отдых, отели';
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
        ['name' => 'Россия', 'url' => '']
    ];
    $schema_data = [
        '@type' => 'Place',
        'name' => 'Россия',
        'description' => 'Туры в Россия от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры по России, отдых в России, внутренний туризм'
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
    <?php $th_country_cta_source = 'country_russia'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>

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