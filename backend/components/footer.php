<?php
/**
 * Единый футер Travel Hub — подключать в конце <body> перед скриптами.
 * Требует Tailwind (CDN) на странице.
 */
require_once dirname(__DIR__) . '/config/contacts.php';
$thc = th_contacts();
?>
<footer class="site-footer relative mt-auto overflow-hidden border-t border-slate-800/80 text-slate-300" style="background-color:#10102E;">
    <div class="pointer-events-none absolute inset-0" style="background:radial-gradient(ellipse 80% 50% at 50% -20%, rgba(26,26,64,0.18), transparent 55%);" aria-hidden="true"></div>
    <div class="relative mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-12 lg:flex-row lg:justify-between lg:gap-16">
            <div class="max-w-md space-y-5">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 via-indigo-600 to-indigo-900 text-white shadow-lg shadow-indigo-900/40">
                        <i class="fas fa-plane text-sm" aria-hidden="true"></i>
                    </div>
                    <span class="heading-font text-xl font-bold tracking-tight text-white sm:text-2xl">Travel Hub</span>
                </div>
                <p class="text-sm leading-relaxed text-slate-400">
                    Подбор туров и премиум‑сервис для отдыха. Работаем с проверенными туроператорами — от заявки до вылета.
                </p>
                <div class="flex flex-wrap gap-3">
                    <a href="tel:<?php echo htmlspecialchars($thc['phone_tel'], ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-[#FF6B6B] to-[#f65252] px-4 py-2.5 text-sm font-semibold text-white shadow-lg transition duration-200 hover:opacity-95">
                        <i class="fas fa-phone"></i>
                        <?php echo htmlspecialchars($thc['phone_display'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                    <a href="/frontend/window/banks_rekvesit.php" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-indigo-700 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-950/50 transition duration-200 hover:from-indigo-500 hover:to-indigo-600 hover:shadow-xl">
                        <i class="fas fa-university"></i>
                        Реквизиты
                    </a>
                </div>
                <div class="flex flex-wrap gap-2 pt-1">
                    <a href="<?php echo htmlspecialchars($thc['max_url'], ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex h-11 min-w-[2.75rem] items-center justify-center rounded-xl bg-slate-800/90 px-2 text-xs font-extrabold tracking-wide text-slate-200 transition duration-200 hover:bg-[#5B7CFF] hover:text-white" aria-label="MAX" rel="noopener noreferrer" target="_blank">MAX</a>
                    <a href="<?php echo htmlspecialchars($thc['tg_url'], ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-slate-800/90 text-slate-300 transition duration-200 hover:bg-sky-600 hover:text-white" aria-label="Telegram" rel="noopener noreferrer" target="_blank"><i class="fab fa-telegram text-lg"></i></a>
                    <a href="<?php echo htmlspecialchars($thc['vk_url'], ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-slate-800/90 text-slate-300 transition duration-200 hover:bg-[#0077FF] hover:text-white" aria-label="VK" rel="noopener noreferrer" target="_blank"><i class="fab fa-vk text-lg"></i></a>
                </div>
            </div>

            <div class="grid flex-1 grid-cols-2 gap-10 sm:grid-cols-2 lg:grid-cols-4 lg:gap-12">
                <div>
                    <h3 class="mb-4 text-xs font-bold uppercase tracking-wider text-slate-500">Компания</h3>
                    <ul class="space-y-2.5 text-sm">
                        <li><a href="/frontend/window/about.php" class="text-slate-400 transition hover:text-white">О нас</a></li>
                        <li><a href="/frontend/window/offices.php" class="text-slate-400 transition hover:text-white">Офисы</a></li>
                        <li><a href="/frontend/window/contacts.php" class="text-slate-400 transition hover:text-white">Контакты</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="mb-4 text-xs font-bold uppercase tracking-wider text-slate-500">Туры и цены</h3>
                    <ul class="space-y-2.5 text-sm">
                        <li><a href="/frontend/window/promotions.php" class="text-slate-400 transition hover:text-white">Акции</a></li>
                        <li><a href="/frontend/window/countries-list.php" class="text-slate-400 transition hover:text-white">Страны</a></li>
                        <li><a href="/frontend/window/popular-hotels.php" class="text-slate-400 transition hover:text-white">Отели</a></li>
                        <li><a href="/frontend/window/offices.php" class="text-slate-400 transition hover:text-white">Офисы</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="mb-4 text-xs font-bold uppercase tracking-wider text-slate-500">Сервис</h3>
                    <ul class="space-y-2.5 text-sm">
                        <li><a href="/frontend/window/services.php" class="text-slate-400 transition hover:text-white">Услуги</a></li>
                        <li><a href="/frontend/window/tour-calendar.php" class="text-slate-400 transition hover:text-white">Календарь дат</a></li>
                        <li><a href="https://apps.apple.com/ru/app/travelhub/id6786282632" class="text-slate-400 transition hover:text-white" target="_blank" rel="noopener noreferrer" id="th-app-install-link">Приложение iOS</a></li>
                        <li><a href="/frontend/window/for-operators.php" class="text-slate-400 transition hover:text-white">Для менеджеров</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="mb-4 text-xs font-bold uppercase tracking-wider text-slate-500">Связь</h3>
                    <ul class="space-y-2.5 text-sm">
                        <li><a href="tel:<?php echo htmlspecialchars($thc['phone_tel'], ENT_QUOTES, 'UTF-8'); ?>" class="text-slate-400 transition hover:text-white"><?php echo htmlspecialchars($thc['phone_display'], ENT_QUOTES, 'UTF-8'); ?></a></li>
                        <li><a href="mailto:<?php echo htmlspecialchars($thc['email'], ENT_QUOTES, 'UTF-8'); ?>" class="text-slate-400 transition hover:text-white"><?php echo htmlspecialchars($thc['email'], ENT_QUOTES, 'UTF-8'); ?></a></li>
                        <li class="text-slate-500"><?php echo htmlspecialchars($thc['address_short'], ENT_QUOTES, 'UTF-8'); ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="mt-12 flex flex-col items-center justify-between gap-4 border-t border-slate-800/90 pt-8 text-xs text-slate-500 sm:flex-row">
            <p>© <?php echo date('Y'); ?> Travel Hub. Все права защищены.</p>
            <div class="flex flex-wrap items-center justify-center gap-6">
                <a href="/frontend/window/consent.php" class="transition hover:text-indigo-400">Согласие на обработку ПД</a>
                <a href="/frontend/window/privacy.php" class="transition hover:text-indigo-400">Политика конфиденциальности</a>
                <a href="/frontend/window/terms.php" class="transition hover:text-indigo-400">Пользовательское соглашение</a>
            </div>
        </div>
    </div>
</footer>
<?php include __DIR__ . '/tour_booking_modal.php'; ?>
<?php include __DIR__ . '/site_feedback_modal.php'; ?>
<?php include __DIR__ . '/site_lead_bar.php'; ?>
<?php include __DIR__ . '/tour_link_scripts.php'; ?>
<?php
if (!defined('TH_SAFE_CONSOLE_INCLUDED')) {
    define('TH_SAFE_CONSOLE_INCLUDED', true);
    $_th_safe_console_path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'safe-console.js';
    $_th_safe_console_v = is_file($_th_safe_console_path) ? (string) filemtime($_th_safe_console_path) : '1';
    echo '<script src="/frontend/js/safe-console.js?v=' . htmlspecialchars($_th_safe_console_v, ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
}
?>
<?php
if (!defined('TH_CONVERSION_BOOST_INCLUDED')) {
    define('TH_CONVERSION_BOOST_INCLUDED', true);
    $_th_cb_css = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'th-conversion-boost.css';
    $_th_cb_js = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'th-conversion-boost.js';
    $_th_cb_css_v = is_file($_th_cb_css) ? (string) filemtime($_th_cb_css) : '1';
    $_th_cb_js_v = is_file($_th_cb_js) ? (string) filemtime($_th_cb_js) : '1';
    echo '<link rel="stylesheet" href="/frontend/css/th-conversion-boost.css?v=' . htmlspecialchars($_th_cb_css_v, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '<script src="/frontend/js/th-conversion-boost.js?v=' . htmlspecialchars($_th_cb_js_v, ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
}
if (!defined('TH_PROMO_APPLY_INCLUDED')) {
    define('TH_PROMO_APPLY_INCLUDED', true);
    $_th_promo_apply_path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'th-promo-apply.js';
    $_th_promo_apply_v = is_file($_th_promo_apply_path) ? (string) filemtime($_th_promo_apply_path) : '1';
    echo '<script src="/frontend/js/th-promo-apply.js?v=' . htmlspecialchars($_th_promo_apply_v, ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
}
if (!defined('TH_PROMO_TIMER_INCLUDED')) {
    define('TH_PROMO_TIMER_INCLUDED', true);
    $_th_promo_timer_path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'promo-timer.js';
    $_th_promo_timer_v = is_file($_th_promo_timer_path) ? (string) filemtime($_th_promo_timer_path) : '1';
    echo '<script src="/frontend/js/promo-timer.js?v=' . htmlspecialchars($_th_promo_timer_v, ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
}
?>
<?php
require_once __DIR__ . '/yandex_metrika.php';
th_yandex_metrika_print_snippet();
?>
