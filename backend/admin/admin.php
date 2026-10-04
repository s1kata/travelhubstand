<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../components/tour_bookings_schema.php';

session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? null) !== 'admin') {
    header('Location: ../../frontend/window/login.php');
    exit;
}

$stats = [
    'users' => 0,
    'admins' => 0,
];

$tourBookingCounts = ['website' => 0, 'app' => 0, 'total' => 0];

$recentUsers = [];

if ($pdo) {
    try {
        $stats['users'] = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $stats['admins'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

        $recentUsersStmt = $pdo->query('SELECT name, email, reg_date FROM users ORDER BY reg_date DESC LIMIT 5');
        $recentUsers = $recentUsersStmt->fetchAll();

        ensureTourBookingsTable($pdo);
        $tourBookingCounts['website'] = (int) $pdo->query(
            "SELECT COUNT(*) FROM tour_bookings WHERE COALESCE(NULLIF(TRIM(request_source), ''), 'website') = 'website'"
        )->fetchColumn();
        $tourBookingCounts['app'] = (int) $pdo->query(
            "SELECT COUNT(*) FROM tour_bookings WHERE TRIM(COALESCE(request_source, '')) = 'app'"
        )->fetchColumn();
        $tourBookingCounts['total'] = $tourBookingCounts['website'] + $tourBookingCounts['app'];
    } catch (PDOException $e) {
        error_log('[admin] ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title>Админ-панель | Travel Hub</title>
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="alternate icon" href="/frontend/favicon.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --font-sans: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            --bg-body: #f4f9ff;
            --bg-surface: #ffffff;
            --bg-muted: #eaf3ff;
            --accent-primary: #3ba3ff;
            --accent-secondary: #7bc4ff;
            --text-primary: #1f2a44;
            --text-secondary: #4f5f78;
            --border-soft: rgba(59, 163, 255, 0.18);
            --shadow-soft: 0 22px 48px rgba(59, 163, 255, 0.18);
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: var(--font-sans);
            background: linear-gradient(180deg, #f8fbff 0%, #eff5ff 45%, #fdfdff 100%);
            color: var(--text-primary);
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            width: 100%;
            max-width: 100vw;
        }
        .heading-font { font-family: var(--font-sans); font-weight: 600; }
        .metric-card {
            background: var(--bg-surface);
            border-radius: 20px;
            border: 1px solid var(--border-soft);
            box-shadow: var(--shadow-soft);
            padding: 1.25rem;
        }
        .eyebrow-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: 0.32em;
            font-size: 0.65rem;
            background: rgba(59, 163, 255, 0.12);
            border: 1px solid rgba(59, 163, 255, 0.24);
            color: var(--text-primary);
        }
        
        /* Мобильная адаптация */
        @media (max-width: 767px) {
            body {
                font-size: 14px;
            }
            
            header {
                padding: 0.75rem 1rem !important;
            }
            
            header .th-container {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 1rem !important;
            }
            
            header .flex.items-center {
                flex-wrap: wrap;
                width: 100%;
                justify-content: space-between;
            }
            
            header a {
                font-size: 0.875rem;
                padding: 0.5rem 0.75rem !important;
            }
            
            .heading-font.text-2xl {
                font-size: 1.25rem !important;
            }
            
            h1 {
                font-size: 1.75rem !important;
                line-height: 1.3 !important;
            }
            
            .text-4xl {
                font-size: 2rem !important;
            }
            
            .th-container {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
            
            main {
                padding-top: 1rem !important;
                padding-bottom: 1rem !important;
            }
            
            .space-y-12 > * + * {
                margin-top: 2rem !important;
            }
            
            .metric-card {
                padding: 1rem !important;
                border-radius: 16px !important;
            }
            
            .text-3xl {
                font-size: 1.75rem !important;
            }
            
            .grid {
                gap: 1rem !important;
            }
            
            .grid-cols-1.sm\:grid-cols-2 {
                grid-template-columns: 1fr !important;
            }
            
            .grid-cols-1.md\:grid-cols-2.lg\:grid-cols-3 {
                grid-template-columns: 1fr !important;
            }
            
            .rounded-xl {
                padding: 0.75rem 1rem !important;
                font-size: 0.875rem !important;
            }
            
            .rounded-xl i {
                margin-left: 0.5rem !important;
            }
            
            .text-xs {
                font-size: 0.7rem !important;
            }
            
            .text-sm {
                font-size: 0.8rem !important;
            }
            
            .flex.items-center.justify-between {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 0.5rem !important;
            }
            
            .flex.items-center.justify-between span {
                font-size: 0.7rem !important;
                word-break: break-word;
            }
            
            .max-w-2xl {
                max-width: 100% !important;
            }
            
            .eyebrow-badge {
                font-size: 0.6rem !important;
                padding: 4px 12px !important;
                letter-spacing: 0.2em !important;
            }
        }
        
        @media (min-width: 768px) and (max-width: 1023px) {
            .grid-cols-1.md\:grid-cols-2.lg\:grid-cols-3 {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }
    </style>
</head>
<body class="bg-transparent text-slate-900 min-h-screen">
    <header class="backdrop-blur-md bg-white/90 border-b border-sky-100 sticky top-0 z-40 shadow-sm">
        <div class="th-container mx-auto px-4 sm:px-6 py-3 sm:py-5">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4">
                <a href="/index.php" class="flex items-center gap-2 sm:gap-3">
                    <span class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 flex items-center justify-center shadow-lg flex-shrink-0">
                        <i class="fas fa-plane text-white text-xs sm:text-base"></i>
                    </span>
                    <span class="heading-font text-lg sm:text-2xl font-bold text-sky-600">Travel Hub Admin</span>
                </a>
                <div class="flex flex-wrap items-center gap-2 sm:gap-3 text-xs sm:text-sm text-slate-600 w-full sm:w-auto">
                    <span class="flex items-center gap-1 sm:gap-2"><i class="fas fa-user-shield text-sky-500"></i><span class="hidden sm:inline"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span><span class="sm:hidden"><?php echo htmlspecialchars(mb_substr($_SESSION['user_name'] ?? 'Admin', 0, 10)); ?></span></span>
                    <a href="../scripts/logout.php" class="px-3 sm:px-4 py-1.5 sm:py-2 rounded-full bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 text-white shadow-md hover:shadow-lg transition text-xs sm:text-sm whitespace-nowrap">Выход</a>
                </div>
            </div>
        </div>
    </header>

    <main class="py-8 sm:py-12 md:py-16">
        <div class="th-container mx-auto px-4 sm:px-6">
            <div class="max-w-6xl mx-auto space-y-8 sm:space-y-12">
                <div class="text-center space-y-3 sm:space-y-4">
                    <span class="eyebrow-badge inline-flex items-center gap-2">
                        <i class="fas fa-chart-pie"></i>
                        Admin Console
                    </span>
                    <h1 class="heading-font text-2xl sm:text-3xl md:text-4xl font-bold text-slate-900 px-2">Контрольная панель Travel Hub</h1>
                    <p class="text-slate-600 max-w-2xl mx-auto text-sm sm:text-base px-2">Отслеживайте пользователей, заявки и туры, хранящиеся в защищённой SQL-базе, и управляйте сервисом в едином интерфейсе.</p>
                </div>

                <section class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                        <?php
                        $metricIcons = [
                            'users' => 'fa-users',
                            'admins' => 'fa-user-shield',
                        ];
                        $metricLabels = [
                            'users' => 'Пользователи',
                            'admins' => 'Админы',
                        ];
                        foreach ($stats as $key => $value):
                    ?>
                        <div class="metric-card p-4 sm:p-5">
                            <div class="flex items-center justify-between text-slate-600 text-xs uppercase tracking-[0.2em] sm:tracking-[0.3em] mb-2 sm:mb-3">
                                <span class="text-[0.65rem] sm:text-xs"><?php echo $metricLabels[$key]; ?></span>
                                <i class="fas <?php echo $metricIcons[$key]; ?> text-sky-500 text-sm sm:text-base"></i>
                            </div>
                            <div class="heading-font text-2xl sm:text-3xl font-semibold text-slate-900"><?php echo number_format($value, 0, '.', ' '); ?></div>
                        </div>
                        <?php endforeach; ?>
                </section>

                <section class="metric-card p-4 sm:p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4 mb-4">
                        <h2 class="heading-font text-xl sm:text-2xl font-semibold text-slate-900 flex items-center gap-2">
                            <i class="fas fa-clipboard-list text-sky-500 text-base sm:text-lg"></i>
                            <span>Заявки на туры</span>
                        </h2>
                        <a href="tour-bookings.php" class="inline-flex items-center justify-center gap-2 rounded-full border border-sky-200 bg-sky-50 px-4 py-2 text-sm font-medium text-sky-700 hover:bg-sky-100 transition whitespace-nowrap">
                            Все заявки <i class="fas fa-arrow-right text-xs"></i>
                        </a>
                    </div>
                    <p class="text-slate-600 text-sm mb-4">Сколько заявок сохранено в базе сайта: с формы на сайте и из мобильного приложения (после успешной отправки в CRM).</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                        <div class="rounded-xl border border-sky-100 bg-gradient-to-br from-white to-sky-50/60 px-4 py-3 sm:py-4">
                            <div class="text-[0.65rem] sm:text-xs uppercase tracking-[0.2em] text-slate-500 mb-1">Всего</div>
                            <div class="heading-font text-2xl sm:text-3xl font-semibold text-slate-900"><?php echo number_format($tourBookingCounts['total'], 0, '.', ' '); ?></div>
                        </div>
                        <div class="rounded-xl border border-emerald-100 bg-white px-4 py-3 sm:py-4">
                            <div class="flex items-center gap-2 text-[0.65rem] sm:text-xs uppercase tracking-[0.2em] text-emerald-700 mb-1">
                                <i class="fas fa-globe"></i> Сайт
                            </div>
                            <div class="heading-font text-2xl sm:text-3xl font-semibold text-slate-900"><?php echo number_format($tourBookingCounts['website'], 0, '.', ' '); ?></div>
                        </div>
                        <div class="rounded-xl border border-violet-100 bg-white px-4 py-3 sm:py-4">
                            <div class="flex items-center gap-2 text-[0.65rem] sm:text-xs uppercase tracking-[0.2em] text-violet-700 mb-1">
                                <i class="fas fa-mobile-screen-button"></i> Приложение
                            </div>
                            <div class="heading-font text-2xl sm:text-3xl font-semibold text-slate-900"><?php echo number_format($tourBookingCounts['app'], 0, '.', ' '); ?></div>
                        </div>
                    </div>
                </section>

                <section>
                    <div class="metric-card p-4 sm:p-6">
                        <h2 class="heading-font text-xl sm:text-2xl font-semibold text-slate-900 mb-3 sm:mb-4 flex items-center gap-2"><i class="fas fa-user-plus text-sky-500 text-base sm:text-lg"></i><span>Новые пользователи</span></h2>
                        <?php if (!empty($recentUsers)): ?>
                            <div class="space-y-2 sm:space-y-3">
                                <?php foreach ($recentUsers as $user): ?>
                                    <div class="rounded-xl border border-sky-100 bg-white px-3 sm:px-4 py-2 sm:py-3 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 sm:gap-4">
                                        <div class="flex-1 min-w-0">
                                            <p class="heading-font text-sm sm:text-base text-slate-900 truncate"><?php echo htmlspecialchars($user['name']); ?></p>
                                            <p class="text-xs text-slate-600 truncate"><?php echo htmlspecialchars($user['email']); ?></p>
                                        </div>
                                        <span class="text-[0.65rem] sm:text-xs text-slate-400 uppercase tracking-[0.2em] sm:tracking-[0.3em] whitespace-nowrap flex-shrink-0"><?php echo htmlspecialchars($user['reg_date']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-slate-600 text-sm sm:text-base">Пока нет новых регистраций.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="metric-card p-4 sm:p-6">
                    <h2 class="heading-font text-xl sm:text-2xl font-semibold text-slate-900 mb-4 sm:mb-6 flex items-center gap-2"><i class="fas fa-toolbox text-sky-500 text-base sm:text-lg"></i><span>Утилиты администратора</span></h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                        <a href="view_database.php" class="rounded-xl bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 text-white px-3 sm:px-4 py-2.5 sm:py-3 font-medium shadow-md hover:shadow-lg transition flex items-center justify-between text-sm sm:text-base">
                            <span class="truncate">Просмотр пользователей</span> <i class="fas fa-users ml-2 flex-shrink-0"></i>
                        </a>
                        <a href="tour-bookings.php" class="rounded-xl border border-sky-100 bg-white px-3 sm:px-4 py-2.5 sm:py-3 text-slate-700 font-medium flex items-center justify-between hover:bg-sky-50 transition text-sm sm:text-base">
                            <span class="truncate">Заявки на туры</span> <i class="fas fa-clipboard-list text-sky-500 ml-2 flex-shrink-0"></i>
                        </a>
                        <a href="set_admin_role.php" class="rounded-xl border border-sky-100 bg-white px-3 sm:px-4 py-2.5 sm:py-3 text-slate-700 font-medium flex items-center justify-between hover:bg-sky-50 transition text-sm sm:text-base">
                            <span class="truncate">Роли и статусы (админ, менеджер)</span> <i class="fas fa-user-shield text-sky-500 ml-2 flex-shrink-0"></i>
                        </a>
                        <a href="edit-country-content.php" class="rounded-xl border border-sky-100 bg-white px-3 sm:px-4 py-2.5 sm:py-3 text-slate-700 font-medium flex items-center justify-between hover:bg-sky-50 transition text-sm sm:text-base">
                            <span class="truncate">Редактировать контент стран</span> <i class="fas fa-globe text-sky-500 ml-2 flex-shrink-0"></i>
                        </a>
                        <a href="edit-vip-hotels.php" class="rounded-xl border border-sky-100 bg-white px-3 sm:px-4 py-2.5 sm:py-3 text-slate-700 font-medium flex items-center justify-between hover:bg-sky-50 transition text-sm sm:text-base">
                            <span class="truncate">VIP Отели Турции</span> <i class="fas fa-hotel text-sky-500 ml-2 flex-shrink-0"></i>
                        </a>
                        <a href="manage-videos.php" class="rounded-xl border border-sky-100 bg-white px-3 sm:px-4 py-2.5 sm:py-3 text-slate-700 font-medium flex items-center justify-between hover:bg-sky-50 transition text-sm sm:text-base">
                            <span class="truncate">Управление видео</span> <i class="fas fa-video text-sky-500 ml-2 flex-shrink-0"></i>
                        </a>
                        <a href="manage-office-employees.php" class="rounded-xl border border-sky-100 bg-white px-3 sm:px-4 py-2.5 sm:py-3 text-slate-700 font-medium flex items-center justify-between hover:bg-sky-50 transition text-sm sm:text-base">
                            <span class="truncate">Сотрудники офисов</span> <i class="fas fa-users text-sky-500 ml-2 flex-shrink-0"></i>
                        </a>
                        <a href="manage-office-photos.php" class="rounded-xl border border-sky-100 bg-white px-3 sm:px-4 py-2.5 sm:py-3 text-slate-700 font-medium flex items-center justify-between hover:bg-sky-50 transition text-sm sm:text-base">
                            <span class="truncate">Фотографии офисов</span> <i class="fas fa-images text-sky-500 ml-2 flex-shrink-0"></i>
                        </a>
                        <a href="manage-services.php" class="rounded-xl border border-sky-100 bg-white px-3 sm:px-4 py-2.5 sm:py-3 text-slate-700 font-medium flex items-center justify-between hover:bg-sky-50 transition text-sm sm:text-base">
                            <span class="truncate">Услуги и цены</span> <i class="fas fa-tags text-sky-500 ml-2 flex-shrink-0"></i>
                        </a>
                        <a href="manage-yandex-feed.php" class="rounded-xl border border-sky-100 bg-white px-3 sm:px-4 py-2.5 sm:py-3 text-slate-700 font-medium flex items-center justify-between hover:bg-sky-50 transition text-sm sm:text-base">
                            <span class="truncate">Фид Яндекс (YML) — офферы и правила /feed.yml</span> <i class="fas fa-rss text-sky-500 ml-2 flex-shrink-0"></i>
                        </a>
                        <a href="update_offices.php" class="rounded-xl border border-sky-100 bg-white px-3 sm:px-4 py-2.5 sm:py-3 text-slate-700 font-medium flex items-center justify-between hover:bg-sky-50 transition text-sm sm:text-base">
                            <span class="truncate">Обновить адреса офисов</span> <i class="fas fa-map-marker-alt text-sky-500 ml-2 flex-shrink-0"></i>
                        </a>
                        <a href="add_employee_info_column.php" class="rounded-xl border border-sky-100 bg-white px-3 sm:px-4 py-2.5 sm:py-3 text-slate-700 font-medium flex items-center justify-between hover:bg-sky-50 transition text-sm sm:text-base">
                            <span class="truncate">Добавить поле info сотрудникам</span> <i class="fas fa-database text-sky-500 ml-2 flex-shrink-0"></i>
                        </a>
                    </div>
                </section>
            </div>
        </div>
    </main>
</body>
</html>