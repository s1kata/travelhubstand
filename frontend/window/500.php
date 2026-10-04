<?php
declare(strict_types=1);
http_response_code(500);
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Ошибка сервера — Travel Hub</title>
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <?php include __DIR__ . '/../../backend/components/design_system_head.php'; ?>
</head>
<body class="min-h-screen bg-gradient-to-b from-slate-50 to-white text-slate-800 flex flex-col items-center justify-center px-4">
    <div class="text-center max-w-md">
        <p class="text-6xl font-bold text-amber-400 mb-2">500</p>
        <h1 class="text-2xl font-semibold text-slate-900 mb-3">Ошибка сервера</h1>
        <p class="text-slate-600 mb-8">Что-то пошло не так. Попробуйте обновить страницу позже.</p>
        <a href="/frontend/index.php" class="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-gradient-to-r from-[#5DA9A4] to-[#8CC7C3] text-white font-medium shadow-md hover:opacity-95 transition-opacity">На главную</a>
    </div>
    <?php include __DIR__ . '/../../backend/components/footer.php'; ?>
</body>
</html>
