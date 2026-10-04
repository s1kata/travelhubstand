<?php
declare(strict_types=1);
http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../../backend/config/contacts.php';
$thc = th_contacts();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Страница не найдена — Travel Hub</title>
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__ . '/../../backend/components/design_system_head.php'; ?>
</head>
<body class="min-h-screen text-slate-800" style="background:linear-gradient(180deg,#eef6f5 0%,#fff 45%);">
    <?php
    $current_page = '';
    include __DIR__ . '/../../backend/components/header.php';
    ?>
    <main class="flex flex-col items-center justify-center px-4 pt-28 pb-16">
        <div class="text-center max-w-lg">
            <p class="heading-font text-6xl font-extrabold text-[#1A1A40] mb-2" style="font-family:Outfit,sans-serif">404</p>
            <h1 class="text-2xl font-semibold text-slate-900 mb-3">Страница не найдена</h1>
            <p class="text-slate-600 mb-8">Зато менеджер на связи — подберём тур или подскажем нужную страницу.</p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center mb-10">
                <a href="/frontend/index.php" class="inline-flex items-center justify-center min-h-[48px] px-6 rounded-xl bg-[#FF6B6B] text-white font-bold shadow-md">На главную</a>
                <a href="tel:<?php echo htmlspecialchars($thc['phone_tel'], ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex items-center justify-center min-h-[48px] px-6 rounded-xl bg-[#5DA9A4] text-white font-bold">
                    <?php echo htmlspecialchars($thc['phone_display'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
                <a href="<?php echo htmlspecialchars($thc['max_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center min-h-[48px] px-6 rounded-xl bg-[#0F1C3F] text-white font-bold">MAX</a>
            </div>
        </div>
    </main>
    <?php
    $th_cta_source = '404_recovery';
    $th_cta_title = 'Не нашли страницу — оставьте телефон';
    $th_cta_sub = 'Перезвоним за 15 минут и поможем с туром или нужным разделом сайта.';
    $th_cta_id = 'th-404-cta';
    include __DIR__ . '/../../backend/components/page_cta_band.php';
    include __DIR__ . '/../../backend/components/footer.php';
    ?>
</body>
</html>
