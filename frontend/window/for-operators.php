<?php
require_once __DIR__ . '/../../backend/config/config.php';
session_start();

$page_title = 'Для туроператоров';
$current_page = 'for-operators';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title); ?> | Travel Hub</title>
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="alternate icon" href="/frontend/favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="/frontend/css/pages/for-operators.css?v=1">
    <?php include __DIR__ . '/../../backend/components/design_system_head.php'; ?>
    </head>
<body class="min-h-screen">
    <?php include __DIR__ . '/../../backend/components/header.php'; ?>

    <main class="relative z-10 pt-20 md:pt-24 pb-8 md:pb-12">
        <div class="th-container mx-auto px-4 sm:px-6 max-w-6xl">
            <div class="text-center mb-8">
                <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-teal-100 text-teal-700 text-sm font-medium mb-4">
                    <i class="fas fa-briefcase"></i>
                    Для менеджеров
                </span>
                <h1 class="heading-font text-2xl sm:text-3xl font-bold text-slate-900 mb-2">Для туроператоров</h1>
                <p class="text-slate-600">Поиск туров Tourvisor — рабочий инструмент для подбора.</p>
            </div>

            <div class="bg-white/95 backdrop-blur rounded-2xl shadow-xl border border-sky-100 p-4 sm:p-6 md:p-8">
                <div class="tv-widget-embed-wrap">
                    <div class="tv-search-form tv-moduleid-9976617"></div>
                </div>
                <script type="text/javascript" src="https://tourvisor.ru/module/init.js"></script>
            </div>

            <p class="mt-6 text-center text-slate-500 text-sm">
                <a href="/index.php" class="text-sky-600 hover:text-sky-700">← На главную</a>
            </p>
        </div>
    </main>

    <?php include __DIR__ . '/../../backend/components/footer.php'; ?>
</body>
</html>