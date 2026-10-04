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
  'slug' => 'egypt',
  'name' => 'Египет',
  'nameEn' => 'Egypt',
  'flag' => '🇪🇬',
  'description' => 'Египет — страна древних пирамид, фараонов и Красного моря с его потрясающим подводным миром. Идеальное место для пляжного отдыха и культурного туризма.',
  'bio' => 'Египет расположен на северо-востоке Африки и частично в Азии (Синайский полуостров). Омывается Средиземным и Красным морями. Климат пустынный, жаркий и сухой. Египет знаменит своими древними памятниками, пирамидами Гизы, храмами Луксора и Каира, а также курортами Красного моря с отличными условиями для дайвинга.

Египет — колыбель одной из древнейших цивилизаций мира, история которой насчитывает более 5000 лет. Страна подарила миру пирамиды, сфинкса, фараонов и множество других чудес древнего мира. Сегодня Египет — это не только исторические памятники, но и современные курорты на побережье Красного моря, которые привлекают миллионы туристов со всего мира.

Красное море славится своим подводным миром — здесь обитает более 1000 видов рыб, коралловые рифы и множество морских обитателей. Курорты Хургада и Шарм-эль-Шейх предлагают отличные условия для дайвинга, снорклинга и пляжного отдыха. В то же время долина Нила и Каир привлекают любителей истории и культуры.',
  'extendedInfo' => 
  array(
    'history' => 'История Египта насчитывает более 5000 лет. Древний Египет был одной из величайших цивилизаций древнего мира, оставившей после себя пирамиды, храмы, гробницы и множество артефактов. Период фараонов продолжался с 3100 года до н.э. до завоевания Египта Александром Македонским в 332 году до н.э. Затем Египет был частью Римской империи, Византии, арабского халифата, Османской империи. В 1922 году Египет стал независимым королевством, а в 1953 году — республикой. Сегодня Египет — крупнейшая арабская страна с населением более 100 миллионов человек.',
    'geography' => 'Египет занимает площадь около 1 миллиона квадратных километров, но 95% территории — это пустыня. Большая часть населения живет в долине и дельте Нила, которая составляет всего 5% территории страны. Нил — самая длинная река в мире (6650 км), протекающая через всю страну с юга на север. На севере Египет омывается Средиземным морем, на востоке — Красным морем. Синайский полуостров соединяет Африку с Азией. Климат пустынный, очень жаркий летом (до 45°C) и теплый зимой (15-25°C).',
    'culture' => 'Египетская культура — это уникальное сочетание древних традиций и современной арабской культуры. Страна является преимущественно мусульманской (90% населения), но есть и значительная христианская община (копты). Египет славится своей литературой, музыкой, кино и искусством. Каир — культурная столица арабского мира. Традиционные ремесла включают изготовление папируса, парфюмерии, ковров, ювелирных изделий. Египтяне известны своим гостеприимством и дружелюбием.',
    'tourism' => 'Туризм — одна из основных отраслей экономики Египта. Страна привлекает туристов древними памятниками (пирамиды Гизы, храмы Луксора и Карнака, Долина царей) и современными курортами на Красном море (Хургада, Шарм-эль-Шейх, Марса-Алам). Популярны круизы по Нилу, экскурсии в Каир, посещение оазисов в пустыне. Египет предлагает широкий выбор отелей — от бюджетных до роскошных курортов класса люкс. Многие отели работают по системе "все включено".',
    'transport' => 'В Египте несколько международных аэропортов: Каир, Хургада, Шарм-эль-Шейх, Луксор. Прямые рейсы выполняются из Москвы в Каир, Хургаду и Шарм-эль-Шейх. Внутри страны можно перемещаться на самолетах, поездах, автобусах или арендованном автомобиле. Популярны круизы по Нилу на комфортабельных теплоходах. В курортных зонах удобно пользоваться такси или трансферами от отелей. Дороги в основном хорошего качества, особенно между крупными городами.',
    'tips' => 'Лучшее время для посещения — с октября по апрель, когда температура комфортная. Летом очень жарко (до 45°C). Обязательно посетите пирамиды Гизы, храмы Луксора, совершите круиз по Нилу. Для дайвинга лучше всего подходит период с марта по ноябрь. В Египте принято торговаться на рынках и в магазинах. Чаевые (бакшиш) — важная часть культуры, обычно 10-15%. В мечетях нужно соблюдать дресс-код. Местная валюта — египетский фунт, но доллары США также широко принимаются. Пейте только бутилированную воду.',
  ),
  'images' => 
  array(
    0 => '../img/египет/photo_2025-11-27_17-02-33.jpg',
    1 => '../img/египет/photo_2025-11-27_17-02-34.jpg',
    2 => '../img/египет/OIP.png',
    3 => '../img/египет/Sphinx1_16x9.avif',
    4 => '../img/египет/Razones-para-no-visitar-Egipto-51.webp'
  ),
  'highlights' => 
  array(
    0 => 'Лучший дайвинг в Красном море',
    1 => 'Древние памятники и пирамиды',
    2 => 'Все включено по доступным ценам',
    3 => 'Богатая история и культура',
  ),
  'bestTime' => 'Октябрь-апрель (лучшее время для отдыха)',
  'currency' => 'Египетский фунт (EGP)',
  'language' => 'Арабский',
  'visa' => 'Виза оформляется по прилёте (25 USD)',
  'detailedInfo' => 
  array(
    'climate' => 'Пустынный климат. Лето жаркое (30-40°C), зима мягкая (15-25°C). На побережье Красного моря климат более мягкий благодаря морскому бризу.',
    'attractions' => 'Пирамиды Гизы, Сфинкс, храмы Луксора и Карнака, Долина царей, Каирский музей, курорты Хургады и Шарм-эль-Шейха, Синайский полуостров.',
    'activities' => 'Дайвинг и снорклинг в Красном море, экскурсии к пирамидам и храмам, круизы по Нилу, сафари по пустыне, пляжный отдых, спа-процедуры.',
    'cuisine' => 'Египетская кухня: фалафель, кошари, фуль, тахини, свежие морепродукты. Популярны также международные блюда в отелях.',
    'culture' => 'Древняя цивилизация с богатой историей. Гостеприимство местных жителей, традиционные базары, исламская архитектура и современные курорты.',
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <?php
    $page_title = 'Египет - Travel Hub | Туры, отели, отдых';
    $page_description = 'Туры в Египет от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Египет, отдых в Египте, отели Египта, туры Хургада, туры Шарм-эль-Шейх';
    $page_keywords = 'туры в Египет, отдых в Египте, отели Египта, туры Хургада, туры Шарм-эль-Шейх, Travel Hub, туры, отдых, отели';
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
        ['name' => 'Египет', 'url' => '']
    ];
    $schema_data = [
        '@type' => 'Place',
        'name' => 'Египет',
        'description' => 'Туры в Египет от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Египет, отдых в Египте, отели Египта, туры Хургада, туры Шарм-эль-Шейх'
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
    <?php $th_country_cta_source = 'country_egypt'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>

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