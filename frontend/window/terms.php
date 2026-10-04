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
    <title>Пользовательское соглашение — Travel Hub</title>
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/frontend/css/pages/terms.css?v=2">
    <?php include __DIR__ . '/../../backend/components/design_system_head.php'; ?>
</head>
<body class="text-slate-900">
<?php $current_page = 'terms'; include __DIR__ . '/../../backend/components/header.php'; ?>

<section class="py-16 sm:py-20 bg-gradient-to-br from-sky-50 via-blue-50 to-white">
    <div class="th-container mx-auto px-4 sm:px-6 lg:px-8 text-center max-w-3xl">
        <h1 class="heading-font text-4xl sm:text-5xl font-bold text-slate-900 mb-4">Пользовательское соглашение</h1>
        <p class="text-xl text-slate-700">Правила использования сайта <?php echo htmlspecialchars($op['site'], ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="text-sm text-slate-500 mt-2">Редакция от <?php echo htmlspecialchars($op['doc_date'], ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
</section>

<section class="py-12 sm:py-16 bg-white">
    <div class="th-container mx-auto px-4 sm:px-6 lg:px-8 max-w-4xl">
        <div class="content-section prose prose-lg max-w-none">
            <h2>1. Общие положения</h2>
            <p>1.1. Настоящее Пользовательское соглашение (далее — «Соглашение») регулирует отношения между <?php echo htmlspecialchars($op['operator_name'], ENT_QUOTES, 'UTF-8'); ?> (далее — «Администрация», «мы») и пользователем сети «Интернет» (далее — «Пользователь») при использовании сайта <?php echo htmlspecialchars($op['site_url'], ENT_QUOTES, 'UTF-8'); ?> (далее — «Сайт»).</p>
            <p>1.2. Сайт является информационным ресурсом для подбора и бронирования туристических услуг. Информация на Сайте не является публичной офертой, если иное прямо не указано.</p>
            <p>1.3. Используя Сайт, Пользователь подтверждает, что ознакомился с Соглашением, <a href="/frontend/window/privacy.php">Политикой конфиденциальности</a> и <a href="/frontend/window/consent.php">Согласием на обработку персональных данных</a>, и принимает их условия.</p>

            <h2>2. Регистрация и использование Сайта</h2>
            <p>2.1. Пользователь обязуется предоставлять достоверные данные при заполнении форм и не использовать Сайт в противоправных целях.</p>
            <p>2.2. Запрещается: автоматический сбор данных без разрешения; размещение вредоносного кода; действия, нарушающие работу Сайта; нарушение прав третьих лиц.</p>

            <h2>3. Бронирование туров и заключение договора</h2>
            <p>3.1. Заявка на Сайте является предварительным обращением. Договор о реализации туристического продукта заключается с туроператором/исполнителем в порядке, установленном законодательством о туристской деятельности.</p>
            <p>3.2. Администрация содействует подбору тура, консультированию и оформлению документов. Условия конкретного тура (цена, сроки, штрафы, виза) определяются договором с туроператором и подтверждаются при бронировании.</p>
            <p>3.3. Цены на Сайте носят справочный характер и могут изменяться до момента подтверждения бронирования менеджером.</p>

            <h2>4. Оплата</h2>
            <p>4.1. Оплата производится способами, указанными на Сайте или менеджером (банковская карта, перевод, в офисе).</p>
            <p>4.2. Платёжные данные банковских карт обрабатываются сертифицированными платёжными провайдерами; Администрация не хранит полные реквизиты карт.</p>

            <h2>5. Промокоды и акции</h2>
            <p>5.1. Промокоды (TRAVEL10, TRAVEL5, TRAVELAPP и др.) предоставляют скидку на тур в процентах, указанных в акции.</p>
            <p>5.2. <strong>Максимальная сумма скидки по промокоду — 5000 (пять тысяч) рублей</strong>, если иное не указано в условиях конкретной акции.</p>
            <p>5.3. Промокод одноразовый для одного пользователя, не суммируется с другими акциями, если не указано иное. Сообщите промокод менеджеру при бронировании.</p>

            <h2>6. Отмена и возврат</h2>
            <p>Условия аннуляции и возврата определяются договором с туроператором, сроками до вылета и тарифами. Подробности сообщает менеджер при бронировании.</p>

            <h2>7. Ответственность</h2>
            <p>7.1. Администрация не несёт ответственности за действия туроператоров, перевозчиков, отелей и иных третьих лиц; за форс-мажор; за временную недоступность Сайта.</p>
            <p>7.2. Администрация прилагает разумные усилия для актуальности информации, но не гарантирует отсутствие технических ошибок в каталоге туров.</p>

            <h2>8. Интеллектуальная собственность</h2>
            <p>Тексты, дизайн, логотипы и иные материалы Сайта охраняются законом. Копирование и коммерческое использование без письменного согласия Администрации запрещены.</p>

            <h2>9. Персональные данные</h2>
            <p>Обработка персональных данных регулируется <a href="/frontend/window/privacy.php">Политикой конфиденциальности</a> и <a href="/frontend/window/consent.php">Согласием на обработку ПД</a>.</p>

            <h2>10. Разрешение споров</h2>
            <p>Споры разрешаются путём переговоров. При недостижении согласия — в суде по месту нахождения Администрации в соответствии с законодательством РФ.</p>

            <h2>11. Контакты</h2>
            <ul>
                <li><?php echo htmlspecialchars($op['operator_short'], ENT_QUOTES, 'UTF-8'); ?></li>
                <li>E-mail: <a href="mailto:<?php echo htmlspecialchars($op['email'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($op['email'], ENT_QUOTES, 'UTF-8'); ?></a></li>
                <li>Телефон: <?php echo htmlspecialchars($op['phone'], ENT_QUOTES, 'UTF-8'); ?></li>
                <li>Адрес: <?php echo htmlspecialchars($op['postal_address'], ENT_QUOTES, 'UTF-8'); ?></li>
            </ul>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../../backend/components/footer.php'; ?>
</body>
</html>
