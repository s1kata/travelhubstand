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
  'slug' => 'uae',
  'name' => 'ОАЭ',
  'nameEn' => 'United Arab Emirates',
  'flag' => '🇦🇪',
  'description' => 'Объединённые Арабские Эмираты — это современная страна с небоскрёбами, роскошными отелями, золотыми пляжами и уникальными развлечениями. Дубай и Абу-Даби предлагают незабываемый отдых класса люкс.',
  'bio' => 'ОАЭ — федерация семи эмиратов на Аравийском полуострове. Столица — Абу-Даби, крупнейший город — Дубай. Климат пустынный, жаркий и сухой. ОАЭ известны своими ультрасовременными городами, роскошными отелями, торговыми центрами мирового класса, искусственными островами и развлекательными комплексами.

ОАЭ — это страна контрастов, где древние традиции арабской культуры гармонично сочетаются с ультрасовременными технологиями и архитектурой. За последние 50 лет страна превратилась из бедного региона в один из самых процветающих и развитых в мире. Дубай и Абу-Даби стали символами роскоши, инноваций и современного образа жизни.

Страна состоит из семи эмиратов: Абу-Даби (столица), Дубай, Шарджа, Аджман, Умм-эль-Кайвайн, Рас-эль-Хайма и Фуджейра. Каждый эмират имеет свою уникальную атмосферу и достопримечательности. ОАЭ — это не только небоскребы и торговые центры, но и пустынные сафари, традиционные рынки, мечети и культурные центры.',
  'extendedInfo' => 
  array(
    'history' => 'ОАЭ были образованы в 1971 году, когда семь эмиратов объединились в федерацию. До открытия нефти в 1960-х годах регион был бедным, основными занятиями были рыболовство, добыча жемчуга и торговля. Открытие нефти кардинально изменило судьбу страны. Под руководством шейха Зайда бин Султана Аль Нахайяна, первого президента ОАЭ, страна начала стремительное развитие. Сегодня ОАЭ — одна из самых богатых и развитых стран мира с высоким уровнем жизни.',
    'geography' => 'ОАЭ расположены на Аравийском полуострове, омываются Персидским заливом на севере и Оманским заливом на востоке. Большую часть территории занимает пустыня Руб-эль-Хали — одна из крупнейших песчаных пустынь в мире. Вдоль побережья расположены солончаки и мангровые заросли. В восточной части страны находятся горы Хаджар. Климат пустынный, очень жаркий летом (до 50°C) и теплый зимой (20-25°C). Осадки редкие, в основном зимой.',
    'culture' => 'Культура ОАЭ основана на исламских традициях, но страна известна своей толерантностью и открытостью. Местное население составляет около 15% от общего числа жителей, остальные — экспаты из разных стран мира. ОАЭ — светское государство, где уважаются традиции всех народов. Традиционные занятия включают верблюжьи бега, соколиную охоту, традиционные танцы и музыку. В стране активно развиваются искусство, культура и образование.',
    'tourism' => 'ОАЭ — одно из самых популярных туристических направлений в мире. Дубай известен своими небоскребами (Бурдж-Халифа — самое высокое здание в мире), роскошными отелями (Бурдж-эль-Араб), искусственными островами (Пальма Джумейра) и крупнейшими торговыми центрами. Абу-Даби славится мечетью шейха Зайда, парком Ferrari World и культурным районом Саадият. Популярны также пустынные сафари, катание на верблюдах, посещение традиционных рынков (суков) и пляжный отдых.',
    'transport' => 'ОАЭ имеют отличную транспортную инфраструктуру. Международные аэропорты есть в Дубае, Абу-Даби и Шардже. Прямые рейсы выполняются из Москвы и других крупных городов. Внутри страны удобно перемещаться на такси, метро (в Дубае), автобусах или арендованном автомобиле. Дороги отличного качества, движение правостороннее. В стране развита сеть отелей и курортов всех категорий, от бюджетных до роскошных.',
    'tips' => 'Лучшее время для посещения — с октября по апрель, когда температура комфортная (20-30°C). Летом очень жарко (до 50°C). В ОАЭ нужно соблюдать местные традиции: скромная одежда в общественных местах, особенно в мечетях. Алкоголь продается только в отелях и специальных магазинах. В Рамадан многие заведения работают по особому графику. Чаевые обычно составляют 10-15%. Местная валюта — дирхам ОАЭ, но доллары и евро также принимаются. Обязательно посетите традиционный рынок (сук), попробуйте местную кухню и совершите пустынное сафари.',
  ),
  'images' => 
  array(
    0 => '../img/ОАЭ/293d346edb7b418fb57d0370087191ae.jpg',
    1 => '../img/ОАЭ/6af6b436e569b190aefcec2795dbbbfb.jpg',
    2 => '../img/ОАЭ/734f8edf8d0c999bb97522274e25b32c.jpg',
    3 => '../img/ОАЭ/c7edcab8ec18469e9cf77cca67157751.jpg',
    4 => '../img/ОАЭ/d7d6b9977c657fc97e65d3d22219d8dc.jpg'
  ),
  'highlights' => 
  array(
    0 => 'Роскошные отели и курорты',
    1 => 'Современная архитектура',
    2 => 'Шопинг мирового класса',
    3 => 'Развлечения для всей семьи',
  ),
  'bestTime' => 'Октябрь-апрель (лучшее время для отдыха)',
  'currency' => 'Дирхам ОАЭ (AED)',
  'language' => 'Арабский, английский',
  'visa' => 'Виза не требуется для граждан РФ (до 90 дней)',
  'detailedInfo' => 
  array(
    'climate' => 'Пустынный климат. Лето очень жаркое (35-45°C), зима теплая (20-25°C). Осадки редкие. Лучшее время для посещения: октябрь-апрель.',
    'attractions' => 'Бурдж-Халифа, Бурдж-эль-Араб, Пальма Джумейра, Дубай Молл, Фонтаны Дубая, Ferrari World, Лувр Абу-Даби, мечеть шейха Зайда.',
    'activities' => 'Шопинг в крупнейших торговых центрах, сафари по пустыне, катание на верблюдах, ски-дубай, аквапарки, пляжный отдых, гольф, яхтинг.',
    'cuisine' => 'Международная кухня мирового класса. Популярны арабские блюда (хумус, шаверма, кебаб), а также рестораны с мишленовскими звездами.',
    'culture' => 'Современная страна с ультрасовременной архитектурой и развлечениями. Сочетание традиционной арабской культуры и современного образа жизни.',
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
    $page_title = 'ОАЭ - Travel Hub | Туры, отели, отдых';
    $page_description = 'Туры в ОАЭ от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в ОАЭ, отдых в ОАЭ, отели ОАЭ, туры Дубай, туры Абу-Даби';
    $page_keywords = 'туры в ОАЭ, отдых в ОАЭ, отели ОАЭ, туры Дубай, туры Абу-Даби, Travel Hub, туры, отдых, отели';
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
        ['name' => 'ОАЭ', 'url' => '']
    ];
    $schema_data = [
        '@type' => 'Place',
        'name' => 'ОАЭ',
        'description' => 'Туры в ОАЭ от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в ОАЭ, отдых в ОАЭ, отели ОАЭ, туры Дубай, туры Абу-Даби'
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
    <?php $th_country_cta_source = 'country_uae'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>

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