    <?php
require_once __DIR__ . '/../../backend/config/config.php';
if (isset($_COOKIE[session_name()]) && $_COOKIE[session_name()] !== '') {
    session_start();
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = 'banks_rekvesit';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title>Банковские реквизиты - Travel Hub</title>
    <link rel="icon" type="image/svg+xml" href="/frontend/favicon.svg">
    <link rel="alternate icon" href="/frontend/favicon.svg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="/frontend/css/pages/banks-rekvesit.css?v=1">
    <?php include __DIR__ . '/../../backend/components/design_system_head.php'; ?>
    </head>
<body class="bg-gradient-to-b from-sky-50/50 to-white min-h-screen">
    <?php include __DIR__ . '/../../backend/components/header.php'; ?>

    <section class="py-16 bg-gradient-to-b from-sky-50/50 to-white">
        <div class="th-container mx-auto px-4">
            <div class="max-w-3xl mx-auto">
                <div class="text-center mb-12">
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gradient-to-r from-sky-300 via-sky-400 to-sky-500 mb-6 shadow-lg shadow-sky-200/60">
                        <i class="fas fa-university text-white text-3xl"></i>
                    </div>
                    <h1 class="heading-font text-4xl font-bold text-slate-900 mb-4">Банковские реквизиты</h1>
                    <p class="text-xl text-slate-600">Реквизиты для оплаты туров и услуг</p>
                </div>

                <div class="bg-white/90 backdrop-blur-lg rounded-2xl shadow-xl border border-sky-100 p-6 md:p-8">
                    <div class="space-y-6 text-slate-700">
                        <div class="pb-4 border-b border-sky-100">
                            <p class="font-medium text-slate-900">Индивидуальный предприниматель Куликов Никита Александрович</p>
                            <p class="text-sm text-slate-500 mt-1">Номер в едином Федеральном реестре турагентов: <span class="font-semibold text-slate-700">РТА 0004877</span></p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="flex items-start gap-3">
                                <i class="fas fa-fingerprint text-sky-500 mt-0.5 w-5"></i>
                                <div>
                                    <span class="text-slate-500 text-sm">ИНН</span>
                                    <p class="font-mono font-semibold text-slate-800">631219827328</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <i class="fas fa-stamp text-sky-500 mt-0.5 w-5"></i>
                                <div>
                                    <span class="text-slate-500 text-sm">ОГРНИП</span>
                                    <p class="font-mono font-semibold text-slate-800">318631300086992</p>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="flex items-start gap-3">
                                <i class="fas fa-map-marker-alt text-sky-500 mt-0.5 w-5"></i>
                                <div>
                                    <span class="text-slate-500 text-sm">Юридический адрес</span>
                                    <p class="text-slate-800">г. Самара, ул. Степана Разина 150-2</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <i class="fas fa-building text-sky-500 mt-0.5 w-5"></i>
                                <div>
                                    <span class="text-slate-500 text-sm">Фактический адрес</span>
                                    <p class="text-slate-800">г. Самара, ул. Ново-Садовая 305А, офис 001</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3">
                                <i class="fas fa-phone text-sky-500 mt-0.5 w-5"></i>
                                <div>
                                    <span class="text-slate-500 text-sm">Телефон</span>
                                    <p class="font-semibold text-slate-800"><a href="tel:+78469550170" class="hover:text-sky-600 transition">8 846 955-01-70</a></p>
                                </div>
                            </div>
                        </div>

                        <div class="pt-6 mt-6 border-t border-sky-200">
                            <h2 class="heading-font text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-university text-sky-500"></i>
                                Банк
                            </h2>
                            <p class="font-semibold text-slate-800 mb-4">ФИЛИАЛ «НИЖЕГОРОДСКИЙ» АО «АЛЬФА-БАНК»</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="bg-sky-50/50 rounded-xl p-4 border border-sky-100">
                                    <span class="text-slate-500 text-sm">БИК</span>
                                    <p class="font-mono font-semibold text-slate-800">042202824</p>
                                </div>
                                <div class="bg-sky-50/50 rounded-xl p-4 border border-sky-100">
                                    <span class="text-slate-500 text-sm">Корреспондентский счёт</span>
                                    <p class="font-mono font-semibold text-slate-800 break-all">30101810200000000824</p>
                                </div>
                                <div class="sm:col-span-2 bg-sky-50/50 rounded-xl p-4 border border-sky-100">
                                    <span class="text-slate-500 text-sm">Расчётный счёт</span>
                                    <p class="font-mono font-semibold text-slate-800 break-all">40802810429220003049</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 text-center">
                    <a href="/index.php" class="inline-flex items-center gap-2 text-sky-600 hover:text-sky-700 font-medium transition">
                        <i class="fas fa-arrow-left"></i> На главную
                    </a>
                </div>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/../../backend/components/footer.php'; ?>
</body>
</html>