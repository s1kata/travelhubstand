<?php
// $type сюда приходит "tours" или "hotels"
$title = $type === 'hotels' ? 'Отели' : 'Туры';
$widgetId = $type === 'hotels' ? '1234567' : '9974456'; // актуализируйте ID для вашего Tourvisor
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title>Поиск <?=$title?> | TravelHub</title>
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
        <link rel="stylesheet" href="/frontend/css/pages/guest-template.css?v=1">
    <?php include __DIR__ . '/../backend/components/design_system_head.php'; ?>
    </head>
<body>
    <div class="guest-shell">
        <a href="/"><img src="/frontend/favicon.svg" alt="Travel Hub" style="height:48px;"></a>
        <h2>Добро пожаловать в TravelHub!</h2>
        <div class="guest-card">
            <?php
            $tourvisor_widget_module = 'search';
            $tourvisor_widget_container_id = 'guest-tourvisor-search';
            $tourvisor_widget_id = $widgetId;
            include __DIR__ . '/../backend/components/tourvisor_widget_embed.php';
            ?>
        </div>
        <form method="POST" action="/backend/api/guest-booking.php">
            <label>Введите номер вашей брони (TVZ-...):</label>
            <input name="booking_number" type="text" required maxlength="24" pattern="TVZ-\\d+">
            <input type="hidden" name="type" value="<?=$type?>">
            <button type="submit">Отправить</button>
        </form>
    </div>
</body>
</html>