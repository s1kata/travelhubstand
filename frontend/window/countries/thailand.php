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
  'slug' => 'thailand',
  'name' => 'Таиланд',
  'nameEn' => 'Thailand',
  'flag' => '🇹🇭',
  'description' => 'Таиланд — страна улыбок, тропических пляжей, древних храмов и потрясающей кухни. Идеальное сочетание пляжного отдыха, культуры и развлечений.',
  'bio' => 'Таиланд расположен в Юго-Восточной Азии. Столица — Бангкок. Климат тропический, с сезоном дождей с мая по октябрь. Таиланд известен своими пляжами на побережье Андаманского моря и Сиамского залива, древними храмами, богатой культурой, отличной кухней и гостеприимством местных жителей.

Таиланд — страна улыбок, где буддийская культура, древние традиции и современность гармонично сочетаются. Страна никогда не была колонизирована, что позволило сохранить уникальную культуру и традиции. Таиланд славится своими золотыми храмами, тропическими пляжами, экзотической кухней и невероятным гостеприимством местных жителей.

Страна разделена на несколько регионов: Центральный (Бангкок и окрестности), Северный (Чиангмай, горы), Северо-Восточный (Исан), Южный (пляжные курорты). Каждый регион имеет свою уникальную культуру, кухню и достопримечательности. Пляжные курорты Пхукет, Паттайя, Краби, Самуи привлекают миллионы туристов, в то время как Бангкок и Чиангмай манят любителей культуры и истории.',
  'extendedInfo' => 
  array(
    'history' => 'История Таиланда насчитывает более 1000 лет. Страна никогда не была колонизирована европейцами, что является уникальным для региона. В XIII веке было основано королевство Сукхотай, затем Аюттхая, а в XVIII веке — современная династия Чакри со столицей в Бангкоке. Таиланд — конституционная монархия, король является символом нации. Страна прошла путь от аграрной экономики до одной из самых развитых стран Юго-Восточной Азии. Сегодня Таиланд — популярное туристическое направление, привлекающее более 40 миллионов туристов ежегодно.',
    'geography' => 'Таиланд занимает площадь около 513 тысяч квадратных километров в центре Юго-Восточной Азии. Страна граничит с Мьянмой, Лаосом, Камбоджей и Малайзией. На юге омывается Андаманским морем и Сиамским заливом. Рельеф разнообразный: на севере горы (до 2565 м), в центре — равнины, на юге — тропические острова. Главная река — Чао-Прайя, протекающая через Бангкок. Климат тропический с тремя сезонами: прохладный (ноябрь-февраль), жаркий (март-май) и дождливый (июнь-октябрь).',
    'culture' => 'Таиланд — буддийская страна (95% населения), где религия играет важную роль в повседневной жизни. Страна известна своими золотыми храмами, монахами в оранжевых одеждах, традиционными фестивалями. Таиланд славится гостеприимством — "страна улыбок" не просто название. Тайская культура включает традиционные танцы, музыку, боевые искусства (муай-тай), ремесла. Особое место занимает тайский массаж, который известен во всем мире. Традиционная архитектура, искусство и литература имеют богатые традиции.',
    'tourism' => 'Таиланд — одно из самых популярных туристических направлений в мире. Пляжные курорты Пхукет, Паттайя, Краби, Самуи, Пхи-Пхи предлагают отличные условия для пляжного отдыха, дайвинга, водных видов спорта. Бангкок привлекает своими храмами, дворцами, шопингом и ночной жизнью. Чиангмай известен древними храмами, слоновьими заповедниками, горными племенами. Популярны также экотуризм, тайский массаж, кулинарные туры. Таиланд предлагает широкий выбор отелей — от бюджетных гестхаусов до роскошных курортов класса люкс.',
    'cuisine' => 'Тайская кухня — одна из самых популярных в мире, известная своими острыми, кислыми, сладкими и солеными вкусами. Популярные блюда: том-ям (острый суп), пад-тай (жареная лапша), зеленое карри, сом-там (острый салат из папайи), кокосовый суп. Тайская кухня использует много специй, трав, кокосовое молоко, лайм, чили. Популярны также морепродукты, свежие фрукты (мангостин, дуриан, рамбутан), тайские десерты. Уличная еда в Таиланде — это отдельная культура, где можно попробовать аутентичные блюда по доступным ценам.',
    'tips' => 'Лучшее время для посещения — с ноября по март (прохладный сезон), когда температура комфортная (25-30°C) и дожди минимальны. В Таиланде нужно уважать короля и буддийские традиции. В храмах нужно снимать обувь, одеваться скромно (закрытые плечи и колени). Нельзя трогать голову тайцев (священная часть тела) и показывать на кого-то ногами. Чаевые не обязательны, но приветствуются (20-50 бат). Торговаться можно на рынках, но не в магазинах. Местная валюта — тайский бат. Обязательно попробуйте уличную еду, но выбирайте места с большим количеством посетителей.',
  ),
  'images' => 
  array(
    0 => '../img/таиланд/f6abf1e77961201063281c7d41fea1ef.jpg',
    1 => '../img/таиланд/91ab2281edaad46dd43d245fccc3d9a0.jpg',
    2 => '../img/таиланд/bbe51cf83248eddbd41d5a67178114ed.jpg',
    3 => '../img/таиланд/870112554ed554357b844f61493ce547.jpg'
  ),
  'highlights' => 
  array(
    0 => 'Тропические пляжи',
    1 => 'Богатая культура и храмы',
    2 => 'Отличная кухня',
    3 => 'Доступные цены',
  ),
  'bestTime' => 'Ноябрь-март (сухой сезон)',
  'currency' => 'Тайский бат (THB)',
  'language' => 'Тайский',
  'visa' => 'Виза не требуется для граждан РФ (до 30 дней)',
  'detailedInfo' => 
  array(
    'climate' => 'Тропический климат. Сухой сезон: ноябрь-март (25-32°C), сезон дождей: май-октябрь. Влажность высокая круглый год.',
    'attractions' => 'Бангкок (храмы, дворцы), Пхукет, Паттайя, острова Пхи-Пхи, Чиангмай, храм Изумрудного Будды, плавучие рынки, слоновьи заповедники.',
    'activities' => 'Пляжный отдых, дайвинг, тайский массаж, экскурсии к храмам, шопинг, тайская кухня, ночная жизнь, экотуризм, посещение слоновьих заповедников.',
    'cuisine' => 'Тайская кухня: том-ям, пад-тай, зеленое карри, сом-там, мангостин, дуриан. Острая и ароматная еда с использованием кокосового молока и специй.',
    'culture' => 'Буддийская культура с древними традициями. Гостеприимство местных жителей, улыбки, храмы, традиционные фестивали и современные развлечения.',
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
    $page_title = 'Таиланд - Travel Hub | Туры, отели, отдых';
    $page_description = 'Туры в Таиланд от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Таиланд, отдых в Таиланде, отели Таиланда, туры Пхукет, туры Паттайя';
    $page_keywords = 'туры в Таиланд, отдых в Таиланде, отели Таиланда, туры Пхукет, туры Паттайя, Travel Hub, туры, отдых, отели';
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
        ['name' => 'Таиланд', 'url' => '']
    ];
    $schema_data = [
        '@type' => 'Place',
        'name' => 'Таиланд',
        'description' => 'Туры в Таиланд от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Таиланд, отдых в Таиланде, отели Таиланда, туры Пхукет, туры Паттайя'
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
    <?php $th_country_cta_source = 'country_thailand'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>

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