<?php
require_once __DIR__ . '/../../../backend/config/config.php';
session_start();

$slug = $_GET['slug'] ?? null;
$hotel = null;
$error = null;

if (!$slug) {
    $error = 'Не передан slug отеля.';
} elseif (!$pdo) {
    $error = 'Ошибка подключения к базе данных.';
} else {
    require_once __DIR__ . '/../../../backend/components/vip_hotels_schema.php';
    require_once __DIR__ . '/../../../backend/components/vip_hotel_display_defaults.php';
    try {
        vip_hotels_ensure_table($pdo);
    } catch (Throwable $e) {
        error_log('[hotel-detail] vip_hotels_ensure_table: ' . $e->getMessage());
    }
    try {
        $stmt = $pdo->prepare('SELECT * FROM vip_hotels WHERE slug = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$slug]);
        $hotel = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($hotel) {
            $hotel['images'] = $hotel['images'] ? (json_decode($hotel['images'], true) ?: []) : [];
            $hotel['features'] = $hotel['features'] ? (json_decode($hotel['features'], true) ?: []) : [];
            $hotel['detailed_info'] = $hotel['detailed_info'] ? (json_decode($hotel['detailed_info'], true) ?: []) : [];
            $hotel = vip_hotels_enrich_hotel_array($hotel);
        } else {
            $error = 'Отель не найден или не активен.';
        }
    } catch (Exception $e) {
        $error = 'Ошибка загрузки данных: ' . $e->getMessage();
    }
}

$cityNames = [
    'Antalya' => 'Анталья',
    'Belek' => 'Белек',
    'Kemer' => 'Кемер',
];

function truncateText($text, $limit = 280) {
    $text = trim($text);
    if (mb_strlen($text) <= $limit) return $text;
    return mb_substr($text, 0, $limit - 1) . '…';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title><?php echo $hotel ? htmlspecialchars($hotel['name']) : 'Отель'; ?> - Travel Hub</title>
    <?php if (!empty($hotel['images'][0])): ?>
        <link rel="preload" as="image" href="<?php echo htmlspecialchars($hotel['images'][0]); ?>" fetchpriority="high">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
        <link rel="stylesheet" href="/frontend/css/pages/hotel-detail.css?v=1">
    <?php include __DIR__ . '/../../../backend/components/design_system_head.php'; ?>
    </head>
<body class="text-slate-900">
    <?php 
    $current_page = 'vip-hotels';
    include __DIR__ . '/../../../backend/components/header.php'; 
    ?>

    <main class="pb-16">
        <div class="th-container mx-auto px-6 max-w-6xl">
            <?php if ($error): ?>
                <div class="mt-12 surface-card p-8 text-center">
                    <p class="text-red-600 text-lg font-semibold"><?php echo htmlspecialchars($error); ?></p>
                    <a href="/frontend/window/turkey-vip-hotels.php" class="inline-block mt-4 px-5 py-3 bg-sky-500 text-white rounded-lg hover:bg-sky-600 transition">Вернуться к списку отелей</a>
                </div>
            <?php else: ?>
                <?php
                $aboutBlock = trim((string) ($hotel['bio'] ?? ''));
                if ($aboutBlock === '') {
                    $aboutBlock = trim((string) ($hotel['description'] ?? ''));
                }
                $mainImage = $hotel['images'][0] ?? 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300"%3E%3Crect fill="%23e2e8f0" width="400" height="300"/%3E%3Ctext fill="%2394a3b8" font-family="sans-serif" font-size="18" x="50%25" y="50%25" text-anchor="middle" dy=".3em"%3ENo image%3C/text%3E%3C/svg%3E';
                ?>
                <section class="relative mt-10">
                    <div class="surface-card overflow-hidden">
                        <div class="grid grid-cols-1 lg:grid-cols-2">
                            <div class="relative h-72 lg:h-full">
                                <img src="<?php echo htmlspecialchars($mainImage); ?>" alt="<?php echo htmlspecialchars($hotel['name']); ?>" class="js-vip-hotel-img w-full h-full object-cover" loading="eager" fetchpriority="high" decoding="auto" width="1600" height="900">
                                <div class="absolute top-4 left-4 chip">
                                    <i class="fas fa-location-dot"></i>
                                    <?php echo htmlspecialchars($cityNames[$hotel['city']] ?? $hotel['city']); ?>
                                </div>
                            </div>
                            <div class="p-6 lg:p-10 space-y-5">
                                <div class="flex flex-wrap gap-3 items-center">
                                    <?php if (!empty($hotel['rating'])): ?>
                                        <span class="badge-soft"><i class="fas fa-star text-amber-400"></i><?php echo htmlspecialchars($hotel['rating']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($hotel['meal_plan'])): ?>
                                        <span class="badge-soft"><i class="fas fa-utensils text-emerald-500"></i><?php echo htmlspecialchars($hotel['meal_plan']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($hotel['beach_type'])): ?>
                                        <span class="badge-soft"><i class="fas fa-umbrella-beach text-sky-500"></i><?php echo htmlspecialchars($hotel['beach_type']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <h1 class="heading-font text-3xl lg:text-4xl font-bold text-slate-900">
                                    <?php echo htmlspecialchars($hotel['name']); ?>
                                </h1>
                                <?php if (!empty($hotel['description'])): ?>
                                    <p class="text-slate-600 text-base leading-relaxed"><?php echo htmlspecialchars($hotel['description']); ?></p>
                                <?php endif; ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-slate-700">
                                    <?php if (!empty($hotel['location'])): ?>
                                        <div class="flex items-start gap-2"><i class="fas fa-map-marker-alt text-sky-500 mt-1"></i><span><?php echo htmlspecialchars($hotel['location']); ?></span></div>
                                    <?php endif; ?>
                                    <?php if (!empty($hotel['distance_to_airport'])): ?>
                                        <div class="flex items-start gap-2"><i class="fas fa-plane-departure text-sky-500 mt-1"></i><span><?php echo htmlspecialchars($hotel['distance_to_airport']); ?></span></div>
                                    <?php endif; ?>
                                    <?php if (!empty($hotel['check_in_time'])): ?>
                                        <div class="flex items-start gap-2"><i class="fas fa-door-open text-emerald-500 mt-1"></i><span>Check-in: <?php echo htmlspecialchars($hotel['check_in_time']); ?></span></div>
                                    <?php endif; ?>
                                    <?php if (!empty($hotel['check_out_time'])): ?>
                                        <div class="flex items-start gap-2"><i class="fas fa-door-closed text-amber-500 mt-1"></i><span>Check-out: <?php echo htmlspecialchars($hotel['check_out_time']); ?></span></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <?php if (!empty($hotel['images'])): ?>
                <?php $imagesJson = json_encode($hotel['images'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
                <section class="mt-10">
                    <div class="flex items-center justify-between mb-4">
                            <h2 class="section-title"><i class="fas fa-images text-sky-500"></i>Галерея</h2>
                        <span class="text-sm text-slate-500">Фото: <?php echo count($hotel['images']); ?></span>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        <?php foreach ($hotel['images'] as $idx => $img): ?>
                            <div class="overflow-hidden rounded-xl border border-sky-100 shadow-sm">
                                <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars(($hotel['name'] ?? 'Отель') . ' — ' . ($hotel['city'] ?? 'город') . ', фото ' . ((int)$idx + 1), ENT_QUOTES, 'UTF-8'); ?>" class="js-vip-hotel-img w-full h-32 object-cover hover:scale-105 transition-transform cursor-pointer" data-index="<?php echo $idx; ?>" loading="eager" decoding="auto" width="640" height="360" onclick="openLightbox(<?php echo $idx; ?>)">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <script>
                (function () {
                    var ph = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300"><rect fill="#e2e8f0" width="400" height="300"/><text fill="#94a3b8" font-family="sans-serif" font-size="18" x="50%" y="50%" text-anchor="middle" dy=".3em">No image</text></svg>');
                    function bindVipImgFallback() {
                        document.querySelectorAll('.js-vip-hotel-img').forEach(function (img) {
                            img.addEventListener('error', function () { this.src = ph; }, { once: true });
                        });
                    }
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', bindVipImgFallback);
                    } else {
                        bindVipImgFallback();
                    }
                })();
                </script>

                <section class="mt-10 grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-2 space-y-6">
                        <?php if ($aboutBlock !== ''): ?>
                        <div class="surface-card p-6">
                            <h3 class="section-title"><i class="fas fa-info-circle text-sky-500"></i>Об отеле</h3>
                            <p class="text-slate-600 leading-relaxed mt-3"><?php echo nl2br(htmlspecialchars(truncateText($aboutBlock, 420))); ?></p>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($hotel['detailed_info'])): ?>
                        <div class="surface-card p-6 space-y-4">
                            <h3 class="section-title"><i class="fas fa-list-alt text-sky-500"></i>Главное о гостинице</h3>
                            <?php
                                $labels = [
                                    'infrastructure' => 'Инфраструктура',
                                    'entertainment' => 'Развлечения',
                                    'spa' => 'SPA и wellness',
                                    'for_children' => 'Для детей',
                                ];
                                foreach ($labels as $k => $label):
                                    if (empty($hotel['detailed_info'][$k])) continue;
                                    $value = $hotel['detailed_info'][$k];
                                    $textVal = is_array($value) ? implode(', ', $value) : $value;
                            ?>
                                <div class="border border-slate-100 rounded-lg p-3">
                                    <p class="text-sm font-semibold text-slate-700 mb-1"><?php echo htmlspecialchars($label); ?></p>
                                    <p class="text-slate-600 text-sm leading-relaxed"><?php echo nl2br(htmlspecialchars(truncateText($textVal, 320))); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="space-y-6">
                        <?php if (!empty($hotel['features'])): ?>
                        <div class="surface-card p-6">
                            <h3 class="section-title"><i class="fas fa-check-circle text-emerald-500"></i>Особенности</h3>
                            <ul class="space-y-2 text-sm text-slate-700 mt-3">
                                <?php foreach ($hotel['features'] as $feat): ?>
                                    <li class="flex items-start gap-2">
                                        <span class="mt-1 text-emerald-500"><i class="fas fa-check"></i></span>
                                        <span><?php echo htmlspecialchars($feat); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <div class="surface-card p-6 space-y-3 text-sm text-slate-700">
                            <?php if (!empty($hotel['cuisine'])): ?>
                                <div class="flex items-start gap-2"><i class="fas fa-utensils text-emerald-500 mt-1"></i><span><strong>Кухня:</strong> <?php echo htmlspecialchars($hotel['cuisine']); ?></span></div>
                            <?php endif; ?>
                            <?php if (!empty($hotel['check_in_time'])): ?>
                                <div class="flex items-start gap-2"><i class="fas fa-door-open text-sky-500 mt-1"></i><span><strong>Check-in:</strong> <?php echo htmlspecialchars($hotel['check_in_time']); ?></span></div>
                            <?php endif; ?>
                            <?php if (!empty($hotel['check_out_time'])): ?>
                                <div class="flex items-start gap-2"><i class="fas fa-door-closed text-amber-500 mt-1"></i><span><strong>Check-out:</strong> <?php echo htmlspecialchars($hotel['check_out_time']); ?></span></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <!-- Поиск туров по этому отелю (полный UI: туристы, фильтры, ночей 1–28) -->
                <?php
                $vipHotelName = $hotel['name'] ?? '';
                $vipHotelCity = $hotel['city'] ?? '';
                $vipHotelTvIds = !empty($hotel['tourvisor_hotel_id'])
                    ? (string) (int) $hotel['tourvisor_hotel_id']
                    : '';
                include __DIR__ . '/../../../backend/components/vip_hotels_tour_search.php';
                ?>
            <?php endif; ?>
        </div>
    </main>

    <?php if (!empty($hotel['images'])): ?>
    <div id="lightbox" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden items-center justify-center px-4">
        <div class="relative max-w-5xl w-full">
            <button class="absolute -top-10 right-0 text-white text-2xl" onclick="closeLightbox()"><i class="fas fa-times"></i></button>
            <div class="relative bg-slate-900 rounded-2xl overflow-hidden shadow-2xl">
                <img id="lightbox-image" src="" alt="<?php echo htmlspecialchars(($hotel['name'] ?? 'Отель') . ' — ' . ($hotel['city'] ?? 'город') . ', фото', ENT_QUOTES, 'UTF-8'); ?>" class="w-full max-h-[80vh] object-contain">
                <button class="absolute left-3 top-1/2 -translate-y-1/2 bg-white/70 text-slate-800 rounded-full w-10 h-10 flex items-center justify-center shadow hover:bg-white" onclick="prevLightbox(event)"><i class="fas fa-chevron-left"></i></button>
                <button class="absolute right-3 top-1/2 -translate-y-1/2 bg-white/70 text-slate-800 rounded-full w-10 h-10 flex items-center justify-center shadow hover:bg-white" onclick="nextLightbox(event)"><i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="flex justify-center gap-2 mt-3 flex-wrap text-xs text-white/80" id="lightbox-dots"></div>
        </div>
    </div>

    <script>
        const galleryImages = <?php echo $imagesJson ?? '[]'; ?>;
        let currentIndex = 0;

        function openLightbox(index = 0) {
            currentIndex = index;
            updateLightbox();
            const lb = document.getElementById('lightbox');
            lb.classList.remove('hidden');
            lb.classList.add('flex');
        }

        function closeLightbox() {
            const lb = document.getElementById('lightbox');
            lb.classList.add('hidden');
            lb.classList.remove('flex');
        }

        function updateLightbox() {
            const imgEl = document.getElementById('lightbox-image');
            imgEl.src = galleryImages[currentIndex] || 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300"%3E%3Crect fill="%23e2e8f0" width="400" height="300"/%3E%3Ctext fill="%2394a3b8" font-family="sans-serif" font-size="18" x="50%25" y="50%25" text-anchor="middle" dy=".3em"%3ENo image%3C/text%3E%3C/svg%3E';
            renderDots();
        }

        function prevLightbox(e) {
            e?.stopPropagation();
            currentIndex = (currentIndex - 1 + galleryImages.length) % galleryImages.length;
            updateLightbox();
        }

        function nextLightbox(e) {
            e?.stopPropagation();
            currentIndex = (currentIndex + 1) % galleryImages.length;
            updateLightbox();
        }

        function renderDots() {
            const dots = document.getElementById('lightbox-dots');
            dots.innerHTML = galleryImages.map((_, i) => `
                <button onclick="jumpLightbox(${i})" class="w-2.5 h-2.5 rounded-full ${i === currentIndex ? 'bg-white' : 'bg-white/40'}"></button>
            `).join('');
        }

        function jumpLightbox(i) {
            currentIndex = i;
            updateLightbox();
        }

        document.getElementById('lightbox')?.addEventListener('click', (e) => {
            if (e.target.id === 'lightbox') closeLightbox();
        });
        document.addEventListener('keydown', (e) => {
            const lb = document.getElementById('lightbox');
            if (lb && !lb.classList.contains('hidden')) {
                if (e.key === 'Escape') closeLightbox();
                if (e.key === 'ArrowLeft') prevLightbox();
                if (e.key === 'ArrowRight') nextLightbox();
            }
        });
    </script>
    <?php endif; ?>
    <?php include __DIR__ . '/../../../backend/components/footer.php'; ?>
</body>
</html>