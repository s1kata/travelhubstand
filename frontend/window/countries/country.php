<?php
require_once __DIR__ . '/../../../backend/config/config.php';
session_start();

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

$countrySlug = strtolower(trim((string) ($_GET['country'] ?? 'seychelles')));

// Получаем информацию о стране через API
$countryData = null;
$countryNotFound = false;

// Используем прямой доступ к данным стран из API файла
// Загружаем массив стран напрямую, избегая exit в API
$apiFile = __DIR__ . '/../../../backend/api/countries.php';

// Временно сохраняем оригинальный $_GET
$originalGet = $_GET;
$_GET['slug'] = $countrySlug;

// Определяем константу, чтобы API не использовал exit (если еще не определена)
if (!defined('API_INCLUDED')) {
    define('API_INCLUDED', true);
}

// Перехватываем вывод API
ob_start();
include $apiFile;
$response = ob_get_clean();

// Восстанавливаем $_GET
$_GET = $originalGet;

if ($response) {
    $decoded = json_decode($response, true);
    if ($decoded && !isset($decoded['error']) && isset($decoded['slug'])) {
        $countryData = $decoded;
    }
}

// Если страна не найдена — показываем корректное состояние, а не подменяем другой страной.
if (!$countryData) {
    $countryNotFound = true;
    $countryData = [
        'slug' => $countrySlug ?: 'unknown',
        'name' => 'Страна не найдена',
        'nameEn' => 'Country Not Found',
        'flag' => '🌍',
        'description' => 'Мы не нашли страницу этой страны. Проверьте ссылку или выберите направление из списка стран.',
        'bio' => 'Возможно, страница была перемещена или название страны указано в другом формате.',
        'images' => [
            'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1920&q=80'
        ],
        'highlights' => [],
        'bestTime' => '—',
        'currency' => '—',
        'language' => '—',
        'visa' => '—'
    ];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($countryData['name']); ?> - Travel Hub</title>
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="alternate icon" href="/frontend/favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="/frontend/css/pages/country.css?v=1">
    <?php include __DIR__ . '/../../../backend/components/design_system_head.php'; ?>
    </head>
<body class="text-slate-900">
    <?php 
    $current_page = 'countries';
    include __DIR__ . '/../../../backend/components/header.php'; 
    ?>

    <!-- Hero Section -->
    <section class="relative bg-gradient-to-br from-sky-100 via-white to-sky-50 text-slate-900 py-20 md:py-28 overflow-hidden">
        <div class="absolute inset-0 overflow-hidden z-0">
            <?php if (!empty($countryData['images'][0])): ?>
            <img src="<?php echo htmlspecialchars($countryData['images'][0]); ?>" 
                 alt="<?php echo htmlspecialchars($countryData['name']); ?>" 
                 class="w-full h-full object-cover opacity-20">
            <?php endif; ?>
            <div class="absolute inset-0 bg-gradient-to-t from-white via-transparent to-transparent"></div>
        </div>
        <div class="container mx-auto px-6 relative z-10">
            <div class="max-w-6xl mx-auto text-center space-y-6">
                <div class="inline-flex items-center gap-3 mb-4">
                    <div class="th-country-hero__iso" style="display:inline-flex;min-width:2.75rem;padding:0.35rem 0.65rem;border-radius:999px;background:rgba(15,23,42,0.08);border:1px solid rgba(15,23,42,0.12);" aria-label="<?php echo htmlspecialchars(getCountryCode($countrySlug), ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="th-country-hero__iso-code" style="font-size:0.95rem;font-weight:800;letter-spacing:0.12em;color:#0f172a;"><?php echo htmlspecialchars(getCountryCode($countrySlug), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
                <h1 class="heading-font text-5xl md:text-6xl lg:text-7xl font-bold text-slate-900 leading-tight">
                    <?php echo htmlspecialchars($countryData['name']); ?>
                </h1>
                    <p class="text-xl md:text-2xl text-slate-900 max-w-3xl mx-auto leading-relaxed">
                    <?php echo htmlspecialchars($countryData['description']); ?>
                </p>
            </div>
        </div>
    </section>
    <?php if ($countryNotFound): ?>
    <section class="py-8 bg-amber-50 border-y border-amber-200">
        <div class="container mx-auto px-6">
            <div class="max-w-4xl mx-auto text-center">
                <p class="text-amber-800 font-medium mb-4">Страница направления не найдена. Перейдите к списку доступных стран.</p>
                <a href="/frontend/window/countries-list.php" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-amber-500 text-white hover:bg-amber-600 transition-colors">
                    <i class="fas fa-globe"></i> Открыть список стран
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Country Info Section -->
    <section class="py-16 bg-white">
        <div class="container mx-auto px-6">
            <div class="max-w-6xl mx-auto">
                <!-- Photo Gallery Strip -->
                <?php if (!empty($countryData['images'])): ?>
                <div class="mb-12">
                    <h2 class="heading-font text-3xl font-bold text-slate-900 mb-6 flex items-center gap-3">
                        <i class="fas fa-images text-sky-500"></i>
                        Фотогалерея <?php echo htmlspecialchars($countryData['name']); ?>
                    </h2>
                    <p class="text-slate-900 mb-6">Нажмите на любое фото для просмотра в полном размере</p>
                    <div class="overflow-x-auto pb-4 -mx-6 px-6 photo-gallery-strip">
                        <div class="flex gap-4 min-w-max">
                            <?php 
                            // Показываем все изображения
                            foreach ($countryData['images'] as $index => $image): 
                            ?>
                            <div class="flex-shrink-0 w-80 h-64 rounded-2xl overflow-hidden shadow-lg relative z-10 group cursor-pointer gallery-image" 
                                 data-image="<?php echo htmlspecialchars($image); ?>"
                                 data-index="<?php echo $index; ?>">
                                <img src="<?php echo htmlspecialchars($image); ?>" 
                                     alt="<?php echo htmlspecialchars($countryData['name']); ?> - Фото <?php echo $index + 1; ?>" 
                                     class="w-full h-full object-cover group-hover:scale-110 transition duration-500"
                                     loading="lazy"
                                     onerror="this.src='https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1920&q=80'">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-4">
                                    <span class="text-slate-900 text-sm font-medium">Фото <?php echo $index + 1; ?> из <?php echo count($countryData['images']); ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Lightbox Modal for Gallery -->
                <div id="gallery-lightbox" class="fixed inset-0 bg-black/90 z-50 hidden items-center justify-center p-4">
                    <button class="absolute top-4 right-4 text-slate-900 hover:text-sky-400 transition text-3xl z-10" id="close-lightbox">
                        <i class="fas fa-times"></i>
                    </button>
                    <button class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-900 hover:text-sky-400 transition text-3xl z-10" id="prev-image">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-900 hover:text-sky-400 transition text-3xl z-10" id="next-image">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <div class="max-w-7xl w-full h-full flex items-center justify-center">
                        <img id="lightbox-image" src="" alt="<?php echo htmlspecialchars($countryData['name'] ?? 'Фото', ENT_QUOTES, 'UTF-8'); ?> - фото" class="max-w-full max-h-full object-contain rounded-lg">
                    </div>
                    <div class="absolute bottom-4 left-1/2 -translate-x-1/2 text-slate-900 text-sm" id="image-counter"></div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </section>

    <!-- TourVisor Search Section -->
    <section class="py-16 bg-gradient-to-b from-white to-sky-50">
        <div class="container mx-auto px-6">
            <div class="text-center mb-8 space-y-3">
                <h2 class="heading-font text-2xl sm:text-3xl md:text-4xl font-bold text-slate-900">Поиск туров</h2>
                <p class="text-slate-900 max-w-2xl mx-auto">Заполните параметры — и мы предложим лучшие варианты под ваши даты и бюджет</p>
            </div>
            <div class="max-w-7xl mx-auto">
                <div class="surface-card p-4 sm:p-6 md:p-8 lg:p-10">
                    <div class="tv-search-form tv-moduleid-9975486"></div>
                </div>
            </div>
        </div>
    </section>
    <?php $th_country_cta_source = 'country_country'; include __DIR__ . '/../../../backend/components/country_contact_lead.php'; ?>

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
                    if (window.THMobile && window.THMobile.lockScroll) window.THMobile.lockScroll(true);
                }
            }

            function closeLightboxFunc() {
                lightbox.classList.add('hidden');
                lightbox.classList.remove('flex');
                if (window.THMobile && window.THMobile.lockScroll) window.THMobile.lockScroll(false);
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

            // Keyboard navigation
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

        // Tours data and functionality
        let currentPage = 1;
        const toursPerPage = 12;
        const countrySlug = '<?php echo htmlspecialchars($countrySlug); ?>';
        const priceFormatter = new Intl.NumberFormat('ru-RU');

        const formatPrice = (value) => {
            if (value === null || typeof value === 'undefined' || Number.isNaN(value)) {
                return 'По запросу';
            }
            return `${priceFormatter.format(value)} ₽`;
        };

        async function loadTours(page = 1) {
            const loading = document.getElementById('loading');
            const toursGrid = document.getElementById('tours-grid');
            const loadMoreBtn = document.getElementById('load-more');

            loading.style.display = 'flex';
            loadMoreBtn.classList.add('hidden');

            try {
                const response = await fetch(`../../backend/api/tours.php?page=${page}&filter=${countrySlug}&per_page=${toursPerPage}&context=list`);
                const text = await response.text();
                let data = { tours: [], hasMore: false };
                if ((text || '').trim()) { try { data = JSON.parse(text); } catch (e) {} }

                if (page === 1) {
                    toursGrid.innerHTML = '';
                }

                renderTours(data.tours || []);

                if (data.hasMore) {
                    loadMoreBtn.classList.remove('hidden');
                }

                currentPage = page;
            } catch (error) {
                console.error('Error loading tours:', error);
                toursGrid.innerHTML = '<div class="col-span-full text-center text-slate-900 py-12">Не удалось загрузить туры. Попробуйте обновить страницу.</div>';
            } finally {
                loading.style.display = 'none';
            }
        }

        function renderTours(tours) {
            const toursGrid = document.getElementById('tours-grid');
            
            if (tours.length === 0) {
                toursGrid.innerHTML = '<div class="col-span-full text-center text-slate-900 py-12">Туры в данную страну временно недоступны. Попробуйте позже.</div>';
                return;
            }

            tours.forEach(tour => {
                const tourCard = createTourCard(tour);
                toursGrid.appendChild(tourCard);
            });
        }

        function createTourCard(tour) {
            const card = document.createElement('article');
            card.className = 'th-tour-card';

            const adultsN = (function(){ try { const raw = localStorage.getItem('tv_tourists'); if (raw) { const j = JSON.parse(raw); if (j && typeof j.adults === 'number' && j.adults >= 1 && j.adults <= 9) return j.adults; } } catch(e) {} return 2; })();
            const priceNum = parseInt(String(tour.price || 0), 10) || 0;
            const fallback = 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=600&q=80';
            const imgSrc = (tour.image || fallback).replace(/"/g, '&quot;');
            const nameEsc = (tour.title || '').replace(/</g, '&lt;').replace(/"/g, '&quot;');
            const geoEsc = (tour.subtitle || tour.region || '').replace(/</g, '&lt;');
            const catNum = parseInt(String(tour.category || tour.stars || 0), 10) || 0;
            const starsHtml = catNum > 0 ? '★'.repeat(Math.min(catNum, 5)) : '';
            const meal = (tour.meal || '').replace(/</g, '&lt;');
            const nights = parseInt(String(tour.nights || 0), 10) || 0;
            const nightsLbl = nights === 1 ? '1 ночь' : nights < 5 ? nights + ' ночи' : nights + ' ночей';
            const datesMeta = (nights ? nightsLbl + ', ' : '') + adultsN + ' взр.';
            const href = tour.link || tour.url || '/frontend/window/contacts.php';
            const badgeHtml = tour.badge ? `<span class="th-tour-card__badge th-tour-card__badge--exclusive">${tour.badge.replace(/</g,'&lt;')}</span>` : '';

            if (window.THTourCard && typeof window.THTourCard.render === 'function') {
                const h = { name: tour.title, category: tour.category || tour.stars, image: tour.image };
                const t = { nights: tour.nights, meal: { name: tour.meal } };
                card.innerHTML = window.THTourCard.render(h, {
                    tour: t, detailUrl: href, adults: adultsN, price: priceNum,
                    country: tour.subtitle || '', meal: tour.meal || '', badge: tour.badge || '',
                    carousel: true, image: tour.image,
                    departureCity: (window.TH_DEPARTURE && window.TH_DEPARTURE.name) || 'Самара',
                    countryCard: true
                });
                return card;
            }
            card.innerHTML = `
                <a href="${href}" class="th-tour-card__link th-tour-card__link--main">
                    <div class="th-tour-card__media">
                        <img src="${imgSrc}" alt="${nameEsc}" class="th-tour-card__img" loading="eager" decoding="async"
                             onerror="this.onerror=null;this.src='${fallback}'">
                        ${badgeHtml}
                    </div>
                    <div class="th-tour-card__body">
                        ${geoEsc ? `<p class="th-tour-card__geo">${geoEsc}</p>` : ''}
                        <div class="th-tour-card__name-row">
                            <h3 class="th-tour-card__name">${nameEsc}</h3>
                            ${starsHtml ? `<span class="th-tour-card__stars">${starsHtml}</span>` : ''}
                        </div>
                        ${meal ? `<span class="th-tour-card__meal-badge">${meal}</span>` : ''}
                        <span class="th-tour-card__dep-city">Вылет: ${(window.TH_DEPARTURE && window.TH_DEPARTURE.name) ? String(window.TH_DEPARTURE.name).replace(/</g,'&lt;') : 'Самара'}</span>
                        <div class="th-tour-card__price-block">
                            <span class="th-tour-card__price-label">за ${adultsN} взр.</span>
                            <span class="th-tour-card__price">${priceNum > 0 ? formatPrice(priceNum) : 'Уточняйте'}</span>
                            <span class="th-tour-card__dates">${datesMeta}</span>
                        </div>
                    </div>
                </a>
                <div class="th-tour-card__actions">
                    <a href="${href}" class="th-tour-card__btn th-tour-card__btn--secondary">${(window.THTourCard && window.THTourCard.DETAIL_BTN_LABEL) ? window.THTourCard.DETAIL_BTN_LABEL : 'Просмотреть параметры тура и забронировать'}</a>
                </div>`;

            return card;
        }

        function renderStars(rating = 0) {
            const stars = Math.floor(rating);
            const hasHalfStar = rating % 1 !== 0;
            let starsHtml = '';
            for (let i = 0; i < stars; i++) starsHtml += '<i class="fas fa-star"></i>';
            if (hasHalfStar) starsHtml += '<i class="fas fa-star-half-alt"></i>';
            for (let i = stars + (hasHalfStar ? 1 : 0); i < 5; i++) starsHtml += '<i class="far fa-star"></i>';
            return starsHtml;
        }

    </script>

    <script type="text/javascript" src="//tourvisor.ru/module/init.js" defer></script>
</body>
</html>