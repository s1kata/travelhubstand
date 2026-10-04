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
  'slug' => 'armenia',
  'name' => 'Армения',
  'nameEn' => 'Armenia',
  'flag' => '🇦🇲',
  'description' => 'Армения — древняя страна с богатой историей, уникальной культурой, горными пейзажами и гостеприимными людьми.',
  'bio' => 'Армения — страна в Закавказье, известная как одна из древнейших цивилизаций мира. Страна славится своей богатой историей, уникальной культурой, древними храмами, горными пейзажами и гостеприимством местных жителей. Ереван — столица с розовыми зданиями из туфа, древние монастыри, озеро Севан — все это делает Армению уникальным направлением.

От столицы Еревана до древних монастырей Гегард и Хор Вирап, от горы Арарат до озера Севан — Армения предлагает разнообразные впечатления. Страна известна своим коньяком, вином, фруктами и гостеприимством.

Армянская кухня — одна из древнейших в мире, известная своими мясными блюдами, лавашем, долмой, свежими овощами и травами.',
  'images' => 
  array(
    0 => '../img/армения/photo_2025-12-02_23-31-35.jpg',
    1 => '../img/армения/photo_2025-12-02_23-31-36.jpg',
    2 => '../img/армения/photo_2025-12-02_23-31-37.jpg',
    3 => '../img/армения/photo_2025-12-02_23-31-38.jpg',
    4 => '../img/армения/photo_2025-12-02_23-31-39.jpg'
  ),
  'highlights' => 
  array(
    0 => 'Древние монастыри',
    1 => 'Горные пейзажи',
    2 => 'Богатая культура',
    3 => 'Гостеприимство',
  ),
  'bestTime' => 'Май-октябрь (лучшее время)',
  'currency' => 'Армянский драм (AMD)',
  'language' => 'Армянский',
  'visa' => 'Виза не требуется для граждан РФ (до 180 дней)',
  'detailedInfo' => 
  array(
    'climate' => 'Континентальный климат. Лето теплое (20-30°C), зима холодная (-5 до -10°C). В горах прохладнее. Лучшее время: май-октябрь. Низкая влажность.',
    'attractions' => 'Ереван (столица), монастыри Гегард и Хор Вирап, гора Арарат, озеро Севан, храм Гарни, Эчмиадзин, крепость Амберд, каньон Дебед.',
    'activities' => 'Экскурсии к древним монастырям, горные походы, посещение озера Севан, дегустация коньяка и вина, культурные экскурсии, шопинг, спа-процедуры.',
    'cuisine' => 'Армянская кухня: хаш, долма, лаваш, кебабы, свежие овощи и травы, коньяк, вино, фрукты. Одна из древнейших кухонь в мире с богатыми традициями.',
    'culture' => 'Одна из древнейших цивилизаций мира. Богатое культурное наследие, древние монастыри, гостеприимство, традиционные ремесла, музыка, литература.',
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
    $page_title = 'Армения - Travel Hub | Туры, отели, отдых';
    $page_description = 'Туры в Армения от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Армению, отдых в Армении, туры Ереван';
    $page_keywords = 'туры в Армению, отдых в Армении, туры Ереван, Travel Hub, туры, отдых, отели';
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
        ['name' => 'Армения', 'url' => '']
    ];
    $schema_data = [
        '@type' => 'Place',
        'name' => 'Армения',
        'description' => 'Туры в Армения от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Армению, отдых в Армении, туры Ереван'
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
    <?php $th_country_cta_source = 'country_armenia'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>

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