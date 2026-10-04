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
  'slug' => 'montenegro',
  'name' => 'Черногория',
  'nameEn' => 'Montenegro',
  'flag' => '🇲🇪',
  'description' => 'Черногория — балканская страна с потрясающим побережьем Адриатического моря, горами и богатой историей.',
  'bio' => 'Черногория — небольшая страна на Балканском полуострове, расположенная на побережье Адриатического моря. Страна славится своими потрясающими пляжами, горными пейзажами, средневековыми городами и богатой историей. Будва, Котор, Свети-Стефан — эти прибрежные города привлекают туристов своими старыми кварталами и пляжами.

От побережья Адриатики до горных вершин, от средневековых крепостей до современных курортов — Черногория предлагает разнообразные впечатления. Страна известна своими национальными парками, включая Дурмитор и Скадарское озеро, и гостеприимством местных жителей.

Черногория — идеальное место для пляжного отдыха, активного туризма, культурных экскурсий. Страна предлагает отличное соотношение цены и качества.',
  'images' => 
  array(
    0 => '../img/черногорие/07d6f0e65f7ff0ae1570156147bd9c08.jpg',
    1 => '../img/черногорие/852ee1c1a27389bcdb3458b982c9b136.jpg',
    2 => '../img/черногорие/89aa7cd7cbd0c388c659060e12ccc3ed.jpg',
    3 => '../img/черногорие/924db76f6e3d6e447ae72083bb195563.jpg',
    4 => '../img/черногорие/9c8c8e948873483b5b22f7e8258b730b.jpg',
    7 => 'https://images.unsplash.com/photo-1500375592092-40eb2168fd21?auto=format&fit=crop&w=1920&q=80'
  ),
  'highlights' => 
  array(
    0 => 'Побережье Адриатики',
    1 => 'Средневековые города',
    2 => 'Горные пейзажи',
    3 => 'Доступные цены',
  ),
  'bestTime' => 'Май-сентябрь (пляжный сезон)',
  'currency' => 'Евро (EUR)',
  'language' => 'Черногорский',
  'visa' => 'Виза не требуется для граждан РФ (до 30 дней)',
  'detailedInfo' => 
  array(
    'climate' => 'Средиземноморский климат на побережье. Лето теплое (25-30°C), зима мягкая (5-15°C). В горах прохладнее. Пляжный сезон: май-сентябрь.',
    'attractions' => 'Будва (старый город), Котор (залив и крепость), Свети-Стефан, Дурмитор (национальный парк), Скадарское озеро, горные пейзажи, средневековые крепости.',
    'activities' => 'Пляжный отдых, активный туризм в горах, экскурсии к средневековым городам, рафтинг, треккинг, яхтинг, культурные экскурсии, спа-процедуры.',
    'cuisine' => 'Черногорская кухня: свежие морепродукты, пршут (вяленое мясо), каймак (сливки), местные сыры, вино, ракия. Смесь средиземноморской и балканской кухонь.',
    'culture' => 'Богатая история с венецианским и османским влиянием. Гостеприимство местных жителей, традиционные фестивали, музыка, отличное соотношение цены и качества.',
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
    $page_title = 'Черногория - Travel Hub | Туры, отели, отдых';
    $page_description = 'Туры в Черногория от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Черногорию, отдых в Черногории, туры Будва';
    $page_keywords = 'туры в Черногорию, отдых в Черногории, туры Будва, Travel Hub, туры, отдых, отели';
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
        ['name' => 'Черногория', 'url' => '']
    ];
    $schema_data = [
        '@type' => 'Place',
        'name' => 'Черногория',
        'description' => 'Туры в Черногория от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Черногорию, отдых в Черногории, туры Будва'
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
    <?php $th_country_cta_source = 'country_montenegro'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>

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