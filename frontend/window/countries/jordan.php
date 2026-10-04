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
  'slug' => 'jordan',
  'name' => 'Иордания',
  'nameEn' => 'Jordan',
  'flag' => '🇯🇴',
  'description' => 'Иордания — страна на Ближнем Востоке с древним городом Петра, Мертвым морем и богатой историей.',
  'bio' => 'Иордания — страна на Ближнем Востоке, известная древним городом Петра, одним из новых семи чудес света, Мертвым морем с его целебными свойствами и богатой историей. Страна славится своим гостеприимством, безопасностью и уникальными достопримечательностями.

От древней Петры до Мертвого моря, от пустыни Вади-Рум до столицы Аммана — Иордания предлагает невероятное разнообразие впечатлений. Страна известна своими историческими памятниками, возможностями для активного туризма и уникальными природными явлениями.

Иорданская кухня — это смесь арабских и средиземноморских традиций. Популярны мансаф (национальное блюдо), хумус, фалафель, свежие овощи, оливки.',
  'images' => 
  array(
    0 => '../img/иордания/15b34ae290d2e35fe95a4157675c86b9.jpg',
    1 => '../img/иордания/6fc08a0921d8c5bd95104b5a1019ff53.jpg',
    2 => '../img/иордания/9b8b4a226f3225c7e9b2a8003052e714.jpg',
    3 => '../img/иордания/9f6b257df316b32699f06099852f7885.jpg',
    4 => '../img/иордания/a60f4597e3f7266ef5840357da970c55.jpg'
  ),
  'highlights' => 
  array(
    0 => 'Древний город Петра',
    1 => 'Мертвое море',
    2 => 'Пустыня Вади-Рум',
    3 => 'Богатая история',
  ),
  'bestTime' => 'Март-май, сентябрь-ноябрь',
  'currency' => 'Иорданский динар (JOD)',
  'language' => 'Арабский, английский',
  'visa' => 'Требуется виза для граждан РФ',
  'detailedInfo' => 
  array(
    'climate' => 'Пустынный климат. Лето жаркое (30-35°C), зима прохладная (10-15°C). Лучшее время: март-май и сентябрь-ноябрь. Осадки редкие, в основном зимой.',
    'attractions' => 'Петра (древний город, одно из семи чудес света), Мертвое море, пустыня Вади-Рум, Амман (столица), Джераш (римские руины), крепость Карак.',
    'activities' => 'Экскурсии к Петре, плавание в Мертвом море, пустынные сафари, треккинг, дайвинг в Акабе, посещение исторических памятников, спа-процедуры.',
    'cuisine' => 'Иорданская кухня: мансаф (национальное блюдо), хумус, фалафель, свежие овощи, оливки, арабский кофе. Смесь арабских и средиземноморских традиций.',
    'culture' => 'Богатая история с древними памятниками. Гостеприимство, безопасность, уникальные достопримечательности, сочетание древности и современности.',
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
    $page_title = 'Иордания - Travel Hub | Туры, отели, отдых';
    $page_description = 'Туры в Иордания от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Иорданию, отдых в Иордании, туры Акаба';
    $page_keywords = 'туры в Иорданию, отдых в Иордании, туры Акаба, Travel Hub, туры, отдых, отели';
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
        ['name' => 'Иордания', 'url' => '']
    ];
    $schema_data = [
        '@type' => 'Place',
        'name' => 'Иордания',
        'description' => 'Туры в Иордания от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Иорданию, отдых в Иордании, туры Акаба'
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
    <?php $th_country_cta_source = 'country_jordan'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>

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