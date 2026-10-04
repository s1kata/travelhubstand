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
  'slug' => 'vietnam',
  'name' => 'Вьетнам',
  'nameEn' => 'Vietnam',
  'flag' => '🇻🇳',
  'description' => 'Вьетнам — страна с богатой историей, потрясающими пляжами, уникальной культурой и одной из лучших кухонь в мире.',
  'bio' => 'Вьетнам расположен в Юго-Восточной Азии на побережье Южно-Китайского моря. Страна имеет форму вытянутой буквы S и простирается на 1650 км с севера на юг. Вьетнам славится своими потрясающими пляжами, бухтой Халонг, древними храмами и невероятно вкусной кухней.

От шумного Ханоя на севере до динамичного Хошимина на юге, от горных племен Сапы до пляжей Нячанга и Фукуока — Вьетнам предлагает невероятное разнообразие впечатлений. Страна известна своей историей, включая войну во Вьетнаме, но сегодня это мирная и гостеприимная страна, привлекающая миллионы туристов.

Вьетнамская кухня считается одной из лучших в мире — свежие морепродукты, ароматные травы, баланс вкусов создают неповторимые блюда. Популярны фо (суп с лапшой), спринг-роллы, вьетнамский кофе и многое другое.',
  'images' => 
  array(
    0 => '../img/вьетнам/0d1951e284d67cca12e1f58edebf5e0a.jpg',
    1 => '../img/вьетнам/28dcf89f0847ebdd7843300473cdc348.jpg',
    2 => '../img/вьетнам/65b04b931680a73bb108ed037549fb87.jpg',
    3 => '../img/вьетнам/a4625e465dc90fd548b8d4a93fe07956.jpg',
    4 => '../img/вьетнам/d22324f7162c63a0f8e0dd15f43239c1.jpg'
  ),
  'highlights' => 
  array(
    0 => 'Бухта Халонг',
    1 => 'Потрясающие пляжи',
    2 => 'Уникальная кухня',
    3 => 'Богатая история и культура',
  ),
  'bestTime' => 'Ноябрь-апрель (сухой сезон)',
  'currency' => 'Вьетнамский донг (VND)',
  'language' => 'Вьетнамский',
  'visa' => 'Виза не требуется для граждан РФ (до 15 дней)',
  'detailedInfo' => 
  array(
    'climate' => 'Тропический муссонный климат. Север: прохладная зима (15-20°C), жаркое лето (30-35°C). Юг: жарко круглый год (25-35°C). Сезон дождей: май-октябрь.',
    'attractions' => 'Бухта Халонг (ЮНЕСКО), Ханой (старый квартал), Хошимин, Хюэ (императорский город), Хойан, пляжи Нячанга и Фукуока, дельта Меконга.',
    'activities' => 'Пляжный отдых, дайвинг и снорклинг, экскурсии к историческим памятникам, круизы по бухте Халонг, мотоциклетные туры, дегустация вьетнамской кухни.',
    'cuisine' => 'Вьетнамская кухня: фо (суп с лапшой), спринг-роллы, бань ми (сэндвичи), вьетнамский кофе, свежие морепродукты, ароматные травы, баланс вкусов.',
    'culture' => 'Богатая история с влиянием Китая и Франции. Гостеприимство местных жителей, традиционные ремесла, водные куклы, буддийские храмы и современные города.',
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
    $page_title = 'Вьетнам - Travel Hub | Туры, отели, отдых';
    $page_description = 'Туры в Вьетнам от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры во Вьетнам, отдых во Вьетнаме, туры Нячанг, туры Фукуок';
    $page_keywords = 'туры во Вьетнам, отдых во Вьетнаме, туры Нячанг, туры Фукуок, Travel Hub, туры, отдых, отели';
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
        ['name' => 'Вьетнам', 'url' => '']
    ];
    $schema_data = [
        '@type' => 'Place',
        'name' => 'Вьетнам',
        'description' => 'Туры в Вьетнам от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры во Вьетнам, отдых во Вьетнаме, туры Нячанг, туры Фукуок'
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
    <?php $th_country_cta_source = 'country_vietnam'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>

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