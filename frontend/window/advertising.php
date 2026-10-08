<?php
require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/config/legal.php';
session_start();
$op = th_legal_operator();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title>Согласие на получение рекламы — Travel Hub</title>
    <meta name="description" content="Согласие на получение рекламных сообщений от Travel Hub (travelhub63.ru)">
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/frontend/css/pages/privacy.css?v=2">
    <?php include __DIR__ . '/../../backend/components/design_system_head.php'; ?>
</head>
<body class="text-slate-900">
<?php
$current_page = 'advertising';
include __DIR__ . '/../../backend/components/header.php';
?>

<section class="py-16 sm:py-20 bg-gradient-to-br from-sky-50 via-blue-50 to-white">
    <div class="th-container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto">
            <h1 class="heading-font text-3xl sm:text-4xl md:text-5xl font-bold text-slate-900 mb-4">
                Согласие на получение рекламы
            </h1>
            <p class="text-lg text-slate-700">Отдельное согласие по Федеральному закону № 38-ФЗ «О рекламе»</p>
            <p class="text-sm text-slate-500 mt-2">Редакция от <?php echo htmlspecialchars($op['doc_date'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($op['consent_version'], ENT_QUOTES, 'UTF-8'); ?>)</p>
        </div>
    </div>
</section>

<section class="py-12 sm:py-16 bg-white">
    <div class="th-container mx-auto px-4 sm:px-6 lg:px-8 max-w-4xl">
        <div class="content-section prose prose-lg max-w-none">
            <p>
                Отмечая соответствующий чекбокс на Сайте, я даю предварительное согласие
                <strong><?php echo htmlspecialchars($op['advertiser_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                (ОГРНИП <?php echo htmlspecialchars($op['ogrnip'], ENT_QUOTES, 'UTF-8'); ?>,
                ИНН <?php echo htmlspecialchars($op['inn'], ENT_QUOTES, 'UTF-8'); ?>,
                адрес: <?php echo htmlspecialchars($op['legal_address'], ENT_QUOTES, 'UTF-8'); ?>,
                бренд Travel Hub, сайт <?php echo htmlspecialchars($op['site'], ENT_QUOTES, 'UTF-8'); ?>)
                как рекламораспространителю на получение рекламы о турах, услугах и акциях Travel Hub.
            </p>
            <h2 class="heading-font text-2xl font-bold">Кто даёт согласие</h2>
            <p>Субъект, указавший свои контактные данные в форме Сайта (ФИО, телефон и/или email).</p>
            <h2 class="heading-font text-2xl font-bold">На что даётся согласие</h2>
            <p>Получение рекламных сообщений по сетям электросвязи: SMS, электронная почта, телефонные звонки и сообщения в мессенджерах.</p>
            <h2 class="heading-font text-2xl font-bold">От кого</h2>
            <p>От рекламораспространителя <?php echo htmlspecialchars($op['advertiser_short'], ENT_QUOTES, 'UTF-8'); ?>.</p>
            <h2 class="heading-font text-2xl font-bold">Добровольность</h2>
            <p>Согласие на рекламу не является условием отправки заявки или регистрации. Без отметки чекбокса рекламу не направляем.</p>
            <h2 class="heading-font text-2xl font-bold">Отзыв</h2>
            <p>
                Согласие можно отозвать в любой момент:
            </p>
            <ul>
                <li>по электронной почте: <a href="mailto:<?php echo htmlspecialchars($op['email'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($op['email'], ENT_QUOTES, 'UTF-8'); ?></a></li>
                <li>по телефону: <a href="tel:+78462541656"><?php echo htmlspecialchars($op['phone'], ENT_QUOTES, 'UTF-8'); ?></a></li>
                <li>на почтовый адрес: <?php echo htmlspecialchars($op['postal_address'], ENT_QUOTES, 'UTF-8'); ?></li>
            </ul>
            <p>Обработка персональных данных для исполнения заявки оформляется
                <a href="/frontend/window/consent.php">отдельным согласием</a>
                и не заменяется настоящим документом.</p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../../backend/components/footer.php'; ?>
</body>
</html>
