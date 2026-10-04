<?php
require_once __DIR__ . '/../../backend/config/config.php';
session_start();

// Получаем настройки страницы из БД
$pageSettings = [
    'button_text' => 'Перейти на RuTube',
    'page_text' => 'Видеообзоры отелей от наших экспертов. Узнайте больше о комфорте, сервисе и атмосфере отелей перед бронированием.',
    'rutube_url' => ''
];

try {
    // Создаем таблицу, если её нет
    $pdo->exec("CREATE TABLE IF NOT EXISTS video_page_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        button_text TEXT DEFAULT 'Перейти на RuTube',
        page_text TEXT DEFAULT 'Видеообзоры отелей от наших экспертов. Узнайте больше о комфорте, сервисе и атмосфере отелей перед бронированием.',
        rutube_url TEXT DEFAULT '',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $stmt = $pdo->query("SELECT * FROM video_page_settings LIMIT 1");
    $settings = $stmt->fetch();
    if ($settings) {
        $pageSettings = [
            'button_text' => $settings['button_text'] ?? 'Перейти на RuTube',
            'page_text' => $settings['page_text'] ?? 'Видеообзоры отелей от наших экспертов. Узнайте больше о комфорте, сервисе и атмосфере отелей перед бронированием.',
            'rutube_url' => $settings['rutube_url'] ?? ''
        ];
    }
} catch (PDOException $e) {
    error_log('[Video Page] Error fetching settings: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title>Видеообзоры отелей - Travel Hub</title>
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="alternate icon" href="/frontend/favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="/frontend/css/pages/video-tutorials.css?v=1">
    <?php include __DIR__ . '/../../backend/components/design_system_head.php'; ?>
    </head>
<body class="ds-page text-slate-900 antialiased">
    <?php 
    $current_page = 'video-tutorials';
    include __DIR__ . '/../../backend/components/header.php'; 
    ?>

    <!-- Hero Section -->
    <section class="relative py-12 sm:py-16 md:py-20 lg:py-28 hero-background">
        <div class="th-container mx-auto px-4 sm:px-6 relative z-10">
            <div class="text-center max-w-4xl mx-auto">
                <span class="inline-flex items-center gap-2 px-3 sm:px-4 py-2 rounded-full bg-indigo-50 border border-indigo-100 text-xs uppercase tracking-[0.2em] sm:tracking-[0.28em] text-indigo-600 mb-4 sm:mb-6">Помощь и поддержка</span>
                <h1 class="heading-font text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-bold text-slate-900 mb-4 sm:mb-6">Видеоинструкции</h1>
                <p class="text-base sm:text-lg md:text-xl text-slate-600 mb-6 sm:mb-8 px-2"><?php echo htmlspecialchars($pageSettings['page_text']); ?></p>
            </div>
        </div>
    </section>

    <!-- Main Video Section -->
    <section class="py-8 sm:py-12 md:py-16">
        <div class="th-container mx-auto px-4 sm:px-6">
            <div class="max-w-7xl mx-auto">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8 lg:gap-10">
                    <!-- Main Video Player -->
                    <div class="lg:col-span-2">
                        <div class="surface-card p-4 sm:p-6">
                            <div class="video-player-container mb-6" id="main-video-player">
                                <iframe 
                                    id="main-video-iframe"
                                    src="https://rutube.ru/play/embed/dQw4w9WgXcQ" 
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                    allowfullscreen
                                    loading="lazy">
                                </iframe>
                            </div>
                            <div id="main-video-info">
                                <h2 class="heading-font text-xl sm:text-2xl font-bold text-slate-900 mb-3" id="main-video-title">
                                    Обзор отеля
                                </h2>
                                <p class="text-slate-600" id="main-video-description">
                                    Детальный видеообзор отеля от наших экспертов. Узнайте все о комфорте, сервисе и атмосфере отеля перед бронированием.
                                </p>
                            </div>
                        </div>

                        <!-- Video Transcript / Key Points -->
                        <div class="surface-card p-6 sm:p-8 mt-6">
                            <h3 class="heading-font text-xl font-bold text-slate-900 mb-4">Что вы узнаете из обзора</h3>
                            <div class="text-slate-600 space-y-3" id="video-key-points">
                                <p>• Комфорт и оснащение номеров</p>
                                <p>• Уровень сервиса и обслуживания</p>
                                <p>• Инфраструктура отеля и территории</p>
                                <p>• Питание и рестораны</p>
                                <p>• Расположение и окрестности</p>
                            </div>
                        </div>
                    </div>

                    <!-- Video List Sidebar -->
                    <div class="lg:col-span-1">
                        <div class="surface-card p-4 sm:p-6">
                            <!-- Filters -->
                            <div class="mb-6">
                                <h3 class="heading-font text-lg font-bold text-slate-900 mb-4">Категории</h3>
                                <div class="flex flex-wrap">
                                    <span class="filter-tag active" data-filter="all">Все</span>
                                    <span class="filter-tag" data-filter="booking">Бронирование</span>
                                    <span class="filter-tag" data-filter="reporting">Отчетность</span>
                                    <span class="filter-tag" data-filter="mobile">Мобильное приложение</span>
                                </div>
                            </div>

                            <!-- Video List -->
                            <div class="video-list">
                                <!-- Video items will be populated here -->
                                <div class="video-list-item active" data-video-url="https://rutube.ru/video/dQw4w9WgXcQ/" data-category="booking" data-title="Обзор отеля" data-description="Детальный видеообзор отеля от наших экспертов. Узнайте все о комфорте, сервисе и атмосфере отеля перед бронированием.">
                                    <div class="video-thumbnail-container">
                                        <img src="https://st.rutube.ru/thumbs/320x240/dQw4w9WgXcQ.jpg" alt="Видео" class="video-thumbnail" onerror="this.src='https://via.placeholder.com/320x240?text=Video'">
                                        <span class="video-duration">5:23</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h4 class="font-semibold text-slate-900 mb-1 text-sm sm:text-base">Обзор отеля</h4>
                                        <p class="text-xs text-slate-500">5 звезд</p>
                                    </div>
                                </div>

                                <div class="video-list-item" data-video-id="jNQXAC9IVRw" data-category="booking" data-title="Как изменить или отменить бронирование" data-description="Узнайте, как можно изменить параметры уже забронированного тура или отменить бронирование.">
                                    <div class="video-thumbnail-container">
                                        <img src="https://img.youtube.com/vi/jNQXAC9IVRw/mqdefault.jpg" alt="Видео" class="video-thumbnail">
                                        <span class="video-duration">3:45</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h4 class="font-semibold text-slate-900 mb-1 text-sm sm:text-base">Как изменить или отменить бронирование</h4>
                                        <p class="text-xs text-slate-500">Бронирование</p>
                                    </div>
                                </div>

                                <div class="video-list-item" data-video-id="9bZkp7q19f0" data-category="reporting" data-title="Как получить отчет о поездке" data-description="Инструкция по получению и работе с отчетами о ваших поездках в личном кабинете.">
                                    <div class="video-thumbnail-container">
                                        <img src="https://img.youtube.com/vi/9bZkp7q19f0/mqdefault.jpg" alt="Видео" class="video-thumbnail">
                                        <span class="video-duration">4:12</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h4 class="font-semibold text-slate-900 mb-1 text-sm sm:text-base">Как получить отчет о поездке</h4>
                                        <p class="text-xs text-slate-500">Отчетность</p>
                                    </div>
                                </div>

                                <div class="video-list-item" data-video-id="kJQP7kiw5Fk" data-category="mobile" data-title="Работа с мобильным приложением" data-description="Обзор функций мобильного приложения TravelHub63 и как им пользоваться.">
                                    <div class="video-thumbnail-container">
                                        <img src="https://img.youtube.com/vi/kJQP7kiw5Fk/mqdefault.jpg" alt="Видео" class="video-thumbnail">
                                        <span class="video-duration">6:30</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h4 class="font-semibold text-slate-900 mb-1 text-sm sm:text-base">Работа с мобильным приложением</h4>
                                        <p class="text-xs text-slate-500">Мобильное приложение</p>
                                    </div>
                                </div>

                                <div class="video-list-item" data-video-id="fJ9rUzIMcZQ" data-category="booking" data-title="Как выбрать отель и даты" data-description="Пошаговая инструкция по выбору отеля и оптимальных дат для вашего путешествия.">
                                    <div class="video-thumbnail-container">
                                        <img src="https://img.youtube.com/vi/fJ9rUzIMcZQ/mqdefault.jpg" alt="Видео" class="video-thumbnail">
                                        <span class="video-duration">7:15</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h4 class="font-semibold text-slate-900 mb-1 text-sm sm:text-base">Как выбрать отель и даты</h4>
                                        <p class="text-xs text-slate-500">Бронирование</p>
                                    </div>
                                </div>

                                <div class="video-list-item" data-video-id="OPf0YbXqDm0" data-category="reporting" data-title="Экспорт данных для бухгалтерии" data-description="Как экспортировать данные о поездках в форматах, удобных для бухгалтерского учета.">
                                    <div class="video-thumbnail-container">
                                        <img src="https://img.youtube.com/vi/OPf0YbXqDm0/mqdefault.jpg" alt="Видео" class="video-thumbnail">
                                        <span class="video-duration">4:50</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h4 class="font-semibold text-slate-900 mb-1 text-sm sm:text-base">Экспорт данных для бухгалтерии</h4>
                                        <p class="text-xs text-slate-500">Отчетность</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-8 sm:py-12 md:py-16 bg-white">
        <div class="th-container mx-auto px-4 sm:px-6">
            <div class="max-w-4xl mx-auto">
                <div class="text-center mb-12">
                    <h2 class="heading-font text-2xl sm:text-3xl md:text-4xl font-bold text-slate-900 mb-4">Часто задаваемые вопросы</h2>
                    <p class="text-slate-600">Ответы на популярные вопросы о наших видеообзорах отелей</p>
                </div>
                
                <div class="space-y-4">
                    <div class="surface-card p-6">
                        <h3 class="heading-font text-lg font-semibold text-slate-900 mb-2">Как часто обновляются видеообзоры?</h3>
                        <p class="text-slate-600">Мы регулярно добавляем новые видеообзоры отелей. Наши эксперты лично посещают отели и создают детальные обзоры с актуальной информацией.</p>
                    </div>
                    
                    <div class="surface-card p-6">
                        <h3 class="heading-font text-lg font-semibold text-slate-900 mb-2">Можно ли забронировать отель после просмотра обзора?</h3>
                        <p class="text-slate-600">Да, конечно! После просмотра видеообзора вы можете связаться с нами для бронирования отеля или воспользоваться формой поиска туров на странице.</p>
                    </div>
                    
                    <div class="surface-card p-6">
                        <h3 class="heading-font text-lg font-semibold text-slate-900 mb-2">Отели каких стран представлены в обзорах?</h3>
                        <p class="text-slate-600">Мы создаем обзоры отелей из самых популярных туристических направлений: Турция, Египет, ОАЭ, Таиланд, Мальдивы и многие другие страны.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-12 sm:py-16 md:py-20">
        <div class="th-container mx-auto px-4 sm:px-6">
            <div class="max-w-4xl mx-auto">
                <div class="cta-section">
                    <h2 class="heading-font text-2xl sm:text-3xl md:text-4xl font-bold mb-4">Остались вопросы?</h2>
                    <p class="text-lg mb-8 opacity-90">Наши эксперты всегда на связи и готовы помочь вам</p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <?php if (!empty($pageSettings['rutube_url'])): ?>
                        <a href="<?php echo htmlspecialchars($pageSettings['rutube_url']); ?>" target="_blank" rel="noopener noreferrer" class="inline-block bg-white text-indigo-600 px-8 py-4 rounded-lg font-semibold hover:shadow-xl transition transform hover:-translate-y-1">
                            <i class="fas fa-play-circle mr-2"></i><?php echo htmlspecialchars($pageSettings['button_text']); ?>
                        </a>
                        <?php endif; ?>
                        <a href="https://t.me/TravelHub63" target="_blank" rel="noopener noreferrer" class="inline-block bg-white text-indigo-600 px-8 py-4 rounded-lg font-semibold hover:shadow-xl transition transform hover:-translate-y-1">
                            <i class="fab fa-telegram mr-2"></i>Telegram
                        </a>
                        <a href="/frontend/window/contacts.php" class="inline-block bg-white text-indigo-600 px-8 py-4 rounded-lg font-semibold hover:shadow-xl transition transform hover:-translate-y-1">
                            <i class="fas fa-envelope mr-2"></i>Написать
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/../../backend/components/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const videoItems = document.querySelectorAll('.video-list-item');
            const mainVideoIframe = document.getElementById('main-video-iframe');
            const mainVideoTitle = document.getElementById('main-video-title');
            const mainVideoDescription = document.getElementById('main-video-description');
            const filterTags = document.querySelectorAll('.filter-tag');
            
            // Video switching
            videoItems.forEach(item => {
                item.addEventListener('click', function() {
                    const videoUrl = this.getAttribute('data-video-url');
                    const title = this.getAttribute('data-title');
                    const description = this.getAttribute('data-description');
                    
                    // Extract video ID from RuTube URL
                    let videoId = '';
                    if (videoUrl) {
                        const match = videoUrl.match(/rutube\.ru\/video\/([^\/]+)/);
                        if (match) {
                            videoId = match[1];
                        } else {
                            // If it's already an ID
                            videoId = videoUrl;
                        }
                    }
                    
                    // Update main video
                    if (videoId) {
                        mainVideoIframe.src = `https://rutube.ru/play/embed/${videoId}`;
                    }
                    mainVideoTitle.textContent = title;
                    mainVideoDescription.textContent = description;
                    
                    // Update active state
                    videoItems.forEach(v => v.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Scroll to top of video player on mobile
                    if (window.innerWidth < 1024) {
                        document.getElementById('main-video-player').scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            });
            
            // Filter functionality
            filterTags.forEach(tag => {
                tag.addEventListener('click', function() {
                    const filter = this.getAttribute('data-filter');
                    
                    // Update active filter
                    filterTags.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Filter videos
                    videoItems.forEach(item => {
                        const category = item.getAttribute('data-category');
                        if (filter === 'all' || category === filter) {
                            item.style.display = 'flex';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            });
            
            // Extract RuTube video ID from URL (for admin panel integration)
            function getRuTubeVideoId(url) {
                if (!url) return null;
                // Format: https://rutube.ru/video/{id}/
                const match = url.match(/rutube\.ru\/video\/([^\/\?]+)/);
                if (match) {
                    return match[1];
                }
                // If it's already just an ID
                if (url.length > 0 && !url.includes('http')) {
                    return url;
                }
                return null;
            }
        });
    </script>

</body>
</html>