<?php
require_once __DIR__ . '/../../backend/config/config.php';
session_start();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title>VIP Отели Турции - Travel Hub</title>
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="alternate icon" href="/frontend/favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="/frontend/css/pages/turkey-vip-hotels.css?v=1">
    <?php include __DIR__ . '/../../backend/components/design_system_head.php'; ?>
    </head>
<body class="ds-page text-slate-900 antialiased">
    <?php 
    $current_page = 'vip-hotels';
    include __DIR__ . '/../../backend/components/header.php'; 
    ?>

    <!-- Hero Section -->
    <section class="ds-page-hero relative py-20 md:py-28 bg-gradient-to-br from-indigo-50 via-white to-slate-50">
        <div class="th-container mx-auto px-6">
            <div class="text-center max-w-4xl mx-auto">
                <span class="pill-badge mb-6">VIP Отели</span>
                <h1 class="heading-font text-4xl md:text-5xl font-bold text-slate-900 mb-6">VIP Отели Турции</h1>
                <p class="text-xl text-slate-600 mb-8">Роскошные отели премиум-класса в лучших курортах Турции: Анталья, Белек и Кемер</p>
            </div>
        </div>
    </section>

    <!-- Hotels Content -->
    <section class="py-16">
        <div class="th-container mx-auto px-6">
            <div class="max-w-7xl mx-auto">
                <!-- City Filter -->
                <div class="mb-8 flex justify-center">
                    <div class="flex gap-2 bg-white rounded-full p-2 shadow-md border border-indigo-100">
                        <button onclick="filterHotels('')" class="city-filter-btn px-6 py-2 rounded-full font-medium transition bg-indigo-600 text-white" data-city="">Все города</button>
                        <button onclick="filterHotels('Antalya')" class="city-filter-btn px-6 py-2 rounded-full font-medium transition text-slate-600 hover:bg-indigo-50" data-city="Antalya">Анталья</button>
                        <button onclick="filterHotels('Belek')" class="city-filter-btn px-6 py-2 rounded-full font-medium transition text-slate-600 hover:bg-indigo-50" data-city="Belek">Белек</button>
                        <button onclick="filterHotels('Kemer')" class="city-filter-btn px-6 py-2 rounded-full font-medium transition text-slate-600 hover:bg-indigo-50" data-city="Kemer">Кемер</button>
                    </div>
                </div>

                <!-- Loading State -->
                <div id="loading" class="text-center py-12">
                    <i class="fas fa-spinner fa-spin text-4xl text-indigo-600 mb-4"></i>
                    <p class="text-slate-600">Загрузка отелей...</p>
                </div>

                <!-- Hotels Grid -->
                <div id="hotels-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8"></div>

                <!-- Empty State -->
                <div id="empty-state" class="hidden text-center py-12 max-w-lg mx-auto">
                    <i class="fas fa-hotel text-6xl text-slate-300 mb-4"></i>
                    <p class="text-xl text-slate-600">Отели временно недоступны</p>
                    <p id="vip-empty-hint" class="text-sm text-slate-500 mt-3">Сейчас список VIP-отелей пуст. Загляните позже или выберите туры в Турцию на главной.</p>
                </div>
            </div>
        </div>
    </section>

    <script>
        let allHotels = [];
        let currentCity = '';

        // Загрузка отелей
        async function loadHotels(city = '') {
            const loading = document.getElementById('loading');
            const container = document.getElementById('hotels-container');
            const emptyState = document.getElementById('empty-state');
            
            loading.classList.remove('hidden');
            container.innerHTML = '';
            emptyState.classList.add('hidden');
            const hintLoad = document.getElementById('vip-empty-hint');
            if (hintLoad) { hintLoad.classList.add('hidden'); hintLoad.textContent = ''; }

            const url = city 
                ? `/backend/api/vip-hotels.php?city=${encodeURIComponent(city)}`
                : '/backend/api/vip-hotels.php';
            try {
                const response = await fetch(url);
                const text = await response.text();
                let data = { hotels: [] };
                if ((text || '').trim()) {
                    try { data = JSON.parse(text); } catch (e) { data = { hotels: [], _parse_error: e.message }; }
                }
                console.group('%c[VIP отели] Ответ API', 'color: #1A1A40; font-weight: bold');
                console.log('URL:', url);
                console.log('HTTP status:', response.status);
                console.log('error (из ответа):', data.error || '—');
                console.log('hotels.length:', data.hotels ? data.hotels.length : 0);
                console.log('Полный ответ (скопируй и отправь при проблеме):', data);
                console.groupEnd();

                if (data.hotels && data.hotels.length > 0) {
                    const unique = [];
                    const seen = new Set();
                    data.hotels.forEach(h => {
                        const key = h.slug || `${h.name}-${h.city}`;
                        if (!seen.has(key)) {
                            seen.add(key);
                            unique.push(h);
                        }
                    });
                    allHotels = unique;
                    displayHotels(unique);
                } else {
                    if (data.error) console.warn('[VIP отели] Причина пустого списка:', data.error);
                    const hint = document.getElementById('vip-empty-hint');
                    if (hint) {
                        hint.textContent = (data.message && String(data.message).trim())
                            ? String(data.message).trim()
                            : 'Сейчас список VIP-отелей пуст. Загляните позже или выберите туры в Турцию на главной.';
                        hint.classList.remove('hidden');
                    }
                    emptyState.classList.remove('hidden');
                }
            } catch (error) {
                console.error('[VIP отели] Ошибка загрузки:', error);
                console.log('URL запроса:', url);
                container.innerHTML = '<div class="col-span-full text-center text-red-600">Ошибка загрузки данных</div>';
            } finally {
                loading.classList.add('hidden');
            }
        }

        /* ─── Экранирование для вставки в HTML ─── */
        function escHtml(s) {
            if (s == null || s === '') return '';
            const d = document.createElement('div');
            d.textContent = String(s);
            return d.innerHTML;
        }

        /* ─── Форматирование цены ─── */
        function fmtPrice(n) {
            return Number(n).toLocaleString('ru-RU') + '\u00a0\u20bd';
        }

        /* ─── Загрузка цены для одного отеля ─── */
        async function loadHotelPrice(slug) {
            try {
                const r = await fetch(`/backend/api/vip-hotel-minprice.php?slug=${encodeURIComponent(slug)}`);
                const d = await r.json();
                return (d && d.success && d.minPrice) ? d.minPrice : null;
            } catch (e) {
                return null;
            }
        }

        /* ─── Патч цены на уже вставленной карточке ─── */
        function applyPrice(slug, price) {
            const priceEl = document.querySelector(`.vip-price-val[data-slug="${slug}"]`);
            if (!priceEl) return;
            if (price) {
                priceEl.innerHTML = `<span style="font-size:11px;color:#94a3b8;display:block;margin-bottom:1px">от</span>`
                    + `<strong style="font-size:20px;font-weight:800;color:#FF6B6B">${fmtPrice(price)}</strong>`;
                priceEl.closest('.vip-price-block').style.opacity = '1';
            } else {
                priceEl.innerHTML = `<span style="font-size:12px;color:#94a3b8">уточняйте цену</span>`;
                priceEl.closest('.vip-price-block').style.opacity = '1';
            }
        }

        /* ─── Отображение отелей + запуск загрузки цен ─── */
        function displayHotels(hotels) {
            const container = document.getElementById('hotels-container');
            const emptyState = document.getElementById('empty-state');

            if (hotels.length === 0) {
                emptyState.classList.remove('hidden');
                return;
            }

            emptyState.classList.add('hidden');
            const hintEl = document.getElementById('vip-empty-hint');
            if (hintEl) { hintEl.classList.add('hidden'); hintEl.textContent = ''; }
            const unique = [];
            const seen = new Set();
            hotels.forEach(h => {
                const key = h.slug || `${h.name}-${h.city}`;
                if (!seen.has(key)) { seen.add(key); unique.push(h); }
            });

            const cityNames = { Antalya: 'Анталья', Belek: 'Белек', Kemer: 'Кемер' };
            const fallbackImg = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300"%3E%3Crect fill="%23e2e8f0" width="400" height="300"/%3E%3Ctext fill="%2394a3b8" font-family="sans-serif" font-size="18" x="50%25" y="50%25" text-anchor="middle" dy=".3em"%3ENo image%3C/text%3E%3C/svg%3E';

            container.innerHTML = unique.map(hotel => {
                const cityName = cityNames[hotel.city] || escHtml(hotel.city);
                const imageUrl = hotel.image || fallbackImg;
                const slug     = hotel.slug || '';
                const nameSafe = escHtml(hotel.name);
                const descSafe = hotel.description ? escHtml(hotel.description) : '';

                return `<div class="hotel-card rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden hover:shadow-md transition-shadow" data-hotel-slug="${escHtml(slug)}">
                    <div class="relative h-64 overflow-hidden">
                        <img src="${escHtml(imageUrl)}" alt="${nameSafe}" class="vip-card-photo w-full h-full object-cover">
                        <div class="absolute top-4 right-4 bg-white/90 backdrop-blur-sm px-3 py-1 rounded-full text-sm font-semibold text-indigo-600">
                            ${escHtml(hotel.rating || '5*')}
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-semibold text-indigo-600 uppercase tracking-wide">${cityName}</span>
                        </div>
                        <h3 class="heading-font text-xl font-bold text-slate-900 mb-2">${nameSafe}</h3>
                        ${descSafe ? `<p class="text-slate-600 text-sm mb-3 line-clamp-2">${descSafe}</p>` : ''}

                        <p class="text-sm text-slate-500 mb-4">Цены — в турах на странице отеля</p>

                        <a href="/frontend/window/hotels/hotel-detail.php?slug=${encodeURIComponent(slug)}#vip-tour-search-section"
                           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 text-white font-semibold hover:bg-indigo-700 transition">
                            Смотреть туры <i class="fas fa-arrow-right text-sm"></i>
                        </a>
                    </div>
                </div>`;
            }).join('');

            container.querySelectorAll('.vip-card-photo').forEach(img => {
                img.addEventListener('error', () => { img.src = fallbackImg; }, { once: true });
            });
        }

        // Фильтрация по городу
        function filterHotels(city) {
            currentCity = city;
            
            // Обновление активной кнопки
            document.querySelectorAll('.city-filter-btn').forEach(btn => {
                btn.classList.remove('active', 'bg-indigo-600', 'text-white');
                btn.classList.add('text-slate-600', 'hover:bg-indigo-50');
            });
            
            const activeBtn = document.querySelector(`[data-city="${city}"]`);
            if (activeBtn) {
                activeBtn.classList.add('active', 'bg-indigo-600', 'text-white');
                activeBtn.classList.remove('text-slate-600', 'hover:bg-indigo-50');
            }

            loadHotels(city);
        }

        document.addEventListener('DOMContentLoaded', () => loadHotels());
    </script>
    <?php include __DIR__ . '/../../backend/components/footer.php'; ?>
</body>
</html>