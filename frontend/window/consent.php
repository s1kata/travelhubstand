<?php
require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/config/legal.php';
session_start();
$op = th_legal_operator();
$third = th_legal_third_parties();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title>Согласие на обработку персональных данных — Travel Hub</title>
    <meta name="description" content="Согласие посетителя на обработку персональных данных на сайте travelhub63.ru">
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/frontend/css/pages/privacy.css?v=2">
    <?php include __DIR__ . '/../../backend/components/design_system_head.php'; ?>
</head>
<body class="text-slate-900">
<?php
$current_page = 'consent';
include __DIR__ . '/../../backend/components/header.php';
?>

<section class="py-16 sm:py-20 bg-gradient-to-br from-sky-50 via-blue-50 to-white">
    <div class="th-container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto">
            <h1 class="heading-font text-3xl sm:text-4xl md:text-5xl font-bold text-slate-900 mb-4">
                Согласие на обработку персональных данных
            </h1>
            <p class="text-lg text-slate-700">Посетителя (пользователя) информационного ресурса</p>
            <p class="text-sm text-slate-500 mt-2">Редакция от <?php echo htmlspecialchars($op['doc_date'], ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    </div>
</section>

<section class="py-12 sm:py-16 bg-white">
    <div class="th-container mx-auto px-4 sm:px-6 lg:px-8 max-w-4xl">
        <div class="content-section prose prose-lg max-w-none">
            <p>
                Я, посетитель (пользователь) информационного ресурса Оператора (как данный термин определён ниже),
                включая мобильные приложения и веб-сайт Оператора в информационно-коммуникационной сети «Интернет»
                (в том числе по адресу: <?php echo htmlspecialchars($op['site'], ENT_QUOTES, 'UTF-8'); ?>),
                включая их любые страницы (вне зависимости от уровня их размещения на домене) и разделы
                (далее совместно и по отдельности — «Платформа»),
                даю своё согласие <?php echo htmlspecialchars($op['operator_name'], ENT_QUOTES, 'UTF-8'); ?>
                (ОГРНИП: <?php echo htmlspecialchars($op['ogrnip'], ENT_QUOTES, 'UTF-8'); ?>,
                ИНН: <?php echo htmlspecialchars($op['inn'], ENT_QUOTES, 'UTF-8'); ?>,
                юридический адрес: <?php echo htmlspecialchars($op['legal_address'], ENT_QUOTES, 'UTF-8'); ?>,
                далее — «Оператор»)
                на обработку моих персональных данных (с использованием средств автоматизации и без таковых)
                в соответствии с
                <a href="/frontend/window/privacy.php">Политикой в отношении обработки и защиты персональных данных</a>,
                опубликованной на Платформе, и на указанных ниже условиях (далее — «Согласие»):
            </p>

            <h2 class="heading-font text-2xl font-bold">Состав обрабатываемых персональных данных</h2>
            <p>фамилия, имя, отчество; контактные номера телефонов; адрес электронной почты; город и иные сведения,
                которые я добровольно сообщаю в формах Платформы; IP-адрес; сведения об используемом веб-браузере;
                сведения о времени посещения Платформы; файлы cookie; иные сведения, которые я сообщил о себе
                (в т. ч. посредством Платформы) и которые отвечают указанным в настоящем Согласии целям обработки.
                Паспортные и иные данные для оформления тура — только если я предоставлю их Оператору отдельно
                для исполнения договора.</p>

            <h2 class="heading-font text-2xl font-bold">Цели обработки персональных данных</h2>
            <p>обработка заявок и обращений; предоставление информации о турах, услугах и акциях
                (в т. ч. рекламного характера при наличии согласия); использование функциональности Платформы;
                создание и обслуживание учётной записи; аналитика работы Сайта при согласии на cookies;
                исполнение требований законодательства РФ.</p>

            <h2 class="heading-font text-2xl font-bold">Действия с персональными данными</h2>
            <p>сбор, запись, систематизация, накопление, хранение, уточнение (обновление, изменение), извлечение,
                использование, передача (предоставление, доступ) третьим лицам на условиях, изложенных ниже,
                обезличивание, блокирование, удаление, уничтожение.</p>

            <h2 class="heading-font text-2xl font-bold">Передача персональных данных третьим лицам</h2>
            <p>Оператор вправе передать персональные данные и/или поручить их обработку в указанных выше целях третьим лицам, включая:</p>
            <ul>
                <?php foreach ($third as $t): ?>
                <li>
                    <strong><?php echo htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    (ИНН: <?php echo htmlspecialchars($t['inn'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if (!empty($t['ogrn'])): ?>, ОГРН: <?php echo htmlspecialchars($t['ogrn'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>),
                    адрес: <?php echo htmlspecialchars($t['address'], ENT_QUOTES, 'UTF-8'); ?>
                </li>
                <?php endforeach; ?>
            </ul>

            <h2 class="heading-font text-2xl font-bold">Срок действия Согласия</h2>
            <p>до достижения цели обработки персональных данных или отзыва Согласия, в зависимости от того, что наступит раньше.</p>

            <h2 class="heading-font text-2xl font-bold">Отзыв Согласия</h2>
            <p>
                Отзыв согласия может быть осуществлён предоставившим его лицом в любой момент посредством направления
                соответствующего заявления, позволяющего установить личность отправителя и содержание требования об отзыве согласия:
            </p>
            <ul>
                <li>по электронной почте: <a href="mailto:<?php echo htmlspecialchars($op['email'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($op['email'], ENT_QUOTES, 'UTF-8'); ?></a></li>
                <li>на почтовый адрес: <?php echo htmlspecialchars($op['postal_address'], ENT_QUOTES, 'UTF-8'); ?></li>
            </ul>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../../backend/components/footer.php'; ?>
</body>
</html>
