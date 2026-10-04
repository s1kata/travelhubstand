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
  'slug' => 'turkey',
  'name' => 'Турция',
  'nameEn' => 'Turkey',
  'flag' => '🇹🇷',
  'description' => 'Турция — страна на стыке Европы и Азии, предлагающая богатую историю, прекрасные пляжи, роскошные отели и отличную кухню. Идеальное направление для пляжного отдыха и культурного туризма.',
  'bio' => 'Турция расположена на полуострове Малая Азия и частично в Европе. Омывается четырьмя морями: Чёрным, Мраморным, Эгейским и Средиземным. Климат средиземноморский на побережье, континентальный во внутренних районах. Турция славится своими курортами на побережье Средиземного и Эгейского морей, богатой историей и культурным наследием.

Турция — это уникальная страна, расположенная на стыке Европы и Азии, что делает её мостом между двумя континентами. Страна имеет богатейшую историю, уходящую корнями в глубокую древность. Здесь находились великие империи: Византийская, Римская, Османская. На территории Турции сохранились памятники различных эпох — от античных руин до великолепных мечетей османского периода.

Современная Турция — это динамично развивающаяся страна с населением более 80 миллионов человек. Столица — Анкара, но самым известным городом является Стамбул, бывший Константинополь, который на протяжении веков был столицей Византийской и Османской империй. Турция славится своим гостеприимством, отличной кухней и комфортными условиями для отдыха.',
  'extendedInfo' => 
  array(
    'history' => 'Территория современной Турции была заселена с древнейших времен. Здесь находились такие великие цивилизации, как Хеттское царство, Лидия, Фригия. В античный период здесь процветали греческие города-государства, а затем территория вошла в состав Римской империи. После падения Рима здесь возникла Византийская империя со столицей в Константинополе. В XV веке Константинополь был завоеван османами, и на протяжении нескольких веков Османская империя была одной из самых могущественных держав мира. В 1923 году была провозглашена Турецкая Республика под руководством Мустафы Кемаля Ататюрка.',
    'geography' => 'Турция занимает площадь около 780 тысяч квадратных километров. Страна омывается четырьмя морями: Чёрным на севере, Эгейским на западе, Средиземным на юге и Мраморным морем, которое соединяет Чёрное и Эгейское моря через проливы Босфор и Дарданеллы. Большую часть территории занимают горы: на востоке находятся Армянское нагорье и горы Тавр, на севере — Понтийские горы. Самая высокая точка — гора Арарат (5137 м). Крупнейшие реки — Евфрат, Тигр, Кызылырмак.',
    'culture' => 'Турецкая культура представляет собой уникальное сочетание восточных и западных традиций. Страна является светским государством, но большинство населения исповедует ислам. Турция славится своими традициями гостеприимства, богатой кухней, музыкой и танцами. Особое место занимают турецкие бани (хамамы), которые являются неотъемлемой частью культуры. В Турции сохранились традиции ремесленничества: ковроткачество, керамика, ювелирное дело. Турецкая литература и искусство имеют богатые традиции, восходящие к османскому периоду.',
    'tourism' => 'Турция — одно из самых популярных туристических направлений в мире, ежегодно принимающее более 50 миллионов туристов. Основные курорты расположены на побережье Средиземного и Эгейского морей: Анталия, Алания, Сиде, Бодрум, Мармарис, Кушадасы. Популярны также культурные туры в Стамбул, Каппадокию, Памуккале. Турция предлагает широкий выбор отелей — от бюджетных до роскошных курортов класса люкс. Многие отели работают по системе "все включено", что делает отдых очень удобным и выгодным.',
    'cuisine' => 'Турецкая кухня — одна из самых разнообразных и вкусных в мире. Она вобрала в себя традиции тюркских, арабских, греческих и армянских народов. Популярные блюда: кебабы (шиш-кебаб, дёнер, адана-кебаб), долма (фаршированные овощи), баклава, турецкие сладости (лукум, халва), манты, пиде (турецкая пицца). Обязательно стоит попробовать турецкий кофе и чай, которые являются неотъемлемой частью культуры. Турецкий завтрак (кахвалты) — это целый ритуал с множеством блюд: сыры, оливки, помидоры, огурцы, яйца, мед, джемы.',
    'tips' => 'Лучшее время для пляжного отдыха — с мая по октябрь, когда температура воды и воздуха наиболее комфортна. Для экскурсионных туров подходит любое время года, но летом может быть жарко. В Турции принято торговаться на базарах, это часть культуры. Чаевые обычно составляют 10-15% от суммы счета. Обязательно посетите турецкую баню (хамам) — это уникальный опыт. В мечетях нужно соблюдать дресс-код: закрытые плечи и колени, для женщин — платок. Турецкая лира — местная валюта, но доллары и евро также принимаются во многих местах.',
  ),
  'images' => 
  array(
    0 => '../img/турция/ostrovok-filters-4-10.jpg',
    1 => '../img/турция/8adb6bb9b9dffab0eaabe4b0bc19c702.jpg',
    2 => '../img/турция/65a1a790-511c-11ed-a9f6-7a6bd602295d.1220x600.jpeg',
    3 => '../img/турция/bodrum-1.jpg',
    4 => '../img/турция/turkey-stambul-22181.jpg'
  ),
  'highlights' => 
  array(
    0 => 'Все включено в лучших отелях',
    1 => 'Богатая история и культура',
    2 => 'Отличная кухня и сервис',
    3 => 'Доступные цены и широкий выбор',
  ),
  'bestTime' => 'Апрель-октябрь (пик сезона: июнь-сентябрь)',
  'currency' => 'Турецкая лира (TRY)',
  'language' => 'Турецкий',
  'visa' => 'Виза не требуется для граждан РФ (до 60 дней)',
  'detailedInfo' => 
  array(
    'climate' => 'Средиземноморский климат на побережье. Лето жаркое и сухое (25-35°C), зима мягкая (10-15°C). Внутренние районы имеют континентальный климат. Лучшее время для пляжного отдыха — с мая по октябрь.',
    'attractions' => 'Стамбул (Айя-София, Голубая мечеть, дворец Топкапы), Каппадокия, Памуккале, Эфес, Анталия, Бодрум, Мармарис, курорты на побережье Средиземного и Эгейского морей, Троя, Пергам.',
    'activities' => 'Пляжный отдых, экскурсии по историческим местам, шопинг, турецкая баня (хамам), круизы по Босфору, дайвинг, парапланеризм в Олюденизе, полеты на воздушном шаре в Каппадокии, горнолыжный спорт.',
    'cuisine' => 'Турецкая кухня: кебабы, дёнер, долма, манты, пиде, баклава, лукум. Турецкий кофе и чай. Турецкий завтрак (кахвалты) с множеством блюд. Морепродукты на побережье. Раки — традиционный напиток.',
    'culture' => 'Богатое культурное наследие на стыке Европы и Азии. Гостеприимство местных жителей, традиционные базары, восточная архитектура и современные курорты. Традиционные ремесла: ковроткачество, керамика, ювелирное дело.',
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
    $page_title = 'Турция - Travel Hub | Туры, отели, отдых';
    $page_description = 'Туры в Турция от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Турцию, отдых в Турции, отели Турции, туры Анталия, туры Бодрум';
    $page_keywords = 'туры в Турцию, отдых в Турции, отели Турции, туры Анталия, туры Бодрум, Travel Hub, туры, отдых, отели';
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
        ['name' => 'Турция', 'url' => '']
    ];
    $schema_data = [
        '@type' => 'Place',
        'name' => 'Турция',
        'description' => 'Туры в Турция от Travel Hub. Подбор отелей, виз, трансферов. Премиум сервис и персональный консьерж. туры в Турцию, отдых в Турции, отели Турции, туры Анталия, туры Бодрум'
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
    <?php $th_country_cta_source = 'country_turkey'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>

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