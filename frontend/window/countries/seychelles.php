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
  'slug' => 'seychelles',
  'name' => 'Сейшелы',
  'nameEn' => 'Seychelles',
  'flag' => '🇸🇨',
  'description' => 'Сейшельские острова — это райский уголок в Индийском океане, состоящий из 115 островов. Здесь вас ждут белоснежные пляжи, кристально чистая вода, уникальная природа и роскошные отели мирового класса.',
  'bio' => 'Сейшелы — это архипелаг из 115 гранитных и коралловых островов, расположенный в Индийском океане к востоку от Африки. Столица — Виктория на острове Маэ. Климат тропический, температура круглый год 24-30°C. Сейшелы славятся своими уникальными пляжами, такими как Анс Сурс д\'Аржан на острове Ла-Диг, и эндемичной флорой и фауной, включая гигантских черепах и пальмы коко-де-мер.

Сейшельские острова — это настоящий рай для любителей пляжного отдыха и дайвинга. Здесь вы найдете одни из самых красивых пляжей в мире с белоснежным песком и кристально чистой водой. Архипелаг состоит из внутренних гранитных островов и внешних коралловых атоллов, каждый из которых имеет свой уникальный характер.

Основные острова для туризма: Маэ (самый большой остров с международным аэропортом), Праслин (известен долиной Валле-де-Мэ с пальмами коко-де-мер), Ла-Диг (знаменит пляжем Анс Сурс д\'Аржан). На Сейшелах вы можете заняться дайвингом, снорклингом, рыбалкой, кайтсерфингом или просто наслаждаться пляжным отдыхом в роскошных отелях.

Сейшелы были открыты португальцами в 1502 году, но долгое время оставались необитаемыми. Первые поселения появились только в XVIII веке, когда острова стали французской колонией. Сегодня Сейшелы — независимая республика с населением около 100 тысяч человек. Официальные языки — креольский, английский и французский, что отражает многонациональное наследие островов.

Природа Сейшел уникальна — более 80% территории покрыто тропическими лесами, многие виды растений и животных являются эндемичными и встречаются только здесь. Особенно известны гигантские черепахи Альдабры, которые могут жить более 100 лет, и пальмы коко-де-мер, плоды которых считаются самыми крупными в растительном мире.',
  'extendedInfo' => 
  array(
    'history' => 'Сейшелы были открыты португальским мореплавателем Васко да Гамой в 1502 году, но долгое время оставались необитаемыми из-за отсутствия пресной воды. В 1756 году острова были колонизированы французами, которые назвали их в честь министра финансов Франции Жана Моро де Сешеля. В 1814 году Сейшелы перешли под контроль Великобритании и оставались британской колонией до 1976 года, когда получили независимость. Сегодня Сейшелы — президентская республика с развитой туристической индустрией.',
    'geography' => 'Архипелаг состоит из 115 островов, из которых только 33 обитаемы. Острова делятся на две группы: внутренние гранитные острова (Маэ, Праслин, Ла-Диг) и внешние коралловые атоллы. Самый большой остров — Маэ (155 км²), на нем расположена столица Виктория и международный аэропорт. Высочайшая точка — гора Морн-Сейшелуа (905 м) на острове Маэ. Острова окружены коралловыми рифами, которые создают идеальные условия для дайвинга и снорклинга.',
    'nature' => 'Флора и фауна Сейшел уникальны — более 80% видов являются эндемичными. В долине Валле-де-Мэ на острове Праслин растут пальмы коко-де-мер, которые производят самые крупные орехи в мире (до 30 кг). На острове Альдабра обитает крупнейшая в мире популяция гигантских черепах (более 150 тысяч особей). В водах вокруг островов можно встретить более 1000 видов рыб, дельфинов, китов и морских черепах. На Сейшелах обитает множество редких птиц, включая сейшельскую славку и сейшельского черного попугая.',
    'tourism' => 'Туризм — основной источник дохода Сейшел. Острова предлагают роскошные курорты мирового класса, многие из которых расположены на частных островах. Популярные направления включают пляжный отдых, дайвинг, снорклинг, рыбалку, парусный спорт и экотуризм. На Сейшелах работают отели таких известных сетей, как Four Seasons, Hilton, Constance, Six Senses и другие. Многие курорты предлагают систему "все включено" и эксклюзивные услуги, включая частных поваров, батлеров и спа-процедуры.',
    'transport' => 'Международный аэропорт Сейшел находится на острове Маэ, в 11 км от столицы Виктории. Прямые рейсы выполняются из Москвы, Дубая, Абу-Даби, Стамбула и других городов. Между островами можно перемещаться на паромах, скоростных катерах или небольших самолетах. На крупных островах доступна аренда автомобилей, но большинство туристов предпочитают пользоваться услугами такси или трансферами от отелей.',
    'tips' => 'Лучшее время для посещения — апрель-май и октябрь-ноябрь, когда погода наиболее комфортная, а дожди минимальны. Обязательно посетите долину Валле-де-Мэ на Праслине, пляж Анс Сурс д\'Аржан на Ла-Диге и совершите экскурсию на необитаемые острова. Не забудьте взять с собой солнцезащитный крем, так как солнце здесь очень активное. На Сейшелах принято оставлять чаевые (10-15%), но это не обязательно. Местная валюта — сейшельская рупия, но доллары США и евро также широко принимаются.',
  ),
  'detailedInfo' => 
  array(
    'climate' => 'Тропический морской климат. Температура воздуха: 24-30°C круглый год. Сезон дождей: декабрь-февраль, но дожди кратковременные.',
    'attractions' => 'Долина Валле-де-Мэ (ЮНЕСКО), пляж Анс Сурс д\'Аржан, Морской национальный парк Сент-Анн, остров Кузин, заповедник Альдабра.',
    'activities' => 'Дайвинг, снорклинг, рыбалка, кайтсерфинг, парусный спорт, экскурсии по островам, наблюдение за птицами и черепахами.',
    'cuisine' => 'Креольская кухня с влиянием французской, индийской и китайской. Популярные блюда: рыба в кокосовом соусе, осьминог карри, фрукты и морепродукты.',
    'culture' => 'Многонациональное население с креольской культурой. Официальные языки: креольский, английский, французский. Местные жители дружелюбны и гостеприимны.',
  ),
  'images' => 
  array(
    0 => '../img/сейшелы/35ff072cc8f8c7eda59a97b543a7f1e4.jpg',
    1 => '../img/сейшелы/3eb1dc06e7f2052f058abd73cdd7929d.jpg',
    2 => '../img/сейшелы/a605d9c888f456b0bd001f4b3ef79d68.jpg',
    3 => '../img/сейшелы/c3ef99b44739059a79cbe4c652b198df.jpg',
    4 => '../img/сейшелы/f85329734ed3088d1825c9a63116e0f1.jpg'
  ),
  'highlights' => 
  array(
    0 => 'Роскошные курорты класса люкс',
    1 => 'Приватные острова и виллы',
    2 => 'Уникальная природа и эндемичные виды',
    3 => 'Идеальные условия для дайвинга и снорклинга',
  ),
  'bestTime' => 'Круглый год (лучшее время: апрель-май, октябрь-ноябрь)',
  'currency' => 'Сейшельская рупия (SCR)',
  'language' => 'Английский, французский, креольский',
  'visa' => 'Виза не требуется для граждан РФ (до 30 дней)',
);

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
    $page_title = 'Сейшелы - Travel Hub | Туры, отели, отдых';
    $page_description = 'Туры в Сейшелы от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры на Сейшелы, отдых на Сейшелах, премиум туры Сейшелы';
    $page_keywords = 'туры на Сейшелы, отдых на Сейшелах, премиум туры Сейшелы, Travel Hub, туры, отдых, отели';
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
        ['name' => 'Сейшелы', 'url' => '']
    ];
    $schema_data = [
        '@type' => 'Place',
        'name' => 'Сейшелы',
        'description' => 'Туры в Сейшелы от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры на Сейшелы, отдых на Сейшелах, премиум туры Сейшелы'
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
    <?php $th_country_cta_source = 'country_seychelles'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>

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